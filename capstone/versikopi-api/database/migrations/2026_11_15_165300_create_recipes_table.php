<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recipes')) {
            Schema::create('recipes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
                $table->string('prep_time')->default('2 Menit');
                $table->longText('ingredients'); // JSON daftar bahan & takaran M/L
                $table->text('steps')->nullable();
                $table->string('barista_note')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
