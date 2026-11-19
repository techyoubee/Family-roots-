<?php
/**
 * Every family on the platform (platform administrators).
 *
 * @var string|null $title
 * @var array       $families
 * @var string      $search
 */
?>
<section class="section section--tight">
    <div class="wrap">
        <p class="text-small text-muted">
            <a href="<?= e(route('admin.index')) ?>">&larr; Platform overview</a>
        </p>
        <p class="eyebrow">Platform administration</p>
        <h1>All families &amp; their members</h1>
        <p class="text-muted prose">
            Open a family's member list to see the registered accounts that belong to it. This is the same
            screen family administrators use under <strong>Manage</strong>, and it is read-only for platform
            administrators who are not members of that family.
        </p>

        <div class="card mb-4">
            <form class="filter-bar" method="get" action="<?= e(url('/admin/families')) ?>" role="search">
                <div class="form-row">
                    <label for="q">Search family name, public ID or country</label>
                    <input type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="e.g. BH KIRU or FAM-77E493">
                </div>
                <div class="form-row">
                    <button class="btn btn--primary" type="submit">Search</button>
                </div>
                <?php if ($search !== ''): ?>
                    <div class="form-row">
                        <a class="btn btn--outline" href="<?= e(url('/admin/families')) ?>">Clear</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($families)): ?>
            <div class="empty-state">
                <h3>No families matched</h3>
                <p>Try a different name, public ID or country.</p>
            </div>
        <?php else: ?>
            <div class="card card--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th scope="col">Family</th>
                                <th scope="col">Location</th>
                                <th scope="col">Visibility</th>
                                <th scope="col">Verification</th>
                                <th scope="col">Members</th>
                                <th scope="col">Ancestors</th>
                                <th scope="col">Open requests</th>
                                <th scope="col"><span class="visually-hidden">Open</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($families as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($row['name']) ?></strong>
                                        <div class="text-small text-muted"><?= e($row['public_id']) ?></div>
                                    </td>
                                    <td class="text-small">
                                        <?= e(trim(($row['region'] ?: '') . (($row['region'] && $row['country']) ? ', ' : '') . ($row['country'] ?: '')) ?: 'Not recorded') ?>
                                    </td>
                                    <td class="text-small"><?= e(status_label($row['visibility'])) ?></td>
                                    <td><?= status_badge($row['verification_status']) ?></td>
                                    <td class="text-small"><strong><?= e((int) $row['member_count']) ?></strong></td>
                                    <td class="text-small"><?= e((int) $row['ancestor_count']) ?></td>
                                    <td class="text-small">
                                        <?php if ((int) $row['open_requests'] > 0): ?>
                                            <span class="badge badge--pending"><?= e((int) $row['open_requests']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-small">
                                        <?php if (route_exists('family.members')): ?>
                                            <a href="<?= e(route('family.members', ['family' => $row['public_id']])) ?>">Members</a>
                                        <?php endif; ?>
                                        <?php if (route_exists('family.applications')): ?>
                                            &middot; <a href="<?= e(route('family.applications', ['family' => $row['public_id']])) ?>">Requests</a>
                                        <?php endif; ?>
                                        <?php if (route_exists('families.show')): ?>
                                            &middot; <a href="<?= e(route('families.show', ['family' => $row['public_id']])) ?>">Profile</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
