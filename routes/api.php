<?php

Route::get('/api/health', function(Request $req, Response $res) {
    $res->status(200)->json(['message' => 'OK', 'query_params' => $req->params]);
});

Route::group('/api/users', 'user.routes', ['role:admin']);
Route::group('/api/auth', 'auth.routes');