<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

use Illuminate\Support\Str;
use Xendit\Configuration;
use Xendit\Invoice\InvoiceApi;
use Illuminate\Http\Response;


use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductSize;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class OrderControllerUserAdmin extends Controller
{

    protected $orderToken;

    public function __construct()
    {
        // Configuration::setXenditKey(env('XENDIT_SECRET_KEY')); // Mengambil API key dari file .env

        // Mengambil token dari .env untuk validasi order
        // $this->orderToken = env('ORDER_TOKEN');
    }
   



    public function showOrder($id)
    {
        $order = Order::with('items')->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }


        $data = [
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone,
            'total_amount' => $order->total_amount,
            'status' => $order->status,
            'items' => $order->items->map(function ($item) {
                return [

                    'product_name' => $item->product->name, // Pastikan relasi ke produk ada
    
                    'product_images' => array_map(function ($image) {
                        return asset('storage/products/' . $image);
                    }, json_decode($item->product->images, true)), // Pastikan ini array
                    'sizes' => [
                        [
                            'size' => $item->size,
                            'quantity' => $item->quantity,
                        ]
                    ],
                ];
            }),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Detail Order retrieved successfully',
            'order' => $data], 200);
    }





    public function allOrder()
    {
        $order = Order::get();
        return response()->json([
            'success' => true,
            'message' => 'Data Order retrieved successfully',
            'data' => $order,
            
        ], 200);
    }

   


  

    public function createOrder2(Request $request)
    {
        // Validasi input
        $validated = $request->validate([
            'customer_name' => 'required|string',
            'customer_email' => 'required|email',
            'customer_phone' => 'required|string',
            'status' => 'nullable|string',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $totalAmount = 0;

        // Menghitung total amount untuk order
        foreach ($validated['items'] as $item) {
            $product = Product::find($item['product_id']);
            $totalAmount += $product->price * $item['quantity'];
        }

        // Membuat external ID untuk invoice
        $externalId = 'ORD-' . date('Ymd') . '-' . rand(1000, 9999);

        // Membuat order
        $order = Order::create([
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'],
            'customer_phone' => $validated['customer_phone'],
            'total_amount' => $totalAmount,
            'status' => 'pending',
            'external_id' => $externalId,

        ]);

        // Menambahkan items ke dalam order
        foreach ($validated['items'] as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
            ]);
        }


        // Mengirim permintaan ke Xendit untuk membuat invoice
        // Mengirim permintaan invoice ke Xendit
        $apiInstance = new InvoiceApi();


        try {
            $createInvoiceRequest = $apiInstance->createInvoice([
                'external_id' => $externalId,
                'description' => 'Order for ' . $order->customer_name,
                'amount' => $order->total_amount,
                'customer' => [
                    'given_names' => $order->customer_name,
                    'email' => $order->customer_email, // Pastikan email dimasukkan jika perlu
                ],
                'callback_virtual_account_paid' => route('payment.callback', ['id' => $order->id]), // URL callback
                'success_redirect_url' => "https://49cf-180-245-177-221.ngrok-free.app/", // Ganti dengan URL kamu
                'failure_redirect_url' => "https://localhost:8000/failure", // Ganti dengan URL kamu
            ]);

            if (isset($createInvoiceRequest['invoice_url'])) {
                // Menyimpan invoice_url dan external_id di database
                $order->external_id = $externalId;
                $order->invoice_url = $createInvoiceRequest['invoice_url'];
                $order->save();



                return response()->json([
                    'success' => true,
                    'message' => 'Order created successfully',
                    'data' => [
                        'order_id' => $order->id,
                        'customer_name' => $order->customer_name,
                        'customer_email' => $order->customer_email,
                        'customer_phone' => $order->customer_phone,
                        'total_amount' => $order->total_amount,
                        'status' => $order->status,
                        'external_id' => $order->external_id,
                        'invoice_url' => $order->invoice_url,
                        'invoice_id' => 'http://localhost:8000/api/checkoutt/' . $createInvoiceRequest['id'],

                    ]
                ], 201);
            } else {
                return response()->json(['error' => 'Failed to create invoice, no invoice URL found.'], 500);
            }
        } catch (\Xendit\XenditSdkException $e) {
            // Menghapus order jika terjadi error
            $order->delete();
            return response()->json(['error' => 'Failed to create invoice: ' . $e->getMessage()], 500);
        }
    }

}
