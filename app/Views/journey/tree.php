<?php
/**
 * Interactive SVG tree, rendered on the server first and enhanced by JS.
 *
 * @var string|null $emptyReason
 * @var array       $journey
 * @var array       $layout
 * @var array       $payload
 * @var array       $stats
 * @var array       $family
 * @var array       $stepUrls
 */
include __DIR__ . '/_family.php';

$json = json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    $json = '{}';
}
$certLabels = ['verified' => 'Verified', 'proposed' => 'Proposed', 'unknown' => 'Unknown', 'disputed' => 'Disputed'];
$svgWidth = max(640, (float) ($layout['width'] ?? 640));
$svgHeight = max(320, (float) ($layout['height'] ?? 320));
$layoutById = [];
foreach ($layout['nodes'] as $position) {
    $layoutById[(int) $position['id']] = $position;
}
$yearsOf = static function (array $node) {
    $birth = $node['birth_year'] !== null ? (string) $node['birth_year'] : '?';
    if (!empty($node['living'])) {
        return $birth . ' - living';
    }
    if ($node['death_year'] !== null) {
        return $birth . ' - ' . $node['death_year'];
    }
    return $birth === '?' ? 'dates unknown' : 'born ' . $birth;
};
$rootNode = $journey['root_id'] !== null && isset($journey['nodes'][$journey['root_id']])
    ? $journey['nodes'][$journey['root_id']]
    : null;
