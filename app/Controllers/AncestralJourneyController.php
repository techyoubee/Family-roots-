<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AncestralJourney;
use App\Models\Ancestor;
use App\Models\FamilyMember;
use App\Models\FamilyRelationship;
use App\Services\AncestryCalculator;

/**
 * "My Ancestral Journey" - an interactive, read-only presentation of the
 * family relationships that already exist in the database.
 *
 * The feature never creates, edits or invents people. A signed-in user must
 * belong to at least one active family; the journey is scoped to that family
 * and to the ancestor record linked to the member (when one exists).
 */
final class AncestralJourneyController extends Controller
{
    /** Safety limit for the SVG view; the query is bounded on purpose. */
    private const ANCESTOR_LIMIT = 500;

    public function intro(Request $request)
    {
        return $this->renderStep('intro', $request, 'intro');
    }

    public function animate(Request $request)
    {
        return $this->renderStep('animate', $request, 'animate');
    }

    public function tree(Request $request)
    {
        return $this->renderStep('tree', $request, 'tree');
    }

    public function timeline(Request $request)
    {
        return $this->renderStep('timeline', $request, 'timeline');
    }

    /** JSON feed consumed by the animated stage and the SVG tree. */
    public function data(Request $request)
    {
        $context = $this->context($request);

        return $this->json([
            'ok'      => $context['reason'] === null,
            'reason'  => $context['reason'],
            'meta'    => $context['meta'],
            'payload' => $context['payload'],
        ]);
    }

    /** Mark the journey completed, then open the interactive tree. */
    public function complete(Request $request)
    {
        $user = Auth::user();
        $familyId = (int) $request->input('family_id', 0);
        $membership = $familyId > 0 ? FamilyMember::findFor($familyId, $user['id']) : null;

        if (!$membership || $membership['status'] !== 'active') {
            Session::flash('error', 'Choose one of your families before starting the journey.');
            return Response::redirect(query_url('/journey'));
        }

        AncestralJourney::complete($user['id'], $familyId);

        $family = Database::first('SELECT id, public_id, name FROM families WHERE id = ?', [$familyId]);
        Session::flash('success', 'Journey saved. Explore the tree at your own pace.');

        return Response::redirect(query_url(
            '/journey/tree',
            $family ? ['family' => $family['public_id']] : []
        ));
    }

    private function renderStep($template, Request $request, $step)
    {
        $context = $this->context($request);

        if ($context['family'] !== null && $context['reason'] === null) {
            AncestralJourney::touch($context['user']['id'], $context['family']['id'], $step);
            $context['journeyRow'] = AncestralJourney::forUser($context['user']['id'], $context['family']['id']);
        }

        return $this->view('journey/' . $template, [
            'title'       => $context['family'] !== null
                ? 'Ancestral Journey - ' . $context['family']['name']
                : 'Ancestral Journey',
            'styles'      => ['/assets/css/journey/journey.css'],
            'scripts'     => ['/assets/js/journey/journey.js'],
            'user'        => $context['user'],
            'memberships' => $context['memberships'],
            'family'      => $context['family'],
            'emptyReason' => $context['reason'],
            'truncated'   => $context['truncated'],
            'root'        => $context['root'],
            'journey'     => $context['journey'],
            'layout'      => $context['layout'],
            'timeline'    => $context['timeline'],
            'stats'       => $context['stats'],
            'payload'     => $context['payload'],
            'journeyRow'  => $context['journeyRow'],
            'stepUrls'    => $this->stepUrls($context['family']),
            'currentStep' => $step,
        ]);
    }

