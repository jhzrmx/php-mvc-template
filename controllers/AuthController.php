<?php

class AuthController {

    // Login throttling: failed attempts per (IP + username) and per IP
    // (constants are public: PHP 7.0 has no private/protected class constants)
    const LOGIN_MAX_PER_USER = 5;
    const LOGIN_MAX_PER_IP = 20;
    const LOGIN_WINDOW = 900; // 15 minutes
    // Registration throttling: attempts per IP
    const REGISTER_MAX_PER_IP = 10;
    const REGISTER_WINDOW = 3600; // 1 hour

    private function tooManyRequests($retryAfter) {
        $retryAfter = max(1, (int) $retryAfter);
        abort(429, 'Please try again in ' . $retryAfter . ' seconds', ['Retry-After' => $retryAfter]);
    }

    /**
     * The JSON body, with "email" accepted as an alias for "username".
     */
    private function credentials(Request $req) {
        $body = $req->json();
        if (!isset($body['username']) && isset($body['email'])) {
            $body['username'] = $body['email'];
        }
        return $body;
    }

    /**
     * POST /api/auth/login
     * Body: { "username": "...", "password": "..." }
     * Looks up user in DB (RedBean) and verifies password.
     */
    public function login(Request $req, Response $res) {
        $data = $req->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ], [], $this->credentials($req));

        $username = trim($data['username']);
        $password = $data['password'];

        $ip = $req->ip();
        $userKey = 'login:' . $ip . ':' . strtolower($username);
        $ipKey = 'login-ip:' . $ip;

        if (RateLimiter::tooManyAttempts($userKey, self::LOGIN_MAX_PER_USER)) {
            $this->tooManyRequests(RateLimiter::retryAfter($userKey));
        }
        if (RateLimiter::tooManyAttempts($ipKey, self::LOGIN_MAX_PER_IP)) {
            $this->tooManyRequests(RateLimiter::retryAfter($ipKey));
        }

        $userBean = User::findByUsername($username);

        // Same work and same message whether the user exists or not (no username enumeration)
        $valid = $userBean
            ? User::verifyPassword($userBean, $password)
            : User::dummyVerify($password);

        if (!$userBean || !$valid) {
            RateLimiter::hit($userKey, self::LOGIN_MAX_PER_USER, self::LOGIN_WINDOW);
            RateLimiter::hit($ipKey, self::LOGIN_MAX_PER_IP, self::LOGIN_WINDOW);
            $res->error(401, 'Invalid credentials', 'Invalid username or password');
        }

        RateLimiter::clear($userKey);
        $this->respondWithToken($res, $userBean);
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
        // Count every attempt, valid or not
        $key = 'register:' . $req->ip();
        if (!RateLimiter::hit($key, self::REGISTER_MAX_PER_IP, self::REGISTER_WINDOW)) {
            $this->tooManyRequests(RateLimiter::retryAfter($key));
        }

        $data = $req->validate([
            'username' => 'required|string|max:255',
            'password' => 'required|string|min:8|max_bytes:72',
            'email' => 'nullable|email|max:255',
        ], [], $this->credentials($req));

        try {
            // role is always 'user' here
            $bean = User::create(trim($data['username']), $data['password'], $data['email'] ?? null);
            $this->respondWithToken($res, $bean);
        } catch (InvalidArgumentException $e) {
            $res->error(400, 'Sign up failed', $e->getMessage());
        }
    }

    /**
     * GET /api/auth/me (protected)
     * Returns the current user record from the database.
     */
    public function me(Request $req, Response $res) {
        $user = Auth::user();
        if (!$user) {
            $res->error(401, 'Not authenticated');
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
