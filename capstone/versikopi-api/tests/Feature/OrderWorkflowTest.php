<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private int $productId;

    private int $ingredientId;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('settings')->insert(['key' => 'catalog_version', 'value' => 'versikopi-official-v2']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/ingredients')->assertOk();

        $this->ingredientId = DB::table('ingredients_stock')->insertGetId([
            'name' => 'Biji Uji',
            'unit' => 'gr',
            'current_stock' => 9000,
            'max_stock' => 10000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->productId = DB::table('products')->insertGetId([
            'name' => 'Kopi Uji',
            'category' => 'Coffee',
            'item_type' => 'satuan',
            'description' => '',
            'price_m' => 12000,
            'price_l' => 15000,
            'stock' => 20,
            'max_stock' => 20,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('recipes')->insert([
            'product_id' => $this->productId,
            'prep_time' => '2 Menit',
            'ingredients' => json_encode([
                ['name' => 'Biji Uji', 'amount_m' => 1000, 'amount_l' => 1500, 'unit' => 'gr'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_checkout_rejects_cash_shortfall_without_creating_an_order_or_deducting_stock(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'kasir']));

        $this->postJson('/api/orders', [
            'items' => [['id' => $this->productId, 'qty' => 1, 'size' => 'M']],
            'payment_method' => 'cash',
            'cash_received' => 11999,
        ])->assertUnprocessable()->assertJsonValidationErrors('cash_received');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('ingredients_stock', ['id' => $this->ingredientId, 'current_stock' => 9000]);
    }

    public function test_checkout_uses_database_price_and_deducts_recipe_stock(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'kasir']));

        $response = $this->postJson('/api/orders', [
            'items' => [['id' => $this->productId, 'qty' => 1, 'size' => 'M', 'unit_price' => 1]],
            'payment_method' => 'cash',
            'cash_received' => 12000,
        ])->assertCreated()->assertJsonPath('subtotal', 12000)->assertJsonPath('total', 12000);

        $this->assertDatabaseHas('ingredients_stock', ['id' => $this->ingredientId, 'current_stock' => 8000]);
        $this->assertDatabaseHas('orders', ['id' => $response->json('order_id'), 'total' => 12000]);
    }

    public function test_checkout_can_use_every_complete_medium_portion(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'kasir']));

        $this->postJson('/api/orders', [
            'items' => [['id' => $this->productId, 'qty' => 9, 'size' => 'M']],
            'payment_method' => 'qris',
        ])->assertCreated()->assertJsonPath('total', 108000);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('ingredients_stock', ['id' => $this->ingredientId, 'current_stock' => 0]);
    }

    public function test_large_checkout_can_use_every_complete_large_portion(): void
    {
        $this->postJson('/api/orders', [
            'items' => [['id' => $this->productId, 'qty' => 6, 'size' => 'L']],
            'payment_method' => 'qris',
        ])->assertCreated()->assertJsonPath('total', 90000);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('ingredients_stock', ['id' => $this->ingredientId, 'current_stock' => 0]);
    }

    public function test_mixed_sizes_can_jointly_exhaust_shared_ingredients(): void
    {
        $this->postJson('/api/orders', [
            'items' => [
                ['id' => $this->productId, 'qty' => 3, 'size' => 'M'],
                ['id' => $this->productId, 'qty' => 4, 'size' => 'L'],
            ],
            'payment_method' => 'qris',
        ])->assertCreated()->assertJsonPath('total', 96000);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('ingredients_stock', ['id' => $this->ingredientId, 'current_stock' => 0]);
    }

    public function test_different_products_can_jointly_exhaust_shared_ingredients(): void
    {
        $otherProductId = $this->createProductSharingIngredient();

        $this->postJson('/api/orders', [
            'items' => [
                ['id' => $this->productId, 'qty' => 4, 'size' => 'M'],
                ['id' => $otherProductId, 'qty' => 5, 'size' => 'M'],
            ],
            'payment_method' => 'qris',
        ])->assertCreated()->assertJsonPath('total', 108000);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('ingredients_stock', ['id' => $this->ingredientId, 'current_stock' => 0]);
    }

    public function test_percentage_threshold_does_not_reserve_usable_ingredients(): void
    {
        $otherProductId = $this->createProductSharingIngredient();
        DB::table('ingredients_stock')->where('id', $this->ingredientId)->update([
            'current_stock' => 20000, 'max_stock' => 200000,
        ]);

        $this->postJson('/api/orders', [
            'items' => [
                ['id' => $this->productId, 'qty' => 6, 'size' => 'M'],
                ['id' => $otherProductId, 'qty' => 5, 'size' => 'M'],
            ],
            'payment_method' => 'qris',
        ])->assertCreated()->assertJsonPath('total', 132000);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('ingredients_stock', ['id' => $this->ingredientId, 'current_stock' => 9000]);
    }

    public function test_bundle_and_single_product_can_jointly_exhaust_shared_ingredients(): void
    {
        $bundleId = DB::table('products')->insertGetId([
            'name' => 'Dua Kopi Uji', 'category' => 'Bundling', 'item_type' => 'paketan',
            'price_m' => 20000, 'price_l' => 20000,
            'bundle_items' => json_encode([['product_id' => $this->productId, 'qty' => 2]]),
        ]);

        $this->postJson('/api/orders', [
            'items' => [
                ['id' => $bundleId, 'qty' => 1, 'size' => 'M'],
                ['id' => $this->productId, 'qty' => 7, 'size' => 'M'],
            ],
            'payment_method' => 'qris',
        ])->assertCreated()->assertJsonPath('total', 104000);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('ingredients_stock', ['id' => $this->ingredientId, 'current_stock' => 0]);
    }

    public function test_large_checkout_deducts_the_selected_size_recipe(): void
    {
        $this->postJson('/api/orders', [
            'items' => [['id' => $this->productId, 'qty' => 3, 'size' => 'L']],
            'payment_method' => 'qris',
        ])->assertCreated()->assertJsonPath('total', 45000);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('ingredients_stock', ['id' => $this->ingredientId, 'current_stock' => 4500]);
    }

    public function test_shared_ingredient_checkout_deducts_both_products(): void
    {
        $otherProductId = $this->createProductSharingIngredient();

        $this->postJson('/api/orders', [
            'items' => [
                ['id' => $this->productId, 'qty' => 3, 'size' => 'M'],
                ['id' => $otherProductId, 'qty' => 3, 'size' => 'M'],
            ],
            'payment_method' => 'qris',
        ])->assertCreated()->assertJsonPath('total', 72000);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('ingredients_stock', ['id' => $this->ingredientId, 'current_stock' => 3000]);
    }

    public function test_shared_ingredient_checkout_can_leave_five_percent(): void
    {
        $otherProductId = $this->createProductSharingIngredient();
        DB::table('ingredients_stock')->where('id', $this->ingredientId)->update([
            'current_stock' => 20000, 'max_stock' => 200000,
        ]);

        $this->postJson('/api/orders', [
            'items' => [
                ['id' => $this->productId, 'qty' => 5, 'size' => 'M'],
                ['id' => $otherProductId, 'qty' => 5, 'size' => 'M'],
            ],
            'payment_method' => 'qris',
        ])->assertCreated()->assertJsonPath('total', 120000);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('ingredients_stock', ['id' => $this->ingredientId, 'current_stock' => 10000]);
    }

    private function createProductSharingIngredient(): int
    {
        $productId = DB::table('products')->insertGetId([
            'name' => 'Kopi Uji Lain', 'category' => 'Coffee', 'item_type' => 'satuan',
            'price_m' => 12000, 'price_l' => 15000,
        ]);
        DB::table('recipes')->insert([
            'product_id' => $productId,
            'ingredients' => json_encode([
                ['name' => 'Biji Uji', 'amount_m' => 1000, 'amount_l' => 1500, 'unit' => 'gr'],
            ]),
        ]);

        return $productId;
    }

    public function test_void_restores_recipe_stock_once(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'kasir']));
        $orderId = $this->postJson('/api/orders', [
            'items' => [['id' => $this->productId, 'qty' => 1, 'size' => 'M']],
            'payment_method' => 'cash',
            'cash_received' => 12000,
        ])->assertCreated()->json('order_id');
        $this->assertDatabaseHas('ingredients_stock', ['id' => $this->ingredientId, 'current_stock' => 8000]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson("/api/orders/{$orderId}/void")->assertOk();
        $this->postJson("/api/orders/{$orderId}/void")->assertUnprocessable();

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'void']);
        $this->assertDatabaseHas('ingredients_stock', ['id' => $this->ingredientId, 'current_stock' => 9000]);
    }
}
