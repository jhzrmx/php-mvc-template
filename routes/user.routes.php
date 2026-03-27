<?php

Route::get('/', 'UserController@index');
Route::get('/:id', 'UserController@show');
Route::post('/', 'UserController@create');
Route::put('/:id', 'UserController@update');
Route::patch('/:id', 'UserController@patch');
Route::delete('/:id', 'UserController@delete');