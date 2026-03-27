<?php

Route::post('/login', 'AuthController@login');
Route::post('/register', 'AuthController@register');
Route::get('/me', 'AuthController@me', ['auth']);
