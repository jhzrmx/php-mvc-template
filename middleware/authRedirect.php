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

    $authHeader = $req->params['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $m)) {
        $token = trim($m[1]);
    } elseif (!empty($_SERVER['HTTP_X_AUTH_TOKEN'])) {
        $token = trim($_SERVER['HTTP_X_AUTH_TOKEN']);
    } elseif (!empty($_COOKIE['token'])) {
        $token = trim($_COOKIE['token']);
    } else {
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
