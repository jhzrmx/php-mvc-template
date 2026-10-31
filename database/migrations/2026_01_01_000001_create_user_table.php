<?php

/**
 * Creates the `user` table.
 *
 * RedBean builds tables from a "seed" bean, and the seed values decide each column's type.
 * token_version is seeded with a large number so it becomes a real INT column; a seed of 0
 * would give MySQL a TINYINT that overflows at 255 logouts once the schema is frozen.
 */
return function () {
    if (in_array('user', R::inspect(), true)) {
        return; // already created (e.g. by an older version of this template)
    }

    $seed = R::dispense('user');
    $seed->username = 'seed';
    $seed->password_hash = str_repeat('x', 60);
    $seed->email = 'seed@example.com';
    $seed->role = 'user';
    $seed->token_version = 2000000000;
    $seed->created_at = date('Y-m-d H:i:s');
    R::store($seed);
    R::trash($seed); // keep the columns, drop the row
};
