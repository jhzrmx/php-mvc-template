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
            abort(403, 'You do not have permission to access this user');
        }
        return [$actor, $isAdmin];
    }

    /**
     * GET /api/users?page=1&per_page=20 (admin only, enforced by route middleware)
     */
    public function index(Request $req, Response $res) {
        $result = User::paginate($req->input('page', 1), $req->input('per_page', 20));
        $res->status(200)->json($result);
    }

    /** GET /api/users/:id (self or admin) */
    public function show(Request $req, Response $res, string $id) {
        $this->authorizeAccess($res, $id);
        $user = User::find($id);
        if (!$user) {
            abort(404, 'User does not exist');
        }
        $res->status(200)->json($user);
    }

    /** POST /api/users (admin only, enforced by route middleware) */
    public function create(Request $req, Response $res) {
        $data = $req->validate([
            'username' => 'required|string|max:255',
            'password' => 'required|string|min:8|max_bytes:72',
            'email' => 'nullable|email|max:255',
            'role' => 'nullable|in:user,admin',
        ], [], $req->json());

        try {
            $bean = User::create(
                trim($data['username']),
                $data['password'],
                $data['email'] ?? null,
                $data['role'] ?? User::ROLE_USER
            );
            $res->status(201)->json(User::toArray($bean));
        } catch (InvalidArgumentException $e) {
            $res->error(400, 'Could not create user', $e->getMessage());
        }
    }

    /** PUT /api/users/:id (self or admin) */
    public function update(Request $req, Response $res, string $id) {
        list($actor, $isAdmin) = $this->authorizeAccess($res, $id);
        $body = $req->json();
        $isSelf = (int) $actor->id === (int) $id;

        $req->validate([
            'username' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'password' => 'nullable|string|min:8|max_bytes:72',
            'current_password' => 'nullable|string',
        ], [], $body);

        // Only these fields can be changed. `role` can never be set through this endpoint.
        $data = array_intersect_key($body, array_flip(['username', 'email', 'password']));

        if (!empty($data['password']) && $isSelf) {
            // Changing your own password requires proving you know the current one
            $key = 'pwchange:' . $actor->id;
            if (RateLimiter::tooManyAttempts($key, 5)) {
                $retryAfter = max(1, RateLimiter::retryAfter($key));
                abort(429, 'Please try again in ' . $retryAfter . ' seconds', ['Retry-After' => $retryAfter]);
            }
            $current = $body['current_password'] ?? '';
            if (!User::verifyPassword($actor, is_string($current) ? $current : '')) {
                RateLimiter::hit($key, 5, 900);
                $res->error(403, 'Forbidden', 'Current password is incorrect');
            }
            RateLimiter::clear($key);
        }

        try {
            $bean = User::updateById($id, $data);
            if (!$bean) {
                abort(404, 'User does not exist');
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
            $res->error(400, 'Could not update user', $e->getMessage());
        }
    }

    /** PATCH /api/users/:id (self or admin) */
    public function patch(Request $req, Response $res, string $id) {
        $this->update($req, $res, $id);
    }

    /** DELETE /api/users/:id (self or admin) */
    public function destroy(Request $req, Response $res, string $id) {
        list($actor) = $this->authorizeAccess($res, $id);
        if (!User::deleteById($id)) {
            abort(404, 'User does not exist');
        }
        if ((int) $actor->id === (int) $id) {
            Auth::clearTokenCookie();
        }
        $res->status(200)->json(['message' => 'User deleted']);
    }
}
