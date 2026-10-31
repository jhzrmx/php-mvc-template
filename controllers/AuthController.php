<?php

class AuthController {

    // Login throttling: failed attempts per (IP + username) and per IP
    private const LOGIN_MAX_PER_USER = 5;
    private const LOGIN_MAX_PER_IP = 20;
    private const LOGIN_WINDOW = 900; // 15 minutes
    // Registration throttling: attempts per IP
    private const REGISTER_MAX_PER_IP = 10;
    private const REGISTER_WINDOW = 3600; // 1 hour

    private function clientIp() {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    private function tooManyRequests(Response $res, $retryAfter) {
        $retryAfter = max(1, (int) $retryAfter);
        header('Retry-After: ' . $retryAfter);
        $res->status(429)->json([
            'error' => 'Too many attempts',
            'message' => 'Please try again in ' . $retryAfter . ' seconds'
        ]);
    }

    private function serverError(Response $res, Throwable $e) {
        error_log(get_class($e) . ': ' . $e->getMessage());
        $res->status(500)->json(['error' => 'Internal Server Error', 'message' => 'An unexpected error occurred']);
    }

    /**
     * POST /api/auth/login
     * Body: { "username": "...", "password": "..." }
     * Looks up user in DB (RedBean) and verifies password.
     */
    public function login(Request $req, Response $res) {
        $body = is_array($req->body) ? $req->body : [];
        $username = trim((string) ($body['username'] ?? $body['email'] ?? ''));
        $password = $body['password'] ?? '';

        if ($username === '' || !is_string($password) || $password === '') {
            $res->status(400)->json(['error' => 'Missing required fields', 'message' => 'Username and password are required']);
        }

        $ip = $this->clientIp();
        $userKey = 'login:' . $ip . ':' . strtolower($username);
        $ipKey = 'login-ip:' . $ip;

        if (RateLimiter::tooManyAttempts($userKey, self::LOGIN_MAX_PER_USER)) {
            $this->tooManyRequests($res, RateLimiter::retryAfter($userKey));
        }
        if (RateLimiter::tooManyAttempts($ipKey, self::LOGIN_MAX_PER_IP)) {
            $this->tooManyRequests($res, RateLimiter::retryAfter($ipKey));
        }

        try {
            $userBean = User::findByUsername($username);

            // Same work and same message whether the user exists or not (no username enumeration)
            $valid = $userBean
                ? User::verifyPassword($userBean, $password)
                : User::dummyVerify($password);

            if (!$userBean || !$valid) {
                RateLimiter::hit($userKey, self::LOGIN_MAX_PER_USER, self::LOGIN_WINDOW);
                RateLimiter::hit($ipKey, self::LOGIN_MAX_PER_IP, self::LOGIN_WINDOW);
                $res->status(401)->json(['error' => 'Invalid credentials', 'message' => 'Invalid username or password']);
            }

            RateLimiter::clear($userKey);
            $this->respondWithToken($res, $userBean);
        } catch (Throwable $e) {
            $this->serverError($res, $e);
        }
    }

    private function respondWithToken(Response $res, $userBean, $status = 200) {
        $wantsToken = Auth::wantsToken();
        $session = Auth::login($userBean, !$wantsToken);
        $body = [
            'expires_in' => $session['expires_in'],
            'user' => User::toArray($userBean),
        ];
        if ($wantsToken) {
            $body['token'] = $session['token'];
        }
        $res->status($status)->json($body);
    }

    /**
     * POST /api/auth/register
     * Body: { "username": "...", "password": "...", "email": "..." (optional) }
     */
    public function register(Request $req, Response $res) {
        $body = is_array($req->body) ? $req->body : [];
        $username = trim((string) ($body['username'] ?? $body['email'] ?? ''));
        $password = $body['password'] ?? '';
        $email = isset($body['email']) ? trim((string) $body['email']) : null;

        if (!RateLimiter::hit('register:' . $this->clientIp(), self::REGISTER_MAX_PER_IP, self::REGISTER_WINDOW)) {
            $this->tooManyRequests($res, RateLimiter::retryAfter('register:' . $this->clientIp()));
        }

        if ($username === '' || !is_string($password) || $password === '') {
            $res->status(400)->json(['error' => 'Missing required fields', 'message' => 'Username and password are required']);
        }

        try {
            $bean = User::create($username, $password, $email); // role is always 'user' here
            $this->respondWithToken($res, $bean);
        } catch (InvalidArgumentException $e) {
            $res->status(400)->json(['error' => 'Sign up failed', 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->serverError($res, $e);
        }
    }

    /**
     * GET /api/auth/me (protected)
     * Returns the current user record from the database.
     */
    public function me(Request $req, Response $res) {
        $user = Auth::user();
        if (!$user) {
            $res->status(401)->json(['error' => 'Not authenticated']);
        }
        $res->status(200)->json(['user' => $user]);
    }

    /**
     * POST /api/auth/logout
     * Revokes every token issued to this user and clears the auth cookie.
     */
    public function logout(Request $req, Response $res) {
        Auth::logout();
        $res->status(200)->json(['message' => 'Logged out']);
    }
}
