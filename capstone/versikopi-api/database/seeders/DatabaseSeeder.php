<?php
namespace Database\Seeders;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Default
        $accounts = [
            ['name' => 'Admin Versi Kopi', 'email' => 'admin@versikopi.test', 'role' => 'admin'],
            ['name' => 'Kasir Versi Kopi', 'email' => 'kasir@versikopi.test', 'role' => 'kasir'],
        ];

        foreach ($accounts as $a) {
            $user = User::firstOrNew(['email' => $a['email']]);
            $user->name = $a['name'];
            $user->password = Hash::make('password'); // Password login: password
            $user->role = $a['role'];
            $user->save();
        }

        // 2. Settings Toko
        $settings = [
            'store_name'         => 'VERSI KOPI',
            'tagline'            => 'Secangkir cerita di setiap tegukan',
            'address'            => 'Jl. Senja Raya No. 17, Bandung',
            'phone'              => '0812-3456-7890',
            'tax_percent'        => '0',
            'service_percent'    => '0',
            'receipt_footer'     => 'Terima kasih sudah ngopi di Versi Kopi ☕',
            'custom_note'        => 'Buka setiap hari 08.00 - 22.00 WIB',
            'qris_merchant'      => 'Versi Kopi',
            'auto_print'         => '1',
            'notification_sound' => '1',
        ];
        foreach ($settings as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // 3. Menu Kopi & Makanan
        $products = [
            ['Kopi Susu Gula Aren', 'Signature', 'Espresso, susu segar, dan gula aren legit yang bikin nagih.', 22000, 27000, 60, '🧋', '#C08552,#6F4E37'],
            ['Americano', 'Coffee', 'Espresso dengan air mineral. Ringan, bersih, dan fokus.', 20000, 25000, 80, '☕', '#8B5E3C,#3E2723'],
            ['Cappuccino', 'Coffee', 'Espresso, susu steamed, dan foam tebal yang lembut.', 25000, 30000, 55, '☕', '#B08968,#5C3D2E'],
            ['Caffè Latte', 'Coffee', 'Perpaduan espresso dan susu creamy dengan latte art.', 25000, 30000, 55, '🥛', '#C9A227,#6F4E37'],
            ['Mocha Latte', 'Coffee', 'Cokelat premium bertemu espresso dan susu segar.', 28000, 33000, 45, '🍫', '#7B4B3A,#2E1A15'],
            ['Espresso', 'Coffee', 'Satu shot pekat untuk kamu yang butuh energi instan.', 18000, 22000, 100, '☕', '#6F4E37,#2B1D16'],
            ['Matcha Latte', 'Non-Coffee', 'Matcha Jepang grade premium dengan susu creamy.', 28000, 33000, 40, '🍵', '#A3B18A,#4F6F52'],
            ['Chocolate', 'Non-Coffee', 'Cokelat hitam cair dengan susu hangat, manis pas.', 26000, 31000, 48, '🍫', '#A9746E,#4A2C2A'],
            ['Strawberry Tea', 'Non-Coffee', 'Teh hitam segar dengan stroberi asli dan es batu.', 24000, 29000, 42, '🍓', '#E67E7E,#A93F55'],
            ['Croissant Butter', 'Pastry', 'Croissant butter panggang, renyah di luar lembut di dalam.', 22000, 22000, 25, '🥐', '#E0A96D,#B5651D'],
            ['Donut Glaze', 'Pastry', 'Donut empuk dengan lapisan gula manis klasik.', 15000, 15000, 30, '🍩', '#D9A5B3,#A9556D'],
            ['French Fries', 'Snack', 'Kentang goreng renyah dengan saus rumput laut.', 20000, 25000, 40, '🍟', '#E8B44F,#B07D1A']
        ];
        foreach ($products as $p) {
            $prodId = DB::table('products')->insertGetId([
                'name'        => $p[0],
                'category'    => $p[1],
                'description' => $p[2],
                'price_m'     => $p[3],
                'price_l'     => $p[4],
                'stock'       => $p[5],
                'emoji'       => $p[6],
                'gradient'    => $p[7],
                'active'      => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
            DB::table('inventory_logs')->insert([
                'product_id'   => $prodId,
                'product_name' => $p[0],
                'type'         => 'in',
                'qty'          => $p[5],
                'stock_after'  => $p[5],
                'note'         => 'Stok awal saat toko dibuka',
                'reference'    => 'STOK-AWAL',
                'actor_name'   => 'Admin Versi Kopi',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
        // 4. Promo
        $promos = [
            ['NGOPI15', 'percent', 15, 50000, 15000, 1],
            ['HEMAT5K', 'fixed', 5000, 30000, 0, 1],
            ['WEEKEND20', 'percent', 20, 75000, 25000, 0],
        ];

        foreach ($promos as $promo) {
            DB::table('promos')->insert([
                'code'         => $promo[0],
                'type'         => $promo[1],
                'value'        => $promo[2],
                'min_subtotal' => $promo[3],
                'max_discount' => $promo[4],
                'active'       => $promo[5],
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }
}