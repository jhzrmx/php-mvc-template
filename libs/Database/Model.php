<?php

/**
 * @package Model
 * @author jhzrmx
 * @version 1.0.0
 * @license MIT
 * @link https://github.com/jhzrmx/php-mvc-template
 * @requires PHP 5.6+
 * @dependencies RedBeanPHP (libs/RedbeanPHP/rb.php)
 * @description Base helper for RedBean models. Beans are stored/retrieved via R::.
 *
 * Return types are consistent:
 *  - all(), find(), where(), paginate() return plain arrays (safe for JSON, with $hidden removed)
 *  - findBean() returns a RedBean bean (or null) for code that needs to modify and store it
 *
 * Example:
 *   class Post extends Model {
 *       protected static $type = 'post';        // lower-case letters/digits, no underscores
 *       protected static $hidden = ['secret'];  // never exported
 *   }
 *   Post::paginate(2, 20, 'author_id = ?', [5], 'created_at DESC');
 */

namespace JHz\Database;

use InvalidArgumentException;
use R;

abstract class Model {
    /** @var string RedBean bean type (table name) */
    protected static $type = '';

    /** @var array Fields removed from toArray() / JSON output. */
    protected static $hidden = [];

    /**
     * Get all beans as a list of arrays (safe for JSON, no RedBean instance).
     */
    public static function all() {
        $beans = R::findAll(static::$type);
        return array_values(array_map([static::class, 'beanToArray'], $beans));
    }

    /**
     * Find one by ID. Returns array or null.
     */
    public static function find($id) {
        $bean = static::findBean($id);
        return $bean ? static::beanToArray($bean) : null;
    }

    /**
     * Find one by ID as a RedBean bean. Returns bean or null.
     */
    public static function findBean($id) {
        $id = (int) $id;
        if ($id <= 0) return null;
        $bean = R::load(static::$type, $id);
        return $bean->getID() ? $bean : null;
    }

    /**
     * Rows matching a SQL condition, as a list of arrays.
     *
     *   Post::where('author_id = ? AND published = ?', [5, 1], 'created_at DESC');
     *
     * $sql is a condition with ? placeholders (never put user input in it directly);
     * $orderBy must be a plain column name with optional ASC/DESC.
     */
    public static function where($sql, array $bindings = [], $orderBy = 'id ASC') {
        $beans = R::findAll(static::$type, static::buildQuery($sql, $orderBy), $bindings);
        return array_values(array_map([static::class, 'beanToArray'], $beans));
    }

    /**
     * Number of rows, optionally matching a condition: User::count('role = ?', ['admin']).
     */
    public static function count($sql = '', array $bindings = []) {
        return (int) R::count(static::$type, (string) $sql, $bindings);
    }

    /**
     * One page of rows plus paging info.
     *
     * @return array ['data' => [...], 'meta' => ['page', 'per_page', 'total', 'last_page']]
     */
    public static function paginate($page = 1, $perPage = 15, $sql = '', array $bindings = [], $orderBy = 'id ASC') {
        $page = max(1, (int) $page);
        $perPage = min(100, max(1, (int) $perPage));
        $total = static::count($sql, $bindings);

        $query = static::buildQuery($sql, $orderBy) . ' LIMIT ? OFFSET ?';
        $beans = R::findAll(
            static::$type,
            $query,
            array_merge($bindings, [$perPage, ($page - 1) * $perPage])
        );

        return [
            'data' => array_values(array_map([static::class, 'beanToArray'], $beans)),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * A bean as a plain array without the $hidden fields.
     */
    public static function toArray($bean) {
        if (!$bean) return null;
        $data = $bean->export();
        foreach (static::$hidden as $field) {
            unset($data[$field]);
        }
        return $data;
    }

    /**
     * Hook used by all()/find()/where()/paginate(). Override toArray() instead for most needs.
     */
    protected static function beanToArray($bean) {
        return static::toArray($bean);
    }

    private static function buildQuery($sql, $orderBy) {
        $orderBy = trim((string) $orderBy);
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\s+(ASC|DESC))?$/i', $orderBy)) {
            throw new InvalidArgumentException('Invalid ORDER BY: use a column name with optional ASC or DESC.');
        }
        $sql = trim((string) $sql);
        return ($sql !== '' ? $sql . ' ' : '') . 'ORDER BY ' . $orderBy;
    }
}
