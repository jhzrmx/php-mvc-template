<?php

/**
 * @package Route
 * @author jhzrmx
 * @version 1.1.0
 * @license MIT
 * @link https://github.com/jhzrmx/php-mvc-template
 * @requires PHP 7.0+
 * @dependencies Request, Response and RouteDefinition (this folder), HttpException and ValidationException (libs/Exceptions/)
 * @description A small Laravel-style router with middleware, route groups, named routes, parameter constraints,
 * Request/Response injection into handlers and PHP view rendering with layouts.
 */

namespace JHz\Http;

use JHz\Exceptions\HttpException;
use Closure;
use Exception;
use InvalidArgumentException;
use JsonSerializable;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

class Route {
    /**
     * A route segment that captures a parameter: ":id" or ":id(\d+)" (inline constraint).
     */
    const PARAM_PATTERN = '/^:(\w+)(?:\((.+)\))?$/';

    /**
     * The base path for the routes.
     *
     * @var string
     */
    private static $base_path = '';
    /**
     * Every registered route, in order.
     * Each: ['method', 'pattern', 'callback', 'middlewares', 'where', 'name'].
     *
     * @var array
     */
    private static $routes = [];
    /**
     * Route name => index in $routes.
     *
     * @var array
     */
    private static $names = [];
    /**
     * Whether dispatch() has already run.
     *
     * @var bool
     */
    private static $dispatched = false;
    /**
     * Whether a route has been matched.
     *
     * @var bool
     */
    private static $route_matched = false;
    /**
     * Methods that ARE allowed for the requested path (set when it matched under another method).
     *
     * @var array
     */
    private static $allowed = [];
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
     * Named middlewares: name => callable.
     *
     * @var array
     */
    private static $middlewares = [];
    /**
     * CORS settings registered with Route::cors() (null = CORS disabled).
     *
     * @var array|null
     */
    private static $cors = null;
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
     * Forget all routes, names, middlewares and settings (used by the test suite).
     *
     * @return void
     */
    public static function reset() {
        self::$base_path = '';
        self::$routes = [];
        self::$names = [];
        self::$dispatched = false;
        self::$route_matched = false;
        self::$allowed = [];
        self::$is404Called = false;
        self::$group_prefix = '';
        self::$group_middlewares = [];
        self::$middlewares = [];
        self::$cors = null;
        self::$request = null;
        self::$response = null;
    }

    /**
     * Initialize the router.
     *
     * @return void
     */
    public static function init() {
        // HTML forms can only send GET/POST, so allow tunnelling PUT/PATCH/DELETE through a POST field
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_method']) && is_string($_POST['_method'])) {
            $override = strtoupper($_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $_SERVER['REQUEST_METHOD'] = $override;
            }
        }
        self::sendCorsHeaders();
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
     * Configure CORS. Call this BEFORE Route::init() so preflight (OPTIONS) requests get the
     * right headers. Only requests whose Origin is in $origins receive CORS headers.
     *
     * Credentials (cookies) can only be allowed together with an explicit origin list;
     * browsers reject "*" combined with credentials, so that combination sends no headers.
     *
     * @param array $origins        Allowed origins, e.g. ['https://app.example.com'] or ['*'].
     * @param array|null $methods   Allowed methods (default: GET, POST, PUT, PATCH, DELETE, OPTIONS).
     * @param array|null $headers   Allowed request headers (default: Content-Type, Authorization, X-Auth-Token, X-Return-Token).
     * @param bool $credentials     Allow cookies / Authorization on cross-origin requests.
     * @param int $maxAge           Seconds browsers may cache the preflight result.
     * @return void
     */
    public static function cors($origins = ['*'], $methods = null, $headers = null, $credentials = false, $maxAge = 600) {
        self::$cors = [
            'origins' => array_values((array) $origins),
            'methods' => $methods ?: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'headers' => $headers ?: ['Content-Type', 'Authorization', 'X-Auth-Token', 'X-Return-Token'],
            'credentials' => (bool) $credentials,
            'maxAge' => (int) $maxAge,
        ];
    }

