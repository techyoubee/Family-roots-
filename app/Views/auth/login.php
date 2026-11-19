<?php
/**
 * Sign in.
 *
 * @var string|null $title
 * @var string      $next
 */
$next = isset($next) ? (string) $next : '';
?>
<section class="section">
    <div class="wrap">
        <div class="auth-panel">
            <div class="auth-panel__aside">
                <p class="eyebrow">Family Roots</p>
                <h1>Sign in to your account</h1>
                <p class="prose">
                    Access your families, your ancestry records and every verification request you are part of.
                    Family Roots never asks for a surname alone - sign in is protected by a password you choose
                    and an email address you confirm.
                </p>
                <?php if (route_exists('auth.register')): ?>
                    <p class="text-muted">New here? <a href="<?= e(route('auth.register')) ?>">Create an account</a> - it takes a minute and you confirm your email before adding records.</p>
                <?php endif; ?>
            </div>

            <form class="auth-panel__form form" method="post" action="<?= e(url('/login')) ?>" novalidate>
                <?= csrf_field() ?>
                <?php if ($next !== ''): ?>
                    <input type="hidden" name="next" value="<?= e($next) ?>">
                <?php endif; ?>

                <div class="form-row">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" value="<?= e(old('email')) ?>"
                           autocomplete="email" required <?= has_error('email') ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>>
                    <?php echo error('email'); ?>
                </div>

                <div class="form-row">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" autocomplete="current-password" required
                           <?= has_error('password') ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>>
                    <?php echo error('password'); ?>
                </div>

                <div class="form-actions">
                    <button class="btn btn--primary" type="submit">Sign in</button>
                    <?php if (route_exists('auth.password.request')): ?>
                        <a class="text-small" href="<?= e(route('auth.password.request')) ?>">Forgot your password?</a>
                    <?php endif; ?>
                </div>

                <?php if (config('app.env') === 'local'): ?>
                    <p class="hint mt-5">
                        Local demo accounts (password <code>FamilyRoots!Demo2026</code>):
                        <code>founder@bh-kiru.example</code>, <code>moderator@bh-kiru.example</code>,
                        <code>rina.aman@example.test</code>, <code>platform.admin@family-roots.local</code>.
                    </p>
                <?php endif; ?>
            </form>
        </div>
    </div>
</section>
