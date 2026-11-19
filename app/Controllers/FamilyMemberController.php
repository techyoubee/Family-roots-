<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Models\FamilyMember;
use App\Services\FamilyService;

/**
 * Member management for family administrators.
 *
 * Role changes and removals run through FamilyService so the founder and
 * self-management guards apply on every path.
 */
final class FamilyMemberController extends Controller
{
    /** Member list with per-row management controls (administrators only). */
    public function index(Request $request)
    {
        $family = $request->attribute('family');
        $members = FamilyMember::listForFamily($family['id']);
        $actorId = (int) $this->userId();
        $actorRole = Auth::isPlatformAdmin() && !Auth::roleIn($family['id'])
            ? 'admin'
            : Auth::roleIn($family['id']);

        $rows = [];
        foreach ($members as $member) {
            $rows[] = [
                'member'    => $member,
                'canChange' => $this->canChangeRole($actorId, $actorRole, $member),
                'canRemove' => $this->canRemove($actorId, $actorRole, $member),
            ];
        }

        return $this->view('families/members', [
            'title'  => $family['name'] . ' - members',
            'family' => $family,
            'rows'   => $rows,
            'myRole' => Auth::roleIn($family['id']),
        ]);
    }

    public function updateRole(Request $request)
    {
        $family = $request->attribute('family');
        $member = FamilyMember::find((int) $request->routeParam('member'));
        if (!$member || (int) $member['family_id'] !== (int) $family['id'] || $member['status'] !== 'active') {
            Session::flash('error', 'That membership could not be found in this family.');
            return $this->redirect('/families/' . rawurlencode($family['public_id']) . '/members');
        }

        $actor = Auth::user();
        $actorRole = Auth::isPlatformAdmin() && !Auth::roleIn($family['id'])
            ? 'admin'
            : Auth::roleIn($family['id']);
        $newRole = (string) $request->input('role', '');

        $error = FamilyService::changeMemberRole($family, $actor, $actorRole, $member, $newRole, $request);
        if ($error !== null) {
            Session::flash('error', $error);
            return $this->redirect('/families/' . rawurlencode($family['public_id']) . '/members');
        }

        Session::flash('success', 'The member\'s role has been updated.');
        return $this->redirect('/families/' . rawurlencode($family['public_id']) . '/members');
    }

    public function remove(Request $request)
    {
        $family = $request->attribute('family');
        $member = FamilyMember::find((int) $request->routeParam('member'));
        if (!$member || (int) $member['family_id'] !== (int) $family['id'] || $member['status'] !== 'active') {
            Session::flash('error', 'That membership could not be found in this family.');
            return $this->redirect('/families/' . rawurlencode($family['public_id']) . '/members');
        }

        $actor = Auth::user();
        $actorRole = Auth::isPlatformAdmin() && !Auth::roleIn($family['id'])
            ? 'admin'
            : Auth::roleIn($family['id']);

        $error = FamilyService::removeMember($family, $actor, $actorRole, $member, $request);
        if ($error !== null) {
            Session::flash('error', $error);
            return $this->redirect('/families/' . rawurlencode($family['public_id']) . '/members');
        }

        Session::flash('success', 'The member has been removed from the family.');
        return $this->redirect('/families/' . rawurlencode($family['public_id']) . '/members');
    }

    private function canChangeRole($actorId, $actorRole, array $member)
    {
        if ($member['role'] === 'founder' || (int) $member['user_id'] === $actorId) {
            return false;
        }
        if ($member['role'] === 'admin') {
            return $actorRole === 'founder';
        }
        return in_array($actorRole, ['founder', 'admin'], true);
    }

    private function canRemove($actorId, $actorRole, array $member)
    {
        if ($member['role'] === 'founder' || (int) $member['user_id'] === $actorId) {
            return false;
        }
        if ($member['role'] === 'admin') {
            return $actorRole === 'founder';
        }
        return in_array($actorRole, ['founder', 'admin'], true);
    }
}
