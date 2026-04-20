<?php

class UserController {

    public function index(Request $req, Response $res) {
        $users = User::all();
        $res->status(200)->json(['data' => $users]);
    }

    public function show(Request $req, Response $res, string $id) {
        $user = User::find($id);
        if (!$user) {
            $res->status(404)->json(['error' => 'User not found', 'message' => 'User with ID ' . $id . ' does not exist']);
        }
        $res->status(200)->json($user);
    }

    public function create(Request $req, Response $res) {
        $data = is_array($req->body) ? $req->body : [];
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $email = isset($data['email']) ? trim($data['email']) : null;

        if ($username === '' || $password === '') {
            $res->status(400)->json(['error' => 'Missing required fields', 'message' => 'Username and password are required']);
        }
        if (strlen($password) < 6) {
            $res->status(400)->json(['error' => 'Password too short', 'message' => 'Password must be at least 6 characters']);
        }

        try {
            $bean = User::create($username, $password, $email);
            $user = User::toArray($bean);
            $res->status(201)->json($user);
        } catch (Exception $e) {
            $res->status(400)->json(['error' => 'Sign up failed', 'message' => $e->getMessage()]);
        }
    }

    public function update(Request $req, Response $res, string $id) {
        $data = is_array($req->body) ? $req->body : [];
        $bean = User::updateById($id, $data);
        if (!$bean) {
            $res->status(404)->json(['error' => 'User not found', 'message' => 'User with ID ' . $id . ' does not exist']);
        }
        $res->status(200)->json(User::toArray($bean));
    }

    public function patch(Request $req, Response $res, string $id) {
        $data = is_array($req->body) ? $req->body : [];
        $bean = User::updateById($id, $data);
        if (!$bean) {
            $res->status(404)->json(['error' => 'User not found', 'message' => 'User with ID ' . $id . ' does not exist']);
        }
        $res->status(200)->json(User::toArray($bean));
    }

    public function destroy(Request $req, Response $res, string $id) {
        if (!User::deleteById($id)) {
            $res->status(404)->json(['error' => 'User not found', 'message' => 'User with ID ' . $id . ' does not exist']);
        }
        $res->status(200)->json(['message' => 'User deleted']);
    }
}
