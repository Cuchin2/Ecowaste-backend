<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Llama a tus otros seeders aquí
        $this->call([
            UserSeeder::class, // Esto ejecutará tu UserSeeder
            // OtrosSeeders::class, // Si tienes más seeders, los añadirías aquí
            // ProductSeeder::class,
            // CategorySeeder::class,
        ]);


    }
}
