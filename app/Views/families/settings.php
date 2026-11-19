<?php
/**
 * Family profile settings (administrators only).
 *
 * @var string|null $title
 * @var array      $family
 * @var string|null $myRole
 */
$visibilityLabels = [
    'private'   => 'Private - only members can find it',
    'members'   => 'Members - visible to signed-in members',
    'directory' => 'Listed - appear in the public directory',
];
?>
<section class="section section--tight">
    <div class="wrap wrap--mid">
        <p class="text-small text-muted">
            <a href="<?= e(url('/families/' . rawurlencode($family['public_id']))) ?>">&larr; Back to the family profile</a>
        </p>
        <p class="eyebrow">Family settings &middot; <?= e($family['public_id']) ?></p>
        <h1>Profile settings</h1>
        <p class="text-muted">
            Changes are recorded in the family audit trail. The web link (slug) never changes after registration.
            Family verification status is managed by the platform, not here.
        </p>

        <div class="card">
            <form class="form" method="post" action="<?= e(url('/families/' . rawurlencode($family['public_id']) . '/settings')) ?>" novalidate>
                <?= csrf_field() ?>

                <div class="form-row">
                    <label for="name">Family name</label>
                    <input type="text" id="name" name="name" value="<?= e(old('name', $family['name'])) ?>" maxlength="150" required
                           <?= has_error('name') ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>>
                    <?php echo error('name'); ?>
                </div>

                <div class="form-row">
                    <label for="description">Short description</label>
                    <textarea id="description" name="description" rows="3" maxlength="2000"><?= e(old('description', $family['description'])) ?></textarea>
                    <?php echo error('description'); ?>
                </div>

                <div class="form-row">
                    <label for="history">Family history</label>
                    <textarea id="history" name="history" rows="8" maxlength="10000"><?= e(old('history', $family['history'])) ?></textarea>
                    <p class="hint">Visible to approved members only. Write what you know; mark uncertain parts as unconfirmed rather than deleting them.</p>
                    <?php echo error('history'); ?>
                </div>

                <div class="form-row">
                    <label for="origins">Origins</label>
                    <input type="text" id="origins" name="origins" value="<?= e(old('origins', $family['origins'])) ?>" maxlength="255">
                    <?php echo error('origins'); ?>
                </div>

                <div class="form-row">
                    <label for="founding_note">Founding ancestors</label>
                    <textarea id="founding_note" name="founding_note" rows="3" maxlength="2000"><?= e(old('founding_note', $family['founding_note'])) ?></textarea>
                    <p class="hint">Who the family traces itself from. This is a statement, not a verified fact.</p>
                    <?php echo error('founding_note'); ?>
                </div>

                <div class="form-row form-row--split">
                    <div>
                        <label for="region">Region</label>
                        <input type="text" id="region" name="region" value="<?= e(old('region', $family['region'])) ?>" maxlength="120">
                        <?php echo error('region'); ?>
                    </div>
                    <div>
                        <label for="country">Country</label>
                        <input type="text" id="country" name="country" value="<?= e(old('country', $family['country'])) ?>" maxlength="120">
                        <?php echo error('country'); ?>
                    </div>
                </div>

                <div class="form-row">
                    <label for="visibility">Who can see this profile?</label>
                    <select id="visibility" name="visibility" required>
                        <?php foreach ($visibilityLabels as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= old('visibility', $family['visibility']) === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php echo error('visibility'); ?>
                </div>

                <div class="form-actions">
                    <button class="btn btn--primary" type="submit">Save the profile</button>
                    <a class="text-small" href="<?= e(url('/families/' . rawurlencode($family['public_id']))) ?>">Discard changes</a>
                </div>
            </form>
        </div>
    </div>
</section>
