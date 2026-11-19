<?php
/**
 * Notification centre.
 *
 * @var string|null $title
 * @var array       $notifications
 * @var int         $unread
 * @var bool        $unreadOnly
 */
?>
<section class="section section--tight">
    <div class="wrap">
        <p class="eyebrow">Stay informed</p>
        <h1>Notifications</h1>
        <p class="text-muted prose">
            Every decision that affects you - an application received, approved, declined, or a request for more
            information - appears here and, when mail delivery is configured, is also sent to your email address.
        </p>

        <div class="form-actions">
            <a class="btn btn--outline btn--small<?= $unreadOnly ? '' : ' is-active' ?>" href="<?= e(url('/notifications')) ?>">All</a>
            <a class="btn btn--outline btn--small<?= $unreadOnly ? ' is-active' : '' ?>" href="<?= e(query_url('/notifications', ['filter' => 'unread'])) ?>">Unread (<?= e($unread) ?>)</a>
            <form method="post" action="<?= e(url('/notifications/read-all')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button class="btn btn--ghost btn--small" type="submit">Mark all as read</button>
            </form>
        </div>

        <?php if (empty($notifications)): ?>
            <div class="card mt-5">
                <div class="card__body">
                    <p class="text-muted"><?php echo $unreadOnly ? 'No unread notifications.' : 'Nothing here yet.'; ?></p>
                </div>
            </div>
        <?php else: ?>
            <ul class="list-plain notification-list mt-5">
                <?php foreach ($notifications as $notification): ?>
                    <li class="notification-list__item<?= empty($notification['read_at']) ? ' is-unread' : '' ?>">
                        <div>
                            <?php if (!empty($notification['link_path'])): ?>
                                <a class="notification-list__title" href="<?= e(url($notification['link_path'])) ?>"><?= e($notification['title']) ?></a>
                            <?php else: ?>
                                <span class="notification-list__title"><?= e($notification['title']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($notification['body'])): ?>
                                <p class="text-small text-muted"><?= e($notification['body']) ?></p>
                            <?php endif; ?>
                            <p class="text-small text-muted"><?= e(status_label($notification['type'])) ?> · <?= e(format_datetime($notification['created_at'])) ?></p>
                        </div>
                        <div class="notification-list__actions">
                            <?php if (empty($notification['read_at'])): ?>
                                <form method="post" action="<?= e(url('/notifications/' . (int) $notification['id'] . '/read')) ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn--outline btn--small" type="submit">Mark read</button>
                                </form>
                            <?php else: ?>
                                <span class="text-small text-muted">Read <?= e(time_ago($notification['read_at'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
