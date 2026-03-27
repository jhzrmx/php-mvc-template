<?php

/**
 * @package DotEnv
 * @author jhzrmx
 * @version 1.0.0
 * @license MIT
 * @link https://github.com/jhzrmx/php-mvc-template
 */
class DotEnv {
	/**
     * Load environment variables from a .env file.
     *
     * @param string $filePath The path to the .env file.
     * @return void
     * @throws Exception If the file doesn't exist, cannot be read, or contains mismatched quotes.
     */
    public static function loadFromFile($filePath = ".env") {
        if (!file_exists($filePath)) {
            throw new Exception("The .env file does not exist: " . $filePath);
        }

        if (!is_readable($filePath)) {
            throw new Exception("The .env file is not readable: " . $filePath);
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (isset($lines[0])) {
            $lines[0] = preg_replace('/^\xEF\xBB\xBF/', '', $lines[0]);
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            $parts = explode('=', $line, 2);

            $name  = isset($parts[0]) ? $parts[0] : null;
            $value = isset($parts[1]) ? $parts[1] : null;

            if ($value === null) {
                continue;
            }

            $name  = trim(preg_replace('/^export\s+/', '', $name));
            $value = trim($value);

            if (!preg_match('/^[A-Z0-9_]+$/', $name)) {
                throw new Exception("Invalid environment variable name: " . $name);
            }

            if (isset($_ENV[$name]) || getenv($name) !== false) {
                continue;
            }

            $value = preg_replace('/\s+#.*$/', '', $value);

            $first = strlen($value) > 0 ? $value[0] : '';
            $last  = strlen($value) > 0 ? substr($value, -1) : '';

            if (($first === '"' || $first === "'") && $last !== $first) {
                throw new Exception("Mismatched quotes in: " . $line);
            }

            if ($first === $last && ($first === '"' || $first === "'")) {
                $value = substr($value, 1, -1);
            }
            $value = self::expandVariables($value);
            $value = self::convertKeyValue($value);

            $_ENV[$name] = $value;
            putenv($name . '=' . $value);
        }
    }

    /**
     * Convert string values to their appropriate types (boolean, null, integer, float).
     *
     * @param string $value The value to convert.
     * @return mixed The converted value.
     */
    private static function convertKeyValue($value) {
        $lower = strtolower($value);

        if ($lower === 'true') return true;
        if ($lower === 'false') return false;
        if ($lower === 'null') return null;

        if (ctype_digit($value)) return (int)$value;
        if (is_numeric($value)) return (float)$value;

        return $value;
    }

    /**
     * Expand variables in the format ${VAR_NAME} within the given value.
     *
     * @param string $value The value containing potential variable references.
     * @return string The value with variables expanded.
     */
    private static function expandVariables($value) {
        return preg_replace_callback('/\$\{([A-Z0-9_]+)\}/i', function ($matches) {
            $var = $matches[1];
            if (isset($_ENV[$var])) {
                return $_ENV[$var];
            }
            $env = getenv($var);
            if ($env !== false) {
                return $env;
            }
            return $matches[0];
        }, $value);
    }
}