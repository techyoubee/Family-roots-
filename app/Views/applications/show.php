<?php
/**
 * One membership application, from the applicant's point of view.
 *
 * @var string|null $title
 * @var array       $request    the membership_requests row
 * @var array       $family
 * @var array       $claims     membership_request_claims rows
 * @var array|null  $assessment decoded match summary (null until submitted)
 * @var bool        $isOwner
 * @var bool        $editable
 */
$r = $request;
$status = $r['status'];
$isClosed = in_array($status, ['approved', 'declined', 'cancelled'], true);
$base = '/applications/' . rawurlencode($r['public_id']);
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
$claimById = [];
foreach ($claims as $c) {
    $claimById[(int) $c['id']] = $c;
}
?>
<section class="section section--tight">
    <div class="wrap">
        <p class="text-small text-muted">
            <a href="<?= e(url('/applications')) ?>">&larr; Back to your applications</a>
        </p>
        <div class="split mb-4">
            <div>
                <p class="eyebrow">Application &middot; <?= e($r['public_id']) ?></p>
                <h1 class="mb-0"><?= e($family['name']) ?></h1>
                <p class="text-muted">
                    Submitted <?= e(format_date($r['submitted_at'] ?? $r['created_at'])) ?>
                    &middot; last updated <?= e(format_date($r['updated_at'] ?? $r['created_at'])) ?>
                </p>
            </div>
            <div><?= status_badge($status) ?></div>
        </div>

        <?php if ($r['decision_reason']): ?>
            <div class="alert alert--info" role="status">
                <div>
                    <strong>Matching assessment:</strong> <?= e($r['decision_reason']) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($status === 'more_info_required' && !empty($r['more_info_request'])): ?>
            <div class="alert alert--warning" role="alert">
                <div>
                    <strong>A moderator needs more information:</strong>
                    <?= nl2br(e($r['more_info_request'])) ?>
                    You can edit the details below and resubmit.
                </div>
            </div>
        <?php endif; ?>

        <?php if ($status === 'declined'): ?>
            <div class="alert alert--error" role="alert">
                <div>
                    <strong>This application was declined.</strong>
                    <?php if (!empty($r['decline_reason'])): ?>
                        Reason: <?= nl2br(e($r['decline_reason'])) ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($status === 'approved'): ?>
            <div class="alert alert--success" role="status">
                <div>
                    You are now a member of <?= e($family['name']) ?>. You can open the family tree and begin
                    exploring its records.
                </div>
            </div>
        <?php endif; ?>

        <?php if ($status === 'appeal_requested'): ?>
            <div class="alert alert--info" role="status">
                <div>Your appeal has been received and is awaiting review. <?= nl2br(e((string) $r['appeal_reason'])) ?></div>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section section--tight">
    <div class="wrap grid grid--2">
        <div class="card">
            <h2>About your claim</h2>
            <?php if ($editable): ?>
                <form class="form" method="post" action="<?= e(url($base)) ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <label for="claimed_relationship">How are you connected to this family?</label>
                        <select id="claimed_relationship" name="claimed_relationship">
                            <option value="">Not stated</option>
                            <?php foreach ($relationshipOptions as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= old('claimed_relationship', $r['claimed_relationship'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="hint">You are the reference point; list your ancestors in the next panel.</p>
                        <?php echo error('claimed_relationship'); ?>
                    </div>
                    <div class="form-row">
                        <label for="applicant_message">Message to the reviewer</label>
                        <textarea id="applicant_message" name="applicant_message" rows="4" maxlength="4000"><?= e(old('applicant_message', $r['applicant_message'] ?? '')) ?></textarea>
                        <p class="hint">Explain anything the reviewer should know. Keep private information out.</p>
                        <?php echo error('applicant_message'); ?>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn--primary" type="submit">Save details</button>
                    </div>
                </form>
            <?php else: ?>
                <ul class="meta-list">
                    <li><span class="meta-key">Connection:</span> <?= e($relationshipOptions[$r['claimed_relationship']] ?? 'Not stated') ?></li>
                    <li><span class="meta-key">Your message:</span> <?= $r['applicant_message'] ? nl2br(e($r['applicant_message'])) : '<span class="text-muted">None</span>' ?></li>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Status timeline</h2>
            <ul class="meta-list">
                <li><span class="meta-key">Created:</span> <?= e(format_datetime($r['created_at'])) ?></li>
                <?php if (!empty($r['submitted_at'])): ?><li><span class="meta-key">Submitted:</span> <?= e(format_datetime($r['submitted_at'])) ?></li><?php endif; ?>
                <?php if (!empty($r['last_reviewed_at'])): ?><li><span class="meta-key">Last reviewed:</span> <?= e(format_datetime($r['last_reviewed_at'])) ?></li><?php endif; ?>
                <?php if (!empty($r['decided_at'])): ?><li><span class="meta-key">Decided:</span> <?= e(format_datetime($r['decided_at'])) ?></li><?php endif; ?>
                <?php if (!empty($r['appealed_at'])): ?><li><span class="meta-key">Appealed:</span> <?= e(format_datetime($r['appealed_at'])) ?></li><?php endif; ?>
            </ul>

            <div class="form-actions">
                <?php if ($editable): ?>
                    <form method="post" action="<?= e(url($base . '/submit')) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn--primary" type="submit" <?= empty($claims) ? 'disabled' : '' ?>>Submit for review</button>
                    </form>
                <?php endif; ?>
                <?php if ($status === 'declined' && empty($r['appeal_reason'])): ?>
                    <form method="post" action="<?= e(url($base . '/appeal')) ?>" class="form form--narrow">
                        <?= csrf_field() ?>
                        <div class="form-row">
                            <label for="appeal_reason">Appeal this decision (once)</label>
                            <textarea id="appeal_reason" name="appeal_reason" rows="3" maxlength="2000" required></textarea>
                            <p class="hint">Explain what the reviewer may have missed.</p>
                        </div>
                        <button class="btn btn--outline" type="submit">Submit appeal</button>
                    </form>
                <?php endif; ?>
                <?php if (!$isClosed): ?>
                    <form method="post" action="<?= e(url($base . '/cancel')) ?>" data-confirm="Cancel this application?">
                        <?= csrf_field() ?>
                        <button class="btn btn--ghost-light" type="submit">Cancel application</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="section section--tight">
    <div class="wrap">
        <div class="split mb-3">
            <h2 class="mb-0">Declared ancestors</h2>
            <p class="text-small text-muted mb-0">
                A surname alone is never enough - add parents and grandparents so the match is meaningful.
            </p>
        </div>

        <?php if (empty($claims)): ?>
            <div class="empty-state">
                <h3>No ancestors declared yet</h3>
                <p>Add at least one parent or grandparent before submitting.</p>
            </div>
        <?php else: ?>
            <div class="card card--flush mb-4">
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Relationship</th>
                                <th scope="col">Years</th>
                                <th scope="col">Match</th>
                                <?php if ($editable): ?><th scope="col"><span class="visually-hidden">Remove</span></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($claims as $claim): ?>
                                <tr>
                                    <td>
                                        <strong><?= e(trim(($claim['given_names'] ?? '') . ' ' . ($claim['surname'] ?? ''))) ?></strong>
                                        <?php if (!empty($claim['birth_place'])): ?><div class="text-small text-muted"><?= e($claim['birth_place']) ?></div><?php endif; ?>
                                    </td>
                                    <td><?= e($relationshipOptions[$claim['relationship']] ?? status_label($claim['relationship'])) ?></td>
                                    <td class="text-small"><?= e(trim(($claim['birth_date_text'] ?? '') !== '' ? $claim['birth_date_text'] : '?') . ' - ' . ($claim['death_date_text'] ?? '')) ?></td>
                                    <td class="text-small">
                                        <?php if ($claim['match_confidence'] !== null && $claim['match_confidence'] > 0): ?>
                                            <?= e(number_format((float) $claim['match_confidence'], 1)) ?>%
                                            <?php if (!empty($claim['matched_ancestor_id'])): ?><span class="badge badge--confirmed">Matched</span><?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($editable): ?>
                                        <td>
                                            <form method="post" action="<?= e(url($base . '/claims/' . (int) $claim['id'] . '/delete')) ?>">
                                                <?= csrf_field() ?>
                                                <button class="btn btn--ghost-light btn--small" type="submit">Remove</button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                                <?php if (!empty($claim['match_explanation'])): ?>
                                    <tr>
                                        <td colspan="<?= $editable ? 5 : 4 ?>" class="text-small text-muted"><?= e($claim['match_explanation']) ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($editable): ?>
            <div class="card">
                <h2>Add an ancestor</h2>
                <form class="form" method="post" action="<?= e(url($base . '/claims')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="form-row form-row--split">
                        <div>
                            <label for="relationship">Relationship *</label>
                            <select id="relationship" name="relationship" required>
                                <?php foreach ($relationshipOptions as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= old('relationship') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php echo error('relationship'); ?>
                        </div>
                        <div>
                            <label for="sex">Sex</label>
                            <select id="sex" name="sex">
                                <option value="unknown">Unknown</option>
                                <option value="male" <?= old('sex') === 'male' ? 'selected' : '' ?>>Male</option>
                                <option value="female" <?= old('sex') === 'female' ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row form-row--split">
                        <div>
                            <label for="given_names">Given names *</label>
                            <input type="text" id="given_names" name="given_names" value="<?= e(old('given_names')) ?>" maxlength="150" required>
                            <?php echo error('given_names'); ?>
                        </div>
                        <div>
                            <label for="surname">Surname</label>
                            <input type="text" id="surname" name="surname" value="<?= e(old('surname')) ?>" maxlength="100">
                            <?php echo error('surname'); ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <label for="alt_names">Other names / spellings</label>
                        <input type="text" id="alt_names" name="alt_names" value="<?= e(old('alt_names')) ?>" maxlength="500">
                    </div>
                    <div class="form-row form-row--split">
                        <div>
                            <label for="birth_date_text">Birth as recorded</label>
                            <input type="text" id="birth_date_text" name="birth_date_text" value="<?= e(old('birth_date_text')) ?>" maxlength="60" placeholder="e.g. circa 1912">
                        </div>
                        <div>
                            <label for="death_date_text">Death as recorded</label>
                            <input type="text" id="death_date_text" name="death_date_text" value="<?= e(old('death_date_text')) ?>" maxlength="60">
                        </div>
                    </div>
                    <div class="form-row form-row--split">
                        <div>
                            <label for="birth_place">Birth place</label>
                            <input type="text" id="birth_place" name="birth_place" value="<?= e(old('birth_place')) ?>" maxlength="160">
                        </div>
                        <div>
                            <label class="checkbox" for="is_living">
                                <input type="checkbox" id="is_living" name="is_living" value="1" <?= old('is_living') ? 'checked' : '' ?>>
                                <span>This person may still be living</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-row">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="3" maxlength="4000"><?= e(old('notes')) ?></textarea>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn--primary" type="submit">Add ancestor</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($assessment && !empty($assessment['claims'])): ?>
<section class="section section--tight">
    <div class="wrap">
        <div class="card">
            <h2>How the matching engine read your claims</h2>
            <p class="text-muted text-small">
                These scores are workflow indicators used to route your application - they are not proof of
                biological kinship. A moderator makes the final decision.
            </p>
            <ul class="meta-list">
                <?php foreach ($assessment['claims'] as $entry): ?>
                    <li>
                        <span class="meta-key"><?= e($entry['name'] ?: ($relationshipOptions[$entry['relationship']] ?? 'Claim')) ?>:</span>
                        <?= e(number_format((float) $entry['confidence'], 1)) ?>% -
                        <?= e($entry['explanation']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>
<?php endif; ?>
