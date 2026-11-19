<?php
/**
 * A family's membership review queue (administrators).
 *
 * @var string|null $title
 * @var array       $family
 * @var array       $rows
 * @var string      $status
 * @var int         $pending
 */
$base = '/families/' . rawurlencode($family['public_id']) . '/applications';
$filters = [
    ''                  => 'All applications',
    'pending_review'    => 'Awaiting review',
    'auto_matched'      => 'Auto-matched (confirm)',
    'appeal_requested'  => 'Appeals',
    'more_info_required' => 'More info requested',
    'approved'          => 'Approved',
    'declined'          => 'Declined',
    'cancelled'         => 'Cancelled',
];
?>
<section class="section section--tight">
    <div class="wrap">
        <p class="text-small text-muted">
            <a href="<?= e(url('/families/' . rawurlencode($family['public_id']))) ?>">&larr; Back to the family profile</a>
        </p>
        <div class="split mb-4">
            <div>
                <p class="eyebrow">Membership review &middot; <?= e($family['public_id']) ?></p>
                <h1><?= e($family['name']) ?></h1>
                <p class="text-muted">
                    Every application is a documented claim, not an automatic grant. Read the declared ancestors
                    and the matching explanation, then decide.
                </p>
            </div>
            <div>
                <?php if ($pending > 0): ?>
                    <span class="badge badge--pending"><?= e((int) $pending) ?> awaiting a decision</span>
                <?php else: ?>
                    <span class="badge badge--verified">Queue clear</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-4">
            <form class="form" method="get" action="<?= e(url($base)) ?>">
                <div class="form-row">
                    <label for="status">Show</label>
                    <select id="status" name="status" data-auto-submit>
                        <?php foreach ($filters as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button class="btn btn--primary" type="submit">Apply filter</button>
                </div>
            </form>
        </div>

        <?php if (empty($rows)): ?>
            <div class="empty-state">
                <h3>Nothing to review<?= $status !== '' ? ' in this filter' : '' ?></h3>
                <p>Applications appear here as soon as an applicant submits them.</p>
            </div>
        <?php else: ?>
            <div class="card card--flush">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th scope="col">Applicant</th>
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
                                        <strong><?= e($row['applicant_name'] ?? 'Unknown') ?></strong>
                                        <div class="text-small text-muted"><?= e($row['applicant_email'] ?? '') ?></div>
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
                                        <a href="<?= e(route('family.applications.review', ['family' => $family['public_id'], 'application' => $row['public_id']])) ?>">Review</a>
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
