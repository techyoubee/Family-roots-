<?php
/**
 * One ancestor record with its family connections.
 *
 * @var string|null $title
 * @var array      $family
 * @var array      $ancestor
 * @var array      $parents
 * @var array      $spouses
 * @var array      $children
 * @var array      $siblings
 * @var array      $duplicates  same-name candidates (administrators only)
 * @var bool       $canWrite
 * @var bool       $canAdmin
 */
$baseUrl = '/families/' . rawurlencode($family['public_id']) . '/ancestors';
$birth = \App\Models\Ancestor::birthYear($ancestor);
$death = \App\Models\Ancestor::deathYear($ancestor);

function ancestor_link($baseUrl, $person)
{
    return url($baseUrl . '/' . (int) $person['id']);
}

function person_line($baseUrl, $person, $extra = '')
{
    $years = [];
    $b = \App\Models\Ancestor::birthYear($person);
    $d = \App\Models\Ancestor::deathYear($person);
    if ($b) {
        $years[] = $b;
    }
    if ($d) {
        $years[] = $d;
    }
    $suffix = $years ? ' (' . e(implode('-', $years)) . ')' : '';
    $note = $extra !== '' ? ' <span class="text-small text-muted">' . e($extra) . '</span>' : '';
    return '<a href="' . e(ancestor_link($baseUrl, $person)) . '">' . e(\App\Models\Ancestor::displayName($person)) . '</a>' . $suffix . $note;
}
?>
<section class="section section--tight">
    <div class="wrap">
        <p class="text-small text-muted">
            <a href="<?= e(url($baseUrl)) ?>">&larr; Back to ancestor records</a>
        </p>
        <div class="split mb-4">
            <div>
                <p class="eyebrow">Ancestor record &middot; Generation <?= e((int) $ancestor['generation']) ?></p>
                <h1><?= e(\App\Models\Ancestor::displayName($ancestor)) ?></h1>
                <p class="text-muted">
                    <?php if ($birth || $death): ?>
                        <?= e($birth ? $birth : '?') ?> - <?= e($death ? $death : (!empty($ancestor['is_living']) ? 'living' : '?')) ?>
                        &middot;
                    <?php endif; ?>
                    <?php if (!empty($ancestor['branch'])): ?>Branch: <?= e($ancestor['branch']) ?> &middot;<?php endif; ?>
                    Sex: <?= e(status_label($ancestor['sex'])) ?>
                </p>
            </div>
            <div>
                <?= status_badge($ancestor['record_status']) ?>
                <?php if (!empty($ancestor['is_living'])): ?>
                    <span class="badge badge--neutral">Living person</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($ancestor['record_status'] === 'proposed'): ?>
            <div class="alert alert--info" role="status">
                <div>This record is a proposal. It becomes confirmed only after a family administrator reviews the evidence.</div>
            </div>
        <?php elseif ($ancestor['record_status'] === 'disputed'): ?>
            <div class="alert alert--warning" role="status">
                <div>This record is disputed. Treat its details as unconfirmed until the family resolves the disagreement.</div>
            </div>
        <?php endif; ?>

        <?php if (!empty($duplicates)): ?>
            <div class="alert alert--warning" role="status">
                <div>
                    <strong>Possible similar records:</strong>
                    <?php foreach ($duplicates as $i => $duplicate): ?>
                        <?= $i > 0 ? '; ' : '' ?>
                        <a href="<?= e(ancestor_link($baseUrl, $duplicate)) ?>"><?= e(\App\Models\Ancestor::displayName($duplicate)) ?></a>
                        <?= status_badge($duplicate['record_status']) ?>
                    <?php endforeach; ?>
                    Nothing is merged automatically - review them yourself.
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid--2 mb-4">
            <div class="card">
                <h2>Record details</h2>
                <ul class="meta-list">
                    <li><span class="meta-key">Given names:</span> <?= e($ancestor['given_names']) ?></li>
                    <li><span class="meta-key">Surname:</span> <?= e($ancestor['surname'] ?: 'Not recorded') ?></li>
                    <?php if (!empty($ancestor['alt_names'])): ?>
                        <li><span class="meta-key">Other names:</span> <?= e($ancestor['alt_names']) ?></li>
                    <?php endif; ?>
                    <li><span class="meta-key">Birth:</span> <?= e(($ancestor['birth_date_text'] ?: $ancestor['birth_date'] ?: 'Not recorded') . (!empty($ancestor['birth_place']) ? ' - ' . $ancestor['birth_place'] : '')) ?></li>
                    <li><span class="meta-key">Death:</span> <?= e(($ancestor['death_date_text'] ?: $ancestor['death_date'] ?: (!empty($ancestor['is_living']) ? 'Living' : 'Not recorded')) . (!empty($ancestor['death_place']) ? ' - ' . $ancestor['death_place'] : '')) ?></li>
                    <?php if (!empty($ancestor['notes'])): ?>
                        <li><span class="meta-key">Notes:</span> <?= nl2br(e($ancestor['notes'])) ?></li>
                    <?php endif; ?>
                    <?php if (!empty($ancestor['evidence_source'])): ?>
                        <li><span class="meta-key">Evidence noted:</span> <?= nl2br(e($ancestor['evidence_source'])) ?></li>
                    <?php endif; ?>
                </ul>
                <p class="text-small text-muted">Last updated <?= e(format_date($ancestor['updated_at'])) ?>. Created <?= e(format_date($ancestor['created_at'])) ?>.</p>

                <div class="form-actions">
                    <?php if ($canWrite && route_exists('ancestors.edit')): ?>
                        <a class="btn btn--primary" href="<?= e(route('ancestors.edit', ['family' => $family['public_id'], 'ancestor' => $ancestor['id']])) ?>">Edit this record</a>
                    <?php endif; ?>
                    <?php if ($canWrite && route_exists('ancestors.create')): ?>
                        <a class="btn btn--outline" href="<?= e(route('ancestors.create', ['family' => $family['public_id']])) ?>">Add another person</a>
                    <?php endif; ?>
                </div>
                <?php if ($canAdmin): ?>
                    <form method="post" action="<?= e(url($baseUrl . '/' . (int) $ancestor['id'] . '/delete')) ?>" class="form" data-confirm="Delete this person and every relationship pointing at them? This cannot be undone.">
                        <?= csrf_field() ?>
                        <div class="form-actions">
                            <button class="btn btn--danger" type="submit">Delete record</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Family connections</h2>
                <?php if (empty($parents) && empty($spouses) && empty($children) && empty($siblings)): ?>
                    <p class="text-muted">No relationships recorded yet. Use the relationships page to link parents, partners or children.</p>
                <?php else: ?>
                    <?php if (!empty($parents)): ?>
                        <h3>Parents</h3>
                        <ul class="list-plain">
                            <?php foreach ($parents as $parent): ?>
                                <li><?= person_line($baseUrl, $parent, status_label($parent['parent_role']) . ' - ' . $parent['relationship_status']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if (!empty($spouses)): ?>
                        <h3>Partners</h3>
                        <ul class="list-plain">
                            <?php foreach ($spouses as $spouse): ?>
                                <li><?= person_line($baseUrl, $spouse, $spouse['relationship_status']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if (!empty($children)): ?>
                        <h3>Children</h3>
                        <ul class="list-plain">
                            <?php foreach ($children as $child): ?>
                                <li><?= person_line($baseUrl, $child, $child['relationship_status']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if (!empty($siblings)): ?>
                        <h3>Siblings</h3>
                        <ul class="list-plain">
                            <?php foreach ($siblings as $sibling): ?>
                                <li><?= person_line($baseUrl, $sibling) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
