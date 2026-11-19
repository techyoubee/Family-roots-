<?php

namespace App\Services;

use App\Models\Ancestor;

/**
 * Read-only ancestry graph calculations for the Ancestral Journey.
 *
 * The calculator works on rows that have already been loaded in this request
 * (one bounded query per table). Nothing here touches the database, which
 * keeps the HTML pages and the JSON endpoint consistent and fast.
 *
 * Rules:
 *  - only recorded parent edges create ancestry. A surname, a birth year or a
 *    shared place never implies a parent - nothing is invented;
 *  - every person and connection keeps its recorded status, surfaced as
 *    verified / proposed / unknown / disputed;
 *  - an ancestor reached through more than one lineage is reported as a
 *    pedigree-collapse duplicate instead of being drawn as two different
 *    people.
 */
final class AncestryCalculator
{
    public const VERIFIED = 'verified';
    public const PROPOSED = 'proposed';
    public const DISPUTED = 'disputed';
    public const UNKNOWN  = 'unknown';

    /**
     * Four ancestor steps plus the focus person = the platform's five
     * generations (you, parents, grandparents, great-grandparents,
     * 2x great-grandparents).
     */
    public const MAX_DEPTH = 4;

    /** Hard cap so a malformed graph cannot explode path counting. */
    private const PATH_CAP = 64;

    /**
     * Index people and edges.
     *
     * @param array $ancestors ancestor rows (must include id)
     * @param array $edges     family_relationships rows
     * @return array{people:array,parents:array,children:array,spouses:array}
     */
    public static function buildIndex(array $ancestors, array $edges)
    {
        $people = [];
        foreach ($ancestors as $ancestor) {
            if (!empty($ancestor['id'])) {
                $people[(int) $ancestor['id']] = $ancestor;
            }
        }

        $parents = [];
        $children = [];
        $spouses = [];

        foreach ($edges as $edge) {
            $fromId = (int) ($edge['from_ancestor_id'] ?? 0);
            $toId = (int) ($edge['to_ancestor_id'] ?? 0);
            if ($fromId === 0 || $toId === 0 || !isset($people[$fromId]) || !isset($people[$toId])) {
                continue;
            }

            $kind = isset($edge['relationship_kind']) ? (string) $edge['relationship_kind'] : 'parent_of';
            $status = self::normaliseStatus($edge['status'] ?? null);
            $type = isset($edge['relationship_type']) ? (string) $edge['relationship_type'] : 'unknown';
            $unionYear = null;
            if (isset($edge['union_year']) && $edge['union_year'] !== null && $edge['union_year'] !== '') {
                $unionYear = (int) $edge['union_year'];
            }

            if ($kind === 'parent_of') {
                $parents[$toId][] = [
                    'relationship_id' => isset($edge['id']) ? (int) $edge['id'] : null,
                    'status'          => $status,
                    'type'            => $type,
                    'role'            => isset($edge['parent_role']) ? (string) $edge['parent_role'] : 'unknown',
                    'union_year'      => $unionYear,
                    'person'          => $people[$fromId],
                ];
                $children[$fromId][] = [
                    'relationship_id' => isset($edge['id']) ? (int) $edge['id'] : null,
                    'status'          => $status,
                    'type'            => $type,
                    'person'          => $people[$toId],
                ];
            } elseif ($kind === 'spouse_of') {
                $link = [
                    'relationship_id' => isset($edge['id']) ? (int) $edge['id'] : null,
                    'status'          => $status,
                    'type'            => $type,
                    'union_year'      => $unionYear,
                ];
                $spouses[$fromId][] = $link + ['person' => $people[$toId]];
                $spouses[$toId][] = $link + ['person' => $people[$fromId]];
            }
        }

        $roleOrder = ['father' => 0, 'mother' => 1, 'parent' => 2, 'unknown' => 3];
        foreach ($parents as $childId => $links) {
            usort($links, static function ($a, $b) use ($roleOrder) {
                $ra = $roleOrder[$a['role']] ?? 9;
                $rb = $roleOrder[$b['role']] ?? 9;
                if ($ra !== $rb) {
                    return $ra <=> $rb;
                }
                return ((int) $a['person']['id']) <=> ((int) $b['person']['id']);
            });
            $parents[$childId] = $links;
        }

        return [
            'people'   => $people,
            'parents'  => $parents,
            'children' => $children,
            'spouses'  => $spouses,
        ];
    }

