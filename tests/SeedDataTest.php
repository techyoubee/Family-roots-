<?php

namespace Tests;

use App\Core\Database;
use App\Models\Ancestor;
use App\Models\Family;
use App\Models\FamilyRelationship;
use App\Services\Seeder;

/**
 * The demo seed must be honest about what it is: a demonstration profile whose
 * ancestors are fictional sample records, always created unverified.
 */
final class SeedDataTest extends TestCase
{
    private static $seeded = false;

    public function setUp()
    {
        if (!self::$seeded) {
            Seeder::run();
            self::$seeded = true;
        }
    }

    private function bhKiru()
    {
        return Family::findBySlug('bh-kiru');
    }

    public function testBhKiruProfileExistsAndIsNotAutomaticallyVerified()
    {
        $family = $this->bhKiru();
        $this->assertNotNull($family, 'The BH KIRU example family should exist.');
        $this->assertSame('BH KIRU', $family['name']);
        $this->assertSame('unverified', $family['verification_status'], 'Creating a profile must never imply historical verification.');
        $this->assertNull($family['verified_at']);
        $this->assertNotNull($family['public_id']);
    }

    public function testSeededAncestorsAreLabelledFictionalAndProposed()
    {
        $family = $this->bhKiru();
        $ancestors = Ancestor::forFamily($family['id'], ['limit' => 100]);
        $this->assertGreaterThan(0, count($ancestors), 'BH KIRU should carry sample ancestor records.');

        foreach ($ancestors as $ancestor) {
            $this->assertSame('proposed', $ancestor['record_status'], 'Seeded records must not be marked confirmed.');
            $this->assertContains('Fictional sample record', (string) $ancestor['notes']);
        }
    }

    public function testSeedCoversFiveGenerations()
    {
        $family = $this->bhKiru();
        $counts = Ancestor::generationCounts($family['id']);
        $generations = array_map('intval', array_column($counts, 'generation'));
        foreach ([1, 2, 3, 4, 5] as $generation) {
            $this->assertContains($generation, $generations, 'Missing generation ' . $generation . ' in the sample tree.');
        }
    }

    public function testFiveGenerationTraversalFromTheYoungestSampleMember()
    {
        $family = $this->bhKiru();
        $youngest = Database::first(
            'SELECT * FROM ancestors WHERE family_id = ? ORDER BY generation DESC, id ASC LIMIT 1',
            [$family['id']]
        );
        $this->assertNotNull($youngest);

        $ancestors = FamilyRelationship::ancestorsOf($youngest['id'], 5);
        // Four older generations above the youngest record (parents to 4x great-grandparents).
        $this->assertGreaterThan(0, count($ancestors));

        $oldest = Database::first(
            'SELECT * FROM ancestors WHERE family_id = ? ORDER BY generation ASC, id ASC LIMIT 1',
            [$family['id']]
        );
        $this->assertContains((int) $oldest['id'], array_keys($ancestors), 'The founding generation must be reachable within five generations.');
    }

    public function testSecondFamilyExistsIndependently()
    {
        $amani = Family::findBySlug('amani-demo');
        $this->assertNotNull($amani, 'A second independent family should exist for isolation testing.');
        $this->assertSame('private', $amani['visibility']);
        $this->assertSame('unverified', $amani['verification_status']);
    }

    public function testFamiliesDoNotShareAncestorRecords()
    {
        $bhKiru = $this->bhKiru();
        $amani = Family::findBySlug('amani-demo');

        $shared = (int) Database::value(
            'SELECT COUNT(*) FROM ancestors a JOIN ancestors b ON a.id = b.id WHERE a.family_id = ? AND b.family_id = ?',
            [$bhKiru['id'], $amani['id']]
        );
        $this->assertSame(0, $shared);

        $crossEdges = (int) Database::value(
            'SELECT COUNT(*) FROM family_relationships r
              JOIN ancestors a ON a.id = r.from_ancestor_id
              JOIN ancestors b ON b.id = r.to_ancestor_id
             WHERE a.family_id <> b.family_id',
            []
        );
        $this->assertSame(0, $crossEdges, 'No relationship may link two different families.');
    }

    public function testSeedIsIdempotent()
    {
        $before = (int) Database::value('SELECT COUNT(*) FROM families');
        Seeder::run();
        $after = (int) Database::value('SELECT COUNT(*) FROM families');
        $this->assertSame($before, $after, 'Re-running the seed must not duplicate data.');
    }
}
