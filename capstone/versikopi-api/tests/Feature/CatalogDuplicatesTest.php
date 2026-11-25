<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogDuplicatesTest extends TestCase
{
    use RefreshDatabase;

    private const MENU = ['name' => 'Kopi Susu', 'category' => 'Coffee', 'price_m' => 10000, 'price_l' => 12000, 'stock' => 10];

    public function test_duplicate_menu_names_are_rejected_after_normalizing_case_and_spaces(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $id = $this->postJson('/api/products', self::MENU)->assertCreated()->json('id');

        $this->postJson('/api/products', ['name' => '  KOPI   susu  '] + self::MENU)
            ->assertUnprocessable()->assertJsonValidationErrors('name')->assertJsonPath('errors.name.0', 'Menu sudah ada. Gunakan menu yang sudah terdaftar.');
        $this->putJson("/api/products/{$id}", self::MENU)->assertOk();

        $this->assertDatabaseCount('products', 1);
    }

    public function test_renaming_another_menu_to_an_existing_name_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson('/api/products', self::MENU)->assertCreated();
        $id = $this->postJson('/api/products', ['name' => 'Teh'] + self::MENU)->assertCreated()->json('id');

        $this->putJson("/api/products/{$id}", ['name' => 'kopi susu'] + self::MENU)->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->assertDatabaseHas('products', ['id' => $id, 'name' => 'Teh']);
    }

    public function test_duplicate_ingredient_creation_is_rejected_but_explicit_restock_is_allowed(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $id = DB::table('ingredients_stock')->insertGetId(['name' => 'Susu Segar', 'unit' => 'ml', 'current_stock' => 100, 'max_stock' => 100]);
        $payload = ['name' => ' susu   SEGAR ', 'unit' => 'ml', 'amount' => 50];

        $this->postJson('/api/ingredients/restock', $payload)->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertDatabaseHas('ingredients_stock', ['id' => $id, 'current_stock' => 100]);
        $this->postJson('/api/ingredients/restock', ['ingredient_id' => $id] + $payload)->assertOk();

        $this->assertDatabaseCount('ingredients_stock', 1);
        $this->assertDatabaseHas('ingredients_stock', ['id' => $id, 'current_stock' => 150]);
    }

    public function test_existing_recipe_requires_explicit_edit_intent_instead_of_silent_overwrite(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $productId = $this->postJson('/api/products', self::MENU)->assertCreated()->json('id');
        $payload = ['product_id' => $productId, 'ingredients' => [
            ['name' => 'Susu', 'amount_m' => '100', 'amount_l' => '150', 'unit' => 'ml'],
        ]];
        $this->postJson('/api/recipes', $payload)->assertOk();
        $recipeId = DB::table('recipes')->where('product_id', $productId)->value('id');

        $this->postJson('/api/recipes', ['steps' => 'Tidak Boleh Menimpa'] + $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('product_id');
        $this->assertDatabaseMissing('recipes', ['steps' => 'Tidak Boleh Menimpa']);
        $this->postJson('/api/recipes', ['recipe_id' => $recipeId, 'steps' => 'Edit Sah'] + $payload)->assertOk();

        $this->assertDatabaseCount('recipes', 1);
        $this->assertDatabaseHas('recipes', ['id' => $recipeId, 'steps' => 'Edit Sah']);
    }

    public function test_duplicate_ingredient_rows_in_a_recipe_are_rejected_without_partial_writes(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $productId = $this->postJson('/api/products', self::MENU)->assertCreated()->json('id');

        $this->postJson('/api/recipes', ['product_id' => $productId, 'ingredients' => [
            ['name' => 'Susu Segar', 'amount_m' => '100', 'amount_l' => '150', 'unit' => 'ml'],
            ['name' => ' susu   SEGAR ', 'amount_m' => '20', 'amount_l' => '20', 'unit' => 'ml'],
        ]])->assertUnprocessable()->assertJsonValidationErrors('ingredients');

        $this->assertDatabaseCount('recipes', 0);
        $this->assertDatabaseCount('ingredients_stock', 0);
    }
}
