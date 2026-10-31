<?php

if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

function isProduction() {
    return env('APP_ENV') === 'production';
}

// Baseline security headers for every response
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

try {
    require_once BASE_PATH . '/libs/index.php';

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
    Route::loadModels();
    Route::loadMiddlewares();
    Route::loadRoutes();

    Route::add404('views/404.html');
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal Server Error', 'message' => isProduction() ? 'An unexpected error occurred on the server.' : $e->getMessage()]);
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    exit();
}
