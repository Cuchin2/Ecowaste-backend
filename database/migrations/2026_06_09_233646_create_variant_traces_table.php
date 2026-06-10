<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_traces', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('color_flavor_product_id');
            $table->unsignedBigInteger('trace_id');
            $table->timestamps();

            // Claves foráneas
            $table->foreign('color_flavor_product_id', 'fk_variant_traces_cfp')
                  ->references('id')->on('color_flavor_product')->onDelete('cascade');
            $table->foreign('trace_id')
                  ->references('id')->on('traces')->onDelete('cascade');

            // Evita duplicados
            $table->unique(['color_flavor_product_id', 'trace_id'], 'variant_traces_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_traces');
    }
};