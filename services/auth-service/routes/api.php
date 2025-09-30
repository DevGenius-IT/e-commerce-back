<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

// Health check endpoint
Route::get('health', function () {
    return response()->json(['status' => 'healthy', 'service' => 'auth-service']);
});

// All API routes are now handled via RabbitMQ through the API Gateway
// Internal routes for RabbitMQ handler processing
Route::group([], function () {
  Route::post("register", [AuthController::class, "register"]);
  Route::post("login", [AuthController::class, "login"]);
  Route::post("validate-token", [AuthController::class, "validateToken"]);

  Route::middleware("auth:api")->group(function () {
    Route::post("logout", [AuthController::class, "logout"]);
    Route::post("refresh", [AuthController::class, "refresh"]);
    Route::get("me", [AuthController::class, "me"]);

    // Roles management routes
    Route::prefix('roles')->group(function () {
      Route::get('/', [RoleController::class, 'index']);
      Route::post('/', [RoleController::class, 'store']);
      Route::get('/{id}', [RoleController::class, 'show']);
      Route::put('/{id}', [RoleController::class, 'update']);
      Route::delete('/{id}', [RoleController::class, 'destroy']);
      Route::post('/{id}/permissions', [RoleController::class, 'assignPermissions']);
    });

    // Permissions management routes
    Route::prefix('permissions')->group(function () {
      Route::get('/', [PermissionController::class, 'index']);
      Route::post('/', [PermissionController::class, 'store']);
      Route::get('/{id}', [PermissionController::class, 'show']);
      Route::put('/{id}', [PermissionController::class, 'update']);
      Route::delete('/{id}', [PermissionController::class, 'destroy']);
    });

    // User roles/permissions management
    Route::prefix('users')->group(function () {
      Route::post('/{id}/roles', [AuthController::class, 'assignRole']);
      Route::delete('/{id}/roles/{role}', [AuthController::class, 'removeRole']);
      Route::post('/{id}/permissions', [AuthController::class, 'assignPermission']);
      Route::delete('/{id}/permissions/{permission}', [AuthController::class, 'removePermission']);
    });
  });
});
