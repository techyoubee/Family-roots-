<?php

/**
 * Family Roots - lightweight .env loader.
 *
 * Reads KEY=VALUE lines from a `.env` file and exposes them through getenv(),
 * $_ENV and $_SERVER. Real environment variables always win, so container or
 * hosting-provided configuration overrides the file.
 */

namespace App\Core;

final class Env
{
    private static $loaded = false;

    public static function load($file)
    {
        if (self::$loaded || !is_file($file) || !is_readable($file)) {
            return;
        }
        self::$loaded = true;

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            if ($key === '') {
                continue;
            }

            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = substr($value, -1);
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                } else {
                    $hash = strpos($value, ' #');
                    if ($hash !== false) {
                        $value = rtrim(substr($value, 0, $hash));
                    }
                }
            }

            self::set($key, $value);
        }
    }

    public static function set($key, $value)
    {
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
        }
        if (!isset($_ENV[$key])) {
            $_ENV[$key] = $value;
        }
        if (!isset($_SERVER[$key])) {
            $_SERVER[$key] = $value;
        }
    }

    public static function get($key, $default = null)
    {
        $value = getenv($key);
        return ($value === false || $value === '') ? $default : $value;
    }

    /** Reset loader state - used by the test harness. */
    public static function reset()
    {
        self::$loaded = false;
    }
}
