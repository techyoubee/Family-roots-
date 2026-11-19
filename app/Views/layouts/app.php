<?php
/**
 * Main layout.
 *
 * @var string      $title
 * @var string      $content  rendered page markup
 * @var array|null  $currentUser
 * @var int         $unreadNotifications
 * @var string|null $flashSuccess
 * @var string|null $flashError
 * @var string|null $flashInfo
 */
$pageTitle = isset($title) ? $title : config('app.name', 'Family Roots');
$user = isset($currentUser) ? $currentUser : (function_exists('auth') ? auth() : null);
$unread = isset($unreadNotifications) ? (int) $unreadNotifications : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="Family Roots helps families document their ancestry, run five-generation family trees and verify relatives through documented relationships.">
    <link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
<?php if (!empty($styles)): foreach ($styles as $style): ?>
    <link rel="stylesheet" href="<?= e(asset($style)) ?>">
<?php endforeach; endif; ?>
    <meta name="theme-color" content="#0b1f38">
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>

<header class="site-header">
    <div class="wrap">
        <div class="site-header__bar">
            <a class="brand" href="<?= e(url('/')) ?>">
                <span class="brand__mark" aria-hidden="true">FR</span>
                <span class="brand__text">Family Roots<small>Ancestry &amp; verification</small></span>
            </a>
            <button class="nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="site-nav">
                <span></span><span></span><span></span>
                <span class="visually-hidden">Toggle navigation</span>
            </button>
        </div>
        <nav class="site-nav" id="site-nav" aria-label="Main navigation">
            <ul>
                <li><a href="<?= e(url('/')) ?>" class="<?= e(is_active_route('/')) ?>">Home</a></li>
                <?php if (route_exists('families.index')): ?>
                    <li><a href="<?= e(route('families.index')) ?>" class="<?= e(is_active_route('/families')) ?>">Family directory</a></li>
                <?php endif; ?>
                <?php if (route_exists('about')): ?>
                    <li><a href="<?= e(route('about')) ?>" class="<?= e(is_active_route('/about')) ?>">About</a></li>
                <?php endif; ?>
                <?php if (route_exists('privacy')): ?>
                    <li><a href="<?= e(route('privacy')) ?>" class="<?= e(is_active_route('/privacy')) ?>">Privacy</a></li>
                <?php endif; ?>

                <?php if ($user): ?>
                    <?php if (route_exists('dashboard')): ?>
                        <li><a href="<?= e(route('dashboard')) ?>" class="<?= e(is_active_route('/dashboard')) ?>">Dashboard</a></li>
                    <?php endif; ?>
                    <?php if (route_exists('journey.intro')): ?>
                        <li><a href="<?= e(route('journey.intro')) ?>" class="<?= e(is_active_route('/journey')) ?>">Journey</a></li>
                    <?php endif; ?>
                    <?php if (!empty($user['is_platform_admin']) && route_exists('admin.index')): ?>
                        <li><a href="<?= e(route('admin.index')) ?>" class="<?= e(is_active_route('/admin')) ?>">Platform</a></li>
                    <?php endif; ?>
                    <?php if (route_exists('notifications.index')): ?>
                        <li>
                            <a href="<?= e(route('notifications.index')) ?>">
                                Notifications<?php if ($unread > 0): ?><span class="nav-badge" aria-label="<?= e($unread) ?> unread"><?= e($unread) ?></span><?php endif; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if (route_exists('settings.index')): ?>
                        <li><a href="<?= e(route('settings.index')) ?>">Account</a></li>
                    <?php endif; ?>
                    <li>
                        <form method="post" action="<?= e(url('/logout')) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn--ghost-light btn--small">Sign out</button>
                        </form>
                    </li>
                <?php else: ?>
                    <?php if (route_exists('auth.login')): ?>
                        <li><a href="<?= e(route('auth.login')) ?>">Sign in</a></li>
                    <?php endif; ?>
                    <?php if (route_exists('auth.register')): ?>
                        <li><a class="nav-cta" href="<?= e(route('auth.register')) ?>">Create account</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<?php if (!empty($flashSuccess) || !empty($flashError) || !empty($flashInfo)): ?>
    <div class="wrap flash-region">
        <?php if (!empty($flashSuccess)): ?>
            <div class="alert alert--success" role="status" data-autoclose>
                <div><?= e($flashSuccess) ?></div>
                <button type="button" class="alert__close" aria-label="Dismiss message">&times;</button>
            </div>
        <?php endif; ?>
        <?php if (!empty($flashError)): ?>
            <div class="alert alert--error" role="alert">
                <div><?= e($flashError) ?></div>
                <button type="button" class="alert__close" aria-label="Dismiss message">&times;</button>
            </div>
        <?php endif; ?>
        <?php if (!empty($flashInfo)): ?>
            <div class="alert alert--info" role="status">
                <div><?= e($flashInfo) ?></div>
                <button type="button" class="alert__close" aria-label="Dismiss message">&times;</button>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<main id="main">
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="wrap">
        <div class="site-footer__grid">
            <div>
                <h3>Family Roots</h3>
                <p>Document your family, trace five generations of ancestry and connect with relatives through relationships that can be checked and reviewed - never through a surname alone.</p>
            </div>
            <div>
                <h3>Platform</h3>
                <ul class="list-plain">
                    <li><a href="<?= e(url('/')) ?>">Home</a></li>
                    <?php if (route_exists('families.index')): ?><li><a href="<?= e(route('families.index')) ?>">Family directory</a></li><?php endif; ?>
                    <?php if (route_exists('about')): ?><li><a href="<?= e(route('about')) ?>">About</a></li><?php endif; ?>
                    <?php if (route_exists('family.register')): ?><li><a href="<?= e(route('family.register')) ?>">Register a family</a></li><?php endif; ?>
                </ul>
            </div>
            <div>
                <h3>Trust &amp; safety</h3>
                <ul class="list-plain">
                    <?php if (route_exists('privacy')): ?><li><a href="<?= e(route('privacy')) ?>">Privacy policy</a></li><?php endif; ?>
                    <?php if (route_exists('about')): ?><li><a href="<?= e(route('about')) ?>#matching">How matching works</a></li><?php endif; ?>
                    <?php if (route_exists('settings.index')): ?><li><a href="<?= e(route('settings.index')) ?>">Your data &amp; settings</a></li><?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="site-footer__bottom">
            <span>&copy; <?= e(date('Y')) ?> Family Roots. Demo data shown in this application is fictional.</span>
            <span>Matching scores are workflow indicators, not proof of biological ancestry.</span>
        </div>
    </div>
</footer>

<script src="<?= e(asset('/assets/js/app.js')) ?>" defer></script>
<?php if (!empty($scripts)): foreach ($scripts as $script): ?>
    <script src="<?= e(asset($script)) ?>" defer></script>
<?php endforeach; endif; ?>
</body>
</html>
