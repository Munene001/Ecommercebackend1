<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shop;

class ShopController extends Controller
{
    public function getProducts($shop_id)
    {
        $shop = Shop::with([
            'products.images',
            'products.productvariants',
            'products.productdescriptions'
        ])->findOrFail($shop_id);
        return response()->json($shop->products);
    }
}
