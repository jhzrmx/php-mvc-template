<?php

/**
 * Simple auth helper. Holds JWT instance and current user payload.
 * Set by auth middleware after successful JWT verification.
 */
class Auth {
    private static $jwt = null;
    private static $payload = null;

    public static function init(JWT $jwt) {
        self::$jwt = $jwt;
    }

    public static function getJwt() {
        return self::$jwt;
    }

    public static function setPayload(array $payload) {
        self::$payload = $payload;
    }

    public static function payload() {
        return self::$payload;
    }

    public static function user() {
        return self::$payload;
    }

    public static function id() {
        return self::$payload['sub'] ?? self::$payload['id'] ?? null;
    }

    public static function check() {
        return self::$payload !== null;
    }
}
