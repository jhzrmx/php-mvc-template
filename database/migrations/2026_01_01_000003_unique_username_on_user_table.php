<?php

/**
 * Enforces unique usernames in the database itself, so two simultaneous sign-ups
 * can't both pass the "does this username exist?" check.
 */
return function () {
    if (!R::getWriter()->addUniqueConstraint('user', ['username'])) {
        throw new Exception(
            'Could not add a unique index on user.username. If you have duplicate usernames, '
            . 'rename or remove them, then run: php mvc migrate'
        );
    }
};
