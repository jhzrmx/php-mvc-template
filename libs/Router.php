<?php

/**
 * @package Router
 * @author jhzrmx
 * @version 1.0.0
 * @license MIT
 * @link https://github.com/jhzrmx/php-mvc-template
 */

class Request {
    /**
     * The body of the request.
     *
     * @var array
     */
    public $body;
    /**
     * The query parameters of the request.
     *
     * @var array
     */
    public $params;
    /**
     * The form data of the request.
     *
     * @var array
     */
    public $formData;
    /**
     * The files of the request.
     *
     * @var array
     */
    public $files;

    /**
     * Constructor for the Request class.
     *
     * @return void
     */
    public function __construct() {
        $input = file_get_contents('php://input');
        $decoded = json_decode($input, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $this->body = $decoded;
        } else {
            $this->body = $input;
        }
        $this->params = $_GET;
        $this->formData = $_POST;
        $this->files = $_FILES;
    }
}

class Response {
    /**
     * Data passed to PHP view files.
     *
     * @var array
     */
    private array $viewData = [];

    /**
     * Set the status code for the response.
     *
     * @param int $code The status code to set.
     * @return $this
     */
    public function status($code) {
        http_response_code($code);
        return $this;
    }

    /**
     * Send a JSON response.
     *
     * @param array $data The data to send.
     * @return void
     */
    public function json($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }

    /**
     * Send a text response.
     *
     * @param string $text The text to send.
     * @param string $contentType The content type to send.
     * @return void
     */
    public function send($text, $contentType = 'text/plain') {
        header("Content-Type: $contentType");
        echo $text;
        exit();
    }

    /**
     * Send a file response.
     *
     * @param string $file The file to send.
     * @param array $data Variables to extract into the file scope.
     * @return void
     */
    public function file($file, array $data = []) {
        $vars = array_merge($this->viewData, $data);
        if (!empty($vars)) {
            extract($vars, EXTR_SKIP);
        }
        require $file;
        exit();
    }

    /**
     * Add variables to be used by a PHP view file.
     *
     * Usage:
     *   $res->pass(['user' => $user])->file('views/dashboard.php');
     *
     * @param array $data
     * @return $this
     */
    public function pass(array $data) {
        $this->viewData = array_merge($this->viewData, $data);
        return $this;
    }
    
    /**
     * Redirect to a URL.
     *
     * @param string $url The URL to redirect to.
     * @param int $statusCode The status code for the redirect (default: 302).
     * @return void
     */
    public function redirect($url, $statusCode = 302) {
        http_response_code($statusCode);
        header("Location: $url");
        exit();
    }
}

class Route {
    /**
     * The base path for the routes.
     *
     * @var string
     */
    private static $base_path = '';
    /**
     * Whether a route has been matched.
     *
     * @var bool
     */
    private static $route_matched = false;
    /**
     * Whether a 404 error has been called.
     *
     * @var bool
     */
    private static $is404Called = false;
    /**
     * The prefix for the group routes.
     *
     * @var string
     */
    private static $group_prefix = '';
    /**
     * The middlewares for the group routes.
     *
     * @var array
     */
    private static $group_middlewares = [];
    /**
     * The middlewares for the routes.
     *
     * @var array
     */
    private static $middlewares = [];
    /**
     * The request object.
     *
     * @var Request
     */
    private static $request;
    /**
     * The response object.
     *
     * @var Response
     */
    private static $response;
    /**
     * The directory for the controllers.
     *
     * @var string
     */
    private static $controllersDir = 'controllers';
    /**
     * The directory for the routes.
     *
     * @var string
     */
    private static $routesDir = 'routes';
    /**
     * The directory for the models.
     *
     * @var string
     */
    private static $modelsDir = 'models';
    /**
     * The directory for the middlewares.
     *
     * @var string
     */
    private static $middlewaresDir = 'middleware';

