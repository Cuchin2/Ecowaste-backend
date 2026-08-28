<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\State;
use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Forzamos la memoria a 1GB para estar 100% seguros
        ini_set('memory_limit', '1024M');
        
        DB::connection()->disableQueryLog();
        
        $dataPath = database_path('seeders/data');

        // 2. Verificamos que los 3 archivos existan
        $files = ['countries.json', 'states.json', 'cities.json'];
        foreach ($files as $file) {
            if (!file_exists("$dataPath/$file")) {
                $this->command->error("❌ Falta el archivo: $dataPath/$file");
                return;
            }
        }

        $this->command->info('⏳ Procesando Countries...');
        $this->seedCountries("$dataPath/countries.json");
        
        $this->command->info('⏳ Procesando States...');
        $this->seedStates("$dataPath/states.json");
        
        $this->command->info('⏳ Procesando Cities (esto puede tomar 1-2 minutos)...');
        $this->seedCities("$dataPath/cities.json");
        
        $this->command->info('✅ ¡Locations seeded successfully!');
    }

    private function seedCountries(string $path): void
    {
        $data = json_decode(file_get_contents($path), true);
        $countries = [];
        
        foreach ($data as $item) {
            $countries[] = [
                'id' => $item['id'],
                'iso2' => $item['iso2'],
                'name' => $item['name'],
                'iso3' => $item['iso3'] ?? null,
                'phone_code' => $item['phone_code'] ?? null,
                'currency' => $item['currency'] ?? null,
                'currency_name' => $item['currency_name'] ?? null,
                'currency_symbol' => $item['currency_symbol'] ?? null,
                'flag' => $item['emoji'] ?? null,
                'region' => $item['region'] ?? null,
                'subregion' => $item['subregion'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($countries, 1000) as $chunk) {
            Country::insert($chunk);
        }
        $this->command->info('📍 Countries insertados: ' . count($countries));
    }

    private function seedStates(string $path): void
    {
        $data = json_decode(file_get_contents($path), true);
        $states = [];
        
        foreach ($data as $item) {
            $states[] = [
                'id' => $item['id'],
                'country_id' => $item['country_id'],
                'iso2' => $item['state_code'] ?? null,
                'name' => $item['name'],
                'type' => $item['type'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($states, 1000) as $chunk) {
            State::insert($chunk);
        }
        $this->command->info('📍 States insertados: ' . count($states));
    }

    private function seedCities(string $path): void
    {
        $data = json_decode(file_get_contents($path), true);
        $cities = [];
        
        foreach ($data as $item) {
            $cities[] = [
                'id' => $item['id'],
                'country_id' => $item['country_id'],
                'state_id' => $item['state_id'],
                'name' => $item['name'],
                'latitude' => $item['latitude'] ?? null,
                'longitude' => $item['longitude'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Chunk de 1000 para no saturar la memoria de MySQL
        foreach (array_chunk($cities, 1000) as $chunk) {
            City::insert($chunk);
        }
        $this->command->info('📍 Cities insertadas: ' . count($cities));
    }
}