<?php

/**
 * @package Loader
 * @author jhzrmx
 * @version 2.0.0
 * @license MIT
 * @link https://github.com/jhzrmx/php-mvc-template
 * @requires PHP 7.0+
 * @dependencies libs/autoload.php, libs/helpers.php, libs/RedbeanPHP/
 * @description Boots the libraries: registers the PSR-4 autoloader for the JHz\\ namespace (libs/autoload.php),
 * loads the global helper functions and RedBeanPHP (a global class that isn't namespaced), and registers an
 * autoloader that finds classes in models/ and controllers/ by file name.
 */

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/RedbeanPHP/index.php';

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
