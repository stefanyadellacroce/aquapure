<?php
$defaultFrom = date("Y-m-01");
$defaultTo = date("Y-m-d");
$parseReportDate = static function (mixed $value, string $fallback): string {
    $value = trim((string) $value);
    $date = DateTimeImmutable::createFromFormat("!Y-m-d", $value);
    $errors = DateTimeImmutable::getLastErrors();

    if (
        !$date ||
        ($errors !== false && ($errors["warning_count"] > 0 || $errors["error_count"] > 0)) ||
        $date->format("Y-m-d") !== $value
    ) {
        return $fallback;
    }

    return $value;
};

$from = $parseReportDate($_GET["from"] ?? $defaultFrom, $defaultFrom);
$to = $parseReportDate($_GET["to"] ?? $defaultTo, $defaultTo);
$rangeNotice = null;
if ($from > $to) {
    [$from, $to] = [$to, $from];
    $rangeNotice = "The report dates were reversed, so they were arranged chronologically.";
}

$endExclusive = (new DateTimeImmutable($to))
    ->modify("+1 day")
    ->format("Y-m-d");
$rangeParams = [$from, $endExclusive];
$adminReport = is_admin();

$orderStatement = db()->prepare(
    "SELECT
        COUNT(*) AS orders_count,
        COALESCE(SUM(CASE WHEN order_status NOT IN ('Completed', 'Delivered', 'Cancelled') THEN 1 ELSE 0 END), 0) AS open_orders,
        COALESCE(SUM(CASE WHEN order_status IN ('Completed', 'Delivered') THEN 1 ELSE 0 END), 0) AS completed_orders,
        COALESCE(SUM(CASE WHEN order_status = 'Cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_orders,
        COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN total_amount ELSE 0 END), 0) AS paid_order_value,
        COALESCE(SUM(CASE WHEN payment_status IN ('Unpaid', 'Partial') THEN 1 ELSE 0 END), 0) AS outstanding_orders,
        COALESCE(AVG(total_amount), 0) AS average_order_value
     FROM orders
     WHERE order_date >= ? AND order_date < ?",
);
$orderStatement->execute($rangeParams);
$orderSummary = $orderStatement->fetch();

$paymentStatement = db()->prepare(
    "SELECT
        COUNT(*) AS payment_records,
        COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN 1 ELSE 0 END), 0) AS paid_payments,
        COALESCE(SUM(CASE WHEN payment_status = 'Partial' THEN 1 ELSE 0 END), 0) AS partial_payments,
        COALESCE(SUM(CASE WHEN payment_status = 'Unpaid' THEN 1 ELSE 0 END), 0) AS unpaid_payments,
        COALESCE(SUM(CASE WHEN payment_status IN ('Paid', 'Partial') THEN GREATEST(amount_paid - change_amount, 0) ELSE 0 END), 0) AS collections
     FROM payments
     WHERE COALESCE(payment_date, created_at) >= ?
       AND COALESCE(payment_date, created_at) < ?",
);
$paymentStatement->execute($rangeParams);
$paymentSummary = $paymentStatement->fetch();

$deliveryStatement = db()->prepare(
    "SELECT
        COUNT(*) AS deliveries_count,
        COALESCE(SUM(CASE WHEN delivery_status = 'Delivered' THEN 1 ELSE 0 END), 0) AS delivered_count,
        COALESCE(SUM(CASE WHEN delivery_status NOT IN ('Delivered', 'Cancelled') THEN 1 ELSE 0 END), 0) AS active_deliveries,
        COALESCE(SUM(CASE WHEN delivery_status = 'Cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_deliveries
     FROM deliveries
     WHERE preferred_delivery_date >= ? AND preferred_delivery_date < ?",
);
$deliveryStatement->execute($rangeParams);
$deliverySummary = $deliveryStatement->fetch();

$inventorySummary = db()
    ->query(
        "SELECT
            COUNT(*) AS inventory_items,
            COALESCE(SUM(CASE WHEN quantity <= reorder_level AND quantity > 0 THEN 1 ELSE 0 END), 0) AS low_stock_items,
            COALESCE(SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END), 0) AS out_of_stock_items,
            COALESCE(SUM(quantity), 0) AS units_on_hand
         FROM inventory",
    )
    ->fetch();

$dailyStatement = db()->prepare(
    "SELECT
        DATE(order_date) AS report_date,
        COUNT(*) AS orders_count,
        COALESCE(SUM(CASE WHEN order_status IN ('Completed', 'Delivered') THEN 1 ELSE 0 END), 0) AS completed_orders,
        COALESCE(SUM(CASE WHEN order_type = 'Delivery' THEN 1 ELSE 0 END), 0) AS delivery_orders,
        COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN total_amount ELSE 0 END), 0) AS paid_order_value
     FROM orders
     WHERE order_date >= ? AND order_date < ?
     GROUP BY DATE(order_date)
     ORDER BY report_date DESC",
);
$dailyStatement->execute($rangeParams);
$dailyRows = $dailyStatement->fetchAll();

