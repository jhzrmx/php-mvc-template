<?php

/**
 * @package ErrorHandler
 * @author jhzrmx
 * @version 1.0.0
 * @license MIT
 * @link https://github.com/jhzrmx/php-mvc-template
 * @requires PHP 7.0+
 * @dependencies HttpException (libs/Exceptions/), Route, Request and Response (libs/Http/), env() helper (libs/helpers.php); uses Log (libs/Support/Log.php) when available
 * @description Central error handling. Call ErrorHandler::register() once at startup (bootstrap.php does).
 *
 * What it does
 *  - Turns PHP warnings and notices into ErrorException, so they can't silently corrupt a page
 *    (deprecations are only logged at "debug" level). Set ERRORS_AS_EXCEPTIONS=false to disable.
 *  - Catches uncaught exceptions and fatal errors (parse errors, out of memory, ...).
 *  - Discards any half-rendered output, then sends one clean response:
 *      JSON for API requests / clients that want JSON, otherwise an HTML error page
 *      (views/errors/<status>.php, then views/errors/error.php, then a built-in page).
 *  - Logs server errors (5xx) with Log::exception(); client errors (4xx) only at debug level.
 *  - Shows details (class, file, line, trace) only in debug mode; otherwise a generic message.
 *    HttpException messages are always shown, because they are written for the client.
 *
 * Debug mode: APP_DEBUG=true/false wins. Without it, debug is on only for APP_ENV
 * local/development/dev/testing. With no APP_ENV at all (e.g. a missing .env), details are shown
 * only to requests from the same machine.
 */

namespace JHz\Exceptions;

use JHz\Http\Request;
use JHz\Http\Response;
use JHz\Http\Route;
use JHz\Support\Log;
use ErrorException;
use Throwable;

class ErrorHandler {
    private static $registered = false;
    private static $handling = false;
    private static $reserved = null;

