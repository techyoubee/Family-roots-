<?php
/**
 * Request a password reset link.
 *
 * @var string|null $title
 */
?>
<section class="section">
    <div class="wrap">
        <div class="card auth-card">
            <p class="eyebrow">Account recovery</p>
            <h1>Reset your password</h1>
            <p class="text-muted prose">
                Enter the email address registered on your account. If an account exists for it, we send a
                one-time link that stays valid for a short window. We never confirm whether an address is
                registered, so you can try without worry.
            </p>

            <form class="form mt-5" method="post" action="<?= e(url('/password/forgot')) ?>" novalidate>
                <?= csrf_field() ?>

                <div class="form-row">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" value="<?= e(old('email')) ?>" autocomplete="email" required
                           <?= has_error('email') ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>>
                    <?php echo error('email'); ?>
                </div>

                <div class="form-actions">
                    <button class="btn btn--primary" type="submit">Send reset link</button>
                    <?php if (route_exists('auth.login')): ?>
                        <a class="btn btn--outline" href="<?= e(route('auth.login')) ?>">Back to sign in</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</section>
