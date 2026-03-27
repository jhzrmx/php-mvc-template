<?php

/**
 * Base helper for RedBean models. Beans are stored/retrieved via R::.
 */
abstract class Model {
    /** @var string RedBean bean type (table name) */
    protected static $type = '';

    /**
     * Get all beans as arrays (safe for JSON, no RedBean instance).
     */
    public static function all() {
        $beans = R::findAll(static::$type);
        return array_map([static::class, 'beanToArray'], $beans);
    }

    /**
     * Find one by ID. Returns array or null.
     */
    public static function find($id) {
        $id = (int) $id;
        if ($id <= 0) return null;
        $bean = R::load(static::$type, $id);
        return $bean->getID() ? static::beanToArray($bean) : null;
    }

    /**
     * Override in subclass to hide sensitive fields (e.g. password_hash).
     */
    protected static function beanToArray($bean) {
        return $bean->export();
    }
}
