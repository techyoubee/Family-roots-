<?php
/**
 * Cinematic journey: generation-by-generation reveal.
 *
 * The page is fully usable without JavaScript: a static group of generations
 * is rendered below the stage and only hidden once the script has initialised.
 *
 * @var string|null $emptyReason
 * @var array       $journey
 * @var array       $payload
 * @var array       $family
 * @var array       $stepUrls
 */
include __DIR__ . '/_family.php';

$json = json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    $json = '{}';
}
$levelCount = max(1, count($journey['levels']));
$rootNode = $journey['root_id'] !== null && isset($journey['nodes'][$journey['root_id']])
    ? $journey['nodes'][$journey['root_id']]
    : null;
$certLabels = ['verified' => 'Verified', 'proposed' => 'Proposed', 'unknown' => 'Unknown', 'disputed' => 'Disputed'];
?>
<section class="section section--tight">
    <div class="wrap">
        <?php if ($emptyReason !== null): ?>
            <?php include __DIR__ . '/_empty.php'; ?>
        <?php else: ?>
            <div class="journey-animate" data-journey-animate>
                <div class="journey-stage" data-journey-stage>
                    <div class="journey-stage__sky" aria-hidden="true">
                        <span class="journey-star journey-star--1"></span>
                        <span class="journey-star journey-star--2"></span>
                        <span class="journey-star journey-star--3"></span>
                        <span class="journey-star journey-star--4"></span>
                        <span class="journey-star journey-star--5"></span>
                    </div>

                    <div class="journey-stage__top">
                        <div class="journey-progress" role="progressbar" aria-valuemin="1"
                             aria-valuemax="<?= e($levelCount) ?>" aria-valuenow="1"
                             aria-label="Generations revealed" data-journey-progressbar>
                            <span data-journey-progress></span>
                        </div>
                        <p class="journey-stage__counter">
                            Generation <span data-journey-level>0</span>
                            <span class="text-muted">of <?= e($levelCount - 1) ?> back</span>
                        </p>
                    </div>

                    <div class="journey-stage__card" data-journey-card>
                        <p class="journey-stage__kicker" data-journey-kicker>Focus person</p>
                        <h2 data-journey-name><?= e($rootNode ? $rootNode['name'] : 'No person selected') ?></h2>
                        <p class="journey-stage__years" data-journey-years>
                            <?php if ($rootNode): ?>
                                <?= e($rootNode['birth_year'] !== null ? $rootNode['birth_year'] : '?') ?><?= $rootNode['living'] ? ' &ndash; living' : ($rootNode['death_year'] !== null ? ' &ndash; ' . $rootNode['death_year'] : '') ?>
                            <?php endif; ?>
                        </p>
                        <dl class="journey-stage__meta">
                            <div><dt>Record</dt><dd data-journey-record><?= e($certLabels[$rootNode['certainty']] ?? 'Unknown') ?></dd></div>
                            <div><dt>Generation</dt><dd data-journey-generation><?= e($rootNode ? $rootNode['generation'] : '') ?></dd></div>
                            <div><dt>Branch</dt><dd data-journey-branch><?= e($rootNode && $rootNode['branch'] ? $rootNode['branch'] : 'Not recorded') ?></dd></div>
                            <div><dt>Place</dt><dd data-journey-place><?= e($rootNode ? trim(($rootNode['person']['birth_place'] ?? '') . ' ' . ($rootNode['person']['death_place'] ?? '')) : '') ?: 'Not recorded' ?></dd></div>
                        </dl>
                        <p class="journey-stage__note" data-journey-note></p>
                    </div>

                    <ol class="journey-stage__trail" data-journey-trail aria-label="Generations revealed"></ol>

                    <div class="journey-stage__controls">
                        <button type="button" class="btn btn--outline" data-journey-prev disabled>Back</button>
                        <button type="button" class="btn btn--primary" data-journey-play data-playing="false">Play the journey</button>
                        <button type="button" class="btn btn--outline" data-journey-next>Next generation</button>
                    </div>
                </div>

                <div class="journey-complete-row">
                    <form method="post" action="<?= e($stepUrls['complete']) ?>" class="journey-complete-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="family_id" value="<?= e($family['id']) ?>">
                        <button type="submit" class="btn btn--primary">Finish and open the tree</button>
                    </form>
                    <a class="btn btn--ghost" href="<?= e($stepUrls['tree']) ?>">Skip the animation</a>
                    <a class="btn btn--ghost" href="<?= e($stepUrls['timeline']) ?>">Timeline</a>
                </div>

                <?php if ($truncated): ?>
                    <div class="alert alert--info" role="status">
                        <div>Showing the first 500 people in this family. Narrow the records to trace a smaller branch.</div>
                    </div>
                <?php endif; ?>

                <!-- Static, accessible version: hidden by the script after init. -->
                <div class="journey-static" data-journey-fallback>
                    <h2>All generations</h2>
                    <?php foreach ($journey['levels'] as $level => $ids): ?>
                        <section class="journey-static__group">
                            <h3><?= e(\App\Controllers\AncestralJourneyController::generationLabel($level)) ?>
                                <span class="text-small text-muted"><?= e(count($ids)) ?> <?= count($ids) === 1 ? 'person' : 'people' ?></span>
                            </h3>
                            <ul class="journey-static__people">
                                <?php foreach ($ids as $id): ?>
                                    <?php $person = $journey['nodes'][$id]; ?>
                                    <li class="journey-person-pill journey-person-pill--<?= e($person['certainty']) ?>">
                                        <strong><?= e($person['name']) ?></strong>
                                        <span class="text-small text-muted">
                                            <?= e($person['birth_year'] !== null ? $person['birth_year'] : '?') ?>
                                            <?= $person['living'] ? '&ndash; living' : ($person['death_year'] !== null ? '&ndash; ' . $person['death_year'] : '') ?>
                                        </span>
                                        <span class="journey-badge journey-badge--<?= e($person['certainty']) ?>"><?= e($certLabels[$person['certainty']] ?? 'Unknown') ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endforeach; ?>
                </div>
            </div>

            <script type="application/json" id="journey-payload"><?= $json ?></script>
        <?php endif; ?>
    </div>
</section>
