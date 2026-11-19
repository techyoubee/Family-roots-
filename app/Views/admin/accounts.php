<?php
/**
 * Registered accounts (platform administrators).
 *
 * @var string|null $title
 * @var array       $users
 * @var int         $total
 * @var int         $page
 * @var int         $pages
 * @var array       $filters
 * @var array       $stats
 * @var string|null $localVerificationLink
 */
$base = '/admin/accounts';
$build = static function (array $overrides) use ($filters): string {
    return query_url('/admin/accounts', array_merge($filters, $overrides));
};
$statusOptions = [
    ''                 => 'Any status',
    'active'           => 'Active',
    'pending'          => 'Pending email confirmation',
    'suspended'        => 'Suspended',
    'deletion_requested' => 'Deletion requested',
];
?>
<section class="section section--tight">
    <div class="wrap">
        <p class="text-small text-muted">
            <a href="<?= e(route('admin.index')) ?>">&larr; Platform overview</a>
        </p>
        <p class="eyebrow">Platform administration</p>
        <h1>Registered accounts</h1>
        <p class="text-muted prose">
            <?= e((int) $stats['users']) ?> account<?= (int) $stats['users'] === 1 ? '' : 's' ?> in total,
            <?= e((int) $stats['active']) ?> active, <?= e((int) $stats['pending']) ?> awaiting email confirmation,
            <?= e((int) $stats['verified']) ?> with a verified email address.
        </p>

        <?php if (!empty($localVerificationLink)): ?>
            <div class="alert alert--success" role="status">
                <div>
                    <strong>No email was sent - this server logs mail instead.</strong>
                    Open this confirmation link in the same browser:
                    <div class="link-box">
                        <a href="<?= e($localVerificationLink) ?>"><?= e($localVerificationLink) ?></a>
                    </div>
                </div>
                <button type="button" class="alert__close" aria-label="Dismiss message">&times;</button>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <form class="filter-bar" method="get" action="<?= e(url($base)) ?>" role="search">
                <div class="form-row">
                    <label for="q">Search name or email</label>
                    <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="e.g. founder@bh-kiru.example">
                </div>
                <div class="form-row">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label for="verified">Email</label>
                    <select id="verified" name="verified">
                        <?php foreach (['' => 'Any', 'yes' => 'Verified', 'no' => 'Not verified'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $filters['verified'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label for="role">Role</label>
                    <select id="role" name="role">
                        <?php foreach (['' => 'Any', 'admin' => 'Platform administrators', 'member' => 'Standard accounts'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $filters['role'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <button class="btn btn--primary" type="submit">Apply</button>
                </div>
                <?php if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['verified'] !== '' || $filters['role'] !== ''): ?>
                    <div class="form-row">
                        <a class="btn btn--outline" href="<?= e(url($base)) ?>">Clear</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($users)): ?>
            <div class="empty-state">
                <h3>No accounts matched</h3>
                <p>Try a different search term or clear the filters.</p>
            </div>
        <?php else: ?>
            <p class="text-small text-muted">
                Showing <?= e(count($users)) ?> of <?= e($total) ?> account<?= $total === 1 ? '' : 's' ?>
                &middot; page <?= e($page) ?> of <?= e($pages) ?>.
            </p>
            <div class="card card--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th scope="col">Account</th>
                                <th scope="col">Status</th>
                                <th scope="col">Email</th>
                                <th scope="col">Families</th>
                                <th scope="col">Joined</th>
                                <th scope="col">Last sign-in</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($row['full_name']) ?></strong>
                                        <?php if (!empty($row['is_platform_admin'])): ?>
                                            <span class="badge badge--verified">Platform admin</span>
                                        <?php endif; ?>
                                        <div class="text-small text-muted"><?= e($row['email']) ?></div>
                                        <div class="text-small text-muted"><?= e($row['public_id']) ?></div>
                                    </td>
                                    <td><?= status_badge($row['status']) ?></td>
                                    <td class="text-small">
                                        <?php if (!empty($row['email_verified_at'])): ?>
                                            <span class="badge badge--verified">Verified</span>
                                        <?php else: ?>
                                            <span class="badge badge--pending">Not verified</span>
                                            <form class="cell-action" method="post"
                                                  action="<?= e(route('admin.accounts.verification', ['user' => $row['public_id']])) ?>">
                                                <?= csrf_field() ?>
                                                <button class="btn btn--outline btn--small" type="submit">Resend link</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-small">
                                        <?php if (empty($row['memberships'])): ?>
                                            <span class="text-muted">No family</span>
                                        <?php else: ?>
                                            <?php foreach ($row['memberships'] as $membership): ?>
                                                <div>
                                                    <a href="<?= e(url('/families/' . rawurlencode($membership['public_id']))) ?>"><?= e($membership['name']) ?></a>
                                                    <span class="text-muted">(<?= e(ucfirst($membership['role'])) ?>)</span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-small"><?= e(format_date($row['created_at'])) ?></td>
                                    <td class="text-small"><?= e(!empty($row['last_login_at']) ? format_date($row['last_login_at']) : 'Never') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($pages > 1): ?>
                <nav class="pagination" aria-label="Account pages">
                    <?php if ($page > 1): ?>
                        <a class="btn btn--outline btn--small" href="<?= e($build(['page' => $page - 1])) ?>">&larr; Previous</a>
                    <?php endif; ?>
                    <span class="text-small text-muted">Page <?= e($page) ?> of <?= e($pages) ?></span>
                    <?php if ($page < $pages): ?>
                        <a class="btn btn--outline btn--small" href="<?= e($build(['page' => $page + 1])) ?>">Next &rarr;</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
