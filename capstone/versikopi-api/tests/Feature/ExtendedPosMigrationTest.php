<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExtendedPosMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_adopting_existing_schema_preserves_inventory_and_product_metadata(): void
    {
        $ingredientId = DB::table('ingredients_stock')->insertGetId([
            'name' => 'Stok Toko Lama', 'unit' => 'gr', 'current_stock' => 123, 'max_stock' => 500,
        ]);
        $productId = DB::table('products')->insertGetId([
            'name' => 'Paket Toko Lama', 'category' => 'Bundling', 'price_m' => 12345, 'price_l' => 12345,
            'item_type' => 'paketan', 'bundle_items' => '[]', 'max_stock' => 99, 'image_url' => '/foto-lama.jpg',
        ]);
        $migration = require database_path('migrations/2026_11_25_122828_add_extended_pos_schema.php');

        $migration->up();

        $this->assertDatabaseHas('ingredients_stock', ['id' => $ingredientId, 'current_stock' => 123]);
        $this->assertDatabaseHas('products', [
            'id' => $productId, 'price_m' => 12345, 'item_type' => 'paketan',
            'bundle_items' => '[]', 'max_stock' => 99, 'image_url' => '/foto-lama.jpg',
        ]);
    }

    public function test_rollback_is_blocked_before_it_can_destroy_adopted_data(): void
    {
        $ingredientId = DB::table('ingredients_stock')->insertGetId([
            'name' => 'Stok Toko Lama', 'current_stock' => 123,
        ]);
        $orderId = DB::table('orders')->insertGetId([
            'code' => 'LEGACY-ORDER', 'cashier_name' => 'Pemilik', 'items' => '[]',
            'subtotal' => 12345, 'total' => 12345, 'payment_method' => 'cash', 'status' => 'void',
            'customer_name' => 'Pelanggan Lama', 'order_type' => 'dine_in', 'table_number' => 'A1',
        ]);
        $migration = require database_path('migrations/2026_11_25_122828_add_extended_pos_schema.php');
        $blocked = false;

        try {
            $migration->down();
        } catch (\RuntimeException $exception) {
            $blocked = true;
            $this->assertStringContainsString('forward-only', $exception->getMessage());
        }

        $this->assertTrue($blocked, 'Rollback must stop instead of deleting adopted POS data.');
        $this->assertDatabaseHas('ingredients_stock', ['id' => $ingredientId, 'current_stock' => 123]);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId, 'status' => 'void', 'customer_name' => 'Pelanggan Lama',
            'order_type' => 'dine_in', 'table_number' => 'A1',
        ]);
    }
}
