<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockWarningTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_warning_uses_percentage_not_number_of_portions(): void
    {
        DB::table('settings')->insert(['key' => 'catalog_version', 'value' => 'versikopi-official-v2']);
        $this->getJson('/api/products')->assertOk();

        DB::table('ingredients_stock')->insert([
            'name' => 'Susu', 'unit' => 'ml', 'current_stock' => 8760, 'max_stock' => 9000,
        ]);
        $productId = DB::table('products')->insertGetId([
            'name' => 'Paket Uji', 'category' => 'Coffee', 'price_m' => 10000, 'price_l' => 10000,
        ]);
        DB::table('recipes')->insert([
            'product_id' => $productId,
            'ingredients' => json_encode([['name' => 'Susu', 'unit' => 'ml', 'amount_m' => 600, 'amount_l' => 600]]),
        ]);

        $this->getJson('/api/products')->assertOk()
            ->assertJsonPath('0.stock', 14)
            ->assertJsonPath('0.stock_percent', 97)
            ->assertJsonPath('0.is_warning', false);

        DB::table('ingredients_stock')->where('name', 'Susu')->update(['current_stock' => 1800]);
        DB::table('recipes')->where('product_id', $productId)->update([
            'ingredients' => json_encode([['name' => 'Susu', 'unit' => 'ml', 'amount_m' => 100, 'amount_l' => 100]]),
        ]);

        $this->getJson('/api/products')->assertOk()
            ->assertJsonPath('0.stock', 18)
            ->assertJsonPath('0.stock_percent', 20)
            ->assertJsonPath('0.is_warning', true);
    }
}