    /**
     * Walk parent edges upwards from one person, level by level.
     *
     * @return array root_id, max_depth, nodes, levels, parent_ids, pedigree_collapse
     */
    public static function journey(array $index, $rootId, $maxDepth = self::MAX_DEPTH)
    {
        $rootId = (int) $rootId;
        $maxDepth = max(1, min((int) $maxDepth, self::MAX_DEPTH));

        if ($rootId === 0 || !isset($index['people'][$rootId])) {
            return self::emptyJourney($maxDepth);
        }

        $levels = [0 => [$rootId]];
        $depthOf = [$rootId => 0];
        $multiplicity = [$rootId => 1];
        $incoming = [$rootId => []];
        $parentIds = [];

        $frontier = [$rootId];
        for ($depth = 0; $depth < $maxDepth && !empty($frontier); $depth++) {
            $next = [];
            foreach ($frontier as $childId) {
                $links = isset($index['parents'][$childId]) ? $index['parents'][$childId] : [];
                foreach ($links as $link) {
                    $parentId = (int) $link['person']['id'];
                    $parentIds[$childId][$parentId] = true;
                    $incoming[$parentId][] = $link['status'];

                    // Every distinct lineage contributes one path. If the same
                    // ancestor is reached twice this value becomes > 1 and the
                    // person is reported as pedigree collapse.
                    $weight = isset($multiplicity[$childId]) ? $multiplicity[$childId] : 1;
                    $current = isset($multiplicity[$parentId]) ? $multiplicity[$parentId] : 0;
                    $multiplicity[$parentId] = min(self::PATH_CAP, $current + $weight);

                    if (!isset($depthOf[$parentId])) {
                        $depthOf[$parentId] = $depth + 1;
                        $levels[$depth + 1][] = $parentId;
                        $next[] = $parentId;
                    }
                }
            }
            $frontier = $next;
        }

        $nodes = [];
        foreach ($depthOf as $personId => $level) {
            $person = $index['people'][$personId];
            $statuses = isset($incoming[$personId]) ? $incoming[$personId] : [];
            $nodes[$personId] = [
                'id'            => (int) $personId,
                'level'         => (int) $level,
                'person'        => $person,
                'name'          => Ancestor::displayName($person),
                'birth_year'    => Ancestor::birthYear($person),
                'death_year'    => Ancestor::deathYear($person),
                'living'        => (int) ($person['is_living'] ?? 0) === 1,
                'sex'           => isset($person['sex']) ? (string) $person['sex'] : 'unknown',
                'generation'    => isset($person['generation']) ? (int) $person['generation'] : 0,
                'branch'        => !empty($person['branch']) ? (string) $person['branch'] : null,
                'record_status' => self::normaliseRecordStatus($person),
                'certainty'     => self::classify($person, $statuses),
                'multiplicity'  => isset($multiplicity[$personId]) ? (int) $multiplicity[$personId] : 1,
                'collapsed'     => isset($multiplicity[$personId]) && $multiplicity[$personId] > 1,
                'parents'       => [],
                'spouses'       => [],
            ];
        }

        // Parent references, limited to people who made it into the graph.
        foreach ($nodes as $personId => $node) {
            if (!isset($parentIds[$personId])) {
                continue;
            }
            foreach (array_keys($parentIds[$personId]) as $parentId) {
                if (!isset($nodes[$parentId])) {
                    continue;
                }
                $nodes[$personId]['parents'][] = [
                    'id'        => (int) $parentId,
                    'certainty' => $nodes[$parentId]['certainty'],
                ];
            }
            usort($nodes[$personId]['parents'], static function ($a, $b) {
                return $a['id'] <=> $b['id'];
            });
        }

        // Spouse references (only people already in the graph), de-duplicated.
        foreach ($nodes as $personId => $node) {
            $seen = [];
            $links = isset($index['spouses'][$personId]) ? $index['spouses'][$personId] : [];
            foreach ($links as $link) {
                $spouseId = (int) $link['person']['id'];
                if (!isset($nodes[$spouseId]) || isset($seen[$spouseId])) {
                    continue;
                }
                $seen[$spouseId] = true;
                $nodes[$personId]['spouses'][] = [
                    'id'         => $spouseId,
                    'status'     => $link['status'],
                    'type'       => $link['type'],
                    'union_year' => $link['union_year'],
                ];
            }
        }

        // Stable, readable order inside each generation (oldest birth first).
        $maxLevel = count($levels) - 1;
        for ($level = 1; $level <= $maxLevel; $level++) {
            if (empty($levels[$level])) {
                continue;
            }
            usort($levels[$level], static function ($a, $b) use ($nodes) {
                $na = $nodes[$a];
                $nb = $nodes[$b];
                if ($na['birth_year'] !== $nb['birth_year']) {
                    if ($na['birth_year'] === null) {
                        return 1;
                    }
                    if ($nb['birth_year'] === null) {
                        return -1;
                    }
                    return $na['birth_year'] <=> $nb['birth_year'];
                }
                return strcasecmp($na['name'], $nb['name']);
            });
        }

        return [
            'root_id'           => $rootId,
            'max_depth'         => $maxDepth,
            'nodes'             => $nodes,
            'levels'            => $levels,
            'parent_ids'        => $parentIds,
            'pedigree_collapse' => self::collapseReport($nodes),
        ];
    }

