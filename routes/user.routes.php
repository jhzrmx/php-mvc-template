<?php

// Old Routes but still supported:
// Route::get('/', 'UserController@index');
// Route::get('/:id', 'UserController@show');
// Route::post('/', 'UserController@create');
// Route::put('/:id', 'UserController@update');
// Route::patch('/:id', 'UserController@patch');
// Route::delete('/:id', 'UserController@destroy');

Route::get('/', [UserController::class, 'index']);
Route::get('/:id', [UserController::class, 'show']);
Route::post('/', [UserController::class, 'create']);
Route::put('/:id', [UserController::class, 'update']);
Route::patch('/:id', [UserController::class, 'patch']);
Route::delete('/:id', [UserController::class, 'destroy']);