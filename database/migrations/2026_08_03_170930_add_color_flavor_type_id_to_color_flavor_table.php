<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar columna nullable
        Schema::table('color_flavor', function (Blueprint $table) {
            $table->foreignId('color_flavor_type_id')->nullable()->after('id');
        });

        // 2. Insertar tipos base (usando DB para evitar modelos)
        $types = [
            ['name' => 'color', 'order' => 1],
            ['name' => 'sabor', 'order' => 2],
        ];
        DB::table('color_flavor_types')->insert($types);

        // 3. Obtener IDs de los tipos recién insertados
        $colorId = DB::table('color_flavor_types')->where('name', 'color')->value('id');
        $saborId = DB::table('color_flavor_types')->where('name', 'sabor')->value('id');

        // 4. Asignar color_flavor_type_id según el antiguo campo 'type'
        DB::table('color_flavor')->get()->each(function ($row) use ($colorId, $saborId) {
            $typeId = $row->type === 'color' ? $colorId : $saborId;
            DB::table('color_flavor')
                ->where('id', $row->id)
                ->update(['color_flavor_type_id' => $typeId]);
        });

        // 5. Hacer NOT NULL (ya todos tienen valor)
        Schema::table('color_flavor', function (Blueprint $table) {
            $table->foreignId('color_flavor_type_id')->nullable(false)->change();
        });

        // 6. Eliminar columna type
        Schema::table('color_flavor', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        // 7. Agregar foreign key
        Schema::table('color_flavor', function (Blueprint $table) {
            $table->foreign('color_flavor_type_id')->references('id')->on('color_flavor_types')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        // Revertir
        Schema::table('color_flavor', function (Blueprint $table) {
            $table->dropForeign(['color_flavor_type_id']);
            $table->string('type')->nullable()->after('id');
        });

        // Rellenar type con los nombres de los tipos
        DB::table('color_flavor')->get()->each(function ($row) {
            $type = DB::table('color_flavor_types')->find($row->color_flavor_type_id);
            if ($type) {
                DB::table('color_flavor')->where('id', $row->id)->update(['type' => $type->name]);
            }
        });

        Schema::table('color_flavor', function (Blueprint $table) {
            $table->dropColumn('color_flavor_type_id');
        });

        DB::table('color_flavor_types')->truncate();
    }
};