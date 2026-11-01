<?php

/**
 * @package Validator
 * @author jhzrmx
 * @version 1.0.0
 * @license MIT
 * @link https://github.com/jhzrmx/php-mvc-template
 * @requires PHP 7.0+
 * @dependencies None (standalone, can be downloaded and used on its own)
 * @description Small validator.
 *
 *   $v = Validator::make($data, [
 *       'username' => 'required|string|alpha_dash|min:3|max:32',
 *       'email'    => 'nullable|email',
 *       'age'      => 'integer|between:18,120',
 *       'role'     => 'in:user,admin',
 *       'password' => ['required', 'min:8', 'confirmed'],
 *   ]);
 *   if ($v->fails()) { $v->errors(); }  // ['field' => ['message']]
 *   $clean = $v->validated();            // only the fields that have rules
 *
 * Rules: required, nullable, string, integer, numeric, boolean, array, email, url, date,
 *        alpha, alpha_num, alpha_dash, min:n, max:n, max_bytes:n, between:a,b, in:a,b, not_in:a,b,
 *        regex:/pattern/, same:field, confirmed
 *
 * A field that is missing/empty and not "required" skips its other rules.
 * Only the first failing rule is reported for each field.
 * min/max/between measure the value for numbers, characters for strings, items for arrays
 * (a field is treated as a number when it also has the integer or numeric rule).
 * max_bytes:n counts bytes, for limits that are really byte limits (e.g. bcrypt passwords, 72 bytes).
 */

namespace JHz\Support;

use InvalidArgumentException;

class Validator {
    private $data;
    private $rules;
    private $messages;
    private $errors = [];
    private $ran = false;

    public static function make(array $data, array $rules, array $messages = []) {
        return new self($data, $rules, $messages);
    }

    private function __construct(array $data, array $rules, array $messages) {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = $messages;
    }

    public function passes() {
        $this->run();
        return count($this->errors) === 0;
    }

    public function fails() {
        return !$this->passes();
    }

    /**
     * @return array ['field' => ['message', ...]]
     */
    public function errors() {
        $this->run();
        return $this->errors;
    }

    public function firstError() {
        foreach ($this->errors() as $messages) {
            return $messages[0];
        }
        return null;
    }

    /**
     * The input restricted to fields that have rules. Call after passes().
     */
    public function validated() {
        return array_intersect_key($this->data, $this->rules);
    }

