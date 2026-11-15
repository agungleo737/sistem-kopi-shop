<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // autentikasi proteksi bruteforce
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:255'],
            'role'     => ['required', 'string', 'in:kasir,admin'],
        ]);

        $throttleKey = 'login:' . Str::lower($data['email']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'message' => "Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik.",
            ], 429);
        }

        if (User::count() === 0) {
            User::create([
                'name'     => 'Admin Versi Kopi',
                'email'    => 'admin@versikopi.test',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ]);
            User::create([
                'name'     => 'Kasir Versi Kopi',
                'email'    => 'kasir@versikopi.test',
                'password' => Hash::make('password'),
                'role'     => 'kasir',
            ]);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            return response()->json(['message' => 'Email atau password salah.'], 422);
        }

        if ($user->role !== $data['role']) {
            RateLimiter::hit($throttleKey, 60);
            return response()->json([
                'message' => "Akun ini tidak memiliki akses sebagai {$data['role']}.",
            ], 403);
        }

        RateLimiter::clear($throttleKey);

        // hapus token lama
        $user->tokens()->where('name', 'versikopi')->delete();

        $token = $user->createToken('versikopi', ['role:' . $user->role])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => (int) $user->id,
                'name'  => e($user->name),
                'email' => e($user->email),
                'role'  => $user->role,
            ],
        ]);
    }

    // data user aktif
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id'    => (int) $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role,
        ]);
    }

    // keluar sesi
    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }
}
