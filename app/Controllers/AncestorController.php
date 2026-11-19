<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Ancestor;
use App\Models\FamilyRelationship;

/**
 * Ancestor records inside one family tree.
 *
 * Viewers are read-only. Members add and edit records; every new record is
 * created as `proposed` - adding a person never asserts the claim is true.
 * Only administrators change record status or delete records, and deletion
 * removes every relationship that pointed at the person.
 */
final class AncestorController extends Controller
{
    public function index(Request $request)
    {
        $family = $request->attribute('family');
        $isAdmin = Auth::isFamilyAdmin($family['id']) || Auth::isPlatformAdmin();

        $filters = [
            'q'          => trim((string) $request->query('q', '')),
            'generation' => (string) $request->query('generation', ''),
            'branch'     => trim((string) $request->query('branch', '')),
            'status'     => (string) $request->query('status', ''),
        ];
        $activeFilters = array_filter($filters, static function ($value) {
            return $value !== '';
        });

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;
        $total = Ancestor::countMatching($family['id'], $filters);
        $ancestors = Ancestor::forFamily($family['id'], array_merge($filters, [
            'limit'  => $perPage,
            'offset' => ($page - 1) * $perPage,
        ]));

        return $this->view('ancestors/index', [
            'title'       => $family['name'] . ' - ancestor records',
            'family'      => $family,
            'ancestors'   => $ancestors,
            'filters'     => $filters,
            'activeCount' => count($activeFilters),
            'total'       => $total,
            'page'        => $page,
            'perPage'     => $perPage,
            'branches'    => Ancestor::branches($family['id']),
            'genCounts'   => Ancestor::generationCounts($family['id']),
            'statusCounts'=> Ancestor::statusCounts($family['id']),
            'myRole'      => Auth::roleIn($family['id']),
            'canWrite'    => $this->canWrite($family['id']),
            'canAdmin'    => $isAdmin,
        ]);
    }

    public function show(Request $request)
    {
        $family = $request->attribute('family');
        $ancestor = Ancestor::findForFamily($family['id'], (int) $request->routeParam('ancestor'));
        if (!$ancestor) {
            abort(404, 'That ancestor record could not be found in this family.');
        }

        $canAdmin = Auth::isFamilyAdmin($family['id']) || Auth::isPlatformAdmin();
        $duplicates = $canAdmin
            ? Ancestor::duplicateCandidates($family['id'], (string) $ancestor['name_normalized'], $ancestor['id'])
            : [];

        return $this->view('ancestors/show', [
            'title'      => \App\Models\Ancestor::displayName($ancestor) . ' - ' . $family['name'],
            'family'     => $family,
            'ancestor'   => $ancestor,
            'parents'    => FamilyRelationship::parentsOf($ancestor['id']),
            'spouses'    => FamilyRelationship::spousesOf($ancestor['id']),
            'children'   => FamilyRelationship::childrenOf($ancestor['id']),
            'siblings'   => FamilyRelationship::siblingsOf($ancestor['id']),
            'duplicates' => $duplicates,
            'canWrite'   => $this->canWrite($family['id']),
            'canAdmin'   => $canAdmin,
            'myRole'     => Auth::roleIn($family['id']),
        ]);
    }

    public function create(Request $request)
    {
        $family = $request->attribute('family');
        return $this->view('ancestors/form', [
            'title'    => 'Add an ancestor - ' . $family['name'],
            'family'   => $family,
            'ancestor' => null,
            'canAdmin' => Auth::isFamilyAdmin($family['id']) || Auth::isPlatformAdmin(),
            'myRole'   => Auth::roleIn($family['id']),
        ]);
    }

