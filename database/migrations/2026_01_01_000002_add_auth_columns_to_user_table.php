<?php

/**
 * Adds `role` and `token_version` to a `user` table created before they existed.
 * Does nothing on fresh databases.
 */
return function () {
    $columns = R::inspect('user');
    if (isset($columns['role']) && isset($columns['token_version'])) {
        return;
    }

    $seed = R::dispense('user');
    $seed->username = '__migration_seed__';
    $seed->role = 'user';
    $seed->token_version = 2000000000; // see the create_user_table migration for why
    R::store($seed);
    R::trash($seed);

    $table = R::getWriter()->esc('user');
    R::exec("UPDATE $table SET role = 'user' WHERE role IS NULL");
    R::exec("UPDATE $table SET token_version = 0 WHERE token_version IS NULL");
};
