<?php

/**
 * @package HttpException
 * @author jhzrmx
 * @version 1.0.0
 * @license MIT
 * @link https://github.com/jhzrmx/php-mvc-template
 * @requires PHP 5.6+
 * @dependencies None (standalone, can be downloaded and used on its own)
 * @description An error that should be shown to the client with a specific HTTP status.
 * The message is meant for the client, so keep secrets out of it.
 *
 * Throw it from anywhere: controllers, middleware, models, even PHP views.
 *
 *   throw new HttpException(404, 'Post not found');
 *   abort(403, 'Admins only');
 *   abort(429, 'Slow down', ['Retry-After' => 30]);
 *
 * The ErrorHandler turns it into JSON (API / JSON clients) or an HTML error page.
 */
class HttpException extends RuntimeException {
    private static $titles = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        413 => 'Payload Too Large',
        415 => 'Unsupported Media Type',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
    ];

    private $statusCode;
    private $headers;

    /**
     * @param int            $statusCode HTTP status (400-599; anything else becomes 500).
     * @param string         $message    Shown to the client.
     * @param array          $headers    Extra response headers, e.g. ['Retry-After' => 30].
     * @param Throwable|null $previous
     */
    public function __construct($statusCode = 500, $message = '', array $headers = [], $previous = null) {
        $statusCode = (int) $statusCode;
        if ($statusCode < 400 || $statusCode > 599) {
            $statusCode = 500;
        }
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        parent::__construct((string) $message, $statusCode, $previous);
    }

    public function getStatusCode() {
        return $this->statusCode;
    }

    public function getHeaders() {
        return $this->headers;
    }

    /**
     * Short name of the error ("Not Found"); used as the "error" field of JSON responses.
     */
    public function getTitle() {
        return self::titleFor($this->statusCode);
    }

    /**
     * Extra fields merged into the JSON error body.
     *
     * @return array
     */
    public function getData() {
        return [];
    }

    public static function titleFor($status) {
        if (isset(self::$titles[$status])) {
            return self::$titles[$status];
        }
        return $status >= 500 ? 'Server Error' : 'Error';
    }
}

/**
 * Thrown when input fails validation (HTTP 422). Request::validate() throws it.
 * Catch it if you want to handle failures yourself (e.g. redirect back to a form):
 *
 *   try { $data = $req->validate([...]); }
 *   catch (ValidationException $e) { $e->errors(); }
 */
class ValidationException extends HttpException {
    private $errors;

    /**
     * @param array  $errors  ['field' => ['message', ...]]
     * @param string $message Summary shown to the client (default: a generic sentence).
     */
    public function __construct(array $errors, $message = '', $previous = null) {
        $this->errors = $errors;
        parent::__construct(422, $message !== '' ? $message : 'The given data was invalid.', [], $previous);
    }

    public function errors() {
        return $this->errors;
    }

    public function getTitle() {
        return 'Validation failed';
    }

    public function getData() {
        return ['errors' => $this->errors];
    }
}
