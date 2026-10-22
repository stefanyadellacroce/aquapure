<?php
$metrics = db()
    ->query(
        "SELECT
        (SELECT COUNT(*) FROM customers WHERE account_status = 'Active') AS total_customers,
        (SELECT COUNT(*) FROM users WHERE role = 'Staff' AND account_status = 'Active') AS total_staff,
        (SELECT COUNT(*) FROM orders WHERE DATE(order_date) = CURDATE()) AS today_orders,
        (SELECT COUNT(*) FROM orders WHERE order_status = 'Pending') AS pending_orders,
        (SELECT COUNT(*) FROM orders WHERE order_status = 'Preparing') AS preparing_orders,
        (SELECT COUNT(*) FROM orders WHERE order_status = 'Ready for Pickup') AS ready_pickup_orders,
        (SELECT COUNT(*) FROM orders WHERE order_status = 'Completed') AS completed_orders,
        (SELECT COALESCE(SUM(amount_paid - change_amount), 0) FROM payments WHERE payment_status = 'Paid' AND DATE(payment_date) = CURDATE()) AS today_sales,
        (SELECT COALESCE(SUM(amount_paid - change_amount), 0) FROM payments WHERE payment_status = 'Paid' AND YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE())) AS monthly_sales,
        (SELECT COUNT(*) FROM deliveries WHERE delivery_status NOT IN ('Delivered', 'Cancelled')) AS pending_deliveries,
        (SELECT COUNT(*) FROM inventory WHERE quantity <= reorder_level) AS low_stock",
    )
    ->fetch();

$actionOrders = db()
    ->query(
        "SELECT o.*, c.full_name
         FROM orders o
         JOIN customers c ON c.customer_id = o.customer_id
         WHERE o.order_status NOT IN ('Completed', 'Cancelled')
         ORDER BY FIELD(o.order_status, 'Pending', 'Confirmed', 'Preparing', 'Ready for Pickup', 'Out for Delivery', 'Delivered'), o.order_date
         LIMIT 8",
    )
    ->fetchAll();
$recent = db()
    ->query(
        "SELECT o.*, c.full_name
         FROM orders o
         JOIN customers c ON c.customer_id = o.customer_id
         ORDER BY o.order_date DESC
         LIMIT 6",
    )
    ->fetchAll();
$lowStock = db()
    ->query(
        "SELECT inventory_id, item_name, category, quantity, unit, reorder_level,
                CASE WHEN quantity = 0 THEN 'Out of Stock' ELSE 'Low Stock' END AS stock_status
         FROM inventory
         WHERE quantity <= reorder_level
         ORDER BY quantity = 0 DESC, quantity, item_name
         LIMIT 5",
    )
    ->fetchAll();
$todayDeliveries = db()
    ->query(
        "SELECT d.delivery_id, d.delivery_status, d.preferred_delivery_date, o.order_id, c.full_name
         FROM deliveries d
         JOIN orders o ON o.order_id = d.order_id
         JOIN customers c ON c.customer_id = o.customer_id
         WHERE d.preferred_delivery_date <= CURDATE()
           AND d.delivery_status NOT IN ('Delivered', 'Cancelled')
         ORDER BY d.preferred_delivery_date, d.delivery_id
         LIMIT 5",
    )
    ->fetchAll();
$activity = [];
if (is_admin()) {
    $activity = db()
        ->query(
            "SELECT a.*, u.full_name
             FROM activity_logs a
             LEFT JOIN users u ON u.user_id = a.user_id
             ORDER BY a.created_at DESC
             LIMIT 6",
        )
        ->fetchAll();
}

$primaryCards = [
    ["Today's sales", money($metrics["today_sales"]), "bi-cash-stack", "Paid payments recorded today", ""],
    ["Today's orders", $metrics["today_orders"], "bi-receipt", "Orders placed today", ""],
    ["Pending orders", $metrics["pending_orders"], "bi-hourglass-split", "Waiting for confirmation", (int) $metrics["pending_orders"] > 0 ? "metric-card--attention" : ""],
    ["Low-stock items", $metrics["low_stock"], "bi-exclamation-triangle", "At or below reorder level", (int) $metrics["low_stock"] > 0 ? "metric-card--attention" : ""],
];
?>
<header class="page-header">
    <div>
        <p class="eyebrow mb-1"><?= e(current_account()["role"]) ?> dashboard</p>
        <h1 class="section-title mb-1">Good day, <?= e(current_account()["name"]) ?></h1>
        <p class="text-secondary mb-0">Orders, deliveries, payments, and stock that need attention now.</p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn-primary" href="<?= e(url("portal/index.php?page=order-create")) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i>Create walk-in order</a>
    </div>
