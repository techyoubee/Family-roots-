<?php

namespace Tests;

use App\Core\Database;
use App\Core\Str;
use App\Models\Ancestor;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\FamilyRelationship;
use App\Models\MembershipRequest;
use App\Models\MembershipRequestClaim;
use App\Models\User;

/**
 * Reusable factories for test data. Every helper returns IDs so tests read
 * like the workflow they exercise. All generated data is fictional.
 */
final class Fixtures
{
    private static $counter = 0;

    public static function unique($prefix = 'item')
    {
        self::$counter++;
        return $prefix . '-' . self::$counter . '-' . substr(Str::randomToken(3), 0, 6);
    }

    public static function password()
    {
        return 'TestPassw0rd!2026';
    }

    public static function user(array $overrides = [])
    {
        $token = self::unique('user');
        $data = array_merge([
            'email'             => $token . '@example.test',
            'password_hash'     => password_hash(self::password(), PASSWORD_DEFAULT),
            'full_name'         => 'Test Person ' . $token,
            'status'            => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ], $overrides);

        $user = User::create($data);
        return $user['id'];
    }

    public static function platformAdmin(array $overrides = [])
    {
        return self::user(array_merge(['is_platform_admin' => 1], $overrides));
    }

    public static function family($founderUserId, array $overrides = [])
    {
        $token = self::unique('family');
        $family = Family::create(array_merge([
            'name'              => 'Test Family ' . $token,
            'slug'              => 'test-family-' . $token,
            'description'       => 'Test family profile',
            'history'           => 'Test family history',
            'visibility'        => 'directory',
            'verification_status' => 'unverified',
            'founder_user_id'   => (int) $founderUserId,
        ], $overrides));

        FamilyMember::upsertRole($family['id'], $founderUserId, 'founder', [
            'joined_at'   => date('Y-m-d H:i:s'),
            'approved_at' => date('Y-m-d H:i:s'),
        ]);

        return $family['id'];
    }

    public static function member($familyId, $userId, $role = 'member')
    {
        return FamilyMember::upsertRole($familyId, $userId, $role, [
            'joined_at'   => date('Y-m-d H:i:s'),
            'approved_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function ancestor($familyId, $givenNames, $surname = null, $generation = 1, array $overrides = [])
    {
        $ancestor = Ancestor::create(array_merge([
            'given_names'   => $givenNames,
            'surname'       => $surname,
            'sex'           => 'unknown',
            'generation'    => (int) $generation,
            'record_status' => 'confirmed',
            'visibility'    => 'members',
        ], $overrides), $familyId);

        return $ancestor['id'];
    }

    public static function edge($familyId, $parentId, $childId, $parentRole = 'father', array $overrides = [])
    {
        return FamilyRelationship::create(array_merge([
            'family_id'         => (int) $familyId,
            'relationship_kind' => 'parent_of',
            'from_ancestor_id'  => (int) $parentId,
            'to_ancestor_id'    => (int) $childId,
            'parent_role'       => $parentRole,
            'relationship_type' => 'biological',
            'status'            => 'confirmed',
        ], $overrides));
    }

    public static function union($familyId, $aId, $bId)
    {
        return FamilyRelationship::create([
            'family_id'         => (int) $familyId,
            'relationship_kind' => 'spouse_of',
            'from_ancestor_id'  => (int) $aId,
            'to_ancestor_id'    => (int) $bId,
            'relationship_type' => 'unknown',
            'status'            => 'confirmed',
        ]);
    }

    /** A five-generation patrilineal chain; returns ancestors by generation. */
    public static function fiveGenerationChain($familyId, $surname = 'Sample')
    {
        $chain = [];
        $child = null;
        for ($generation = 1; $generation <= 5; $generation++) {
            $person = self::ancestor($familyId, 'Gen' . $generation . ' ' . $surname, $surname, $generation);
            $chain[$generation] = $person;
            if ($child !== null) {
                self::edge($familyId, $person, $child, 'father');
            }
            $child = $person;
        }
        return $chain;
    }

    public static function membershipRequest($familyId, $applicantUserId, array $overrides = [])
    {
        return MembershipRequest::create(array_merge([
            'family_id'         => (int) $familyId,
            'applicant_user_id' => (int) $applicantUserId,
            'status'            => 'draft',
        ], $overrides));
    }

    public static function claim($requestId, array $overrides = [])
    {
        return MembershipRequestClaim::create(array_merge([
            'relationship' => 'father',
            'given_names'  => 'Claimed',
            'surname'      => 'Relative',
            'sex'          => 'unknown',
        ], $overrides), $requestId);
    }

    public static function reset()
    {
        self::$counter = 0;
    }

    public static function database()
    {
        return Database::pdo();
    }
}
