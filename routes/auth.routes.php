<?php

Route::post('/login', 'AuthController@login');
Route::post('/register', 'AuthController@register');
Route::post('/logout', 'AuthController@logout');
Route::get('/me', 'AuthController@me', ['auth']);