</header>

<div class="row g-3 mb-4">
    <?php foreach ($primaryCards as [$label, $value, $icon, $context, $className]): ?>
        <div class="col-6 col-xl-3">
            <article class="metric-card <?= e($className) ?>">
                <div class="d-flex justify-content-between gap-2">
                    <div class="metric-label"><?= e($label) ?></div>
                    <i class="bi <?= e($icon) ?>" aria-hidden="true"></i>
                </div>
                <div class="metric-value"><?= e((string) $value) ?></div>
                <div class="metric-context"><?= e($context) ?></div>
            </article>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <section class="panel-card h-100">
            <div class="section-header">
                <div>
                    <h2 class="h5 mb-1">Orders requiring action</h2>
                    <p class="small text-secondary mb-0">Open orders are sorted by workflow stage and age.</p>
                </div>
                <a class="btn btn-sm btn-light" href="<?= e(url("portal/index.php?page=orders")) ?>">View order queue</a>
            </div>
            <div class="table-responsive mobile-table-cards" role="region" tabindex="0" aria-label="Dashboard table">
                <table data-layout="cards" role="table" class="table align-middle"><caption class="visually-hidden">Orders requiring action</caption>
                    <thead><tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Type</th><th scope="col" class="text-end">Total</th><th scope="col">Payment</th><th scope="col">Status</th><th scope="col" class="text-end">Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($actionOrders as $order): ?>
                        <tr>
                            <td class="table-primary-cell" data-column-label="Order"><div class="table-cell-value"><strong><?= e(order_number((int) $order["order_id"])) ?></strong><div class="small text-secondary"><?= e(date("M d, g:i A", strtotime($order["order_date"]))) ?></div></div></td>
                            <td data-column-label="Customer"><div class="table-cell-value"><?= e($order["full_name"]) ?></div></td>
                            <td data-column-label="Type"><div class="table-cell-value"><?= e($order["order_type"]) ?></div></td>
                            <td class="text-end money" data-column-label="Total"><div class="table-cell-value"><?= money($order["total_amount"]) ?></div></td>
                            <td data-column-label="Payment"><div class="table-cell-value"><span class="badge <?= e(badge_class($order["payment_status"])) ?>"><?= e($order["payment_status"]) ?></span></div></td>
                            <td data-column-label="Status"><div class="table-cell-value"><span class="badge <?= e(badge_class($order["order_status"])) ?>"><?= e($order["order_status"]) ?></span></div></td>
                            <td class="text-end" data-column-label="Action"><div class="table-cell-value"><a class="btn btn-sm btn-light" href="<?= e(url("portal/index.php?page=order&id=" . $order["order_id"])) ?>">Manage</a></div></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$actionOrders): ?>
                        <tr class="table-empty-row"><td colspan="7" class="empty-state"><i class="bi bi-check2-circle d-block fs-3 mb-2" aria-hidden="true"></i><strong>No open orders need action.</strong><div class="small mt-1">The current order queue is clear.</div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <div class="col-xl-4">
        <section class="panel-card h-100">
            <h2 class="h5 mb-3">Operational overview</h2>
            <div class="quick-stat"><span>Preparing</span><strong><?= number_format((int) $metrics["preparing_orders"]) ?></strong></div>
            <div class="quick-stat"><span>Ready for pickup</span><strong><?= number_format((int) $metrics["ready_pickup_orders"]) ?></strong></div>
            <div class="quick-stat"><span>Active deliveries</span><strong><?= number_format((int) $metrics["pending_deliveries"]) ?></strong></div>
            <div class="quick-stat"><span>Completed orders</span><strong><?= number_format((int) $metrics["completed_orders"]) ?></strong></div>
            <?php if (is_admin()): ?>
                <div class="quick-stat"><span>Active customers</span><strong><?= number_format((int) $metrics["total_customers"]) ?></strong></div>
                <div class="quick-stat"><span>Active staff</span><strong><?= number_format((int) $metrics["total_staff"]) ?></strong></div>
                <div class="quick-stat"><span>Sales this month</span><strong><?= e(money($metrics["monthly_sales"])) ?></strong></div>
            <?php endif; ?>
        </section>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <section class="panel-card h-100">
            <div class="section-header">
                <div><h2 class="h5 mb-1">Inventory attention</h2><p class="small text-secondary mb-0">Critical items first.</p></div>
                <a class="btn btn-sm btn-light" href="<?= e(url("portal/index.php?page=inventory")) ?>">Inventory</a>
            </div>
            <?php foreach ($lowStock as $item): ?>
                <div class="quick-stat">
                    <div><strong class="d-block"><?= e($item["item_name"]) ?></strong><span class="small text-secondary"><?= e($item["category"]) ?> · Reorder at <?= number_format((int) $item["reorder_level"]) ?></span></div>
                    <span class="badge <?= e(badge_class($item["stock_status"])) ?>"><?= number_format((int) $item["quantity"]) ?> <?= e($item["unit"]) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (!$lowStock): ?><div class="empty-state py-4"><i class="bi bi-check2-circle d-block fs-3 mb-2" aria-hidden="true"></i><strong>Stock levels look healthy.</strong></div><?php endif; ?>
        </section>
    </div>
    <div class="col-lg-6">
        <section class="panel-card h-100">
            <div class="section-header">
                <div><h2 class="h5 mb-1">Deliveries due</h2><p class="small text-secondary mb-0">Due today or earlier and still open.</p></div>
                <a class="btn btn-sm btn-light" href="<?= e(url("portal/index.php?page=deliveries")) ?>">Deliveries</a>
            </div>
            <?php foreach ($todayDeliveries as $delivery): ?>
                <div class="quick-stat">
                    <div><a class="fw-semibold" href="<?= e(url("portal/index.php?page=order&id=" . $delivery["order_id"])) ?>"><?= e(order_number((int) $delivery["order_id"])) ?></a><span class="d-block small text-secondary"><?= e($delivery["full_name"]) ?> · <?= e(date("M d, Y", strtotime($delivery["preferred_delivery_date"]))) ?></span></div>
                    <span class="badge <?= e(badge_class($delivery["delivery_status"])) ?>"><?= e($delivery["delivery_status"]) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (!$todayDeliveries): ?><div class="empty-state py-4"><i class="bi bi-truck d-block fs-3 mb-2" aria-hidden="true"></i><strong>No overdue deliveries.</strong></div><?php endif; ?>
        </section>
    </div>
