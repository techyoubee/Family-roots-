<?php
/**
 * Pick the family a new membership application is about.
 *
 * @var string|null $title
 * @var array       $families
 */
?>
<section class="section section--tight">
    <div class="wrap">
        <p class="text-small text-muted">
            <a href="<?= e(route('applications.index')) ?>">&larr; Back to your applications</a>
        </p>
        <p class="eyebrow">Membership</p>
        <h1>Which family are you applying to?</h1>
        <p class="text-muted">
            Choose a family, then describe the ancestors you can name. You will be able to add several
            relatives and submit when you are ready. A moderator reviews every application.
        </p>

        <?php if (empty($families)): ?>
            <div class="empty-state">
                <h3>No families are listed in the directory</h3>
                <p>Ask a family administrator for a direct link, or register your own family.</p>
                <?php if (route_exists('family.register')): ?>
                    <a class="btn btn--primary" href="<?= e(route('family.register')) ?>">Register a family</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid grid--3">
                <?php foreach ($families as $family): ?>
                    <article class="card">
                        <h2 class="mb-0"><a href="<?= e(url('/families/' . rawurlencode($family['public_id']))) ?>"><?= e($family['name']) ?></a></h2>
                        <p class="text-small text-muted mb-2"><?= e($family['public_id']) ?>
                            <?php if (!empty($family['region']) || !empty($family['country'])): ?>
                                &middot; <?= e(trim(($family['region'] ?? '') . ', ' . ($family['country'] ?? ''), ', ')) ?>
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($family['description'])): ?>
                            <p class="text-small"><?= e(mb_substr((string) $family['description'], 0, 140)) ?><?= mb_strlen((string) $family['description']) > 140 ? '&hellip;' : '' ?></p>
                        <?php endif; ?>
                        <div class="form-actions">
                            <a class="btn btn--primary btn--small" href="<?= e(route('applications.create', ['family' => $family['public_id']])) ?>">Apply to join</a>
                            <a class="btn btn--outline btn--small" href="<?= e(url('/families/' . rawurlencode($family['public_id']))) ?>">View profile</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