    /**
     * Certainty of one person, combining their own record status with the
     * status of every recorded link that connects them to the previous
     * generation. Disputed wins over everything; a confirmed record only
     * becomes "verified" when its connections are confirmed too.
     *
     * @param array $person
     * @param array $incomingStatuses
     * @return string
     */
    public static function classify(array $person, array $incomingStatuses)
    {
        $record = self::normaliseRecordStatus($person);
        $statuses = [];
        foreach ($incomingStatuses as $status) {
            $statuses[] = self::normaliseStatus($status);
        }

        if ($record === 'disputed' || in_array('disputed', $statuses, true)) {
            return self::DISPUTED;
        }
        if ($record === 'unknown' || in_array('unknown', $statuses, true)) {
            return self::UNKNOWN;
        }
        if ($record === 'confirmed' && !in_array('proposed', $statuses, true)) {
            return self::VERIFIED;
        }
        return self::PROPOSED;
    }

    /**
     * Generational layout for the SVG tree. Positions are returned as numbers
     * so JavaScript only has to draw them.
     */
    public static function layout(array $journey)
    {
        $levels = isset($journey['levels']) ? $journey['levels'] : [];
        $nodes = isset($journey['nodes']) ? $journey['nodes'] : [];
        if (empty($nodes) || empty($levels)) {
            return ['width' => 0, 'height' => 0, 'nodes' => [], 'edges' => [], 'levels' => []];
        }

        $nodeWidth = 176;
        $nodeHeight = 68;
        $hGap = 26;
        $vGap = 96;
        $padding = 40;

        $maxLevel = 0;
        foreach (array_keys($levels) as $level) {
            $maxLevel = max($maxLevel, (int) $level);
        }

        // Order parents under their children to reduce crossing lines.
        $ordered = [0 => array_values($levels[0])];
        $position = [];
        foreach ($ordered[0] as $index => $id) {
            $position[$id] = $index;
        }

        for ($level = 1; $level <= $maxLevel; $level++) {
            $row = [];
            $seen = [];
            $previous = isset($ordered[$level - 1]) ? $ordered[$level - 1] : [];
            foreach ($previous as $childId) {
                if (empty($nodes[$childId]['parents'])) {
                    continue;
                }
                $parents = $nodes[$childId]['parents'];
                usort($parents, static function ($a, $b) use ($position) {
                    $pa = isset($position[$a['id']]) ? $position[$a['id']] : PHP_INT_MAX;
                    $pb = isset($position[$b['id']]) ? $position[$b['id']] : PHP_INT_MAX;
                    if ($pa !== $pb) {
                        return $pa <=> $pb;
                    }
                    return $a['id'] <=> $b['id'];
                });
                foreach ($parents as $parent) {
                    $parentId = (int) $parent['id'];
                    if (!isset($nodes[$parentId]) || (int) $nodes[$parentId]['level'] !== $level || isset($seen[$parentId])) {
                        continue;
                    }
                    $seen[$parentId] = true;
                    $row[] = $parentId;
                }
            }
            foreach ($levels[$level] as $id) {
                if (!isset($seen[$id])) {
                    $seen[$id] = true;
                    $row[] = $id;
                }
            }
            $ordered[$level] = $row;
            foreach ($row as $index => $id) {
                $position[$id] = $index;
            }
        }

        $maxRow = 1;
        foreach ($ordered as $row) {
            $maxRow = max($maxRow, count($row));
        }
        $width = max(640, $maxRow * $nodeWidth + max(0, $maxRow - 1) * $hGap + $padding * 2);
        $height = ($maxLevel + 1) * $nodeHeight + $maxLevel * $vGap + $padding * 2;

        $layoutNodes = [];
        $layoutLevels = [];
        foreach ($ordered as $level => $ids) {
            $count = count($ids);
            $rowWidth = $count * $nodeWidth + max(0, $count - 1) * $hGap;
            $startX = ($width - $rowWidth) / 2;
            $y = $padding + ($maxLevel - $level) * ($nodeHeight + $vGap);
            $layoutLevels[$level] = [];
            foreach ($ids as $index => $id) {
                $x = $startX + $index * ($nodeWidth + $hGap);
                $layoutLevels[$level][] = (int) $id;
                $layoutNodes[$id] = [
                    'id'       => (int) $id,
                    'level'    => (int) $level,
                    'x'        => round($x, 1),
                    'y'        => round($y, 1),
                    'width'    => $nodeWidth,
                    'height'   => $nodeHeight,
                    'center_x' => round($x + $nodeWidth / 2, 1),
                    'center_y' => round($y + $nodeHeight / 2, 1),
                ];
            }
        }

        $edges = [];
        foreach ($nodes as $id => $node) {
            if (!isset($layoutNodes[$id])) {
                continue;
            }
            foreach ($node['parents'] as $parent) {
                $parentId = (int) $parent['id'];
                if (!isset($layoutNodes[$parentId])) {
                    continue;
                }
                // Only draw a line between adjacent generations. When the same
                // person is both a parent and a more distant ancestor (pedigree
                // collapse) the shared-lineage marker explains it without an
                // ambiguous cross-level line.
                if ((int) $nodes[$parentId]['level'] !== (int) $node['level'] + 1) {
                    continue;
                }
                $edges[] = [
                    'from'      => $parentId,
                    'to'        => (int) $id,
                    'kind'      => 'parent',
                    'certainty' => $parent['certainty'],
                    'from_x'    => $layoutNodes[$parentId]['center_x'],
                    'from_y'    => $layoutNodes[$parentId]['y'] + $nodeHeight,
                    'to_x'      => $layoutNodes[$id]['center_x'],
                    'to_y'      => $layoutNodes[$id]['y'],
                ];
            }
        }

        // Spouse/union links drawn as horizontal dashed connectors.
        $drawnPairs = [];
        foreach ($nodes as $id => $node) {
            foreach ($node['spouses'] as $spouse) {
                $spouseId = (int) $spouse['id'];
                if (!isset($layoutNodes[$spouseId])) {
                    continue;
                }
                if ((int) $nodes[$spouseId]['level'] !== (int) $node['level']) {
                    continue;
                }
                $key = min((int) $id, $spouseId) . ':' . max((int) $id, $spouseId);
                if (isset($drawnPairs[$key])) {
                    continue;
                }
                $drawnPairs[$key] = true;
                $edges[] = [
                    'from'      => (int) $id,
                    'to'        => $spouseId,
                    'kind'      => 'spouse',
                    'certainty' => self::classify(['record_status' => $spouse['status']], []),
                    'from_x'    => $layoutNodes[$id]['center_x'],
                    'from_y'    => $layoutNodes[$id]['center_y'],
                    'to_x'      => $layoutNodes[$spouseId]['center_x'],
                    'to_y'      => $layoutNodes[$spouseId]['center_y'],
                ];
            }
        }

        return [
            'width'  => round($width, 1),
            'height' => round($height, 1),
            'nodes'  => array_values($layoutNodes),
            'edges'  => $edges,
            'levels' => $layoutLevels,
        ];
    }

