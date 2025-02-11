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
            $product = Product::with(["productdescriptions", "productvariants", "images"])->where('product_id', $ProductUuid)->firstOrFail();
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
    //
}
