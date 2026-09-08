<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            
            // 👇 CRÍTICO: Relación con la orden de venta
            $table->unsignedBigInteger('order_id');
            $table->foreign('order_id')->references('id')->on('sale_orders')->onDelete('cascade');
            $table->index('order_id'); // 👈 Mejora de rendimiento

            // Datos de entrega
            $table->string('name');
            $table->string('last_name');
            $table->string('address');
            $table->string('reference')->nullable();
            $table->string('country');
            $table->string('city');
            $table->string('state');
            $table->string('district');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_orders');
    }
};