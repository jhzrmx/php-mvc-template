<?php

/**
 * @package DotEnv
 * @author jhzrmx
 * @version 1.1.0
 * @license MIT
 * @link https://github.com/jhzrmx/php-mvc-template
 * @requires PHP 7.0+
 * @dependencies None (standalone, can be downloaded and used on its own)
 * @description Loads variables from a .env file into $_ENV and the process environment. The global env() helper
 * (libs/helpers.php) reads them back ("true"/"false"/"null"/"empty" are cast like in Laravel).
 * See DotEnv::loadFromFile() for the supported syntax.
 */

namespace JHz\Support;

use Exception;

class DotEnv {
    /**
     * Load environment variables from a .env file.
     *
     * Values are always stored as strings (so DB_PASS=0123 stays "0123"). Use env() to read
     * a variable with true/false/null casting, or cast explicitly: (int) env('PORT').
     *
     * Supported syntax:
     *   KEY=value                  unquoted (inline comments allowed: KEY=value # comment)
     *   KEY="value with # and $"   double quotes: escapes \n \r \t \" \\ \$ and ${VAR} expansion
     *   KEY='literal ${value}'     single quotes: no escapes, no expansion
     *   export KEY=value
     *
     * Variables that already exist in the real environment are never overridden.
     *
     * @param string $filePath The path to the .env file.
     * @return void
     * @throws Exception If the file doesn't exist, cannot be read, or contains invalid syntax.
     */
    public static function loadFromFile($filePath = ".env") {
        if (!file_exists($filePath)) {
            throw new Exception("The .env file does not exist: " . $filePath);
        }

        if (!is_readable($filePath)) {
            throw new Exception("The .env file is not readable: " . $filePath);
        }

        $content = file_get_contents($filePath);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // UTF-8 BOM

        foreach (preg_split('/\r\n|\n|\r/', $content) as $rawLine) {
            $line = trim($rawLine);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $name  = trim(preg_replace('/^export\s+/', '', trim(substr($line, 0, $pos))));
            $value = ltrim(substr($line, $pos + 1));

            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                throw new Exception("Invalid environment variable name: " . $name);
            }

            if (isset($_ENV[$name]) || getenv($name) !== false) {
                continue;
            }

            $value = self::parseValue($value, $name);

            $_ENV[$name] = $value;
            putenv($name . '=' . $value);
        }
    }

    /**
     * Parse the right-hand side of a KEY=VALUE line.
     *
     * @param string $value The raw value (already left-trimmed).
     * @param string $name  The variable name, for error messages (never the value: it may be a secret).
     * @return string
     * @throws Exception On unterminated quotes or junk after a closing quote.
     */
    private static function parseValue($value, $name) {
        if ($value === '' || $value[0] === '#') {
            return '';
        }

        $quote = $value[0];

        if ($quote === "'") {
            $end = strpos($value, "'", 1);
            if ($end === false) {
                throw new Exception("Mismatched quotes in the value of " . $name);
            }
            self::assertOnlyCommentAfter(substr($value, $end + 1), $name);
            return substr($value, 1, $end - 1);
        }

        if ($quote === '"') {
            $escapes = ['n' => "\n", 'r' => "\r", 't' => "\t", '"' => '"', '\\' => '\\', '$' => '$'];
            $len = strlen($value);
            $out = '';
            $closed = false;
            $i = 1;
            while ($i < $len) {
                $ch = $value[$i];
                if ($ch === '\\' && $i + 1 < $len && isset($escapes[$value[$i + 1]])) {
                    $out .= $escapes[$value[$i + 1]];
                    $i += 2;
                    continue;
                }
                if ($ch === '"') {
                    $closed = true;
                    $i++;
                    break;
                }
                if ($ch === '$' && preg_match('/\G\$\{([A-Za-z0-9_]+)\}/', $value, $m, 0, $i)) {
                    $out .= self::lookup($m[1], $m[0]);
                    $i += strlen($m[0]);
                    continue;
                }
                $out .= $ch;
                $i++;
            }
            if (!$closed) {
                throw new Exception("Mismatched quotes in the value of " . $name);
            }
            self::assertOnlyCommentAfter(substr($value, $i), $name);
            return $out;
        }

        // Unquoted: strip a trailing " # comment", then expand ${VAR}
        $value = trim(preg_replace('/\s+#.*$/', '', $value));
        return self::expandVariables($value);
    }

    private static function assertOnlyCommentAfter($rest, $name) {
        $rest = trim($rest);
        if ($rest !== '' && $rest[0] !== '#') {
            throw new Exception("Unexpected characters after the closing quote in the value of " . $name);
        }
    }

    /**
     * Look up a variable from the .env values loaded so far or the real environment.
     *
     * @param string $var      Variable name.
     * @param string $fallback Returned unchanged when the variable isn't set.
     * @return string
     */
    public static function lookup($var, $fallback) {
        if (isset($_ENV[$var])) {
            return (string) $_ENV[$var];
        }
        $env = getenv($var);
        return $env !== false ? $env : $fallback;
    }

    /**
     * Expand variables in the format ${VAR_NAME} within the given value.
     *
     * @param string $value The value containing potential variable references.
     * @return string The value with variables expanded.
     */
    private static function expandVariables($value) {
        return preg_replace_callback('/\$\{([A-Za-z0-9_]+)\}/', function ($matches) {
            return DotEnv::lookup($matches[1], $matches[0]);
        }, $value);
    }
}
