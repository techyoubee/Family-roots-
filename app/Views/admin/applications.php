<?php
/**
 * Membership requests across every family (platform administrators).
 *
 * @var string|null $title
 * @var array       $rows
 * @var array       $counts
 * @var int         $openTotal
 * @var array       $filters
 */
$statusOptions = [
    'open'               => 'Awaiting action',
    'all'                => 'All requests',
    'pending_review'     => 'Pending review',
    'auto_matched'       => 'Auto-matched',
    'appeal_requested'   => 'Appeals',
    'more_info_required' => 'More info requested',
    'submitted'          => 'Submitted',
    'approved'           => 'Approved',
    'declined'           => 'Declined',
    'cancelled'          => 'Cancelled',
    'draft'              => 'Drafts',
];
?>
<section class="section section--tight">
    <div class="wrap">
        <p class="text-small text-muted">
            <a href="<?= e(route('admin.index')) ?>">&larr; Platform overview</a>
        </p>
        <p class="eyebrow">Platform administration</p>
        <h1>Membership requests</h1>
        <p class="text-muted prose">
            Every request to join a family, across the whole platform.
            <?= e((int) $openTotal) ?> currently await a family administrator's decision.
            Opening a request takes you to that family's review screen.
        </p>

        <div class="card mb-4">
            <form class="filter-bar" method="get" action="<?= e(url('/admin/applications')) ?>" role="search">
                <div class="form-row">
                    <label for="q">Search applicant, family or reference</label>
                    <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="e.g. BH KIRU or REQ-ABC123">
                </div>
                <div class="form-row">
                    <label for="status">Show</label>
                    <select id="status" name="status">
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <button class="btn btn--primary" type="submit">Apply</button>
                </div>
                <?php if ($filters['q'] !== '' || $filters['status'] !== 'open'): ?>
                    <div class="form-row">
                        <a class="btn btn--outline" href="<?= e(url('/admin/applications')) ?>">Reset</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($rows)): ?>
            <div class="empty-state">
                <h3>No requests to show</h3>
                <p><?= $filters['status'] === 'open' ? 'Nothing is waiting for a decision right now.' : 'No requests match these filters.' ?></p>
            </div>
        <?php else: ?>
            <div class="card card--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th scope="col">Applicant</th>
                                <th scope="col">Family</th>
                                <th scope="col">Reference</th>
                                <th scope="col">Claimed</th>
                                <th scope="col">Score</th>
                                <th scope="col">Status</th>
                                <th scope="col">Submitted</th>
                                <th scope="col"><span class="visually-hidden">Review</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($row['applicant_name']) ?></strong>
                                        <div class="text-small text-muted"><?= e($row['applicant_email']) ?></div>
                                    </td>
                                    <td class="text-small">
                                        <a href="<?= e(url('/families/' . rawurlencode($row['family_public_id']))) ?>"><?= e($row['family_name']) ?></a>
                                        <div class="text-small text-muted"><?= e($row['family_public_id']) ?></div>
                                    </td>
                                    <td class="text-small"><?= e($row['public_id']) ?></td>
                                    <td class="text-small"><?= e(status_label((string) ($row['claimed_relationship'] ?? 'not stated'))) ?></td>
                                    <td class="text-small">
                                        <?php if ($row['match_score'] !== null): ?>
                                            <?= e(number_format((float) $row['match_score'], 1)) ?>%
                                        <?php else: ?>
                                            <span class="text-muted">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= status_badge($row['status']) ?></td>
                                    <td class="text-small"><?= e(format_date($row['submitted_at'] ?? $row['created_at'])) ?></td>
                                    <td class="text-small">
                                        <?php if (route_exists('family.applications.review')): ?>
                                            <a href="<?= e(route('family.applications.review', ['family' => $row['family_public_id'], 'application' => $row['public_id']])) ?>">Review</a>
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
