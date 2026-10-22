<?php
$customerId = (int) current_account()["id"];
$activeStatement = db()->prepare(
    "SELECT o.*, d.preferred_delivery_date, d.delivery_address, d.recipient_name, d.delivery_status
     FROM orders o
     LEFT JOIN deliveries d ON d.order_id = o.order_id
     WHERE o.customer_id = ?
       AND o.order_status NOT IN ('Completed', 'Cancelled')
     ORDER BY o.order_date DESC
     LIMIT 1",
);
$activeStatement->execute([$customerId]);
$activeOrder = $activeStatement->fetch() ?: null;

$recentStatement = db()->prepare(
    "SELECT *
     FROM orders
     WHERE customer_id = ?
     ORDER BY order_date DESC
     LIMIT 5",
);
$recentStatement->execute([$customerId]);
$recentOrders = $recentStatement->fetchAll();

$notificationStatement = db()->prepare(
    "SELECT *
     FROM notifications
     WHERE customer_id = ?
     ORDER BY is_read ASC, created_at DESC
     LIMIT 5",
);
$notificationStatement->execute([$customerId]);
$notifications = $notificationStatement->fetchAll();
$hasUnread = (bool) array_filter($notifications, static fn ($notice) => !(bool) $notice["is_read"]);
?>
<section class="customer-welcome d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <p class="eyebrow mb-1">Customer home</p>
        <h1 class="section-title mb-1">Hello, <?= e(current_account()["name"]) ?></h1>
        <p class="text-secondary mb-0">Order water, follow your active order, and review recent purchases.</p>
    </div>
    <div class="welcome-actions d-flex gap-2">
        <a class="btn btn-primary" href="<?= e(url("customer/index.php?page=products")) ?>"><i class="bi bi-droplet" aria-hidden="true"></i>Order water</a>
        <a class="btn btn-light" href="<?= e(url("customer/index.php?page=orders")) ?>">My orders</a>
    </div>
</section>

<?php if ($activeOrder): ?>
    <?php
    $activeSteps = $activeOrder["order_type"] === "Delivery"
        ? ["Pending", "Confirmed", "Preparing", "Out for Delivery", "Delivered"]
        : ["Pending", "Confirmed", "Preparing", "Ready for Pickup", "Completed"];
    $activeIndex = array_search($activeOrder["order_status"], $activeSteps, true);
    ?>
    <section class="panel-card active-order-card mb-4" aria-labelledby="active-order-title">
        <div class="page-header mb-3">
            <div>
                <p class="eyebrow mb-1">Active order</p>
                <h2 class="h4 mb-1" id="active-order-title"><?= e(order_number((int) $activeOrder["order_id"])) ?></h2>
                <p class="small text-secondary mb-0">Placed <?= e(date("F d, Y g:i A", strtotime($activeOrder["order_date"]))) ?></p>
            </div>
            <div class="page-header__actions">
                <span class="badge <?= e(badge_class($activeOrder["order_status"])) ?>"><?= e($activeOrder["order_status"]) ?></span>
                <a class="btn btn-sm btn-light" href="<?= e(url("customer/index.php?page=order&id=" . $activeOrder["order_id"])) ?>">View details</a>
            </div>
        </div>
        <div class="timeline mb-3">
            <?php foreach ($activeSteps as $index => $step): ?>
                <div class="timeline-step <?= $activeIndex !== false && $index <= $activeIndex ? "done" : "" ?>"><?= e($step) ?></div>
            <?php endforeach; ?>
        </div>
        <div class="row g-3">
            <div class="col-sm-4"><div class="small text-secondary">Receiving method</div><strong><?= e($activeOrder["order_type"]) ?></strong></div>
            <div class="col-sm-4"><div class="small text-secondary">Payment</div><span class="badge <?= e(badge_class($activeOrder["payment_status"])) ?>"><?= e($activeOrder["payment_status"]) ?></span></div>
            <div class="col-sm-4"><div class="small text-secondary"><?= $activeOrder["order_type"] === "Delivery" ? "Preferred delivery" : "Next step" ?></div><strong><?= $activeOrder["order_type"] === "Delivery" ? e($activeOrder["preferred_delivery_date"] ? date("M d, Y", strtotime($activeOrder["preferred_delivery_date"])) : "To be scheduled") : "Wait for Ready for Pickup" ?></strong></div>
        </div>
    </section>
