<?php

use App\Http\Controllers\API\WebsiteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Health check endpoint (public)
Route::get('/health', [WebsiteController::class, 'health']);

// Public routes
Route::prefix('websites')->group(function () {
    Route::get('/', [WebsiteController::class, 'index']);
    Route::get('/search', [WebsiteController::class, 'search']);
    Route::get('/{website}', [WebsiteController::class, 'show']);
});

// Protected routes (require JWT authentication)
Route::middleware(['auth:api'])->prefix('websites')->group(function () {
    Route::post('/', [WebsiteController::class, 'store']);
    Route::put('/{website}', [WebsiteController::class, 'update']);
    Route::delete('/{website}', [WebsiteController::class, 'destroy']);
});