<?php
/**
 * Register a new family. The person registering becomes its founder.
 *
 * @var string|null $title
 */
?>
<section class="section section--tight">
    <div class="wrap wrap--narrow">
        <p class="eyebrow">Register a family</p>
        <h1>Create your family profile</h1>
        <p class="text-muted">
            A profile describes where your family comes from and who its founding members are.
            You choose who can see it - a private profile is invisible to everyone outside its members - and it is
            never marked as historically verified just because it exists; verification needs documented evidence and
            platform review.
        </p>

        <div class="card">
            <form class="form" method="post" action="<?= e(url('/families/create')) ?>" novalidate>
                <?= csrf_field() ?>

                <div class="form-row">
                    <label for="name">Family name</label>
                    <input type="text" id="name" name="name" value="<?= e(old('name')) ?>" maxlength="150" required
                           placeholder="e.g. Kiru family"
                           <?= has_error('name') ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>>
                    <p class="hint">The name your family uses. It becomes part of the profile link and can be changed later.</p>
                    <?php echo error('name'); ?>
                </div>

                <div class="form-row">
                    <label for="description">Short description</label>
                    <textarea id="description" name="description" rows="3" maxlength="2000"><?= e(old('description')) ?></textarea>
                    <p class="hint">One or two sentences about this family. Shown in the directory when you list the profile publicly.</p>
                    <?php echo error('description'); ?>
                </div>

                <div class="form-row">
                    <label for="origins">Origins</label>
                    <input type="text" id="origins" name="origins" value="<?= e(old('origins')) ?>" maxlength="255"
                           placeholder="e.g. Originally from Sulawesi, Indonesia">
                    <p class="hint">Where the family traces its roots. Never used for automatic matching on its own.</p>
                    <?php echo error('origins'); ?>
                </div>

                <div class="form-row form-row--split">
                    <div>
                        <label for="region">Region</label>
                        <input type="text" id="region" name="region" value="<?= e(old('region')) ?>" maxlength="120" placeholder="e.g. South Sulawesi">
                        <?php echo error('region'); ?>
                    </div>
                    <div>
                        <label for="country">Country</label>
                        <input type="text" id="country" name="country" value="<?= e(old('country')) ?>" maxlength="120" placeholder="e.g. Indonesia">
                        <?php echo error('country'); ?>
                    </div>
                </div>

                <div class="form-row">
                    <label for="visibility">Who can see this profile?</label>
                    <select id="visibility" name="visibility" required>
                        <option value="private" <?= old('visibility') === 'private' ? 'selected' : '' ?>>Private - only members can find it</option>
                        <option value="members" <?= old('visibility', 'members') === 'members' ? 'selected' : '' ?>>Members - visible to signed-in members</option>
                        <option value="directory" <?= old('visibility') === 'directory' ? 'selected' : '' ?>>Listed - appear in the public directory</option>
                    </select>
                    <p class="hint">A private profile is invisible to everyone else. You can change this at any time in settings.</p>
                    <?php echo error('visibility'); ?>
                </div>

                <div class="form-actions">
                    <button class="btn btn--primary" type="submit">Register the family</button>
                    <?php if (route_exists('dashboard')): ?>
                        <a class="text-small" href="<?= e(route('dashboard')) ?>">Cancel and return to the dashboard</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</section>
