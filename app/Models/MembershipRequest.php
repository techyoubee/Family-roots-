<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Str;

/**
 * A membership request is one person's documented claim to belong to a family.
 *
 * Nothing here decides membership on its own: the matching engine (Module E)
 * produces a score and a per-claim explanation, but a family administrator
 * always makes the final decision. The row keeps every step - submission,
 * scoring, review, decision, appeal - so the workflow is auditable.
 */
final class MembershipRequest
{
    /** Statuses at which a request still occupies the "one open request" slot. */
    public const OPEN_STATUSES = [
        'draft', 'submitted', 'auto_matched', 'pending_review', 'more_info_required', 'appeal_requested',
    ];

    /** Statuses that can no longer be acted on by the applicant. */
    public const CLOSED_STATUSES = ['approved', 'declined', 'cancelled'];

    public static function find($id)
    {
        return Database::first('SELECT * FROM membership_requests WHERE id = ?', [(int) $id]);
    }

    public static function findByPublicId($publicId)
    {
        return Database::first('SELECT * FROM membership_requests WHERE public_id = ?', [(string) $publicId]);
    }

    /** Family-scoped fetch so a request can never be opened through another family. */
    public static function findForFamily($familyId, $key)
    {
        if (ctype_digit((string) $key)) {
            return Database::first(
                'SELECT * FROM membership_requests WHERE id = ? AND family_id = ?',
                [(int) $key, (int) $familyId]
            );
        }
        return Database::first(
            'SELECT * FROM membership_requests WHERE public_id = ? AND family_id = ?',
            [(string) $key, (int) $familyId]
        );
    }

    public static function create(array $data)
    {
        $data['public_id'] = $data['public_id'] ?? self::generatePublicId();
        $data['status'] = $data['status'] ?? 'draft';
        unset($data['id']);
        $id = Database::insert('membership_requests', $data);
        return self::find($id);
    }

    public static function update($id, array $data)
    {
        return Database::update('membership_requests', $data, 'id = :id', ['id' => (int) $id]);
    }

    public static function generatePublicId()
    {
        do {
            $code = 'REQ-' . strtoupper(substr(Str::randomToken(6), 0, 6));
            $exists = Database::first('SELECT id FROM membership_requests WHERE public_id = ?', [$code]);
        } while ($exists);
        return $code;
    }

    /**
     * The applicant's single open request in a family, or null. The database
     * also enforces this with a unique key; this is the readable accessor.
     */
    public static function openFor($familyId, $userId)
    {
        return Database::first(
            'SELECT * FROM membership_requests
              WHERE family_id = ? AND applicant_user_id = ?
                AND status IN (' . self::openStatusSql() . ')
              ORDER BY id DESC LIMIT 1',
            [(int) $familyId, (int) $userId]
        );
    }

    /** Every request from one applicant (newest first). */
    public static function forApplicant($userId, $limit = 50)
    {
        return Database::select(
            'SELECT r.*, f.name AS family_name, f.public_id AS family_public_id, f.visibility AS family_visibility
               FROM membership_requests r
               JOIN families f ON f.id = r.family_id
              WHERE r.applicant_user_id = ?
              ORDER BY r.created_at DESC LIMIT ' . (int) $limit,
            [(int) $userId]
        );
    }

    /** The review queue for one family, most urgent first. */
    public static function forFamily($familyId, array $filters = [])
    {
        $sql = 'SELECT r.*, u.full_name AS applicant_name, u.email AS applicant_email
                  FROM membership_requests r
                  JOIN users u ON u.id = r.applicant_user_id
                 WHERE r.family_id = ?';
        $params = [(int) $familyId];

        if (!empty($filters['status'])) {
            $sql .= ' AND r.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['open_only'])) {
            $sql .= ' AND r.status IN (' . self::openStatusSql() . ')';
        }
        // Drafts belong to the applicant alone; administrators see submitted work.
        if (!empty($filters['decidable'])) {
            $sql .= " AND r.status NOT IN ('draft')";
        }

        $sql .= " ORDER BY FIELD(r.status, 'pending_review', 'auto_matched', 'appeal_requested', 'more_info_required', 'submitted', 'approved', 'declined', 'cancelled', 'draft'), r.created_at DESC";

        $limit = isset($filters['limit']) ? max(1, min(200, (int) $filters['limit'])) : 100;
        $sql .= ' LIMIT ' . $limit;
        return Database::select($sql, $params);
    }

    public static function countForFamily($familyId, $status = null)
    {
        $sql = 'SELECT COUNT(*) FROM membership_requests WHERE family_id = ?';
        $params = [(int) $familyId];
        if ($status !== null) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        return (int) Database::value($sql, $params);
    }

    /** How many requests await a human decision (used on dashboards). */
    public static function pendingReviewCount($familyId)
    {
        return (int) Database::value(
            "SELECT COUNT(*) FROM membership_requests WHERE family_id = ? AND status IN ('submitted','auto_matched','pending_review','appeal_requested')",
            [(int) $familyId]
        );
    }

    public static function statusCounts($familyId)
    {
        return Database::select(
            'SELECT status, COUNT(*) AS total FROM membership_requests WHERE family_id = ? GROUP BY status',
            [(int) $familyId]
        );
    }

    public static function isOpen(array $request)
    {
        return in_array($request['status'], self::OPEN_STATUSES, true);
    }

    private static function openStatusSql()
    {
        return "'" . implode("','", self::OPEN_STATUSES) . "'";
    }
}
