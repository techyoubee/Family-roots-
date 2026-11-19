<?php

namespace App\Models;

use App\Core\Database;

/**
 * Per-user progress through the Ancestral Journey.
 *
 * The feature is additive: if the `ancestral_journeys` table has not been
 * created yet (the application database user may lack DDL rights), every
 * method here degrades to a no-op and the journey pages still render.
 */
final class AncestralJourney
{
    /** @var bool|null */
    private static $available;

    public static function available()
    {
        if (self::$available !== null) {
            return self::$available;
        }
        try {
            $table = Database::value("SHOW TABLES LIKE 'ancestral_journeys'");
            self::$available = is_string($table) && $table !== '';
        } catch (\Throwable $e) {
            self::$available = false;
        }
        return self::$available;
    }

    /** @return array|null */
    public static function forUser($userId, $familyId)
    {
        if (!self::available()) {
            return null;
        }
        try {
            return Database::first(
                'SELECT * FROM ancestral_journeys WHERE user_id = ? AND family_id = ?',
                [(int) $userId, (int) $familyId]
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** The journey this user touched most recently, across families. */
    public static function latestForUser($userId)
    {
        if (!self::available()) {
            return null;
        }
        try {
            return Database::first(
                'SELECT * FROM ancestral_journeys
                  WHERE user_id = ?
                  ORDER BY COALESCE(completed_at, last_seen_at, updated_at, created_at) DESC, id DESC
                  LIMIT 1',
                [(int) $userId]
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Create the progress row on first visit, or refresh it on later visits. */
    public static function start($userId, $familyId, $step = 'intro')
    {
        if (!self::available()) {
            return null;
        }
        try {
            $now = date('Y-m-d H:i:s');
            $existing = self::forUser($userId, $familyId);
            if ($existing) {
                $data = ['last_seen_at' => $now, 'step' => (string) $step];
                if (empty($existing['started_at'])) {
                    $data['started_at'] = $now;
                }
                Database::update('ancestral_journeys', $data, 'id = :id', ['id' => (int) $existing['id']]);
                return self::forUser($userId, $familyId);
            }
            Database::insert('ancestral_journeys', [
                'user_id'      => (int) $userId,
                'family_id'    => (int) $familyId,
                'step'         => (string) $step,
                'started_at'   => $now,
                'last_seen_at' => $now,
                'meta'         => json_encode(['version' => 1]),
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
            return self::forUser($userId, $familyId);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function touch($userId, $familyId, $step)
    {
        return self::start($userId, $familyId, $step);
    }

    public static function complete($userId, $familyId)
    {
        if (!self::available()) {
            return null;
        }
        try {
            $now = date('Y-m-d H:i:s');
            $existing = self::forUser($userId, $familyId);
            if (!$existing) {
                self::start($userId, $familyId, 'complete');
                $existing = self::forUser($userId, $familyId);
            }
            if (!$existing) {
                return null;
            }
            Database::update('ancestral_journeys', [
                'step'         => 'complete',
                'completed_at' => $now,
                'last_seen_at' => $now,
            ], 'id = :id', ['id' => (int) $existing['id']]);
            return self::forUser($userId, $familyId);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Delete one progress row (used by "start again"). */
    public static function reset($userId, $familyId)
    {
        if (!self::available()) {
            return false;
        }
        try {
            return Database::execute(
                'DELETE FROM ancestral_journeys WHERE user_id = ? AND family_id = ?',
                [(int) $userId, (int) $familyId]
            ) >= 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
