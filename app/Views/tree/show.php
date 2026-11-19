<?php
/**
 * Server-rendered family tree outline, grouped by generation.
 *
 * @var string|null $title
 * @var array      $family
 * @var array      $generations  generation number => people (each with parents/children/partners edges)
 * @var int        $total
 * @var bool       $truncated
 * @var int        $limit
 */
$ancestorBase = '/families/' . rawurlencode($family['public_id']) . '/ancestors';
// Styling comes from app.css (strict CSP: no inline styles in views).
$statusClasses = [
    'confirmed' => 'card--status-confirmed',
    'proposed'  => 'card--status-proposed',
    'unknown'   => 'card--status-unknown',
    'disputed'  => 'card--status-disputed',
];

/** Compact name + years for an edge side. */
function tree_edge_label($edge, $side)
{
    $name = trim(($edge[$side . '_given'] ?? '') . ' ' . ($edge[$side . '_surname'] ?? ''));
    return $name === '' ? 'Unnamed record' : $name;
}

function tree_person_years($person)
{
    $birth = \App\Models\Ancestor::birthYear($person);
    $death = \App\Models\Ancestor::deathYear($person);
    if (!$birth && !$death) {
        return '';
    }
    return '(' . ($birth ? $birth : '?') . '-' . ($death ? $death : (!empty($person['is_living']) ? 'living' : '?')) . ')';
}
?>
<section class="section section--tight">
    <div class="wrap">
        <p class="text-small text-muted">
            <a href="<?= e(url('/families/' . rawurlencode($family['public_id']))) ?>">&larr; Back to the family profile</a>
        </p>
        <div class="split mb-4">
            <div>
                <p class="eyebrow">Family tree &middot; <?= e($family['public_id']) ?></p>
                <h1><?= e($family['name']) ?></h1>
                <p class="text-muted">
                    <?= e($total) ?> people, oldest generation first. Every connection below was recorded by a
                    member and carries its own status - proposed links may still be wrong.
                </p>
            </div>
            <div>
                <?php if (route_exists('ancestors.index')): ?>
                    <a class="btn btn--outline" href="<?= e(route('ancestors.index', ['family' => $family['public_id']])) ?>">All records</a>
                <?php endif; ?>
                <?php if (route_exists('relationships.index')): ?>
                    <a class="btn btn--outline" href="<?= e(route('relationships.index', ['family' => $family['public_id']])) ?>">Relationships</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($truncated): ?>
            <div class="alert alert--info" role="status">
                <div>Showing the first <?= e($limit) ?> people. Narrow your view with the ancestor records page when the tree grows larger.</div>
            </div>
        <?php endif; ?>

        <?php if (empty($generations)): ?>
            <div class="empty-state">
                <h3>No ancestor records yet</h3>
                <p>The tree takes shape once people are added and linked together.</p>
            </div>
        <?php else: ?>
            <div class="tree-shell">
                <div class="tree-toolbar">
                    <span class="text-small text-muted">Generation 0 is the oldest known ancestors. Links open each person's record.</span>
                </div>
                <?php foreach ($generations as $generation => $people): ?>
                    <section class="section section--compact">
                        <div class="wrap">
                            <h2 class="mb-4">Generation <?= e((int) $generation) ?> <span class="text-small text-muted">(<?= e(count($people)) ?> people)</span></h2>
                            <div class="grid grid--2">
                                <?php foreach ($people as $person): ?>
                                    <div class="card card--status <?= e($statusClasses[$person['record_status']] ?? '') ?>">
                                        <div class="split">
                                            <div>
                                                <a class="node__name" href="<?= e(url($ancestorBase . '/' . (int) $person['id'])) ?>"><?= e(\App\Models\Ancestor::displayName($person)) ?></a>
                                                <div class="node__dates text-small text-muted">
                                                    <?= e(tree_person_years($person)) ?>
                                                    <?php if (!empty($person['branch'])): ?>&middot; <?= e($person['branch']) ?><?php endif; ?>
                                                </div>
                                            </div>
                                            <div><?= status_badge($person['record_status']) ?></div>
                                        </div>

                                        <?php if (!empty($person['parents']) || !empty($person['partners']) || !empty($person['children'])): ?>
                                            <ul class="list-plain text-small mt-0">
                                                <?php foreach ($person['parents'] as $edge): ?>
                                                    <li>
                                                        Parent: <a href="<?= e(url($ancestorBase . '/' . (int) $edge['from_ancestor_id'])) ?>"><?= e(tree_edge_label($edge, 'from')) ?></a>
                                                        (<?= e(status_label($edge['parent_role'])) ?>, <?= e($edge['status']) ?>)
                                                    </li>
                                                <?php endforeach; ?>
                                                <?php foreach ($person['partners'] as $edge): ?>
                                                    <li>
                                                        Partner: <a href="<?= e(url($ancestorBase . '/' . (int) ($edge['from_ancestor_id'] == $person['id'] ? $edge['to_ancestor_id'] : $edge['from_ancestor_id']))) ?>"><?= e(tree_edge_label($edge, $edge['from_ancestor_id'] == $person['id'] ? 'to' : 'from')) ?></a>
                                                        (<?= e($edge['status']) ?>)
                                                    </li>
                                                <?php endforeach; ?>
                                                <?php foreach ($person['children'] as $edge): ?>
                                                    <li>
                                                        Child: <a href="<?= e(url($ancestorBase . '/' . (int) $edge['to_ancestor_id'])) ?>"><?= e(tree_edge_label($edge, 'to')) ?></a>
                                                        (<?= e($edge['status']) ?>)
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p class="text-small text-muted mb-0">No relationships recorded yet.</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </section>
                <?php endforeach; ?>

                <div class="tree-legend">
                    <span class="legend-key"><span class="legend-swatch legend-swatch--confirmed"></span> Confirmed record</span>
                    <span class="legend-key"><span class="legend-swatch legend-swatch--proposed"></span> Proposed record</span>
                    <span class="legend-key"><span class="legend-swatch legend-swatch--unknown"></span> Unknown status</span>
                    <span class="legend-key"><span class="legend-swatch legend-swatch--disputed"></span> Disputed record</span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
