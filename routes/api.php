<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RecentlyViewedController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\WishlistController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
*/

// Public Authentication Routes
Route::post('/register', [RegisteredUserController::class, 'store'])->name('register');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login');
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{product_id}', [WishlistController::class, 'destroy']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/reviews', [ReviewController::class, 'review'])->middleware('throttle:10,1');
});
Route::get('/products/{product_id}/reviews', [ReviewController::class, 'index']);



// Other Public Routes
Route::get('/test', function () {
    return response()->json(['message' => 'API is working!']);
});
Route::get('shop/{ShopUuid}/products', [ShopController::class, 'showProducts']);
Route::get('/product/{ProductUuid}', [ProductController::class, 'showSpecificProduct']);
Route::get('/products/search', [ProductController::class, 'search']);
Route::post('/products/filter', [ProductController::class, 'filter']);
Route::get('products/categories', [ProductController::class, 'categories']);
Route::post('/recentlyviewed/track', [RecentlyViewedController::class, 'track']);
Route::get('/recentlyviewed/index', [RecentlyViewedController::class, 'index']);
Route::post('/products/bulk', [ProductController::class, 'showMultipleProducts']);

// Google Auth Routes
Route::prefix('auth/google')->group(function () {
    Route::get('/redirect', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('/callback', [GoogleController::class, 'callback'])->name('google.callback');
});
