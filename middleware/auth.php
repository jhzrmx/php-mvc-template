<?php

/**
 * Auth middleware: validates Bearer JWT and sets Auth payload.
 * Use on routes that require authentication.
 */
return function ($params) {
    $req = Route::request();
    $res = Route::response();
    $jwt = Auth::getJwt();

    if (!$jwt) {
        $res->status(500)->json(['error' => 'Auth not configured']);
    }

    $authHeader = $req->params['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $m)) {
        $token = trim($m[1]);
    } elseif (!empty($_SERVER['HTTP_X_AUTH_TOKEN'])) {
        $token = trim($_SERVER['HTTP_X_AUTH_TOKEN']);
    } elseif (!empty($_COOKIE['token'])) {
        $token = trim($_COOKIE['token']);
    } else {
        $res->status(401)->json(['error' => 'Missing or invalid Authorization header (use Bearer token or X-Auth-Token)']);
    }

    $result = $jwt->verify($token);
    if (empty($result['valid'])) {
        $res->status(401)->json([
            'error' => 'Unauthorized',
            'reason' => $result['reason'] ?? 'Invalid token'
        ]);
    }

    Auth::setPayload($result['payload']);
};
