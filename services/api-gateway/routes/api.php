<?php

// use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\AuthController;
use Illuminate\Support\Facades\Route;

Route::group([], function () {
  // Public routes
  Route::post("login", [AuthController::class, "login"]);

  // Protected routes
  // Route::middleware("auth.gateway")->group(function () {
  //   Route::apiResource("products", ProductController::class);
  // });
});
