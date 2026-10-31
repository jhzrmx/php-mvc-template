<?php

/**
 * Simple auth helper. Holds JWT instance and current user payload.
 * Set by auth middleware after successful JWT verification.
 * Also handles token cookie storage and retrieval.
 */
class Auth {
    private static $jwt = null;
    private static $payload = null;
    private static $user = null;
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
     * Issue a token for a user bean, set the auth cookie and mark them as logged in.
     *
     * @return int Token lifetime in seconds
     */
    public static function login($userBean) {
        $expiresIn = (int) ($_ENV['JWT_EXPIRES_IN'] ?? getenv('JWT_EXPIRES_IN') ?: 3600);
        if ($expiresIn <= 0) {
            $expiresIn = 3600;
        }
        $payload = [
            'sub' => (int) $userBean->id,
            'tv'  => (int) $userBean->token_version,
            'exp' => time() + $expiresIn,
        ];
        self::setTokenCookie(self::$jwt->sign($payload), $expiresIn);
        self::$payload = $payload;
        self::$user = $userBean;
        return $expiresIn;
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
