<?php

namespace App\Models;

use App\Core\Database;

/**
 * Parent/child and spouse edges.
 *
 * Every write is validated here so that:
 *  - both persons belong to the same family as the edge;
 *  - a person cannot be their own parent/direct descendant (no cycles);
 *  - duplicate edges are rejected by a unique index and a friendly check;
 *  - a relationship's status (confirmed / proposed / disputed) is explicit;
 *  - an unknown relationship type stays 'unknown' - nothing is invented.
 */
final class FamilyRelationship
{
    /** @return string|null error message when invalid, null when acceptable */
    public static function validate($familyId, $kind, $fromId, $toId, $excludeId = null)
    {
        if (!in_array($kind, ['parent_of', 'spouse_of'], true)) {
            return 'Unsupported relationship kind.';
        }
        if ((int) $fromId === (int) $toId) {
            return 'A person cannot be related to themselves.';
        }
        $from = Ancestor::findForFamily($familyId, $fromId);
        $to = Ancestor::findForFamily($familyId, $toId);
        if (!$from || !$to) {
            return 'Both people must already exist in this family tree.';
        }
        if (self::edgeExists($familyId, $kind, $fromId, $toId)) {
            return 'That relationship is already recorded.';
        }
        if ($kind === 'parent_of' && self::wouldCreateCycle($familyId, $fromId, $toId)) {
            return 'That link would create an impossible ancestry loop.';
        }
        return null;
    }

    public static function edgeExists($familyId, $kind, $fromId, $toId)
    {
        return ((int) Database::value(
            'SELECT COUNT(*) FROM family_relationships
              WHERE family_id = ? AND relationship_kind = ? AND from_ancestor_id = ? AND to_ancestor_id = ?',
            [(int) $familyId, $kind, (int) $fromId, (int) $toId]
        )) > 0;
    }

    public static function create(array $data)
    {
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        $data['updated_at'] = $data['updated_at'] ?? date('Y-m-d H:i:s');
        if (!isset($data['status'])) {
            $data['status'] = 'proposed';
        }
        if (!isset($data['relationship_type'])) {
            $data['relationship_type'] = 'unknown';
        }
        unset($data['id']);
        $id = Database::insert('family_relationships', $data);
        return self::find($id);
    }

    public static function find($id)
    {
        return Database::first('SELECT * FROM family_relationships WHERE id = ?', [(int) $id]);
    }

    public static function findForFamily($familyId, $id)
    {
        return Database::first(
            'SELECT * FROM family_relationships WHERE id = ? AND family_id = ?',
            [(int) $id, (int) $familyId]
        );
    }

    public static function update($id, $familyId, array $data)
    {
        return Database::update(
            'family_relationships',
            $data,
            'id = :id AND family_id = :family',
            ['id' => (int) $id, 'family' => (int) $familyId]
        );
    }

    public static function delete($id, $familyId)
    {
        return Database::execute(
            'DELETE FROM family_relationships WHERE id = ? AND family_id = ?',
            [(int) $id, (int) $familyId]
        );
    }

    public static function forFamily($familyId, $kind = null)
    {
        $sql = 'SELECT r.*,
                       pf.given_names AS from_given, pf.surname AS from_surname, pf.generation AS from_generation,
                       pt.given_names AS to_given, pt.surname AS to_surname, pt.generation AS to_generation
                  FROM family_relationships r
                  JOIN ancestors pf ON pf.id = r.from_ancestor_id
                  JOIN ancestors pt ON pt.id = r.to_ancestor_id
                 WHERE r.family_id = ?';
        $params = [(int) $familyId];
        if ($kind !== null) {
            $sql .= ' AND r.relationship_kind = ?';
            $params[] = $kind;
        }
        $sql .= ' ORDER BY r.relationship_kind, r.id';
        return Database::select($sql, $params);
    }

    public static function countForFamily($familyId)
    {
        return (int) Database::value('SELECT COUNT(*) FROM family_relationships WHERE family_id = ?', [(int) $familyId]);
    }

    public static function parentsOf($ancestorId)
    {
        return Database::select(
            'SELECT r.parent_role, r.relationship_type, r.status AS relationship_status, a.*
               FROM family_relationships r
               JOIN ancestors a ON a.id = r.from_ancestor_id
              WHERE r.relationship_kind = "parent_of" AND r.to_ancestor_id = ?
              ORDER BY FIELD(r.parent_role, "father", "mother", "parent", "unknown")',
            [(int) $ancestorId]
        );
    }

    public static function childrenOf($ancestorId, $kind = 'parent_of')
    {
        return Database::select(
            'SELECT r.parent_role, r.relationship_type, r.status AS relationship_status, a.*
               FROM family_relationships r
               JOIN ancestors a ON a.id = r.to_ancestor_id
              WHERE r.relationship_kind = ? AND r.from_ancestor_id = ?
              ORDER BY a.generation DESC, a.birth_date_text',
            [$kind, (int) $ancestorId]
        );
    }

    public static function spousesOf($ancestorId)
    {
        return Database::select(
            'SELECT r.status AS relationship_status, r.relationship_type, a.*
               FROM family_relationships r
               JOIN ancestors a ON a.id = CASE WHEN r.from_ancestor_id = ? THEN r.to_ancestor_id ELSE r.from_ancestor_id END
              WHERE r.relationship_kind = "spouse_of" AND (r.from_ancestor_id = ? OR r.to_ancestor_id = ?)',
            [(int) $ancestorId, (int) $ancestorId, (int) $ancestorId]
        );
    }

    public static function siblingsOf($ancestorId)
    {
        $parents = self::parentsOf($ancestorId);
        $siblings = [];
        foreach ($parents as $parent) {
            foreach (self::childrenOf($parent['id']) as $child) {
                if ((int) $child['id'] !== (int) $ancestorId) {
                    $siblings[(int) $child['id']] = $child;
                }
            }
        }
        return array_values($siblings);
    }

