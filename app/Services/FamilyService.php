<?php

namespace App\Services;

use App\Core\Request;
use App\Core\Str;
use App\Models\Family;
use App\Models\FamilyMember;

/**
 * Family and membership write operations.
 *
 * Controllers validate shape; the security decisions live here:
 *  - the founder role is fixed and cannot be reassigned or removed;
 *  - you can never change your own role or remove yourself (no accidental
 *    lock-out of a family you administer);
 *  - only the founder appoints or removes other administrators;
 *  - every change is audited and the affected member is notified.
 */
final class FamilyService
{
    /** Stable, unique slug derived from the family name. */
    public static function uniqueSlug($name)
    {
        $base = Str::slug((string) $name);
        if ($base === '') {
            $base = 'family';
        }
        if (!Family::slugExists($base)) {
            return $base;
        }
        for ($i = 2; $i <= 100; $i++) {
            $candidate = $base . '-' . $i;
            if (!Family::slugExists($candidate)) {
                return $candidate;
            }
        }
        return $base . '-' . strtolower(substr(Str::randomToken(4), 0, 4));
    }

    /**
     * Register a new family. The owner becomes its founder with the founder
     * membership role. The profile always starts `unverified` - registering
     * a family never asserts that its history is true.
     *
     * @param array $owner signed-in user row
     * @param array $data  name, description, history, origins, region, country, visibility
     * @return array created family row
     */
    public static function createFamily(array $owner, array $data, Request $request = null)
    {
        $family = Family::create([
            'name'             => trim((string) $data['name']),
            'slug'             => self::uniqueSlug($data['name']),
            'description'      => $data['description'] ?? null,
            'history'          => $data['history'] ?? null,
            'origins'          => $data['origins'] ?? null,
            'region'           => $data['region'] ?? null,
            'country'          => $data['country'] ?? null,
            'visibility'       => $data['visibility'] ?? 'members',
            'founder_user_id'  => (int) $owner['id'],
        ]);

        FamilyMember::upsertRole($family['id'], $owner['id'], 'founder', [
            'joined_at'   => date('Y-m-d H:i:s'),
            'approved_at' => date('Y-m-d H:i:s'),
        ]);

        AuditLog::record('family.created', [
            'family_id'   => $family['id'],
            'entity_type' => 'family',
            'entity_id'   => $family['id'],
            'summary'     => 'Family "' . $family['name'] . '" was registered.',
            'request'     => $request,
        ]);

        return $family;
    }

    /**
     * Update a family profile. The slug never changes after registration so
     * existing links and public identifiers stay valid.
     *
     * @param array $family full family row (before)
     * @param array $actor  signed-in user row
     * @return array refreshed family row
     */
    public static function updateProfile(array $family, array $actor, array $data, Request $request = null)
    {
        $fields = array_intersect_key($data, array_flip([
            'name', 'description', 'history', 'origins', 'founding_note', 'region', 'country', 'visibility',
        ]));
        $before = array_intersect_key($family, $fields);

        Family::update($family['id'], $fields);

        AuditLog::record('family.profile_updated', [
            'family_id'   => $family['id'],
            'entity_type' => 'family',
            'entity_id'   => $family['id'],
            'summary'     => 'Profile of "' . $family['name'] . '" was updated.',
            'before'      => $before,
            'after'       => $fields,
            'request'     => $request,
        ]);

        return Family::find($family['id']);
    }

    /**
     * Change a member's role. Returns an error message when the change is not
     * allowed, or null when the role was updated.
     *
     * @param array $memberRow row from family_members (joined with user data optional)
     * @param string $newRole  admin|member|viewer (founder is never accepted)
     * @return string|null error message, or null on success
     */
    public static function changeMemberRole(array $family, array $actor, $actorRole, array $memberRow, $newRole, Request $request = null)
    {
        if ($memberRow['role'] === 'founder') {
            return 'The founder role is fixed and cannot be changed.';
        }
        if ((int) $actor['id'] === (int) $memberRow['user_id']) {
            return 'You cannot change your own role here.';
        }
        if (!in_array($newRole, ['admin', 'member', 'viewer'], true)) {
            return 'That role does not exist.';
        }
        if ($newRole === 'admin' && $actorRole !== 'founder') {
            return 'Only the founder can appoint administrators.';
        }
        if ($memberRow['role'] === 'admin' && $actorRole !== 'founder') {
            return 'Only the founder can change another administrator\'s role.';
        }

        $beforeRole = $memberRow['role'];
        FamilyMember::upsertRole($family['id'], $memberRow['user_id'], $newRole);

        AuditLog::record('family.member_role_changed', [
            'family_id'   => $family['id'],
            'entity_type' => 'family_member',
            'entity_id'   => $memberRow['id'],
            'summary'     => 'A member\'s role changed from ' . $beforeRole . ' to ' . $newRole . '.',
            'before'      => ['role' => $beforeRole],
            'after'       => ['role' => $newRole],
            'request'     => $request,
        ]);

        NotificationService::notify(
            (int) $memberRow['user_id'],
            'family.role_changed',
            'Your role in ' . $family['name'] . ' changed',
            'An administrator changed your role from ' . self::status_label_public($beforeRole) . ' to ' . self::status_label_public($newRole) . '.',
            '/families/' . rawurlencode($family['public_id']),
            (int) $family['id']
        );

        return null;
    }

    /**
     * Remove a member from a family. Returns an error message when not
     * allowed, or null on success. Removal is a status change - the audit
     * trail keeps the history.
     *
     * @return string|null
     */
    public static function removeMember(array $family, array $actor, $actorRole, array $memberRow, Request $request = null)
    {
        if ($memberRow['role'] === 'founder') {
            return 'The founder cannot be removed from the family.';
        }
        if ((int) $actor['id'] === (int) $memberRow['user_id']) {
            return 'You cannot remove yourself here. Ask another administrator.';
        }
        if ($memberRow['role'] === 'admin' && $actorRole !== 'founder') {
            return 'Only the founder can remove an administrator.';
        }

        FamilyMember::remove($family['id'], $memberRow['user_id']);

        AuditLog::record('family.member_removed', [
            'family_id'   => $family['id'],
            'entity_type' => 'family_member',
            'entity_id'   => $memberRow['id'],
            'summary'     => 'A member was removed from ' . $family['name'] . '.',
            'before'      => ['role' => $memberRow['role'], 'user_id' => (int) $memberRow['user_id']],
            'request'     => $request,
        ]);

        NotificationService::notify(
            (int) $memberRow['user_id'],
            'family.removed',
            'You were removed from ' . $family['name'],
            'An administrator removed your access to this family. Your account is unaffected.',
            null,
            (int) $family['id']
        );

        return null;
    }

    /** Role label for notification text without pulling in the view layer. */
    private static function status_label_public($role)
    {
        return ucwords(str_replace('_', ' ', (string) $role));
    }
}
