<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\CategoryControllerAdmin;
use App\Http\Controllers\Api\Admin\DashboardControllerAdmin;
use App\Http\Controllers\Api\Admin\ProductControllerAdmin;
use App\Http\Controllers\Api\Admin\OrderControllerUserAdmin;

use App\Http\Controllers\Api\Users\ProductControllerUser;
use App\Http\Controllers\Api\Users\OrderControllerUser;
use App\Http\Controllers\Api\Users\ProductReviewController;

use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Users\CategoryControllerUser;




Route::post('/register', [RegisterController::class, '__invoke'])->name('register');
Route::post('/register', [LoginController::class, '__invoke'])->name('login');




// Kategori Routes
Route::get('categories', [CategoryControllerUser::class, 'index']);        // GET: Tampilkan semua kategori
Route::get('categories/{id}', [CategoryControllerUser::class, 'show']);    // GET: Tampilkan kategori berdasarkan ID




// Produk Routes
Route::get('products', [ProductControllerUser::class, 'index']);        // GET: Tampilkan semua produk
Route::get('products/{id}', [ProductControllerUser::class, 'show']);    // GET: Tampilkan produk berdasarkan ID

Route::get('/product/filter', [ProductControllerUser::class, 'filter']);

// review/rating
Route::post('/review', [ProductReviewController::class, 'addReview']); // Tambah review
Route::get('/review/{productId}', [ProductReviewController::class, 'showReviews']); // Tampilkan review
Route::get('/reviews/all', [ProductReviewController::class, 'getProductRatings']); // Tampilkan review


// Order Routes
Route::post('orders', [OrderControllerUser::class, 'createOrder']);          // POST: Tambah pesanan baru
Route::get('orders/show/{id}', [OrderControllerUser::class, 'showOrder']);          // POST: Tambah pesanan baru


Route::post('/xendit/callback/{id}', [OrderControllerUser::class, 'notification'])->name('payment.callback');



// Route::middleware('auth:api')->get('/admin/products', [ProductControllerUser::class, 'index']);

Route::post('/register', [RegisterController::class, '__invoke'])->name('admin.register');
Route::post('/login', App\Http\Controllers\Api\Auth\LoginController::class)->name('admin.login');


// Admin
Route::prefix('admin')->middleware('auth:api')->group(function () {
  
  
    Route::get('/dashboard', [DashboardControllerAdmin::class, 'dashboard']);             // GET: Semua pesanan

    
    // Routes for products
    Route::get('products', [ProductControllerAdmin::class, 'index']);        // GET: Tampilkan semua produk
    Route::get('products/{id}', [ProductControllerAdmin::class, 'show']);    
    Route::post('products', [ProductControllerAdmin::class, 'store']);       // POST: Tambah produk baru
    Route::post('products/{id}', [ProductControllerAdmin::class, 'update']);  // PUT: Update produk berdasarkan ID
    Route::delete('products/{id}', [ProductControllerAdmin::class, 'destroy']);

    // Routes for categories
    Route::get('categories', [CategoryControllerAdmin::class, 'index']);        // GET: Tampilkan semua kategori
    Route::get('categories/{id}', [CategoryControllerAdmin::class, 'show']);    // GET: Tampilkan kategori berdasarkan ID
    Route::post('categories', [CategoryControllerAdmin::class, 'store']);       // POST: Tambah kategori baru
    Route::post('categories/{id}', [CategoryControllerAdmin::class, 'update']);  // PUT: Update kategori berdasarkan ID
    Route::delete('categories/{id}', [CategoryControllerAdmin::class, 'destroy']);

    // Routes for orders
    Route::get('orders', [OrderControllerUserAdmin::class, 'allOrder']);             // GET: Semua pesanan
    Route::get('orders/{id}', [OrderControllerUserAdmin::class, 'ShowOrder']);       // GET: Tampilkan pesanan berdasarkan ID
    Route::put('orders/{id}', [OrderControllerUserAdmin::class, 'update']);          // PUT: Update status pesanan
    Route::delete('orders/{id}', [OrderControllerUserAdmin::class, 'destroy']);      // DELETE: Hapus pesanan berdasarkan ID
});
