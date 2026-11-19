<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Str;

/**
 * Demo seed data.
 *
 * Every person seeded here is a FICTIONAL sample record used to exercise the
 * tree, the matching engine and the review workflow. Nothing in this file
 * asserts real BH KIRU history: seeded families start as `unverified` and
 * seeded ancestors are marked `proposed` with an explicit sample note.
 *
 * The seed is idempotent - running it twice does nothing the second time.
 */
final class Seeder
{
    const DEMO_PASSWORD = 'FamilyRoots!Demo2026';

    /** @return array<string> human readable summary lines */
    public static function run()
    {
        if (Database::value('SELECT COUNT(*) FROM families WHERE slug = ?', ['bh-kiru']) > 0) {
            return ['demo data already present - skipped (use database/install.php --fresh to reset)'];
        }

        $summary = [];
        $now = date('Y-m-d H:i:s');

        $users = self::seedUsers($now);
        $summary[] = 'created ' . count($users) . ' demo accounts (password: ' . self::DEMO_PASSWORD . ')';

        $bhKiru = self::seedBhKiruFamily($users, $now);
        $summary[] = 'created family "' . $bhKiru['name'] . '" (' . $bhKiru['public_id'] . ', status: ' . $bhKiru['verification_status'] . ')';

        $amani = self::seedAmaniFamily($users, $now);
        $summary[] = 'created family "' . $amani['name'] . '" (' . $amani['public_id'] . ', status: ' . $amani['verification_status'] . ')';

        $summary[] = 'created ' . self::countTable('ancestors') . ' ancestor records in total';
        $summary[] = 'created ' . self::countTable('family_relationships') . ' relationship records in total';

        return $summary;
    }

