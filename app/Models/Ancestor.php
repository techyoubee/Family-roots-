<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Str;

/**
 * Ancestor records are genealogy persons, not login accounts. A family can hold
 * thousands of them; at most one may be linked to a user account.
 */
final class Ancestor
{
    public static function find($id)
    {
        return Database::first('SELECT * FROM ancestors WHERE id = ?', [(int) $id]);
    }

    /** Family-scoped fetch: prevents cross-family record access by id. */
    public static function findForFamily($familyId, $ancestorId)
    {
        return Database::first(
            'SELECT * FROM ancestors WHERE id = ? AND family_id = ?',
            [(int) $ancestorId, (int) $familyId]
        );
    }

    public static function create(array $data, $familyId, $userId = null)
    {
        $data['family_id'] = (int) $familyId;
        $data['created_by_user_id'] = $userId !== null ? (int) $userId : ($data['created_by_user_id'] ?? null);
        $data['name_normalized'] = Str::normalizeName(($data['given_names'] ?? '') . ' ' . ($data['surname'] ?? ''));
        unset($data['id']);
        $id = Database::insert('ancestors', $data);
        return self::find($id);
    }

    public static function update($id, array $data, $familyId)
    {
        if (isset($data['given_names']) || isset($data['surname'])) {
            $current = self::findForFamily($familyId, $id);
            if (!$current) {
                return 0;
            }
            $given = isset($data['given_names']) ? $data['given_names'] : $current['given_names'];
            $surname = isset($data['surname']) ? $data['surname'] : $current['surname'];
            $data['name_normalized'] = Str::normalizeName($given . ' ' . $surname);
        }
        return Database::update('ancestors', $data, 'id = :id AND family_id = :family', ['id' => (int) $id, 'family' => (int) $familyId]);
    }

    public static function delete($id, $familyId)
    {
        return Database::execute('DELETE FROM ancestors WHERE id = ? AND family_id = ?', [(int) $id, (int) $familyId]);
    }

    public static function countForFamily($familyId)
    {
        return (int) Database::value('SELECT COUNT(*) FROM ancestors WHERE family_id = ?', [(int) $familyId]);
    }

    /**
     * @param array $filters keys: q, generation, branch, status, living, limit, offset
     */
    public static function forFamily($familyId, array $filters = [])
    {
        $sql = 'SELECT * FROM ancestors WHERE family_id = ?';
        $params = [(int) $familyId];

        if (!empty($filters['q'])) {
            $like = '%' . Str::normalizeName($filters['q']) . '%';
            $sql .= ' AND (name_normalized LIKE ? OR alt_names LIKE ? OR birth_place LIKE ?)';
            $params[] = $like;
            $params[] = '%' . $filters['q'] . '%';
            $params[] = '%' . $filters['q'] . '%';
        }
        if (isset($filters['generation']) && $filters['generation'] !== '' && $filters['generation'] !== null) {
            $sql .= ' AND generation = ?';
            $params[] = (int) $filters['generation'];
        }
        if (!empty($filters['branch'])) {
            $sql .= ' AND branch = ?';
            $params[] = $filters['branch'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND record_status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['surname'])) {
            $sql .= ' AND surname LIKE ?';
            $params[] = '%' . $filters['surname'] . '%';
        }

        $sql .= ' ORDER BY generation DESC, surname ASC, given_names ASC';

        $limit = isset($filters['limit']) ? max(1, min(200, (int) $filters['limit'])) : 50;
        $offset = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;
        $sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;

        return Database::select($sql, $params);
    }

