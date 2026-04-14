<?php

/**
 * Simple auth helper. Holds JWT instance and current user payload.
 * Set by auth middleware after successful JWT verification.
 * Also handles token cookie storage and retrieval.
 */
class Auth {
    private static $jwt = null;
    private static $payload = null;
    private static string $tokenCookie = 'token';

    public static function init(JWT $jwt) {
        self::$jwt = $jwt;
    }

    public static function setTokenCookieName(string $tokenCookie) {
        self::$tokenCookie = $tokenCookie;
    }

    private static function isSecureRequest() {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        return false;
    }

    /**
     * Store a JWT token in an HTTP-only cookie.
     */
    public static function setTokenCookie(string $token, int $expiresIn) {
        $secure = self::isSecureRequest();
        setcookie(self::$tokenCookie, $token, [
            'expires' => time() + $expiresIn,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function getToken() {
        $authHeader = $req->params['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $m)) {
            $token = trim($m[1]);
        } elseif (!empty($_SERVER['HTTP_X_AUTH_TOKEN'])) {
            $token = trim($_SERVER['HTTP_X_AUTH_TOKEN']);
        } elseif (!empty($_COOKIE['token'])) {
            $token = trim($_COOKIE['token']);
        } else {
            return null;
        }
        return $token;
    }

    public static function clearTokenCookie() {
        $secure = self::isSecureRequest();
        setcookie(self::$tokenCookie, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function getJwt() {
        return self::$jwt;
    }

    public static function isVerified() {
        $token = self::getToken();
        if (!$token) {
            return false;
        }
        $result = self::$jwt->verify($token);
        return !empty($result['valid']);
    }

    public static function setPayload(array $payload) {
        self::$payload = $payload;
    }

    public static function payload() {
        return self::$payload;
    }

    public static function user() {
        return self::$payload;
    }

    public static function id() {
        return self::$payload['sub'] ?? self::$payload['id'] ?? null;
    }

    public static function check() {
        return self::$payload !== null;
    }

    public static function clear() {
        self::clearTokenCookie();
        self::$payload = null;
    }
}
