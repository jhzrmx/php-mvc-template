<?php

/**
 * @package Autoload
 * @author jhzrmx
 * @version 1.0.0
 * @license MIT
 * @link https://github.com/jhzrmx/php-mvc-template
 * @requires PHP 7.0+
 * @dependencies None
 * @description PSR-4 autoloader for the JHz\ namespace: JHz\Http\Route is libs/Http/Route.php.
 * Used when Composer's autoloader isn't installed (Composer maps the same prefix, see composer.json).
 */

spl_autoload_register(function ($class) {
    $prefix = 'JHz\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
