<?php

/**
 * Same as auth but redirects to /login on failure (for protected page routes).
 */
return function ($params) {
    $req = Route::request();
    $res = Route::response();
    $jwt = Auth::getJwt();

    if (!$jwt) {
        header('Location: /login', true, 302);
        exit();
    }

    $token = Auth::getToken();
    if (!$token) {
        header('Location: /login', true, 302);
        exit();
    }

    $result = $jwt->verify($token);
    if (empty($result['valid'])) {
        header('Location: /login', true, 302);
        exit();
    }

    Auth::setPayload($result['payload']);
};
