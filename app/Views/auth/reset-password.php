<?php
/**
 * Choose a new password from an emailed reset link.
 *
 * @var string|null $title
 * @var string      $token
 * @var bool        $valid
 */
?>
<section class="section">
    <div class="wrap">
        <div class="card auth-card">
            <?php if (!$valid): ?>
                <p class="eyebrow">Account recovery</p>
                <h1>This link is no longer valid</h1>
                <div class="alert alert--warning" role="alert">
                    <div>Reset links are single use and expire after a short time. Request a new one and use it straight away.</div>
                </div>
                <div class="form-actions">
                    <?php if (route_exists('auth.password.request')): ?>
                        <a class="btn btn--primary" href="<?= e(route('auth.password.request')) ?>">Request a new link</a>
                    <?php endif; ?>
                    <?php if (route_exists('auth.login')): ?>
                        <a class="btn btn--outline" href="<?= e(route('auth.login')) ?>">Back to sign in</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p class="eyebrow">Account recovery</p>
                <h1>Choose a new password</h1>
                <p class="text-muted prose">
                    Pick a password of at least 10 characters. Changing your password signs out any other
                    outstanding reset links - you will need to sign in again with the new one.
                </p>

                <form class="form mt-5" method="post" action="<?= e(url('/password/reset')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">

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
                        <button class="btn btn--primary" type="submit">Save new password</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