    /**
     * Birth, union and death events in chronological order. People without a
     * usable year are counted separately instead of being given a made-up date.
     */
    public static function timeline(array $journey, $limit = 250)
    {
        $nodes = isset($journey['nodes']) ? $journey['nodes'] : [];
        $events = [];
        $undated = 0;

        foreach ($nodes as $node) {
            $person = $node['person'];
            if ($node['birth_year'] !== null) {
                $events[] = [
                    'type'        => 'birth',
                    'year'        => (int) $node['birth_year'],
                    'title'       => 'Born',
                    'person_id'   => (int) $node['id'],
                    'person_name' => $node['name'],
                    'place'       => (string) ($person['birth_place'] ?? ''),
                    'detail'      => (string) ($person['birth_date_text'] ?? ''),
                    'certainty'   => $node['certainty'],
                    'status'      => $node['record_status'],
                ];
            } else {
                $undated++;
            }

            if ($node['death_year'] !== null) {
                $events[] = [
                    'type'        => 'death',
                    'year'        => (int) $node['death_year'],
                    'title'       => 'Died',
                    'person_id'   => (int) $node['id'],
                    'person_name' => $node['name'],
                    'place'       => (string) ($person['death_place'] ?? ''),
                    'detail'      => (string) ($person['death_date_text'] ?? ''),
                    'certainty'   => $node['certainty'],
                    'status'      => $node['record_status'],
                ];
            }
        }

        // Union events: only once per pair, and only when a year was recorded.
        $seenUnions = [];
        foreach ($nodes as $node) {
            foreach ($node['spouses'] as $spouse) {
                $spouseId = (int) $spouse['id'];
                if ($node['id'] > $spouseId) {
                    continue;
                }
                if ($spouse['union_year'] === null) {
                    continue;
                }
                $key = $node['id'] . ':' . $spouseId;
                if (isset($seenUnions[$key])) {
                    continue;
                }
                $seenUnions[$key] = true;
                $spouseName = isset($nodes[$spouseId]) ? $nodes[$spouseId]['name'] : 'Unknown';
                $events[] = [
                    'type'        => 'union',
                    'year'        => (int) $spouse['union_year'],
                    'title'       => 'Union',
                    'person_id'   => null,
                    'person_name' => $node['name'] . ' & ' . $spouseName,
                    'place'       => '',
                    'detail'      => '',
                    'certainty'   => self::classify(['record_status' => $spouse['status']], []),
                    'status'      => $spouse['status'],
                ];
            }
        }

        $typeOrder = ['birth' => 0, 'union' => 1, 'death' => 2];
        usort($events, static function ($a, $b) use ($typeOrder) {
            if ($a['year'] !== $b['year']) {
                return $a['year'] <=> $b['year'];
            }
            $ta = isset($typeOrder[$a['type']]) ? $typeOrder[$a['type']] : 9;
            $tb = isset($typeOrder[$b['type']]) ? $typeOrder[$b['type']] : 9;
            if ($ta !== $tb) {
                return $ta <=> $tb;
            }
            return strcasecmp($a['person_name'], $b['person_name']);
        });

        $total = count($events);
        $minYear = null;
        $maxYear = null;
        foreach ($events as $event) {
            if ($minYear === null || $event['year'] < $minYear) {
                $minYear = $event['year'];
            }
            if ($maxYear === null || $event['year'] > $maxYear) {
                $maxYear = $event['year'];
            }
        }

        return [
            'events'    => array_slice($events, 0, max(1, (int) $limit)),
            'total'     => $total,
            'truncated' => $total > $limit,
            'undated'   => $undated,
            'min_year'  => $minYear,
            'max_year'  => $maxYear,
        ];
    }

