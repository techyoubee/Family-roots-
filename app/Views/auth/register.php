<?php
/**
 * Create an account.
 *
 * @var string|null $title
 */
?>
<section class="section">
    <div class="wrap">
        <div class="auth-panel">
            <div class="auth-panel__aside">
                <p class="eyebrow">Join Family Roots</p>
                <h1>Create your account</h1>
                <p class="prose">
                    An account lets you register a family, declare your ancestry, and apply to families you
                    already belong to. Your email address is confirmed before you can add or edit records, and
                    your details are never shown as family data until you choose to link them.
                </p>
                <ul class="list-plain text-small text-muted">
                    <li>Records you add are private to your family by default.</li>
                    <li>Matching scores are workflow indicators, never proof of kinship.</li>
                    <li>You can update your details or request account deletion at any time.</li>
                </ul>
            </div>

            <form class="auth-panel__form form" method="post" action="<?= e(url('/register')) ?>" novalidate>
                <?= csrf_field() ?>

                <div class="form-row">
                    <label for="full_name">Full name</label>
                    <input type="text" id="full_name" name="full_name" value="<?= e(old('full_name')) ?>"
                           autocomplete="name" maxlength="150" required
                           <?= has_error('full_name') ? 'aria-invalid="true" aria-describedby="full_name-error"' : '' ?>>
                    <p class="hint">Use the name your family knows you by. You can add a display name later.</p>
                    <?php echo error('full_name'); ?>
                </div>

                <div class="form-row">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" value="<?= e(old('email')) ?>"
                           autocomplete="email" maxlength="190" required
                           <?= has_error('email') ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>>
                    <p class="hint">We send a confirmation link here. One account per address.</p>
                    <?php echo error('email'); ?>
                </div>

                <div class="form-row">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" autocomplete="new-password" minlength="10" required
                           <?= has_error('password') ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>>
                    <p class="hint">At least 10 characters. A short phrase beats a short password.</p>
                    <?php echo error('password'); ?>
                </div>

                <div class="form-row">
                    <label for="password_confirmation">Confirm password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
                </div>

                <div class="form-row">
                    <label class="checkbox" for="terms">
                        <input type="checkbox" id="terms" name="terms" value="1" required>
                        <span>I agree to the <a href="<?= e(route_exists('privacy') ? route('privacy') : url('/privacy')) ?>">privacy policy</a> and understand that my account details are used to manage my family records and verification requests.</span>
                    </label>
                    <?php echo error('terms'); ?>
                </div>

                <div class="form-actions">
                    <button class="btn btn--primary" type="submit">Create account</button>
                    <?php if (route_exists('auth.login')): ?>
                        <a class="text-small" href="<?= e(route('auth.login')) ?>">I already have an account</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</section>
