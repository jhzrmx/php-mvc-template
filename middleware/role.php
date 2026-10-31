<?php

/**
 * Role middleware. Must run after `auth` / `authRedirect`.
 * Usage: ['auth', 'role:admin']  or  ['auth', 'role:admin,editor']
 * The role is read from the database on every request, never from the token.
 */
return function ($params) {
    $res = Route::response();
    $user = Auth::currentUser();
    $role = $user ? ($user->role ?? User::ROLE_USER) : null;

    if ($role === null || !in_array($role, $params, true)) {
        $res->status(403)->json(['error' => 'Forbidden', 'message' => 'You do not have permission to do this']);
    }
};