$paymentStatusStatement = db()->prepare(
    "SELECT
        payment_status,
        COUNT(*) AS records_count,
        COALESCE(SUM(CASE WHEN payment_status IN ('Paid', 'Partial') THEN GREATEST(amount_paid - change_amount, 0) ELSE 0 END), 0) AS collected_amount
     FROM payments
     WHERE COALESCE(payment_date, created_at) >= ?
       AND COALESCE(payment_date, created_at) < ?
     GROUP BY payment_status
     ORDER BY FIELD(payment_status, 'Paid', 'Partial', 'Unpaid', 'Refunded')",
);
$paymentStatusStatement->execute($rangeParams);
$paymentStatuses = $paymentStatusStatement->fetchAll();

$deliveryStatusStatement = db()->prepare(
    "SELECT delivery_status, COUNT(*) AS deliveries_count
     FROM deliveries
     WHERE preferred_delivery_date >= ? AND preferred_delivery_date < ?
     GROUP BY delivery_status
     ORDER BY FIELD(delivery_status, 'Pending', 'Confirmed', 'Preparing', 'Out for Delivery', 'Delivered', 'Cancelled')",
);
$deliveryStatusStatement->execute($rangeParams);
$deliveryStatuses = $deliveryStatusStatement->fetchAll();

$lowStock = db()
    ->query(
        "SELECT
            inventory_id, item_name, category, quantity, unit, reorder_level,
            CASE
                WHEN quantity = 0 THEN 'Out of Stock'
                WHEN quantity <= reorder_level THEN 'Low Stock'
                ELSE 'In Stock'
            END AS stock_status
         FROM inventory
         WHERE quantity <= reorder_level
         ORDER BY quantity = 0 DESC, quantity ASC, item_name ASC
         LIMIT 8",
    )
    ->fetchAll();

$topProducts = [];
$paymentMethods = [];
$inventoryMovements = [];
$unitsSold = 0;
if ($adminReport) {
    $unitsStatement = db()->prepare(
        "SELECT COALESCE(SUM(oi.quantity), 0) AS units_sold
         FROM order_items oi
         INNER JOIN orders o ON o.order_id = oi.order_id
         WHERE o.order_date >= ? AND o.order_date < ?",
    );
    $unitsStatement->execute($rangeParams);
    $unitsSold = (int) ($unitsStatement->fetch()["units_sold"] ?? 0);

    $topProductsStatement = db()->prepare(
        "SELECT
            p.product_name,
            p.category,
            COALESCE(SUM(oi.quantity), 0) AS units_sold,
            COALESCE(SUM(oi.subtotal), 0) AS sales_value
         FROM order_items oi
         INNER JOIN orders o ON o.order_id = oi.order_id
         INNER JOIN products p ON p.product_id = oi.product_id
         WHERE o.order_date >= ? AND o.order_date < ?
         GROUP BY p.product_id, p.product_name, p.category
         ORDER BY units_sold DESC, sales_value DESC, p.product_name ASC
         LIMIT 6",
    );
    $topProductsStatement->execute($rangeParams);
    $topProducts = $topProductsStatement->fetchAll();

    $paymentMethodsStatement = db()->prepare(
        "SELECT
            payment_method,
            COUNT(*) AS records_count,
            COALESCE(SUM(CASE WHEN payment_status IN ('Paid', 'Partial') THEN GREATEST(amount_paid - change_amount, 0) ELSE 0 END), 0) AS collected_amount
         FROM payments
         WHERE COALESCE(payment_date, created_at) >= ?
           AND COALESCE(payment_date, created_at) < ?
         GROUP BY payment_method
         ORDER BY collected_amount DESC, payment_method ASC",
    );
    $paymentMethodsStatement->execute($rangeParams);
    $paymentMethods = $paymentMethodsStatement->fetchAll();

    $inventoryMovementsStatement = db()->prepare(
        "SELECT
            i.item_name,
            COUNT(*) AS transaction_count,
            COALESCE(SUM(CASE WHEN t.transaction_type = 'Stock In' THEN t.quantity ELSE 0 END), 0) AS stock_in,
            COALESCE(SUM(CASE WHEN t.transaction_type = 'Stock Out' THEN t.quantity ELSE 0 END), 0) AS stock_out,
            COALESCE(SUM(CASE WHEN t.transaction_type = 'Adjustment' THEN t.quantity ELSE 0 END), 0) AS adjustments
         FROM inventory_transactions t
         INNER JOIN inventory i ON i.inventory_id = t.inventory_id
         WHERE t.transaction_date >= ? AND t.transaction_date < ?
         GROUP BY i.inventory_id, i.item_name
         ORDER BY transaction_count DESC, i.item_name ASC
         LIMIT 8",
    );
    $inventoryMovementsStatement->execute($rangeParams);
    $inventoryMovements = $inventoryMovementsStatement->fetchAll();
}

