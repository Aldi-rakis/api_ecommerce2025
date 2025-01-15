<?php

namespace App\Http\Controllers\Api;

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
        'images' => 'required|array',
        'images.*' => 'file|image|mimes:jpeg,png,jpg,gif|max:2048',
        'size' => 'required|string',
        'price' => 'required|numeric',
        'rating' => 'nullable|numeric',
        'category_id' => 'required|exists:categories,id',
    ]);

    $product = Product::findOrFail($id); // Ambil produk berdasarkan ID

    // Hapus gambar lama jika ada
    if ($product->images) {
        foreach ($product->images as $image) {
            Storage::disk('public')->delete('products/' . $image); // Hapus gambar lama dari storage
        }
    }

    // Proses unggah gambar baru
    $imagePaths = [];
    foreach ($request->file('images') as $image) {
        $imageName = $image->hashName();
        $image->storeAs('products', $imageName, 'public');
        $imagePaths[] = $imageName; // Simpan gambar yang baru
    }

    // Update produk dengan gambar baru
    $product->update([
        'name' => $validated['name'],
        'description' => $validated['description'],
        'images' => $imagePaths, // Simpan gambar sebagai array
        'size' => $validated['size'],
        'price' => $validated['price'],
        'rating' => $validated['rating'],
        'category_id' => $validated['category_id'],
    ]);

    return response()->json($product, 200); // Kembalikan produk yang diperbarui
}

 

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();
        return response()->json([
            'success' => true,
            'message' => 'Product deleted']);
    }
}
