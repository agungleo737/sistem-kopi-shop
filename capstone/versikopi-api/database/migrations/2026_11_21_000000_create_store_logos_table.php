<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_logos', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('mime', 32);
            $table->string('version', 64);
            $table->longText('encoded_image');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_logos');
    }
};
