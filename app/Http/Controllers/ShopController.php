<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ShopController extends Controller
{
    public function showProducts($ShopUuid)
    {
        try {
            $shop = Shop::where('shop_id', $ShopUuid)->firstOrFail();
            $products = $shop->products()->with(['productvariants', 'productdescriptions', 'images'])->get();
            return response()->json([
                'shop' => $shop,
                'products' => $products,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Shop not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred. Please try again later.'], 500);
        }
    }
}
