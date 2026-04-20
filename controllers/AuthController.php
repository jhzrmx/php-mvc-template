<?php

class AuthController {

    /**
     * POST /api/auth/login
     * Body: { "username": "...", "password": "..." }
     * Looks up user in DB (RedBean) and verifies password.
     */
    public function login(Request $req, Response $res) {
        $body = is_array($req->body) ? $req->body : [];
        $username = trim($body['username'] ?? $body['email'] ?? '');
        $password = $body['password'] ?? '';

        if ($username === '' || $password === '') {
            $res->status(400)->json(['error' => 'Missing required fields', 'message' => 'Username and password are required']);
        }

        try {
            $userBean = User::findByUsername($username);
            if (!$userBean) {
                $res->status(401)->json(['error' => 'User not found', 'message' => 'User with username ' . $username . ' does not exist']);
            }
            if (!User::verifyPassword($userBean, $password)) {
                $res->status(401)->json(['error' => 'Invalid credentials', 'message' => 'Invalid username or password']);
            }

            $userData = User::toArray($userBean);
            $this->respondWithToken($res, $username, $userData);
        } catch (Exception $e) {
            $res->status(400)->json(['error' => 'Login failed', 'message' => $e->getMessage()]);
        }
    }

    private function respondWithToken(Response $res, $username, array $userData) {
        $jwt = Auth::getJwt();
        $expiresIn = (int) (getenv('JWT_EXPIRES_IN') ?: $_ENV['JWT_EXPIRES_IN'] ?? 3600);
        $payload = [
            'sub' => $username,
            'username' => $username,
            'exp' => time() + $expiresIn,
        ];
        $token = $jwt->sign($payload);
        Auth::setTokenCookie($token, $expiresIn);
        $res->status(200)->json([
            'expires_in' => $expiresIn,
            'user' => $userData,
        ]);
    }

    /**
     * POST /api/auth/register
     * Body: { "username": "...", "password": "...", "email": "..." (optional) }
     */
    public function register(Request $req, Response $res) {
        $body = is_array($req->body) ? $req->body : [];
        $username = trim($body['username'] ?? $body['email'] ?? '');
        $password = $body['password'] ?? '';
        $email = isset($body['email']) ? trim($body['email']) : null;

        if ($username === '' || $password === '') {
            $res->status(400)->json(['error' => 'Missing required fields', 'message' => 'Username and password are required']);
        }
        if (strlen($password) < 6) {
            $res->status(400)->json(['error' => 'Password too short', 'message' => 'Password must be at least 6 characters']);
        }

        try {
            $bean = User::create($username, $password, $email);
            $userData = User::toArray($bean);
            $this->respondWithToken($res, $username, $userData);
        } catch (Exception $e) {
            $res->status(400)->json(['error' => 'Sign up failed', 'message' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/auth/me (protected)
     * Returns current user from JWT; if stored in DB, returns full user record.
     */
    public function me(Request $req, Response $res) {
        $payload = Auth::user();
        if (!$payload) {
            $res->status(401)->json(['error' => 'Not authenticated']);
        }
        $username = $payload['username'] ?? $payload['sub'] ?? null;
        $userBean = $username ? User::findByUsername($username) : null;
        $user = $userBean ? User::toArray($userBean) : $payload;
        $res->status(200)->json(['user' => $user]);
    }

    /**
     * POST /api/auth/logout
     * Clears the auth cookie.
     */
    public function logout(Request $req, Response $res) {
        Auth::clearTokenCookie();
        $res->status(200)->json(['message' => 'Logged out']);
    }
}
