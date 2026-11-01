<?php

/**
 * Role middleware. Must run after `auth` / `authRedirect`.
 * Usage: ['auth', 'role:admin']  or  ['auth', 'role:admin,editor']
 * The role is read from the database on every request, never from the token.
 */
return function ($params) {
    $user = Auth::currentUser();
    $role = $user ? ($user->role ?? User::ROLE_USER) : null;

    if ($role === null || !in_array($role, $params, true)) {
        abort(403, 'You do not have permission to do this');
    }
};
