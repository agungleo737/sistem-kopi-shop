<?php
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Autentikasi
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// pengaturan toko
Route::get('/settings', function () {
    return response()->json(DB::table('settings')->pluck('value', 'key'));
});
Route::get('/promos', function () {
    return response()->json(DB::table('promos')->where('active', 1)->get());
});

// produk
Route::get('/products', function () {
    return response()->json(DB::table('products')->where('active', 1)->get());
});

// Tambah produk baru
Route::post('/products', function (Request $request) {
    $id = DB::table('products')->insertGetId([
        'name'        => $request->input('name'),
        'category'    => $request->input('category'),
        'description' => $request->input('description', ''),
        'price_m'     => $request->input('price_m', 0),
        'price_l'     => $request->input('price_l', 0),
        'stock'       => $request->input('stock', 0),
        'emoji'       => $request->input('emoji', '☕'),
        'gradient'    => $request->input('gradient', '#C08552,#6F4E37'),
        'active'      => 1,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
    // Catat ke inventory log
    DB::table('inventory_logs')->insert([
        'product_id'   => $id,
        'product_name' => $request->input('name'),
        'type'         => 'in',
        'qty'          => $request->input('stock', 0),
        'stock_after'  => $request->input('stock', 0),
        'note'         => 'Stok awal penambahan menu baru',
        'reference'    => 'NEW-ITEM',
        'actor_name'   => 'Admin',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);
    return response()->json(['success' => true, 'id' => $id]);
});

// Restock Produk
Route::post('/products/{id}/restock', function (Request $request, $id) {
    $qty = (int) $request->input('qty', 0);
    $product = DB::table('products')->where('id', $id)->first();

    if (!$product) {
        return response()->json(['error' => 'Produk tidak ditemukan'], 404);
    }
    $newStock = $product->stock + $qty;
    DB::table('products')->where('id', $id)->update([
        'stock'      => $newStock,
        'updated_at' => now(),
    ]);
    DB::table('inventory_logs')->insert([
        'product_id'   => $id,
        'product_name' => $product->name,
        'type'         => 'in',
        'qty'          => $qty,
        'stock_after'  => $newStock,
        'note'         => $request->input('note', 'Restock barang masuk gudang'),
        'reference'    => 'RESTOCK',
        'actor_name'   => 'Admin',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);
    return response()->json(['success' => true, 'new_stock' => $newStock]);
});
// riwayat transaksi
// Ambil riwayat order
Route::get('/orders', function () {
    $orders = DB::table('orders')->orderBy('id', 'desc')->get();
    return response()->json(['orders' => $orders]);
});

// Simpan transaksi baru
Route::post('/orders', function (Request $request) {
    $wib = now()->setTimezone('Asia/Jakarta');
    $prefix = 'KS-' . $wib->format('Ymd');
    $count = DB::table('orders')->where('code', 'like', "{$prefix}-%")->count();
    $orderCode = $prefix . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    $items = $request->input('items', []);
    $orderId = DB::table('orders')->insertGetId([
        'code'           => $orderCode,
        'cashier_id'     => $request->input('cashier_id', 2),
        'cashier_name'   => $request->input('cashier_name', 'Kasir Versi Kopi'),
        'items'          => is_array($items) ? json_encode($items) : $items,
        'subtotal'       => $request->input('subtotal', 0),
        'discount'       => $request->input('discount', 0),
        'promo_code'     => $request->input('promo_code'),
        'tax'            => $request->input('tax', 0),
        'service'        => $request->input('service', 0),
        'total'          => $request->input('total', 0),
        'payment_method' => $request->input('payment_method', 'cash'),
        'cash_received'  => $request->input('cash_received', 0),
        'change_amount'  => $request->input('change_amount', 0),
        'status'         => 'paid',
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    // Potong stok produk & catat log keluar
    if (is_array($items)) {
        foreach ($items as $item) {
            if (isset($item['id']) && isset($item['qty'])) {
                $prod = DB::table('products')->where('id', $item['id'])->first();
                if ($prod) {
                    $newStock = max(0, $prod->stock - $item['qty']);
                    DB::table('products')->where('id', $item['id'])->update(['stock' => $newStock]);

                    DB::table('inventory_logs')->insert([
                        'product_id'   => $prod->id,
                        'product_name' => $prod->name,
                        'type'         => 'out',
                        'qty'          => $item['qty'],
                        'stock_after'  => $newStock,
                        'note'         => "Penjualan Kasir ({$orderCode})",
                        'reference'    => $orderCode,
                        'actor_name'   => $request->input('cashier_name', 'Kasir'),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
            }
        }
    }

    return response()->json([
        'success'    => true,
        'order_code' => $orderCode,
        'order_id'   => $orderId,
    ]);
});