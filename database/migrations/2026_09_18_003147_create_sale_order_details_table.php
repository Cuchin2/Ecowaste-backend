<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_order_details', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('sale_order_id')->constrained('sale_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            // Datos SNAPSHOT (Instantánea del momento de la compra)
            $table->string('name'); // Nombre del producto/sku
            $table->string('brand')->nullable();
            $table->string('image')->nullable(); // URL de la imagen
            $table->integer('quantity');
            $table->decimal('sell_price', 10, 2); // Precio al momento de la venta
            
            // Datos descriptivos
            $table->string('color_flavor')->nullable(); // Ej: "Talla - Ro1"
            $table->string('slug');
            $table->string('sku'); // Código legible del SKU
            $table->unsignedBigInteger('sku_id'); // Referencia al SKU original
            $table->unsignedBigInteger('product_id'); // Referencia al producto original
            
            $table->timestamps();
            
            // Índices para búsquedas rápidas en el historial
            $table->index(['sale_order_id', 'user_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_order_details');
    }
};