<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('color_flavor_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('color_flavor_id')->constrained('color_flavor')->onDelete('cascade');
            $table->unsignedInteger('order')->default(0); // 👈 Campo clave para el orden
            
            // Evitar duplicados
            $table->unique(['product_id', 'color_flavor_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('color_flavor_product');
    }
};
