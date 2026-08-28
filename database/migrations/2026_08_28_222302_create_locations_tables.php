<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla de países
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('iso2', 2)->unique(); // PE, MX, AR
            $table->string('name', 100);
            $table->string('iso3', 3)->nullable();
            $table->string('phone_code', 10)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('currency_name', 50)->nullable();
            $table->string('currency_symbol', 10)->nullable();
            $table->string('flag', 10)->nullable(); // Emoji o URL
            $table->string('region', 50)->nullable();
            $table->string('subregion', 50)->nullable();
            $table->timestamps();
        });

        // Tabla de estados/provincias
        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('iso2', 10)->nullable(); // Código del estado
            $table->string('name', 100);
            $table->string('type', 50)->nullable(); // 'Departamento', 'Provincia', etc.
            $table->timestamps();

            $table->index(['country_id', 'iso2']);
        });

        // Tabla de ciudades
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();

            $table->index(['state_id']);
            $table->index(['country_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
        Schema::dropIfExists('states');
        Schema::dropIfExists('countries');
    }
};