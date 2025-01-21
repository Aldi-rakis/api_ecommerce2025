<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProductControllerUser extends Controller
{
    public function index()
    {
        $products = Product::with(['sizes', 'category'])->get();
    
        return response()->json([
            'status' => true,
            'message' => 'Product data retrieved successfully',
            'products' => $products->map(function ($product) {
                $images = is_string($product->images) ? json_decode($product->images, true) : $product->images;
    
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'images' => collect($images)->map(function ($image) {
                        return url('storage/products/' . $image);
                    }),
                    'size' => $product->size,
                    'price' => $product->price,
                    'rating' => $product->rating,
                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                    ] : null,
                    'sizes' => $product->sizes->map(fn($size) => [
                        'size' => $size->size,
                        'stock' => $size->stock,
                        'price' => $size->price,
                    ]),
                ];
            }),
        ]);
    }
    

    

    public function show($id)
    {
        // Ambil detail produk berdasarkan ID
        $product = Product::with('category', 'sizes')->findOrFail($id);
    
        // Mengubah gambar JSON string menjadi array
        $images = json_decode($product->images);
    
        // Menambahkan URL gambar untuk setiap gambar
        $product->images = array_map(function ($image) {
            return asset('storage/products/' . $image);
        }, $images);
    
        // Gunakan transform untuk memilih field yang diinginkan di relasi sizes
        $product->sizes = $product->sizes->transform(function ($size) {
            return [
                'size' => $size->size,
                'price' => $size->price,
                'stock' => $size->stock,
            ];
        });
    
        // Sembunyikan kolom yang tidak diperlukan
        $product->makeHidden(['created_at', 'updated_at']);
    
        // Ambil rekomendasi 5 produk dengan rating tertinggi
        $recommendedProducts = Product::where('id', '!=', $id) // Kecualikan produk saat ini
            ->orderBy('rating', 'desc') // Urutkan berdasarkan rating tertinggi
            ->take(5) // Ambil 5 produk
            ->get(['id', 'name', 'rating', 'images']) // Pilih field yang diinginkan
            ->map(function ($recommendedProduct) {
                // Tambahkan URL gambar untuk setiap rekomendasi produk
                $recommendedProduct->images = array_map(function ($image) {
                    return asset('storage/products/' . $image);
                }, json_decode($recommendedProduct->images));
                return $recommendedProduct;
            });
    
        return response()->json([
            'success' => true,
            'message' => 'Detail Product retrieved successfully',
            'data' => [
                'product' => $product,
                'recommendations' => $recommendedProducts, // Tambahkan rekomendasi produk
            ],
        ]);
    }
    
 


    public function filter(Request $request)
    {
        // Validasi input
        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id', // Validasi kategori
            'min_price' => 'nullable|numeric|min:0', // Harga minimum
            'max_price' => 'nullable|numeric|min:0', // Harga maksimum
            'size' => 'nullable|string', // Ukuran
        ]);
    
        // Query produk
        $query = Product::query();
    
        // Filter berdasarkan kategori
        if ($request->has('category_id')) {
            $query->where('category_id', $validated['category_id']);
        }
    
        // Filter berdasarkan harga
        if ($request->has('min_price')) {
            $query->whereHas('sizes', function ($q) use ($validated) {
                $q->where('price', '>=', $validated['min_price']);
            });
        }
    
        if ($request->has('max_price')) {
            $query->whereHas('sizes', function ($q) use ($validated) {
                $q->where('price', '<=', $validated['max_price']);
            });
        }
    
        // Filter berdasarkan ukuran
        if ($request->has('size')) {
            $query->whereHas('sizes', function ($q) use ($validated) {
                $q->where('size', $validated['size']);
            });
        }
    
        // Ambil hasil query dengan relasi
        $product = $query->with(['sizes', 'category'])->get();
    
       
        return response()->json([
            'status' => true,
            'message' => 'Product data retrieved successfully',
            'products' => $product->map(function ($product) {
                $images = is_string($product->images) ? json_decode($product->images, true) : $product->images;
    
                
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'images' => collect($images)->map(function ($image) {
                        return url('storage/products/' . $image);
                    }),
                    'size' => $product->size,
                    'price' => $product->price,
                    'rating' => $product->rating,
                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                    ] : null,
                    'sizes' => $product->sizes->map(fn($size) => [
                        'size' => $size->size,
                        'stock' => $size->stock,
                        'price' => $size->price,
                    ]),
                ];
            }),
        ]);
    }
    
    

}
