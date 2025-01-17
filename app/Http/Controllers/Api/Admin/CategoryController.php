<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;


class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::all();

        return [
            'status' => true,
            'message' => 'Data Category',
            'categories' => $categories,
        ];
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
        ]);

        // Inisialisasi variabel untuk menyimpan nama file gambar
        $imageName = null;

        // Proses upload gambar jika tersedia
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = $image->hashName(); // Hash name untuk nama file unik
            $image->storeAs('categories', $imageName, 'public'); // Simpan di disk 'public'
        }

        // Simpan kategori ke database
        $category = Category::create([
            'name' => $request->name,
            'image' => $imageName, // Simpan nama file gambar atau null jika tidak ada gambar
        ]);

        return response()->json(['message' => 'Category created successfully', 'data' => $category], 201);
    }


    public function show($id)
    {
        $category = Category::findOrFail($id);
        return response()->json($category);
    }

    //    
    public function update(Request $request, $id)
    {
        // Temukan category berdasarkan ID
        $category = Category::find($id);

        // Jika category tidak ditemukan, kembalikan error
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        // Validasi data yang masuk
        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:categories,name,' . $category->id,
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Cek jika ada file gambar yang diupload
        if ($request->hasFile('image')) {
            // Hapus gambar lama
            Storage::disk('local')->delete('public/categories/' . basename($category->image));

            // Upload gambar baru
            $image = $request->file('image');
            $image->storeAs('public/categories', $image->hashName());

            // Update category dengan gambar baru
            $category->update([
                'image' => $image->hashName(),
                'name' => $request->name,  // Perbaikan: gunakan $request->title
          
            ]);
        } else {
            // Update category tanpa gambar
            $category->update([
                'name' => $request->name,  // Perbaikan: gunakan $request->title
             
            ]);
        }

        // Cek jika update berhasil dan kembalikan response
        if ($category) {
            return response()->json(
                [
                    'message' => 'Data Category berhasil diperbarui',
                    'data' => $category
                ]
            );
        }

        // Kembalikan error jika update gagal
        return response()->json(['message' => 'Failed to update news'], 500);
    }



    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['message' => 'category tidak ditemukan'], 404);
        }

        // Hapus gambar jika ada
        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();

        return response()->json(['message' => 'category berhasil dihapus']);
    }
    
}
