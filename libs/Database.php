<?php

/**
 * Connects RedBeanPHP using the DB_* values from .env.
 * Shared by the web app (bootstrap.php) and the CLI (mvc).
 */
class Database {
    /**
     * @param bool $freezeInProduction Freeze the schema when APP_ENV=production.
     *                                 Pass false for migrations, which need to change the schema.
     * @throws Exception If DB_DSN is missing.
     */
    public static function connect($freezeInProduction = true) {
        $dsn = (string) env('DB_DSN', '');
        if ($dsn === '') {
            throw new Exception('DB_DSN is not set in .env');
        }

        // Resolve relative SQLite paths against the project root, not the current directory
        if (defined('BASE_PATH') && stripos($dsn, 'sqlite:') === 0) {
            $path = substr($dsn, 7);
            $isAbsolute = preg_match('#^([A-Za-z]:[\\\\/]|/|\\\\)#', $path) === 1;
            if ($path !== '' && $path !== ':memory:' && !$isAbsolute) {
                $dsn = 'sqlite:' . BASE_PATH . '/' . ltrim(preg_replace('#^\./#', '', $path), '/');
            }
        }

        $user = (string) env('DB_USER', '');
        $pass = (string) env('DB_PASS', '');

        if ($user === '' && $pass === '') {
            R::setup($dsn);
        } else {
            R::setup($dsn, $user, $pass);
        }

        if ($freezeInProduction && env('APP_ENV') === 'production') {
            R::freeze(true);
        }
    }
}
