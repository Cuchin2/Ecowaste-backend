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
        Schema::create('color_flavor', function (Blueprint $table) {
            $table->id();
            $table->string('name');                   // Ej: "Amarillo", "Fresa"
            $table->string('hex', 7)->nullable();     // #DADADA (solo para colores)
            $table->string('code', 2)->unique();      // código alfanumérico 2 dígitos
            $table->enum('type', ['color', 'sabor']); // distingue si es color o sabor
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('color_flavor');
    }
};
