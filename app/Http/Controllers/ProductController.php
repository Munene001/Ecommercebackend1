<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Exception;
use Illuminate\Http\JsonResponse;
use PhpParser\Node\Stmt\TryCatch;

use function Laravel\Prompts\error;

class ProductController extends Controller
{
    public function  showSpecificProduct($ProductUuid)
    {
        try {
            $product = Product::with(["productdescriptions", "images"])->where('product_id', $ProductUuid)->firstOrFail();
            return response()->json([
                'product' => $product,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Product is not found'], 404);
            //throw $th;
        } catch (Exception $e) {
            return response()->json(['error' => 'Something went wrong'], 500);
        }
    }
    public function search(Request $request)
    {
        $query = $request->input('query');
        $categoryUuid = $request->input('category_id');
        $products = Product::where(function ($q) use ($query) {
            $q->where('productname', 'like', '%' . $query . '%')
                ->orWhere('description', 'like', '%' . $query . '%')
                ->orWhere('price', 'like', '%' . $query . '%');
        });

        if ($categoryUuid) {
            $products->whereHas('categories', function ($q) use ($categoryUuid) {
                $q->where('category_id', $categoryUuid);
            });
        }
        return response()->json($products->get());
    }

    //
}