    public function store(Request $request)
    {
        $family = $request->attribute('family');
        $isAdmin = Auth::isFamilyAdmin($family['id']) || Auth::isPlatformAdmin();

        $data = $this->validatedRecord($request, $isAdmin);
        if (is_string($data)) {
            Session::flash('error', $data);
            return $this->redirect('/families/' . rawurlencode($family['public_id']) . '/ancestors/create');
        }

        $ancestor = Ancestor::create($data, $family['id'], $this->userId());

        \App\Services\AuditLog::record('ancestor.created', [
            'family_id'   => $family['id'],
            'entity_type' => 'ancestor',
            'entity_id'   => $ancestor['id'],
            'summary'     => 'Added ancestor record ' . Ancestor::displayName($ancestor) . '.',
            'after'       => ['name' => Ancestor::displayName($ancestor), 'record_status' => $ancestor['record_status']],
            'request'     => $request,
        ]);

        $duplicates = Ancestor::duplicateCandidates($family['id'], (string) $ancestor['name_normalized'], $ancestor['id']);
        if (!empty($duplicates)) {
            $names = [];
            foreach (array_slice($duplicates, 0, 3) as $duplicate) {
                $names[] = Ancestor::displayName($duplicate) . ' (' . status_label($duplicate['record_status']) . ')';
            }
            Session::flash('info', 'Record created. Possible similar records already exist: ' . implode('; ', $names) . '. Nothing is merged automatically - please review them yourself.');
        } else {
            Session::flash('success', 'The ancestor record has been added. It stays proposed until a family administrator confirms it.');
        }

        return $this->redirect('/families/' . rawurlencode($family['public_id']) . '/ancestors/' . (int) $ancestor['id']);
    }

    public function edit(Request $request)
    {
        $family = $request->attribute('family');
        $ancestor = Ancestor::findForFamily($family['id'], (int) $request->routeParam('ancestor'));
        if (!$ancestor) {
            abort(404, 'That ancestor record could not be found in this family.');
        }

        return $this->view('ancestors/form', [
            'title'    => 'Edit ' . Ancestor::displayName($ancestor),
            'family'   => $family,
            'ancestor' => $ancestor,
            'canAdmin' => Auth::isFamilyAdmin($family['id']) || Auth::isPlatformAdmin(),
            'myRole'   => Auth::roleIn($family['id']),
        ]);
    }

    public function update(Request $request)
    {
        $family = $request->attribute('family');
        $ancestor = Ancestor::findForFamily($family['id'], (int) $request->routeParam('ancestor'));
        if (!$ancestor) {
            abort(404, 'That ancestor record could not be found in this family.');
        }

        $isAdmin = Auth::isFamilyAdmin($family['id']) || Auth::isPlatformAdmin();
        $data = $this->validatedRecord($request, $isAdmin);
        if (is_string($data)) {
            Session::flash('error', $data);
            return $this->redirect('/families/' . rawurlencode($family['public_id']) . '/ancestors/' . (int) $ancestor['id'] . '/edit');
        }

        Ancestor::update($ancestor['id'], $data, $family['id']);

        \App\Services\AuditLog::record('ancestor.updated', [
            'family_id'   => $family['id'],
            'entity_type' => 'ancestor',
            'entity_id'   => $ancestor['id'],
            'summary'     => 'Updated ancestor record ' . Ancestor::displayName($ancestor) . '.',
            'before'      => ['name' => Ancestor::displayName($ancestor), 'record_status' => $ancestor['record_status']],
            'after'       => ['record_status' => isset($data['record_status']) ? $data['record_status'] : $ancestor['record_status']],
            'request'     => $request,
        ]);

        Session::flash('success', 'The ancestor record has been saved.');
        return $this->redirect('/families/' . rawurlencode($family['public_id']) . '/ancestors/' . (int) $ancestor['id']);
    }

