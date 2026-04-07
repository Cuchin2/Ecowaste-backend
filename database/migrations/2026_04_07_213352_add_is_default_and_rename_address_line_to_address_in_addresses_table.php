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
        Schema::table('addresses', function (Blueprint $table) {
            // Renombrar address_line a address (si la columna existe)
            if (Schema::hasColumn('addresses', 'address_line')) {
                $table->renameColumn('address_line', 'address');
            } else {
                // Si no existe, la creamos (por si acaso)
                $table->string('address')->after('name');
            }

            // Agregar is_default (booleano, false por defecto)
            $table->boolean('is_default')->default(false)->after('reference');

            // Agregar verified (booleano, false por defecto)
            $table->boolean('verified')->default(false)->after('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['is_default', 'verified']);
            $table->renameColumn('address', 'address_line');
        });
    }
};
