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
        Schema::create('variant_octogons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('color_flavor_product_id');
            $table->unsignedBigInteger('variant_octogon_id');
            $table->foreign('color_flavor_product_id', 'fk_variant_variant_octogons_pivot')
                  ->references('id')->on('color_flavor_product')->onDelete('cascade');
            $table->foreign('variant_octogon_id')
                  ->references('id')->on('variant_octogons')->onDelete('cascade');  
            $table->unique(['color_flavor_product_id', 'variant_octogon_id'], 'variant_variant_octogons_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variant_octogons');
    }
};
