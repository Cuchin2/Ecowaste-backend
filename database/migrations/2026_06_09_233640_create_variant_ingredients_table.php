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
        Schema::create('variant_ingredients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('color_flavor_product_id');
            $table->unsignedBigInteger('ingredient_id');
            $table->foreign('color_flavor_product_id', 'fk_variant_ingredients_pivot')
                  ->references('id')->on('color_flavor_product')->onDelete('cascade');
            $table->foreign('ingredient_id')
                  ->references('id')->on('ingredients')->onDelete('cascade');
            
            $table->unique(['color_flavor_product_id', 'ingredient_id'], 'variant_ingredients_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variant_ingredients');
    }
};