</div>

<div class="row g-3">
    <div class="<?= is_admin() ? "col-xl-8" : "col-12" ?>">
        <section class="panel-card h-100">
            <div class="section-header"><h2 class="h5 mb-0">Recent orders</h2><a class="btn btn-sm btn-light" href="<?= e(url("portal/index.php?page=orders")) ?>">View all</a></div>
            <div class="table-responsive mobile-table-cards" role="region" tabindex="0" aria-label="Dashboard table">
                <table data-layout="cards" role="table" class="table align-middle"><caption class="visually-hidden">Recent orders</caption>
                    <thead><tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col" class="text-end">Total</th><th scope="col">Status</th><th scope="col" class="text-end">Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $order): ?>
                        <tr>
                            <td class="table-primary-cell" data-column-label="Order"><div class="table-cell-value"><strong><?= e(order_number((int) $order["order_id"])) ?></strong><div class="small text-secondary"><?= e(date("M d, Y", strtotime($order["order_date"]))) ?></div></div></td>
                            <td data-column-label="Customer"><div class="table-cell-value"><?= e($order["full_name"]) ?></div></td>
                            <td class="text-end money" data-column-label="Total"><div class="table-cell-value"><?= money($order["total_amount"]) ?></div></td>
                            <td data-column-label="Status"><div class="table-cell-value"><span class="badge <?= e(badge_class($order["order_status"])) ?>"><?= e($order["order_status"]) ?></span></div></td>
                            <td class="text-end" data-column-label="Action"><div class="table-cell-value"><a class="btn btn-sm btn-light" href="<?= e(url("portal/index.php?page=order&id=" . $order["order_id"])) ?>">View</a></div></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?><tr class="table-empty-row"><td colspan="5" class="empty-state">No orders have been recorded yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <?php if (is_admin()): ?>
        <div class="col-xl-4">
            <section class="panel-card h-100">
                <div class="section-header"><h2 class="h5 mb-0">Recent activity</h2><a class="btn btn-sm btn-light" href="<?= e(url("portal/index.php?page=activity-logs")) ?>">View logs</a></div>
                <?php foreach ($activity as $item): ?>
                    <div class="quick-stat align-items-start">
                        <div><strong class="d-block small"><?= e($item["action"]) ?></strong><span class="small text-secondary"><?= e($item["full_name"] ?? "System") ?></span></div>
                        <time class="small text-secondary text-nowrap" datetime="<?= e(date(DATE_ATOM, strtotime($item["created_at"]))) ?>"><?= e(date("M d, g:i A", strtotime($item["created_at"]))) ?></time>
                    </div>
                <?php endforeach; ?>
                <?php if (!$activity): ?><div class="empty-state py-4">No activity has been recorded.</div><?php endif; ?>
            </section>
        </div>
    <?php endif; ?>
</div>
