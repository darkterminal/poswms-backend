<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Swagger UI Documentation
Route::get('/docs/api', function () {
    return view('swagger');
})->name('docs.api');
