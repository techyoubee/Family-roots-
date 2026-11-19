<?php
/**
 * @var array      $family
 * @var array      $stats
 * @var bool       $isMember
 * @var string|null $myRole
 */
$visibilityLabel = [
    'private'   => 'Private profile',
    'members'   => 'Visible to members',
    'directory' => 'Listed in the directory',
][$family['visibility']] ?? 'Visibility not set';
?>
<section class="section section--tight">
    <div class="wrap">
        <p class="text-small text-muted"><a href="<?= e(url('/families')) ?>">&larr; Back to the directory</a></p>
        <div class="split mb-4">
            <div>
                <p class="eyebrow">Family profile &middot; <?= e($family['public_id']) ?></p>
                <h1 class="mb-0"><?= e($family['name']) ?></h1>
                <p class="text-muted">
                    <?= e($visibilityLabel) ?>
                    <?php if (!empty($family['region']) || !empty($family['country'])): ?>
                        &middot; <?= e(trim(($family['region'] ?? '') . ', ' . ($family['country'] ?? ''), ', ')) ?>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <?= status_badge($family['verification_status'], $family['verification_status'] === 'verified' ? 'Historically verified' : 'Verification: ' . status_label($family['verification_status'])) ?>
            </div>
        </div>

        <?php if ($family['verification_status'] === 'unverified'): ?>
            <div class="alert alert--warning" role="status">
                <div>
                    This profile has not been historically verified. Creating a family profile never makes its history
                    verified; that requires documented evidence and a platform review.
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section section--tight">
    <div class="wrap grid grid--2">
        <div class="card">
            <h2>About this family</h2>
            <p><?= nl2br(e($family['description'] ?? 'No description recorded yet.')) ?></p>
            <?php if (!empty($family['origins'])): ?>
                <p><strong>Origins:</strong> <?= e($family['origins']) ?></p>
            <?php endif; ?>
            <?php if (!empty($family['founding_note'])): ?>
                <p><strong>Founding ancestors:</strong> <?= nl2br(e($family['founding_note'])) ?></p>
            <?php endif; ?>
        </div>
        <div class="card">
            <h2>At a glance</h2>
            <ul class="meta-list">
                <li><span class="meta-key">Registered members:</span> <?= e((int) $stats['members']) ?></li>
                <li><span class="meta-key">Ancestor records:</span> <?= e((int) $stats['ancestors']) ?></li>
                <li><span class="meta-key">Documented relationships:</span> <?= e((int) $stats['relationships']) ?></li>
                <li><span class="meta-key">Profile created:</span> <?= e(format_date($family['created_at'])) ?></li>
                <?php if (!empty($family['verified_at'])): ?>
                    <li><span class="meta-key">Verified:</span> <?= e(format_date($family['verified_at'])) ?></li>
                <?php endif; ?>
            </ul>

            <div class="form-actions">
                <?php if ($isMember): ?>
                    <?php if (route_exists('tree.show')): ?>
                        <a class="btn btn--primary" href="<?= e(route('tree.show', ['family' => $family['public_id']])) ?>">Open the family tree</a>
                    <?php endif; ?>
                    <?php if (route_exists('ancestors.index')): ?>
                        <a class="btn btn--outline" href="<?= e(route('ancestors.index', ['family' => $family['public_id']])) ?>">Ancestor records</a>
                    <?php endif; ?>
                    <?php if (route_exists('relationships.index')): ?>
                        <a class="btn btn--outline" href="<?= e(route('relationships.index', ['family' => $family['public_id']])) ?>">Relationships</a>
                    <?php endif; ?>
                    <?php if (in_array($myRole, ['founder', 'admin'], true)): ?>
                        <?php if (route_exists('family.members')): ?>
                            <a class="btn btn--outline" href="<?= e(route('family.members', ['family' => $family['public_id']])) ?>">Members</a>
                        <?php endif; ?>
                        <?php if (route_exists('family.settings')): ?>
                            <a class="btn btn--outline" href="<?= e(route('family.settings', ['family' => $family['public_id']])) ?>">Settings</a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <span class="badge badge--verified">You are a member<?= $myRole ? ' (' . e(status_label($myRole)) . ')' : '' ?></span>
                <?php elseif (route_exists('family.register')): ?>
                    <a class="btn btn--primary" href="<?= e(route('family.register')) ?>">Register your own family</a>
                <?php endif; ?>
            </div>
            <?php if (!$isMember): ?>
                <p class="hint">
                    Ancestor records, family history and evidence for this family are only visible to approved members.
                    <?php if (route_exists('applications.index')): ?>
                        If you believe you belong to this family, <a href="<?= e(route('applications.index')) ?>">start a membership application</a>.
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if (!empty($family['history'])): ?>
<section class="section section--tight">
    <div class="wrap">
        <div class="card">
            <h2>Family history</h2>
            <?php if ($isMember): ?>
                <p><?= nl2br(e($family['history'])) ?></p>
            <?php else: ?>
                <p class="text-muted">
                    The detailed family history is visible to approved members only.
                    Request membership to read it.
                </p>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>
