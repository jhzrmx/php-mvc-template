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
     * @return void
     */
    public function file($file) {
        require $file;
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
                require_once $file;
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
     * @param string $action The action to run.
     * @param array $params The parameters to pass to the controller.
     * @return void
     */
    private static function runController($action, $params) {
        list($controller, $method) = explode('@', $action);
        $file = self::rootDir() . "/" . self::$controllersDir . "/$controller.php";
        if (!file_exists($file)) {
            throw new Exception("Controller not found: $controller");
        }
        require_once $file;
        $instance = new $controller();
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
        } elseif (str_contains($callback, '@')) {
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