<?php

use JHz\Http\Request;
use JHz\Http\Route;

// Handlers can simply return an array: it is sent as JSON with status 200.
Route::get('/api/health', function (Request $req) {
    return ['message' => 'OK', 'query_params' => $req->params];
})->name('health');

Route::group('/api/users', 'user.routes', ['auth']);
Route::group('/api/auth', 'auth.routes');
