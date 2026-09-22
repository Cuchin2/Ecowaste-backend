<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cambiar el enum para incluir PROCESSING
        DB::statement("ALTER TABLE sale_orders MODIFY COLUMN status ENUM('CREATE', 'PAID', 'PROCESSING', 'TRACKING', 'DONE', 'CANCEL') DEFAULT 'CREATE'");
    }

    public function down(): void
    {
        // Revertir el cambio (eliminando PROCESSING)
        DB::statement("ALTER TABLE sale_orders MODIFY COLUMN status ENUM('CREATE', 'PAID', 'CANCEL', 'TRACKING', 'DONE') DEFAULT 'CREATE'");
    }
};