$reportCards = [
    ["Orders received", $orderSummary["orders_count"], "bi-receipt"],
    ["Open orders", $orderSummary["open_orders"], "bi-hourglass-split"],
    ["Paid sales value", money($orderSummary["paid_order_value"]), "bi-graph-up-arrow"],
    ["Collected payments", money($paymentSummary["collections"]), "bi-cash-coin"],
];
?>
<header class="page-header">
    <div>
        <p class="eyebrow mb-1"><?= e($adminReport ? "Administrative analytics" : "Staff operational reporting") ?></p>
        <h1 class="section-title mb-1">Reports</h1>
        <p class="text-secondary mb-0"><?= e($adminReport ? "Sales, payment, delivery, and inventory performance for management." : "A concise view of the work, payments, deliveries, and stock that need attention.") ?></p>
    </div>
    <div class="page-header__actions"><button class="btn btn-outline-primary no-print" type="button" data-print-page><i class="bi bi-printer" aria-hidden="true"></i>Print report</button></div>
</header>

<div class="print-only mb-3">
    <h1>AquaPure <?= e($adminReport ? "Management" : "Operations") ?> Report</h1>
    <p><?= e(date("F d, Y", strtotime($from))) ?> to <?= e(date("F d, Y", strtotime($to))) ?></p>
</div>

<section class="filter-bar mb-3 no-print">
    <form method="get" class="row g-2 align-items-end">
        <input type="hidden" name="page" value="reports">
        <div class="col-md-4"><label class="form-label" for="reportFrom">From <span class="text-danger" aria-hidden="true">*</span></label><input id="reportFrom" type="date" class="form-control" name="from" value="<?= e($from) ?>" required></div>
        <div class="col-md-4"><label class="form-label" for="reportTo">To <span class="text-danger" aria-hidden="true">*</span></label><input id="reportTo" type="date" class="form-control" name="to" value="<?= e($to) ?>" required></div>
        <div class="col-md-4"><button class="btn btn-primary w-100">Generate report</button></div>
    </form>
</section>