    /** Aggregate counters used by the intro, tree and timeline pages. */
    public static function stats(array $journey)
    {
        $nodes = isset($journey['nodes']) ? $journey['nodes'] : [];
        $counts = [self::VERIFIED => 0, self::PROPOSED => 0, self::UNKNOWN => 0, self::DISPUTED => 0];
        $branches = [];
        $living = 0;
        $oldest = null;
        $newest = null;
        $maxLevel = 0;

        foreach ($nodes as $node) {
            $certainty = isset($counts[$node['certainty']]) ? $node['certainty'] : self::UNKNOWN;
            $counts[$certainty]++;
            if (!empty($node['branch'])) {
                $branches[(string) $node['branch']] = true;
            }
            if (!empty($node['living'])) {
                $living++;
            }
            if ($node['birth_year'] !== null) {
                if ($oldest === null || $node['birth_year'] < $oldest) {
                    $oldest = (int) $node['birth_year'];
                }
                if ($newest === null || $node['birth_year'] > $newest) {
                    $newest = (int) $node['birth_year'];
                }
            }
            $maxLevel = max($maxLevel, (int) $node['level']);
        }

        // Parent slots that the family has not recorded yet. Only counted for
        // generations that were not cut off by the depth limit.
        $missingParentLinks = 0;
        foreach ($nodes as $node) {
            if ((int) $node['level'] >= $maxLevel) {
                continue;
            }
            $recorded = count($node['parents']);
            if ($recorded < 2) {
                $missingParentLinks += 2 - $recorded;
            }
        }

        $collapse = isset($journey['pedigree_collapse']) ? $journey['pedigree_collapse'] : ['count' => 0, 'repeat_appearances' => 0, 'people' => []];
        $branchList = array_keys($branches);
        sort($branchList, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'total'                => count($nodes),
            'generations'          => $maxLevel + 1,
            'verified'             => $counts[self::VERIFIED],
            'proposed'             => $counts[self::PROPOSED],
            'unknown'              => $counts[self::UNKNOWN],
            'disputed'             => $counts[self::DISPUTED],
            'living'               => $living,
            'branches'             => count($branchList),
            'branch_list'          => $branchList,
            'oldest_birth_year'    => $oldest,
            'newest_birth_year'    => $newest,
            'missing_parent_links' => $missingParentLinks,
            'pedigree_collapse'    => (int) $collapse['count'],
            'repeat_appearances'   => (int) $collapse['repeat_appearances'],
            'shared_ancestors'     => isset($collapse['people']) ? $collapse['people'] : [],
        ];
    }

