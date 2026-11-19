<?php

namespace App\Core;

/**
 * Append-only application log. Secrets, tokens, passwords and raw evidence
 * content are stripped before anything reaches disk.
 */
final class Logger
{
    /** @var string[] */
    private static $redactedKeys = [
        'password', 'password_confirmation', 'pass', 'passwd', 'token', 'token_hash',
        'csrf', '_token', 'secret', 'authorization', 'api_key', 'app_key',
    ];

    public static function debug($message, array $context = [])
    {
        self::write('DEBUG', $message, $context);
    }

    public static function info($message, array $context = [])
    {
        self::write('INFO', $message, $context);
    }

    public static function warning($message, array $context = [])
    {
        self::write('WARNING', $message, $context);
    }

    public static function error($message, array $context = [])
    {
        self::write('ERROR', $message, $context);
    }

    public static function exception(\Throwable $e, $level = 'ERROR')
    {
        self::write($level, $e->getMessage(), [
            'exception' => get_class($e),
            'file'      => $e->getFile() . ':' . $e->getLine(),
            'trace'     => self::redact(explode("\n", $e->getTraceAsString())),
        ]);
    }

    public static function directory()
    {
        $dir = Config::get('root', dirname(__DIR__, 2)) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        return $dir;
    }

    private static function write($level, $message, array $context)
    {
        $line = sprintf(
            '[%s] %s: %s%s',
            date('Y-m-d H:i:s'),
            $level,
            str_replace(["\r", "\n"], ' ', (string) $message),
            $context ? ' ' . json_encode(self::redact($context), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''
        );
        $file = self::directory() . '/app-' . date('Y-m-d') . '.log';
        @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public static function redact($value, $depth = 0)
    {
        if ($depth > 6) {
            return '***';
        }
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                if (is_string($key) && self::isSensitive($key)) {
                    $clean[$key] = '***redacted***';
                    continue;
                }
                $clean[$key] = self::redact($item, $depth + 1);
            }
            return $clean;
        }
        return $value;
    }

    private static function isSensitive($key)
    {
        $key = strtolower($key);
        foreach (self::$redactedKeys as $needle) {
            if (strpos($key, $needle) !== false) {
                return true;
            }
        }
        return strpos($key, 'evidence') !== false && strpos($key, 'id') === false;
    }
}
