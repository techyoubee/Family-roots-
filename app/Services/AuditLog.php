<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Str;

/**
 * Append-only audit trail. Every membership, verification, correction and
 * account event is recorded here with the actor, the family it belongs to and
 * an optional before/after snapshot. Failures are logged but never interrupt
 * the user action that triggered them.
 *
 * Personal data is kept out of the summaries where possible; snapshots are for
 * the record itself (name, status), not for passwords or tokens.
 */
final class AuditLog
{
    public static function record($action, array $options = [])
    {
        if (!self::isEnabled()) {
            return null;
        }
        $request = $options['request'] ?? (isset($GLOBALS['__request']) ? $GLOBALS['__request'] : null);
        $actorId = array_key_exists('actor_user_id', $options) ? $options['actor_user_id'] : Auth::id();

        try {
            return Database::insert('audit_logs', [
                'family_id'     => !empty($options['family_id']) ? (int) $options['family_id'] : null,
                'actor_user_id' => $actorId ? (int) $actorId : null,
                'action'        => Str::limit($action, 80),
                'entity_type'   => !empty($options['entity_type']) ? Str::limit($options['entity_type'], 60) : null,
                'entity_id'     => !empty($options['entity_id']) ? (int) $options['entity_id'] : null,
                'summary'       => !empty($options['summary']) ? Str::limit($options['summary'], 500) : null,
                'before_json'   => self::encode($options['before'] ?? null),
                'after_json'    => self::encode($options['after'] ?? null),
                'ip_address'    => $request instanceof Request ? $request->ip() : null,
                'user_agent'    => $request instanceof Request ? Str::limit($request->userAgent(), 255) : null,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Audit log write failed for action ' . $action . ': ' . $e->getMessage());
            return null;
        }
    }

    private static function encode($value)
    {
        if ($value === null) {
            return null;
        }
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $json === false ? null : $json;
    }

    /** Recent activity, optionally scoped to one family. */
    public static function recent($familyId = null, $limit = 100)
    {
        $sql = 'SELECT a.*, u.full_name AS actor_name
                  FROM audit_logs a
             LEFT JOIN users u ON u.id = a.actor_user_id';
        $params = [];
        if ($familyId) {
            $sql .= ' WHERE a.family_id = ?';
            $params[] = (int) $familyId;
        }
        $sql .= ' ORDER BY a.created_at DESC, a.id DESC LIMIT ' . (int) $limit;
        return Database::select($sql, $params);
    }

    public static function isEnabled()
    {
        return (bool) Config::get('audit.enabled', true);
    }
}
