<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CountryController extends Controller
{
    /**
     * Display a listing of active countries.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Country::active()->orderBy('name');

        // Optional search by name or code
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%")
                  ->orWhere('iso3', 'LIKE', "%{$search}%");
            });
        }

        $countries = $query->get();

        return response()->json([
            'data' => $countries,
            'meta' => [
                'total' => $countries->count()
            ]
        ]);
    }

    /**
     * Display the specified country with its regions.
     */
    public function show(Country $country): JsonResponse
    {
        if (!$country->is_active) {
            return response()->json(['error' => 'Country not found'], 404);
        }

        $country->load(['regions' => function ($query) {
            $query->active()->orderBy('name');
        }]);

        return response()->json(['data' => $country]);
    }

    /**
     * Get regions for a specific country.
     */
    public function regions(Country $country): JsonResponse
    {
        if (!$country->is_active) {
            return response()->json(['error' => 'Country not found'], 404);
        }

        $regions = $country->regions()
            ->active()
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $regions,
            'meta' => [
                'total' => $regions->count(),
                'country' => $country->name
            ]
        ]);
    }
}