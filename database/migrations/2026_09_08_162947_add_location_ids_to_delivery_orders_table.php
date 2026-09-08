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
        Schema::table('delivery_orders', function (Blueprint $table) {
            //
            $table->string('country_code', 10)->nullable()->after('country');
            $table->unsignedBigInteger('state_id')->nullable()->after('state');
            $table->unsignedBigInteger('city_id')->nullable()->after('city');
            $table->unsignedBigInteger('district_id')->nullable()->after('district');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            //
            $table->dropColumn(['country_code', 'state_id', 'city_id', 'district_id']);
        });
    }
};
