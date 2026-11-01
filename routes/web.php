<?php

Route::get('/', 'views/login.html');
Route::get('/login', 'views/login.html')->name('login');
Route::get('/signup', 'views/signup.html')->name('signup');
Route::get('/dashboard', 'views/dashboard.html', ['authRedirect'])->name('dashboard');

// A server-rendered page: views/dashboard-no-js.php inside views/layouts/app.php
Route::get('/dashboard-no-js', function (Response $res) {
    $res->view('dashboard-no-js', ['user' => Auth::user()]);
}, ['authRedirect'])->name('dashboard.nojs');

Route::post('/logout', function (Response $res) {
    Auth::logout(); // revokes all of this user's tokens and clears the cookie
    $res->redirect('/login');
}, ['authRedirect'])->name('logout');
