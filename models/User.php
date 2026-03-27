<?php

/**
 * User model using RedBean (table: user).
 * Fields: username, password_hash, email, created_at
 */
class User extends Model {
    protected static $type = 'user';

    /**
     * Find user by username. Returns bean or null.
     */
    public static function findByUsername($username) {
        $username = trim((string) $username);
        if ($username === '') return null;
        return R::findOne(self::$type, ' username = ? ', [$username]);
    }

    /**
     * Create and store a user. Password is hashed. Returns saved bean.
     */
    public static function create($username, $password, $email = null) {
        $username = trim((string) $username);
        if ($username === '') {
            throw new Exception('Username is required.');
        }
        if (self::findByUsername($username)) {
            throw new Exception('Username already exists.');
        }
        $bean = R::dispense(self::$type);
        $bean->username = $username;
        $bean->password_hash = password_hash($password, PASSWORD_DEFAULT);
        $bean->email = $email !== null ? trim((string) $email) : null;
        $bean->created_at = date('Y-m-d H:i:s');
        R::store($bean);
        return $bean;
    }

    /**
     * Verify plain password against stored hash. Returns true if valid.
     */
    public static function verifyPassword($bean, $password) {
        if (!$bean || !isset($bean->password_hash)) return false;
        return password_verify($password, $bean->password_hash);
    }

    /**
     * Exclude password_hash from array export.
     */
    protected static function beanToArray($bean) {
        return self::toArray($bean);
    }

    /** Public export for a single user bean (no password_hash). */
    public static function toArray($bean) {
        if (!$bean) return null;
        $a = $bean->export();
        unset($a['password_hash']);
        return $a;
    }

    /**
     * Update user by id. Allowed keys: username, email, password (will be hashed).
     */
    public static function updateById($id, $data) {
        $id = (int) $id;
        if ($id <= 0) return null;
        $bean = R::load(self::$type, $id);
        if (!$bean->getID()) return null;
        if (isset($data['username'])) {
            $u = trim((string) $data['username']);
            if ($u !== '') $bean->username = $u;
        }
        if (array_key_exists('email', $data)) $bean->email = $data['email'] ? trim((string) $data['email']) : null;
        if (!empty($data['password'])) $bean->password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
        R::store($bean);
        return $bean;
    }

    /**
     * Delete user by id. Returns true if deleted.
     */
    public static function deleteById($id) {
        $id = (int) $id;
        if ($id <= 0) return false;
        $bean = R::load(self::$type, $id);
        if (!$bean->getID()) return false;
        R::trash($bean);
        return true;
    }
}
