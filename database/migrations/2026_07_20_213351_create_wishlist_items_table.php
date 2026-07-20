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
        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wishlist_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_sku_id')->constrained('product_skus')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->text('note')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
            
            $table->unique(['wishlist_id', 'product_sku_id']);
            $table->index(['wishlist_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wishlist_items');
    }
};
