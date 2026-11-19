<?php
/**
 * Generic error page. Internal details are only printed when APP_DEBUG=true.
 *
 * @var int    $status
 * @var string $message
 * @var string|null $detail
 */
?>
<section class="section">
    <div class="wrap">
        <div class="card card--centered">
            <p class="eyebrow">Error <?= e($status) ?></p>
            <h1><?= e($message) ?></h1>
            <?php if (!empty($detail)): ?>
                <pre class="code-block"><?= e($detail) ?></pre>
            <?php endif; ?>
            <p class="text-muted">If this keeps happening, please report it to the platform administrators and mention error code <?= e($status) ?>.</p>
            <div class="form-actions">
                <a class="btn btn--primary" href="<?= e(url('/')) ?>">Back to the home page</a>
                <?php if (route_exists('families.index')): ?>
                    <a class="btn btn--outline" href="<?= e(route('families.index')) ?>">Browse families</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
