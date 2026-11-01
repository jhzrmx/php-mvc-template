<?php

/**
 * User model using RedBean (table: user).
 * Fields: username, password_hash, email, role, token_version, created_at, updated_at
 *
 * Validation problems throw InvalidArgumentException; its message is safe to show to clients.
 * Any other exception (e.g. a PDOException) should be logged and NOT shown to clients.
 */
class User extends Model {
    protected static $type = 'user';

    const ROLE_USER = 'user';
    const ROLE_ADMIN = 'admin';
    const PASSWORD_MIN = 8;
    const PASSWORD_MAX = 72; // bcrypt silently ignores anything past 72 bytes

    /** Fields never exposed in API output. */
    protected static $hidden = ['password_hash', 'token_version'];

    /**
     * Find user by username. Returns bean or null.
     */
    public static function findByUsername($username) {
        $username = trim((string) $username);
        if ($username === '') return null;
        return R::findOne(self::$type, ' username = ? ', [$username]);
    }

    public static function isAdmin($bean) {
        return $bean && ($bean->role ?? self::ROLE_USER) === self::ROLE_ADMIN;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function assertValidPassword($password) {
        if (!is_string($password) || strlen($password) < self::PASSWORD_MIN) {
            throw new InvalidArgumentException('Password must be at least ' . self::PASSWORD_MIN . ' characters.');
        }
        if (strlen($password) > self::PASSWORD_MAX) {
            throw new InvalidArgumentException('Password must be at most ' . self::PASSWORD_MAX . ' characters.');
        }
    }

    /**
     * Create and store a user. Password is hashed. Returns saved bean.
     * Role is always 'user' unless an admin-only code path passes another one explicitly.
     *
     * @throws InvalidArgumentException
     */
    public static function create($username, $password, $email = null, $role = self::ROLE_USER) {
        $username = trim((string) $username);
        if ($username === '') {
            throw new InvalidArgumentException('Username is required.');
        }
        if (strlen($username) > 255) {
            throw new InvalidArgumentException('Username is too long.');
        }
        self::assertValidPassword($password);
        $email = self::normalizeEmail($email);
        if (!in_array($role, [self::ROLE_USER, self::ROLE_ADMIN], true)) {
            throw new InvalidArgumentException('Invalid role.');
        }
        if (self::findByUsername($username)) {
            throw new InvalidArgumentException('Username already exists.');
        }
        $bean = R::dispense(self::$type);
        $bean->username = $username;
        $bean->password_hash = password_hash($password, PASSWORD_DEFAULT);
        $bean->email = $email;
        $bean->role = $role;
        $bean->token_version = 0;
        $bean->created_at = date('Y-m-d H:i:s');
        $bean->updated_at = $bean->created_at;
        try {
            R::store($bean);
        } catch (Throwable $e) {
            // Lost a race with a simultaneous sign-up: the unique index rejected the insert
            if (self::findByUsername($username)) {
                throw new InvalidArgumentException('Username already exists.');
            }
            throw $e;
        }
        return $bean;
    }

    /**
     * Verify plain password against stored hash. Returns true if valid.
     */
    public static function verifyPassword($bean, $password) {
        if (!$bean || !isset($bean->password_hash) || !is_string($password)) return false;
        return password_verify($password, $bean->password_hash);
    }

    /**
     * Burn the same CPU time as a real verification, so response time doesn't reveal
     * whether a username exists. Always returns false.
     */
    public static function dummyVerify($password) {
        static $hash = null;
        if ($hash === null) {
            $hash = password_hash('dummy-password-for-timing', PASSWORD_DEFAULT);
        }
        password_verify(is_string($password) ? $password : '', $hash);
        return false;
    }

    /**
     * Invalidate every token issued so far for this user.
     */
    public static function revokeTokens($bean) {
        $bean->token_version = (int) $bean->token_version + 1;
        $bean->updated_at = date('Y-m-d H:i:s');
        R::store($bean);
    }

    /**
     * Change a user's role (used by the CLI: php mvc user:promote).
     *
     * @throws InvalidArgumentException
     */
    public static function setRole($bean, $role) {
        if (!in_array($role, [self::ROLE_USER, self::ROLE_ADMIN], true)) {
            throw new InvalidArgumentException('Invalid role.');
        }
        $bean->role = $role;
        $bean->token_version = (int) $bean->token_version + 1; // force re-login with the new role
        $bean->updated_at = date('Y-m-d H:i:s');
        R::store($bean);
        return $bean;
    }

    /**
     * A user as a safe array: hidden fields removed, role always present.
     */
    public static function toArray($bean) {
        $data = parent::toArray($bean);
        if ($data !== null) {
            $data['role'] = $data['role'] ?? self::ROLE_USER;
        }
        return $data;
    }

    /**
     * Update user by id. Allowed keys: username, email, password (will be hashed).
     * `role` can never be changed here. Changing the password revokes existing tokens.
     *
     * @return object|null The bean, or null if the user doesn't exist.
     * @throws InvalidArgumentException
     */
    public static function updateById($id, $data) {
        $bean = self::findBean($id);
        if (!$bean) return null;

        if (isset($data['username'])) {
            $u = trim((string) $data['username']);
            if ($u !== '' && $u !== $bean->username) {
                if (strlen($u) > 255) {
                    throw new InvalidArgumentException('Username is too long.');
                }
                $existing = self::findByUsername($u);
                if ($existing && (int) $existing->id !== (int) $bean->id) {
                    throw new InvalidArgumentException('Username already exists.');
                }
                $bean->username = $u;
            }
        }
        if (array_key_exists('email', $data)) {
            $bean->email = self::normalizeEmail($data['email']);
        }
        if (!empty($data['password'])) {
            self::assertValidPassword($data['password']);
            $bean->password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
            $bean->token_version = (int) $bean->token_version + 1;
        }
        $bean->updated_at = date('Y-m-d H:i:s');
        try {
            R::store($bean);
        } catch (Throwable $e) {
            $existing = self::findByUsername($bean->username);
            if ($existing && (int) $existing->id !== (int) $bean->id) {
                throw new InvalidArgumentException('Username already exists.');
            }
            throw $e;
        }
        return $bean;
    }

    /**
     * Delete user by id. Returns true if deleted.
     */
    public static function deleteById($id) {
        $bean = self::findBean($id);
        if (!$bean) return false;
        R::trash($bean);
        return true;
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function normalizeEmail($email) {
        if ($email === null || $email === '') return null;
        $email = trim((string) $email);
        if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email address is not valid.');
        }
        return $email;
    }
}