    /**
     * Resolve the member's family and build every presentation artefact from a
     * single snapshot of ancestors + relationships.
     */
    private function context(Request $request)
    {
        $user = Auth::user();
        $memberships = FamilyMember::familiesForUser($user['id']);
        $family = $this->resolveFamily($request, $user['id'], $memberships);

        $base = [
            'user'        => $user,
            'memberships' => $memberships,
            'family'      => $family,
            'journeyRow'  => null,
            'root'        => null,
            'index'       => ['people' => [], 'parents' => [], 'children' => [], 'spouses' => []],
            'journey'     => AncestryCalculator::journey([], 0),
            'layout'      => ['width' => 0, 'height' => 0, 'nodes' => [], 'edges' => [], 'levels' => []],
            'timeline'    => ['events' => [], 'total' => 0, 'truncated' => false, 'undated' => 0, 'min_year' => null, 'max_year' => null],
            'stats'       => AncestryCalculator::stats(['nodes' => [], 'levels' => [], 'pedigree_collapse' => ['count' => 0, 'repeat_appearances' => 0, 'people' => []]]),
            'payload'     => null,
            'meta'        => ['family' => null, 'root_id' => null, 'max_depth' => AncestryCalculator::MAX_DEPTH],
            'truncated'   => false,
            'reason'      => 'no_family',
        ];

        if ($family === null) {
            $base['payload'] = $this->emptyPayload();
            return $base;
        }

        $base['journeyRow'] = AncestralJourney::forUser($user['id'], $family['id']);
        $membership = FamilyMember::findFor($family['id'], $user['id']);
        $includeAdmins = Auth::isFamilyAdmin($family['id']);

        $ancestors = $this->loadAncestors($family['id'], $includeAdmins);
        $edges = FamilyRelationship::forFamily($family['id']);
        $index = AncestryCalculator::buildIndex($ancestors, $edges);
        $base['index'] = $index;
        $base['truncated'] = count($ancestors) >= self::ANCESTOR_LIMIT;

        $root = $this->resolveRoot($index, $membership, $user['id']);
        if ($root === null) {
            $base['reason'] = 'no_ancestors';
            $base['payload'] = $this->emptyPayload($family);
            return $base;
        }

        $journey = AncestryCalculator::journey($index, $root['id'], AncestryCalculator::MAX_DEPTH);
        $layout = AncestryCalculator::layout($journey);
        $timeline = AncestryCalculator::timeline($journey);
        $stats = AncestryCalculator::stats($journey);
        $payload = $this->buildPayload($family, $root, $journey, $layout, $timeline, $stats);

        $base['root'] = $root;
        $base['journey'] = $journey;
        $base['layout'] = $layout;
        $base['timeline'] = $timeline;
        $base['stats'] = $stats;
        $base['payload'] = $payload;
        $base['meta'] = $payload['meta'];
        $base['reason'] = null;

        return $base;
    }

    /**
     * Pick an active family: explicit request, then the last journey, then the
     * member's most recently joined family. Never accepts a family the user is
     * not an active member of.
     */
    private function resolveFamily(Request $request, $userId, array $memberships)
    {
        if (empty($memberships)) {
            return null;
        }

        $requested = trim((string) $request->input('family', ''));
        if ($requested !== '') {
            foreach ($memberships as $membership) {
                if ((string) $membership['public_id'] === $requested || (string) $membership['id'] === $requested) {
                    return $membership;
                }
            }
        }

        $latest = AncestralJourney::latestForUser($userId);
        if ($latest) {
            foreach ($memberships as $membership) {
                if ((int) $membership['id'] === (int) $latest['family_id']) {
                    return $membership;
                }
            }
        }

        return $memberships[0];
    }

    private function loadAncestors($familyId, $includeAdmins)
    {
        $visibility = $includeAdmins ? ['public', 'members', 'admins'] : ['public', 'members'];
        $placeholders = implode(',', array_fill(0, count($visibility), '?'));
        $params = array_merge([(int) $familyId], $visibility);
        $sql = 'SELECT * FROM ancestors
                 WHERE family_id = ?
                   AND visibility IN (' . $placeholders . ')
                 ORDER BY generation DESC, id ASC
                 LIMIT ' . self::ANCESTOR_LIMIT;

        return Database::select($sql, $params);
    }

    /**
     * Prefer the ancestor record linked to this member, then one linked to the
     * user account, then the youngest recorded generation. Nothing is created
     * when the family has no records yet.
     */
    private function resolveRoot(array $index, $membership, $userId)
    {
        if (empty($index['people'])) {
            return null;
        }

        if ($membership && !empty($membership['ancestor_id'])) {
            $linkedId = (int) $membership['ancestor_id'];
            if (isset($index['people'][$linkedId])) {
                return $index['people'][$linkedId];
            }
        }

        foreach ($index['people'] as $person) {
            if (!empty($person['linked_user_id']) && (int) $person['linked_user_id'] === (int) $userId) {
                return $person;
            }
        }

        $candidates = array_values($index['people']);
        usort($candidates, static function ($a, $b) use ($index) {
            $generationA = (int) ($a['generation'] ?? 0);
            $generationB = (int) ($b['generation'] ?? 0);
            if ($generationA !== $generationB) {
                return $generationB <=> $generationA;
            }
            $parentsA = isset($index['parents'][(int) $a['id']]) ? count($index['parents'][(int) $a['id']]) : 0;
            $parentsB = isset($index['parents'][(int) $b['id']]) ? count($index['parents'][(int) $b['id']]) : 0;
            if ($parentsA !== $parentsB) {
                return $parentsB <=> $parentsA;
            }
            return ((int) $a['id']) <=> ((int) $b['id']);
        });

        return $candidates[0];
    }

