<?php

Route::get('/', 'views/login.html');
Route::get('/login', 'views/login.html');
Route::get('/signup', 'views/signup.html');
Route::get('/dashboard', 'views/dashboard.html', ['authRedirect']);

Route::get('/dashboard-no-js', function(Response $res) {
    $user = Auth::user();
    $res->status(200)->pass(['user' => $user])->file('views/dashboard-no-js.php');
}, ['authRedirect']);

Route::get('/example', function(Response $res) {
    $user = Auth::user();
    $res->view('example');

    // TODO: Issue when passing data to view, it doesn't update when the same view is rendered again with different data. This is because the compiled view is cached and doesn't account for data changes. A possible solution is to include a hash of the data in the cache filename, but this may lead to excessive cache files if there are many unique data sets.
    // $res->view('example', ['user' => $user]);
});

Route::post('/logout', function(Request $req, Response $res) {
    Auth::clear();
    $res->redirect('/login');
}, ['authRedirect']);