    /**
     * Emit CORS headers for the current request when Route::cors() was configured.
     *
     * @return void
     */
    private static function sendCorsHeaders() {
        if (self::$cors === null) {
            return;
        }
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin === '' || !is_string($origin)) {
            return; // not a cross-origin browser request
        }

        $c = self::$cors;
        if (in_array('*', $c['origins'], true) && !$c['credentials']) {
            header('Access-Control-Allow-Origin: *');
        } elseif (in_array($origin, $c['origins'], true)) {
            // Echo only an origin that is on the allow-list (never the raw header value)
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin', false);
            if ($c['credentials']) {
                header('Access-Control-Allow-Credentials: true');
            }
        } else {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Methods: ' . implode(', ', $c['methods']));
            header('Access-Control-Allow-Headers: ' . implode(', ', $c['headers']));
            header('Access-Control-Max-Age: ' . $c['maxAge']);
        }
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
            if (in_array($origin, $allowedOrigins, true)) {
                header("Access-Control-Allow-Origin: $origin");
                header('Vary: Origin', false);
            }
        }
    }

    /**
     * Add CORS methods for allowed HTTP methods.
     *
     * @param array $methods    The allowed HTTP methods for CORS (default: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']).
     * @return void
     */
    public static function addCORSMethods($methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']) {
        header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
    }

    /**
     * Add CORS headers for allowed headers.
     *
     * @param array $headers    The allowed headers for CORS (default: ['Content-Type', 'Authorization']).
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
        if (defined('BASE_PATH')) {
            return realpath(BASE_PATH);
        }
        return realpath(dirname($_SERVER['SCRIPT_FILENAME']));
    }
    
    /**
     * Path helper to support multiple environments
     *
     * @return string
     */
    private static function path(...$segments) {
        // Only trim the END of the first segment so absolute paths ("/var/www/app") keep their leading slash.
        $first = rtrim((string) array_shift($segments), '/\\');
        $rest = array_map(function ($segment) {
            return trim($segment, '/\\');
        }, $segments);
        return join(DIRECTORY_SEPARATOR, array_merge([$first], $rest));
    }

    /**
     * Set the controller directory.
     *
     * @param string $dir   The directory to set.
     * @return void
     */
    public static function setControllersDir($dir) {
        self::$controllersDir = rtrim($dir, '/\\');
    }

    /**
     * Set the route directory.
     *
     * @param string $dir   The directory to set.
     * @return void
     */
    public static function setRoutesDir($dir) {
        self::$routesDir = rtrim($dir, '/\\');
    }

    /**
     * Set the models directory.
     *
     * @param string $dir   The directory to set.
     * @return void
     */
    public static function setModelsDir($dir) {
        self::$modelsDir = rtrim($dir, '/\\');
    }

    /**
     * Set a prefix for the routes that follow, or - when $routes is given - for just that group.
     *
     * The one-argument form changes the prefix for EVERYTHING declared afterwards;
     * prefer Route::group() (or pass $routes here), which restore it automatically.
     *
     * @param string                $prefix       The prefix to set.
     * @param callable|string|null  $routes       Optional: a closure or route file, like group().
     * @param array|null            $middlewares  Optional group middlewares (only with $routes).
     * @return void
     */
    public static function prefix($prefix, $routes = null, $middlewares = null) {
        if ($routes !== null) {
            self::group($prefix, $routes, $middlewares);
            return;
        }
        self::$group_prefix = rtrim(self::$group_prefix, '/') . '/' . trim($prefix, '/\\');
    }

    /**
     * Register a route. Nothing runs until dispatch().
     *
     * @return RouteDefinition
     */
    private static function add($method, $route, $callback, $middlewares) {
        $route = (string) $route;
        if ($route !== '' && $route[0] !== '/') {
            $route = '/' . $route;
        }

        $index = count(self::$routes);
        self::$routes[] = [
            'method' => $method,
            'pattern' => self::$group_prefix . $route,
            'callback' => $callback,
            'middlewares' => array_merge(self::$group_middlewares, (array) $middlewares),
            'where' => [],
            'name' => null,
        ];
        return new RouteDefinition($index);
    }

    /**
     * @internal Used by RouteDefinition.
     */
    public static function setRouteName($index, $name) {
        $name = (string) $name;
        if (isset(self::$names[$name]) && self::$names[$name] !== $index) {
            throw new InvalidArgumentException("Route name already in use: $name");
        }
        self::$routes[$index]['name'] = $name;
        self::$names[$name] = $index;
    }

    /**
     * @internal Used by RouteDefinition.
     */
    public static function addRouteMiddleware($index, $middlewares) {
        self::$routes[$index]['middlewares'] = array_merge(self::$routes[$index]['middlewares'], (array) $middlewares);
    }

    /**
     * @internal Used by RouteDefinition.
     */
    public static function setRouteConstraint($index, $param, $regex) {
        self::$routes[$index]['where'][$param] = $regex;
    }

    /**
     * Register a GET route (also answers HEAD).
     *
     * Route patterns: "/users/:id" captures a parameter; "/users/:id(\d+)" only matches digits.
     * Constraint regexes can't contain "/".
     *
     * @param string                $route        The route pattern.
     * @param callable|string|array $callback     Closure, 'Controller@method', [Controller::class, 'method'] or a file path.
     * @param array                 $middlewares  Middleware names (e.g. ['auth', 'role:admin']).
     * @return RouteDefinition
     */
    public static function get($route, $callback, $middlewares = []) {
        return self::add('GET', $route, $callback, $middlewares);
    }

    /**
     * Register a POST route.
     *
     * @return RouteDefinition
     */
    public static function post($route, $callback, $middlewares = []) {
        return self::add('POST', $route, $callback, $middlewares);
    }

    /**
     * Register a PUT route.
     *
     * @return RouteDefinition
     */
    public static function put($route, $callback, $middlewares = []) {
        return self::add('PUT', $route, $callback, $middlewares);
    }

    /**
     * Register a PATCH route.
     *
     * @return RouteDefinition
     */
    public static function patch($route, $callback, $middlewares = []) {
        return self::add('PATCH', $route, $callback, $middlewares);
    }

    /**
     * Register a DELETE route.
     *
     * @return RouteDefinition
     */
    public static function delete($route, $callback, $middlewares = []) {
        return self::add('DELETE', $route, $callback, $middlewares);
    }

    /**
     * Register a route that answers every HTTP method.
     *
     * @return RouteDefinition
     */
    public static function any($route, $callback, $middlewares = []) {
        return self::add('*', $route, $callback, $middlewares);
    }

    /**
     * Group routes under a prefix and shared middlewares.
     *
     * @param string                $prefix       The prefix for the group (e.g. '/api').
     * @param callable|string       $routes       A closure that declares routes, or a file name in the routes folder.
     * @param array|null            $middlewares  Middlewares applied to every route in the group.
     * @return void
     * @throws Exception If a route file doesn't exist.
     */
    public static function group($prefix, $routes, $middlewares = null) {
        $previousPrefix = self::$group_prefix;
        $previousMiddlewares = self::$group_middlewares;

        $clean = trim((string) $prefix, '/\\');
        self::$group_prefix .= $clean === '' ? '' : '/' . $clean;

        if (is_array($middlewares)) {
            self::$group_middlewares = array_merge(self::$group_middlewares, $middlewares);
        }

        try {
            if (is_string($routes)) {
                // A string is always a route file (never a function name)
                if (strpos($routes, '.php') === false) {
                    $routes .= '.php';
                }
                $file = self::path(self::rootDir(), self::$routesDir, $routes);
                if (!file_exists($file)) {
                    throw new Exception("Route file not found: $routes");
                }
                require $file;
            } elseif (is_callable($routes)) {
                call_user_func($routes);
            }
        } finally {
            // Restore even if a route file throws, so state can't leak into later routes
            self::$group_prefix = $previousPrefix;
            self::$group_middlewares = $previousMiddlewares;
        }
    }

    /**
     * Register a named middleware.
     *
     * A middleware can declare Request and/or Response parameters (they are injected);
     * its other parameter receives the middleware arguments (['admin'] for "role:admin").
     * Returning an array or string sends it as the response and stops the request.
     *
     * @param string   $name
     * @param callable $callback
     * @return void
     */
    public static function middleware($name, $callback) {
        self::$middlewares[$name] = $callback;
    }

    /**
     * Register every file in the middleware folder (name = file name without .php).
     *
     * @return void
     */
    public static function loadMiddlewares() {
        $files = glob(self::path(self::rootDir(), self::$middlewaresDir) . DIRECTORY_SEPARATOR . '*.php');
        if ($files) {
            foreach ($files as $file) {
                self::$middlewares[basename($file, '.php')] = require $file;
            }
        }
    }

    /**
     * Load every model file. (bootstrap.php also registers an autoloader, so this is optional.)
     *
     * @return void
     */
    public static function loadModels() {
        $files = glob(self::path(self::rootDir(), self::$modelsDir) . DIRECTORY_SEPARATOR . '*.php');
        if ($files) {
            foreach ($files as $file) {
                require_once $file;
            }
        }
    }

    /**
     * Register the standard REST routes for a controller:
     * index, create, store, show, edit, update (PUT+PATCH), destroy.
     *
     * @param string       $name        e.g. 'posts' -> /posts
     * @param string|array $controller  PostController::class
     * @param array        $middlewares
     * @return void
     */
    public static function resource($name, $controller, $middlewares = []) {
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
     * Like resource() but without the HTML-form routes (create/edit): index, store, show, update, destroy.
     *
     * @param string       $name
     * @param string|array $controller
     * @param array        $middlewares
     * @return void
     */
    public static function apiResource($name, $controller, $middlewares = []) {
        if (is_array($controller)) {
            $controller = $controller[0];
        }
        $base = '/' . trim($name, '/');

        self::get($base, [$controller, 'index'], $middlewares);
        self::post($base, [$controller, 'store'], $middlewares);
        self::get($base . '/:id', [$controller, 'show'], $middlewares);
        self::put($base . '/:id', [$controller, 'update'], $middlewares);
        self::patch($base . '/:id', [$controller, 'update'], $middlewares);
        self::delete($base . '/:id', [$controller, 'destroy'], $middlewares);
    }

    /**
     * Load route files from the routes folder. This only REGISTERS routes;
     * they run when dispatch() is called (add404() and setSPA() do that for you).
     *
     * @param array $routes File names without .php
     * @return void
     */
    public static function loadRoutes($routes = ['web', 'api']) {
        foreach ($routes as $route) {
            $file = self::path(self::rootDir(), self::$routesDir, "$route.php");
            if (is_file($file)) {
                include_once $file;
            }
        }
    }

    /**
     * Split a request URI into path segments (query string removed, trailing slash ignored).
     */
    private static function requestParts($uri) {
        $path = explode('?', (string) $uri, 2)[0];
        $path = explode('#', $path, 2)[0];
        $path = rtrim($path, '/\\');
        return explode('/', trim($path, '/'));
    }

    /**
     * Try to match one route against the request segments.
     *
     * @return array|null The captured parameters, or null when the path doesn't match.
     */
    private static function matchPath(array $route, array $reqParts) {
        $parts = explode('/', trim(self::$base_path . $route['pattern'], '/'));
        if (count($parts) !== count($reqParts)) {
            return null;
        }

        $params = [];
        foreach ($parts as $i => $part) {
            if (preg_match(self::PARAM_PATTERN, $part, $m)) {
                $value = $reqParts[$i];
                if ($value === '') {
                    return null;
                }
                $regex = $route['where'][$m[1]] ?? ($m[2] ?? null);
                if ($regex !== null && preg_match('#^(?:' . str_replace('#', '\#', $regex) . ')$#', $value) !== 1) {
                    return null;
                }
                $params[] = rawurldecode($value);
                continue;
            }
            if ($part !== $reqParts[$i]) {
                return null;
            }
        }
        return $params;
    }

    /**
     * Find the route for a method + URI without running anything.
     *
     * @param string $method  HTTP method.
     * @param string $uri     Request URI (query string is ignored).
     * @return array|null
     *   ['route' => array, 'params' => array]  a route matched
     *   ['allowed' => array]                   the path exists but not for this method (405)
     *   null                                   nothing matches (404)
     */
    public static function resolve($method, $uri) {
        $method = strtoupper($method);
        $reqParts = self::requestParts($uri);
        $allowed = [];

        foreach (self::$routes as $index => $route) {
            $params = self::matchPath($route, $reqParts);
            if ($params === null) {
                continue;
            }
            $m = $route['method'];
            if ($m === '*' || $m === $method || ($method === 'HEAD' && $m === 'GET')) {
                return ['route' => $route, 'params' => $params, 'index' => $index];
            }
            $allowed[] = $m;
        }

        if ($allowed) {
            $allowed = array_values(array_unique($allowed));
            if (in_array('GET', $allowed, true)) {
                $allowed[] = 'HEAD';
            }
            $allowed[] = 'OPTIONS';
            return ['allowed' => $allowed];
        }
        return null;
    }

    /**
     * Run the first route that matches the current request (and exit). If none matches this
     * returns, remembering which methods WOULD have matched so add404() can answer 405.
     * Safe to call more than once. add404() and setSPA() call it for you.
     *
     * @return void
     */
    public static function dispatch() {
        if (self::$dispatched) {
            return;
        }
        self::$dispatched = true;

        $result = self::resolve($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
        if ($result === null) {
            return;
        }
        if (isset($result['allowed'])) {
            self::$allowed = $result['allowed'];
            return;
        }

        self::$route_matched = true;
        $route = $result['route'];
        self::runMiddlewares($route['middlewares']);
        self::respond(self::execute($route['callback'], $result['params']));
        exit();
    }

    /**
     * Build the URL of a named route: Route::url('users.show', ['id' => 5], ['tab' => 'info']).
     * Parameters not used in the path are added to the query string.
     *
     * @param string $name
     * @param array  $params  Route parameters, by name.
     * @param array  $query   Extra query string values.
     * @return string
     * @throws InvalidArgumentException If the route doesn't exist or a parameter is missing.
     */
    public static function url($name, array $params = [], array $query = []) {
        if (!isset(self::$names[$name])) {
            throw new InvalidArgumentException("Route not defined: $name");
        }
        $pattern = self::$routes[self::$names[$name]]['pattern'];

        $segments = [];
        foreach (explode('/', trim($pattern, '/')) as $part) {
            if (preg_match(self::PARAM_PATTERN, $part, $m)) {
                if (!array_key_exists($m[1], $params)) {
                    throw new InvalidArgumentException("Missing parameter '{$m[1]}' for route $name");
                }
                $segments[] = rawurlencode((string) $params[$m[1]]);
                unset($params[$m[1]]);
            } else {
                $segments[] = $part;
            }
        }

        $url = self::$base_path . '/' . implode('/', $segments);
        $query = array_merge($params, $query);
        if ($query) {
            $url .= '?' . http_build_query($query);
        }
        return $url;
    }

    /**
     * Describe every registered route (used by "php mvc routes").
     *
     * @return array List of ['method', 'uri', 'name', 'action', 'middleware'].
     */
    public static function routes() {
        $list = [];
        foreach (self::$routes as $route) {
            $list[] = [
                'method' => $route['method'] === '*' ? 'ANY' : $route['method'],
                'uri' => self::$base_path . ($route['pattern'] === '' ? '/' : $route['pattern']),
                'name' => $route['name'] ?? '',
                'action' => self::describe($route['callback']),
                'middleware' => implode(', ', array_map(function ($mw) {
                    return is_string($mw) ? $mw : 'Closure';
                }, $route['middlewares'])),
            ];
        }
        return $list;
    }

    private static function describe($callback) {
        if ($callback instanceof Closure) {
            return 'Closure';
        }
        if (is_string($callback)) {
            return $callback;
        }
        if (is_array($callback) && count($callback) === 2) {
            return (is_object($callback[0]) ? get_class($callback[0]) : $callback[0]) . '@' . $callback[1];
        }
        return is_object($callback) ? get_class($callback) : 'unknown';
    }

    /**
     * Class/type names of a parameter's type declaration, for Request/Response injection.
     * Works on every supported PHP version:
     *  - PHP 7.1+: ReflectionNamedType::getName()
     *  - PHP 8.0+: union types expose their parts through getTypes()
     *  - PHP 7.0:  ReflectionType has no getName(); its string form is the type name
     */
    private static function typeNames($type) {
        if ($type === null) {
            return [];
        }
        if (method_exists($type, 'getName')) {
            return [$type->getName()];
        }
        if (method_exists($type, 'getTypes')) {
            $names = [];
            foreach ($type->getTypes() as $t) {
                if (method_exists($t, 'getName')) {
                    $names[] = $t->getName();
                }
            }
            return $names;
        }
        return [ltrim((string) $type, '?')];
    }

    private static function reflect($callback) {
        if ($callback instanceof Closure) {
            return new ReflectionFunction($callback);
        }
        if (is_array($callback) && count($callback) === 2) {
            return new ReflectionMethod($callback[0], $callback[1]);
        }
        if (is_object($callback)) {
            return new ReflectionMethod($callback, '__invoke');
        }
        if (is_string($callback) && strpos($callback, '::') !== false) {
            return new ReflectionMethod($callback);
        }
        return new ReflectionFunction($callback);
    }

    /**
     * Work out the arguments for a handler: Request/Response parameters are injected by type,
     * everything else is filled in order from the matched route parameters.
     */
    private static function buildArgs(ReflectionFunctionAbstract $ref, array $params) {
        $args = [];
        $i = 0;
        foreach ($ref->getParameters() as $param) {
            $names = self::typeNames($param->getType());
            if (in_array(Request::class, $names, true)) {
                $args[] = self::$request;
                continue;
            }
            if (in_array(Response::class, $names, true)) {
                $args[] = self::$response;
                continue;
            }
            if ($param->isVariadic()) {
                while ($i < count($params)) {
                    $args[] = $params[$i++];
                }
                break;
            }
            if ($i < count($params)) {
                $args[] = $params[$i++];
                continue;
            }
            $args[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
        }
        return $args;
    }

    private static function invoke($callback, array $params) {
        return call_user_func_array($callback, self::buildArgs(self::reflect($callback), $params));
    }

    /**
     * Turn a handler's return value into a response: arrays/objects become JSON,
     * strings become HTML. null (and anything else) is ignored, so handlers that
     * already send their response with $res->json() etc. work unchanged.
     */
    private static function respond($result) {
        if ($result === null || !self::$response) {
            return;
        }
        if (is_array($result) || $result instanceof JsonSerializable || $result instanceof stdClass) {
            self::$response->json($result);
        }
        if (is_string($result)) {
            self::$response->send($result, 'text/html; charset=utf-8');
        }
    }

    private static function runMiddlewares(array $middlewares) {
        foreach ($middlewares as $mw) {
            $params = [];
            if (is_string($mw)) {
                $parts = explode(':', $mw, 2);
                $name = $parts[0];
                $params = isset($parts[1]) ? explode(',', $parts[1]) : [];

                if (!isset(self::$middlewares[$name])) {
                    throw new Exception("Middleware not found: $name");
                }
                $callback = self::$middlewares[$name];
            } else {
                $callback = $mw; // a closure given directly
            }

            if (!is_callable($callback)) {
                throw new Exception('Middleware is not callable: ' . (is_string($mw) ? $mw : 'closure'));
            }

            $args = [];
            $gaveParams = false;
            foreach (self::reflect($callback)->getParameters() as $param) {
                $names = self::typeNames($param->getType());
                if (in_array(Request::class, $names, true)) {
                    $args[] = self::$request;
                } elseif (in_array(Response::class, $names, true)) {
                    $args[] = self::$response;
                } elseif (!$gaveParams) {
                    $args[] = $params;
                    $gaveParams = true;
                } else {
                    $args[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
                }
            }

            self::respond(call_user_func_array($callback, $args));
        }
    }

    private static function runController($callback, array $params) {
        // 'AuthController@login' or [MyController::class, 'method']
        if (is_string($callback)) {
            list($controller, $method) = explode('@', $callback, 2);
        } elseif (is_array($callback) && count($callback) === 2) {
            $controller = $callback[0];
            $method = $callback[1];
        } else {
            throw new Exception("Invalid controller callback.");
        }

        if (is_object($controller)) {
            $instance = $controller;
        } else {
            $className = (string) $controller;
            $shortName = basename(str_replace('\\', '/', $className));
            $file = self::path(self::rootDir(), self::$controllersDir, "$shortName.php");
            if (file_exists($file)) {
                require_once $file;
            }
            if (!class_exists($className)) {
                throw new Exception("Controller class not found: $className");
            }
            $instance = new $className();
        }

        $ref = new ReflectionMethod($instance, $method);
        if (!$ref->isPublic()) {
            throw new Exception("Controller method is not public: " . get_class($instance) . "::$method");
        }
        return $ref->invokeArgs($instance, self::buildArgs($ref, $params));
    }

    private static function execute($callback, array $params) {
        // Closure / invokable object
        if ($callback instanceof Closure || is_object($callback)) {
            return self::invoke($callback, $params);
        }
        // Plain function name (but not 'Controller@method')
        if (is_string($callback) && strpos($callback, '@') === false && function_exists($callback)) {
            return self::invoke($callback, $params);
        }
        // Controller
        if ((is_string($callback) && strpos($callback, '@') !== false) || (is_array($callback) && count($callback) === 2)) {
            return self::runController($callback, $params);
        }
        // A file inside the project
        self::serveFile((string) $callback);
        return null;
    }

    private static function serveFile($callback) {
        $root = self::rootDir();
        $file = realpath(self::path($root, $callback));

        if (!$file || strncmp($file, $root . DIRECTORY_SEPARATOR, strlen($root) + 1) !== 0) {
            throw new Exception("Invalid file path");
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'php') {
            // Buffered: if the page fails halfway, nothing partial reaches the browser
            echo Response::capture($file);
            return;
        }

        // Static files are sent as-is and never executed as PHP
        $types = [
            'html' => 'text/html; charset=utf-8',
            'htm' => 'text/html; charset=utf-8',
            'txt' => 'text/plain; charset=utf-8',
            'json' => 'application/json',
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
        ];
        header('Content-Type: ' . ($types[$ext] ?? 'text/html; charset=utf-8'));
        readfile($file);
    }

    /**
     * Serve a single-page app file for every URL that matched no route.
     *
     * @param string $file  The file to serve (default: index.html).
     * @return void
     */
    public static function setSPA($file = 'index.html') {
        self::dispatch();
        if (self::$is404Called) {
            throw new Exception("Cannot set SPA after 404 callback has been called");
        }
        if ($file) {
            echo Response::capture(self::path(self::rootDir(), $file));
        }
        exit();
    }

    /**
     * Run the matching route, and if there is none fail with an HttpException:
     * 405 (with an Allow header) when the path exists under a different method, otherwise 404.
     * The ErrorHandler renders it: JSON for API requests, otherwise $file if given
     * (kept for backwards compatibility), otherwise views/errors/404.php or the built-in page.
     *
     * @param string|null $file  Page to show for a 404 in a browser, relative to the project root.
     * @return void
     * @throws HttpException
     */
    public static function add404($file = null) {
        self::dispatch();
        self::$is404Called = true;

        if (self::$allowed) {
            $allow = implode(', ', self::$allowed);
            throw new HttpException(405, 'Allowed methods: ' . $allow, ['Allow' => $allow]);
        }

        $request = self::$request ?: new Request();
        if ($file && !$request->wantsJson()) {
            $html = Response::capture(self::path(self::rootDir(), $file));
            http_response_code(404);
            echo $html;
            exit();
        }

        throw new HttpException(404, 'The requested resource was not found');
    }
}
