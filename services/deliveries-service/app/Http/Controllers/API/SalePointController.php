<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SalePoint;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class SalePointController extends Controller
{
    /**
     * Get all active sale points.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SalePoint::active();

        // Filter by city if provided
        if ($request->has('city')) {
            $query->inCity($request->city);
        }

        // Filter by postal code if provided
        if ($request->has('postal_code')) {
            $query->inPostalCode($request->postal_code);
        }

        // Search by name if provided
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        // If coordinates are provided, sort by distance
        if ($request->has('latitude') && $request->has('longitude')) {
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            
            $salePoints = $query->get()->map(function ($point) use ($latitude, $longitude) {
                $point->distance = $point->distanceTo($latitude, $longitude);
                return $point;
            })->sortBy('distance');

            return response()->json([
                'success' => true,
                'data' => $salePoints->values()->all(),
            ]);
        }

        $salePoints = $query->orderBy('name')->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $salePoints,
        ]);
    }

    /**
     * Get a specific sale point.
     */
    public function show($id): JsonResponse
    {
        $salePoint = SalePoint::with(['deliveries' => function ($query) {
            $query->latest()->limit(10);
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $salePoint,
        ]);
    }

    /**
     * Create a new sale point (Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:sale_points,code',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'opening_hours' => 'nullable|array',
            'is_active' => 'boolean',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $salePoint = SalePoint::create($validator->validated());

        return response()->json([
            'success' => true,
            'data' => $salePoint,
            'message' => 'Sale point created successfully'
        ], 201);
    }

    /**
     * Update a sale point (Admin only).
     */
    public function update(Request $request, $id): JsonResponse
    {
        $salePoint = SalePoint::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:50|unique:sale_points,code,' . $id,
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'opening_hours' => 'nullable|array',
            'is_active' => 'boolean',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $salePoint->update($validator->validated());

        return response()->json([
            'success' => true,
            'data' => $salePoint,
            'message' => 'Sale point updated successfully'
        ]);
    }

    /**
     * Delete a sale point (Admin only).
     */
    public function destroy($id): JsonResponse
    {
        $salePoint = SalePoint::findOrFail($id);
        
        // Check if there are active deliveries
        if ($salePoint->active_deliveries_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete sale point with active deliveries'
            ], 422);
        }

        $salePoint->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sale point deleted successfully'
        ]);
    }

    /**
     * Get nearby sale points based on coordinates.
     */
    public function nearby(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|integer|min:1|max:100', // radius in km
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $radius = $request->radius ?? 25; // Default 25km radius

        $salePoints = SalePoint::active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(function ($point) use ($latitude, $longitude) {
                $point->distance = $point->distanceTo($latitude, $longitude);
                return $point;
            })
            ->filter(function ($point) use ($radius) {
                return $point->distance <= $radius;
            })
            ->sortBy('distance')
            ->values();

        return response()->json([
            'success' => true,
            'data' => $salePoints,
            'search_parameters' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'radius_km' => $radius,
                'results_count' => $salePoints->count(),
            ]
        ]);
    }

    /**
     * Get sale point statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_sale_points' => SalePoint::count(),
            'active_sale_points' => SalePoint::active()->count(),
            'inactive_sale_points' => SalePoint::where('is_active', false)->count(),
            'with_coordinates' => SalePoint::whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->count(),
            'by_city' => SalePoint::select('city')
                ->selectRaw('COUNT(*) as count')
                ->whereNotNull('city')
                ->groupBy('city')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            'deliveries_stats' => SalePoint::withCount(['deliveries'])
                ->orderBy('deliveries_count', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($point) {
                    return [
                        'name' => $point->name,
                        'city' => $point->city,
                        'deliveries_count' => $point->deliveries_count,
                        'active_deliveries_count' => $point->active_deliveries_count,
                    ];
                })
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}