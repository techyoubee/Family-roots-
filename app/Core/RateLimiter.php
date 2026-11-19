<?php

namespace App\Core;

/**
 * Database-backed rate limiter used to slow down credential stuffing, password
 * resets, registration abuse and evidence uploads. Keys are hashed so raw
 * emails / IPs are not stored in the bucket table.
 */
final class RateLimiter
{
    public static function key(...$parts)
    {
        return hash('sha256', implode('|', array_map('strval', $parts)));
    }

    /** @return bool true when the attempt is allowed */
    public static function attempt($action, $key, $maxAttempts, $windowSeconds)
    {
        self::prune();
        $bucket = self::key($action, $key);
        $since = date('Y-m-d H:i:s', time() - max(1, (int) $windowSeconds));

        $hits = (int) Database::value(
            'SELECT COUNT(*) FROM rate_limit_hits WHERE bucket_key = ? AND action = ? AND created_at >= ?',
            [$bucket, $action, $since]
        );

        if ($hits >= (int) $maxAttempts) {
            return false;
        }

        Database::insert('rate_limit_hits', [
            'bucket_key' => $bucket,
            'action'     => $action,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    public static function remaining($action, $key, $maxAttempts, $windowSeconds)
    {
        $bucket = self::key($action, $key);
        $since = date('Y-m-d H:i:s', time() - max(1, (int) $windowSeconds));
        $hits = (int) Database::value(
            'SELECT COUNT(*) FROM rate_limit_hits WHERE bucket_key = ? AND action = ? AND created_at >= ?',
            [$bucket, $action, $since]
        );
        return max(0, (int) $maxAttempts - $hits);
    }

    public static function clear($action, $key)
    {
        Database::execute('DELETE FROM rate_limit_hits WHERE bucket_key = ? AND action = ?', [self::key($action, $key), $action]);
    }

    public static function prune($olderThanSeconds = 86400)
    {
        // Cheap probabilistic cleanup so the table does not grow forever.
        if (random_int(1, 50) !== 1) {
            return;
        }
        Database::execute(
            'DELETE FROM rate_limit_hits WHERE created_at < ?',
            [date('Y-m-d H:i:s', time() - (int) $olderThanSeconds)]
        );
    }

    public static function flush()
    {
        Database::execute('DELETE FROM rate_limit_hits');
    }
}
