<?php
/**
 * @var array $family
 */
$badge = $family['verification_status'] === 'verified'
    ? ['badge--verified', 'Historically verified']
    : ($family['verification_status'] === 'rejected'
        ? ['badge--rejected', 'Verification declined']
        : ['badge--unverified', 'Not yet verified']);
?>
<article class="card">
    <div class="split mb-4">
        <div class="avatar" aria-hidden="true"><?= e(initials($family['name'])) ?></div>
        <span class="badge <?= e($badge[0]) ?>"><?= e($badge[1]) ?></span>
    </div>
    <h3><a href="<?= e(url('/families/' . $family['public_id'])) ?>"><?= e($family['name']) ?></a></h3>
    <p class="text-small text-muted">
        <?php if (!empty($family['origins'])): ?>Origins: <?= e($family['origins']) ?><?php else: ?>Origins not documented yet<?php endif; ?>
    </p>
    <?php if (!empty($family['description'])): ?>
        <p><?= e(short_name($family['description'], 140)) ?></p>
    <?php endif; ?>
    <ul class="inline-list">
        <?php if (isset($family['member_count'])): ?>
            <li class="tag"><?= e((int) $family['member_count']) ?> members</li>
        <?php endif; ?>
        <?php if (isset($family['ancestor_count'])): ?>
            <li class="tag"><?= e((int) $family['ancestor_count']) ?> ancestor records</li>
        <?php endif; ?>
    </ul>
</article>
