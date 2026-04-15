<?php

// Old Routes but still supported:
// Route::post('/login', 'AuthController@login');
// Route::post('/register', 'AuthController@register');
// Route::post('/logout', 'AuthController@logout');
// Route::get('/me', 'AuthController@me', ['auth']);

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/me', [AuthController::class, 'me'], ['auth']);