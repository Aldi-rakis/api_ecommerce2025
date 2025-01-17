<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function dashboard()
    {
        // Total produk
        $totalProducts = Product::count();

        // Total kategori
        $totalCategories = Category::count();

        // 5 data order terakhir
        $latestOrders = Order::with('items') // Include relasi items
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Total penghasilan dari order dengan status "paid"
        $totalEarnings = Order::where('status', 'paid')
            ->sum('total_amount');

        // Response JSON
        return response()->json([
            'message' => 'Dashboard data retrieved successfully',
            'data' => [
                'total_products' => $totalProducts,
                'total_categories' => $totalCategories,
                'latest_orders' => $latestOrders,
                'total_earnings' => $totalEarnings,
            ],
        ], 200);
    }
}
