<?php

use App\Http\Controllers\CategoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ProductController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::get('shop/{ShopUuid}/products', [ShopController::class, 'showProducts']);
Route::get('product/{ProductUuid}', [ProductController::class, 'showSpecificProduct']);
Route::get('/test', function () {
    return response()->json(['message' => 'API is working!']);
});
Route::get('/products/search', [ProductController::class, 'search']);
