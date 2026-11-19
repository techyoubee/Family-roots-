<?php
/**
 * Review one membership application (administrators).
 *
 * @var string|null $title
 * @var array       $family
 * @var array       $request    membership_requests row (joined applicant fields absent here)
 * @var array       $claims
 * @var array|null  $assessment
 */
$r = $request;
$status = $r['status'];
$decidable = in_array($status, ['submitted', 'auto_matched', 'pending_review', 'appeal_requested', 'more_info_required'], true);
$base = '/families/' . rawurlencode($family['public_id']) . '/applications';
$relationshipOptions = [
    'father'            => 'Father',
    'mother'            => 'Mother',
    'grandfather'       => 'Grandfather',
    'grandmother'       => 'Grandmother',
    'great_grandfather' => 'Great-grandfather',
    'great_grandmother' => 'Great-grandmother',
    'parent'            => 'Parent (unspecified)',
    'other'             => 'Other relative',
];
$applicant = \App\Core\Database::first('SELECT full_name, email FROM users WHERE id = ?', [(int) $r['applicant_user_id']]);
?>
<section class="section section--tight">
    <div class="wrap">
        <p class="text-small text-muted">
            <a href="<?= e(url($base)) ?>">&larr; Back to the review queue</a>
        </p>
        <div class="split mb-4">
            <div>
                <p class="eyebrow">Application &middot; <?= e($r['public_id']) ?></p>
                <h1 class="mb-0"><?= e($applicant['full_name'] ?? 'Applicant') ?></h1>
                <p class="text-muted">
                    <?= e($applicant['email'] ?? '') ?>
                    &middot; submitted <?= e(format_date($r['submitted_at'] ?? $r['created_at'])) ?>
                </p>
            </div>
            <div><?= status_badge($status) ?></div>
        </div>

        <?php if ($r['decision_reason']): ?>
            <div class="alert alert--info" role="status">
                <div><strong>Matching assessment:</strong> <?= e($r['decision_reason']) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($r['applicant_message'])): ?>
            <div class="card mb-4">
                <h2>Message from the applicant</h2>
                <p><?= nl2br(e($r['applicant_message'])) ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($r['appeal_reason'])): ?>
            <div class="alert alert--warning" role="alert">
                <div><strong>Appeal:</strong> <?= nl2br(e($r['appeal_reason'])) ?></div>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section section--tight">
    <div class="wrap">
        <h2>Declared ancestors</h2>
        <?php if (empty($claims)): ?>
            <div class="empty-state">
                <h3>No ancestors were declared</h3>
                <p>Without named relatives there is nothing to review. Ask the applicant for more information.</p>
            </div>
        <?php else: ?>
            <div class="card card--flush mb-4">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th scope="col">Declared name</th>
                                <th scope="col">Relationship</th>
                                <th scope="col">Years</th>
                                <th scope="col">Best match</th>
                                <th scope="col">Confidence</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($claims as $claim): ?>
                                <tr>
                                    <td>
                                        <strong><?= e(trim(($claim['given_names'] ?? '') . ' ' . ($claim['surname'] ?? ''))) ?></strong>
                                        <?php if (!empty($claim['alt_names'])): ?><div class="text-small text-muted">Also: <?= e($claim['alt_names']) ?></div><?php endif; ?>
                                    </td>
                                    <td><?= e($relationshipOptions[$claim['relationship']] ?? status_label($claim['relationship'])) ?></td>
                                    <td class="text-small"><?= e(trim(($claim['birth_date_text'] ?? '') !== '' ? $claim['birth_date_text'] : '?') . ' - ' . ($claim['death_date_text'] ?? '')) ?></td>
                                    <td class="text-small">
                                        <?php if (!empty($claim['matched_ancestor_id'])): ?>
                                            <?php $matched = \App\Models\Ancestor::find($claim['matched_ancestor_id']); ?>
                                            <?= $matched ? e(\App\Models\Ancestor::displayName($matched)) : '<span class="text-muted">&mdash;</span>' ?>
                                        <?php else: ?>
                                            <span class="text-muted">No match</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-small">
                                        <?php if ($claim['match_confidence'] !== null): ?>
                                            <?= e(number_format((float) $claim['match_confidence'], 1)) ?>%
                                        <?php else: ?>
                                            <span class="text-muted">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if (!empty($claim['match_explanation'])): ?>
                                    <tr>
                                        <td colspan="5" class="text-small text-muted"><?= e($claim['match_explanation']) ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($decidable): ?>
<section class="section section--tight">
    <div class="wrap grid grid--2">
        <div class="card">
            <h2>Approve</h2>
            <p class="text-small text-muted">
                Approving makes this person an active member of <?= e($family['name']) ?>.
            </p>
            <form class="form" method="post" action="<?= e(url($base . '/' . rawurlencode($r['public_id']))) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="approve">
                <button class="btn btn--primary" type="submit">Approve membership</button>
            </form>
        </div>

        <div class="card">
            <h2>Request more information</h2>
            <form class="form" method="post" action="<?= e(url($base . '/' . rawurlencode($r['public_id']))) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="more_info">
                <div class="form-row">
                    <label for="more_info_message">What do you need from the applicant?</label>
                    <textarea id="more_info_message" name="more_info_message" rows="3" maxlength="2000" required></textarea>
                </div>
                <button class="btn btn--outline" type="submit">Send request</button>
            </form>
        </div>
    </div>

    <div class="wrap">
        <div class="card card--review">
            <h2>Decline</h2>
            <p class="text-small text-muted">A decline must include a reason the applicant can read and appeal.</p>
            <form class="form" method="post" action="<?= e(url($base . '/' . rawurlencode($r['public_id']))) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="decline">
                <div class="form-row">
                    <label for="decline_reason">Reason for the applicant *</label>
                    <textarea id="decline_reason" name="decline_reason" rows="3" maxlength="2000" required></textarea>
                </div>
                <button class="btn btn--ghost-light" type="submit">Decline application</button>
            </form>
        </div>
    </div>
</section>
<?php else: ?>
<section class="section section--tight">
    <div class="wrap">
        <div class="alert alert--info" role="status">
            <div>This application is <strong><?= e(status_label($status)) ?></strong> and no longer needs a decision.</div>
        </div>
    </div>
</section>
<?php endif; ?>
