<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_skus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('color_flavor_id')->nullable()->constrained('color_flavor')->onDelete('set null');
            $table->foreignId('size_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('empaque_id')->nullable()->constrained()->onDelete('set null'); // 👈 nuevo
            $table->string('code')->unique();
            $table->string('name')->nullable();
            $table->decimal('sell_price', 12, 2)->nullable();;
            $table->integer('stock')->default(0)->nullable();;
            $table->boolean('offer')->default(false);
            $table->decimal('offer_price', 12, 2)->nullable();
            $table->timestamps();

            // Evitar duplicados de combinación (producto, color, tamaño, empaque)
            $table->unique(['product_id', 'color_flavor_id', 'size_id', 'empaque_id'], 'unique_product_variant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_skus');
    }
};