<?php
/**
 * Chronological timeline of recorded birth, union and death events.
 *
 * @var string|null $emptyReason
 * @var array       $journey
 * @var array       $timeline
 * @var array       $stats
 * @var array       $family
 * @var array       $stepUrls
 */
include __DIR__ . '/_family.php';

$certLabels = ['verified' => 'Verified', 'proposed' => 'Proposed', 'unknown' => 'Unknown', 'disputed' => 'Disputed'];
$byYear = [];
foreach ($timeline['events'] as $event) {
    $byYear[(int) $event['year']][] = $event;
}
ksort($byYear);
?>
<section class="section section--tight">
    <div class="wrap">
        <?php if ($emptyReason !== null): ?>
            <?php include __DIR__ . '/_empty.php'; ?>
        <?php else: ?>
            <div class="journey-timeline-head">
                <div>
                    <p class="eyebrow">Chronology</p>
                    <h2><?= e($timeline['total']) ?> recorded event<?= $timeline['total'] === 1 ? '' : 's' ?></h2>
                    <p class="text-muted">
                        <?php if ($timeline['min_year'] !== null): ?>
                            Spanning <?= e($timeline['min_year']) ?> to <?= e($timeline['max_year']) ?>.
                        <?php endif; ?>
                        Only events with a recorded year are placed on the line. Undated people are
                        listed in the tree instead of being given an invented date.
                    </p>
                </div>
                <div class="form-actions">
                    <a class="btn btn--primary" href="<?= e($stepUrls['tree']) ?>">Open the tree</a>
                    <a class="btn btn--ghost" href="<?= e($stepUrls['animate']) ?>">Replay journey</a>
                </div>
            </div>

            <?php if ($timeline['undated'] > 0): ?>
                <p class="text-small text-muted">
                    <?= e($timeline['undated']) ?> <?= $timeline['undated'] === 1 ? 'person has' : 'people have' ?>
                    no usable year recorded. Their relationships still appear in the tree.
                </p>
            <?php endif; ?>

            <?php if ($timeline['truncated']): ?>
                <div class="alert alert--info" role="status">
                    <div>Showing the first <?= e(count($timeline['events'])) ?> of <?= e($timeline['total']) ?> events.</div>
                </div>
            <?php endif; ?>

            <?php if (empty($byYear)): ?>
                <div class="journey-empty card">
                    <div class="card__body">
                        <h3>No dated events yet</h3>
                        <p class="text-muted">
                            Nobody in this five-generation view has a recorded birth, death or union year.
                            Add dates to the ancestor records to build a timeline.
                        </p>
                        <a class="btn btn--outline" href="<?= e($stepUrls['tree']) ?>">Explore the tree instead</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="journey-timeline">
                    <div class="journey-timeline__line" aria-hidden="true"></div>
                    <?php foreach ($byYear as $year => $events): ?>
                        <section class="journey-timeline__year" aria-labelledby="journey-year-<?= e($year) ?>">
                            <h3 class="journey-timeline__year-label" id="journey-year-<?= e($year) ?>"><?= e($year) ?></h3>
                            <ol class="journey-timeline__events">
                                <?php foreach ($events as $event): ?>
                                    <li class="journey-event journey-event--<?= e($event['type']) ?>">
                                        <span class="journey-event__marker" aria-hidden="true"></span>
                                        <div class="journey-event__body">
                                            <p class="journey-event__title">
                                                <span class="journey-event__kind"><?= e($event['title']) ?></span>
                                                <?= e($event['person_name']) ?>
                                            </p>
                                            <?php if ($event['place'] !== '' || $event['detail'] !== ''): ?>
                                                <p class="journey-event__meta">
                                                    <?= e($event['place']) ?><?= $event['place'] !== '' && $event['detail'] !== '' ? ' &middot; ' : '' ?><?= e($event['detail']) ?>
                                                </p>
                                            <?php endif; ?>
                                            <span class="journey-badge journey-badge--<?= e($event['certainty']) ?>">
                                                <?= e($certLabels[$event['certainty']] ?? 'Unknown') ?>
                                            </span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($stats['pedigree_collapse'] > 0): ?>
                <p class="text-small text-muted journey-footnote">
                    <?= e($stats['pedigree_collapse']) ?> ancestor<?= $stats['pedigree_collapse'] === 1 ? ' is' : 's are' ?>
                    shared across multiple lineage paths. Their events appear once per person, not once per branch.
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
