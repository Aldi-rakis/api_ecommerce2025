<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\Auth\RegisterController;

use App\Http\Controllers\Api\Auth\LoginController;




Route::post('/register', [RegisterController::class, '__invoke'])->name('register');


Route::post('/login', App\Http\Controllers\Api\Auth\LoginController::class)->name('login');





// Kategori Routes
Route::get('categories', [CategoryController::class, 'index']);        // GET: Tampilkan semua kategori
Route::get('categories/{id}', [CategoryController::class, 'show']);    // GET: Tampilkan kategori berdasarkan ID




// Produk Routes
Route::get('products', [ProductController::class, 'index']);        // GET: Tampilkan semua produk
Route::get('products/{id}', [ProductController::class, 'show']);    // GET: Tampilkan produk berdasarkan ID

Route::get('/product/filter', [ProductController::class, 'filter']);



// Order Routes
Route::post('orders', [OrderController::class, 'createOrder']);          // POST: Tambah pesanan baru

Route::post('/xendit/callback/{id}', [OrderController::class, 'notification'])->name('payment.callback');



Route::middleware('auth:api')->get('/admin/products', [ProductController::class, 'index']);



// Admin
Route::prefix('admin')->middleware('auth:api')->group(function () {
  
    Route::post('/register', [RegisterController::class, '__invoke'])->name('admin.register');
    Route::post('/login', App\Http\Controllers\Api\Auth\LoginController::class)->name('admin.login');
    Route::get('/dashboard', [DashboardController::class, 'dashboard']);             // GET: Semua pesanan

    
    // Routes for products
    Route::post('products', [ProductController::class, 'store']);       // POST: Tambah produk baru
    Route::post('products/{id}', [ProductController::class, 'update']);  // PUT: Update produk berdasarkan ID
    Route::delete('products/{id}', [ProductController::class, 'destroy']);

    // Routes for categories
    Route::post('categories', [CategoryController::class, 'store']);       // POST: Tambah kategori baru
    Route::post('categories/{id}', [CategoryController::class, 'update']);  // PUT: Update kategori berdasarkan ID
    Route::delete('categories/{id}', [CategoryController::class, 'destroy']);

    // Routes for orders
    Route::get('orders', [OrderController::class, 'allOrder']);             // GET: Semua pesanan
    Route::get('orders/{id}', [OrderController::class, 'ShowOrder']);       // GET: Tampilkan pesanan berdasarkan ID
    Route::put('orders/{id}', [OrderController::class, 'update']);          // PUT: Update status pesanan
    Route::delete('orders/{id}', [OrderController::class, 'destroy']);      // DELETE: Hapus pesanan berdasarkan ID
});
