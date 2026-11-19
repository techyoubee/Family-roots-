<?php
/**
 * Create or edit one ancestor record.
 *
 * @var string|null $title
 * @var array      $family
 * @var array|null $ancestor
 * @var bool       $canAdmin
 */
$baseUrl = '/families/' . rawurlencode($family['public_id']) . '/ancestors';
$isEdit = $ancestor !== null;
$action = $isEdit
    ? $baseUrl . '/' . (int) $ancestor['id']
    : $baseUrl;
$sex = old('sex', $ancestor['sex'] ?? 'unknown');
$isLiving = (string) old('is_living', isset($ancestor['is_living']) ? (int) $ancestor['is_living'] : '0') === '1';
$recordStatus = old('record_status', $ancestor['record_status'] ?? 'proposed');
?>
<section class="section section--tight">
    <div class="wrap wrap--mid">
        <p class="text-small text-muted">
            <a href="<?= e(url($baseUrl)) ?>">&larr; Back to ancestor records</a>
        </p>
        <p class="eyebrow"><?= $isEdit ? 'Edit record' : 'Add an ancestor' ?> &middot; <?= e($family['name']) ?></p>
        <h1><?= $isEdit ? e(\App\Models\Ancestor::displayName($ancestor)) : 'New ancestor record' ?></h1>
        <p class="text-muted">
            Write what the family actually knows. Uncertain dates go in the text fields - leave the exact
            date empty rather than guessing. New records are stored as <strong>proposed</strong>.
        </p>

        <div class="card">
            <form class="form" method="post" action="<?= e(url($action)) ?>" novalidate>
                <?= csrf_field() ?>

                <div class="form-row form-row--split">
                    <div>
                        <label for="given_names">Given names *</label>
                        <input type="text" id="given_names" name="given_names" value="<?= e(old('given_names', $ancestor['given_names'] ?? '')) ?>" maxlength="150" required
                               <?= has_error('given_names') ? 'aria-invalid="true" aria-describedby="given_names-error"' : '' ?>>
                        <?php echo error('given_names'); ?>
                    </div>
                    <div>
                        <label for="surname">Surname</label>
                        <input type="text" id="surname" name="surname" value="<?= e(old('surname', $ancestor['surname'] ?? '')) ?>" maxlength="100">
                        <?php echo error('surname'); ?>
                    </div>
                </div>

                <div class="form-row">
                    <label for="alt_names">Other names / spellings</label>
                    <input type="text" id="alt_names" name="alt_names" value="<?= e(old('alt_names', $ancestor['alt_names'] ?? '')) ?>" maxlength="500">
                    <p class="hint">Comma-separated variants the family has used for this person.</p>
                    <?php echo error('alt_names'); ?>
                </div>

                <div class="form-row form-row--split">
                    <div>
                        <label for="sex">Sex *</label>
                        <select id="sex" name="sex" required>
                            <option value="unknown" <?= $sex === 'unknown' ? 'selected' : '' ?>>Unknown</option>
                            <option value="male" <?= $sex === 'male' ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= $sex === 'female' ? 'selected' : '' ?>>Female</option>
                        </select>
                        <?php echo error('sex'); ?>
                    </div>
                    <div>
                        <label for="generation">Generation *</label>
                        <input type="number" id="generation" name="generation" min="0" max="9" step="1"
                               value="<?= e(old('generation', $ancestor['generation'] ?? '0')) ?>" required>
                        <p class="hint">0 = the oldest known generation. Count upward toward the present.</p>
                        <?php echo error('generation'); ?>
                    </div>
                </div>

                <div class="form-row form-row--split">
                    <div>
                        <label for="birth_date">Birth date</label>
                        <input type="date" id="birth_date" name="birth_date" value="<?= e(old('birth_date', $ancestor['birth_date'] ?? '')) ?>">
                        <?php echo error('birth_date'); ?>
                    </div>
                    <div>
                        <label for="birth_date_text">Birth date as recorded</label>
                        <input type="text" id="birth_date_text" name="birth_date_text" value="<?= e(old('birth_date_text', $ancestor['birth_date_text'] ?? '')) ?>" maxlength="60" placeholder="e.g. circa 1912 or 3 Rajab 1331">
                        <p class="hint">Use this when the exact date is unknown or recorded differently.</p>
                        <?php echo error('birth_date_text'); ?>
                    </div>
                </div>

                <div class="form-row form-row--split">
                    <div>
                        <label for="death_date">Death date</label>
                        <input type="date" id="death_date" name="death_date" value="<?= e(old('death_date', $ancestor['death_date'] ?? '')) ?>">
                        <?php echo error('death_date'); ?>
                    </div>
                    <div>
                        <label for="death_date_text">Death date as recorded</label>
                        <input type="text" id="death_date_text" name="death_date_text" value="<?= e(old('death_date_text', $ancestor['death_date_text'] ?? '')) ?>" maxlength="60">
                        <?php echo error('death_date_text'); ?>
                    </div>
                </div>

                <div class="form-row form-row--split">
                    <div>
                        <label for="birth_place">Birth place</label>
                        <input type="text" id="birth_place" name="birth_place" value="<?= e(old('birth_place', $ancestor['birth_place'] ?? '')) ?>" maxlength="160">
                        <?php echo error('birth_place'); ?>
                    </div>
                    <div>
                        <label for="death_place">Death place</label>
                        <input type="text" id="death_place" name="death_place" value="<?= e(old('death_place', $ancestor['death_place'] ?? '')) ?>" maxlength="160">
                        <?php echo error('death_place'); ?>
                    </div>
                </div>

                <div class="form-row form-row--split">
                    <div>
                        <label for="branch">Branch</label>
                        <input type="text" id="branch" name="branch" value="<?= e(old('branch', $ancestor['branch'] ?? '')) ?>" maxlength="120" placeholder="e.g. Elder line">
                        <?php echo error('branch'); ?>
                    </div>
                    <div>
                        <label class="checkbox" for="is_living">
                            <input type="checkbox" id="is_living" name="is_living" value="1" <?= $isLiving ? 'checked' : '' ?>>
                            <span>This person may still be living</span>
                        </label>
                        <p class="hint">Living people are shown with less detail by default.</p>
                    </div>
                </div>

                <div class="form-row">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="4" maxlength="10000"><?= e(old('notes', $ancestor['notes'] ?? '')) ?></textarea>
                    <?php echo error('notes'); ?>
                </div>

                <div class="form-row">
                    <label for="evidence_source">Evidence / source notes</label>
                    <textarea id="evidence_source" name="evidence_source" rows="3" maxlength="4000"><?= e(old('evidence_source', $ancestor['evidence_source'] ?? '')) ?></textarea>
                    <p class="hint">Where this information came from - a document, an elder's story, a headstone. Formal evidence review comes later.</p>
                    <?php echo error('evidence_source'); ?>
                </div>

                <?php if ($canAdmin): ?>
                    <div class="form-row">
                        <label for="record_status">Record status (administrators)</label>
                        <select id="record_status" name="record_status">
                            <?php foreach (['proposed', 'confirmed', 'unknown', 'disputed'] as $status): ?>
                                <option value="<?= e($status) ?>" <?= $recordStatus === $status ? 'selected' : '' ?>><?= e(status_label($status)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="hint">Marking a record confirmed says the family reviewed it - it does not make the family profile historically verified.</p>
                        <?php echo error('record_status'); ?>
                    </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button class="btn btn--primary" type="submit"><?= $isEdit ? 'Save the record' : 'Add the ancestor' ?></button>
                    <a class="text-small" href="<?= e(url($baseUrl)) ?>">Discard and go back</a>
                </div>
            </form>
        </div>
    </div>
</section>