<?php else: ?>
    <section class="panel-card empty-state mb-4">
        <i class="bi bi-droplet d-block fs-2 mb-2" aria-hidden="true"></i>
        <h2 class="h5">No active order right now</h2>
        <p>Choose from the available AquaPure products when you are ready for your next refill.</p>
        <a class="btn btn-primary" href="<?= e(url("customer/index.php?page=products")) ?>">Browse products</a>
    </section>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <section class="panel-card h-100">
            <div class="section-header"><div><h2 class="h5 mb-1">Recent orders</h2><p class="small text-secondary mb-0">Your latest AquaPure purchases.</p></div><a class="btn btn-sm btn-light" href="<?= e(url("customer/index.php?page=orders")) ?>">View all</a></div>
            <div class="table-responsive mobile-table-cards" role="region" tabindex="0" aria-label="Dashboard table">
                <table data-layout="cards" role="table" class="table align-middle"><caption class="visually-hidden">Recent orders</caption>
                    <thead><tr><th scope="col">Order</th><th scope="col" class="text-end">Total</th><th scope="col">Payment</th><th scope="col">Status</th><th scope="col" class="text-end">Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td class="table-primary-cell" data-column-label="Order"><div class="table-cell-value"><strong><?= e(order_number((int) $order["order_id"])) ?></strong><div class="small text-secondary"><?= e(date("M d, Y", strtotime($order["order_date"]))) ?> · <?= e($order["order_type"]) ?></div></div></td>
                            <td class="text-end money" data-column-label="Total"><div class="table-cell-value"><?= money($order["total_amount"]) ?></div></td>
                            <td data-column-label="Payment"><div class="table-cell-value"><span class="badge <?= e(badge_class($order["payment_status"])) ?>"><?= e($order["payment_status"]) ?></span></div></td>
                            <td data-column-label="Status"><div class="table-cell-value"><span class="badge <?= e(badge_class($order["order_status"])) ?>"><?= e($order["order_status"]) ?></span></div></td>
                            <td class="text-end" data-column-label="Action"><div class="table-cell-value"><a href="<?= e(url("customer/index.php?page=order&id=" . $order["order_id"])) ?>" class="btn btn-sm btn-light">View</a></div></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentOrders): ?><tr class="table-empty-row"><td colspan="5" class="empty-state">No orders yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <div class="col-lg-4">
        <section class="panel-card h-100">
            <div class="section-header">
                <div><h2 class="h5 mb-1">Notifications</h2><p class="small text-secondary mb-0"><?= $hasUnread ? "You have unread updates." : "You're all caught up." ?></p></div>
                <?php if ($hasUnread): ?>
                    <form method="post" class="m-0"><?= csrf_field() ?><input type="hidden" name="action" value="mark_notifications"><button class="btn btn-sm btn-light">Mark read</button></form>
                <?php endif; ?>
            </div>
            <div class="summary-list">
                <?php foreach ($notifications as $notice): ?>
                    <article class="notification-item <?= !$notice["is_read"] ? "unread" : "" ?>">
                        <div class="fw-semibold"><?= e($notice["title"]) ?><?php if (!$notice["is_read"]): ?><span class="visually-hidden">, unread</span><?php endif; ?></div>
                        <div class="small text-secondary"><?= e($notice["message"]) ?></div>
                        <time class="small text-secondary" datetime="<?= e(date(DATE_ATOM, strtotime($notice["created_at"]))) ?>"><?= e(date("M d, g:i A", strtotime($notice["created_at"]))) ?></time>
                    </article>
                <?php endforeach; ?>
                <?php if (!$notifications): ?><div class="empty-state py-4"><i class="bi bi-bell d-block fs-3 mb-2" aria-hidden="true"></i>No notifications yet.</div><?php endif; ?>
            </div>
        </section>
    </div>
</div>
