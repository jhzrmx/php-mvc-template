<?php

class UserController {

    /**
     * Only the account owner or an admin may touch a user record.
     * Non-admins get the same 403 for any other ID, whether or not it exists.
     *
     * @return array [actorBean, isAdmin]
     */
    private function authorizeAccess(Response $res, $id) {
        $actor = Auth::currentUser();
        $isAdmin = User::isAdmin($actor);
        if (!$actor || (!$isAdmin && (int) $actor->id !== (int) $id)) {
            $res->status(403)->json(['error' => 'Forbidden', 'message' => 'You do not have permission to access this user']);
        }
        return [$actor, $isAdmin];
    }

    private function notFound(Response $res, $id) {
        $res->status(404)->json(['error' => 'User not found', 'message' => 'User does not exist']);
    }

    private function serverError(Response $res, Throwable $e) {
        error_log(get_class($e) . ': ' . $e->getMessage());
        $res->status(500)->json(['error' => 'Internal Server Error', 'message' => 'An unexpected error occurred']);
    }

    /** GET /api/users (admin only, enforced by route middleware) */
    public function index(Request $req, Response $res) {
        $users = User::all();
        $res->status(200)->json(['data' => $users]);
    }

    /** GET /api/users/:id (self or admin) */
    public function show(Request $req, Response $res, string $id) {
        $this->authorizeAccess($res, $id);
        $user = User::find($id);
        if (!$user) {
            $this->notFound($res, $id);
        }
        $res->status(200)->json($user);
    }

    /** POST /api/users (admin only, enforced by route middleware) */
    public function create(Request $req, Response $res) {
        $data = is_array($req->body) ? $req->body : [];
        $username = trim((string) ($data['username'] ?? ''));
        $password = $data['password'] ?? '';
        $email = isset($data['email']) ? trim((string) $data['email']) : null;
        $role = $data['role'] ?? User::ROLE_USER;

        if ($username === '' || !is_string($password) || $password === '') {
            $res->status(400)->json(['error' => 'Missing required fields', 'message' => 'Username and password are required']);
        }

        try {
            $bean = User::create($username, $password, $email, $role);
            $res->status(201)->json(User::toArray($bean));
        } catch (InvalidArgumentException $e) {
            $res->status(400)->json(['error' => 'Could not create user', 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->serverError($res, $e);
        }
    }

    /** PUT /api/users/:id (self or admin) */
    public function update(Request $req, Response $res, string $id) {
        [$actor, $isAdmin] = $this->authorizeAccess($res, $id);
        $body = is_array($req->body) ? $req->body : [];
        $isSelf = (int) $actor->id === (int) $id;

        // Only these fields can be changed. `role` can never be set through this endpoint.
        $data = array_intersect_key($body, array_flip(['username', 'email', 'password']));

        if (!empty($data['password']) && $isSelf) {
            // Changing your own password requires proving you know the current one
            $key = 'pwchange:' . $actor->id;
            if (RateLimiter::tooManyAttempts($key, 5)) {
                header('Retry-After: ' . max(1, RateLimiter::retryAfter($key)));
                $res->status(429)->json(['error' => 'Too many attempts', 'message' => 'Please try again later']);
            }
            $current = $body['current_password'] ?? '';
            if (!User::verifyPassword($actor, is_string($current) ? $current : '')) {
                RateLimiter::hit($key, 5, 900);
                $res->status(403)->json(['error' => 'Forbidden', 'message' => 'Current password is incorrect']);
            }
            RateLimiter::clear($key);
        }

        try {
            $bean = User::updateById($id, $data);
            if (!$bean) {
                $this->notFound($res, $id);
            }
            // Changing a password revokes old tokens; keep the person who did it signed in
            $out = User::toArray($bean);
            if (!empty($data['password']) && $isSelf) {
                $wantsToken = Auth::wantsToken();
                $session = Auth::login($bean, !$wantsToken);
                if ($wantsToken) {
                    $out['token'] = $session['token']; // the old token was just revoked
                }
            }
            $res->status(200)->json($out);
        } catch (InvalidArgumentException $e) {
            $res->status(400)->json(['error' => 'Could not update user', 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->serverError($res, $e);
        }
    }

    /** PATCH /api/users/:id (self or admin) */
    public function patch(Request $req, Response $res, string $id) {
        $this->update($req, $res, $id);
    }

    /** DELETE /api/users/:id (self or admin) */
    public function destroy(Request $req, Response $res, string $id) {
        [$actor] = $this->authorizeAccess($res, $id);
        if (!User::deleteById($id)) {
            $this->notFound($res, $id);
        }
        if ((int) $actor->id === (int) $id) {
            Auth::clearTokenCookie();
        }
        $res->status(200)->json(['message' => 'User deleted']);
    }
}
