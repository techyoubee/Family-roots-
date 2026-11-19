<?php

namespace App\Models;

use App\Core\Database;

final class FamilyMember
{
    public static function find($id)
    {
        return Database::first('SELECT * FROM family_members WHERE id = ?', [(int) $id]);
    }

    public static function findFor($familyId, $userId)
    {
        return Database::first(
            'SELECT * FROM family_members WHERE family_id = ? AND user_id = ?',
            [(int) $familyId, (int) $userId]
        );
    }

    /** Active role of a user inside a family, or null when not a member. */
    public static function roleFor($familyId, $userId)
    {
        $row = Database::first(
            'SELECT role FROM family_members WHERE family_id = ? AND user_id = ? AND status = ?',
            [(int) $familyId, (int) $userId, 'active']
        );
        return $row ? $row['role'] : null;
    }

    public static function create(array $data)
    {
        $data['status'] = $data['status'] ?? 'active';
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        unset($data['id']);
        return Database::insert('family_members', $data);
    }

    public static function upsertRole($familyId, $userId, $role, array $extra = [])
    {
        $existing = self::findFor($familyId, $userId);
        if ($existing) {
            $data = array_merge(['role' => $role, 'status' => 'active'], $extra);
            Database::update('family_members', $data, 'id = :id', ['id' => (int) $existing['id']]);
            return (int) $existing['id'];
        }
        return self::create(array_merge([
            'family_id' => (int) $familyId,
            'user_id'   => (int) $userId,
            'role'      => $role,
        ], $extra));
    }

    public static function remove($familyId, $userId)
    {
        return Database::update(
            'family_members',
            ['status' => 'removed'],
            'family_id = :f AND user_id = :u',
            ['f' => (int) $familyId, 'u' => (int) $userId]
        );
    }

    /** @return array active members with profile data joined */
    public static function listForFamily($familyId, $withRemoved = false)
    {
        $sql = 'SELECT m.*, u.full_name, u.email, u.display_name, a.given_names, a.surname, a.generation
                  FROM family_members m
                  JOIN users u ON u.id = m.user_id
             LEFT JOIN ancestors a ON a.id = m.ancestor_id
                 WHERE m.family_id = ?';
        $params = [(int) $familyId];
        if (!$withRemoved) {
            $sql .= " AND m.status = 'active'";
        }
        $sql .= " ORDER BY FIELD(m.role, 'founder', 'admin', 'member', 'viewer'), u.full_name";
        return Database::select($sql, $params);
    }

    public static function adminsFor($familyId)
    {
        return Database::select(
            "SELECT m.user_id, m.role, u.full_name, u.email
               FROM family_members m
               JOIN users u ON u.id = m.user_id
              WHERE m.family_id = ? AND m.status = 'active' AND m.role IN ('founder','admin')",
            [(int) $familyId]
        );
    }

    public static function countActive($familyId)
    {
        return (int) Database::value(
            "SELECT COUNT(*) FROM family_members WHERE family_id = ? AND status = 'active'",
            [(int) $familyId]
        );
    }

    public static function familiesForUser($userId)
    {
        return Database::select(
            "SELECT m.role, m.joined_at, f.*
               FROM family_members m
               JOIN families f ON f.id = m.family_id
              WHERE m.user_id = ? AND m.status = 'active'
              ORDER BY m.joined_at DESC",
            [(int) $userId]
        );
    }
}
