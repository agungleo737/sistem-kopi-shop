<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecipeAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_locks_when_milk_cannot_make_one_portion_even_if_coffee_remains(): void
    {
        $productId = $this->createMilkCoffee(96);

        $this->getJson('/api/products')->assertOk()
            ->assertJsonPath('0.stock', 0)->assertJsonPath('0.is_locked', true);
        $this->postJson('/api/orders', [
            'items' => [['id' => $productId, 'qty' => 1, 'size' => 'M']], 'payment_method' => 'qris',
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_logs', 0);
        $this->assertDatabaseHas('ingredients_stock', ['name' => 'Susu Uji', 'current_stock' => 96]);
        $this->assertDatabaseHas('ingredients_stock', ['name' => 'Kopi Uji', 'current_stock' => 5]);
    }

    public function test_last_medium_portion_is_sellable_below_five_percent_but_large_is_not(): void
    {
        $productId = $this->createMilkCoffee(100);

        $this->getJson('/api/products')->assertOk()
            ->assertJsonPath('0.stock_m', 1)->assertJsonPath('0.stock_l', 0)
            ->assertJsonPath('0.is_locked', false)->assertJsonPath('0.is_warning', true);
        $this->postJson('/api/orders', [
            'items' => [['id' => $productId, 'qty' => 1, 'size' => 'L']], 'payment_method' => 'qris',
        ])->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertDatabaseCount('orders', 0);

        $this->postJson('/api/orders', [
            'items' => [['id' => $productId, 'qty' => 1, 'size' => 'M']], 'payment_method' => 'qris',
        ])->assertCreated();

        $this->assertDatabaseHas('ingredients_stock', ['name' => 'Susu Uji', 'current_stock' => 0]);
        $this->assertDatabaseHas('ingredients_stock', ['name' => 'Kopi Uji', 'current_stock' => 4]);
        $this->getJson('/api/products')->assertOk()->assertJsonPath('0.is_locked', true);
    }

    public function test_missing_recipe_ingredient_blocks_the_menu_and_checkout(): void
    {
        $productId = $this->createMilkCoffee(1000);
        DB::table('ingredients_stock')->where('name', 'Susu Uji')->delete();
        DB::table('ingredients_stock')->where('name', 'Kopi Uji')->update(['max_stock' => 5]);

        $this->getJson('/api/products')->assertOk()->assertJsonPath('0.is_locked', true);
        $this->postJson('/api/orders', [
            'items' => [['id' => $productId, 'qty' => 1, 'size' => 'M']], 'payment_method' => 'qris',
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('ingredients_stock', ['name' => 'Kopi Uji', 'current_stock' => 5]);
    }

    public function test_shared_ingredients_cannot_be_oversold_by_duplicate_cart_lines(): void
    {
        $productId = $this->createMilkCoffee(200);

        $this->postJson('/api/orders', [
            'items' => [
                ['id' => $productId, 'qty' => 1, 'size' => 'M'],
                ['id' => $productId, 'qty' => 1, 'size' => 'L'],
            ],
            'payment_method' => 'qris',
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_logs', 0);
        $this->assertDatabaseHas('ingredients_stock', ['name' => 'Susu Uji', 'current_stock' => 200]);
    }

    public function test_products_without_recipes_use_real_product_stock_not_a_fabricated_capacity(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'kasir']));
        $productId = DB::table('products')->insertGetId([
            'name' => 'Snack Uji', 'category' => 'Snack', 'price_m' => 10000, 'price_l' => 10000,
            'stock' => 1, 'max_stock' => 100,
        ]);

        $this->getJson('/api/products')->assertOk()
            ->assertJsonPath('0.stock', 1)->assertJsonPath('0.is_locked', false);
        $this->postJson('/api/orders', [
            'items' => [['id' => $productId, 'qty' => 1, 'size' => 'M']], 'payment_method' => 'qris',
        ])->assertCreated();

        $this->assertDatabaseHas('products', ['id' => $productId, 'stock' => 0]);
        $this->getJson('/api/products')->assertOk()->assertJsonPath('0.is_locked', true);
    }

    public function test_large_remains_sellable_when_its_recipe_fits_but_medium_does_not(): void
    {
        $productId = $this->createMilkCoffee(100);
        DB::table('recipes')->where('product_id', $productId)->update([
            'ingredients' => json_encode([
                ['name' => 'Susu Uji', 'amount_m' => 150, 'amount_l' => 100, 'unit' => 'ml'],
                ['name' => 'Kopi Uji', 'amount_m' => 1, 'amount_l' => 1, 'unit' => 'gr'],
            ]),
        ]);

        $this->getJson('/api/products')->assertOk()
            ->assertJsonPath('0.stock_m', 0)->assertJsonPath('0.stock_l', 1)
            ->assertJsonPath('0.is_locked', false);
        $this->postJson('/api/orders', [
            'items' => [['id' => $productId, 'qty' => 1, 'size' => 'L']], 'payment_method' => 'qris',
        ])->assertCreated();

        $this->assertDatabaseHas('ingredients_stock', ['name' => 'Susu Uji', 'current_stock' => 0]);
    }

    public function test_legacy_recipe_without_large_amount_uses_medium_amount_for_checkout_and_void(): void
    {
        $productId = $this->createMilkCoffee(100);
        DB::table('recipes')->where('product_id', $productId)->update([
            'ingredients' => json_encode([
                ['name' => 'Susu Uji', 'amount_m' => 100, 'unit' => 'ml'],
                ['name' => 'Kopi Uji', 'amount_m' => 1, 'unit' => 'gr'],
            ]),
        ]);

        $orderId = $this->postJson('/api/orders', [
            'items' => [['id' => $productId, 'qty' => 1, 'size' => 'L']], 'payment_method' => 'qris',
        ])->assertCreated()->json('order_id');

        $this->assertDatabaseHas('ingredients_stock', ['name' => 'Susu Uji', 'current_stock' => 0]);
        $this->assertDatabaseHas('ingredients_stock', ['name' => 'Kopi Uji', 'current_stock' => 4]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson("/api/orders/{$orderId}/void")->assertOk();

        $this->assertDatabaseHas('ingredients_stock', ['name' => 'Susu Uji', 'current_stock' => 100]);
        $this->assertDatabaseHas('ingredients_stock', ['name' => 'Kopi Uji', 'current_stock' => 5]);
    }

    private function createMilkCoffee(int $milkStock): int
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'kasir']));
        DB::table('ingredients_stock')->insert([
            ['name' => 'Susu Uji', 'unit' => 'ml', 'current_stock' => $milkStock, 'max_stock' => 10000],
            ['name' => 'Kopi Uji', 'unit' => 'gr', 'current_stock' => 5, 'max_stock' => 1000],
        ]);
        $productId = DB::table('products')->insertGetId([
            'name' => 'Kopi Susu Uji', 'category' => 'Coffee', 'price_m' => 10000, 'price_l' => 15000,
        ]);
        DB::table('recipes')->insert([
            'product_id' => $productId,
            'ingredients' => json_encode([
                ['name' => 'Susu Uji', 'amount_m' => 100, 'amount_l' => 150, 'unit' => 'ml'],
                ['name' => 'Kopi Uji', 'amount_m' => 1, 'amount_l' => 2, 'unit' => 'gr'],
            ]),
        ]);

        return $productId;
    }
}