<?php if ($rangeNotice): ?>
    <div class="alert alert-info no-print" role="status"><?= e($rangeNotice) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php foreach ($reportCards as [$label, $value, $icon]): ?>
        <div class="col-6 col-xl-3"><article class="metric-card"><div class="d-flex justify-content-between gap-2"><div class="metric-label"><?= e($label) ?></div><i aria-hidden="true" class="bi <?= e($icon) ?>"></i></div><div class="metric-value"><?= e((string) $value) ?></div></article></div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <section class="panel-card h-100">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 mb-0">Daily sales and order activity</h2><p class="small text-secondary mb-0">Paid sales value is grouped by the date each order was placed.</p></div><a class="btn btn-sm btn-light no-print" href="<?= e(url("portal/index.php?page=orders")) ?>">Orders</a></div>
            <div class="table-responsive" role="region" tabindex="0" aria-label="Reports table"><table data-layout="scroll" role="table" class="table align-middle mb-0"><caption class="visually-hidden">Daily sales and order activity</caption><thead><tr><th scope="col">Date</th><th scope="col">Orders</th><th scope="col">Completed</th><th scope="col">Delivery</th><th scope="col" class="text-end">Paid sales value</th></tr></thead><tbody>
                <?php foreach ($dailyRows as $row): ?>
                    <tr><td data-column-label="Date"><div class="table-cell-value"><?= e(date("M d, Y", strtotime($row["report_date"]))) ?></div></td><td data-column-label="Orders"><div class="table-cell-value"><?= number_format((int) $row["orders_count"]) ?></div></td><td data-column-label="Completed"><div class="table-cell-value"><?= number_format((int) $row["completed_orders"]) ?></div></td><td data-column-label="Delivery"><div class="table-cell-value"><?= number_format((int) $row["delivery_orders"]) ?></div></td><td class="text-end" data-column-label="Paid sales value"><div class="table-cell-value"><?= e(money($row["paid_order_value"])) ?></div></td></tr>
                <?php endforeach; ?>
                <?php if (!$dailyRows): ?><tr class="table-empty-row"><td colspan="5" class="empty-state">No orders were recorded in this period.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>
    </div>
    <div class="col-xl-4">
        <section class="panel-card h-100">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><h2 class="h5 mb-0">Delivery summary</h2><a class="btn btn-sm btn-light no-print" href="<?= e(url("portal/index.php?page=deliveries")) ?>">Deliveries</a></div>
            <div class="row g-2 mb-3"><div class="col-6"><div class="small text-secondary">Delivered</div><strong><?= number_format((int) $deliverySummary["delivered_count"]) ?></strong></div><div class="col-6"><div class="small text-secondary">In progress</div><strong><?= number_format((int) $deliverySummary["active_deliveries"]) ?></strong></div></div>
            <?php foreach ($deliveryStatuses as $status): ?>
                <div class="d-flex justify-content-between align-items-center border-bottom py-2"><span><?= e($status["delivery_status"]) ?></span><span class="badge <?= e(badge_class($status["delivery_status"])) ?>"><?= number_format((int) $status["deliveries_count"]) ?></span></div>
            <?php endforeach; ?>
            <?php if (!$deliveryStatuses): ?><p class="text-secondary mb-0">No deliveries are scheduled in this period.</p><?php endif; ?>
        </section>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-6">
        <section class="panel-card h-100">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 mb-0">Payment summary</h2><p class="small text-secondary mb-0"><?= number_format((int) $paymentSummary["payment_records"]) ?> record<?= (int) $paymentSummary["payment_records"] === 1 ? "" : "s" ?> in the selected period.</p></div><a class="btn btn-sm btn-light no-print" href="<?= e(url("portal/index.php?page=payments")) ?>">Payments</a></div>
            <div class="table-responsive" role="region" tabindex="0" aria-label="Reports table"><table data-layout="scroll" role="table" class="table align-middle mb-0"><caption class="visually-hidden">Payment summary</caption><thead><tr><th scope="col">Status</th><th scope="col">Records</th><th scope="col" class="text-end">Collected</th></tr></thead><tbody>
                <?php foreach ($paymentStatuses as $status): ?>
                    <tr><td data-column-label="Status"><div class="table-cell-value"><span class="badge <?= e(badge_class($status["payment_status"])) ?>"><?= e($status["payment_status"]) ?></span></div></td><td data-column-label="Records"><div class="table-cell-value"><?= number_format((int) $status["records_count"]) ?></div></td><td class="text-end" data-column-label="Collected"><div class="table-cell-value"><?= e(money($status["collected_amount"])) ?></div></td></tr>
                <?php endforeach; ?>
                <?php if (!$paymentStatuses): ?><tr class="table-empty-row"><td colspan="3" class="empty-state">No payment records were updated in this period.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>
    </div>
    <div class="col-xl-6">
        <section class="panel-card h-100">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 mb-0">Inventory attention</h2><p class="small text-secondary mb-0"><?= number_format((int) $inventorySummary["inventory_items"]) ?> items · <?= number_format((int) $inventorySummary["units_on_hand"]) ?> units on hand</p></div><a class="btn btn-sm btn-light no-print" href="<?= e(url("portal/index.php?page=inventory")) ?>">Inventory</a></div>
            <?php foreach ($lowStock as $item): ?>
                <div class="d-flex justify-content-between align-items-center gap-3 border-bottom py-2"><div><strong><?= e($item["item_name"]) ?></strong><div class="small text-secondary"><?= e($item["category"]) ?> · Reorder at <?= number_format((int) $item["reorder_level"]) ?></div></div><span class="badge <?= e(badge_class($item["stock_status"])) ?>"><?= number_format((int) $item["quantity"]) ?> <?= e($item["unit"]) ?></span></div>
            <?php endforeach; ?>
            <?php if (!$lowStock): ?><p class="text-secondary mb-0">All inventory levels are above their reorder points.</p><?php endif; ?>
        </section>
    </div>
