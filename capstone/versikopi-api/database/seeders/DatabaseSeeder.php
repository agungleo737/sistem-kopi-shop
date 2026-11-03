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

        $settings = [
            'store_name'         => 'VERSI KOPI',
            'auto_print'         => '1',
            'notification_sound' => '1',
        ];

        foreach ($settings as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}