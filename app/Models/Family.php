<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Str;

final class Family
{
    public static function find($id)
    {
        return Database::first('SELECT * FROM families WHERE id = ?', [(int) $id]);
    }

    public static function findByPublicId($publicId)
    {
        return Database::first('SELECT * FROM families WHERE public_id = ?', [(string) $publicId]);
    }

    public static function findBySlug($slug)
    {
        return Database::first('SELECT * FROM families WHERE slug = ?', [(string) $slug]);
    }

    public static function slugExists($slug, $exceptId = null)
    {
        $sql = 'SELECT COUNT(*) FROM families WHERE slug = ?';
        $params = [(string) $slug];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = (int) $exceptId;
        }
        return ((int) Database::value($sql, $params)) > 0;
    }

    public static function create(array $data)
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name'] ?? 'family');
        $data['public_id'] = $data['public_id'] ?? self::generatePublicId();
        $data['verification_status'] = $data['verification_status'] ?? 'unverified';
        $data['visibility'] = $data['visibility'] ?? 'members';
        unset($data['id']);

        $id = Database::insert('families', $data);
        return self::find($id);
    }

    public static function generatePublicId()
    {
        do {
            $code = 'FAM-' . strtoupper(substr(Str::randomToken(6), 0, 6));
            $exists = Database::first('SELECT id FROM families WHERE public_id = ?', [$code]);
        } while ($exists);
        return $code;
    }

    public static function update($id, array $data)
    {
        return Database::update('families', $data, 'id = :id', ['id' => (int) $id]);
    }

    public static function verify($id, $byUserId, $note = null)
    {
        return Database::update('families', [
            'verification_status' => 'verified',
            'verification_note'   => $note,
            'verified_at'         => date('Y-m-d H:i:s'),
            'verified_by_user_id' => (int) $byUserId,
        ], 'id = :id', ['id' => (int) $id]);
    }

    public static function rejectVerification($id, $byUserId, $note = null)
    {
        return Database::update('families', [
            'verification_status' => 'rejected',
            'verification_note'   => $note,
            'verified_at'         => null,
            'verified_by_user_id' => (int) $byUserId,
        ], 'id = :id', ['id' => (int) $id]);
    }

    public static function isVerified($family)
    {
        return is_array($family) && ($family['verification_status'] ?? '') === 'verified';
    }

    /**
     * Families whose profile may be shown in the public directory. Private
     * families never appear and their trees stay unreachable without a role.
     */
    public static function forDirectory($search = null, $limit = 50)
    {
        $sql = "SELECT f.*, (SELECT COUNT(*) FROM family_members m WHERE m.family_id = f.id AND m.status = 'active') AS member_count,
                       (SELECT COUNT(*) FROM ancestors a WHERE a.family_id = f.id) AS ancestor_count
                  FROM families f
                 WHERE f.visibility = 'directory'";
        $params = [];
        if ($search !== null && trim($search) !== '') {
            $sql .= ' AND (f.name LIKE ? OR f.origins LIKE ? OR f.country LIKE ? OR f.region LIKE ?)';
            $like = '%' . trim($search) . '%';
            $params = [$like, $like, $like, $like];
        }
        $sql .= ' ORDER BY f.verification_status = "verified" DESC, f.name ASC LIMIT ' . (int) $limit;
        return Database::select($sql, $params);
    }

    public static function countAll()
    {
        return (int) Database::value('SELECT COUNT(*) FROM families');
    }

    public static function withStats($familyId)
    {
        return Database::first(
            'SELECT f.*,
                    (SELECT COUNT(*) FROM family_members m WHERE m.family_id = f.id AND m.status = "active") AS member_count,
                    (SELECT COUNT(*) FROM ancestors a WHERE a.family_id = f.id) AS ancestor_count,
                    (SELECT COUNT(*) FROM family_relationships r WHERE r.family_id = f.id) AS relationship_count
               FROM families f
              WHERE f.id = ?',
            [(int) $familyId]
        );
    }
}
