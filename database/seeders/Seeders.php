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
        DB::connection()->disableQueryLog();
        
        $jsonPath = database_path('seeders/data/countries+states+cities.json');
        
        if (!file_exists($jsonPath)) {
            $this->command->error('❌ Archivo JSON no encontrado. Asegúrate de guardarlo en: database/seeders/data/');
            return;
        }

        $this->command->info('⏳ Leyendo archivo JSON... (esto puede tomar unos segundos)');
        $data = json_decode(file_get_contents($jsonPath), true);

        $this->seedCountries($data);
        $this->seedStates($data);
        $this->seedCities($data);
        
        $this->command->info('✅ ¡Locations seeded successfully!');
    }

    private function seedCountries(array $data): void
    {
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

        // Insertamos en lotes de 1000 para no saturar la memoria
        foreach (array_chunk($countries, 1000) as $chunk) {
            Country::insert($chunk);
        }
        
        $this->command->info('📍 Countries insertados: ' . count($countries));
    }

    private function seedStates(array $data): void
    {
        $states = [];
        foreach ($data as $country) {
            if (empty($country['states'])) {
                continue;
            }
            
            foreach ($country['states'] as $state) {
                $states[] = [
                    'id' => $state['id'],
                    'country_id' => $country['id'],
                    'iso2' => $state['state_code'] ?? null,
                    'name' => $state['name'],
                    'type' => $state['type'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($states, 1000) as $chunk) {
            State::insert($chunk);
        }
        
        $this->command->info('📍 States insertados: ' . count($states));
    }

    private function seedCities(array $data): void
    {
        $cities = [];
        foreach ($data as $country) {
            if (empty($country['states'])) {
                continue;
            }
            
            foreach ($country['states'] as $state) {
                if (empty($state['cities'])) {
                    continue;
                }
                
                foreach ($state['cities'] as $city) {
                    $cities[] = [
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

        // Las ciudades son muchas (~150,000), los chunks de 1000 son vitales aquí
        foreach (array_chunk($cities, 1000) as $chunk) {
            City::insert($chunk);
        }
        
        $this->command->info('📍 Cities insertadas: ' . count($cities));
    }
}