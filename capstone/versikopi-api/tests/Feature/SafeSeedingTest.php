<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SafeSeedingTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_without_provisioning_values_never_creates_default_accounts(): void
    {
        config(['pos.initial_admin.email' => null, 'pos.initial_admin.password' => null]);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('products', 27);
        $this->assertDatabaseCount('recipes', 21);
        $this->assertDatabaseCount('ingredients_stock', 13);
    }

    public function test_reseeding_preserves_passwords_custom_prices_stock_and_settings(): void
    {
        config(['pos.initial_admin.email' => 'owner@example.test', 'pos.initial_admin.password' => 'initial-test-password']);
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'owner@example.test')->firstOrFail();
        $user->update(['password' => Hash::make('changed-test-password')]);
        $productId = DB::table('products')->min('id');
        $ingredientId = DB::table('ingredients_stock')->min('id');
        DB::table('products')->where('id', $productId)->update(['price_m' => 12345]);
        DB::table('ingredients_stock')->where('id', $ingredientId)->update(['current_stock' => 123]);
        DB::table('settings')->where('key', 'store_name')->update(['value' => 'Toko Pemilik']);

        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(Hash::check('changed-test-password', $user->fresh()->password));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('products', 27);
        $this->assertDatabaseHas('products', ['id' => $productId, 'price_m' => 12345]);
        $this->assertDatabaseHas('ingredients_stock', ['id' => $ingredientId, 'current_stock' => 123]);
        $this->assertDatabaseHas('settings', ['key' => 'store_name', 'value' => 'Toko Pemilik']);
    }
}
