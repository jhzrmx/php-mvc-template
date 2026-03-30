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

    $token = Auth::getToken();
    if (!$token) {
        $res->status(401)->json(['error' => 'Unauthorized']);
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
