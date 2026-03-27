<?php

try {
    require_once 'libs/index.php';

    DotEnv::loadFromFile();

    R::setup($_ENV['DB_DSN'], $_ENV['DB_USER'], $_ENV['DB_PASS']);

    Route::loadModels();

    $jwtSecret = $_ENV['JWT_SECRET'] ?? null;

    if (empty($jwtSecret) || strlen($jwtSecret) < 32) {
        throw new Exception('JWT_SECRET must be set in .env and be at least 32 characters.');
    }

    Auth::init(new JWT($jwtSecret));

    Route::init();
    Route::middleware('auth', require 'middleware/auth.php');
    Route::middleware('authRedirect', require 'middleware/authRedirect.php');

    require_once 'routes/web.php';

    Route::add404('views/404.html');
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    if (!empty($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production') {
        echo json_encode(['error' => 'Internal Server Error']);
    } else {
        echo json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
    }
    error_log($e->getTraceAsString());
    exit();
}
