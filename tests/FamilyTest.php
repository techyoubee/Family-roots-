<?php

namespace Tests;

use App\Core\Auth;
use App\Models\Ancestor;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\FamilyRelationship;
use App\Models\User;
use App\Services\FamilyService;

/**
 * Family management: registration, profile updates, member roles and
 * membership removal guards.
 */
final class FamilyTest extends TestCase
{
    public function setUp()
    {
        Auth::logout();
    }

    public function tearDown()
    {
        Auth::logout();
    }

    public function testCreatingAFamilyMakesTheOwnerItsFounder()
    {
        $ownerId = Fixtures::user();
        $owner = User::find($ownerId);

        $family = FamilyService::createFamily($owner, [
            'name'       => 'Kirant Test Family',
            'visibility' => 'members',
        ]);

        $this->assertSame('unverified', $family['verification_status'], 'A new family is never automatically verified.');
        $this->assertSame('members', $family['visibility'], 'The requested visibility is stored.');
        $this->assertEquals($ownerId, (int) $family['founder_user_id'], 'The creator is recorded as founder.');

        $role = FamilyMember::roleFor($family['id'], $ownerId);
        $this->assertSame('founder', $role, 'The creator receives the founder membership role.');
    }

    public function testSlugCollisionsGetAUniqueFallback()
    {
        $owner = User::find(Fixtures::user());
        $first = FamilyService::createFamily($owner, ['name' => 'Duplicate Name Family', 'visibility' => 'private']);
        $second = FamilyService::createFamily($owner, ['name' => 'Duplicate Name Family', 'visibility' => 'private']);

        $this->assertNotEquals($first['slug'], $second['slug'], 'Two families with the same name get different slugs.');
        $this->assertTrue(Family::slugExists($second['slug']), 'The fallback slug exists.');
        $this->assertSame(0, (int) \App\Core\Database::value('SELECT COUNT(*) FROM families WHERE slug = ? AND id <> ?', [$second['slug'], $second['id']]), 'The fallback slug is unique in the table.');
    }

    public function testProfileUpdateKeepsTheSlugStable()
    {
        $owner = User::find(Fixtures::user());
        $family = FamilyService::createFamily($owner, ['name' => 'Stable Link Family', 'visibility' => 'members']);
        $originalSlug = $family['slug'];

        $updated = FamilyService::updateProfile($family, $owner, [
            'name'        => 'Renamed Family Entirely',
            'description' => 'A new description.',
            'visibility'  => 'directory',
        ]);

        $this->assertSame($originalSlug, $updated['slug'], 'Renaming a family never changes its slug (links stay valid).');
        $this->assertSame('Renamed Family Entirely', $updated['name'], 'The new name is stored.');
        $this->assertSame('directory', $updated['visibility'], 'Visibility can be updated.');
    }

    public function testFounderRoleCannotBeChanged()
    {
        $founder = User::find(Fixtures::user());
        $familyId = Fixtures::family($founder['id']);
        $family = Family::find($familyId);
        $memberRow = FamilyMember::findFor($familyId, $founder['id']);

        $error = FamilyService::changeMemberRole($family, $founder, 'founder', $memberRow, 'member');
        $this->assertNotNull($error, 'The founder role is protected.');
        $this->assertSame('founder', FamilyMember::roleFor($familyId, $founder['id']), 'The founder role is unchanged after the attempt.');
    }

    public function testNobodyCanChangeTheirOwnRole()
    {
        $founder = User::find(Fixtures::user());
        $familyId = Fixtures::family($founder['id']);
        $family = Family::find($familyId);

        $adminId = Fixtures::user();
        Fixtures::member($familyId, $adminId, 'admin');
        $adminRow = FamilyMember::findFor($familyId, $adminId);

        $error = FamilyService::changeMemberRole($family, User::find($adminId), 'admin', $adminRow, 'viewer');
        $this->assertNotNull($error, 'Changing your own role is refused.');
        $this->assertSame('admin', FamilyMember::roleFor($familyId, $adminId), 'The role stays unchanged.');
    }

    public function testOnlyTheFounderAppointsAdministrators()
    {
        $founder = User::find(Fixtures::user());
        $familyId = Fixtures::family($founder['id']);
        $family = Family::find($familyId);

        $adminId = Fixtures::user();
        Fixtures::member($familyId, $adminId, 'admin');
        $memberId = Fixtures::user();
        Fixtures::member($familyId, $memberId, 'member');
        $memberRow = FamilyMember::findFor($familyId, $memberId);

        $error = FamilyService::changeMemberRole($family, User::find($adminId), 'admin', $memberRow, 'admin');
        $this->assertNotNull($error, 'An administrator cannot appoint another administrator.');
        $this->assertSame('member', FamilyMember::roleFor($familyId, $memberId), 'The role is unchanged.');

        $error = FamilyService::changeMemberRole($family, $founder, 'founder', $memberRow, 'admin');
        $this->assertNull($error, 'The founder can appoint an administrator.');
        $this->assertSame('admin', FamilyMember::roleFor($familyId, $memberId), 'The new role is stored.');
    }

