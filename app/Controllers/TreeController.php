<?php

namespace App\Controllers;

use App\Core\Request;
use App\Models\Ancestor;
use App\Models\FamilyRelationship;

/**
 * Server-rendered family tree outline.
 *
 * People are grouped by generation (oldest first) and each person shows their
 * parents, partners and children as links. The page works without JavaScript;
 * a pannable canvas can enhance it later.
 */
final class TreeController extends Controller
{
    public function show(Request $request)
    {
        $family = $request->attribute('family');

        $limit = 300;
        $ancestors = Ancestor::forFamily($family['id'], ['limit' => $limit]);
        $edges = FamilyRelationship::forFamily($family['id']);

        $byId = [];
        foreach ($ancestors as $ancestor) {
            $ancestor['parents'] = [];
            $ancestor['children'] = [];
            $ancestor['partners'] = [];
            $byId[(int) $ancestor['id']] = $ancestor;
        }

        foreach ($edges as $edge) {
            $fromId = (int) $edge['from_ancestor_id'];
            $toId = (int) $edge['to_ancestor_id'];
            if ($edge['relationship_kind'] === 'parent_of') {
                if (isset($byId[$toId])) {
                    $byId[$toId]['parents'][] = $edge;
                }
                if (isset($byId[$fromId])) {
                    $byId[$fromId]['children'][] = $edge;
                }
            } elseif ($edge['relationship_kind'] === 'spouse_of') {
                if (isset($byId[$fromId])) {
                    $byId[$fromId]['partners'][] = $edge;
                }
                if (isset($byId[$toId])) {
                    $byId[$toId]['partners'][] = $edge;
                }
            }
        }

        $generations = [];
        foreach ($byId as $person) {
            $generations[(int) $person['generation']][] = $person;
        }
        krsort($generations);

        $total = count($byId);
        $truncated = Ancestor::countForFamily($family['id']) > $limit;

        return $this->view('tree/show', [
            'title'       => $family['name'] . ' - family tree',
            'family'      => $family,
            'generations' => $generations,
            'total'       => $total,
            'truncated'   => $truncated,
            'limit'       => $limit,
            'myRole'      => \App\Core\Auth::roleIn($family['id']),
        ]);
    }
}
