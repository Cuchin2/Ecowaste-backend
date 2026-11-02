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
        Schema::table('brands', function (Blueprint $table) {
            //
            $table->boolean('is_active')->default(false)->after('order');
            $table->index(['is_active', 'order', 'name'], 'brands_active_order_name_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            //
            // Eliminamos el índice primero (buena práctica)
            $table->dropIndex('brands_active_order_name_index');
            
            // Eliminamos la columna
            $table->dropColumn('is_active');
        });
    }
};