    /** Trim database rows to the fields the browser really needs. */
    private function buildPayload(array $family, array $root, array $journey, array $layout, array $timeline, array $stats)
    {
        $nodes = [];
        foreach ($journey['nodes'] as $node) {
            $person = $node['person'];
            $nodes[] = [
                'id'              => (int) $node['id'],
                'name'            => $node['name'],
                'given_names'     => (string) ($person['given_names'] ?? ''),
                'surname'         => (string) ($person['surname'] ?? ''),
                'sex'             => $node['sex'],
                'level'           => (int) $node['level'],
                'generation'      => (int) $node['generation'],
                'branch'          => $node['branch'],
                'birth_year'      => $node['birth_year'],
                'death_year'      => $node['death_year'],
                'birth_date_text' => (string) ($person['birth_date_text'] ?? ''),
                'death_date_text' => (string) ($person['death_date_text'] ?? ''),
                'birth_place'     => (string) ($person['birth_place'] ?? ''),
                'death_place'     => (string) ($person['death_place'] ?? ''),
                'living'          => $node['living'],
                'record_status'   => $node['record_status'],
                'certainty'       => $node['certainty'],
                'multiplicity'    => (int) $node['multiplicity'],
                'collapsed'       => $node['collapsed'],
                'parents'         => array_map(static function ($parent) {
                    return (int) $parent['id'];
                }, $node['parents']),
                'spouses'         => array_map(static function ($spouse) {
                    return [
                        'id'         => (int) $spouse['id'],
                        'status'     => $spouse['status'],
                        'union_year' => $spouse['union_year'],
                    ];
                }, $node['spouses']),
            ];
        }

        $generations = [];
        foreach ($journey['levels'] as $level => $ids) {
            $generations[] = [
                'level' => (int) $level,
                'label' => $this->generationLabel((int) $level),
                'ids'   => array_map('intval', $ids),
            ];
        }
        usort($generations, static function ($a, $b) {
            return $a['level'] <=> $b['level'];
        });

        return [
            'meta' => [
                'family'       => [
                    'id'        => (int) $family['id'],
                    'public_id' => (string) $family['public_id'],
                    'name'      => (string) $family['name'],
                ],
                'root_id'      => (int) $root['id'],
                'root_name'    => Ancestor::displayName($root),
                'max_depth'    => (int) $journey['max_depth'],
                'generated_at' => date(DATE_ATOM),
            ],
            'nodes'       => $nodes,
            'edges'       => $layout['edges'],
            'positions'   => $layout['nodes'],
            'layout'      => ['width' => $layout['width'], 'height' => $layout['height']],
            'generations' => $generations,
            'stats'       => $stats,
            'collapse'    => $journey['pedigree_collapse'],
            'timeline'    => $timeline,
        ];
    }

    private function emptyPayload(array $family = null)
    {
        return [
            'meta' => [
                'family'       => $family ? [
                    'id'        => (int) $family['id'],
                    'public_id' => (string) $family['public_id'],
                    'name'      => (string) $family['name'],
                ] : null,
                'root_id'      => null,
                'root_name'    => null,
                'max_depth'    => AncestryCalculator::MAX_DEPTH,
                'generated_at' => date(DATE_ATOM),
            ],
            'nodes'       => [],
            'edges'       => [],
            'positions'   => [],
            'layout'      => ['width' => 0, 'height' => 0],
            'generations' => [],
            'stats'       => AncestryCalculator::stats(['nodes' => [], 'levels' => [], 'pedigree_collapse' => ['count' => 0, 'repeat_appearances' => 0, 'people' => []]]),
            'collapse'    => ['count' => 0, 'repeat_appearances' => 0, 'people' => []],
            'timeline'    => ['events' => [], 'total' => 0, 'truncated' => false, 'undated' => 0, 'min_year' => null, 'max_year' => null],
        ];
    }

    public static function generationLabel($level)
    {
        $level = (int) $level;
        if ($level === 0) {
            return 'You / focus person';
        }
        $labels = [
            1 => 'Parents',
            2 => 'Grandparents',
            3 => 'Great-grandparents',
            4 => '2x great-grandparents',
            5 => '3x great-grandparents',
        ];
        return isset($labels[$level]) ? $labels[$level] : $level . ' generations back';
    }

    private function stepUrls($family)
    {
        $params = $family ? ['family' => $family['public_id']] : [];
        return [
            'intro'    => query_url('/journey', $params),
            'animate'  => query_url('/journey/animate', $params),
            'tree'     => query_url('/journey/tree', $params),
            'timeline' => query_url('/journey/timeline', $params),
            'data'     => query_url('/journey/data', $params),
            'complete' => query_url('/journey/complete'),
        ];
    }
}