    /**
     * Initialize the router.
     *
     * @return void
     */
    public static function init() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_method'])) {
            $_SERVER['REQUEST_METHOD'] = strtoupper($_POST['_method']);
        }
        if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
            http_response_code(204);
            exit();
        }
        self::$request = new Request();
        self::$response = new Response();
    }

    /**
     * Get the request object.
     *
     * @return Request
     */
    public static function request() {
        return self::$request;
    }

    /**
     * Get the response object.
     *
     * @return Response
     */
    public static function response() {
        return self::$response;
    }

    /**
     * Enable the base path.
     *
     * @return void
     */
    public static function enableBasePath() {
        $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        self::$base_path = $script === '/' ? '' : $script;
    }

    /**
     * Set the base path.
     *
     * @param string $new_base_path The new base path.
     * @return void
     */
    public static function setBasePath($new_base_path) {
        self::$base_path = $new_base_path;
    }

    /**
     * Add CORS headers for allowed origins.
     *
     * @param array $allowedOrigins The allowed origins for CORS (default: ['*']).
     * @return void
     */
    public static function addCORSUrl($allowedOrigins = ['*']) {
        if (in_array('*', $allowedOrigins)) {
            header('Access-Control-Allow-Origin: *');
        } else {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            if (in_array($origin, $allowedOrigins)) {
                header("Access-Control-Allow-Origin: $origin");
            }
        }
    }

    /**
     * Add CORS methods for allowed HTTP methods.
     *
     * @param array $methods The allowed HTTP methods for CORS (default: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']).
     * @return void
     */
    public static function addCORSMethods($methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']) {
        header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
    }

    /**
     * Add CORS headers for allowed headers.
     *
     * @param array $headers The allowed headers for CORS (default: ['Content-Type', 'Authorization']).
     * @return void
     */
    public static function addCORSHeaders($headers = ['Content-Type', 'Authorization']) {
        header('Access-Control-Allow-Headers: ' . implode(', ', $headers));
    }

    /**
     * Get the root directory.
     *
     * @return string
     */
    private static function rootDir() {
        return dirname($_SERVER['SCRIPT_FILENAME']);
    }

    /**
     * Set the controller directory.
     *
     * @param string $dir The directory to set.
     * @return void
     */
    public static function setControllersDir($dir) {
        self::$controllersDir = rtrim($dir, '/');
    }

    /**
     * Set the route directory.
     *
     * @param string $dir The directory to set.
     * @return void
     */
    public static function setRoutesDir($dir) {
        self::$routesDir = rtrim($dir, '/');
    }

    /**
     * Set the prefix for the group routes.
     *
     * @param string $prefix The prefix to set.
     * @return void
     */
    public static function prefix($prefix) {
        self::$group_prefix .= rtrim($prefix, '/');
    }

    /**
     * Get a route.
     *
     * @param string $route The route to get.
     * @param callable $callback The callback to execute.
     * @param array $middlewares The middlewares to run.
     * @return void
     */
    public static function get($route, $callback, $middlewares = []) {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            self::reroute($route, $callback, $middlewares);
        }
    }

    /**
     * Post a route.
     *
     * @param string $route The route to post.
     * @param callable $callback The callback to execute.
     * @param array $middlewares The middlewares to run.
     * @return void
     */
    public static function post($route, $callback, $middlewares = []) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::reroute($route, $callback, $middlewares);
        }
    }

    /**
     * Put a route.
     *
     * @param string $route The route to put.
     * @param callable $callback The callback to execute.
     * @param array $middlewares The middlewares to run.
     * @return void
     */
    public static function put($route, $callback, $middlewares = []) {
        if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            self::reroute($route, $callback, $middlewares);
        }
    }

    /**
     * Patch a route.
     *
     * @param string $route The route to patch.
     * @param callable $callback The callback to execute.
     * @param array $middlewares The middlewares to run.
     * @return void
     */
    public static function patch($route, $callback, $middlewares = []) {
        if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
            self::reroute($route, $callback, $middlewares);
        }
    }

    /**
     * Delete a route.
     *
     * @param string $route The route to delete.
     * @param callable $callback The callback to execute.
     * @param array $middlewares The middlewares to run.
     * @return void
     */
    public static function delete($route, $callback, $middlewares = []) {
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            self::reroute($route, $callback, $middlewares);
        }
    }

    /**
     * Any a route.
     *
     * @param string $route The route to any.
     * @param callable $callback The callback to execute.
     * @param array $middlewares The middlewares to run.
     * @return void
     */
    public static function any($route, $callback, $middlewares = []) {
        self::reroute($route, $callback, $middlewares);
    }

    /**
     * Group a route.
     *
     * @param string $prefix The prefix to set.
     * @param callable $options The options to set.
     * @param callable $routes The routes to set.
     * @return void
     */
    public static function group($prefix, $options, $routes = null) {
        if (is_callable($options) || is_string($options)) {
            // Support signature:
            //   Route::group('/prefix', 'routes.file', ['middleware1', 'middleware2']);
            // In this case, the 3rd argument is treated as middleware, and the 2nd
            // argument is treated as the routes file/callback.
            if (is_array($routes)) {
                $middlewares = $routes;
                $routes = $options;
                $options = ['middleware' => $middlewares];
            } else {
                $routes = $options;
                $options = [];
            }
        }

        $prevPrefix = self::$group_prefix;
        $prevMiddleware = self::$group_middlewares;

        self::$group_prefix .= rtrim($prefix, '/');

        if (isset($options['middleware'])) {
            self::$group_middlewares = array_merge(
                self::$group_middlewares,
                (array) $options['middleware']
            );
        }

        if (is_callable($routes)) {
            call_user_func($routes);
        } elseif (is_string($routes)) {
            if (!str_contains($routes, '.php')) {
                $routes .= '.php';
            }
            $file = self::rootDir() . "/" . self::$routesDir . "/$routes";
            if (!file_exists($file)) {
                throw new Exception("Route file not found: $routes");
            }
            require $file;
        }

        self::$group_prefix = $prevPrefix;
        self::$group_middlewares = $prevMiddleware;
    }

    /**
     * Set a middleware.
     *
     * @param string $name The name of the middleware.
     * @param callable $callback The callback to execute.
     * @return void
     */
    public static function middleware($name, $callback) {
        self::$middlewares[$name] = $callback;
    }

    /**
     * Load the middlewares from the middlewares directory set in $middlewaresDir.
     *
     * @return void
     */
    public static function loadMiddlewares() {
        $files = glob(self::rootDir() . "/" . self::$middlewaresDir . '/*.php');
        if ($files) {
            foreach ($files as $file) {
                self::$middlewares[basename($file, '.php')] = require_once $file;
            }
        }
    }

    /**
     * Load the models from the models directory set in $modelsDir.
     *
     * @return void
     */
    public static function loadModels() {
        $files = glob(self::$modelsDir . '/*.php');
        if ($files) {
            foreach ($files as $file) {
                require $file;
            }
        }
    }

    /**
     * Set the models directory.
     *
     * @param string $dir The directory to set.
     * @return void
     */
    public static function setModelsDir($dir) {
        self::$modelsDir = rtrim($dir, '/');
    }

    /**
     * Run the middlewares.
     *
     * @param array $middlewares The middlewares to run.
     * @return void
     */
    private static function runMiddlewares($middlewares) {
        $all = array_merge(self::$group_middlewares, $middlewares);

        foreach ($all as $mw) {
            $parts = explode(':', $mw);
            $name = $parts[0];
            $params = isset($parts[1]) ? explode(',', $parts[1]) : [];

            if (!isset(self::$middlewares[$name])) {
                throw new Exception("Middleware not found: $name");
            }

            call_user_func(self::$middlewares[$name], $params);
        }
    }

    /**
     * Run the controller.
     *
     * @param string|array $callback The callback to run.
     * @param array $params The parameters to pass to the controller.
     * @return void
     */
    private static function runController($callback, $params) {
        // Still Support: 'AuthController@login'
        // New Implementation: [MyController::class, 'method']
        if (is_string($callback)) {
            list($controller, $method) = explode('@', $callback);
        } elseif (is_array($callback) && count($callback) === 2) {
            $controller = $callback[0];
            $method = $callback[1];
        } else {
            throw new Exception("Invalid controller callback.");
        }
        $className = is_string($controller) ? $controller : get_class($controller);
        $shortName = basename(str_replace('\\', '/', $className));
        $file = self::rootDir() . "/" . self::$controllersDir . "/$shortName.php";
        if (file_exists($file)) {
            require_once $file;
        }
        $instance = new $className();
        $ref = new ReflectionMethod($instance, $method);
        $args = [];
        foreach ($ref->getParameters() as $param) {
            $type = $param->getType();
            if ($type) {
                $name = $type->getName();
                if ($name === Request::class) {
                    $args[] = self::$request;
                    continue;
                }
                if ($name === Response::class) {
                    $args[] = self::$response;
                    continue;
                }
            }
            $args[] = array_shift($params);
        }
        $ref->invokeArgs($instance, $args);
    }

    /**
     * Execute the callback.
     *
     * @param callable $callback The callback to execute.
     * @param array $params The parameters to pass to the callback.
     * @return void
     */
    private static function execute($callback, $params) {
        if (is_callable($callback)) {
            // Inject Request/Response into callable callbacks ONLY when the callback
            // signature declares them, similar to controller type-hint injection.
            // Any other parameters are filled from the matched route parameters.
            try {
                $ref = null;
                if (is_array($callback) && count($callback) === 2) {
                    $ref = new ReflectionMethod($callback[0], $callback[1]);
                } elseif (is_object($callback) && !($callback instanceof Closure) && is_callable($callback)) {
                    // Support invokable objects: new Foo(); where Foo::__invoke()
                    $ref = new ReflectionMethod($callback, '__invoke');
                } else {
                    $ref = new ReflectionFunction($callback);
                }

                $args = [];
                $paramIndex = 0;

                foreach ($ref->getParameters() as $param) {
                    $type = $param->getType();
                    $typeNames = [];
                    if ($type) {
                        // Named types: Request / Response
                        if ($type instanceof ReflectionNamedType) {
                            $typeNames[] = $type->getName();
                        } elseif ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
                            foreach ($type->getTypes() as $t) {
                                if ($t instanceof ReflectionNamedType) {
                                    $typeNames[] = $t->getName();
                                }
                            }
                        }
                    }

                    if (in_array(Request::class, $typeNames, true)) {
                        $args[] = self::$request;
                        continue;
                    }
                    if (in_array(Response::class, $typeNames, true)) {
                        $args[] = self::$response;
                        continue;
                    }

                    // Non-Request/Response parameter: use matched route params first.
                    if ($paramIndex < count($params)) {
                        $args[] = $params[$paramIndex];
                        $paramIndex++;
                        continue;
                    }

                    // Missing parameter: if optional, use default/null.
                    if ($param->isDefaultValueAvailable()) {
                        $args[] = $param->getDefaultValue();
                    } elseif ($param->allowsNull()) {
                        $args[] = null;
                    } elseif ($param->isOptional()) {
                        $args[] = null;
                    } else {
                        // Let PHP throw a useful error when required args are missing.
                        $args[] = null;
                    }
                }

                call_user_func_array($callback, $args);
            } catch (ReflectionException $e) {
                // Fallback to old behavior if reflection fails.
                call_user_func_array($callback, $params);
            }
        } elseif (
            (is_string($callback) && str_contains($callback, '@')) ||
            (is_array($callback) && count($callback) === 2)
        ) {
            self::runController($callback, $params);
        } else {
            require self::rootDir() . '/' . $callback;
        }
    }

    /**
     * Reroute the request.
     *
     * @param string $route The route to reroute.
     * @param callable $callback The callback to execute.
     * @param array $middlewares The middlewares to run.
     * @return void
     */
    private static function reroute($route, $callback, $middlewares = []) {
        if (!empty($route) && $route[0] !== '/') {
            $route = '/' . $route;
        }

        $route = self::$group_prefix . $route;
        $full_route = self::$base_path . $route;

        $request = rtrim($_SERVER['REQUEST_URI'], '/');
        $request = strtok($request, '?');

        $route_parts = explode('/', trim($full_route, '/'));
        $req_parts = explode('/', trim($request, '/'));

        if (count($route_parts) !== count($req_parts)) {
            return;
        }
        $params = [];
        foreach ($route_parts as $i => $part) {
            if (preg_match('/^:/', $part)) {
                $params[] = $req_parts[$i];
                continue;
            }

            if ($part !== $req_parts[$i]) {
                return;
            }
        }

        self::$route_matched = true;
        self::runMiddlewares($middlewares);
        self::execute($callback, $params);
        exit();
    }

    /**
     * Register Laravel-style resource routes.
     *
     * Example:
     * Route::resource('users', [UserController::class]);
     * Route::resource('posts', PostController::class);
     *
     * Generates:
     * GET       /users             -> index
     * GET       /users/create      -> create
     * POST      /users             -> store
     * GET       /users/:id         -> show
     * GET       /users/:id/edit    -> edit
     * PUT       /users/:id         -> update
     * PATCH     /users/:id         -> update
     * DELETE    /users/:id         -> destroy
     * 
     * @param string $name The base name for the resource.
     * @param string|array $controller The controller class or [class, method] to handle the resource routes.
     * @param array $middlewares The middlewares to run for the resource routes.
     * @return void
     */
    public static function resource($name, $controller, $middlewares = []) {
        // Accept:
        // PostController::class
        // [PostController::class]
        // 'PostController'

        if (is_array($controller)) {
            $controller = $controller[0];
        }
        $base = '/' . trim($name, '/');

        self::get($base, [$controller, 'index'], $middlewares);
        self::get($base . '/create', [$controller, 'create'], $middlewares);
        self::post($base, [$controller, 'store'], $middlewares);
        self::get($base . '/:id', [$controller, 'show'], $middlewares);
        self::get($base . '/:id/edit', [$controller, 'edit'], $middlewares);
        self::put($base . '/:id', [$controller, 'update'], $middlewares);
        self::patch($base . '/:id', [$controller, 'update'], $middlewares);
        self::delete($base . '/:id', [$controller, 'destroy'], $middlewares);
    }

    /**
     * Load the web and/or api routes.
     *
     * @return void
     */
    public static function loadRoutes($routes = ['web', 'api']) {
        foreach ($routes as $route) {
            include_once self::rootDir() . '/' . self::$routesDir . "/$route.php";
        }
    }

    /**
     * Set the Single Page Application file, typically index.html from build.
     *
     * @param string $file The file to set.
     * @return void
     */
    public static function setSPA($file = 'index.html') {
        if (!self::$route_matched && !self::$is404Called) {
            if ($file) {
                require self::rootDir() . '/' . $file;
            }
            exit();
        }
        if (self::$is404Called) {
            throw new Exception("Cannot set SPA after 404 callback has been called");
        }
    }

    /**
     * Add a 404 callback file.
     *
     * @param string $file The file to set as the 404 callback.
     * @return void
     */
    public static function add404($file = null) {
        if (!self::$route_matched) {
            self::$is404Called = true;
            http_response_code(404);
            if ($file) {
                require self::rootDir() . '/' . $file;
            }
            exit();
        }
        if (self::$is404Called) {
            throw new Exception("Cannot call 404 callback after a route has been matched");
        }
    }
}