<?php

namespace App\Http\Controllers\Api\Users;

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

class OrderControllerUser extends Controller
{

    protected $orderToken;

    public function __construct()
    {
        Configuration::setXenditKey(env('XENDIT_SECRET_KEY')); // Mengambil API key dari file .env

        // Mengambil token dari .env untuk validasi order
        $this->orderToken = env('ORDER_TOKEN');
    }
    public function createOrder(Request $request)
    {

        // Validasi token
        if ($request->header('Order-Token') !== $this->orderToken) {
            return response()->json(['message' => 'Invalid order token'], 403);
        }
        // Validasi input
        $validated = $request->validate([
            'customer_name' => 'required|string',
            'customer_email' => 'required|email',
            'customer_phone' => 'required|string',
            'order' => 'required|array',
            'order.*.product_id' => 'required|exists:products,id',
            'order.*.sizes' => 'required|array',
            'order.*.sizes.*.size' => 'required|string',
            'order.*.sizes.*.quantity' => 'required|integer|min:1',
        ]);

        $totalAmount = 0;

        // Validasi dan perhitungan harga berdasarkan ukuran dan jumlah
        foreach ($validated['order'] as $item) {
            foreach ($item['sizes'] as $size) {
                $productSize = ProductSize::where('product_id', $item['product_id'])
                    ->where('size', $size['size'])
                    ->first();

                if (!$productSize) {
                    return response()->json([
                        'message' => 'Product size not found',
                        'product_id' => $item['product_id'],
                        'size' => $size['size']
                    ], 404);
                }

                // Periksa stok
                if ($productSize->stock < $size['quantity']) {
                    return response()->json([
                        'message' => 'Stock not enough, check product size',
                        'product_id' => $item['product_id'],
                        'size' => $size['size']
                    ], 400);
                }

                $totalAmount += $productSize->price * $size['quantity'];
            }
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

        // Menambahkan items ke dalam order dan mengurangi stok
        foreach ($validated['order'] as $item) {
            foreach ($item['sizes'] as $size) {
                $productSize = ProductSize::where('product_id', $item['product_id'])
                    ->where('size', $size['size'])
                    ->first();

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'size' => $size['size'],
                    'quantity' => $size['quantity'],
                    // 'price' => $productSize->price,
                ]);

                // Kurangi stok
                $productSize->stock -= $size['quantity'];
                $productSize->save();
            }
        }

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

    private function createXenditInvoice($order)
    {
        // Replace with your Xendit API key
        $apiKey = 'xnd_public_development_St4hKfImy_9TAboMBg3ZAGlmmecMyfht_qqk6Wdqu7uyE4P2gUEBw8QjXPeHp99e';

        $payload = [
            'external_id' => 'order-' . $order->id,
            'amount' => $order->total_amount,
            'payer_email' => $order->customer_email,
            'description' => 'Order #' . $order->id,
        ];


        $response = Http::withBasicAuth($apiKey, '')
            ->post('https://api.xendit.co/v2/invoices', $payload);

        if ($response->successful()) {
            return $response->json()['invoice_url'];
        }

        throw new \Exception('Failed to create Xendit invoice');
    }



    // fungsi untuk mengecek status payment
    public function notification($id)
    {
        $apiInstance = new \Xendit\Invoice\InvoiceApi();

        try {
            // Mendapatkan detail invoice berdasarkan invoice_id dari Xendit
            $result = $apiInstance->getInvoiceById($id);

            // Cari order berdasarkan external_id yang diambil dari detail invoice
            $order = Order::where('external_id', $result['external_id'])->firstOrFail();

            // Jika order sudah dibayar (status 'paid')
            if ($order->status == 'paid') {
                return response()->json(['message' => 'Payment Anda telah berhasil diproses']);
            }

            // Ubah status "SETTLED" menjadi "paid"
            $status = strtolower($result['status'] === 'SETTLED' ? 'paid' : $result['status']);


            // Update status order berdasarkan status invoice dari Xendit
            $order->status = $status;
            $order->save();

            return response()->json(['message' => 'Payment Anda telah berhasil']);

        } catch (\Xendit\XenditSdkException $e) {
            // Menangani error dari SDK Xendit
            return response()->json(['error' => 'Failed to retrieve invoice: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            // Menangani error lainnya
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }


    // fungsi callback webhook xendit
    public function notificationCallback(Request $request)
    {
        /**
         * Mendapatkan token xendit dari webhooks
         */
        $getToken = $request->headers->get('x-callback-token');

        /**
         * Mengabil nilai token yang ditambahakan pada file .env
         */
        $callbackToken = env('XENDIT_CALLBACK_TOKEN');

        try {
            $order = Order::where('external_id', $request->external_id)->first();

            /**
             * Kondisi jika nilai callback token kosong pada file .env
             */
            if (!$callbackToken) {
                return response()->json([
                    'status' => 'Error',
                    'message' => 'Callback token xendit not exists'
                ], Response::HTTP_NOT_FOUND);
            }

            /**
             * Kondisi jika nilai token yang didapatkan tidak sama dengan
             * nilai token yang dimuat pada file .env
             */
            if ($getToken !== $callbackToken) {
                return response()->json([
                    'status' => 'Error',
                    'message' => 'Token callback invalid'
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            /**
             * Jika responses status bernilai PAID
             * ubah status pembayaran pada tabel orders
             */
            if ($order) {
                if ($request->status === 'PAID') {
                    $order->update([
                        'status' => 'paid'
                    ]);
                } else {
                    $order->update([
                        'status' => 'Failed'
                    ]);
                }
            }

            return response()->json([
                'status' => Response::HTTP_OK,
                'message' => 'callback sent'
            ]);
        } catch (\Throwable $th) {
            throw $th;
        }
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
