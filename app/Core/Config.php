<?php

namespace App\Core;

use RuntimeException;

/**
 * Dot-notation configuration store. Booted once from config/config.php and
 * overridden by tests with a dedicated database / matching rules.
 */
final class Config
{
    private static $items = [];

    public static function load(array $items)
    {
        self::$items = $items;
    }

    public static function all()
    {
        return self::$items;
    }

    public static function get($key, $default = null)
    {
        if ($key === null || $key === '') {
            return $default;
        }
        $segments = explode('.', $key);
        $value = self::$items;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public static function has($key)
    {
        return self::get($key, '__missing__') !== '__missing__';
    }

    public static function set($key, $value)
    {
        $segments = explode('.', $key);
        $ref = &self::$items;
        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }

    public static function require($key)
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            throw new RuntimeException('Missing required configuration value: ' . $key);
        }
        return $value;
    }
}
