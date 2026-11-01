<?php

use JHz\Http\Route;
use JHz\Security\Auth;

/**
 * Same as auth but redirects to /login on failure (for protected page routes).
 */
return function ($params) {
    $res = Route::response();

    if (!Auth::getJwt()) {
        $res->redirect('/login');
    }

    $result = Auth::authenticate();
    if (empty($result['valid'])) {
        $res->redirect('/login');
    }
};
