<?php

use App\Http\Controllers\API\AddressController;
use App\Http\Controllers\API\CountryController;
use Illuminate\Support\Facades\Route;

// Health check endpoint
Route::get('health', function () {
    return response()->json(['status' => 'healthy', 'service' => 'addresses-service']);
});

// Public routes for countries and regions
Route::prefix('countries')->group(function () {
    Route::get('/', [CountryController::class, 'index']);
    Route::get('{country}', [CountryController::class, 'show']);
    Route::get('{country}/regions', [CountryController::class, 'regions']);
});

// Protected routes for addresses (authentication handled by gateway)
Route::prefix('addresses')->group(function () {
    Route::get('/', [AddressController::class, 'index']);
    Route::post('/', [AddressController::class, 'store']);
    Route::get('type/{type}', [AddressController::class, 'byType']);
    Route::get('{address}', [AddressController::class, 'show']);
    Route::put('{address}', [AddressController::class, 'update']);
    Route::patch('{address}', [AddressController::class, 'update']);
    Route::delete('{address}', [AddressController::class, 'destroy']);
    Route::post('{address}/set-default', [AddressController::class, 'setDefault']);
});