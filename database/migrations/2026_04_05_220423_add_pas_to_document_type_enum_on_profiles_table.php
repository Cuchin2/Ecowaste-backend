<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{

public function up(): void
    {
        // MySQL: alterar el enum para incluir 'PAS'
        DB::statement("ALTER TABLE profiles MODIFY document_type ENUM('DNI', 'CE', 'PAS') NOT NULL");
    }

    public function down(): void
    {
        // Revertir al estado anterior
        DB::statement("ALTER TABLE profiles MODIFY document_type ENUM('DNI', 'CE') NOT NULL");
    }
};
