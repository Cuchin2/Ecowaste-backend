<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class LocationController extends Controller
{
 
    public function getCountries()  
    { 
        $username = config('services.geonames.username');
        $response = Http::get("http://api.geonames.org/countryInfoJSON?lang=es&username=$username");
        $countries = collect($response->json()['geonames'])->map(function($country) {
            return [
                'code' => $country['countryCode'],
                'name' => $country['countryName'],
            ];
        });
        return response()->json($countries);
    }

    public function getStates($countryCode)
    {
        // Utilizando GeoNames
        $username = config('services.geonames.username');
        $response = Http::get("http:/api.geonames.org/searchJSON?country=$countryCode&lang=es&username=$username");
        $countryId=$response->json()['geonames'][0]['countryId'];
        $response = Http::get("http:/api.geonames.org/childrenJSON?geonameId=$countryId&lang=es&username=$username");
        return response()->json($response->json()['geonames']);
    }

    public function getCities($stateCode)
    { 
        // Utilizando GeoNames
        $username = config('services.geonames.username');
        $response = Http::get("http://api.geonames.org/childrenJSON?geonameId=$stateCode&lang=es&username=$username");
        return response()->json($response->json()['geonames']);
    }
    public function getDistrits($cityCode)
    { 
        // Utilizando GeoNames
        $username = config('services.geonames.username');
        $response = Http::get("http://api.geonames.org/childrenJSON?geonameId=$cityCode&lang=es&username=$username");
        return response()->json($response->json()['geonames']);
    }


}