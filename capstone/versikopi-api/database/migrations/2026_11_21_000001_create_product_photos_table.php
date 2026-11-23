<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_photos', function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained('products')->cascadeOnDelete();
            $table->string('mime', 32);
            $table->longText('encoded_image');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_photos');
    }
};
