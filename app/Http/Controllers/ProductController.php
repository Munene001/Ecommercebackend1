<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Shop;
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
        $categoryUuid = $request->input('categoryUuid');

        $products = Product::with(["images"]);


        if ($query) {
            $products->where(function ($q) use ($query) {
                $q->where('productname', 'like', '%' . $query . '%')
                    ->orWhere('description', 'like', '%' . $query . '%')
                    ->orWhere('price', 'like', '%' . $query . '%');
            });
        }

        if ($categoryUuid) {
            $products->whereHas('categories', function ($q) use ($categoryUuid) {
                $q->where('Product_categories.category_id', $categoryUuid);
            });
        }
        return response()->json($products->get());
    }

    protected function filterByCategories($query, $categoryUuids)
    {

        if ($categoryUuids && is_array($categoryUuids)) {
            $query->whereHas('categories', function ($q) use ($categoryUuids) {
                $q->whereIn('Product_categories.category_id', $categoryUuids);
            });
        }
        return $query;
    }
    protected function filterBySizes($query, $sizes)
    {
        if ($sizes && is_array($sizes)) {
            $query->whereHas('productdescriptions', function ($q) use ($sizes) {
                $q->where(function ($query) use ($sizes) {
                    foreach ($sizes as $size) {
                        $query->orWhere('additional_information', 'like', '%' . $size . '%');
                    }
                });
            });
        }
        return $query;
    }
    protected function filterByColors($query, $colors)
    {
        if ($colors && is_array($colors)) {
            $query->where(function ($q) use ($colors) {
                foreach ($colors as $color) {
                    $q->orWhere('productname', 'like', '%' . $color . '%');
                }
            });
        }
        return $query;
    }
    protected function filterByPriceRange($query, $minPrice, $maxPrice)
    {
        $query->where(function ($q) use ($minPrice, $maxPrice) {
            $q->where(function ($query) use ($minPrice, $maxPrice) {
                $query->whereNotNull('discountprice')
                    ->whereBetween('discountprice', [$minPrice, $maxPrice]);
            })->orWhere(function ($query) use ($minPrice, $maxPrice) {
                $query->whereNull('discountprice')
                    ->whereBetween('price', [$minPrice, $maxPrice]);
            });
        });
        return $query;
    }
    public function filter(Request $request)
    {
        $categoryUuids = $request->input('categoryUuids');
        $sizes = $request->input('sizes');
        $colors = $request->input('colors');
        $minPrice = $request->input('minPrice', 0);
        $maxPrice = $request->input('maxPrice', 20000);
        $page = $request->input('page', 1); // Get page number, default to 1

        $products = Product::with(['images', 'categories', 'productdescriptions']);
        $products = $this->filterByCategories($products, $categoryUuids);
        $products = $this->filterBySizes($products, $sizes);
        $products = $this->filterByColors($products, $colors);
        $products = $this->filterByPriceRange($products, $minPrice, $maxPrice);

        $results = $products->paginate(16, ['*'], 'page', $page);

        return response()->json([
            'products' => $results->items(),
            'total' => $results->total(),
            'current_page' => $results->currentPage(),
            'last_page' => $results->lastPage(),
        ]);
    }

    public function categories(Request $request)
    {
        // Make shop_id required or optional with a default
        $shopId = $request->query('shop_id'); // No default, require from frontend
        if (!$shopId) {
            return response()->json(['error' => 'shop_id is required'], 400);
        }
        $categories = Category::where('shop_id', $shopId)
            ->select('category_id', 'categoryname')
            ->get();
        return response()->json($categories);
    }


    //
}
