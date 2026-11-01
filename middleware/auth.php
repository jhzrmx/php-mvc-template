<?php

use JHz\Http\Route;
use JHz\Security\Auth;

/**
 * Auth middleware (for API routes): validates the JWT, confirms the user still exists
 * and the token hasn't been revoked, then makes the user available via Auth::user().
 */
return function ($params) {
    $res = Route::response();

    if (!Auth::getJwt()) {
        $res->status(500)->json(['error' => 'Auth not configured']);
    }

    $result = Auth::authenticate();
    if (empty($result['valid'])) {
        $res->status(401)->json([
            'error' => 'Unauthorized',
            'reason' => $result['reason'] ?? 'Invalid token'
        ]);
    }
};