    private function run() {
        if ($this->ran) {
            return;
        }
        $this->ran = true;

        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', (string) $ruleSet);
            $parsed = [];
            foreach ($rules as $rule) {
                $rule = trim((string) $rule);
                if ($rule === '') {
                    continue;
                }
                $parsed[] = $this->parseRule($rule);
            }
            $names = array_map(function ($p) {
                return $p[0];
            }, $parsed);

            $value = $this->data[$field] ?? null;

            if ($this->isEmpty($value)) {
                if (in_array('required', $names, true)) {
                    $this->addError($field, 'required', [], $value, false);
                }
                continue;
            }

            $numeric = in_array('integer', $names, true) || in_array('numeric', $names, true);
            foreach ($parsed as $p) {
                list($name, $args) = $p;
                if ($name === 'required' || $name === 'nullable') {
                    continue;
                }
                if (!$this->check($name, $field, $value, $args, $numeric)) {
                    $this->addError($field, $name, $args, $value, $numeric);
                    break;
                }
            }
        }
    }

    private function parseRule($rule) {
        $pos = strpos($rule, ':');
        if ($pos === false) {
            return [strtolower($rule), []];
        }
        $name = strtolower(substr($rule, 0, $pos));
        $arg = substr($rule, $pos + 1);
        return [$name, $name === 'regex' ? [$arg] : explode(',', $arg)];
    }

    private function isEmpty($value) {
        if ($value === null) {
            return true;
        }
        if (is_string($value) && trim($value) === '') {
            return true;
        }
        return is_array($value) && count($value) === 0;
    }

    private function size($value, $numeric) {
        if (is_array($value)) {
            return count($value);
        }
        if ($numeric && is_numeric($value)) {
            return $value + 0;
        }
        if (is_string($value)) {
            return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        }
        return is_numeric($value) ? $value + 0 : 0;
    }

    private function check($name, $field, $value, array $args, $numeric) {
        switch ($name) {
            case 'string':
                return is_string($value);
            case 'integer':
                return is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1);
            case 'numeric':
                return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value));
            case 'boolean':
                return in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true);
            case 'array':
                return is_array($value);
            case 'email':
                return is_string($value) && strlen($value) <= 254 && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            case 'url':
                return is_string($value)
                    && filter_var($value, FILTER_VALIDATE_URL) !== false
                    && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
            case 'date':
                return is_string($value) && strtotime($value) !== false;
            case 'alpha':
                return is_string($value) && preg_match('/^[\pL\pM]+$/u', $value) === 1;
            case 'alpha_num':
                return is_string($value) && preg_match('/^[\pL\pM\pN]+$/u', $value) === 1;
            case 'alpha_dash':
                return is_string($value) && preg_match('/^[\pL\pM\pN_-]+$/u', $value) === 1;
            case 'min':
                return $this->size($value, $numeric) >= (float) ($args[0] ?? 0);
            case 'max':
                return $this->size($value, $numeric) <= (float) ($args[0] ?? 0);
            case 'max_bytes':
                return is_string($value) && strlen($value) <= (int) ($args[0] ?? 0);
            case 'between':
                $size = $this->size($value, $numeric);
                return $size >= (float) ($args[0] ?? 0) && $size <= (float) ($args[1] ?? 0);
            case 'in':
                return is_scalar($value) && in_array((string) $value, $args, true);
            case 'not_in':
                return !is_scalar($value) || !in_array((string) $value, $args, true);
            case 'regex':
                return is_string($value) && @preg_match($args[0], $value) === 1;
            case 'same':
                return $value === ($this->data[$args[0] ?? ''] ?? null);
            case 'confirmed':
                return $value === ($this->data[$field . '_confirmation'] ?? null);
        }
        throw new InvalidArgumentException("Unknown validation rule: $name");
    }

    private function addError($field, $rule, array $args, $value, $numeric) {
        $this->errors[$field][] = $this->message($field, $rule, $args, $value, $numeric);
    }

    private function message($field, $rule, array $args, $value, $numeric) {
        if (isset($this->messages[$field . '.' . $rule])) {
            return $this->messages[$field . '.' . $rule];
        }
        if (isset($this->messages[$rule])) {
            return str_replace(':field', $field, $this->messages[$rule]);
        }

        $name = str_replace('_', ' ', $field);
        $kind = is_array($value) ? 'items' : (($numeric && is_numeric($value)) ? '' : 'characters');
        $unit = $kind === '' ? '' : ' ' . $kind;
        $a0 = $args[0] ?? '';
        $a1 = $args[1] ?? '';

        switch ($rule) {
            case 'required':   return "The $name field is required.";
            case 'string':     return "The $name field must be text.";
            case 'integer':    return "The $name field must be a whole number.";
            case 'numeric':    return "The $name field must be a number.";
            case 'boolean':    return "The $name field must be true or false.";
            case 'array':      return "The $name field must be a list.";
            case 'email':      return "The $name field must be a valid email address.";
            case 'url':        return "The $name field must be a valid http(s) URL.";
            case 'date':       return "The $name field must be a valid date.";
            case 'alpha':      return "The $name field may only contain letters.";
            case 'alpha_num':  return "The $name field may only contain letters and numbers.";
            case 'alpha_dash': return "The $name field may only contain letters, numbers, dashes and underscores.";
            case 'min':        return "The $name field must be at least $a0$unit.";
            case 'max':        return "The $name field must be at most $a0$unit.";
            case 'max_bytes':  return "The $name field must be at most $a0 bytes.";
            case 'between':    return "The $name field must be between $a0 and $a1$unit.";
            case 'in':         return "The selected $name is invalid.";
            case 'not_in':     return "The selected $name is not allowed.";
            case 'regex':      return "The $name field format is invalid.";
            case 'same':       return "The $name field must match $a0.";
            case 'confirmed':  return "The $name confirmation does not match.";
        }
        return "The $name field is invalid.";
    }
}
