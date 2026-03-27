<?php

Route::get('/', 'views/login.html');
Route::get('/login', 'views/login.html');
Route::get('/signup', 'views/signup.html');
Route::get('/dashboard', 'views/dashboard.html', ['authRedirect']);
Route::group('/api/users', 'user.routes', ['auth']);
Route::group('/api/auth', 'auth.routes');
