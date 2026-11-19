<?php
/**
 * Ancestral Journey overview.
 *
 * @var string|null $emptyReason
 * @var array|null  $family
 * @var array       $journey
 * @var array       $stats
 * @var array|null  $journeyRow
 * @var array       $stepUrls
 */
include __DIR__ . '/_family.php';

$certLabels = [
    'verified' => 'Verified',
    'proposed' => 'Proposed',
    'unknown'  => 'Unknown',
    'disputed' => 'Disputed',
];
$certHelp = [
    'verified' => 'The record and every link connecting this person to the next generation are confirmed.',
    'proposed' => 'At least one record or relationship is still proposed and needs a family review.',
    'unknown'  => 'Not enough was recorded to confirm this person or their connections.',
    'disputed' => 'A family member has flagged this record or connection as disputed.',
];
$completed = $journeyRow && !empty($journeyRow['completed_at']);
?>
<section class="section section--tight">
    <div class="wrap">
        <?php if ($emptyReason !== null): ?>
            <?php include __DIR__ . '/_empty.php'; ?>
        <?php else: ?>
            <?php if (empty($user['email_verified_at']) && route_exists('auth.verify.notice')): ?>
                <div class="alert alert--warning" role="alert">
                    <div>
                        <strong>Your email address is not confirmed yet.</strong>
                        The journey is read-only, so you can explore it now.
                        Confirm your address when you are ready to add or edit family records.
                        <a href="<?= e(route('auth.verify.notice')) ?>">Resend the confirmation email</a>.
                    </div>
                </div>
            <?php endif; ?>
            <div class="journey-hero">
                <div class="journey-hero__copy">
                    <p class="eyebrow">Five generations, one story</p>
                    <h2>Travel back through <?= e($stats['generations']) ?> recorded generation<?= $stats['generations'] === 1 ? '' : 's' ?></h2>
                    <p>
                        Starting with <strong><?= e($journey['nodes'][$journey['root_id']]['name'] ?? 'your linked relative') ?></strong>,
                        the journey follows confirmed, proposed, unknown and disputed parent links exactly as your
                        family recorded them.
                    </p>
                    <?php if ($completed): ?>
                        <p><span class="badge badge--verified">Journey completed</span></p>
                    <?php elseif ($journeyRow): ?>
                        <p><span class="badge badge--pending">Journey in progress</span></p>
                    <?php endif; ?>
                    <div class="form-actions">
                        <a class="btn btn--primary" href="<?= e($stepUrls['animate']) ?>">Begin the cinematic journey</a>
                        <a class="btn btn--outline" href="<?= e($stepUrls['tree']) ?>">Open the interactive tree</a>
                        <a class="btn btn--ghost" href="<?= e($stepUrls['timeline']) ?>">See the timeline</a>
                    </div>
                </div>
                <dl class="journey-hero__facts">
                    <div><dt>People in view</dt><dd><?= e($stats['total']) ?></dd></div>
                    <div><dt>Generations</dt><dd><?= e($stats['generations']) ?></dd></div>
                    <div><dt>Branches</dt><dd><?= e($stats['branches']) ?></dd></div>
                    <div><dt>Recorded years</dt><dd>
                        <?php if ($stats['oldest_birth_year'] !== null): ?>
                            <?= e($stats['oldest_birth_year']) ?> &ndash; <?= e($stats['newest_birth_year']) ?>
                        <?php else: ?>
                            Not dated
                        <?php endif; ?>
                    </dd></div>
                </dl>
            </div>

            <div class="journey-stat-grid" aria-label="Certainty summary">
                <?php foreach ($certLabels as $key => $label): ?>
                    <div class="journey-stat journey-stat--<?= e($key) ?>">
                        <span class="journey-stat__value"><?= e($stats[$key]) ?></span>
                        <span class="journey-stat__label"><?= e($label) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="journey-grid">
                <section class="journey-panel">
                    <h3>What the colours mean</h3>
                    <ul class="journey-legend">
                        <?php foreach ($certLabels as $key => $label): ?>
                            <li>
                                <span class="journey-dot journey-dot--<?= e($key) ?>" aria-hidden="true"></span>
                                <div>
                                    <strong><?= e($label) ?></strong>
                                    <p><?= e($certHelp[$key]) ?></p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <section class="journey-panel">
                    <h3>What this journey will not do</h3>
                    <ul class="journey-rules">
                        <li>It never invents an ancestor when a parent was not recorded.</li>
                        <li>It never turns a surname, birth year or place into a relationship.</li>
                        <li>It never merges two records automatically, even when names look identical.</li>
                        <li>It keeps disputed links visible and clearly marked for review.</li>
                    </ul>
                    <?php if ($stats['missing_parent_links'] > 0): ?>
                        <p class="text-small text-muted">
                            <?= e($stats['missing_parent_links']) ?> parent slot<?= $stats['missing_parent_links'] === 1 ? '' : 's' ?>
                            in this view are still unrecorded. They stay blank.
                        </p>
                    <?php endif; ?>
                </section>
            </div>

            <?php if ($stats['pedigree_collapse'] > 0): ?>
                <section class="journey-panel journey-panel--wide">
                    <h3>Pedigree collapse detected</h3>
                    <p class="text-muted">
                        The same ancestor is reached through more than one lineage. This is common in
                        closely connected families. The tree draws the person once and marks the shared
                        branches instead of pretending there are duplicates.
                    </p>
                    <ul class="journey-shared-list">
                        <?php foreach ($stats['shared_ancestors'] as $shared): ?>
                            <li>
                                <strong><?= e($shared['name']) ?></strong>
                                <span class="badge badge--neutral"><?= e($shared['multiplicity']) ?> lineage paths</span>
                                <span class="text-small text-muted"><?= e(\App\Controllers\AncestralJourneyController::generationLabel($shared['level'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if (!empty($stats['branch_list'])): ?>
                <section class="journey-panel journey-panel--wide">
                    <h3>Family branches in this view</h3>
                    <ul class="journey-chips">
                        <?php foreach ($stats['branch_list'] as $branch): ?>
                            <li class="journey-chip"><?= e($branch) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if ($stats['living'] > 0): ?>
                <p class="text-small text-muted">
                    <?= e($stats['living']) ?> living <?= $stats['living'] === 1 ? 'person is' : 'people are' ?>
                    included. Consider privacy before sharing screenshots outside the family.
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
