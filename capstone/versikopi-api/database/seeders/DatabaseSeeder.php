<?php

namespace Database\Seeders;

use App\Http\Controllers\PosController;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // akun default
        $accounts = [
            ['name' => 'Admin Versi Kopi', 'email' => 'admin@versikopi.test', 'role' => 'admin'],
            ['name' => 'Kasir Versi Kopi', 'email' => 'kasir@versikopi.test', 'role' => 'kasir'],
        ];

        foreach ($accounts as $a) {
            $user = User::firstOrNew(['email' => $a['email']]);
            $user->name = $a['name'];
            $user->password = Hash::make('password');
            $user->role = $a['role'];
            $user->save();
        }

        // pengaturan toko resmi versi kopi
        $settings = [
            'store_name'         => 'VERSI KOPI',
            'tagline'            => 'Bukan mood booster, ini mood reset',
            'address'            => 'Jl. Inpres XI No.34, RT.005/RW.008, Larangan Utara, Kec. Larangan, Kota Tangerang, Banten',
            'phone'              => '089612993256',
            'tax_percent'        => '0',
            'service_percent'    => '0',
            'receipt_footer'     => 'Terima kasih telah berkunjung ke Versi Kopi (@Versi_Kopi)',
            'custom_note'        => 'Buka Setiap Hari 13.00 - 23.15 WIB | @Versi_Kopi',
            'qris_merchant'      => 'Versi Kopi',
            'auto_print'         => '1',
            'notification_sound' => '1',
            'catalog_version'    => 'force-resync',
        ];
        foreach ($settings as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // sinkronisasi katalog menu, harga, foto, dan resep resmi
        app(PosController::class)->getProducts();

        // promo toko
        $promos = [
            ['NGOPI15', 'percent', 15, 30000, 10000, 1],
            ['HEMAT5K', 'fixed', 5000, 25000, 0, 1],
            ['WEEKEND20', 'percent', 20, 50000, 15000, 1],
        ];

        foreach ($promos as $promo) {
            DB::table('promos')->updateOrInsert(
                ['code' => $promo[0]],
                [
                    'type'         => $promo[1],
                    'value'        => $promo[2],
                    'min_subtotal' => $promo[3],
                    'max_discount' => $promo[4],
                    'active'       => $promo[5],
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]
            );
        }
    }
}
