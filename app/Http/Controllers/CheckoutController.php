<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\Guest;
use App\Models\ProductSizes;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\log;
use App\Jobs\RestoreStockJob;
use Carbon\Carbon;


class CheckoutController extends Controller
{
    public function showCheckoutForm(Request $request)
    {
        $cart = $request->session()->get('cart', []);
        $shop = Shop::find($request->input('shop_id'));

        if (empty($cart)) {
            return response()->json(['error' => 'Cart is emplty'], 400);
        }
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
            'cart' => $cart
        ]);
    }
    public function processCheckout(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'location' => 'required|string|max:255',
            'shop_id' => 'required|exists:Shops,shop_id',
        ]);
        $cart = $request->session()->get('cart', []);
        if (empty($cart)) {
            return response()->json(['error' => 'Cart is empty'], 400);
        }
        DB::beginTransaction();
        try {
            $totalAmount = array_sum(array_map(fn($item) => ($item['discountprice'] ?? $item['price']) * $item['quantity'], $cart));
            $guest = null;
            if (!Auth::check()) {
                $guest = Guest::firstOrCreate(
                    ['phone' => $request->input('phone')],
                    ['name' => $request->input('phone'), 'email' => $request->input('email'), 'location' => $request->input('location')]

                );
            }
            $sale = new Sale();
            $sale->sale_id = Str::uuid()->toString();
            $sale->shop_id = $request->input('shop_id');
            $sale->total_amount = $totalAmount;
            $sale->payment_method = 'mpesa';
            $sale->status = 'pending';
            $sale->tenant_id = Shop::find($request->input('shop_id'))->tenant_id;
            $sale->user_id = Auth::id();
            $sale->guest_id = $guest ? $guest->guest_id : null;

            if (Auth::check()) {
                $user = Auth::user();
                if (!$user->phone || !$user->location) {
                    $user->phone = $request->input('phone');
                    $user->location = $request->input('location');
                    $user->save();
                }
            }
            $reservedItems = [];
            foreach ($cart as $item) {
                $productSize = ProductSizes::where('size_id', $item['size_id'])->lockForUpdate()->first();
                if (!$productSize || $productSize->stock_quantity < $item['quantity']) {
                    throw new \Exception('Insufficient stock for product size ID:' . $item['size_id']);
                }
                $productSize->stock_quantity -= $item['quantity'];
                $productSize->save();
                $reservedItems[] = [
                    'size_id' => $item['size_id'],
                    'quantity' => $item['quantity']
                ];
            }
            $sale->save();
            foreach ($cart as  $item) {
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
                ->delay(Carbon::now()->addMinutes(5));
            $mpesaResponse = $this->initiateMpesaPayment($sale, $request->input('phone'));
            $sale->mpesa_transaction_id = $mpesaResponse['CheckoutRequestID'] ?? null;
            $sale->save();
            $request->session()->forget('cart');
            DB::commit();
            return response()->json(['success' => true, 'sale_id' => $sale->sale_id]);
        } catch (\Exception $e) {
        }
        DB::rollBack();
        Log::error('Checkout failed:' . $e->getMessage());
        foreach ($cart as $item) {
            ProductSizes::where(
                'size_id',
                $item['size_id']
            )->increment('stock_quantity', $item['quantity']);
        }
        return response()->json(['error' => 'Checkout failed: ' . $e->getMessage()], 500);
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
        } else if ($paymentMethod === 'phone') {
            $shortcode = $shop->mpesa_business_shortcode;
            $transactionType = 'CustomerPayBillOnline';
        } else {
            throw new \Exception('Invalid Mpesa Payment method for shop ID ' . $shop->shop_id);
        }

        if (!$shortcode || !$shop->mpesa_passkey || !$shop->mpesa_consumer_key || !$shop->mpesa_consumer_secret) {
            throw new \Exception('M-pesa credentials incomplete for shop ID ' . $shop->shop_id);
        }
        $phone = preg_replace('/^(?:\+254|0)/', '254', $phone);
        $consumerKey = $shop->mpesa_consumer_key;
        $consumerSecret = $shop->mpesa_consumer_secret;
        $authUrl = config('services.mpesa.environment') === 'sandbox' ? 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
            : 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
        $authResponse = Http::withBasicAuth(
            $consumerKey,
            $consumerSecret
        )->get($authUrl);
        if ($authResponse->failed()) {
            throw new \Exception('Failed to authenticate with M-pesa: ' . $authResponse->body());
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
            throw new \Exception('M-pesa STK Push failed: ' . $response->body());
        }
        return $response->json();
    }
    public function HandleMpesaCallback(Request $request)
    {
        $data = $request->input('Body.stkCallback');
        $transactionId = $data['CheckoutRequestID'];
        $resultCode = $data['ResultCode'];
        $resultDesc = $data['ResultDesc'];
        $sale = Sale::where('mpesa_transaction_id', $transactionId)->first();
        if (!$sale) {
            Log::error('Sale not found for Mpesa transaction: ' . $transactionId);
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
            Log::warning('Mpesa transaction failed: ' . $resultDesc);
        }
        return response()->json(['success' => true]);
    }
    //
}