</div>

<?php if ($adminReport): ?>
    <div class="row g-3 mt-1">
        <div class="col-xl-5">
            <section class="panel-card h-100">
                <h2 class="h5 mb-3">Top products</h2>
                <div class="table-responsive" role="region" tabindex="0" aria-label="Reports table"><table data-layout="scroll" role="table" class="table align-middle mb-0"><caption class="visually-hidden">Top products</caption><thead><tr><th scope="col">Product</th><th scope="col">Units</th><th scope="col" class="text-end">Sales value</th></tr></thead><tbody>
                    <?php foreach ($topProducts as $product): ?>
                        <tr><td data-column-label="Product"><div class="table-cell-value"><strong><?= e($product["product_name"]) ?></strong><div class="small text-secondary"><?= e($product["category"]) ?></div></div></td><td data-column-label="Units"><div class="table-cell-value"><?= number_format((int) $product["units_sold"]) ?></div></td><td class="text-end" data-column-label="Sales value"><div class="table-cell-value"><?= e(money($product["sales_value"])) ?></div></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$topProducts): ?><tr class="table-empty-row"><td colspan="3" class="empty-state">No product sales were recorded in this period.</td></tr><?php endif; ?>
                </tbody></table></div>
            </section>
        </div>
        <div class="col-xl-3">
            <section class="panel-card h-100">
                <h2 class="h5 mb-3">Payment methods</h2>
                <?php foreach ($paymentMethods as $method): ?>
                    <div class="border-bottom py-2"><div class="d-flex justify-content-between gap-2"><strong><?= e($method["payment_method"]) ?></strong><span><?= e(money($method["collected_amount"])) ?></span></div><div class="small text-secondary"><?= number_format((int) $method["records_count"]) ?> record<?= (int) $method["records_count"] === 1 ? "" : "s" ?></div></div>
                <?php endforeach; ?>
                <?php if (!$paymentMethods): ?><p class="text-secondary mb-0">No payment methods were used in this period.</p><?php endif; ?>
            </section>
        </div>
        <div class="col-xl-4">
            <section class="panel-card h-100">
                <h2 class="h5 mb-3">Inventory movement</h2>
                <div class="table-responsive" role="region" tabindex="0" aria-label="Reports table"><table data-layout="scroll" role="table" class="table align-middle mb-0"><caption class="visually-hidden">Inventory movement</caption><thead><tr><th scope="col">Item</th><th scope="col">In</th><th scope="col">Out</th><th scope="col">Adjust.</th></tr></thead><tbody>
                    <?php foreach ($inventoryMovements as $movement): ?>
                        <tr><td data-column-label="Item"><div class="table-cell-value"><?= e($movement["item_name"]) ?></div></td><td data-column-label="In"><div class="table-cell-value"><?= number_format((int) $movement["stock_in"]) ?></div></td><td data-column-label="Out"><div class="table-cell-value"><?= number_format((int) $movement["stock_out"]) ?></div></td><td data-column-label="Adjust."><div class="table-cell-value"><?= number_format((int) $movement["adjustments"]) ?></div></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$inventoryMovements): ?><tr class="table-empty-row"><td colspan="4" class="empty-state">No inventory movements were recorded in this period.</td></tr><?php endif; ?>
                </tbody></table></div>
            </section>
        </div>
    </div>
<?php endif; ?>