    /**
     * Ancestors of a person, breadth-first, with the generation distance.
     * @return array<int,int> ancestorId => distance (1 = parent)
     */
    public static function ancestorsOf($ancestorId, $maxDepth = 5, $statuses = null)
    {
        $result = [];
        $frontier = [(int) $ancestorId];
        $depth = 0;
        $visited = [(int) $ancestorId => true];

        while ($depth < $maxDepth && !empty($frontier)) {
            $depth++;
            $next = [];
            foreach ($frontier as $id) {
                foreach (self::parentsOf($id) as $parent) {
                    if ($statuses !== null && !in_array($parent['relationship_status'], $statuses, true)) {
                        continue;
                    }
                    $pid = (int) $parent['id'];
                    if (!isset($result[$pid])) {
                        $result[$pid] = $depth;
                    }
                    if (!isset($visited[$pid]) && $depth < $maxDepth) {
                        $visited[$pid] = true;
                        $next[] = $pid;
                    }
                }
            }
            $frontier = $next;
        }
        return $result;
    }

    /** @return array<int,int> descendantId => distance */
    public static function descendantsOf($ancestorId, $maxDepth = 10)
    {
        $result = [];
        $frontier = [(int) $ancestorId];
        $depth = 0;
        $visited = [(int) $ancestorId => true];
        while ($depth < $maxDepth && !empty($frontier)) {
            $depth++;
            $next = [];
            foreach ($frontier as $id) {
                foreach (self::childrenOf($id) as $child) {
                    $cid = (int) $child['id'];
                    if (!isset($result[$cid])) {
                        $result[$cid] = $depth;
                    }
                    if (!isset($visited[$cid])) {
                        $visited[$cid] = true;
                        $next[] = $cid;
                    }
                }
            }
            $frontier = $next;
        }
        return $result;
    }

    /**
     * A parent link from $parentId to $childId is invalid when $parentId is
     * already a descendant of $childId (that would close a loop).
     */
    public static function wouldCreateCycle($familyId, $parentId, $childId)
    {
        $parentId = (int) $parentId;
        $stack = [(int) $childId];
        $visited = [];
        while (!empty($stack)) {
            $current = array_pop($stack);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            if ($current === $parentId) {
                return true;
            }
            foreach (Database::select(
                'SELECT to_ancestor_id FROM family_relationships WHERE family_id = ? AND relationship_kind = "parent_of" AND from_ancestor_id = ?',
                [(int) $familyId, $current]
            ) as $row) {
                $stack[] = (int) $row['to_ancestor_id'];
            }
        }
        return false;
    }

    /**
     * Structural integrity report used by the administrator dashboard. Any
     * reported problem is surfaced for human review - nothing is auto-fixed.
     * @return array<int,array>
     */
    public static function integrityIssues($familyId)
    {
        $issues = [];

        $crossFamily = Database::select(
            'SELECT r.id FROM family_relationships r
              JOIN ancestors a ON a.id = r.from_ancestor_id
              JOIN ancestors b ON b.id = r.to_ancestor_id
             WHERE r.family_id = ? AND (a.family_id <> r.family_id OR b.family_id <> r.family_id)',
            [(int) $familyId]
        );
        foreach ($crossFamily as $row) {
            $issues[] = ['type' => 'cross_family_edge', 'relationship_id' => (int) $row['id'], 'message' => 'Relationship links people outside this family.'];
        }

        foreach (self::forFamily($familyId, 'parent_of') as $edge) {
            if (self::wouldCreateCycleExcluding($familyId, (int) $edge['from_ancestor_id'], (int) $edge['to_ancestor_id'], (int) $edge['id'])) {
                $issues[] = [
                    'type'            => 'cycle',
                    'relationship_id' => (int) $edge['id'],
                    'message'         => self::label($edge, 'from') . ' and ' . self::label($edge, 'to') . ' form an ancestry loop.',
                ];
            }
        }

        $orphanParent = Database::select(
            'SELECT a.id, a.given_names, a.surname FROM ancestors a
              WHERE a.family_id = ?
                AND NOT EXISTS (SELECT 1 FROM family_relationships r WHERE r.from_ancestor_id = a.id OR r.to_ancestor_id = a.id)
                AND (SELECT COUNT(*) FROM ancestors b WHERE b.family_id = a.family_id) > 1',
            [(int) $familyId]
        );
        foreach ($orphanParent as $row) {
            $issues[] = [
                'type'      => 'unconnected_person',
                'ancestor_id' => (int) $row['id'],
                'message'   => trim($row['given_names'] . ' ' . $row['surname']) . ' has no recorded relationships yet.',
            ];
        }

        return $issues;
    }

    private static function wouldCreateCycleExcluding($familyId, $parentId, $childId, $excludeRelationshipId)
    {
        $stack = [(int) $childId];
        $visited = [];
        while (!empty($stack)) {
            $current = array_pop($stack);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            if ($current !== (int) $childId && $current === (int) $parentId) {
                return true;
            }
            foreach (Database::select(
                'SELECT to_ancestor_id FROM family_relationships
                  WHERE family_id = ? AND relationship_kind = "parent_of" AND from_ancestor_id = ? AND id <> ?',
                [(int) $familyId, $current, (int) $excludeRelationshipId]
            ) as $row) {
                $stack[] = (int) $row['to_ancestor_id'];
            }
        }
        return false;
    }

    private static function label(array $edge, $side)
    {
        $given = $edge[$side . '_given'] ?? '';
        $surname = $edge[$side . '_surname'] ?? '';
        return trim($given . ' ' . $surname);
    }
}
