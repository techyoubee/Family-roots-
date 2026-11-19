<?php
/**
 * Empty-state guidance shared by every journey step.
 *
 * @var string     $emptyReason  no_family | no_ancestors
 * @var array|null $family
 */
$directoryUrl = route_exists('families.index') ? route('families.index') : url('/families');
$registerUrl = route_exists('family.register') ? route('family.register') : url('/families/create');
$ancestorUrl = $family !== null && route_exists('ancestors.create')
    ? route('ancestors.create', ['family' => $family['public_id']])
    : null;
$relationshipsUrl = $family !== null && route_exists('relationships.index')
    ? route('relationships.index', ['family' => $family['public_id']])
    : null;
?>
<div class="journey-empty card">
    <div class="card__body">
        <?php if ($emptyReason === 'no_ancestors'): ?>
            <p class="eyebrow">Nothing to trace yet</p>
            <h2>This family has no ancestor records yet</h2>
            <p class="text-muted">
                The journey can only show people your family has recorded. Add the first ancestor
                and connect them with a parent or spouse relationship - the tree will build itself
                from those records.
            </p>
            <div class="form-actions">
                <?php if ($ancestorUrl !== null): ?>
                    <a class="btn btn--primary" href="<?= e($ancestorUrl) ?>">Add an ancestor</a>
                <?php endif; ?>
                <?php if ($relationshipsUrl !== null): ?>
                    <a class="btn btn--outline" href="<?= e($relationshipsUrl) ?>">Manage relationships</a>
                <?php endif; ?>
                <a class="btn btn--ghost" href="<?= e($directoryUrl) ?>">Family directory</a>
            </div>
        <?php else: ?>
            <p class="eyebrow">One step first</p>
            <h2>Join a family to begin your journey</h2>
            <p class="text-muted">
                The Ancestral Journey is built from a family's verified records. Your account is not
                an active member of a family yet, so there is no ancestry to display.
            </p>
            <div class="form-actions">
                <a class="btn btn--primary" href="<?= e($directoryUrl) ?>">Find your family</a>
                <?php if (route_exists('family.register')): ?>
                    <a class="btn btn--outline" href="<?= e($registerUrl) ?>">Register a family</a>
                <?php endif; ?>
                <?php if (route_exists('applications.index')): ?>
                    <a class="btn btn--ghost" href="<?= e(route('applications.index')) ?>">Your applications</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