    public static function countMatching($familyId, array $filters = [])
    {
        $sql = 'SELECT COUNT(*) FROM ancestors WHERE family_id = ?';
        $params = [(int) $familyId];
        if (!empty($filters['q'])) {
            $sql .= ' AND (name_normalized LIKE ? OR alt_names LIKE ?)';
            $params[] = '%' . Str::normalizeName($filters['q']) . '%';
            $params[] = '%' . $filters['q'] . '%';
        }
        if (isset($filters['generation']) && $filters['generation'] !== '' && $filters['generation'] !== null) {
            $sql .= ' AND generation = ?';
            $params[] = (int) $filters['generation'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND record_status = ?';
            $params[] = $filters['status'];
        }
        return (int) Database::value($sql, $params);
    }

    public static function branches($familyId)
    {
        return Database::select(
            'SELECT branch, COUNT(*) AS total FROM ancestors WHERE family_id = ? AND branch IS NOT NULL AND branch <> "" GROUP BY branch ORDER BY branch',
            [(int) $familyId]
        );
    }

    public static function generationCounts($familyId)
    {
        return Database::select(
            'SELECT generation, COUNT(*) AS total FROM ancestors WHERE family_id = ? GROUP BY generation ORDER BY generation DESC',
            [(int) $familyId]
        );
    }

    public static function statusCounts($familyId)
    {
        return Database::select(
            'SELECT record_status, COUNT(*) AS total FROM ancestors WHERE family_id = ? GROUP BY record_status',
            [(int) $familyId]
        );
    }

    public static function livingCount($familyId)
    {
        return (int) Database::value('SELECT COUNT(*) FROM ancestors WHERE family_id = ? AND is_living = 1', [(int) $familyId]);
    }

    /**
     * Candidate duplicate records: same normalised name, optionally same birth
     * year/place. Duplicates are reported for human review, never merged
     * automatically.
     */
    public static function duplicateCandidates($familyId, $nameNormalized, $excludeId = null)
    {
        $sql = 'SELECT * FROM ancestors WHERE family_id = ? AND name_normalized = ?';
        $params = [(int) $familyId, $nameNormalized];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = (int) $excludeId;
        }
        $sql .= ' ORDER BY id';
        return Database::select($sql, $params);
    }

    /** Same-name records anywhere in the family, using token similarity. */
    public static function probableDuplicates($familyId, $limit = 100)
    {
        $rows = Database::select(
            'SELECT a.id, a.given_names, a.surname, a.birth_date_text, a.death_date_text, a.generation, a.record_status, a.name_normalized
               FROM ancestors a WHERE a.family_id = ? ORDER BY a.name_normalized LIMIT ' . (int) $limit,
            [(int) $familyId]
        );
        $groups = [];
        foreach ($rows as $row) {
            $key = $row['name_normalized'];
            if ($key === null || $key === '') {
                continue;
            }
            $groups[$key][] = $row;
        }
        return array_values(array_filter($groups, static function ($group) {
            return count($group) > 1;
        }));
    }

    public static function searchInFamily($familyId, $term, $limit = 25)
    {
        $normalized = Str::normalizeName($term);
        return Database::select(
            'SELECT * FROM ancestors
              WHERE family_id = ?
                AND (name_normalized LIKE ? OR given_names LIKE ? OR surname LIKE ? OR alt_names LIKE ?)
              ORDER BY generation DESC, surname ASC
              LIMIT ' . (int) $limit,
            [(int) $familyId, '%' . $normalized . '%', '%' . $term . '%', '%' . $term . '%', '%' . $term . '%']
        );
    }

    public static function linkUser($ancestorId, $familyId, $userId)
    {
        return Database::update(
            'ancestors',
            ['linked_user_id' => $userId ? (int) $userId : null],
            'id = :id AND family_id = :family',
            ['id' => (int) $ancestorId, 'family' => (int) $familyId]
        );
    }

    public static function setRecordStatus($ancestorId, $familyId, $status)
    {
        return Database::update(
            'ancestors',
            ['record_status' => $status],
            'id = :id AND family_id = :family',
            ['id' => (int) $ancestorId, 'family' => (int) $familyId]
        );
    }

    public static function displayName(array $ancestor)
    {
        $name = trim(($ancestor['given_names'] ?? '') . ' ' . ($ancestor['surname'] ?? ''));
        return $name === '' ? 'Unnamed record' : $name;
    }

    public static function birthYear(array $ancestor)
    {
        if (!empty($ancestor['birth_date'])) {
            return (int) substr((string) $ancestor['birth_date'], 0, 4);
        }
        if (!empty($ancestor['birth_date_text']) && preg_match('/(1[5-9]\d\d|20\d\d)/', (string) $ancestor['birth_date_text'], $m)) {
            return (int) $m[1];
        }
        return null;
    }

    public static function deathYear(array $ancestor)
    {
        if (!empty($ancestor['death_date'])) {
            return (int) substr((string) $ancestor['death_date'], 0, 4);
        }
        if (!empty($ancestor['death_date_text']) && preg_match('/(1[5-9]\d\d|20\d\d)/', (string) $ancestor['death_date_text'], $m)) {
            return (int) $m[1];
        }
        return null;
    }
}
