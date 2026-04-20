<?php

try {
    require_once 'libs/index.php';

    DotEnv::loadFromFile();

    R::setup($_ENV['DB_DSN'], $_ENV['DB_USER'], $_ENV['DB_PASS']);
    
    $jwtSecret = $_ENV['JWT_SECRET'] ?? null;
    
    Auth::init(new JWT($jwtSecret));

    Route::init();
    Route::loadModels();
    Route::loadMiddlewares();
    Route::loadRoutes();

    Route::add404('views/404.html');
} catch (Throwable $e) {
    if (!empty($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production') {
        Route::response()->status(500)->json(['error' => 'Internal Server Error']);
    } else {
        Route::response()->status(500)->json(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
    }
    error_log($e->getTraceAsString());
    exit();
}