    private static function seedUsers($now)
    {
        $users = [];
        $definitions = [
            ['platform.admin@family-roots.local', 'Platform Administrator', 'active', 1],
            ['founder@bh-kiru.example', 'BH KIRU Founder', 'active', 0],
            ['moderator@bh-kiru.example', 'BH KIRU Moderator', 'active', 0],
            ['founder@amani-family.example', 'AMANI Family Founder', 'active', 0],
            ['rina.aman@example.test', 'Rina Aman', 'active', 0],
            ['noor.hakim@example.test', 'Noor Hakim', 'active', 0],
        ];

        foreach ($definitions as $def) {
            list($email, $name, $status, $isAdmin) = $def;
            $users[$email] = Database::insert('users', [
                'public_id'         => Str::uuid4(),
                'email'             => $email,
                'password_hash'     => password_hash(self::DEMO_PASSWORD, PASSWORD_DEFAULT),
                'full_name'         => $name,
                'is_platform_admin' => $isAdmin ? 1 : 0,
                'status'            => $status,
                'email_verified_at' => $now,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
        }

        return $users;
    }

    private static function seedBhKiruFamily(array $users, $now)
    {
        $familyId = Database::insert('families', [
            'public_id'           => 'FAM-' . strtoupper(substr(Str::randomToken(6), 0, 6)),
            'slug'                => 'bh-kiru',
            'name'                => 'BH KIRU',
            'description'         => 'Example family profile for the Family Roots platform. This profile is created by the demo seed and is NOT historically verified.',
            'history'             => 'Demonstration only. The ancestor records attached to this profile are fictional samples supplied so the family tree, the matching engine and the administrator review queue can be tested. They do not document real people and no claim about real BH KIRU history is made here.',
            'origins'             => 'Sample origins (demo data)',
            'founding_note'       => 'Founding ancestor records are pending documentation by the family administrators.',
            'visibility'          => 'directory',
            'verification_status' => 'unverified',
            'founder_user_id'     => $users['founder@bh-kiru.example'],
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        Database::insert('family_members', [
            'family_id'   => $familyId,
            'user_id'     => $users['founder@bh-kiru.example'],
            'role'        => 'founder',
            'status'      => 'active',
            'joined_at'   => $now,
            'approved_at' => $now,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
        Database::insert('family_members', [
            'family_id'   => $familyId,
            'user_id'     => $users['moderator@bh-kiru.example'],
            'role'        => 'admin',
            'status'      => 'active',
            'joined_at'   => $now,
            'approved_at' => $now,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $sampleNote = 'Fictional sample record created by the demo seed. Not a verified historical person.';
        $make = static function (array $a) use ($familyId, $sampleNote, $users) {
            return Database::insert('ancestors', [
                'family_id'       => $familyId,
                'given_names'     => $a['given'],
                'surname'         => $a['surname'],
                'name_normalized' => Str::normalizeName($a['given'] . ' ' . ($a['surname'] ?? '')),
                'sex'             => $a['sex'],
                'is_living'       => !empty($a['living']) ? 1 : 0,
                'birth_date_text' => $a['born'],
                'death_date_text' => $a['died'],
                'generation'      => $a['gen'],
                'branch'          => $a['branch'],
                'notes'           => $sampleNote,
                'evidence_source' => 'Demo seed (no external source)',
                'record_status'   => 'proposed',
                'visibility'      => 'members',
                'created_by_user_id' => $users['founder@bh-kiru.example'],
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
        };

        // Five generations, oldest first. Generation 5 = current generation.
        $g1f = $make(['given' => 'Karma Tenzin', 'surname' => 'Dorji', 'sex' => 'male', 'born' => 'about 1884', 'died' => 'about 1949', 'gen' => 1, 'branch' => 'Founding branch']);
        $g1m = $make(['given' => 'Pelden Dolma', 'surname' => 'Dorji', 'sex' => 'female', 'born' => 'about 1889', 'died' => 'about 1952', 'gen' => 1, 'branch' => 'Founding branch']);

        $g2f = $make(['given' => 'Tshering Wangdi', 'surname' => 'Dorji', 'sex' => 'male', 'born' => 'about 1912', 'died' => 'about 1978', 'gen' => 2, 'branch' => 'Elder line']);
        $g2m = $make(['given' => 'Dechen Yangzom', 'surname' => 'Wangmo', 'sex' => 'female', 'born' => 'about 1917', 'died' => 'about 1983', 'gen' => 2, 'branch' => 'Elder line']);

        $g3f = $make(['given' => 'Jigme Dorji', 'surname' => 'Dorji', 'sex' => 'male', 'born' => '1939', 'died' => '2006', 'gen' => 3, 'branch' => 'Elder line']);
        $g3m = $make(['given' => 'Kunzang Choden', 'surname' => 'Dorji', 'sex' => 'female', 'born' => '1943', 'died' => '', 'gen' => 3, 'branch' => 'Elder line']);

        $g4f = $make(['given' => 'Sangay Ngedup', 'surname' => 'Dorji', 'sex' => 'male', 'born' => '1966', 'died' => '', 'gen' => 4, 'branch' => 'Elder line', 'living' => true]);
        $g4m = $make(['given' => 'Yangki Lhamo', 'surname' => 'Dorji', 'sex' => 'female', 'born' => '1969', 'died' => '', 'gen' => 4, 'branch' => 'Elder line', 'living' => true]);

        $g5a = $make(['given' => 'Tenpa Dorji', 'surname' => 'Dorji', 'sex' => 'male', 'born' => '1992', 'died' => '', 'gen' => 5, 'branch' => 'Elder line', 'living' => true]);
        $g5b = $make(['given' => 'Lhamo Dolma', 'surname' => 'Dorji', 'sex' => 'female', 'born' => '1995', 'died' => '', 'gen' => 5, 'branch' => 'Elder line', 'living' => true]);

        $edges = [
            [$g1f, $g2f, 'father'], [$g1m, $g2f, 'mother'],
            [$g2f, $g3f, 'father'], [$g2m, $g3f, 'mother'],
            [$g3f, $g4f, 'father'], [$g3m, $g4f, 'mother'],
            [$g4f, $g5a, 'father'], [$g4m, $g5a, 'mother'],
            [$g4f, $g5b, 'father'], [$g4m, $g5b, 'mother'],
        ];
        foreach ($edges as $edge) {
            self::insertEdge($familyId, $edge[0], $edge[1], $edge[2], $users['founder@bh-kiru.example'], $now);
        }
        self::insertUnion($familyId, $g1f, $g1m, $now);
        self::insertUnion($familyId, $g2f, $g2m, $now);
        self::insertUnion($familyId, $g3f, $g3m, $now);
        self::insertUnion($familyId, $g4f, $g4m, $now);

        // Link the demo members to their own tree records.
        Database::update('family_members', ['ancestor_id' => $g5a], 'family_id = ? AND user_id = ?', [$familyId, $users['founder@bh-kiru.example']]);
        Database::update('family_members', ['ancestor_id' => $g4f], 'family_id = ? AND user_id = ?', [$familyId, $users['moderator@bh-kiru.example']]);

        // Tenpa is the seeded user's own record.
        Database::update('ancestors', ['linked_user_id' => $users['founder@bh-kiru.example']], 'id = ?', [$g5a]);

        self::audit($familyId, $users['founder@bh-kiru.example'], 'seed.family_created', 'families', $familyId, 'Demo seed created the BH KIRU profile (unverified) with fictional sample ancestors.');

        return Database::first('SELECT * FROM families WHERE id = ?', [$familyId]);
    }

    private static function seedAmaniFamily(array $users, $now)
    {
        $familyId = Database::insert('families', [
            'public_id'           => 'FAM-' . strtoupper(substr(Str::randomToken(6), 0, 6)),
            'slug'                => 'amani-demo',
            'name'                => 'AMANI Sample Family',
            'description'         => 'Second, independent demo family used to verify that families cannot see each other\'s private records.',
            'history'             => 'Fictional sample profile. No real genealogy is claimed.',
            'origins'             => 'Sample origins (demo data)',
            'visibility'          => 'private',
            'verification_status' => 'unverified',
            'founder_user_id'     => $users['founder@amani-family.example'],
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        Database::insert('family_members', [
            'family_id'   => $familyId,
            'user_id'     => $users['founder@amani-family.example'],
            'role'        => 'founder',
            'status'      => 'active',
            'joined_at'   => $now,
            'approved_at' => $now,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $sampleNote = 'Fictional sample record created by the demo seed. Not a verified historical person.';
        $a1 = Database::insert('ancestors', [
            'family_id'       => $familyId,
            'given_names'     => 'Amani Juma',
            'surname'         => 'Hakim',
            'name_normalized' => Str::normalizeName('Amani Juma Hakim'),
            'sex'             => 'female',
            'is_living'       => 0,
            'birth_date_text' => 'about 1930',
            'death_date_text' => 'about 1998',
            'generation'      => 1,
            'notes'           => $sampleNote,
            'record_status'   => 'proposed',
            'visibility'      => 'members',
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
        $a2 = Database::insert('ancestors', [
            'family_id'       => $familyId,
            'given_names'     => 'Bakari Amani',
            'surname'         => 'Hakim',
            'name_normalized' => Str::normalizeName('Bakari Amani Hakim'),
            'sex'             => 'male',
            'is_living'       => 1,
            'birth_date_text' => '1958',
            'generation'      => 2,
            'notes'           => $sampleNote,
            'record_status'   => 'proposed',
            'visibility'      => 'members',
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
        Database::update('family_members', ['ancestor_id' => $a2], 'family_id = ? AND user_id = ?', [$familyId, $users['founder@amani-family.example']]);

        self::insertEdge($familyId, $a1, $a2, 'mother', $users['founder@amani-family.example'], $now);

        self::audit($familyId, $users['founder@amani-family.example'], 'seed.family_created', 'families', $familyId, 'Demo seed created an independent second family profile.');

        return Database::first('SELECT * FROM families WHERE id = ?', [$familyId]);
    }

    private static function insertEdge($familyId, $from, $to, $parentRole, $actorUserId, $now)
    {
        Database::insert('family_relationships', [
            'family_id'          => $familyId,
            'relationship_kind'  => 'parent_of',
            'from_ancestor_id'   => $from,
            'to_ancestor_id'     => $to,
            'parent_role'        => $parentRole,
            'relationship_type'  => 'unknown',
            'status'             => 'proposed',
            'notes'              => 'Fictional sample relationship from the demo seed.',
            'created_by_user_id' => $actorUserId,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);
    }

    private static function insertUnion($familyId, $a, $b, $now)
    {
        Database::insert('family_relationships', [
            'family_id'          => $familyId,
            'relationship_kind'  => 'spouse_of',
            'from_ancestor_id'   => $a,
            'to_ancestor_id'     => $b,
            'relationship_type'  => 'unknown',
            'status'             => 'proposed',
            'notes'              => 'Fictional sample partnership from the demo seed.',
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);
    }

    private static function audit($familyId, $actorId, $action, $entityType, $entityId, $summary)
    {
        Database::insert('audit_logs', [
            'family_id'     => $familyId,
            'actor_user_id' => $actorId,
            'action'        => $action,
            'entity_type'   => $entityType,
            'entity_id'     => (int) $entityId,
            'summary'       => $summary,
            'ip_address'    => '127.0.0.1',
            'user_agent'    => 'installer',
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    private static function countTable($table)
    {
        return (int) Database::value('SELECT COUNT(*) FROM `' . preg_replace('/[^a-z_]/i', '', $table) . '`');
    }
}
