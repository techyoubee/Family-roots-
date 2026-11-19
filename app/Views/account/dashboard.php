<?php
/**
 * Signed-in member dashboard.
 *
 * @var string|null $title
 * @var array       $user
 * @var array       $memberships
 * @var array       $notifications
 * @var int         $unread
 * @var bool        $needsEmail
 */
?>
<section class="section section--tight">
    <div class="wrap">
        <p class="eyebrow">Your account</p>
        <h1>Welcome back, <?= e($user['full_name']) ?></h1>
        <p class="text-muted">
            Signed in as <?= e($user['email']) ?>.
            <?php if (!empty($user['is_platform_admin'])): ?>
                <span class="badge badge--verified">Platform administrator</span>
            <?php endif; ?>
        </p>

        <div class="card mb-4 journey-cta">
            <div class="card__body">
                <div class="split">
                    <div>
                        <p class="eyebrow">My Ancestral Journey</p>
                        <h2>Discover Your Roots</h2>
                        <p>Explore your family history through a cinematic journey through generations past.</p>
                    </div>
                    <div class="form-actions">
                        <a class="btn btn--primary" href="<?= e(route_exists('journey.animate') ? route('journey.animate') : url('/journey/animate')) ?>">Begin Journey</a>
                        <a class="btn btn--outline" href="<?= e(route_exists('journey.tree') ? route('journey.tree') : url('/journey/tree')) ?>">Explore Tree</a>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($user['is_platform_admin']) && route_exists('admin.index')): ?>
            <div class="card mb-4">
                <div class="card__header">
                    <h2>Platform administration</h2>
                    <a class="text-small" href="<?= e(route('admin.index')) ?>">Open overview</a>
                </div>
                <div class="card__body">
                    <p class="text-muted">
                        See every registered account, membership request and family on the platform.
                    </p>
                    <div class="form-actions">
                        <a class="btn btn--primary" href="<?= e(route('admin.accounts')) ?>">Registered accounts</a>
                        <a class="btn btn--outline" href="<?= e(route('admin.applications')) ?>">Membership requests</a>
                        <a class="btn btn--outline" href="<?= e(route('admin.families')) ?>">All families</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($needsEmail): ?>
            <div class="alert alert--warning" role="alert">
                <div>
                    <strong>Your email address is not confirmed yet.</strong>
                    You can look around, but adding or editing family records needs a confirmed address.
                    <?php if (route_exists('auth.verify.notice')): ?>
                        <a href="<?= e(route('auth.verify.notice')) ?>">Resend the confirmation email</a>.
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="form-actions">
            <?php if (route_exists('family.register')): ?>
                <a class="btn btn--primary" href="<?= e(route('family.register')) ?>">Register a family</a>
            <?php endif; ?>
            <?php if (route_exists('families.index')): ?>
                <a class="btn btn--outline" href="<?= e(route('families.index')) ?>">Browse the directory</a>
            <?php endif; ?>
            <?php if (route_exists('settings.index')): ?>
                <a class="btn btn--outline" href="<?= e(route('settings.index')) ?>">Account settings</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section section--tight">
    <div class="wrap">
        <div class="grid grid--2">
            <div class="card">
                <div class="card__header">
                    <h2>Your families</h2>
                </div>
                <div class="card__body">
                    <?php if (empty($memberships)): ?>
                        <p class="text-muted">
                            You are not a member of any family yet. Register your own family, or apply to join one
                            you already belong to once its administrators are on the platform.
                        </p>
                    <?php else: ?>
                        <ul class="list-plain family-list">
                            <?php foreach ($memberships as $membership): ?>
                                <li class="family-list__item">
                                    <div>
                                        <a class="family-list__name" href="<?= e(url('/families/' . rawurlencode($membership['public_id']))) ?>"><?= e($membership['name']) ?></a>
                                        <div class="text-small text-muted">
                                            <?= e(($membership['region'] ?: '') !== '' ? $membership['region'] . ', ' : '') . e($membership['country'] ?: 'Region not recorded') ?>
                                        </div>
                                        <div class="text-small">
                                            <?php if (route_exists('tree.show')): ?>
                                                <a href="<?= e(route('tree.show', ['family' => $membership['public_id']])) ?>">Tree</a> &middot;
                                            <?php endif; ?>
                                            <?php if (route_exists('ancestors.index')): ?>
                                                <a href="<?= e(route('ancestors.index', ['family' => $membership['public_id']])) ?>">Records</a>
                                            <?php endif; ?>
                                            <?php if (in_array($membership['role'], ['founder', 'admin'], true) && route_exists('family.members')): ?>
                                                &middot; <a href="<?= e(route('family.members', ['family' => $membership['public_id']])) ?>">Manage</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="family-list__meta">
                                        <?= status_badge($membership['verification_status']) ?>
                                        <span class="badge badge--neutral"><?= e(ucfirst($membership['role'])) ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card__header">
                    <h2>Recent notifications</h2>
                    <?php if (route_exists('notifications.index')): ?>
                        <a class="text-small" href="<?= e(route('notifications.index')) ?>">View all</a>
                    <?php endif; ?>
                </div>
                <div class="card__body">
                    <?php if (empty($notifications)): ?>
                        <p class="text-muted">Nothing new. Decisions about your applications appear here.</p>
                    <?php else: ?>
                        <ul class="list-plain notification-list">
                            <?php foreach ($notifications as $notification): ?>
                                <li class="notification-list__item<?= empty($notification['read_at']) ? ' is-unread' : '' ?>">
                                    <div>
                                        <a class="notification-list__title" href="<?= e($notification['link_path'] ? url($notification['link_path']) : route('notifications.index')) ?>"><?= e($notification['title']) ?></a>
                                        <?php if (!empty($notification['body'])): ?>
                                            <p class="text-small text-muted"><?= e($notification['body']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <time class="text-small text-muted" datetime="<?= e($notification['created_at']) ?>"><?= e(time_ago($notification['created_at'])) ?></time>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
