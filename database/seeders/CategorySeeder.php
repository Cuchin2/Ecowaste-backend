<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         // Nivel 1
        $frutasVerduras = Category::create([
            'name' => 'Frutas y Verduras',
            'slug' => 'frutas-y-verduras',
            'level' => 0,
            'order' => 1
        ]);

        $carnes = Category::create([
            'name' => 'Carnes y Huevos',
            'slug' => 'carnes-y-huevos',
            'level' => 0,
            'order' => 2
        ]);

        // Nivel 2
        $manzanas = Category::create([
            'name' => 'Manzanas',
            'slug' => 'manzanas',
            'parent_id' => $frutasVerduras->id,
            'level' => 1,
            'order' => 1
        ]);

        $platanos = Category::create([
            'name' => 'Plátanos',
            'slug' => 'platanos',
            'parent_id' => $frutasVerduras->id,
            'level' => 1,
            'order' => 2
        ]);

        $pollo = Category::create([
            'name' => 'Pollo',
            'slug' => 'pollo',
            'parent_id' => $carnes->id,
            'level' => 1,
            'order' => 1
        ]);

        // Nivel 3
        Category::create([
            'name' => 'Manzana Fuji',
            'slug' => 'manzana-fuji',
            'parent_id' => $manzanas->id,
            'level' => 2,
            'order' => 1
        ]);

        Category::create([
            'name' => 'Manzana Gala',
            'slug' => 'manzana-gala',
            'parent_id' => $manzanas->id,
            'level' => 2,
            'order' => 2
        ]);

        Category::create([
            'name' => 'Plátano Canarias',
            'slug' => 'platano-canarias',
            'parent_id' => $platanos->id,
            'level' => 2,
            'order' => 1
        ]);

        Category::create([
            'name' => 'Pechuga de Pollo',
            'slug' => 'pechuga-pollo',
            'parent_id' => $pollo->id,
            'level' => 2,
            'order' => 1
        ]);
    }
    
}
