<?php

/**
 * @package Router
 * @author jhzrmx
 * @version 1.1.0
 * @license MIT
 * @link https://github.com/jhzrmx/php-mvc-template
 * @requires PHP 7.0+
 * @dependencies Validator (libs/Validator.php), HttpException and ValidationException (libs/HttpException.php)
 * @description Request, Response, RouteDefinition and Route: a small Laravel-style router with middleware,
 * route groups, named routes, parameter constraints, Request/Response injection into handlers
 * and PHP view rendering with layouts.
 */

class Request {
    /**
     * The decoded JSON body (array) or the raw body (string) when it isn't valid JSON.
     *
     * @var array|string
     */
    public $body;
    /**
     * The query parameters of the request ($_GET).
     *
     * @var array
     */
    public $params;
    /**
     * The form data of the request ($_POST).
     *
     * @var array
     */
    public $formData;
    /**
     * The files of the request ($_FILES).
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

    /**
     * HTTP method (after _method override), upper-case.
     *
     * @return string
     */
    public function method() {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Request path without the query string, e.g. "/api/users/5".
     *
     * @return string
     */
    public function path() {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = explode('?', $uri, 2)[0];
        return $path === '' ? '/' : $path;
    }

    /**
     * The client IP as seen by PHP (REMOTE_ADDR). Behind a proxy, configure the web server
     * to restore the real IP; forwarded headers are deliberately NOT trusted here.
     *
     * @return string
     */
    public function ip() {
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Read a request header (case-insensitive): $req->header('Content-Type').
     *
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    public function header($name, $default = null) {
        $key = strtoupper(str_replace('-', '_', $name));
        if ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
            return $_SERVER[$key] ?? $default;
        }
        if ($key === 'AUTHORIZATION') {
            return $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? $default;
        }
        return $_SERVER['HTTP_' . $key] ?? $default;
    }

    /**
     * The token from "Authorization: Bearer <token>", or null.
     *
     * @return string|null
     */
    public function bearerToken() {
        $header = $this->header('Authorization', '');
        if (is_string($header) && preg_match('/^Bearer\s+(.+)$/i', trim($header), $m)) {
            return trim($m[1]);
        }
        return null;
    }

    public function isJson() {
        return stripos((string) $this->header('Content-Type', ''), 'json') !== false;
    }

    /**
     * True when the client expects JSON: /api/* paths, Accept: application/json, a JSON request
     * body, or X-Requested-With: XMLHttpRequest. Used to pick JSON vs HTML error pages.
     *
     * @return bool
     */
    public function wantsJson() {
        $path = $this->path();
        if ($path === '/api' || strpos($path, '/api/') === 0) {
            return true;
        }
        if (stripos((string) $this->header('Accept', ''), 'application/json') !== false) {
            return true;
        }
        if ($this->isJson()) {
            return true; // a client that sends JSON expects JSON back
        }
        return strtolower((string) $this->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    /**
     * The JSON body as an array (empty array if the body isn't a JSON object/array).
     *
     * @param string|null $key
     * @param mixed       $default
     * @return mixed
     */
    public function json($key = null, $default = null) {
        $data = is_array($this->body) ? $this->body : [];
        if ($key === null) {
            return $data;
        }
        return $data[$key] ?? $default;
    }

    /**
     * Everything the client sent: query string, then form fields, then the JSON body
     * (later sources win). Files are not included.
     *
     * @return array
     */
    public function all() {
        return array_merge(
            is_array($this->params) ? $this->params : [],
            is_array($this->formData) ? $this->formData : [],
            $this->json()
        );
    }

    /**
     * One input value from all(): $req->input('page', 1).
     */
    public function input($key, $default = null) {
        $all = $this->all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function has($key) {
        return array_key_exists($key, $this->all());
    }

    /**
     * Only the listed keys from all().
     */
    public function only(array $keys) {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    /**
     * Validate input; on failure throw a ValidationException (HTTP 422), which the error handler
     * turns into {"error", "message", "errors"} JSON or an error page.
     * Returns the validated fields on success.
     *
     *   $data = $req->validate(['email' => 'required|email'], [], $req->json());
     *
     * @param array      $rules    See Validator.
     * @param array      $messages Custom messages ("field.rule" or "rule" => text).
     * @param array|null $data     What to validate (default: all()). Pass $req->json() to ignore the query string.
     * @return array
     * @throws ValidationException
     */
    public function validate(array $rules, array $messages = [], $data = null) {
        $validator = Validator::make($data === null ? $this->all() : $data, $rules, $messages);
        if ($validator->fails()) {
            throw new ValidationException($validator->errors(), (string) $validator->firstError());
        }
        return $validator->validated();
    }
}

class Response {
    /**
     * Folder (inside the project) that view() loads templates from.
     *
     * @var string
     */
    public static $viewsDir = 'views';

    /**
     * Data passed to PHP view files.
     *
     * @var array
     */
    private $viewData = [];

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
     * Set a response header (chainable). CR/LF are stripped to prevent header injection.
     *
     * @param string $name
     * @param string $value
     * @param bool   $replace
     * @return $this
     */
    public function header($name, $value, $replace = true) {
        if (preg_match('/^[A-Za-z0-9-]+$/', (string) $name) !== 1) {
            throw new InvalidArgumentException("Invalid header name: $name");
        }
        header($name . ': ' . str_replace(["\r", "\n"], '', (string) $value), $replace);
        return $this;
    }

    /**
     * Send a JSON response.
     *
     * @param mixed $data   The data to send.
     * @return void
     */
    public function json($data) {
        // JSON_INVALID_UTF8_SUBSTITUTE exists from PHP 7.2; older versions fail on invalid UTF-8 (handled below)
        $json = json_encode($data, defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0);
        if ($json === false) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo '{"error":"Internal Server Error","message":"The response could not be encoded"}';
            exit();
        }
        header('Content-Type: application/json');
        echo $json;
        exit();
    }

    /**
     * Send a JSON error: {"error": ..., "message": ..., ...extra}
     *
     * @param int    $status
     * @param string $error
     * @param string $message
     * @param array  $extra
     * @return void
     */
    public function error($status, $error, $message = '', array $extra = []) {
        $this->status($status)->json(array_merge(['error' => $error, 'message' => $message], $extra));
    }

    /**
     * Send an empty 204 response.
     *
     * @return void
     */
    public function noContent() {
        http_response_code(204);
        exit();
    }

    /**
     * Send a text response.
     *
     * @param string $text          The text to send.
     * @param string $contentType   The content type to send.
     * @return void
     */
    public function send($text, $contentType = 'text/plain') {
        header("Content-Type: $contentType");
        echo $text;
        exit();
    }

    /**
     * Send a file response (PHP files are executed).
     *
     * The file is rendered into a buffer first, so if it fails halfway nothing partial is sent
     * and the error handler can show a clean error page instead.
     *
     * @param string $file  The file to send.
     * @param array $data   Variables to extract into the file scope.
     * @return void
     */
    public function file($file, array $data = []) {
        $html = self::capture($file, array_merge($this->viewData, $data));
        echo $html;
        exit();
    }

    /**
     * Add variables to be used by a PHP view file.
     *
     * Usage:
     *   $res->pass(['user' => $user])->file('views/dashboard.php');
     *
     * @param array $data   The data to pass to a PHP view file
     * @return $this
     */
    public function pass(array $data) {
        $this->viewData = array_merge($this->viewData, $data);
        return $this;
    }

    /**
     * Render views/<name>.php, optionally inside a layout, and send it.
     *
     *   $res->view('dashboard', ['user' => $user]);
     *
     * The page is fully rendered in memory before anything is sent. If the view (or its layout)
     * throws, the partial output is discarded and the error handler answers with a proper error
     * page, so a failing view can never leave a half-rendered page in the browser.
     *
     * A view picks its layout and title by setting variables at the top of the file:
     *   <?php $layout = 'layouts/app'; $title = 'Dashboard'; ?>
     * The layout receives the rendered page as $content (already HTML: echo it raw) and $title.
     * Escape everything user-controlled with e(). Inside a view you can call abort(404) etc.
     *
     * @param string      $name   View name relative to views/, without ".php" (letters, digits, _ - /).
     * @param array       $data   Variables for the view.
     * @param string|null $layout Layout name; overrides the one set by the view.
     * @return void
     */
    public function view($name, array $data = [], $layout = null) {
        $html = $this->render($name, $data, $layout);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit();
    }

    /**
     * Render a view (and its layout) to a string without sending it. Same rules as view().
     *
     * @param string      $name
     * @param array       $data
     * @param string|null $layout
     * @return string
     * @throws InvalidArgumentException For an invalid view name.
     * @throws Exception                If the view/layout file doesn't exist.
     * @throws Throwable                Whatever the view throws (output buffers are cleaned up first).
     */
    public function render($name, array $data = [], $layout = null) {
        $vars = array_merge($this->viewData, $data);
        $page = self::run($this->viewPath($name), $vars);

        $layout = $layout ?? $page['layout'];
        $html = $page['content'];
        if ($layout) {
            $vars['content'] = $html;
            $vars['title'] = $page['title'] ?? ($vars['title'] ?? null);
            $html = self::run($this->viewPath($layout), $vars)['content'];
        }
        return $html;
    }

    /**
     * Include a PHP file with variables and return everything it printed.
     * Output buffers are always left exactly as they were found, even if the file throws
     * or forgets to close a buffer it opened.
     *
     * @param string $file
     * @param array  $vars
     * @return string
     * @throws Throwable Whatever the file throws.
     */
    public static function capture($file, array $vars = []) {
        return self::run($file, $vars)['content'];
    }

    private static function run($__file, array $__vars) {
        $__level = ob_get_level();
        extract($__vars, EXTR_SKIP);
        ob_start();
        try {
            require $__file;
            // Close buffers the file opened and forgot about (their content is kept)
            while (ob_get_level() > $__level + 1) {
                ob_end_flush();
            }
            $__content = ob_get_clean();
        } catch (Throwable $__e) {
            // Throw away everything the file printed before failing
            while (ob_get_level() > $__level) {
                ob_end_clean();
            }
            throw $__e;
        }
        return [
            'content' => $__content,
            'layout' => isset($layout) ? $layout : null,
            'title' => isset($title) ? $title : null,
        ];
    }

    private function viewPath($__name) {
        $__name = (string) $__name;
        if ($__name === '' || $__name[0] === '/' || strpos($__name, '//') !== false
            || preg_match('#^[A-Za-z0-9_\-/]+$#', $__name) !== 1) {
            throw new InvalidArgumentException("Invalid view name: $__name");
        }
        $base = defined('BASE_PATH') ? BASE_PATH : getcwd();
        $file = $base . '/' . trim(self::$viewsDir, '/\\') . '/' . $__name . '.php';
        if (!is_file($file)) {
            throw new Exception("View not found: $__name");
        }
        return $file;
    }
    
    /**
     * Redirect to a URL.
     *
     * @param string $url       The URL to redirect to.
     * @param int $statusCode   The status code for the redirect (default: 302).
     * @return void
     */
    public function redirect($url, $statusCode = 302) {
        http_response_code($statusCode);
        header('Location: ' . str_replace(["\r", "\n"], '', (string) $url));
        exit();
    }
}

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
