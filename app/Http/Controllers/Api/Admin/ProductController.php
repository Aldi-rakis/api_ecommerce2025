<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
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
    

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'required|string',
            'images' => 'required|array',
            'images.*' => 'file|image|mimes:jpeg,png,jpg,gif|max:10240',
            'price_flat' => 'nullable|string',
            'size' => 'nullable|string',
            'size_stock' => 'required|array',
            'size_stock.*.size' => 'required|string',
            'size_stock.*.stock' => 'required|integer|min:0',
            'size_stock.*.price' => 'required|numeric',
            'rating' => 'nullable|numeric',
            'category_id' => 'required|exists:categories,id',
        ]);
        
        // Simpan file gambar
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imageName = $image->hashName();
                $image->storeAs('products', $imageName, 'public');
                $imagePaths[] = $imageName;
            }
        }
    
        // Simpan produk
        $product = Product::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'images' => json_encode($imagePaths), // Menyimpan gambar dalam format JSON
            'price_flat' => $validated['price_flat'] ?? null, // Jika tidak ada, set null
            'size' => $validated['size'] ?? null, // Jika tidak ada, set null
            'rating' => $validated['rating'],
            'category_id' => $validated['category_id'],
        ]);
    
        // Simpan ukuran dan stok
        foreach ($validated['size_stock'] as $sizeStock) {
            $product->sizes()->create([
                'size' => $sizeStock['size'],
                'stock' => $sizeStock['stock'],
                'price' => $sizeStock['price'],
            ]);
        }

        
    
        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product->load('sizes', 'category'),
        ], 201);
    }
    
    

    public function show($id)
{
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

    return response()->json($product);
}

public function update(Request $request, $id)
{
    $validated = $request->validate([
        'name' => 'required|string',
        'description' => 'required|string',
        'images' => 'nullable|array',
        'images.*' => 'file|image|mimes:jpeg,png,jpg,gif|max:2048',
        'size_stock' => 'required|array',
        'size_stock.*.size' => 'required|string',
        'size_stock.*.stock' => 'required|integer|min:0',
        'size_stock.*.price' => 'required|numeric',
        'rating' => 'nullable|numeric',
        'category_id' => 'required|exists:categories,id',
    ]);

    $product = Product::with('sizes')->findOrFail($id); // Ambil produk beserta ukuran

    // Hapus gambar lama jika ada gambar baru diunggah
    if ($request->hasFile('images')) {
        foreach (json_decode($product->images, true) as $image) {
            Storage::disk('public')->delete('products/' . $image);
        }

        // Proses unggah gambar baru
        $imagePaths = [];
        foreach ($request->file('images') as $image) {
            $imageName = $image->hashName();
            $image->storeAs('products', $imageName, 'public');
            $imagePaths[] = $imageName;
        }
    } else {
        $imagePaths = json_decode($product->images, true); // Tetap gunakan gambar lama jika tidak ada gambar baru
    }

    // Update data produk
    $product->update([
        'name' => $validated['name'],
        'description' => $validated['description'],
        'images' => json_encode($imagePaths), // Simpan gambar dalam format JSON
        'rating' => $validated['rating'],
        'category_id' => $validated['category_id'],
    ]);

    // Update ukuran dan stok
    $product->sizes()->delete(); // Hapus semua ukuran lama
    foreach ($validated['size_stock'] as $sizeStock) {
        $product->sizes()->create([
            'size' => $sizeStock['size'],
            'stock' => $sizeStock['stock'],
            'price' => $sizeStock['price'],
        ]);
    }

    return response()->json([
        'message' => 'Product updated successfully',
        'product' => $product->load('sizes', 'category'),
    ], 200);
}


 

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();
        return response()->json([
            'success' => true,
            'message' => 'Product deleted']);
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
