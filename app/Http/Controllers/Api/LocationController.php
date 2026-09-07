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
        // ⚠️ IMPORTANTE: Regístrate gratis en https://www.geonames.org/login para obtener tu propio username.
        // El usuario 'demo' tiene límites de solicitudes muy estrictos (aprox. 2000/día) y suele fallar en producción.
        $this->username = config('services.geonames.username', 'demo'); 
    }

    // 1️⃣ Obtener todos los países en español
    public function getCountries()
    {
        // Se agrega '_es' a la clave de caché para evitar colisiones con datos en inglés
        return Cache::remember('geonames_countries_es', 86400, function () {
            $response = Http::get("https://api.geonames.org/countryInfoJSON", [
                'username' => $this->username,
                'lang'     => 'es', // 🇪🇸 Fuerza la respuesta en español
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Error al obtener los países'], 500);
            }

            $data = $response->json();
            $countries = collect($data['geonames'] ?? [])->map(fn($c) => [
                'geonameId'  => $c['geonameId'],
                'iso2'       => $c['countryCode'],
                'name'       => $c['countryName'], // Vendrá en español gracias a 'lang' => 'es'
                'flag'       => $c['countryCode'] ?? null,
                'currency'   => $c['currencyCode'] ?? null,
                'phone_code' => $c['phone'] ?? null,
            ]);

            return $countries;
        });
    }

    // 2️⃣ Obtener estados/provincias de un país (por iso2) en español
    public function getStates($countryCode)
    {
        $cacheKey = "geonames_states_{$countryCode}_es";

        return Cache::remember($cacheKey, 86400, function () use ($countryCode) {
            $countryId = $this->getCountryId($countryCode);
            if (!$countryId) {
                return response()->json(['error' => 'País no encontrado'], 404);
            }

            $response = Http::get("https://api.geonames.org/childrenJSON", [
                'geonameId' => $countryId,
                'username'  => $this->username,
                'lang'      => 'es', // 🇪🇸 Fuerza la respuesta en español
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Error al obtener los estados/provincias'], 500);
            }

            $data = $response->json();
            $states = collect($data['geonames'] ?? [])->map(fn($s) => [
                'geonameId' => $s['geonameId'],
                'name'      => $s['name'] ?? $s['toponymName'], // Nombre en español
                'iso2'      => $s['adminCode1'] ?? null,
                'type'      => $s['fcodeName'] ?? null,
            ]);

            return $states;
        });
    }

    // 3️⃣ Obtener ciudades de un estado/provincia en español
    public function getCities($stateId)
    {
        $cacheKey = "geonames_cities_{$stateId}_es";

        return Cache::remember($cacheKey, 86400, function () use ($stateId) {
            $response = Http::get("https://api.geonames.org/childrenJSON", [
                'geonameId' => $stateId,
                'username'  => $this->username,
                'lang'      => 'es', // 🇪🇸 Fuerza la respuesta en español
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Error al obtener las ciudades'], 500);
            }

            $data = $response->json();
            $cities = collect($data['geonames'] ?? [])->map(fn($c) => [
                'geonameId' => $c['geonameId'],
                'name'      => $c['name'] ?? $c['toponymName'], // Nombre en español
            ]);

            return $cities;
        });
    }

    // 4️⃣ Obtener distritos de una ciudad (nivel 4) en español
    public function getDistricts($cityId)
    {
        $cacheKey = "geonames_districts_{$cityId}_es";

        return Cache::remember($cacheKey, 86400, function () use ($cityId) {
            $response = Http::get("https://api.geonames.org/childrenJSON", [
                'geonameId' => $cityId,
                'username'  => $this->username,
                'lang'      => 'es', // 🇪🇸 Fuerza la respuesta en español
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Error al obtener los distritos'], 500);
            }

            $data = $response->json();
            $districts = collect($data['geonames'] ?? [])->map(fn($d) => [
                'geonameId' => $d['geonameId'],
                'name'      => $d['name'] ?? $d['toponymName'], // Nombre en español
            ]);

            return $districts;
        });
    }

    // 🛠️ Helper: obtener geonameId de un país por código ISO
    private function getCountryId($countryCode)
    {
        $cacheKey = 'geonames_countries_iso_es';

        return Cache::remember($cacheKey, 86400, function () {
            $response = Http::get("https://api.geonames.org/countryInfoJSON", [
                'username' => $this->username,
                'lang'     => 'es',
            ]);

            if ($response->failed()) {
                return collect();
            }

            $data = $response->json();
            return collect($data['geonames'] ?? [])->keyBy('countryCode');
        })->get(strtoupper($countryCode))['geonameId'] ?? null;
    }
}