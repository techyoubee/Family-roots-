<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Ancestor;
use App\Models\FamilyRelationship;
use App\Services\AuditLog;

/**
 * Parent/child and spouse links inside one family tree.
 *
 * Every edge is validated by the model (same family, no cycles, no
 * duplicates). Members add links as `proposed`; only administrators may
 * confirm, dispute or delete them.
 */
final class RelationshipController extends Controller
{
    /** Relationship list plus the add-link form (members and above). */
    public function index(Request $request)
    {
        $family = $request->attribute('family');
        $isAdmin = Auth::isFamilyAdmin($family['id']) || Auth::isPlatformAdmin();
        $role = Auth::roleIn($family['id']);

        return $this->view('relationships/index', [
            'title'         => $family['name'] . ' - relationships',
            'family'        => $family,
            'relationships' => FamilyRelationship::forFamily($family['id']),
            'people'        => Ancestor::forFamily($family['id'], ['limit' => 200]),
            'canWrite'      => Auth::isFamilyMember($family['id']) && $role !== 'viewer',
            'canAdmin'      => $isAdmin,
        ]);
    }

    public function store(Request $request)
    {
        $family = $request->attribute('family');
        $isAdmin = Auth::isFamilyAdmin($family['id']) || Auth::isPlatformAdmin();
        $base = '/families/' . rawurlencode($family['public_id']) . '/relationships';

        $validator = Validator::make($request->all(), [
            'relationship_kind'  => 'required|in:parent_of,spouse_of',
            'from_ancestor_id'   => 'required|integer',
            'to_ancestor_id'     => 'required|integer',
            'parent_role'        => 'nullable|in:father,mother,parent,unknown',
            'relationship_type'  => 'nullable|in:biological,adopted,step,foster,unknown',
            'status'             => 'nullable|in:confirmed,proposed,disputed',
            'union_year'         => 'nullable|integer|min:1000|max:2100',
            'notes'              => 'nullable|string|max:500',
            'evidence_source'    => 'nullable|string|max:4000',
        ], [
            'relationship_kind' => 'Relationship kind',
            'from_ancestor_id'  => 'From person',
            'to_ancestor_id'    => 'To person',
        ]);

        if ($validator->fails()) {
            Session::flashErrors($validator->errors(), request()->all());
            return $this->redirect($base);
        }

        $kind = (string) $validator->value('relationship_kind');
        $fromId = (int) $validator->value('from_ancestor_id');
        $toId = (int) $validator->value('to_ancestor_id');

        $error = FamilyRelationship::validate($family['id'], $kind, $fromId, $toId);
        if ($error !== null) {
            Session::flash('error', $error);
            return $this->redirect($base);
        }

        $status = 'proposed';
        if ($isAdmin) {
            $candidate = (string) $validator->value('status', 'proposed');
            if (in_array($candidate, ['confirmed', 'proposed', 'disputed'], true)) {
                $status = $candidate;
            }
        }

        $relationship = FamilyRelationship::create([
            'family_id'          => (int) $family['id'],
            'relationship_kind'  => $kind,
            'from_ancestor_id'   => $fromId,
            'to_ancestor_id'     => $toId,
            'parent_role'        => $kind === 'parent_of' ? ((string) $validator->value('parent_role', 'unknown') ?: 'unknown') : 'unknown',
            'relationship_type'  => (string) $validator->value('relationship_type', 'unknown') ?: 'unknown',
            'status'             => $status,
            'union_year'         => $this->nullableInt($validator->value('union_year')),
            'notes'              => $this->nullableTrim($validator->value('notes')),
            'evidence_source'    => $this->nullableTrim($validator->value('evidence_source')),
            'created_by_user_id' => $this->userId(),
        ]);

        AuditLog::record('relationship.created', [
            'family_id'   => $family['id'],
            'entity_type' => 'family_relationship',
            'entity_id'   => $relationship['id'],
            'summary'     => 'Added a ' . $kind . ' link (' . $status . ').',
            'after'       => ['kind' => $kind, 'from' => $fromId, 'to' => $toId, 'status' => $status],
            'request'     => $request,
        ]);

        $from = Ancestor::find($fromId);
        $to = Ancestor::find($toId);
        Session::flash('success', 'Relationship recorded: ' . Ancestor::displayName($from) . ' ' . $this->phrase($kind) . ' ' . Ancestor::displayName($to) . '.');
        return $this->redirect($base);
    }

    public function delete(Request $request)
    {
        $family = $request->attribute('family');
        $relationship = FamilyRelationship::findForFamily($family['id'], (int) $request->routeParam('relationship'));
        if (!$relationship) {
            abort(404, 'That relationship could not be found in this family.');
        }

        FamilyRelationship::delete($relationship['id'], $family['id']);

        AuditLog::record('relationship.deleted', [
            'family_id'   => $family['id'],
            'entity_type' => 'family_relationship',
            'entity_id'   => $relationship['id'],
            'summary'     => 'Deleted a ' . $relationship['relationship_kind'] . ' link.',
            'before'      => ['kind' => $relationship['relationship_kind'], 'from' => (int) $relationship['from_ancestor_id'], 'to' => (int) $relationship['to_ancestor_id']],
            'request'     => $request,
        ]);

        Session::flash('info', 'The relationship has been deleted.');
        return $this->redirect('/families/' . rawurlencode($family['public_id']) . '/relationships');
    }

    private function phrase($kind)
    {
        return $kind === 'parent_of' ? 'is the parent of' : 'is partnered with';
    }

    private function nullableTrim($value)
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableInt($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }
}
