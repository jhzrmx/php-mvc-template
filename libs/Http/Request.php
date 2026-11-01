<?php

/**
 * @package Request
 * @author jhzrmx
 * @version 1.1.0
 * @license MIT
 * @link https://github.com/jhzrmx/php-mvc-template
 * @requires PHP 7.0+
 * @dependencies Validator (libs/Support/Validator.php), ValidationException (libs/Exceptions/ValidationException.php)
 * @description The incoming HTTP request: JSON/form/query input, headers, client details and validate().
 */

namespace JHz\Http;

use JHz\Exceptions\ValidationException;
use JHz\Support\Validator;

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
