<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RecentlyViewed;
use App\Models\Product;

class RecentlyViewedController extends Controller
{
    public function track(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:Products,product_id',
            'browser_id' => 'required|string|max:64'
        ]);

        $browserId = $request->browser_id;
        $productId = $request->product_id;

        // Update or create the record
        RecentlyViewed::updateOrCreate(
            ['browser_id' => $browserId, 'product_id' => $productId],
            ['viewed_at' => now()]
        );

        // Get the cutoff date (4th most recent)
        $cutoff = RecentlyViewed::where('browser_id', $browserId)
            ->orderBy('viewed_at', 'desc')
            ->skip(3)
            ->value('viewed_at');

        // Delete older records if cutoff exists
        if ($cutoff) {
            RecentlyViewed::where('browser_id', $browserId)
                ->where('viewed_at', '<', $cutoff)
                ->delete();
        }

        return response()->json(['success' => true], 200);
    }
    public function index(Request $request)
    {
        $browserId = $request->cookie('browser_id') ?? $request->header('X-Browser-ID');
        if (!$browserId) {
            return response()->json([], 200);
        }
        $recentViews = RecentlyViewed::where('browser_id', $browserId)
            ->orderBy('viewed_at', 'desc')
            ->limit(4)
            ->with(['product' => fn($query) => $query->with('images')])
            ->get();
        $products = $recentViews->pluck('product')->filter()->values();
        return response()->json($products, 200);
    }
}
