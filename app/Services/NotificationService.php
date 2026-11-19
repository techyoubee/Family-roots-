<?php

namespace App\Services;

use App\Core\Database;

/**
 * In-app notifications. Every decision that affects a user (application
 * received, approved, declined, more information requested, correction
 * resolved) creates a row here and, when mail is configured, an email.
 */
final class NotificationService
{
    public static function notify($userId, $type, $title, $body = null, $linkPath = null, $familyId = null)
    {
        if (empty($userId)) {
            return null;
        }
        $id = Database::insert('notifications', [
            'user_id'    => (int) $userId,
            'family_id'  => $familyId ? (int) $familyId : null,
            'type'       => (string) $type,
            'title'      => \App\Core\Str::limit($title, 150),
            'body'       => $body !== null ? \App\Core\Str::limit($body, 1000) : null,
            'link_path'  => $linkPath,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Mailer::sendIfConfigured($userId, $title, (string) $body);
        return $id;
    }

    public static function notifyMany(array $userIds, $type, $title, $body = null, $linkPath = null, $familyId = null)
    {
        $sent = 0;
        foreach (array_unique(array_map('intval', $userIds)) as $userId) {
            if ($userId > 0 && self::notify($userId, $type, $title, $body, $linkPath, $familyId)) {
                $sent++;
            }
        }
        return $sent;
    }

    public static function forUser($userId, $limit = 30, $unreadOnly = false)
    {
        $sql = 'SELECT * FROM notifications WHERE user_id = ?';
        if ($unreadOnly) {
            $sql .= ' AND read_at IS NULL';
        }
        $sql .= ' ORDER BY created_at DESC LIMIT ' . (int) $limit;
        return Database::select($sql, [(int) $userId]);
    }

    public static function unreadCount($userId)
    {
        try {
            return (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [(int) $userId]);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function markRead($notificationId, $userId)
    {
        return Database::execute(
            'UPDATE notifications SET read_at = ? WHERE id = ? AND user_id = ? AND read_at IS NULL',
            [date('Y-m-d H:i:s'), (int) $notificationId, (int) $userId]
        );
    }

    public static function markAllRead($userId)
    {
        return Database::execute(
            'UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL',
            [date('Y-m-d H:i:s'), (int) $userId]
        );
    }

    public static function belongsTo($notificationId, $userId)
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM notifications WHERE id = ? AND user_id = ?',
            [(int) $notificationId, (int) $userId]
        ) > 0;
    }
}
