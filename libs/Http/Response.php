<?php

/**
 * @package Response
 * @author jhzrmx
 * @version 1.1.0
 * @license MIT
 * @link https://github.com/jhzrmx/php-mvc-template
 * @requires PHP 7.0+
 * @dependencies None (standalone apart from the Request/Route classes in this folder)
 * @description Builds the HTTP response: status, headers, JSON, redirects, files and PHP views with layouts.
 */

namespace JHz\Http;

use Exception;
use InvalidArgumentException;
use Throwable;

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
