<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PosController;
use Illuminate\Support\Facades\Route;

// autentikasi proteksi bruteforce
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// endpoint publik terbatas untuk halaman sebelum login
Route::get('/branding', [PosController::class, 'getBranding']);
Route::get('/branding/logo', [PosController::class, 'getStoreLogo']);
Route::get('/products/{id}/photo', [PosController::class, 'getProductPhoto'])->whereNumber('id');

// endpoint terproteksi
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // akses kasir dan admin
    Route::middleware('role:kasir,admin')->group(function () {
        Route::get('/settings', [PosController::class, 'getSettings']);
        Route::get('/promos', [PosController::class, 'getPromos']);
        Route::get('/products', [PosController::class, 'getProducts']);
        Route::get('/recipes', [PosController::class, 'getRecipes']);
        Route::get('/ingredients', [PosController::class, 'getIngredients']);
        Route::get('/orders', [PosController::class, 'getOrders']);
        Route::post('/orders', [PosController::class, 'storeOrder']);

        Route::post('/promos', [PosController::class, 'storePromo']);
        Route::put('/promos/{id}', [PosController::class, 'updatePromo'])->whereNumber('id');
        Route::delete('/promos/{id}', [PosController::class, 'deletePromo'])->whereNumber('id');
    });

    // khusus admin
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/products', [PosController::class, 'getManagedProducts']);
        Route::post('/products', [PosController::class, 'storeProduct']);
        Route::put('/products/{id}', [PosController::class, 'updateProduct'])->whereNumber('id');
        Route::patch('/products/{id}/status', [PosController::class, 'updateProductStatus'])->whereNumber('id');
        Route::delete('/products/{id}', [PosController::class, 'deleteProduct'])->whereNumber('id');
        Route::post('/products/{id}/restock', [PosController::class, 'restockProduct'])->whereNumber('id');

        Route::post('/recipes', [PosController::class, 'saveRecipe']);
        Route::delete('/recipes/{productId}', [PosController::class, 'deleteRecipe'])->whereNumber('productId');
        Route::post('/ingredients/restock', [PosController::class, 'restockIngredient']);
        Route::delete('/ingredients/{id}', [PosController::class, 'deleteIngredient'])->whereNumber('id');

        Route::post('/orders/{id}/void', [PosController::class, 'voidOrder'])->whereNumber('id');

        Route::get('/users', [AuthController::class, 'getUsers']);
        Route::post('/users', [AuthController::class, 'storeUser']);
        Route::put('/users/{id}', [AuthController::class, 'updateUser'])->whereNumber('id');
        Route::delete('/users/{id}', [AuthController::class, 'deleteUser'])->whereNumber('id');

        Route::get('/inventory-logs', [PosController::class, 'getInventoryLogs']);
        Route::put('/settings', [PosController::class, 'updateSettings']);
    });
});
