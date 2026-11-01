<?php

/**
 * @package Log
 * @author jhzrmx
 * @version 1.0.0
 * @license MIT
 * @link https://github.com/jhzrmx/php-mvc-template
 * @requires PHP 7.0+
 * @dependencies env() helper (libs/DotEnv.php)
 * @description Tiny file logger: storage/logs/app-YYYY-MM-DD.log
 *
 *   Log::info('User signed up', ['id' => 5]);
 *   Log::error('Payment failed');
 *   Log::exception($e);
 *
 * Minimum level comes from LOG_LEVEL in .env (debug, info, warning, error).
 * Default: "info" (and "warning" when APP_ENV=production). If the log directory isn't
 * writable it falls back to PHP's error_log(), so messages are never silently lost.
 */
class Log {
    private static $levels = ['debug' => 100, 'info' => 200, 'warning' => 300, 'error' => 400];
    private static $dir = null;

    /**
     * Log somewhere else. Pass null to go back to storage/logs.
     */
    public static function setDirectory($dir) {
        self::$dir = $dir === null ? null : rtrim($dir, '/\\');
    }

    private static function dir() {
        if (self::$dir === null) {
            $base = defined('BASE_PATH') ? BASE_PATH : getcwd();
            self::$dir = $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        }
        return self::$dir;
    }

    private static function minLevel() {
        $default = env('APP_ENV') === 'production' ? 'warning' : 'info';
        $level = strtolower((string) env('LOG_LEVEL', $default));
        return self::$levels[$level] ?? self::$levels['info'];
    }

    public static function debug($message, array $context = []) {
        self::write('debug', $message, $context);
    }

    public static function info($message, array $context = []) {
        self::write('info', $message, $context);
    }

    public static function warning($message, array $context = []) {
        self::write('warning', $message, $context);
    }

    public static function error($message, array $context = []) {
        self::write('error', $message, $context);
    }

    /**
     * Log an exception (class, message, file:line and trace).
     */
    public static function exception($e, array $context = []) {
        $context['exception'] = get_class($e) . ' at ' . $e->getFile() . ':' . $e->getLine();
        $context['trace'] = $e->getTraceAsString();
        self::write('error', $e->getMessage(), $context);
    }

    private static function write($level, $message, array $context) {
        if (self::$levels[$level] < self::minLevel()) {
            return;
        }

        // One entry per line: strip newlines so user input can't forge log lines
        $message = str_replace(["\r", "\n"], ' ', (string) $message);
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $level . ': ' . $message;
        if ($context) {
            // JSON_INVALID_UTF8_SUBSTITUTE exists from PHP 7.2; JSON_PARTIAL_OUTPUT_ON_ERROR covers older versions
            $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR;
            if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
                $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
            }
            $json = json_encode($context, $jsonFlags);
            $line .= ' ' . str_replace(["\r", "\n"], ' ', (string) $json);
        }

        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            error_log($line);
            return;
        }
        $file = $dir . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
        if (@file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX) === false) {
            error_log($line);
        }
    }
}
