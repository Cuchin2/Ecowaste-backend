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
        // Aumentar memoria a 1GB
        ini_set('memory_limit', '1024M');
        // Aumentar tiempo de ejecución a 5 minutos
        set_time_limit(300);
        
        DB::connection()->disableQueryLog();
        
        $jsonPath = database_path('seeders/data/countries+states+cities.json');
        
        if (!file_exists($jsonPath)) {
            $this->command->error('❌ Archivo JSON no encontrado en: ' . $jsonPath);
            return;
        }

        $this->command->info('⏳ Leyendo archivo JSON (46MB)... esto puede tomar 30-60 segundos');
        
        // Leer y decodificar el JSON
        $jsonContent = file_get_contents($jsonPath);
        $data = json_decode($jsonContent, true);
        
        // Verificar errores
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error('❌ Error al decodificar JSON: ' . json_last_error_msg());
            return;
        }
        
        if (!is_array($data) || empty($data)) {
            $this->command->error('❌ El JSON está vacío o no es un array válido');
            return;
        }

        $this->command->info('✅ JSON cargado correctamente. Procesando ' . count($data) . ' países...');

        $countriesData = [];
        $statesData = [];
        $citiesData = [];

        // Procesar la estructura anidada
        foreach ($data as $country) {
            // 1. Extraer país
            $countriesData[] = [
                'id' => $country['id'],
                'iso2' => $country['iso2'],
                'name' => $country['name'],
                'iso3' => $country['iso3'] ?? null,
                'phone_code' => $country['phonecode'] ?? null,
                'currency' => $country['currency'] ?? null,
                'currency_name' => $country['currency_name'] ?? null,
                'currency_symbol' => $country['currency_symbol'] ?? null,
                'flag' => $country['emoji'] ?? null,
                'region' => $country['region'] ?? null,
                'subregion' => $country['subregion'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // 2. Extraer estados (si existen)
            if (!empty($country['states'])) {
                foreach ($country['states'] as $state) {
                    $statesData[] = [
                        'id' => $state['id'],
                        'country_id' => $country['id'],
                        'iso2' => $state['iso2'] ?? null,
                        'name' => $state['name'],
                        'type' => $state['type'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    // 3. Extraer ciudades (si existen)
                    if (!empty($state['cities'])) {
                        foreach ($state['cities'] as $city) {
                            $citiesData[] = [
                                'id' => $city['id'],
                                'country_id' => $country['id'],
                                'state_id' => $state['id'],
                                'name' => $city['name'],
                                'latitude' => $city['latitude'] ?? null,
                                'longitude' => $city['longitude'] ?? null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }
                }
            }
        }

        // Insertar países
        $this->command->info(' Insertando ' . count($countriesData) . ' países...');
        foreach (array_chunk($countriesData, 1000) as $chunk) {
            Country::insert($chunk);
        }

        // Insertar estados
        $this->command->info('📦 Insertando ' . count($statesData) . ' estados...');
        foreach (array_chunk($statesData, 1000) as $chunk) {
            State::insert($chunk);
        }

        // Insertar ciudades
        $this->command->info('📦 Insertando ' . count($citiesData) . ' ciudades (esto puede tomar unos minutos)...');
        foreach (array_chunk($citiesData, 1000) as $chunk) {
            City::insert($chunk);
        }
        
        $this->command->info('');
        $this->command->info('✅ ¡Locations seeded successfully!');
        $this->command->info('📍 Countries: ' . count($countriesData));
        $this->command->info('📍 States: ' . count($statesData));
        $this->command->info('📍 Cities: ' . count($citiesData));
    }
}