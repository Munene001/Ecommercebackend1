<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RecentlyViewedController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/



Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
Route::get('shop/{ShopUuid}/products', [ShopController::class, 'showProducts']);
Route::get('product/{ProductUuid}', [ProductController::class, 'showSpecificProduct']);
Route::get('/test', function () {
    return response()->json(['message' => 'API is working!']);
});
Route::get('/products/search', [ProductController::class, 'search']);
Route::post('/products/filter', [ProductController::class, 'filter']);
Route::get('products/categories', [ProductController::class, 'categories']);
Route::post('/recentlyviewed/track', [RecentlyViewedController::class, 'track']); // No auth required
Route::get('/recentlyviewed/index', [RecentlyViewedController::class, 'index']);
Route::prefix('auth/google')->group(function () {
    Route::get('/redirect', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('/callback', [GoogleController::class, 'callback'])->name('google.callback');
});
