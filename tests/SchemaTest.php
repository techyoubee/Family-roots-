<?php

namespace Tests;

use App\Core\Database;
use App\Models\Ancestor;
use App\Models\Family;
use App\Models\FamilyRelationship;
use App\Models\User;

/**
 * Schema-level guarantees: the tables the application depends on exist, the
 * uniqueness and check constraints actually fire, cascade rules behave and a
 * person's account cannot be duplicated into their ancestor records.
 */
final class SchemaTest extends TestCase
{
    public function testRequiredTablesExist()
    {
        $required = [
            'users', 'user_tokens', 'families', 'family_members', 'ancestors',
            'family_relationships', 'membership_requests', 'membership_request_claims',
            'verification_evidence', 'audit_logs', 'notifications', 'rate_limit_hits',
            'record_corrections', 'family_verifications', 'schema_migrations',
        ];
        foreach ($required as $table) {
            $this->assertTrue(Database::tableExists($table), 'Missing table: ' . $table);
        }
    }

    public function testMigrationsAreRecorded()
    {
        $applied = Database::select('SELECT version FROM schema_migrations ORDER BY version');
        $this->assertGreaterThan(0, count($applied), 'No migrations recorded.');
        $this->assertContains('001_initial_schema.sql', array_column($applied, 'version'));
    }

    public function testEmailUniquenessIsEnforced()
    {
        $email = Fixtures::unique('duplicate') . '@example.test';
        User::create([
            'email'         => $email,
            'password_hash' => password_hash('x', PASSWORD_DEFAULT),
            'full_name'     => 'First Person',
            'status'        => 'active',
        ]);

        $this->expectException(\PDOException::class, static function () use ($email) {
            User::create([
                'email'         => $email,
                'password_hash' => password_hash('y', PASSWORD_DEFAULT),
                'full_name'     => 'Second Person',
                'status'        => 'active',
            ]);
        });
    }

