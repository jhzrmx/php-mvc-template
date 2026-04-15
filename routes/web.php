<?php

Route::get('/', 'views/login.html');
Route::get('/login', 'views/login.html');
Route::get('/signup', 'views/signup.html');
Route::get('/dashboard', 'views/dashboard.html', ['authRedirect']);

Route::get('/dashboard-no-js', function(Response $res) {
    $user = Auth::user();
    $res->status(200)->pass(['user' => $user])->file('views/dashboard-no-js.php');
}, ['authRedirect']);

Route::post('/logout', function(Request $req, Response $res) {
    Auth::clear();
    $res->redirect('/login');
}, ['authRedirect']);