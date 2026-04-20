<?php

function isProduction() {
    return !empty($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production';
}

try {
    require_once 'libs/index.php';

    DotEnv::loadFromFile();

    if (empty($_ENV['DB_USER']) && empty($_ENV['DB_PASS'])) {
        R::setup($_ENV['DB_DSN']);
    } else {
        R::setup($_ENV['DB_DSN'], $_ENV['DB_USER'], $_ENV['DB_PASS']);
    }
    
    if (isProduction()) R::freeze(true);
    
    $jwtSecret = $_ENV['JWT_SECRET'] ?? null;
    
    Auth::init(new JWT($jwtSecret));

    Route::init();
    Route::loadModels();
    Route::loadMiddlewares();
    Route::loadRoutes();

    Route::add404('views/404.html');
} catch (Throwable $e) {
    if (isProduction()) {
        Route::response()->status(500)->json(['error' => 'Internal Server Error']);
    } else {
        Route::response()->status(500)->json(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
    }
    error_log($e->getTraceAsString());
    exit();
}
