<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_does_not_create_public_default_accounts(): void
    {
        $this->postJson('/api/login', [
            'email' => 'admin@versikopi.test',
            'password' => 'password',
            'role' => 'admin',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_inventory_recipes_settings_and_products_require_authentication(): void
    {
        foreach (['/api/settings', '/api/ingredients', '/api/recipes', '/api/products'] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
    }

    public function test_cashier_can_read_pos_data_but_cannot_read_admin_routes(): void
    {
        DB::table('settings')->insert(['key' => 'catalog_version', 'value' => 'versikopi-official-v2']);
        Sanctum::actingAs(User::factory()->create(['role' => 'kasir']));

        $this->getJson('/api/settings')->assertOk();
        $this->getJson('/api/ingredients')->assertOk();
        $this->getJson('/api/recipes')->assertOk();
        $this->getJson('/api/products')->assertOk();
        $this->getJson('/api/admin/products')->assertForbidden();
        $this->putJson('/api/settings', ['store_name' => 'Tidak Boleh'])->assertForbidden();
    }

    public function test_reading_products_never_seeds_or_replaces_the_catalog(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        DB::table('products')->insert([
            'name' => 'Menu Milik Toko', 'category' => 'Coffee', 'price_m' => 10000, 'price_l' => 15000,
        ]);

        $this->getJson('/api/products')->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Menu Milik Toko');
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('ingredients_stock', 0);
        $this->assertDatabaseMissing('settings', ['key' => 'catalog_version']);
    }

    public function test_login_issues_token_and_logout_revokes_it(): void
    {
        $user = User::factory()->create(['role' => 'kasir']);
        $token = $this->postJson('/api/login', [
            'email' => $user->email, 'password' => 'password', 'role' => 'kasir',
        ])->assertOk()->assertJsonPath('user.id', $user->id)->json('token');

        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('id', $user->id);
        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_rejects_wrong_role_and_rate_limits_failed_passwords(): void
    {
        $user = User::factory()->create(['role' => 'kasir']);
        $this->postJson('/api/login', [
            'email' => $user->email, 'password' => 'password', 'role' => 'admin',
        ])->assertForbidden();

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->postJson('/api/login', [
                'email' => $user->email, 'password' => 'incorrect', 'role' => 'kasir',
            ])->assertUnprocessable();
        }
        $this->postJson('/api/login', [
            'email' => $user->email, 'password' => 'password', 'role' => 'kasir',
        ])->assertStatus(429);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_expired_password_reset_code_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'kasir']);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email, 'token' => Hash::make('123456'), 'created_at' => now()->subMinutes(11),
        ]);

        $this->postJson('/api/reset-password', [
            'email' => $user->email, 'code' => '123456',
            'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ])->assertUnprocessable();
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_reset_revokes_old_tokens_and_cannot_be_reused(): void
    {
        $user = User::factory()->create(['role' => 'kasir']);
        $user->createToken('existing-session');
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email, 'token' => Hash::make('123456'), 'created_at' => now()->subMinutes(2),
        ]);
        $payload = [
            'email' => $user->email, 'code' => '123456',
            'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ];

        $this->postJson('/api/reset-password', $payload)->assertOk();
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/reset-password', $payload)->assertUnprocessable();
    }
}
