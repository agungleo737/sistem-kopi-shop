<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->text('description')->nullable();
            $table->integer('price_m');
            $table->integer('price_l');
            $table->integer('stock')->default(0);
            $table->string('emoji')->default('☕');
            $table->string('gradient')->default('#C08552,#6F4E37');
            $table->boolean('active')->default(1);
            $table->timestamps();
        });

        Schema::create('promos', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('type', ['percent', 'fixed']);
            $table->integer('value');
            $table->integer('min_subtotal')->default(0);
            $table->integer('max_discount')->default(0);
            $table->boolean('active')->default(1);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('cashier_id')->nullable();
            $table->string('cashier_name');
            $table->longText('items'); // JSON daftar item
            $table->integer('subtotal');
            $table->integer('discount')->default(0);
            $table->string('promo_code')->nullable();
            $table->integer('tax')->default(0);
            $table->integer('service')->default(0);
            $table->integer('total');
            $table->enum('payment_method', ['cash', 'qris']);
            $table->integer('cash_received')->default(0);
            $table->integer('change_amount')->default(0);
            $table->string('status')->default('paid');
            $table->timestamps();
        });

        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable();
            $table->string('product_name');
            $table->enum('type', ['in', 'out']);
            $table->integer('qty');
            $table->integer('stock_after');
            $table->string('note')->nullable();
            $table->string('reference')->nullable();
            $table->string('actor_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_logs');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('promos');
        Schema::dropIfExists('products');
    }
};