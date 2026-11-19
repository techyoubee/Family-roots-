<?php
/**
 * Account settings: profile, password and deletion request.
 *
 * @var string|null $title
 * @var array       $user
 */
?>
<section class="section section--tight">
    <div class="wrap">
        <p class="eyebrow">Your account</p>
        <h1>Account settings</h1>
        <p class="text-muted prose">
            Your login details are separate from your family records. Nothing here is shown to other
            family members unless you choose to link your account to an ancestor record.
        </p>
    </div>
</section>

<section class="section section--tight">
    <div class="wrap">
        <div class="grid grid--2">

            <div class="card">
                <div class="card__header"><h2>Profile</h2></div>
                <div class="card__body">
                    <form method="post" action="<?= e(url('/settings/profile')) ?>" novalidate>
                        <?= csrf_field() ?>
                        <div class="form-row">
                            <label for="full_name">Full name</label>
                            <input type="text" id="full_name" name="full_name" value="<?= e(old('full_name', $user['full_name'])) ?>" maxlength="150" required
                                   <?= has_error('full_name') ? 'aria-invalid="true" aria-describedby="full_name-error"' : '' ?>>
                            <?php echo error('full_name'); ?>
                        </div>
                        <div class="form-row">
                            <label for="display_name">Display name <span class="hint">(optional)</span></label>
                            <input type="text" id="display_name" name="display_name" value="<?= e(old('display_name', $user['display_name'])) ?>" maxlength="80">
                            <p class="hint">Shown in place of your full name where a shorter form is useful.</p>
                            <?php echo error('display_name'); ?>
                        </div>
                        <div class="form-row">
                            <label for="phone">Phone <span class="hint">(optional)</span></label>
                            <input type="tel" id="phone" name="phone" value="<?= e(old('phone', $user['phone'])) ?>" maxlength="40">
                            <?php echo error('phone'); ?>
                        </div>
                        <div class="form-row">
                            <label for="timezone">Time zone</label>
                            <input type="text" id="timezone" name="timezone" value="<?= e(old('timezone', $user['timezone'])) ?>" maxlength="64">
                            <?php echo error('timezone'); ?>
                        </div>
                        <div class="form-actions">
                            <button class="btn btn--primary" type="submit">Save profile</button>
                        </div>
                    </form>
                </div>
            </div>

            <div>
                <div class="card">
                    <div class="card__header"><h2>Password</h2></div>
                    <div class="card__body">
                        <form method="post" action="<?= e(url('/settings/password')) ?>" novalidate>
                            <?= csrf_field() ?>
                            <div class="form-row">
                                <label for="current_password">Current password</label>
                                <input type="password" id="current_password" name="current_password" autocomplete="current-password" required
                                       <?= has_error('current_password') ? 'aria-invalid="true" aria-describedby="current_password-error"' : '' ?>>
                                <?php echo error('current_password'); ?>
                            </div>
                            <div class="form-row">
                                <label for="password">New password</label>
                                <input type="password" id="password" name="password" autocomplete="new-password" minlength="10" required
                                       <?= has_error('password') ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>>
                                <p class="hint">At least 10 characters.</p>
                                <?php echo error('password'); ?>
                            </div>
                            <div class="form-row">
                                <label for="password_confirmation">Confirm new password</label>
                                <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
                            </div>
                            <div class="form-actions">
                                <button class="btn btn--primary" type="submit">Change password</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mt-5 card--danger">
                    <div class="card__header"><h2>Delete this account</h2></div>
                    <div class="card__body">
                        <p class="text-small text-muted">
                            Deletion is reviewed by a platform administrator before anything is removed. Family
                            records you contributed stay, but your name is detached from them. Type
                            <strong>DELETE</strong> to confirm.
                        </p>
                        <form method="post" action="<?= e(url('/settings/deletion')) ?>" novalidate>
                            <?= csrf_field() ?>
                            <div class="form-row">
                                <label for="confirm">Type DELETE to confirm</label>
                                <input type="text" id="confirm" name="confirm" autocomplete="off" required
                                       <?= has_error('confirm') ? 'aria-invalid="true" aria-describedby="confirm-error"' : '' ?>>
                                <?php echo error('confirm'); ?>
                            </div>
                            <div class="form-actions">
                                <button class="btn btn--danger" type="submit">Request account deletion</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>
