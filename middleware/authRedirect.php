<?php

/**
 * Same as auth but redirects to /login on failure (for protected page routes).
 */
return function ($params) {
    $req = Route::request();
    $res = Route::response();
    $jwt = Auth::getJwt();

    if (!$jwt) {
        $res->redirect('/login');
        exit();
    }

    $token = Auth::getToken();
    if (!$token) {
        $res->redirect('/login');
        exit();
    }

    $result = $jwt->verify($token);
    if (empty($result['valid'])) {
        $res->redirect('/login');
        exit();
    }

    Auth::setPayload($result['payload']);
};
