<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\Guest;
use App\Models\ProductSizes;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\MpesaTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Jobs\RestoreStockJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class CheckoutController extends Controller
{
    public function showCheckoutForm(Request $request)
    {
        $user = Auth::user();
        $checkoutData = [
            'name' => $user ? $user->username : '',
            'phone' => $user ? $user->phone : '',
            'email' => $user ? $user->email : '',
            'location' => $user ? $user->location : '',
            'is_authenticated' => $user !== null,
        ];

        return response()->json([
            'checkoutData' => $checkoutData,
        ]);
    }

    protected function validateAndProcessCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'location' => 'required|string|max:255',
            'shop_id' => 'required|exists:Shops,shop_id',
            'cart' => 'required|array|min:1',
            'cart.*.product_id' => 'required|exists:Products,product_id',
            'cart.*.size_id' => 'required|exists:Product_sizes,size_id',
            'cart.*.quantity' => 'required|integer|min:1',
            'cart.*.price' => 'required|numeric|min:0',
            'cart.*.discountprice' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return ['error' => ['errors' => $validator->errors()], 'status' => 422];
        }

        $cart = $request->input('cart');
        if (empty($cart)) {
            return ['error' => ['error' => 'Cart is empty'], 'status' => 400];
        }

        $totalAmount = array_sum(array_map(
            fn($item) => ($item['discountprice'] ?? $item['price']) * $item['quantity'],
            $cart
        ));

        return ['cart' => $cart, 'totalAmount' => $totalAmount];
    }

    protected function processSale($cart, $totalAmount, $request, $user = null, $guest = null)
    {
        DB::beginTransaction();
        try {
            $sale = new Sale();
            $sale->sale_id = Str::uuid()->toString();
            $sale->shop_id = $request->input('shop_id');
            $sale->total_amount = $totalAmount;
            $sale->payment_method = 'mpesa';
            $sale->status = 'pending';
            $sale->tenant_id = Shop::find($request->input('shop_id'))->tenant_id;
            $sale->user_id = $user?->user_id;
            $sale->guest_id = $guest?->guest_id;

            $reservedItems = [];
            foreach ($cart as $item) {
                $productSize = ProductSizes::where('size_id', $item['size_id'])->lockForUpdate()->first();
                if (!$productSize || $productSize->stock_quantity < $item['quantity']) {
                    throw new \Exception('Insufficient stock for product size ID: ' . $item['size_id']);
                }
                $productSize->stock_quantity -= $item['quantity'];
                $productSize->save();
                $reservedItems[] = [
                    'size_id' => $item['size_id'],
                    'quantity' => $item['quantity']
                ];
            }

            $sale->save();

            foreach ($cart as $item) {
                $saleItem = new SaleItem();
                $saleItem->saleitem_id = Str::uuid()->toString();
                $saleItem->sale_id = $sale->sale_id;
                $saleItem->product_id = $item['product_id'];
                $saleItem->size_id = $item['size_id'];
                $saleItem->quantity = $item['quantity'];
                $saleItem->price = $item['discountprice'] ?? $item['price'];
                $saleItem->save();
            }

            dispatch(new RestoreStockJob($sale->sale_id, $reservedItems))
                ->delay(Carbon::now()->addMinutes(1));

            $mpesaResponse = $this->initiateMpesaPayment($sale, $request->input('phone'));

            DB::commit();

            return response()->json([
                'success' => true,
                'sale_id' => $sale->sale_id,
                'message' => 'Order placed successfully',
                'user_updated' => $user ? [
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'location' => $user->location
                ] : null
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Checkout failed: ' . $e->getMessage());
            foreach ($cart as $item) {
                ProductSizes::where('size_id', $item['size_id'])
                    ->increment('stock_quantity', $item['quantity']);
            }
            return response()->json(['error' => 'Checkout failed: ' . $e->getMessage()], 500);
        }
    }

    public function processUserCheckout(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $cartData = $this->validateAndProcessCart($request);
        if (isset($cartData['error'])) {
            return response()->json($cartData['error'], $cartData['status']);
        }

        $user->phone = $request->input('phone');
        $user->email = $request->input('email') ?? $user->email;
        $user->location = $request->input('location');
        $user->save();

        return $this->processSale($cartData['cart'], $cartData['totalAmount'], $request, $user);
    }

    public function processGuestCheckout(Request $request)
    {
        $cartData = $this->validateAndProcessCart($request);
        if (isset($cartData['error'])) {
            return response()->json($cartData['error'], $cartData['status']);
        }

        $guest = Guest::firstOrCreate(
            ['phone' => $request->input('phone')],
            [
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'location' => $request->input('location')
            ]
        );

        return $this->processSale($cartData['cart'], $cartData['totalAmount'], $request, null, $guest);
    }

    protected function initiateMpesaPayment(Sale $sale, string $phone)
    {
        $shop = $sale->shop;
        $paymentMethod = $shop->mpesa_payment_method;
        $shortcode = null;
        $transactionType = null;

        if ($paymentMethod === 'paybill') {
            $shortcode = $shop->mpesa_paybill_number;
            $transactionType = 'CustomerPayBillOnline';
        } elseif ($paymentMethod === 'till') {
            $shortcode = $shop->mpesa_till_number;
            $transactionType = 'CustomerBuyGoodsOnline';
        } elseif ($paymentMethod === 'send_money') {
            $shortcode = $shop->mpesa_business_shortcode;
            $transactionType = 'CustomerPayBillOnline';
        } else {
            throw new \Exception('Invalid M-Pesa payment method for shop ID: ' . $shop->shop_id);
        }

        if (!$shortcode || !$shop->mpesa_passkey || !$shop->mpesa_consumer_key || !$shop->mpesa_consumer_secret) {
            throw new \Exception('M-Pesa credentials incomplete for shop ID: ' . $shop->shop_id);
        }

        $phone = preg_replace('/^(?:\+254|0)/', '254', $phone);
        $consumerKey = $shop->mpesa_consumer_key;
        $consumerSecret = $shop->mpesa_consumer_secret;
        $authUrl = config('services.mpesa.environment') === 'sandbox'
            ? 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
            : 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $authResponse = Http::withBasicAuth($consumerKey, $consumerSecret)->get($authUrl);
        Log::info('OAuth Request URL: ' . $authUrl);
        Log::info('OAuth Response: ', $authResponse->json());
        Log::info('OAuth Status: ' . $authResponse->status());
        if ($authResponse->failed()) {
            Log::error('OAuth Error: ' . $authResponse->body());
            throw new \Exception('Failed to authenticate with M-Pesa: ' . $authResponse->body());
        }

        $accessToken = $authResponse->json()['access_token'];
        $stkUrl = config('services.mpesa.environment') === 'sandbox'
            ? 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
            : 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

        $timestamp = now()->format('YmdHis');
        $password = base64_encode($shortcode . $shop->mpesa_passkey . $timestamp);
        $callbackUrl = config('services.mpesa.callback_url');

        $mpesaTransaction = new MpesaTransaction([
            'transaction_id' => Str::uuid()->toString(),
            'sale_id' => $sale->sale_id,
            'amount' => $sale->total_amount,
            'phone' => $phone,
            'shop_id' => $shop->shop_id,
            'status' => 'pending',
        ]);
        $mpesaTransaction->save(); // Save transaction before STK Push

        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => $transactionType,
            'Amount' => (int) $sale->total_amount,
            'PartyA' => $phone,
            'PartyB' => $shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => 'dukatech',
            'TransactionDesc' => 'Sale',
        ];

        $response = Http::withToken($accessToken)->post($stkUrl, $payload);
        Log::info('STK Push Request: ', ['url' => $stkUrl, 'payload' => $payload]);
        Log::info('STK Push Response: ', $response->json());
        Log::info('STK Push Status: ' . $response->status());

        if ($response->failed()) {
            Log::error('STK Push Error: ' . $response->body());
            $mpesaTransaction->status = 'failed';
            $mpesaTransaction->result_desc = $response->body();
            $mpesaTransaction->save();
            throw new \Exception('M-Pesa STK Push failed: ' . $response->body());
        }

        $mpesaTransaction->mpesa_checkout_request_id = $response->json()['CheckoutRequestID'] ?? null;
        $mpesaTransaction->save();

        return $response->json();
    }

    public function handleMpesaCallback(Request $request)
    {
        Log::info('Callback Received: ', $request->all());
        $data = $request->input('Body.stkCallback');
        if (!$data || !isset($data['CheckoutRequestID'], $data['ResultCode'], $data['ResultDesc'])) {
            Log::error('Invalid callback data: No stkCallback found');
            return response()->json(['error' => 'Invalid callback data'], 400);
        }

        $transactionId = $data['CheckoutRequestID'];
        Log::info('Searching for transaction:', ['CheckoutRequestID' => $transactionId]);
        $resultCode = $data['ResultCode'];
        $resultDesc = $data['ResultDesc'];

        // Retry logic to handle race condition
        $attempts = 3;
        $delay = 1; // seconds
        $mpesaTransaction = null;

        while ($attempts > 0) {
            $mpesaTransaction = MpesaTransaction::where('mpesa_checkout_request_id', $transactionId)->first();
            if ($mpesaTransaction) {
                break;
            }
            Log::info('Transaction not found, retrying...', ['attempts_left' => $attempts, 'CheckoutRequestID' => $transactionId]);
            sleep($delay);
            $attempts--;
        }

        if (!$mpesaTransaction) {
            Log::error('M-Pesa transaction not found after retries', [
                'CheckoutRequestID' => $transactionId,
                'all_transactions' => MpesaTransaction::pluck('mpesa_checkout_request_id')->toArray()
            ]);
            return response()->json(['error' => 'M-Pesa transaction not found'], 404);
        }

        $mpesaTransaction->status = $resultCode == 0 ? 'completed' : 'failed';
        $mpesaTransaction->result_desc = $resultDesc;
        $mpesaTransaction->save();

        $sale = $mpesaTransaction->sale;
        if ($sale) {
            $sale->status = $resultCode == 0 ? 'completed' : 'failed';
            $sale->save();
            Log::info('Sale updated: ', ['sale_id' => $sale->sale_id, 'status' => $sale->status]);
        } else {
            Log::warning('Sale not found for M-Pesa transaction: ', ['transaction_id' => $transactionId, 'sale_id' => $mpesaTransaction->sale_id]);
        }

        return response()->json(['success' => true]);
    }
}
