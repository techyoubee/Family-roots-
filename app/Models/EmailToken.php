<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Str;

/**
 * One-time action tokens (email verification, password reset, email change,
 * account deletion). Only a SHA-256 hash of the token is stored, so a database
 * leak does not hand out working links.
 */
final class EmailToken
{
    public static function issue($userId, $purpose, $ttlMinutes, $payload = null)
    {
        $token = Str::randomToken(32);
        $now = time();

        Database::insert('user_tokens', [
            'user_id'    => (int) $userId,
            'purpose'    => (string) $purpose,
            'token_hash' => hash('sha256', $token),
            'payload'    => $payload !== null ? Str::limit($payload, 255) : null,
            'expires_at' => date('Y-m-d H:i:s', $now + max(1, (int) $ttlMinutes) * 60),
            'created_at' => date('Y-m-d H:i:s', $now),
        ]);

        return $token;
    }

    /** Return a token row that is unexpired and unused, or null. */
    public static function findValid($token, $purpose = null)
    {
        if (!is_string($token) || $token === '') {
            return null;
        }
        $sql = 'SELECT * FROM user_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?';
        $params = [hash('sha256', $token), date('Y-m-d H:i:s')];
        if ($purpose !== null) {
            $sql .= ' AND purpose = ?';
            $params[] = (string) $purpose;
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        return Database::first($sql, $params);
    }

    public static function consume($tokenId)
    {
        return Database::update(
            'user_tokens',
            ['used_at' => date('Y-m-d H:i:s')],
            'id = :id AND used_at IS NULL',
            ['id' => (int) $tokenId]
        );
    }

    /** Invalidate every outstanding token of one purpose for a user. */
    public static function invalidate($userId, $purpose)
    {
        return Database::execute(
            'DELETE FROM user_tokens WHERE user_id = ? AND purpose = ? AND used_at IS NULL',
            [(int) $userId, (string) $purpose]
        );
    }

    public static function purgeExpired($olderThanDays = 7)
    {
        return Database::execute(
            'DELETE FROM user_tokens WHERE expires_at < ?',
            [date('Y-m-d H:i:s', time() - ((int) $olderThanDays * 86400))]
        );
    }
}
