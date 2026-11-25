<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'item_type')) {
                $table->string('item_type')->default('satuan')->after('category');
            }
            if (! Schema::hasColumn('products', 'bundle_items')) {
                $table->text('bundle_items')->nullable()->after('description');
            }
            if (! Schema::hasColumn('products', 'max_stock')) {
                $table->integer('max_stock')->default(60)->after('stock');
            }
            if (! Schema::hasColumn('products', 'image_url')) {
                $table->string('image_url')->nullable()->after('gradient');
            }
        });

        DB::statement('UPDATE products SET max_stock = CASE WHEN stock > 60 THEN stock ELSE 60 END WHERE max_stock IS NULL OR max_stock <= 0');

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'customer_name')) {
                $table->string('customer_name')->nullable()->after('cashier_name');
            }
            if (! Schema::hasColumn('orders', 'order_type')) {
                $table->string('order_type')->default('dine_in')->after('customer_name');
            }
            if (! Schema::hasColumn('orders', 'table_number')) {
                $table->string('table_number')->nullable()->after('order_type');
            }
            if (! Schema::hasColumn('orders', 'status')) {
                $table->string('status')->default('paid')->after('change_amount');
            }
        });

        if (! Schema::hasTable('ingredients_stock')) {
            Schema::create('ingredients_stock', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->string('unit')->default('ml');
                $table->float('current_stock')->default(0);
                $table->float('max_stock')->default(1000);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'This migration is forward-only because it may adopt existing POS tables and columns. Use a reviewed corrective migration instead of deleting store data.'
        );
    }
};
