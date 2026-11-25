<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortionMenuTest extends TestCase
{
    use RefreshDatabase;

    private const FOOD = ['name' => 'Dimsum Porsi', 'category' => 'Snack', 'item_type' => 'perporsi', 'price_m' => 13000, 'stock' => 5];

    public function test_admin_creates_portion_food_with_one_price_and_no_recipe(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $id = $this->postJson('/api/products', self::FOOD)->assertCreated()->json('id');

        $this->assertDatabaseHas('products', ['id' => $id, 'item_type' => 'perporsi', 'stock' => 5, 'price_m' => 13000, 'price_l' => 13000]);
        $this->assertDatabaseCount('recipes', 0);
        $this->assertDatabaseCount('ingredients_stock', 0);
        $this->getJson('/api/products')->assertOk()->assertJsonPath('0.stock', 5)->assertJsonPath('0.recipe_summary', []);
    }

    public function test_portion_stock_is_sold_without_large_price_or_recipe_deduction_and_restored_once(): void
    {
        $id = $this->createFoodWithObsoleteRecipe();

        $orderId = $this->postJson('/api/orders', [
            'items' => [['id' => $id, 'qty' => 5, 'size' => 'L']], 'payment_method' => 'qris',
        ])->assertCreated()->assertJsonPath('total', 65000)->assertJsonPath('items.0.size', 'M')->json('order_id');

        $this->assertDatabaseHas('products', ['id' => $id, 'stock' => 0]);
        $this->assertDatabaseHas('ingredients_stock', ['name' => 'Bahan Lama', 'current_stock' => 1000]);
        $this->getJson('/api/products')->assertJsonPath('0.is_locked', true);
        $this->postJson("/api/orders/{$orderId}/void")->assertOk();
        $this->postJson("/api/orders/{$orderId}/void")->assertUnprocessable();
        $this->assertDatabaseHas('products', ['id' => $id, 'stock' => 5]);
        $this->assertDatabaseHas('ingredients_stock', ['name' => 'Bahan Lama', 'current_stock' => 1000]);
    }

    public function test_portion_food_rejects_recipes_and_hides_obsolete_recipes(): void
    {
        $id = $this->createFoodWithObsoleteRecipe();

        $this->getJson('/api/recipes')->assertOk()->assertJsonPath('recipes', []);
        $this->getJson('/api/products')->assertOk()->assertJsonPath('0.recipe_summary', []);
        $this->postJson('/api/recipes', [
            'product_id' => $id,
            'ingredients' => [['name' => 'Bahan Baru', 'amount_m' => '100', 'amount_l' => '100', 'unit' => 'gr']],
        ])->assertUnprocessable()->assertJsonValidationErrors('product_id');

        $this->assertDatabaseMissing('ingredients_stock', ['name' => 'Bahan Baru']);
    }

    public function test_portion_stock_is_shared_between_bundle_and_standalone_sales(): void
    {
        $id = $this->createFoodWithObsoleteRecipe();
        $bundleId = DB::table('products')->insertGetId([
            'name' => 'Paket Dimsum', 'category' => 'Bundling', 'item_type' => 'paketan',
            'price_m' => 25000, 'price_l' => 25000, 'stock' => 0,
            'bundle_items' => json_encode([['product_id' => $id, 'qty' => 2]]),
        ]);

        $this->getJson('/api/products')->assertJsonCount(2)->assertJsonPath('1.stock', 2)
            ->assertJsonPath('1.portion_items.0.product_id', $id)->assertJsonPath('1.portion_items.0.qty', 2);
        $orderId = $this->postJson('/api/orders', [
            'items' => [['id' => $bundleId, 'qty' => 2], ['id' => $id, 'qty' => 1]], 'payment_method' => 'qris',
        ])->assertCreated()->json('order_id');

        $this->assertDatabaseHas('products', ['id' => $id, 'stock' => 0]);
        $this->assertDatabaseHas('ingredients_stock', ['name' => 'Bahan Lama', 'current_stock' => 1000]);
        $this->postJson("/api/orders/{$orderId}/void")->assertOk();
        $this->assertDatabaseHas('products', ['id' => $id, 'stock' => 5]);
    }

    public function test_bundle_and_standalone_cannot_oversell_portions(): void
    {
        $id = $this->createFoodWithObsoleteRecipe();
        $bundleId = DB::table('products')->insertGetId([
            'name' => 'Paket Dimsum', 'category' => 'Bundling', 'item_type' => 'paketan',
            'price_m' => 25000, 'price_l' => 25000,
            'bundle_items' => json_encode([['product_id' => $id, 'qty' => 2]]),
        ]);

        $this->postJson('/api/orders', [
            'items' => [['id' => $bundleId, 'qty' => 2], ['id' => $id, 'qty' => 2]], 'payment_method' => 'qris',
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('products', ['id' => $id, 'stock' => 5]);
    }

    public function test_admin_can_convert_existing_food_and_set_portion_stock_without_changing_material_stock(): void
    {
        $id = $this->createFoodWithObsoleteRecipe();
        DB::table('products')->where('id', $id)->update(['item_type' => 'satuan']);

        $this->putJson("/api/products/{$id}", ['stock' => 8, 'price_m' => 14000] + self::FOOD)->assertOk();

        $this->assertDatabaseHas('products', ['id' => $id, 'stock' => 8, 'price_m' => 14000, 'price_l' => 14000, 'item_type' => 'perporsi']);
        $this->assertDatabaseHas('ingredients_stock', ['name' => 'Bahan Lama', 'current_stock' => 1000]);
    }

    private function createFoodWithObsoleteRecipe(): int
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $id = DB::table('products')->insertGetId(self::FOOD + ['price_l' => 99000]);
        DB::table('ingredients_stock')->insert(['name' => 'Bahan Lama', 'unit' => 'gr', 'current_stock' => 1000, 'max_stock' => 1000]);
        DB::table('recipes')->insert([
            'product_id' => $id,
            'ingredients' => json_encode([['name' => 'Bahan Lama', 'amount_m' => 100, 'amount_l' => 200, 'unit' => 'gr']]),
        ]);

        return $id;
    }
}
