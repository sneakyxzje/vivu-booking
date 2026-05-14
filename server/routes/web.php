<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Welcome to Vivu Booking API Server',
        'status' => 'active',
        'timestamp' => now()->toIso8601String()
    ]);
});
