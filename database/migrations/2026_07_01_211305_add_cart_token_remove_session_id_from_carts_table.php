// database/migrations/xxxx_xx_xx_xxxxxx_add_cart_token_remove_session_id_from_carts_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            // Agregar cart_token (único y nullable para migración)
            $table->string('cart_token')->unique()->nullable()->after('id');
        });

        // Opcional: asignar un token a los carritos existentes que no tengan token
        // (puedes hacerlo con un comando artisan o directamente en la migración con DB::table)
        // Ejemplo:
        // DB::table('carts')->whereNull('cart_token')->each(function ($cart) {
        //     DB::table('carts')->where('id', $cart->id)->update([
        //         'cart_token' => (string) Str::uuid(),
        //     ]);
        // });

        // Luego de tener todos los tokens, eliminar session_id
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('session_id');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->string('session_id')->nullable()->unique();
            $table->dropColumn('cart_token');
        });
    }
};