    /**
     * Install the error, exception and shutdown handlers (safe to call twice).
     *
     * @return void
     */
    public static function register() {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        // Errors are rendered by this class; never print raw PHP errors to visitors
        ini_set('display_errors', '0');
        error_reporting(E_ALL);

        // Memory we can give back so a fatal "out of memory" error can still be reported
        self::$reserved = str_repeat('x', 20480);

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * PHP error handler: warnings/notices become exceptions, deprecations are logged.
     *
     * @return bool|void
     */
    public static function handleError($severity, $message, $file = '', $line = 0) {
        // Respects error_reporting() and the @ operator
        if (!(error_reporting() & $severity)) {
            return false;
        }

        if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
            if (class_exists(Log::class)) {
                Log::debug("Deprecated: $message in $file:$line");
            }
            return true;
        }

        if (env('ERRORS_AS_EXCEPTIONS', true) === false) {
            return false; // fall back to PHP's normal handling
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Uncaught exception handler.
     *
     * @param Throwable $e
     * @return void
     */
    public static function handleException($e) {
        self::handle($e);
    }

    /**
     * Shutdown handler: reports fatal errors that exceptions can't catch.
     *
     * @return void
     */
    public static function handleShutdown() {
        self::$reserved = null;
        if (self::$handling) {
            return;
        }
        $error = error_get_last();
        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if ($error && in_array($error['type'], $fatal, true)) {
            self::handle(new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
        }
    }

    /**
     * Is debug mode on (show error details)?
     *
     * @return bool
     */
    public static function debug() {
        $flag = env('APP_DEBUG');
        if ($flag !== null) {
            return $flag === true || (is_string($flag) && in_array(strtolower($flag), ['1', 'true', 'yes', 'on'], true));
        }

        $appEnv = env('APP_ENV');
        if ($appEnv === null || $appEnv === '') {
            // Nothing configured: only show details to someone on the same machine
            return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
        }
        return in_array(strtolower((string) $appEnv), ['local', 'development', 'dev', 'testing', 'test'], true);
    }

    /**
     * Report the error, discard any partial output, send the error response and stop.
     *
     * @param Throwable    $e
     * @param Request|null $request
     * @return void
     */
    public static function handle($e, $request = null) {
        if (self::$handling) {
            // An error while handling an error: use the plainest possible response
            self::emergency($e);
            exit(1);
        }
        self::$handling = true;

        try {
            self::report($e);
            self::send(self::response($e, $request));
        } catch (Throwable $inner) {
            self::emergency($inner);
        }
        exit(1);
    }

    /**
     * Build the response for an error without sending anything (this is what the tests call).
     *
     * @param Throwable    $e
     * @param Request|null $request
     * @return array ['status' => int, 'headers' => array, 'contentType' => string, 'body' => string]
     */
    public static function response($e, $request = null) {
        if ($request === null) {
            $request = Route::request();
        }
        if ($request === null) {
            $request = new Request();
        }

        $isHttp = $e instanceof HttpException;
        $status = $isHttp ? $e->getStatusCode() : 500;
        $debug = self::debug();
        $title = $isHttp ? $e->getTitle() : HttpException::titleFor(500);

        if ($isHttp) {
            $message = $e->getMessage() !== '' ? $e->getMessage() : $title;
        } elseif ($debug) {
            $message = $e->getMessage() !== '' ? $e->getMessage() : get_class($e);
        } else {
            $message = 'An unexpected error occurred';
        }

        $extra = $isHttp ? $e->getData() : [];
        $details = ($debug && (!$isHttp || $status >= 500)) ? self::details($e) : null;
        $headers = $isHttp ? $e->getHeaders() : [];

        if ($request->wantsJson()) {
            $payload = array_merge(['error' => $title, 'message' => $message], $extra);
            if ($details !== null) {
                $payload['debug'] = $details;
            }
            // JSON_INVALID_UTF8_SUBSTITUTE exists from PHP 7.2; JSON_PARTIAL_OUTPUT_ON_ERROR covers older versions
            $jsonFlags = JSON_PARTIAL_OUTPUT_ON_ERROR;
            if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
                $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
            }
            $body = json_encode($payload, $jsonFlags);
            if ($body === false) {
                $body = '{"error":"Internal Server Error","message":"The error could not be encoded"}';
            }
            $contentType = 'application/json';
        } else {
            $body = self::renderHtml($status, $title, $message, $extra['errors'] ?? [], $details);
            $contentType = 'text/html; charset=utf-8';
        }

        if ($request->method() === 'HEAD') {
            $body = '';
        }

        return [
            'status' => $status,
            'headers' => $headers,
            'contentType' => $contentType,
            'body' => $body,
        ];
    }

    private static function report($e) {
        $status = $e instanceof HttpException ? $e->getStatusCode() : 500;
        if (!class_exists(Log::class)) {
            error_log(get_class($e) . ': ' . $e->getMessage());
            return;
        }
        $context = [
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'path' => explode('?', (string) ($_SERVER['REQUEST_URI'] ?? ''), 2)[0],
        ];
        if ($status < 500) {
            Log::debug("HTTP $status: " . $e->getMessage(), $context);
            return;
        }
        Log::exception($e, $context);
    }

    /**
     * Drop partial output and send the response (if it's still possible to change headers).
     */
    private static function send(array $r) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (headers_sent()) {
            return; // too late to change the response; the error is already logged
        }

        http_response_code($r['status']);
        header('Content-Type: ' . $r['contentType']);
        header('Cache-Control: no-store');
        foreach ($r['headers'] as $name => $value) {
            if (preg_match('/^[A-Za-z0-9-]+$/', (string) $name) === 1) {
                header($name . ': ' . str_replace(["\r", "\n"], '', (string) $value));
            }
        }
        echo $r['body'];
    }

    private static function details($e) {
        $root = defined('BASE_PATH') ? BASE_PATH . DIRECTORY_SEPARATOR : '';
        $strip = function ($text) use ($root) {
            return $root !== '' ? str_replace($root, '', (string) $text) : (string) $text;
        };
        return [
            'exception' => get_class($e),
            'file' => $strip($e->getFile()),
            'line' => $e->getLine(),
            'trace' => array_map($strip, array_slice(explode("\n", $e->getTraceAsString()), 0, 30)),
        ];
    }

    private static function renderHtml($status, $title, $message, $errors, $details) {
        $vars = [
            'status' => $status,
            'title' => $title,
            'message' => $message,
            'errors' => $errors,
            'details' => $details,
        ];

        $response = new Response();
        foreach (['errors/' . $status, 'errors/error'] as $view) {
            try {
                return $response->render($view, $vars);
            } catch (Throwable $inner) {
                // A missing view is normal; a view that is broken itself is logged
                if (strpos($inner->getMessage(), 'View not found') !== 0 && class_exists(Log::class)) {
                    Log::warning('Error view failed: ' . $inner->getMessage());
                }
            }
        }
        return self::builtinHtml($status, $title, $message, $details);
    }

    private static function builtinHtml($status, $title, $message, $details) {
        $h = function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>' . $h($status . ' ' . $title)
            . '</title></head><body style="font-family:sans-serif;text-align:center;margin-top:15vh">'
            . '<h1>' . $h($status) . ' ' . $h($title) . '</h1><p>' . $h($message) . '</p>';
        if ($details !== null) {
            $html .= '<pre style="text-align:left;display:inline-block;max-width:90%;overflow:auto">'
                . $h($details['exception'] . ' at ' . $details['file'] . ':' . $details['line'] . "\n\n" . implode("\n", $details['trace']))
                . '</pre>';
        }
        return $html . '</body></html>';
    }

    /**
     * Last resort when the normal error response itself failed.
     */
    private static function emergency($e) {
        error_log('ErrorHandler failure: ' . get_class($e) . ': ' . $e->getMessage());
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo '500 Internal Server Error';
    }
}
