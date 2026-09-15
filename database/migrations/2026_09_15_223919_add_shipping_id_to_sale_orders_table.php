<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            // Agregamos shipping_id como nullable (una orden puede no tener envío aún)
            $table->unsignedBigInteger('shipping_id')->nullable()->after('status');
            
            // Relación de clave foránea
            $table->foreign('shipping_id')->references('id')->on('shippings')->nullOnDelete();
            
            // Índice para mejorar el rendimiento de las consultas
            $table->index('shipping_id');
        });
    }

    public function down(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->dropForeign(['shipping_id']);
            $table->dropColumn('shipping_id');
        });
    }
};