<?php

/**
 * @package helpers
 * @author jhzrmx
 * @version 1.0.0
 * @license MIT
 * @link https://github.com/jhzrmx/php-mvc-template
 * @requires PHP 7.0+
 * @dependencies Route (libs/Http/Route.php), HttpException (libs/Exceptions/HttpException.php)
 * @description Global helper functions: env() reads environment variables, e() escapes output for HTML, route() builds the URL of a named route,
 * and abort(), abort_if() and abort_unless() stop the request with an HTTP error.
 */

use JHz\Exceptions\HttpException;
use JHz\Http\Route;

if (!function_exists('e')) {
    /**
     * Escape a value for safe output inside HTML (use it on everything user-controlled).
     *
     * @param mixed $value
     * @return string
     */
    function e($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('route')) {
    /**
     * Build a URL for a named route: route('users.show', ['id' => 5]).
     * Extra parameters become the query string.
     *
     * @param string $name
     * @param array  $params
     * @param array  $query
     * @return string
     */
    function route($name, array $params = [], array $query = []) {
        return Route::url($name, $params, $query);
    }
}

if (!function_exists('abort')) {
    /**
     * Stop the request with an HTTP error. The ErrorHandler renders it as JSON or an HTML page.
     * Works anywhere: controllers, middleware, models, even inside a view.
     *
     *   abort(404, 'Post not found');
     *   abort(429, 'Slow down', ['Retry-After' => 30]);
     *
     * @param int    $status
     * @param string $message Safe-to-show text.
     * @param array  $headers
     * @return void
     * @throws HttpException
     */
    function abort($status, $message = '', array $headers = []) {
        throw new HttpException($status, $message, $headers);
    }
}

if (!function_exists('abort_if')) {
    /**
     * abort() when the condition is true.
     */
    function abort_if($condition, $status, $message = '', array $headers = []) {
        if ($condition) {
            abort($status, $message, $headers);
        }
    }
}

if (!function_exists('abort_unless')) {
    /**
     * abort() when the condition is false.
     */
    function abort_unless($condition, $status, $message = '', array $headers = []) {
        if (!$condition) {
            abort($status, $message, $headers);
        }
    }
}

if (!function_exists('env')) {
    /**
     * Read an environment variable. "true"/"false"/"null"/"empty" are cast to
     * true/false/null/"" (like Laravel); everything else is returned as a string.
     *
     * @param string $key
     * @param mixed  $default Returned when the variable isn't set.
     * @return mixed
     */
    function env($key, $default = null) {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        if (!is_string($value)) {
            return $value;
        }
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
            case 'empty':
            case '(empty)':
                return '';
        }
        return $value;
    }
}