    private static function collapseReport(array $nodes)
    {
        $people = [];
        $extras = 0;
        foreach ($nodes as $node) {
            $extra = max(0, (int) $node['multiplicity'] - 1);
            $extras += $extra;
            if ($extra > 0) {
                $people[] = [
                    'id'           => (int) $node['id'],
                    'name'         => $node['name'],
                    'multiplicity' => (int) $node['multiplicity'],
                    'level'        => (int) $node['level'],
                    'certainty'    => $node['certainty'],
                ];
            }
        }
        usort($people, static function ($a, $b) {
            if ($a['multiplicity'] !== $b['multiplicity']) {
                return $b['multiplicity'] <=> $a['multiplicity'];
            }
            return $a['level'] <=> $b['level'];
        });

        return [
            'count'              => count($people),
            'repeat_appearances' => $extras,
            'people'             => $people,
        ];
    }

    private static function emptyJourney($maxDepth)
    {
        return [
            'root_id'           => null,
            'max_depth'         => (int) $maxDepth,
            'nodes'             => [],
            'levels'            => [],
            'parent_ids'        => [],
            'pedigree_collapse' => ['count' => 0, 'repeat_appearances' => 0, 'people' => []],
        ];
    }

    private static function normaliseStatus($status)
    {
        $status = strtolower(trim((string) $status));
        return in_array($status, ['confirmed', 'proposed', 'disputed'], true) ? $status : 'unknown';
    }

    private static function normaliseRecordStatus(array $person)
    {
        $status = strtolower(trim((string) ($person['record_status'] ?? '')));
        return in_array($status, ['confirmed', 'proposed', 'disputed'], true) ? $status : 'unknown';
    }
}
