<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;

class CategoryController extends Controller
{
    public function showCategoryProducts($CategoryUuid)
    {

        try {
            $category = Category::with(['products.productvariants', 'products.productdescriptions', 'products.images'])->where('category_id', $CategoryUuid)->first();
            if (!$category) {
                return response()->json(['error' => 'Category not found'], 404);
            }
            $products = $category->products;
            return response()->json([
                'products' => $products,

            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'please try again later'], 500);
        }
    }
}
