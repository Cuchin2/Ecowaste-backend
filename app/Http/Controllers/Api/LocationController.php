<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class LocationController extends Controller
{
    protected $username;

    public function __construct()
    {
        $this->username = config('services.geonames.username', 'demo'); // Usa 'demo' para pruebas
    }

    // 1️⃣ Obtener todos los países
    public function getCountries()
    {
        return Cache::remember('geonames_countries', 86400, function () {
            $response = Http::get("http://api.geonames.org/countryInfoJSON", [
                'username' => $this->username,
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Error fetching countries'], 500);
            }

            $data = $response->json();
            $countries = collect($data['geonames'] ?? [])->map(fn($c) => [
                'geonameId' => $c['geonameId'],
                'iso2' => $c['countryCode'],
                'name' => $c['countryName'],
                'flag' => $c['countryCode'] ?? null,
                'currency' => $c['currencyCode'] ?? null,
                'phone_code' => $c['phone'] ?? null,
            ]);

            return $countries;
        });
    }

    // 2️⃣ Obtener estados/provincias de un país (por iso2)
    public function getStates($countryCode)
    {
        $cacheKey = "geonames_states_{$countryCode}";

        return Cache::remember($cacheKey, 86400, function () use ($countryCode) {
            // 1. Obtener el geonameId del país
            $countryId = $this->getCountryId($countryCode);
            if (!$countryId) {
                return response()->json(['error' => 'Country not found'], 404);
            }

            // 2. Obtener hijos del país (divisiones administrativas nivel 1)
            $response = Http::get("http://api.geonames.org/childrenJSON", [
                'geonameId' => $countryId,
                'username' => $this->username,
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Error fetching states'], 500);
            }

            $data = $response->json();
            $states = collect($data['geonames'] ?? [])->map(fn($s) => [
                'geonameId' => $s['geonameId'],
                'name' => $s['name'] ?? $s['toponymName'],
                'iso2' => $s['adminCode1'] ?? null,
                'type' => $s['fcodeName'] ?? null,
            ]);

            return $states;
        });
    }

    // 3️⃣ Obtener ciudades de un estado/provincia
    public function getCities($stateId)
    {
        $cacheKey = "geonames_cities_{$stateId}";

        return Cache::remember($cacheKey, 86400, function () use ($stateId) {
            $response = Http::get("http://api.geonames.org/childrenJSON", [
                'geonameId' => $stateId,
                'username' => $this->username,
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Error fetching cities'], 500);
            }

            $data = $response->json();
            $cities = collect($data['geonames'] ?? [])->map(fn($c) => [
                'geonameId' => $c['geonameId'],
                'name' => $c['name'] ?? $c['toponymName'],
            ]);

            return $cities;
        });
    }

    // 4️⃣ Obtener distritos de una ciudad (nivel 4)
    public function getDistricts($cityId)
    {
        $cacheKey = "geonames_districts_{$cityId}";

        return Cache::remember($cacheKey, 86400, function () use ($cityId) {
            // Para distritos, también usamos children (puede ser ADM2, ADM3, etc.)
            $response = Http::get("http://api.geonames.org/childrenJSON", [
                'geonameId' => $cityId,
                'username' => $this->username,
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Error fetching districts'], 500);
            }

            $data = $response->json();
            $districts = collect($data['geonames'] ?? [])->map(fn($d) => [
                'geonameId' => $d['geonameId'],
                'name' => $d['name'] ?? $d['toponymName'],
            ]);

            return $districts;
        });
    }

    // 🛠️ Helper: obtener geonameId de un país por código ISO
    private function getCountryId($countryCode)
    {
        $countries = Cache::remember('geonames_countries_iso', 86400, function () {
            $response = Http::get("http://api.geonames.org/countryInfoJSON", [
                'username' => $this->username,
            ]);

            if ($response->failed()) {
                return collect();
            }

            $data = $response->json();
            return collect($data['geonames'] ?? [])->keyBy('countryCode');
        });

        return $countries->get(strtoupper($countryCode))['geonameId'] ?? null;
    }
}