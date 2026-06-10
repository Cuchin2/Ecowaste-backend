<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_aptitudes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('color_flavor_product_id');
            $table->unsignedBigInteger('aptitude_id');
            $table->timestamps();

            // Claves foráneas
            $table->foreign('color_flavor_product_id', 'fk_variant_aptitudes_cfp')
                  ->references('id')->on('color_flavor_product')->onDelete('cascade');
            $table->foreign('aptitude_id')
                  ->references('id')->on('aptitudes')->onDelete('cascade');

            // Evita duplicados
            $table->unique(['color_flavor_product_id', 'aptitude_id'], 'variant_aptitudes_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_aptitudes');
    }
};