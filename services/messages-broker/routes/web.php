<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => 'Messages Broker Service',
        'status' => 'active',
        'version' => '1.0.0'
    ]);
});