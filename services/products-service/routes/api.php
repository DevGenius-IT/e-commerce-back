<?php

use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\BrandController;
use App\Http\Controllers\API\TypeController;
use App\Http\Controllers\API\CatalogController;
use App\Http\Controllers\API\VatController;
use Illuminate\Support\Facades\Route;

// Health check endpoint
Route::get('health', function () {
    return response()->json(['status' => 'healthy', 'service' => 'products-service']);
});

// Public product routes (for catalog browsing)
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('search', [ProductController::class, 'search']);
    Route::get('{product}', [ProductController::class, 'show']);
});

// Public category routes
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('{category}', [CategoryController::class, 'show']);
    Route::get('{category}/products', [CategoryController::class, 'products']);
});

// Public brand routes
Route::prefix('brands')->group(function () {
    Route::get('/', [BrandController::class, 'index']);
    Route::get('{brand}', [BrandController::class, 'show']);
    Route::get('{brand}/products', [BrandController::class, 'products']);
});

// Public type routes
Route::prefix('types')->group(function () {
    Route::get('/', [TypeController::class, 'index']);
    Route::get('{type}', [TypeController::class, 'show']);
    Route::get('{type}/products', [TypeController::class, 'products']);
});

// Public catalog routes
Route::prefix('catalogs')->group(function () {
    Route::get('/', [CatalogController::class, 'index']);
    Route::get('{catalog}', [CatalogController::class, 'show']);
    Route::get('{catalog}/products', [CatalogController::class, 'products']);
});

// Public VAT rates
Route::prefix('vat')->group(function () {
    Route::get('/', [VatController::class, 'index']);
    Route::get('{vat}', [VatController::class, 'show']);
});

// Debug route to check your Postman token
Route::get('debug-postman-token', function () {
    $token = 'eyJOeXAi0iJKV1QíLCJhbGci0iJIUzI1NíJ9.єyJpсЗMi0íJodНRw0i8vYХV0aС1zzХJ2aWN10jgwMDEvYXВpL2хvZ21uІiwiaWFОIjo×NZU4NjY50ТMЗLCJ1еHАí0jЕ3NТg2NzM1MzсsIm5iZiI6MТc10DY20ТkzNywіanRpIjoíNЗRUVWFUТ3ZwbE1oUnNХciIsInN1YiI6IjEiLCJwcnYі01İjNzс0MzY1ZWV1NjhkNТс4N2V1NDQwNDVmNzIzMzMЗ0DI5Mjk4Y2U3Iiwicm9sZSI6bnVsbCwiZW1haWwі0iJreWxpYW5AY29sbGVjdC12ZXJ5dGhpbmcuY29tInO.XWkLWSMTX-1W9iJA47PXCV31-V1wdMZvqDoDCStuEqs';
    
    try {
        $parts = explode('.', $token);
        
        $debug_info = [
            'token_parts_count' => count($parts),
            'header_raw' => $parts[0] ?? 'missing',
            'payload_raw' => $parts[1] ?? 'missing',
            'signature_raw' => $parts[2] ?? 'missing'
        ];
        
        if (count($parts) !== 3) {
            return response()->json(['error' => 'Token format invalid', 'debug' => $debug_info]);
        }
        
        // Try to decode header
        $header = json_decode(base64_decode($parts[0]), true);
        
        // Try to decode payload with padding fix
        $payload_raw = $parts[1];
        // Add padding if needed
        $payload_raw .= str_repeat('=', (4 - strlen($payload_raw) % 4) % 4);
        $payload = json_decode(base64_decode($payload_raw), true);
        
        return response()->json([
            'header' => $header,
            'payload' => $payload,
            'current_time' => time(),
            'expiration' => $payload['exp'] ?? null,
            'is_expired' => isset($payload['exp']) ? $payload['exp'] < time() : false,
            'debug' => $debug_info,
            'base64_decode_error' => json_last_error_msg()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Failed to decode token',
            'message' => $e->getMessage(),
            'debug' => $debug_info ?? null
        ]);
    }
});

// Admin routes for management (protected by JWT)
Route::middleware(\Shared\Middleware\JWTAuthMiddleware::class)->prefix('admin')->group(function () {
    // Product management
    Route::prefix('products')->group(function () {
        Route::post('/', [ProductController::class, 'store']);
        Route::put('{product}', [ProductController::class, 'update']);
        Route::patch('{product}', [ProductController::class, 'update']);
        Route::delete('{product}', [ProductController::class, 'destroy']);
        Route::post('{product}/stock', [ProductController::class, 'updateStock']);
    });

    // Category management
    Route::prefix('categories')->group(function () {
        Route::post('/', [CategoryController::class, 'store']);
        Route::put('{category}', [CategoryController::class, 'update']);
        Route::patch('{category}', [CategoryController::class, 'update']);
        Route::delete('{category}', [CategoryController::class, 'destroy']);
    });

    // Brand management
    Route::prefix('brands')->group(function () {
        Route::post('/', [BrandController::class, 'store']);
        Route::put('{brand}', [BrandController::class, 'update']);
        Route::patch('{brand}', [BrandController::class, 'update']);
        Route::delete('{brand}', [BrandController::class, 'destroy']);
    });

    // Type management
    Route::prefix('types')->group(function () {
        Route::post('/', [TypeController::class, 'store']);
        Route::put('{type}', [TypeController::class, 'update']);
        Route::patch('{type}', [TypeController::class, 'update']);
        Route::delete('{type}', [TypeController::class, 'destroy']);
    });

    // Catalog management
    Route::prefix('catalogs')->group(function () {
        Route::post('/', [CatalogController::class, 'store']);
        Route::put('{catalog}', [CatalogController::class, 'update']);
        Route::patch('{catalog}', [CatalogController::class, 'update']);
        Route::delete('{catalog}', [CatalogController::class, 'destroy']);
    });

    // VAT management
    Route::prefix('vat')->group(function () {
        Route::post('/', [VatController::class, 'store']);
        Route::put('{vat}', [VatController::class, 'update']);
        Route::patch('{vat}', [VatController::class, 'update']);
        Route::delete('{vat}', [VatController::class, 'destroy']);
    });
});