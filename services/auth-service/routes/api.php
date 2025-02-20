<?php

use App\Http\Controllers\API\AuthController;
use Illuminate\Support\Facades\Route;

Route::group([], function () {
  Route::post("register", [AuthController::class, "register"]);
  Route::post("login", [AuthController::class, "login"]);
  Route::post("validate-token", [AuthController::class, "validateToken"]);

  Route::middleware("auth:api")->group(function () {
    Route::post("logout", [AuthController::class, "logout"]);
    Route::post("refresh", [AuthController::class, "refresh"]);
    Route::get("me", [AuthController::class, "me"]);
  });
});
