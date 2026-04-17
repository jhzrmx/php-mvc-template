<?php

try {
    require_once 'libs/index.php';

    DotEnv::loadFromFile();

    R::setup($_ENV['DB_DSN'], $_ENV['DB_USER'], $_ENV['DB_PASS']);
    
    $jwtSecret = $_ENV['JWT_SECRET'] ?? null;
    
    Auth::init(new JWT($jwtSecret));

    Route::init();
    Route::enableBlade();
    Route::loadModels();
    Route::loadMiddlewares();
    Route::loadRoutes();

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
