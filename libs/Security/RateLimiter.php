<?php

/**
 * @package RateLimiter
 * @author jhzrmx
 * @version 1.0.0
 * @license MIT
 * @link https://github.com/jhzrmx/php-mvc-template
 * @requires PHP 7.0+
 * @dependencies None (standalone, can be downloaded and used on its own)
 * @description Minimal file-based rate limiter (fixed window). No database table needed,
 * so it keeps working when RedBean is frozen in production.
 *
 * Usage:
 *   if (RateLimiter::tooManyAttempts($key, 5)) { ... 429 ... }
 *   RateLimiter::hit($key, 5, 900);   // record a failed attempt
 *   RateLimiter::clear($key);         // reset after success
 *
 * Note: keys are typically built from $_SERVER['REMOTE_ADDR']. If you run behind a
 * reverse proxy, configure your web server to restore the real client IP
 * (e.g. mod_remoteip / real_ip_module) instead of trusting X-Forwarded-For here.
 */

namespace JHz\Security;

class RateLimiter {
    private static $dir = null;

    public static function setDirectory($dir) {
        self::$dir = rtrim($dir, '/\\');
    }

    private static function dir() {
        if (self::$dir === null) {
            $base = defined('BASE_PATH') ? BASE_PATH : getcwd();
            self::$dir = $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'ratelimit';
        }
        return self::$dir;
    }

    /**
     * Read (and optionally increment) the counter for a key.
     *
     * @return array ['count' => int, 'reset' => int]
     */
    private static function update($key, $decay, $increment) {
        $now = time();
        $fallback = ['count' => 0, 'reset' => $now + $decay];

        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            error_log("RateLimiter: cannot create $dir");
            return $fallback; // fail open, but leave a trace in the log
        }

        $path = $dir . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
        if (!$increment && !is_file($path)) {
            return $fallback; // read-only checks never create files
        }
        $fh = @fopen($path, 'c+');
        if (!$fh) {
            error_log("RateLimiter: cannot open $path");
            return $fallback;
        }

        flock($fh, LOCK_EX);
        $data = json_decode((string) stream_get_contents($fh), true);
        if (!is_array($data) || ($data['reset'] ?? 0) <= $now) {
            $data = $fallback;
        }
        if ($increment) {
            $data['count'] = (int) $data['count'] + 1;
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($data));
            fflush($fh);
        }
        flock($fh, LOCK_UN);
        fclose($fh);

        // Occasionally purge stale files
        if (random_int(1, 100) === 1) {
            self::purge();
        }

        return $data;
    }

    private static function purge() {
        foreach (glob(self::dir() . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
            if (@filemtime($file) < time() - 86400) {
                @unlink($file);
            }
        }
    }

    /**
     * Record an attempt. Returns true while still within the limit.
     */
    public static function hit($key, $maxAttempts, $decaySeconds) {
        $data = self::update($key, $decaySeconds, true);
        return $data['count'] <= $maxAttempts;
    }

    /**
     * Check the limit without recording an attempt.
     */
    public static function tooManyAttempts($key, $maxAttempts) {
        $data = self::update($key, 0, false);
        return $data['count'] >= $maxAttempts && $data['reset'] > time();
    }

    public static function retryAfter($key) {
        $data = self::update($key, 0, false);
        return max(0, $data['reset'] - time());
    }

    public static function clear($key) {
        $path = self::dir() . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