    public function delete(Request $request)
    {
        $family = $request->attribute('family');
        $ancestor = Ancestor::findForFamily($family['id'], (int) $request->routeParam('ancestor'));
        if (!$ancestor) {
            abort(404, 'That ancestor record could not be found in this family.');
        }

        $name = Ancestor::displayName($ancestor);
        Ancestor::delete($ancestor['id'], $family['id']);

        \App\Services\AuditLog::record('ancestor.deleted', [
            'family_id'   => $family['id'],
            'entity_type' => 'ancestor',
            'entity_id'   => $ancestor['id'],
            'summary'     => 'Deleted ancestor record ' . $name . '.',
            'before'      => ['name' => $name, 'record_status' => $ancestor['record_status']],
            'request'     => $request,
        ]);

        Session::flash('info', '"' . $name . '" and every relationship pointing at this person have been deleted.');
        return $this->redirect('/families/' . rawurlencode($family['public_id']) . '/ancestors');
    }

    /** Viewers may read; members, admins and platform admins may write. */
    private function canWrite($familyId)
    {
        return Auth::isFamilyMember($familyId) && Auth::roleIn($familyId) !== 'viewer';
    }

    /**
     * Validate an ancestor record. Returns the clean field array, or an
     * error message string when validation fails.
     *
     * @return array|string
     */
    private function validatedRecord(Request $request, $isAdmin)
    {
        $rules = [
            'given_names'   => 'required|string|min:1|max:150',
            'surname'       => 'nullable|string|max:100',
            'alt_names'     => 'nullable|string|max:500',
            'sex'           => 'required|in:male,female,unknown',
            'is_living'     => 'nullable|in:0,1',
            'birth_date'    => 'nullable|date',
            'birth_date_text' => 'nullable|string|max:60',
            'death_date'    => 'nullable|date',
            'death_date_text' => 'nullable|string|max:60',
            'birth_place'   => 'nullable|string|max:160',
            'death_place'   => 'nullable|string|max:160',
            'generation'    => 'required|integer|min:0|max:9',
            'branch'        => 'nullable|string|max:120',
            'notes'         => 'nullable|string|max:10000',
            'evidence_source' => 'nullable|string|max:4000',
            'record_status' => 'in:confirmed,proposed,unknown,disputed',
        ];
        $labels = [
            'given_names' => 'Given names',
            'surname'     => 'Surname',
            'alt_names'   => 'Other names',
            'sex'         => 'Sex',
            'generation'  => 'Generation',
            'branch'      => 'Branch',
            'record_status' => 'Record status',
        ];

        $validator = Validator::make($request->all(), $rules, $labels);
        if ($validator->fails()) {
            Session::flashErrors($validator->errors(), request()->all());
            return 'Please correct the highlighted fields.';
        }

        $data = [
            'given_names'     => trim((string) $validator->value('given_names')),
            'surname'         => $this->nullableTrim($validator->value('surname')),
            'alt_names'       => $this->nullableTrim($validator->value('alt_names')),
            'sex'             => $validator->value('sex'),
            'is_living'       => (int) $request->input('is_living', 0) === 1 ? 1 : 0,
            'birth_date'      => $this->nullableDate($validator->value('birth_date')),
            'birth_date_text' => $this->nullableTrim($validator->value('birth_date_text')),
            'death_date'      => $this->nullableDate($validator->value('death_date')),
            'death_date_text' => $this->nullableTrim($validator->value('death_date_text')),
            'birth_place'     => $this->nullableTrim($validator->value('birth_place')),
            'death_place'     => $this->nullableTrim($validator->value('death_place')),
            'generation'      => (int) $validator->value('generation'),
            'branch'          => $this->nullableTrim($validator->value('branch')),
            'notes'           => $this->nullableTrim($validator->value('notes')),
            'evidence_source' => $this->nullableTrim($validator->value('evidence_source')),
        ];

        if ($isAdmin) {
            $status = (string) $request->input('record_status', 'proposed');
            if (in_array($status, ['confirmed', 'proposed', 'unknown', 'disputed'], true)) {
                $data['record_status'] = $status;
            }
        }

        return $data;
    }

    private function nullableTrim($value)
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableDate($value)
    {
        $trimmed = $this->nullableTrim($value);
        return $trimmed;
    }
}
