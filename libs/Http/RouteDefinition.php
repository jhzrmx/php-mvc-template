<?php

/**
 * @package RouteDefinition
 * @author jhzrmx
 * @version 1.1.0
 * @license MIT
 * @link https://github.com/jhzrmx/php-mvc-template
 * @requires PHP 7.0+
 * @dependencies Route (libs/Http/Route.php)
 * @description Fluent handle returned by Route::get() & co.: name(), middleware() and where().
 */

namespace JHz\Http;

/**
 * Returned by Route::get() & co. so a route can be tweaked fluently:
 *   Route::get('/users/:id', [UserController::class, 'show'])->name('users.show')->where('id', '\d+');
 */
class RouteDefinition {
    private $index;

    public function __construct($index) {
        $this->index = $index;
    }

    public function name($name) {
        Route::setRouteName($this->index, $name);
        return $this;
    }

    public function middleware($middlewares) {
        Route::addRouteMiddleware($this->index, $middlewares);
        return $this;
    }

    public function where($param, $regex) {
        Route::setRouteConstraint($this->index, $param, $regex);
        return $this;
    }
}
