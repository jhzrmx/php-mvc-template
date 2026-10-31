<?php

// This group is mounted at /api/users behind the `auth` middleware (see routes/api.php).
// Listing and creating users is admin-only; the :id routes are limited to the account
// owner or an admin inside UserController (authorizeAccess).

Route::get('/', [UserController::class, 'index'], ['role:admin']);
Route::get('/:id', [UserController::class, 'show']);
Route::post('/', [UserController::class, 'create'], ['role:admin']);
Route::put('/:id', [UserController::class, 'update']);
Route::patch('/:id', [UserController::class, 'patch']);
Route::delete('/:id', [UserController::class, 'destroy']);
