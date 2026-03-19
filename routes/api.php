<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned to "api" middleware group and the "api/v1" prefix.
|
*/

// Public routes
Route::post('/auth/login', [LoginController::class, 'login'])->name('auth.login');

// Protected routes (require Sanctum authentication and tenant scoping)
// All tenant-scoped routes must include {tenant_id} parameter
Route::middleware(['auth:sanctum', 'tenant.scoped'])->prefix('tenants/{tenant_id}')->group(function () {
    Route::post('/auth/logout', [LoginController::class, 'logout'])->name('auth.logout');
    Route::post('/auth/refresh', [LoginController::class, 'refresh'])->name('auth.refresh');
    Route::get('/auth/me', [LoginController::class, 'me'])->name('auth.me');
});