    public function testFamilyMembershipIsUniquePerUserAndFamily()
    {
        $founder = Fixtures::user();
        $familyId = Fixtures::family($founder);
        $member = Fixtures::user();

        Fixtures::member($familyId, $member, 'member');

        $this->expectException(\PDOException::class, static function () use ($familyId, $member) {
            Database::insert('family_members', [
                'family_id'  => $familyId,
                'user_id'    => $member,
                'role'       => 'viewer',
                'status'     => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        });
    }

    public function testDuplicateRelationshipEdgesAreRejected()
    {
        $founder = Fixtures::user();
        $familyId = Fixtures::family($founder);
        $parent = Fixtures::ancestor($familyId, 'Parent One', 'Test', 1);
        $child = Fixtures::ancestor($familyId, 'Child One', 'Test', 2);

        Fixtures::edge($familyId, $parent, $child, 'father');

        $this->expectException(\PDOException::class, static function () use ($familyId, $parent, $child) {
            Database::insert('family_relationships', [
                'family_id'         => $familyId,
                'relationship_kind' => 'parent_of',
                'from_ancestor_id'  => $parent,
                'to_ancestor_id'    => $child,
                'status'            => 'confirmed',
                'created_at'        => date('Y-m-d H:i:s'),
                'updated_at'        => date('Y-m-d H:i:s'),
            ]);
        });
    }

    public function testSelfRelationshipIsRejectedByCheckConstraint()
    {
        $founder = Fixtures::user();
        $familyId = Fixtures::family($founder);
        $person = Fixtures::ancestor($familyId, 'Only Person', 'Test', 2);

        $this->expectException(\PDOException::class, static function () use ($familyId, $person) {
            Database::insert('family_relationships', [
                'family_id'         => $familyId,
                'relationship_kind' => 'parent_of',
                'from_ancestor_id'  => $person,
                'to_ancestor_id'    => $person,
                'status'            => 'proposed',
                'created_at'        => date('Y-m-d H:i:s'),
                'updated_at'        => date('Y-m-d H:i:s'),
            ]);
        });
    }

    public function testDeletingAFamilyCascadesToItsRecords()
    {
        $founder = Fixtures::user();
        $familyId = Fixtures::family($founder);
        $parent = Fixtures::ancestor($familyId, 'Cascade Parent', 'Test', 1);
        $child = Fixtures::ancestor($familyId, 'Cascade Child', 'Test', 2);
        Fixtures::edge($familyId, $parent, $child);

        Database::execute('DELETE FROM families WHERE id = ?', [$familyId]);

        $this->assertSame(0, Ancestor::countForFamily($familyId));
        $this->assertSame(0, FamilyRelationship::countForFamily($familyId));
        $this->assertSame(0, (int) Database::value('SELECT COUNT(*) FROM family_members WHERE family_id = ?', [$familyId]));
    }

    public function testAncestorCannotReferenceAnUnknownFamily()
    {
        $this->expectException(\PDOException::class, static function () {
            Database::insert('ancestors', [
                'family_id'   => 987654321,
                'given_names' => 'Ghost Person',
                'record_status' => 'proposed',
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
        });
    }

    public function testAncestorLinkMustReferenceAnExistingUserAccount()
    {
        $founder = Fixtures::user();
        $familyId = Fixtures::family($founder);

        $this->expectException(\PDOException::class, static function () use ($familyId) {
            Database::insert('ancestors', [
                'family_id'      => $familyId,
                'given_names'    => 'Record With Bad Link',
                'record_status'  => 'proposed',
                'linked_user_id' => 987654321,
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
        });

        // A legitimate link is stored on the ancestor record, not on the account.
        $linkedUserId = Fixtures::user();
        $ancestorId = Fixtures::ancestor($familyId, 'Legit Linking', 'Person', 1, ['linked_user_id' => $linkedUserId]);
        $row = Ancestor::find($ancestorId);
        $this->assertSame($linkedUserId, $row['linked_user_id']);
    }

    public function testDuplicateAncestorRecordsAreDetectableWithoutMerging()
    {
        $founder = Fixtures::user();
        $familyId = Fixtures::family($founder);
        Fixtures::ancestor($familyId, 'Same Name', 'Duplicate', 2);
        Fixtures::ancestor($familyId, 'Same Name', 'Duplicate', 2);

        $normalized = \App\Core\Str::normalizeName('Same Name Duplicate');
        $candidates = Ancestor::duplicateCandidates($familyId, $normalized);
        $this->assertSame(2, count($candidates), 'Both records should be reported as duplicate candidates.');

        $bothStillPresent = Ancestor::countForFamily($familyId);
        $this->assertSame(2, $bothStillPresent, 'Duplicate candidates must never be merged automatically.');
    }

    public function testEvidenceTableRequiresATarget()
    {
        $userId = Fixtures::user();
        $this->expectException(\PDOException::class, static function () use ($userId) {
            Database::insert('verification_evidence', [
                'public_id'           => \App\Core\Str::uuid4(),
                'uploaded_by_user_id' => $userId,
                'title'               => 'Nowhere document',
                'original_filename'   => 'a.pdf',
                'stored_name'         => \App\Core\Str::uuid4() . '.pdf',
                'mime_type'           => 'application/pdf',
                'byte_size'           => 10,
                'sha256'              => hash('sha256', 'x'),
                'created_at'          => date('Y-m-d H:i:s'),
                'updated_at'          => date('Y-m-d H:i:s'),
            ]);
        });
    }

    public function testFamilyPublicIdentifierAndSlugAreUnique()
    {
        $founder = Fixtures::user();
        // Unique per run so a --keep rerun does not collide with a leftover row.
        $slug = 'unique-slug-check-' . substr(\App\Core\Str::randomToken(4), 0, 8);
        $familyId = Fixtures::family($founder, ['slug' => $slug]);

        try {
            $family = Family::find($familyId);
            $this->assertNotNull($family['public_id']);
            $this->assertTrue(strpos($family['public_id'], 'FAM-') === 0);

            $this->expectException(\PDOException::class, static function () use ($founder, $slug) {
                Family::create([
                    'name'            => 'Duplicate Slug Family',
                    'slug'            => $slug,
                    'founder_user_id' => $founder,
                ]);
            });
        } finally {
            Database::execute('DELETE FROM families WHERE id = ?', [$familyId]);
        }
    }
}
