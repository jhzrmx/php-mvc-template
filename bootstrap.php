<?php

if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

// Baseline security headers for every response
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

require_once BASE_PATH . '/libs/index.php';

// From here on, every uncaught exception, PHP warning and fatal error ends in one clean
// error response (JSON or an HTML page) instead of a broken page. See libs/ErrorHandler.php.
ErrorHandler::register();

try {
    DotEnv::loadFromFile(BASE_PATH . '/.env');

    Database::connect();
    // Routes end the request with exit(), so close the connection when PHP shuts down
    register_shutdown_function(function () {
        R::close();
    });

    Auth::init(new JWT(env('JWT_SECRET')));

    // CORS is off unless CORS_ALLOWED_ORIGINS is set (comma-separated list, or *)
    $corsOrigins = array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))));
    if ($corsOrigins) {
        Route::cors($corsOrigins, null, null, env('CORS_ALLOW_CREDENTIALS', false) === true);
    }

    Route::init();
    Route::loadMiddlewares();
    Route::loadRoutes();

    // Runs the matching route; if none matches, throws a 404 (or 405) HttpException
    Route::add404('views/404.html');
} catch (Throwable $e) {
    ErrorHandler::handle($e);
}
