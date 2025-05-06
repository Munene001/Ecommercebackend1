<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\Guest;
use App\Models\ProductSizes;
use App\Models\Sale;
use App\Models\SaleItem;
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
    /**
     * Show checkout form (modified for localStorage)
     * Now doesn't need to retrieve cart from session since it comes from client
     */
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
            // Removed cart from response since it's client-managed
        ]);
    }

    /**
     * Process checkout (modified for localStorage)
     * Now accepts cart data directly in request instead of using session
     */
    public function processCheckout(Request $request)
    {
        // Added validation for cart data
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
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Get cart directly from request instead of session
        $cart = $request->input('cart');
        if (empty($cart)) {
            return response()->json(['error' => 'Cart is empty'], 400);
        }

        DB::beginTransaction();
        try {
            // Calculate total amount from cart items
            $totalAmount = array_sum(array_map(
                fn($item) => ($item['discountprice'] ?? $item['price']) * $item['quantity'],
                $cart
            ));

            // Handle guest checkout
            $guest = null;
            if (!Auth::check()) {
                $guest = Guest::firstOrCreate(
                    ['phone' => $request->input('phone')],
                    [
                        'name' => $request->input('name'),
                        'email' => $request->input('email'),
                        'location' => $request->input('location')
                    ]
                );
            }

            // Create new sale record
            $sale = new Sale();
            $sale->sale_id = Str::uuid()->toString();
            $sale->shop_id = $request->input('shop_id');
            $sale->total_amount = $totalAmount;
            $sale->payment_method = 'mpesa';
            $sale->status = 'pending';
            $sale->tenant_id = Shop::find($request->input('shop_id'))->tenant_id;
            $sale->user_id = Auth::id();
            $sale->guest_id = $guest ? $guest->guest_id : null;

            // Update user details if authenticated
            if (Auth::check()) {
                $user = Auth::user();
                if (!$user->phone || !$user->location) {
                    $user->phone = $request->input('phone');
                    $user->location = $request->input('location');
                    $user->save();
                }
            }

            // Process each cart item and reserve stock
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

            // Create sale items
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

            // Schedule stock restoration job in case payment fails
            dispatch(new RestoreStockJob($sale->sale_id, $reservedItems))
                ->delay(Carbon::now()->addMinutes(5));

            // Initiate M-Pesa payment
            $mpesaResponse = $this->initiateMpesaPayment($sale, $request->input('phone'));
            $sale->mpesa_transaction_id = $mpesaResponse['CheckoutRequestID'] ?? null;
            $sale->save();

            DB::commit();

            // Removed session cart clearing since cart is client-managed
            return response()->json([
                'success' => true,
                'sale_id' => $sale->sale_id,
                'message' => 'Order placed successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Checkout failed: ' . $e->getMessage());

            // Restore stock if error occurs
            foreach ($cart as $item) {
                ProductSizes::where('size_id', $item['size_id'])
                    ->increment('stock_quantity', $item['quantity']);
            }

            return response()->json(['error' => 'Checkout failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Initiate M-Pesa payment (unchanged)
     */
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
        if ($authResponse->failed()) {
            throw new \Exception('Failed to authenticate with M-Pesa: ' . $authResponse->body());
        }

        $accessToken = $authResponse->json()['access_token'];
        $stkUrl = config('services.mpesa.environment') === 'sandbox'
            ? 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
            : 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

        $timestamp = now()->format('YmdHis');
        $password = base64_encode($shortcode . $shop->mpesa_passkey . $timestamp);
        $callbackUrl = config('services.mpesa.callback_url');

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
            'AccountReference' => 'Sale#' . $sale->sale_id,
            'TransactionDesc' => 'Payment for Sale ' . $sale->sale_id,
        ];

        $response = Http::withToken($accessToken)->post($stkUrl, $payload);
        if ($response->failed()) {
            throw new \Exception('M-Pesa STK Push failed: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Handle M-Pesa callback (unchanged)
     */
    public function handleMpesaCallback(Request $request)
    {
        $data = $request->input('Body.stkCallback');
        $transactionId = $data['CheckoutRequestID'];
        $resultCode = $data['ResultCode'];
        $resultDesc = $data['ResultDesc'];

        $sale = Sale::where('mpesa_transaction_id', $transactionId)->first();
        if (!$sale) {
            Log::error('Sale not found for M-Pesa transaction: ' . $transactionId);
            return response()->json(['error' => 'Sale not found'], 404);
        }

        if ($resultCode == 0) {
            $sale->status = 'completed';
            $sale->save();
        } else {
            $sale->status = 'failed';
            $sale->save();
            foreach ($sale->saleItems as $item) {
                ProductSizes::where('size_id', $item->size_id)
                    ->increment('stock_quantity', $item->quantity);
            }
            Log::warning('M-Pesa transaction failed: ' . $resultDesc);
        }

        return response()->json(['success' => true]);
    }
}
