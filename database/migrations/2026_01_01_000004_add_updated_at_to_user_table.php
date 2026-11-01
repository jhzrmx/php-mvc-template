<?php

/**
 * Adds `updated_at` to the user table (existing rows get their created_at value).
 */
return function () {
    if (isset(R::inspect('user')['updated_at'])) {
        return;
    }

    $seed = R::dispense('user');
    $seed->username = '__migration_seed__';
    $seed->updated_at = date('Y-m-d H:i:s');
    R::store($seed);
    R::trash($seed);

    $table = R::getWriter()->esc('user');
    R::exec("UPDATE $table SET updated_at = created_at WHERE updated_at IS NULL");
};
