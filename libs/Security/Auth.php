<?php

/**
 * @package Auth
 * @author jhzrmx
 * @version 1.0.0
 * @license MIT
 * @link https://github.com/jhzrmx/php-mvc-template
 * @requires PHP 7.0+
 * @dependencies JWT (libs/Security/JWT.php), User (models/User.php), env() helper (libs/helpers.php)
 * @description Simple auth helper. Holds JWT instance and current user payload.
 * Set by auth middleware after successful JWT verification.
 * Also handles token cookie storage and retrieval.
 */

namespace JHz\Security;

use User;

class Auth {
    private static $jwt = null;
    private static $payload = null;
    private static $user = null;
    private static $tokenCookie = 'token';

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
     * Send the auth cookie (HttpOnly, SameSite=Lax, Secure on HTTPS).
     *
     * setcookie() only accepts an options array (and therefore SameSite) from PHP 7.3.
     * On PHP 7.0 - 7.2 the same cookie is written as a raw Set-Cookie header instead.
     *
     * @param string $value   Cookie value ('' when deleting).
     * @param int    $expires Unix timestamp.
     * @return void
     */
    private static function sendTokenCookie($value, $expires) {
        $secure = self::isSecureRequest();

        if (PHP_VERSION_ID >= 70300) {
            setcookie(self::$tokenCookie, $value, [
                'expires' => $expires,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            return;
        }

        header(
            'Set-Cookie: ' . self::$tokenCookie . '=' . rawurlencode($value)
            . '; expires=' . gmdate('D, d M Y H:i:s', $expires) . ' GMT'
            . '; Max-Age=' . max(0, $expires - time())
            . '; path=/'
            . ($secure ? '; Secure' : '')
            . '; HttpOnly; SameSite=Lax',
            false // add to, don't replace, other Set-Cookie headers
        );
    }

    /**
     * Store a JWT token in an HTTP-only cookie.
     */
    public static function setTokenCookie(string $token, int $expiresIn) {
        self::sendTokenCookie($token, time() + $expiresIn);
    }

    /**
     * Find the JWT on the request. Checked in this order:
     *   1. Authorization: Bearer <token>
     *   2. X-Auth-Token: <token>
     *   3. the auth cookie (name set with setTokenCookieName(), default "token")
     *
     * @return string|null
     */
    public static function getToken() {
        // REDIRECT_HTTP_AUTHORIZATION is what Apache provides after an internal rewrite
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (is_string($authHeader) && preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $m)) {
            return trim($m[1]);
        }
        if (!empty($_SERVER['HTTP_X_AUTH_TOKEN']) && is_string($_SERVER['HTTP_X_AUTH_TOKEN'])) {
            return trim($_SERVER['HTTP_X_AUTH_TOKEN']);
        }
        // is_string guards against ?token[]= style array cookies
        if (!empty($_COOKIE[self::$tokenCookie]) && is_string($_COOKIE[self::$tokenCookie])) {
            return trim($_COOKIE[self::$tokenCookie]);
        }
        return null;
    }

    public static function clearTokenCookie() {
        self::sendTokenCookie('', time() - 3600);
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

    /**
     * Fully authenticate the current request:
     *  1. a token is present and its signature/expiry are valid
     *  2. the user in `sub` (an integer user ID) still exists
     *  3. the token's version matches the user's current token_version
     *     (bumped on logout / password change, which revokes older tokens)
     *
     * On success the payload and user are stored for Auth::user() / Auth::id().
     *
     * @return array ['valid' => bool, 'reason' => string]
     */
    public static function authenticate() {
        self::$payload = null;
        self::$user = null;

        if (!self::$jwt) {
            return ['valid' => false, 'reason' => 'Auth not configured'];
        }
        $token = self::getToken();
        if (!$token) {
            return ['valid' => false, 'reason' => 'Token missing'];
        }
        $result = self::$jwt->verify($token);
        if (empty($result['valid'])) {
            return ['valid' => false, 'reason' => $result['reason'] ?? 'Invalid token'];
        }

        $payload = $result['payload'];
        $sub = $payload['sub'] ?? null;
        if (!is_int($sub) || $sub <= 0) {
            return ['valid' => false, 'reason' => 'Invalid token subject'];
        }

        $user = User::findBean($sub);
        if (!$user) {
            return ['valid' => false, 'reason' => 'User no longer exists'];
        }
        if (!isset($payload['tv']) || (int) $payload['tv'] !== (int) $user->token_version) {
            return ['valid' => false, 'reason' => 'Token revoked'];
        }

        self::$payload = $payload;
        self::$user = $user;
        return ['valid' => true, 'reason' => 'Token validated'];
    }

    /**
     * API clients that can't use cookies send "X-Return-Token: true" to receive the token
     * in the response body instead (no cookie is set for them).
     * Browsers should keep using the HttpOnly cookie: a token in the body is readable by JavaScript.
     */
    public static function wantsToken() {
        return strtolower(trim((string) ($_SERVER['HTTP_X_RETURN_TOKEN'] ?? ''))) === 'true';
    }

    /**
     * Issue a token for a user bean and mark them as logged in.
     *
     * @param object $userBean
     * @param bool   $setCookie Set the HttpOnly auth cookie (default true).
     * @return array ['token' => string, 'expires_in' => int]
     */
    public static function login($userBean, $setCookie = true) {
        $expiresIn = (int) env('JWT_EXPIRES_IN', 3600);
        if ($expiresIn <= 0) {
            $expiresIn = 3600;
        }
        $payload = [
            'sub' => (int) $userBean->id,
            'tv'  => (int) $userBean->token_version,
            'exp' => time() + $expiresIn,
        ];
        $token = self::$jwt->sign($payload);
        if ($setCookie) {
            self::setTokenCookie($token, $expiresIn);
        }
        self::$payload = $payload;
        self::$user = $userBean;
        return ['token' => $token, 'expires_in' => $expiresIn];
    }

    /**
     * Log out everywhere: invalidates every token issued to this user, then clears the cookie.
     */
    public static function logout() {
        if (self::$user === null) {
            self::authenticate();
        }
        if (self::$user !== null) {
            User::revokeTokens(self::$user);
        }
        self::clear();
    }

    public static function setPayload(array $payload) {
        self::$payload = $payload;
    }

    public static function payload() {
        return self::$payload;
    }

    /**
     * The authenticated user's bean (loaded fresh from the DB during authenticate()).
     */
    public static function currentUser() {
        return self::$user;
    }

    /**
     * The authenticated user as a safe array (no password hash), or null.
     */
    public static function user() {
        return self::$user ? User::toArray(self::$user) : null;
    }

    public static function id() {
        return self::$user ? (int) self::$user->id : null;
    }

    public static function check() {
        return self::$user !== null;
    }

    public static function clear() {
        self::clearTokenCookie();
        self::$payload = null;
        self::$user = null;
    }
}
