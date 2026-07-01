<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->string('cart_token')->nullable()->after('id');
        });

        // Opcional: asignar un token a los items existentes que no tengan token
        // (si tenían session_id, puedes generar un token por cada sesión única)
        $sessionItems = DB::table('wishlist_items')
            ->whereNotNull('session_id')
            ->select('session_id')
            ->distinct()
            ->get();

        foreach ($sessionItems as $session) {
            $token = (string) Str::uuid();
            DB::table('wishlist_items')
                ->where('session_id', $session->session_id)
                ->update(['cart_token' => $token]);
        }

        // Luego eliminar session_id
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->dropColumn('session_id');
        });

        // Hacer cart_token not nullable después de la migración
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->string('cart_token')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->string('session_id')->nullable();
            $table->dropColumn('cart_token');
        });
    }
};