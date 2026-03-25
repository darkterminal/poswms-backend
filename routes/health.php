<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Health Check Route
|--------------------------------------------------------------------------
|
| This route provides a simple health check endpoint for Docker containers
| and load balancers to verify the application is running.
|
*/

Route::get('/api/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is healthy',
        'data' => [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'environment' => config('app.env'),
            'version' => config('app.version', '1.0.0'),
        ],
    ]);
})->name('api.health');
