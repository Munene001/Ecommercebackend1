<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Product;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class ReviewController extends Controller
{
    public function review(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'product_id' => 'required_without:parent_id|string|exists:Products,product_id',
            'rating' => 'required_without:parent_id|integer|between:1,5',
            'comment' => 'nullable|string|max:1000',
            'parent_id' => 'nullable|exists:Reviews,review_id',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $data = $request->only(['product_id', 'rating', 'comment', 'parent_id']);
        $data['user_id'] = $user->user_id;
        if (isset($data['parent_id']) && $data['parent_id']) {
            $data['rating'] = null;
            $data['product_id'] = null;
        }
        $review = Review::create($data);
        return response()->json(
            [
                'message' => 'Review submitted successfully',
                'review' => $review->load('user', 'replies'),
            ],
            201
        );
    }
    public function index(Request $request, $product_id)
    {
        $product = Product::findOrFail($product_id);
        $reviews = $product->reviews()->with(['user', 'replies.user'])->get();

        return response()->json([
            'reviews' => $reviews,
            'average_rating' => $product->average_rating,
            'review_count' => $product->review_count,

        ], 200);
    }


    //
}
