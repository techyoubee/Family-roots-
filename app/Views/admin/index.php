<?php
/**
 * Platform administration overview.
 *
 * @var string|null $title
 * @var array       $stats
 * @var array       $recentUsers
 * @var array       $openRequests
 */
$statCards = [
    ['label' => 'Registered accounts', 'value' => $stats['users'], 'href' => route('admin.accounts')],
    ['label' => 'Active accounts',     'value' => $stats['active']],
    ['label' => 'Pending accounts',    'value' => $stats['pending']],
    ['label' => 'Email verified',      'value' => $stats['verified']],
    ['label' => 'Families',            'value' => $stats['families'], 'href' => route('admin.families')],
    ['label' => 'Active members',      'value' => $stats['members']],
    ['label' => 'Requests awaiting action', 'value' => $stats['pending_requests'], 'href' => route('admin.applications')],
    ['label' => 'Platform admins',     'value' => $stats['admins']],
];
?>
<section class="section section--tight">
    <div class="wrap">
        <p class="eyebrow">Platform administration</p>
        <h1>Registered accounts &amp; platform oversight</h1>
        <p class="text-muted prose">
            A read-only view of everyone who has registered, every family on the platform and every
            membership request. Decisions are still made by each family's administrators on the review screen.
        </p>

        <div class="grid grid--4 admin-stats">
            <?php foreach ($statCards as $card): ?>
                <?php if (!empty($card['href'])): ?>
                    <a class="admin-stat" href="<?= e($card['href']) ?>">
                        <span class="admin-stat__value"><?= e((int) $card['value']) ?></span>
                        <span class="admin-stat__label"><?= e($card['label']) ?></span>
                    </a>
                <?php else: ?>
                    <div class="admin-stat">
                        <span class="admin-stat__value"><?= e((int) $card['value']) ?></span>
                        <span class="admin-stat__label"><?= e($card['label']) ?></span>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="form-actions mb-4">
            <a class="btn btn--primary" href="<?= e(route('admin.accounts')) ?>">View all accounts</a>
            <a class="btn btn--outline" href="<?= e(route('admin.applications')) ?>">Membership requests</a>
            <a class="btn btn--outline" href="<?= e(route('admin.families')) ?>">All families &amp; members</a>
        </div>
    </div>
</section>

<section class="section section--tight">
    <div class="wrap">
        <div class="grid grid--2">
            <div class="card card--flush">
                <div class="card__header">
                    <h2>Newest accounts</h2>
                    <a class="text-small" href="<?= e(route('admin.accounts')) ?>">View all</a>
                </div>
                <?php if (empty($recentUsers)): ?>
                    <div class="card__body"><p class="text-muted">No accounts have registered yet.</p></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                                <tr>
                                    <th scope="col">Account</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentUsers as $row): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($row['full_name']) ?></strong>
                                            <div class="text-small text-muted"><?= e($row['email']) ?></div>
                                        </td>
                                        <td><?= status_badge($row['status']) ?></td>
                                        <td class="text-small"><?= e(format_date($row['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card card--flush">
                <div class="card__header">
                    <h2>Requests awaiting action</h2>
                    <a class="text-small" href="<?= e(route('admin.applications')) ?>">View all</a>
                </div>
                <?php if (empty($openRequests)): ?>
                    <div class="card__body"><p class="text-muted">No membership requests are waiting right now.</p></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                                <tr>
                                    <th scope="col">Applicant</th>
                                    <th scope="col">Family</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($openRequests as $row): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($row['applicant_name']) ?></strong>
                                            <div class="text-small text-muted"><?= e($row['public_id']) ?></div>
                                        </td>
                                        <td class="text-small"><?= e($row['family_name']) ?></td>
                                        <td><?= status_badge($row['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
