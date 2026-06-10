<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_octogons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('color_flavor_product_id');
            $table->unsignedBigInteger('octogon_id');
            $table->timestamps();

            // Claves foráneas
            $table->foreign('color_flavor_product_id', 'fk_variant_octogons_cfp')
                  ->references('id')->on('color_flavor_product')->onDelete('cascade');
            $table->foreign('octogon_id')
                  ->references('id')->on('octogons')->onDelete('cascade');

            // Evita duplicados
            $table->unique(['color_flavor_product_id', 'octogon_id'], 'variant_octogons_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_octogons');
    }
};