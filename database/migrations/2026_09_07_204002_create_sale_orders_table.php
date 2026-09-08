<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_orders', function (Blueprint $table) {
            $table->id();
            
            // Relación con usuario
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id'); // 👈 Mejora de rendimiento

            // Estado y totales
            $table->enum('status', ['CREATE', 'PAID', 'CANCEL', 'TRACKING', 'DONE'])->default('CREATE');
            $table->decimal('total', 10, 2); // 👈 Aumentado a 10,2 para soportar montos más altos si es necesario
            
            // Datos de facturación
            $table->string('name');
            $table->string('last_name');
            $table->string('business')->nullable();
            $table->string('document_type');
            $table->string('dni');
            $table->string('phone');          // 👈 FALTABA
            $table->string('email');          // 👈 FALTABA
            
            // Ubicación de facturación
            $table->string('country');
            $table->string('address');
            $table->string('reference')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('district');
            $table->string('zip_code')->nullable();
            
            // Campos adicionales de tu lógica original
            $table->string('currency', 3)->default('PEN'); // 👈 FALTABA
            $table->boolean('delivery')->default(0);       // 👈 FALTABA (guarda si es envío diferente)
            
            $table->timestamps();
            $table->softDeletes(); // 👈 RECOMENDADO: Permite "borrar" órdenes sin perder el historial
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_orders');
    }
};