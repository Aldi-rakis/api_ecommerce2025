<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\Auth\RegisterController;

use App\Http\Controllers\Api\Auth\LoginController;




Route::post('/register', [RegisterController::class, '__invoke'])->name('register');


Route::post('/login', App\Http\Controllers\Api\Auth\LoginController::class)->name('login');





// Kategori Routes
Route::get('categories', [CategoryController::class, 'index']);        // GET: Tampilkan semua kategori
Route::post('categories', [CategoryController::class, 'store']);       // POST: Tambah kategori baru
Route::get('categories/{id}', [CategoryController::class, 'show']);    // GET: Tampilkan kategori berdasarkan ID
Route::post('categories/{id}', [CategoryController::class, 'update']);  // PUT: Update kategori berdasarkan ID
Route::delete('categories/{id}', [CategoryController::class, 'destroy']); // DELETE: Hapus kategori berdasarkan ID




// Produk Routes
 Route::get('products', [ProductController::class, 'index']);        // GET: Tampilkan semua produk
 Route::post('products', [ProductController::class, 'store']);       // POST: Tambah produk baru
Route::get('products/{id}', [ProductController::class, 'show']);    // GET: Tampilkan produk berdasarkan ID
Route::post('products/{id}', [ProductController::class, 'update']);  // PUT: Update produk berdasarkan ID
Route::delete('products/{id}', [ProductController::class, 'destroy']); // DELETE: Hapus produk berdasarkan ID

Route::get('/product/filter', [ProductController::class, 'filter']);



// Order Routes
Route::get('orders', [OrderController::class, 'allOrder']);          // POST: Tambah pesanan baru
Route::post('orders', [OrderController::class, 'createOrder']);          // POST: Tambah pesanan baru
Route::get('orders/{id}', [OrderController::class, 'ShowOrder']);       // GET: Tampilkan pesanan berdasarkan ID
Route::put('orders/{id}', [OrderController::class, 'update']);     // PUT: Update status pesanan
Route::delete('orders/{id}', [OrderController::class, 'destroy']); // DELETE: Hapus pesanan berdasarkan ID

Route::post('/xendit/callback/{id}', [OrderController::class, 'notification'])->name('payment.callback');



Route::middleware('auth:api')->get('/admin/products', [ProductController::class, 'index']);



