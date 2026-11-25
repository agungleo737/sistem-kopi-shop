<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // autentikasi proteksi bruteforce
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'in:kasir,admin'],
        ]);

        $throttleKey = 'login:'.Str::lower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => "Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik.",
            ], 429);
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

        $token = $user->createToken('versikopi', ['role:'.$user->role])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => (int) $user->id,
                'name' => e($user->name),
                'email' => e($user->email),
                'role' => $user->role,
            ],
        ]);
    }

    // data user aktif
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ]);
    }

    // keluar sesi
    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    // daftar akun
    public function getUsers()
    {
        $users = User::orderBy('id', 'asc')->get(['id', 'name', 'email', 'role', 'created_at']);

        return response()->json(['users' => $users]);
    }

    // tambah akun
    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
            'role' => ['required', 'string', 'in:kasir,admin'],
        ]);

        $user = User::create([
            'name' => trim(strip_tags($data['name'])),
            'email' => Str::lower(trim($data['email'])),
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Akun {$user->name} berhasil ditambahkan.",
            'user' => $user->only(['id', 'name', 'email', 'role', 'created_at']),
        ], 201);
    }

    // ubah akun atau password
    public function updateUser(Request $request, int $id)
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['message' => 'Akun tidak ditemukan.'], 404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email,'.$id],
            'password' => ['nullable', 'string', 'min:6', 'max:255'],
            'role' => ['required', 'string', 'in:kasir,admin'],
        ]);

        if ($user->role === 'admin' && $data['role'] !== 'admin' && User::where('role', 'admin')->count() <= 1) {
            return response()->json(['message' => 'Minimal harus tersisa 1 akun admin.'], 422);
        }

        $user->name = trim(strip_tags($data['name']));
        $user->email = Str::lower(trim($data['email']));
        $user->role = $data['role'];
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
            $user->tokens()->delete();
        }
        $user->save();

        return response()->json([
            'success' => true,
            'message' => "Akun {$user->name} berhasil diperbarui.",
            'user' => $user->only(['id', 'name', 'email', 'role', 'created_at']),
        ]);
    }

    // minta kode reset password
    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
        ]);

        $email = Str::lower(trim($data['email']));
        $throttleKey = 'forgot:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => "Terlalu banyak permintaan reset. Coba lagi dalam {$seconds} detik.",
            ], 429);
        }

        RateLimiter::hit($throttleKey, 60);

        $user = User::where('email', $email)->first();
        $devCode = null;

        if ($user) {
            $code = (string) random_int(100000, 999999);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token' => Hash::make($code),
                    'created_at' => now(),
                ]
            );
            Log::info("Kode reset password Versi Kopi untuk {$email}: {$code} (berlaku 10 menit)");
            if (config('app.debug')) {
                $devCode = $code;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Jika email terdaftar, kode reset 6 digit telah dibuat (berlaku 10 menit).',
            'dev_code' => $devCode,
        ]);
    }

    // simpan password baru dengan kode reset
    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:6', 'max:255', 'confirmed'],
        ]);

        $email = Str::lower(trim($data['email']));
        $throttleKey = 'reset:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => "Terlalu banyak percobaan kode reset. Coba lagi dalam {$seconds} detik.",
            ], 429);
        }

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (
            ! $record ||
            Carbon::parse($record->created_at)->lte(now()->subMinutes(10)) ||
            ! Hash::check($data['code'], $record->token)
        ) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'message' => 'Kode reset tidak valid atau sudah kedaluwarsa.',
            ], 422);
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            return response()->json(['message' => 'Akun tidak ditemukan.'], 404);
        }

        $user->password = Hash::make($data['password']);
        $user->save();
        $user->tokens()->delete();

        DB::table('password_reset_tokens')->where('email', $email)->delete();
        RateLimiter::clear($throttleKey);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diperbarui. Silakan login dengan password baru.',
        ]);
    }

    // hapus akun
    public function deleteUser(Request $request, int $id)
    {
        if ((int) $request->user()?->id === $id) {
            return response()->json(['message' => 'Tidak dapat menghapus akun yang sedang digunakan.'], 422);
        }

        $user = User::find($id);
        if (! $user) {
            return response()->json(['message' => 'Akun tidak ditemukan.'], 404);
        }

        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return response()->json(['message' => 'Minimal harus tersisa 1 akun admin.'], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil dihapus.',
        ]);
    }
}
