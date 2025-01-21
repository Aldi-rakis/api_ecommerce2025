<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview; // Model untuk menyimpan komentar dan rating
use Illuminate\Support\Facades\Validator;

class ProductReviewController extends Controller
{
    /**
     * Tambahkan komentar dan rating untuk produk yang sudah diorder.
     */
    public function addReview(Request $request)
{
    // Validasi input
    $validator = Validator::make($request->all(), [
        'order_id' => 'required|exists:orders,id',
        'product_id' => 'required|exists:products,id',
        'rating' => 'required|integer|min:1|max:5',
        'comment' => 'nullable|string|max:500',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // Cek apakah produk termasuk dalam order
    $order = Order::find($request->order_id);
    $isProductInOrder = $order->orderItems()->where('product_id', $request->product_id)->exists();

    if (!$isProductInOrder) {
        return response()->json([
            'success' => false,
            'message' => 'Product does not belong to the given order.'
        ], 400);
    }

    // Simpan data review
    $review = ProductReview::create([
        'order_id' => $request->order_id,
        'product_id' => $request->product_id,
        'rating' => $request->rating,
        'comment' => $request->comment,
    ]);

    // Hitung rata-rata rating produk
    $averageRating = ProductReview::where('product_id', $request->product_id)->avg('rating');

    // Update kolom rating di tabel produk
    $product = Product::find($request->product_id);
    $product->update(['rating' => round($averageRating, 1)]);

    return response()->json([
        'success' => true,
        'message' => 'Review has been added successfully.',
        'data' => $review,
    ], 201);
}


    /**
     * Tampilkan semua review untuk produk tertentu.
     */
    public function showReviews($productId)
    {
        $reviews = ProductReview::where('product_id', $productId)->get();
        $products = Product::with(relations: 'reviews')->get();

        if ($reviews->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No reviews found for this product.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'rating' => $products->where('id', $productId)->first()->rating,
            'data' => $reviews,
        ], 200);
    }

    public function getProductRatings()
{
    $products = Product::with(relations: 'reviews')->get();

    return response()->json([
        'success' => true,
        'data' => $products->map(function ($product) {
            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'rating' => $product->rating,
                'reviews' => $product->reviews->map(function ($review) {
                    return [
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                    ];
                }),
            ];
        }),
    ]);
}

}
