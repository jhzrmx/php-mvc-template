<?php

// This group is mounted at /api/users behind the `auth` middleware (see routes/api.php).
// Listing and creating users is admin-only; the :id routes are limited to the account
// owner or an admin inside UserController (authorizeAccess).
// ":id(\d+)" only matches numbers, so /api/users/abc is a 404.

Route::get('/', [UserController::class, 'index'], ['role:admin'])->name('users.index');
Route::get('/:id(\d+)', [UserController::class, 'show'])->name('users.show');
Route::post('/', [UserController::class, 'create'], ['role:admin'])->name('users.create');
Route::put('/:id(\d+)', [UserController::class, 'update'])->name('users.update');
Route::patch('/:id(\d+)', [UserController::class, 'patch']);
Route::delete('/:id(\d+)', [UserController::class, 'destroy'])->name('users.destroy');
