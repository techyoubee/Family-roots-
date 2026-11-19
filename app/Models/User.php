<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Str;

final class User
{
    public static function find($id)
    {
        return Database::first('SELECT * FROM users WHERE id = ?', [(int) $id]);
    }

    public static function findByPublicId($publicId)
    {
        return Database::first('SELECT * FROM users WHERE public_id = ?', [(string) $publicId]);
    }

    public static function findByEmail($email)
    {
        return Database::first('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim((string) $email))]);
    }

    public static function emailExists($email, $exceptId = null)
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = ?';
        $params = [mb_strtolower(trim((string) $email))];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = (int) $exceptId;
        }
        return ((int) Database::value($sql, $params)) > 0;
    }

    /**
     * @param array $data email, password_hash, full_name, ...
     * @return array created user row
     */
    public static function create(array $data)
    {
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $data['public_id'] = $data['public_id'] ?? Str::uuid4();
        $data['email'] = $email;
        $data['status'] = $data['status'] ?? 'pending';
        $data['full_name'] = trim((string) ($data['full_name'] ?? ''));
        unset($data['id'], $data['password'], $data['password_confirmation']);

        $id = Database::insert('users', $data);
        return self::find($id);
    }

    public static function updateProfile($id, array $data)
    {
        if (isset($data['email'])) {
            $data['email'] = mb_strtolower(trim((string) $data['email']));
        }
        return Database::update('users', $data, 'id = :id', ['id' => (int) $id]);
    }

    public static function setPassword($id, $passwordHash)
    {
        return Database::update('users', ['password_hash' => $passwordHash], 'id = :id', ['id' => (int) $id]);
    }

    public static function markEmailVerified($id)
    {
        return Database::update('users', [
            'email_verified_at' => date('Y-m-d H:i:s'),
            'status'            => 'active',
        ], 'id = :id', ['id' => (int) $id]);
    }

    public static function recordSuccessfulLogin($id)
    {
        return Database::update('users', [
            'last_login_at'      => date('Y-m-d H:i:s'),
            'failed_login_count' => 0,
            'locked_until'       => null,
        ], 'id = :id', ['id' => (int) $id]);
    }

    public static function recordFailedLogin($id, $lockUntil = null)
    {
        Database::execute('UPDATE users SET failed_login_count = failed_login_count + 1 WHERE id = ?', [(int) $id]);
        if ($lockUntil !== null) {
            Database::update('users', ['locked_until' => $lockUntil], 'id = :id', ['id' => (int) $id]);
        }
    }

    public static function isLocked(array $user)
    {
        return !empty($user['locked_until']) && strtotime($user['locked_until']) > time();
    }

    public static function search($term, $limit = 20)
    {
        return Database::select(
            'SELECT id, public_id, email, full_name, status, created_at
               FROM users
              WHERE full_name LIKE ? OR email LIKE ?
              ORDER BY created_at DESC
              LIMIT ' . (int) $limit,
            ['%' . $term . '%', '%' . $term . '%']
        );
    }
}
