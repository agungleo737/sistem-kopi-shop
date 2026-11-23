<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private const PRODUCT = ['name' => 'Menu Uji', 'category' => 'Coffee', 'price_m' => 12000, 'price_l' => 15000, 'stock' => 20];

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('settings')->insert(['key' => 'catalog_version', 'value' => 'versikopi-official-v2']);
    }

    public function test_admin_uploads_and_replaces_a_product_photo(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $first = UploadedFile::fake()->image('first.png', 100, 100);
        $id = $this->post('/api/products', self::PRODUCT + ['photo' => $first], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $url = $this->getJson('/api/products')->assertOk()->json('0.image_url');
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertContent($first->get());

        $second = UploadedFile::fake()->image('second.jpg', 80, 80);
        $this->post("/api/products/{$id}", self::PRODUCT + ['_method' => 'PUT', 'photo' => $second], ['Accept' => 'application/json'])->assertOk();
        $replacement = $this->getJson('/api/products')->assertOk()->json('0.image_url');
        $this->assertNotSame($url, $replacement);
        $this->get($replacement)->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertContent($second->get());
        $this->putJson("/api/products/{$id}", self::PRODUCT)->assertOk();
        $this->getJson('/api/products')->assertJsonPath('0.image_url', $replacement);
    }

    public function test_invalid_photo_does_not_create_a_product(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->post('/api/products', self::PRODUCT + ['photo' => UploadedFile::fake()->create('photo.pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->assertDatabaseMissing('products', ['name' => 'Menu Uji']);
    }

    public function test_admin_can_restore_a_menu_without_exposing_inactive_menus_to_cashiers(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $id = $this->postJson('/api/products', self::PRODUCT)->assertCreated()->json('id');
        $this->patchJson("/api/products/{$id}/status", ['active' => false])->assertOk();
        $this->getJson('/api/admin/products')->assertJsonPath('0.id', $id)->assertJsonPath('0.active', 0);
        $this->getJson('/api/products')->assertExactJson([]);

        Sanctum::actingAs(User::factory()->create(['role' => 'kasir']));
        $this->getJson('/api/admin/products')->assertForbidden();
        $this->patchJson("/api/products/{$id}/status", ['active' => true])->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->patchJson("/api/products/{$id}/status", ['active' => true])->assertOk();
        $this->getJson('/api/products')->assertJsonPath('0.id', $id)->assertJsonPath('0.active', 1);
    }

    public function test_a_bundle_is_unavailable_while_a_component_is_inactive(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $component = $this->postJson('/api/products', self::PRODUCT)->assertCreated()->json('id');
        $bundle = $this->postJson('/api/products', [
            'name' => 'Paket Uji', 'item_type' => 'paketan',
            'bundle_items' => [['product_id' => $component, 'qty' => 1]],
        ] + self::PRODUCT)->assertCreated()->json('id');

        $this->patchJson("/api/products/{$component}/status", ['active' => false])->assertOk();
        $this->getJson('/api/products')->assertExactJson([]);
        $this->postJson('/api/orders', ['payment_method' => 'qris', 'items' => [['id' => $bundle, 'qty' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->patchJson("/api/products/{$component}/status", ['active' => true])->assertOk();
        $this->getJson('/api/products')->assertJsonCount(2)->assertJsonPath('1.id', $bundle);
    }
}
