<?php

/**
 * @package Loader
 * @author jhzrmx
 * @version 1.0.0
 * @license MIT
 * @link https://github.com/jhzrmx/php-mvc-template
 * @requires PHP 7.0+
 * @dependencies Every file in libs/ (it loads them in dependency order); models/ and controllers/ are autoloaded
 * @description Loads every library in this folder in dependency order and registers an autoloader that finds
 * classes in models/ and controllers/ by file name.
 */

require_once __DIR__ . '/DotEnv.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/HttpException.php';
require_once __DIR__ . '/ErrorHandler.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/JWT.php';
require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/RedbeanPHP/index.php';
require_once __DIR__ . '/Database.php';

// Load model and controller classes on first use (files are named after the class)
spl_autoload_register(function ($class) {
    if (strpbrk($class, '\\/.') !== false) {
        return; // only plain class names map to files
    }
    $root = dirname(__DIR__);
    foreach (['models', 'controllers'] as $dir) {
        $file = $root . '/' . $dir . '/' . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});
