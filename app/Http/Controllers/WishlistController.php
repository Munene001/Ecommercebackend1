<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpParser\Node\Stmt\TryCatch;

class WishlistController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        try {
            $wishlist = $user->wishlist->pluck('product_id');
            return response()->json(['wishlist' => $wishlist], 200);
            //code...
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch wishlist'], 500);
            //throw $th;
        }
    }
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|string|exists:Products,product_id',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Only buyers can modify the wishlist'], 403);
        }
        try {
            $existing = Wishlist::where('user_id', $user->user_id)
                ->where('product_id', $request->product_id)
                ->exists();
            if ($existing) {
                return response()->json(['message' => 'Product already in wishlist'], 200);
            }
            $wishlist = Wishlist::create([
                'user_id' => $user->user_id,
                'product_id' => $request->product_id,
            ]);
            return response()->json([
                'message' => 'Product added to wishlist',
                'product_id' => $wishlist->product_id
            ], 201);



            //code...
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to add to wishlist'], 500);
            //throw $th;
        }
    }
    public function destroy($product_id): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Only authrized can modify the wishlist'], 403);
        }

        try {
            $deleted = Wishlist::where('user_id', $user->user_id)
                ->where('product_id', $product_id)
                ->delete();
            if (!$deleted) {
                return response()->json(['error' => 'Product not found in wishlist'], 404);
            }
            return response()->json(['message' => 'Product removed from wishlist'], 200);
            //code...
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed toremove from wishlist'], 500);
            //throw $th;
        }
    }

    //
}
