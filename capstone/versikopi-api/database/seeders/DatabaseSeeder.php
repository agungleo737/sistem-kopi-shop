<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = trim((string) config('pos.initial_admin.email', ''));
        $adminPassword = (string) config('pos.initial_admin.password', '');

        if (($adminEmail === '') !== ($adminPassword === '')) {
            throw new \InvalidArgumentException('Set both VERSIKOPI_ADMIN_EMAIL and VERSIKOPI_ADMIN_PASSWORD to provision the initial admin.');
        }

        if ($adminEmail !== '') {
            if (! filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || strlen($adminPassword) < 12) {
                throw new \InvalidArgumentException('The initial admin email must be valid and the password must contain at least 12 characters.');
            }

            User::firstOrCreate(
                ['email' => Str::lower($adminEmail)],
                [
                    'name' => 'Admin Versi Kopi',
                    'password' => Hash::make($adminPassword),
                    'role' => 'admin',
                ],
            );
        }

        // pengaturan toko resmi versi kopi
        $settings = [
            'store_name' => 'VERSI KOPI',
            'tagline' => 'Bukan mood booster, ini mood reset',
            'address' => 'Jl. Inpres XI No.34, RT.005/RW.008, Larangan Utara, Kec. Larangan, Kota Tangerang, Banten',
            'phone' => '089612993256',
            'tax_percent' => '0',
            'service_percent' => '0',
            'receipt_footer' => 'Terima kasih telah berkunjung ke Versi Kopi (@Versi_Kopi)',
            'custom_note' => 'Buka Setiap Hari 13.00 - 23.15 WIB | @Versi_Kopi',
            'qris_merchant' => 'Versi Kopi',
            'auto_print' => '1',
            'notification_sound' => '1',

        ];
        foreach ($settings as $key => $value) {
            DB::table('settings')->insertOrIgnore([
                'key' => $key, 'value' => $value, 'updated_at' => now(), 'created_at' => now(),
            ]);
        }

        // sinkronisasi katalog menu, harga, foto, dan resep resmi
        $this->call(OfficialCatalogSeeder::class);

        // promo toko
        $promos = [
            ['NGOPI15', 'percent', 15, 30000, 10000, 1],
            ['HEMAT5K', 'fixed', 5000, 25000, 0, 1],
            ['WEEKEND20', 'percent', 20, 50000, 15000, 1],
        ];

        foreach ($promos as $promo) {
            DB::table('promos')->insertOrIgnore([
                'code' => $promo[0],
                'type' => $promo[1],
                'value' => $promo[2],
                'min_subtotal' => $promo[3],
                'max_discount' => $promo[4],
                'active' => $promo[5],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
