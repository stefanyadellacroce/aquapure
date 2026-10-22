<?php
$notifications = db()->prepare(
    "SELECT n.*, o.order_id
     FROM notifications n
     LEFT JOIN orders o ON o.order_id = n.order_id
     WHERE n.user_id = ? OR (n.user_id IS NULL AND n.customer_id IS NULL)
     ORDER BY n.is_read ASC, n.created_at DESC
     LIMIT 100",
);
$notifications->execute([(int) current_account()["id"]]);
$notifications = $notifications->fetchAll();
$unreadNotifications = array_filter($notifications, static fn ($notice) => !(bool) $notice["is_read"]);
?>
<header class="page-header">
    <div>
        <p class="eyebrow mb-1">Operations alerts</p>
        <h1 class="section-title mb-1">Notifications</h1>
        <p class="text-secondary mb-0">New orders, delivery updates, and inventory notices.</p>
    </div>
    <?php if ($unreadNotifications): ?>
        <div class="page-header__actions">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_system_notifications">
                <button class="btn btn-light" type="submit"><i class="bi bi-check2-all" aria-hidden="true"></i>Mark all read</button>
            </form>
        </div>
    <?php endif; ?>
</header>

<section class="panel-card">
    <div class="section-header"><div><h2 class="h5 mb-1">Notification feed</h2><p class="small text-secondary mb-0">Showing up to 100 notifications; <?= number_format(count($unreadNotifications)) ?> unread in this list</p></div></div>
    <?php foreach ($notifications as $notice): ?>
        <article class="notification-item <?= !$notice["is_read"] ? "unread" : "" ?>">
            <div class="d-flex align-items-start gap-3">
                <div class="icon-box flex-shrink-0"><i class="bi <?= !$notice["is_read"] ? "bi-bell-fill" : "bi-bell" ?>" aria-hidden="true"></i></div>
                <div class="min-w-0">
                    <div class="fw-semibold"><?= e($notice["title"]) ?><?php if (!$notice["is_read"]): ?><span class="visually-hidden">, unread</span><?php endif; ?></div>
                    <p class="text-secondary mb-1"><?= e($notice["message"]) ?></p>
                    <div class="small text-secondary">
                        <time datetime="<?= e(date(DATE_ATOM, strtotime($notice["created_at"]))) ?>"><?= e(date("M d, Y g:i A", strtotime($notice["created_at"]))) ?></time>
                        <?php if ($notice["order_id"]): ?> · <a href="<?= e(url("portal/index.php?page=order&id=" . $notice["order_id"])) ?>">Open order</a><?php endif; ?>
                    </div>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$notifications): ?>
        <div class="empty-state"><i class="bi bi-bell d-block fs-3 mb-2" aria-hidden="true"></i><strong>No notifications.</strong><div class="small mt-1">Operational alerts will appear here.</div></div>
    <?php endif; ?>
</section>
