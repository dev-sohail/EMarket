<?php

use App\Http\Controllers\Ajax\CartController;
use App\Http\Controllers\Ajax\CheckoutController;
use App\Http\Controllers\Ajax\ProductController;
use App\Http\Controllers\Ajax\ReturnController;
use App\Http\Controllers\Ajax\ReviewController;
use App\Http\Controllers\Ajax\WishlistController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->prefix('ajax')->group(function () {
    // Cart Routes
    Route::post('/cart/add', [CartController::class, 'add'])->name('ajax.cart.add');
    Route::post('/cart/update', [CartController::class, 'update'])->name('ajax.cart.update');
    Route::post('/cart/remove', [CartController::class, 'remove'])->name('ajax.cart.remove');
    Route::get('/cart/count', [CartController::class, 'count'])->name('ajax.cart.count');
    Route::get('/cart/summary', [CartController::class, 'summary'])->name('ajax.cart.summary');

    // Wishlist Routes
    Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->name('ajax.wishlist.toggle');

    // Product Routes
    Route::get('/products/search', [ProductController::class, 'search'])->name('ajax.products.search');
    Route::get('/products/filter', [ProductController::class, 'filter'])->name('ajax.products.filter');
    Route::get('/products/variants', [ProductController::class, 'variants'])->name('ajax.products.variants');

    // Checkout Routes
    Route::post('/checkout/calculate', [CheckoutController::class, 'calculate'])->name('ajax.checkout.calculate');
    Route::post('/checkout/apply-coupon', [CheckoutController::class, 'applyCoupon'])->middleware('throttle:10,1')->name('ajax.checkout.apply-coupon');

    // Review Routes
    Route::post('/reviews', [ReviewController::class, 'store'])->name('ajax.reviews.store');

    // Return Routes
    Route::post('/returns', [ReturnController::class, 'store'])->name('ajax.returns.store');
});
