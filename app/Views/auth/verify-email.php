<?php
/**
 * Confirm your email address (landing page after registering, or when a link
 * expired and a new one is needed).
 *
 * @var string|null $title
 * @var array       $user
 * @var bool        $verified
 * @var bool        $mailLogged
 * @var string|null $localLink
 */
?>
<section class="section">
    <div class="wrap">
        <div class="card auth-card">
            <?php if ($verified): ?>
                <p class="eyebrow">All set</p>
                <h1>Your email address is confirmed</h1>
                <p class="text-muted prose">
                    Thank you - <?= e($user['email']) ?> has been verified. You can now register a family,
                    declare ancestry and apply to join families you already belong to.
                </p>
                <div class="form-actions">
                    <?php if (route_exists('dashboard')): ?>
                        <a class="btn btn--primary" href="<?= e(route('dashboard')) ?>">Go to your dashboard</a>
                    <?php endif; ?>
                    <?php if (route_exists('family.register')): ?>
                        <a class="btn btn--outline" href="<?= e(route('family.register')) ?>">Register a family</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p class="eyebrow">One step left</p>
                <h1>Confirm your email address</h1>

                <?php if (!empty($localLink)): ?>
                    <div class="alert alert--success" role="status">
                        <div>
                            <strong>Open this link to confirm the address.</strong>
                            <div class="link-box">
                                <a href="<?= e($localLink) ?>"><?= e($localLink) ?></a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="alert alert--info" role="status">
                    <div>
                        <?php if (!empty($mailLogged)): ?>
                            This server is not configured to deliver email, so confirmation links are written to
                            <code>storage/logs/mail-<?= e(date('Y-m-d')) ?>.log</code> instead.
                            Use the button below to generate a fresh link, then open it.
                        <?php else: ?>
                            We sent a confirmation link to <strong><?= e($user['email']) ?></strong>.
                            Open it to activate your account. The link is valid for a limited time and can be
                            requested again below.
                        <?php endif; ?>
                    </div>
                </div>
                <p class="text-muted text-small">
                    Nothing arrived? Check your spam folder, or make sure the address above is correct - you can
                    change it in account settings once you are signed in.
                </p>

                <form method="post" action="<?= e(url('/verify-email/resend')) ?>" class="mt-5">
                    <?= csrf_field() ?>
                    <div class="form-actions">
                        <button class="btn btn--outline" type="submit">
                            <?= !empty($mailLogged) ? 'Generate a new confirmation link' : 'Resend the confirmation email' ?>
                        </button>
                        <?php if (route_exists('settings.index')): ?>
                            <a class="text-small" href="<?= e(route('settings.index')) ?>">Change my email address</a>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
