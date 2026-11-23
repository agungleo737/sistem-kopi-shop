<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_branding_does_not_change_the_catalog_or_disclose_other_settings(): void
    {
        DB::table('settings')->insert([
            ['key' => 'store_name', 'value' => 'Toko Sore'],
            ['key' => 'tax_percent', 'value' => '10'],
        ]);
        DB::table('products')->insert([
            'name' => 'Produk Toko', 'category' => 'Umum', 'price_m' => 10000, 'price_l' => 10000,
        ]);

        $this->getJson('/api/branding')->assertOk()->assertExactJson(['store_name' => 'Toko Sore', 'tagline' => null, 'logo_version' => null]);
        $this->assertDatabaseHas('products', ['name' => 'Produk Toko']);
        $this->assertDatabaseMissing('settings', ['key' => 'catalog_version']);
    }

    public function test_admin_can_upload_and_remove_the_logo_with_store_settings(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $logo = UploadedFile::fake()->image('logo.png', 100, 100);

        $this->post('/api/settings', ['_method' => 'PUT', 'store_name' => 'Toko Sore', 'logo' => $logo], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('settings.store_name', 'Toko Sore')->assertJsonStructure(['settings' => ['logo_version']]);
        $this->getJson('/api/branding')->assertJsonPath('store_name', 'Toko Sore')->assertJsonMissingExact(['logo_version' => null]);
        $this->get('/api/branding/logo')->assertOk()->assertHeader('Content-Type', 'image/png')->assertContent($logo->get());

        $this->putJson('/api/settings', ['store_name' => 'Toko Sore', 'remove_logo' => true])->assertOk();
        $this->getJson('/api/branding')->assertJsonPath('logo_version', null);
        $this->get('/api/branding/logo')->assertNotFound();
    }

    public function test_unsupported_logo_does_not_change_store_settings(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->post('/api/settings', ['_method' => 'PUT', 'store_name' => 'Toko Sore', 'logo' => UploadedFile::fake()->create('logo.pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('logo');
        $this->assertDatabaseMissing('settings', ['key' => 'store_name']);
    }

    public function test_cashier_cannot_change_branding(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'kasir']));
        $this->putJson('/api/settings', ['store_name' => 'Toko Sore', 'remove_logo' => true])->assertForbidden();
    }
}
