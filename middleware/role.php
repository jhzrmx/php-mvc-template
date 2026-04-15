<?php

/**
 * Auth middleware: validates Bearer JWT and sets Auth payload.
 * Use on routes that require authentication.
 */
return function ($params) {
    $req = Route::request();
    $res = Route::response();
    $jwt = Auth::getJwt();
    $role = $params['role'] ?? null;

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
            'message' => $result['reason'] ?? 'Invalid token'
        ]);
    } elseif ($role && ($result['payload']['role'] ?? null) !== $role) {
        $res->status(403)->json(['error' => 'Forbidden', 'message' => 'Requires role: ' . $role]);
    }

    Auth::setPayload($result['payload']);
};