?>
<section class="section section--tight">
    <div class="wrap">
        <?php if ($emptyReason !== null): ?>
            <?php include __DIR__ . '/_empty.php'; ?>
        <?php else: ?>
            <div class="journey-tree-toolbar">
                <div class="journey-tree-toolbar__zoom" role="group" aria-label="Tree zoom">
                    <button type="button" class="journey-icon-btn" data-journey-zoom="in" aria-label="Zoom in">+</button>
                    <button type="button" class="journey-icon-btn" data-journey-zoom="out" aria-label="Zoom out">&minus;</button>
                    <button type="button" class="journey-icon-btn" data-journey-zoom="reset" aria-label="Reset view">Reset</button>
                </div>
                <p class="text-small text-muted journey-tree-toolbar__hint">
                    Drag to pan &middot; use the buttons to zoom &middot; select a person to read their details.
                </p>
                <div class="journey-tree-toolbar__links">
                    <a class="btn btn--ghost btn--small" href="<?= e($stepUrls['animate']) ?>">Replay journey</a>
                    <a class="btn btn--ghost btn--small" href="<?= e($stepUrls['timeline']) ?>">Timeline</a>
                </div>
            </div>

            <div class="journey-tree-layout">
                <div class="journey-tree-viewport" data-journey-viewport tabindex="0" aria-label="Interactive family tree">
                    <svg class="journey-tree-svg" data-journey-svg
                         viewBox="0 0 <?= e($svgWidth) ?> <?= e($svgHeight) ?>"
                         role="img" aria-labelledby="journey-tree-title">
                        <title id="journey-tree-title">Ancestry tree for <?= e($family['name']) ?>, <?= e($stats['total']) ?> people across <?= e($stats['generations']) ?> generations</title>

                        <g class="journey-links" data-journey-links>
                            <?php foreach ($layout['edges'] as $edge): ?>
                                <line class="journey-link journey-link--<?= e($edge['certainty']) ?><?= $edge['kind'] === 'spouse' ? ' journey-link--spouse' : '' ?>"
                                      x1="<?= e($edge['from_x']) ?>" y1="<?= e($edge['from_y']) ?>"
                                      x2="<?= e($edge['to_x']) ?>" y2="<?= e($edge['to_y']) ?>"></line>
                            <?php endforeach; ?>
                        </g>

                        <g class="journey-people" data-journey-people>
                            <?php foreach ($journey['levels'] as $level => $ids): ?>
                                <?php foreach ($ids as $id): ?>
                                    <?php
                                    $node = $journey['nodes'][$id];
                                    $position = $layoutById[$id] ?? null;
                                    if ($position === null) {
                                        continue;
                                    }
                                    $label = $node['name'] . ', ' . $yearsOf($node) . ', ' . ($certLabels[$node['certainty']] ?? 'Unknown');
                                    ?>
                                    <g class="journey-node journey-node--<?= e($node['certainty']) ?>"
                                       transform="translate(<?= e($position['x']) ?>, <?= e($position['y']) ?>)"
                                       data-person-id="<?= e($node['id']) ?>"
                                       tabindex="0" role="button"
                                       aria-label="<?= e($label) ?>">
                                        <title><?= e($label) ?></title>
                                        <rect class="journey-node__box" width="<?= e($position['width']) ?>" height="<?= e($position['height']) ?>" rx="10"></rect>
                                        <rect class="journey-node__stripe" width="5" height="<?= e($position['height']) ?>" rx="2"></rect>
                                        <text class="journey-node__name" x="14" y="25"><?= e(short_name($node['name'], 24)) ?></text>
                                        <text class="journey-node__years" x="14" y="43"><?= e($yearsOf($node)) ?></text>
                                        <text class="journey-node__level" x="14" y="58"><?= e(\App\Controllers\AncestralJourneyController::generationLabel($node['level'])) ?></text>
                                        <?php if ($node['collapsed']): ?>
                                            <circle class="journey-node__shared" cx="<?= e($position['width'] - 14) ?>" cy="14" r="6"></circle>
                                            <text class="journey-node__shared-mark" x="<?= e($position['width'] - 14) ?>" y="18" text-anchor="middle">&#8734;</text>
                                            <title>Reached through <?= e($node['multiplicity']) ?> lineage paths (pedigree collapse)</title>
                                        <?php endif; ?>
                                    </g>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </g>
                    </svg>

                    <p class="journey-tree-noscript">
                        The tree is drawn above. Select a person to see their details in the panel.
                        If your browser blocks scripts, the person list on the timeline page is a
                        complete text alternative.
                    </p>
                </div>

                <aside class="journey-tree-detail" data-journey-detail aria-live="polite">
                    <?php if ($rootNode): ?>
                        <p class="eyebrow" data-detail-kicker><?= e(\App\Controllers\AncestralJourneyController::generationLabel($rootNode['level'])) ?></p>
                        <h2 data-detail-name><?= e($rootNode['name']) ?></h2>
                        <p class="text-muted" data-detail-years><?= e($yearsOf($rootNode)) ?></p>
                        <p>
                            <span class="journey-badge journey-badge--<?= e($rootNode['certainty']) ?>" data-detail-status>
                                <?= e($certLabels[$rootNode['certainty']] ?? 'Unknown') ?>
                            </span>
                        </p>
                        <dl class="journey-detail-list" data-detail-list>
                            <div><dt>Generation</dt><dd><?= e($rootNode['generation']) ?></dd></div>
                            <div><dt>Branch</dt><dd><?= e($rootNode['branch'] ?: 'Not recorded') ?></dd></div>
                            <div><dt>Born</dt><dd><?= e($rootNode['person']['birth_date_text'] ?: ($rootNode['birth_year'] !== null ? $rootNode['birth_year'] : 'Not recorded')) ?></dd></div>
                            <div><dt>Died</dt><dd><?= e($rootNode['person']['death_date_text'] ?: ($rootNode['death_year'] !== null ? $rootNode['death_year'] : 'Not recorded')) ?></dd></div>
                            <div><dt>Parents</dt><dd>
                                <?php
                                $parentNames = [];
                                foreach ($rootNode['parents'] as $parent) {
                                    if (isset($journey['nodes'][$parent['id']])) {
                                        $parentNames[] = $journey['nodes'][$parent['id']]['name'];
                                    }
                                }
                                echo e($parentNames ? implode(', ', $parentNames) : 'Not recorded');
                                ?>
                            </dd></div>
                        </dl>
                    <?php else: ?>
                        <p class="text-muted">Select a person in the tree.</p>
                    <?php endif; ?>
                </aside>
            </div>

            <div class="journey-tree-legend" aria-label="Legend">
                <?php foreach ($certLabels as $key => $label): ?>
                    <span class="journey-tree-legend__item">
                        <span class="journey-dot journey-dot--<?= e($key) ?>" aria-hidden="true"></span>
                        <?= e($label) ?>
                    </span>
                <?php endforeach; ?>
                <span class="journey-tree-legend__item">
                    <span class="journey-tree-legend__dash" aria-hidden="true"></span> Spouse / union
                </span>
            </div>

            <p class="text-small text-muted journey-footnote">
                Node labels show <?= e($stats['total']) ?> people within five generations.
                A filled circle and &#8734; mark an ancestor reached through several lineage paths
                (pedigree collapse). Nothing here is inferred automatically.
            </p>

            <script type="application/json" id="journey-payload"><?= $json ?></script>
        <?php endif; ?>
    </div>
</section>
