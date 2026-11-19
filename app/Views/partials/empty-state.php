<?php
/**
 * @var string $heading
 * @var string $message
 * @var string|null $action
 */
?>
<div class="empty-state">
    <h3><?= e($heading ?? 'Nothing here yet') ?></h3>
    <p><?= e($message ?? '') ?></p>
    <?php if (!empty($action)): ?>
        <?= $action ?>
    <?php endif; ?>
</div>