    public function testSuccessfulRoleChangeNotifiesTheMember()
    {
        $founder = User::find(Fixtures::user());
        $familyId = Fixtures::family($founder['id']);
        $family = Family::find($familyId);

        $memberId = Fixtures::user();
        Fixtures::member($familyId, $memberId, 'member');
        $memberRow = FamilyMember::findFor($familyId, $memberId);

        $error = FamilyService::changeMemberRole($family, $founder, 'founder', $memberRow, 'viewer');
        $this->assertNull($error, 'The founder can demote a member to viewer.');
        $this->assertSame('viewer', FamilyMember::roleFor($familyId, $memberId), 'The demotion is stored.');

        $notifications = \App\Core\Database::select(
            'SELECT type FROM notifications WHERE user_id = ? AND family_id = ?',
            [$memberId, $familyId]
        );
        $this->assertContains('family.role_changed', array_column($notifications, 'type'), 'The affected member is notified of the role change.');
    }

    public function testFounderAndSelfCannotBeRemoved()
    {
        $founder = User::find(Fixtures::user());
        $familyId = Fixtures::family($founder['id']);
        $family = Family::find($familyId);

        $founderRow = FamilyMember::findFor($familyId, $founder['id']);
        $error = FamilyService::removeMember($family, $founder, 'founder', $founderRow);
        $this->assertNotNull($error, 'The founder cannot be removed.');
        $this->assertSame('founder', FamilyMember::roleFor($familyId, $founder['id']), 'The founder membership survives.');

        $adminId = Fixtures::user();
        Fixtures::member($familyId, $adminId, 'admin');
        $adminRow = FamilyMember::findFor($familyId, $adminId);
        $error = FamilyService::removeMember($family, User::find($adminId), 'admin', $adminRow);
        $this->assertNotNull($error, 'Nobody removes themselves through this path.');
        $this->assertSame('admin', FamilyMember::roleFor($familyId, $adminId), 'The membership survives a self-removal attempt.');
    }

    public function testOnlyTheFounderRemovesAnAdministrator()
    {
        $founder = User::find(Fixtures::user());
        $familyId = Fixtures::family($founder['id']);
        $family = Family::find($familyId);

        $adminAId = Fixtures::user();
        $adminBId = Fixtures::user();
        Fixtures::member($familyId, $adminAId, 'admin');
        Fixtures::member($familyId, $adminBId, 'admin');
        $adminBRow = FamilyMember::findFor($familyId, $adminBId);

        $error = FamilyService::removeMember($family, User::find($adminAId), 'admin', $adminBRow);
        $this->assertNotNull($error, 'One administrator cannot remove another.');
        $this->assertSame('admin', FamilyMember::roleFor($familyId, $adminBId), 'The administrator stays in the family.');

        $error = FamilyService::removeMember($family, $founder, 'founder', $adminBRow);
        $this->assertNull($error, 'The founder can remove an administrator.');
        $this->assertNull(FamilyMember::roleFor($familyId, $adminBId), 'The removed membership is no longer active.');
    }

    public function testAncestorRecordsStayInsideTheirFamily()
    {
        $founderA = User::find(Fixtures::user());
        $familyA = Fixtures::family($founderA['id']);
        $founderB = User::find(Fixtures::user());
        $familyB = Fixtures::family($founderB['id']);

        $personId = Fixtures::ancestor($familyA, 'Scoped', 'Person', 1);

        $this->assertNotNull(Ancestor::findForFamily($familyA, $personId), 'The record is visible inside its own family.');
        $this->assertNull(Ancestor::findForFamily($familyB, $personId), 'The record is invisible to another family.');

        $updated = Ancestor::update($personId, ['surname' => 'Hijacked'], $familyB);
        $this->assertSame(0, $updated, 'Another family cannot update the record.');
        $this->assertSame('Person', Ancestor::find($personId)['surname'], 'The stored surname is unchanged.');

        $deleted = Ancestor::delete($personId, $familyB);
        $this->assertSame(0, $deleted, 'Another family cannot delete the record.');
        $this->assertNotNull(Ancestor::find($personId), 'The record still exists.');
    }

    public function testRelationshipValidationRejectsCyclesAndCrossFamilyPeople()
    {
        $founder = User::find(Fixtures::user());
        $familyId = Fixtures::family($founder['id']);
        $otherFamilyId = Fixtures::family($founder['id']);

        $parentId = Fixtures::ancestor($familyId, 'Parent', 'Cycle', 1);
        $childId = Fixtures::ancestor($familyId, 'Child', 'Cycle', 2);
        $strangerId = Fixtures::ancestor($otherFamilyId, 'Stranger', 'Elsewhere', 1);

        $this->assertNull(FamilyRelationship::validate($familyId, 'parent_of', $parentId, $childId), 'A valid parent link passes validation.');
        Fixtures::edge($familyId, $parentId, $childId, 'father');

        $error = FamilyRelationship::validate($familyId, 'parent_of', $childId, $parentId);
        $this->assertNotNull($error, 'The reverse link would create a cycle and is refused.');

        $error = FamilyRelationship::validate($familyId, 'parent_of', $parentId, $strangerId);
        $this->assertNotNull($error, 'People from another family can never be linked into this tree.');
    }
}
