<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\State;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LocationController extends Controller
{
    /**
     * GET /api/locations/countries
     */
    public function countries(): JsonResponse
    {
        // Cacheamos por 24 horas (86400 segundos). Esta data casi no cambia.
        $countries = Cache::remember('api.countries.all', 86400, function () {
            return Country::select('id', 'iso2', 'name', 'flag', 'currency', 'phone_code')
                ->orderBy('name')
                ->get();
        });

        return response()->json($countries);
    }

    /**
     * GET /api/locations/states?country_id=173
     */
    public function states(Request $request): JsonResponse
    {
        $request->validate([
            'country_id' => 'required|integer|exists:countries,id'
        ]);

        $countryId = $request->input('country_id');

        $states = Cache::remember("api.states.country.{$countryId}", 86400, function () use ($countryId) {
            return State::where('country_id', $countryId)
                ->select('id', 'name', 'iso2', 'type')
                ->orderBy('name')
                ->get();
        });

        return response()->json($states);
    }

    /**
     * GET /api/locations/cities?state_id=3695
     */
    public function cities(Request $request): JsonResponse
    {
        $request->validate([
            'state_id' => 'required|integer|exists:states,id'
        ]);

        $stateId = $request->input('state_id');

        $cities = Cache::remember("api.cities.state.{$stateId}", 86400, function () use ($stateId) {
            return City::where('state_id', $stateId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        });

        return response()->json($cities);
    }
}