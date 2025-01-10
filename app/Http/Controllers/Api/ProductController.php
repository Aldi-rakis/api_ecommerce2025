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
        $products = Product::with('category')->get();
    
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
                    'created_at' => $product->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $product->updated_at->format('Y-m-d H:i:s'),
                ];
            }),
        ], 200);
    }
    

    public function store(Request $request)
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
    
        // Proses unggah gambar
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $image->storeAs('products', $image->hashName() , 'public');
                $imagePaths[] = $image->hashName();
            }
        }
    
        // Simpan produk
        $product = Product::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'images' => $imagePaths, // Simpan sebagai array
            'size' => $validated['size'],
            'price' => $validated['price'],
            'rating' => $validated['rating'],
            'category_id' => $validated['category_id'],
        ]);
    
        // Muat relasi kategori
        // $product->load('category');
    
        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product,
        ], 201);
    }
    
    

    public function show($id)
    {
        $product = Product::with('category')->findOrFail($id);
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
