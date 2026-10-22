<?php
$query = trim((string) ($_GET["q"] ?? ""));
$status = trim((string) ($_GET["status"] ?? ""));
$type = trim((string) ($_GET["type"] ?? ""));
$payment = trim((string) ($_GET["payment"] ?? ""));
$date = trim((string) ($_GET["date"] ?? ""));
$sql =
    "SELECT o.*, c.full_name,
            GROUP_CONCAT(CONCAT(oi.quantity, '× ', p.product_name) SEPARATOR ', ') AS products
     FROM orders o
     JOIN customers c ON c.customer_id = o.customer_id
     JOIN order_items oi ON oi.order_id = o.order_id
     JOIN products p ON p.product_id = oi.product_id
     WHERE 1=1";
$params = [];
if ($query !== "") {
    $sql .= " AND (
        c.full_name LIKE ?
        OR CAST(o.order_id AS CHAR) LIKE ?
        OR EXISTS (
            SELECT 1
            FROM order_items oi_search
            JOIN products p_search ON p_search.product_id = oi_search.product_id
            WHERE oi_search.order_id = o.order_id
              AND p_search.product_name LIKE ?
        )
    )";
    $term = "%" . $query . "%";
    $params = [$term, $term, $term];
}
if ($status !== "") {
    $sql .= " AND o.order_status = ?";
    $params[] = $status;
}
if ($type !== "") {
    $sql .= " AND o.order_type = ?";
    $params[] = $type;
}
if ($payment !== "") {
    $sql .= " AND o.payment_status = ?";
    $params[] = $payment;
}
if ($date !== "") {
    $sql .= " AND DATE(o.order_date) = ?";
    $params[] = $date;
}
$sql .= " GROUP BY o.order_id ORDER BY o.order_date DESC";
$statement = db()->prepare($sql);
$statement->execute($params);
$orders = $statement->fetchAll();
$activeFilterCount = count(array_filter([$query, $status, $type, $payment, $date], static fn ($value) => $value !== ""));
?>
<header class="page-header">
    <div>
        <p class="eyebrow mb-1">Central order queue</p>
        <h1 class="section-title mb-1">Orders</h1>
        <p class="text-secondary mb-0">Find, review, and move customer orders through the correct workflow.</p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn-primary" href="<?= e(url("portal/index.php?page=order-create")) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i>Create walk-in order</a>
    </div>
</header>

<section class="filter-bar mb-3" aria-label="Order filters">
    <form method="get" class="row g-2 align-items-end">
        <input type="hidden" name="page" value="orders">
        <div class="col-sm-12 col-xl-3">
            <label class="form-label" for="orderSearch">Search</label>
            <div class="search-box w-100">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input class="form-control" id="orderSearch" name="q" value="<?= e($query) ?>" placeholder="Order, customer, or product">
            </div>
        </div>
        <div class="col-sm-6 col-lg-3 col-xl-2">
            <label class="form-label" for="orderDate">Date</label>
            <input type="date" class="form-control" id="orderDate" name="date" value="<?= e($date) ?>">
        </div>
        <div class="col-sm-6 col-lg-3 col-xl-2">
            <label class="form-label" for="orderType">Order type</label>
            <select class="form-select" id="orderType" name="type">
                <option value="">All types</option>
                <?php foreach (["Walk-in", "Pickup", "Delivery"] as $item): ?>
                    <option value="<?= e($item) ?>" <?= $type === $item ? "selected" : "" ?>><?= e($item) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6 col-lg-3 col-xl-2">
            <label class="form-label" for="orderStatus">Order status</label>
            <select class="form-select" id="orderStatus" name="status">
                <option value="">All statuses</option>
                <?php foreach (["Pending", "Confirmed", "Preparing", "Ready for Pickup", "Out for Delivery", "Delivered", "Completed", "Cancelled"] as $item): ?>
                    <option value="<?= e($item) ?>" <?= $status === $item ? "selected" : "" ?>><?= e($item) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6 col-lg-3 col-xl-1">
            <label class="form-label" for="paymentStatus">Payment</label>
            <select class="form-select" id="paymentStatus" name="payment">
                <option value="">All</option>
                <?php foreach (["Unpaid", "Partial", "Paid", "Refunded"] as $item): ?>
                    <option value="<?= e($item) ?>" <?= $payment === $item ? "selected" : "" ?>><?= e($item) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-xl-2 d-flex gap-2">
            <button class="btn btn-primary flex-grow-1">Apply<?= $activeFilterCount > 0 ? ' <span class="active-filter-count">' . $activeFilterCount . '</span>' : "" ?></button>
            <?php if ($activeFilterCount > 0): ?><a class="btn btn-light" href="<?= e(url("portal/index.php?page=orders")) ?>">Reset</a><?php endif; ?>
        </div>
    </form>
</section>

<section class="panel-card">
    <div class="section-header">
        <div>
            <h2 class="h5 mb-1">Order queue</h2>
            <p class="small text-secondary mb-0"><?= number_format(count($orders)) ?> matching order<?= count($orders) === 1 ? "" : "s" ?></p>
        </div>
    </div>
    <div class="table-responsive mobile-table-cards" role="region" tabindex="0" aria-label="Orders table">
        <table data-layout="cards" role="table" class="table align-middle"><caption class="visually-hidden">Order queue</caption>
            <thead><tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col" class="d-none d-xl-table-cell">Products</th><th scope="col" class="text-end">Total</th><th scope="col">Type</th><th scope="col">Payment</th><th scope="col">Status</th><th scope="col" class="text-end">Action</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td class="table-primary-cell" data-column-label="Order"><div class="table-cell-value">
                        <strong><?= e(order_number((int) $order["order_id"])) ?></strong>
                        <div class="small text-secondary"><?= e(date("M d, Y · g:i A", strtotime($order["order_date"]))) ?></div>
                        <div class="small text-secondary d-xl-none mt-1"><?= e($order["products"]) ?></div>
                    </div></td>
                    <td data-column-label="Customer"><div class="table-cell-value"><?= e($order["full_name"]) ?></div></td>
                    <td class="d-none d-xl-table-cell table-copy" data-column-label="Products"><div class="table-cell-value"><?= e($order["products"]) ?></div></td>
                    <td class="text-end money" data-column-label="Total"><div class="table-cell-value"><?= money($order["total_amount"]) ?></div></td>
                    <td data-column-label="Type"><div class="table-cell-value"><?= e($order["order_type"]) ?></div></td>
                    <td data-column-label="Payment"><div class="table-cell-value"><span class="badge <?= e(badge_class($order["payment_status"])) ?>"><?= e($order["payment_status"]) ?></span></div></td>
                    <td data-column-label="Status"><div class="table-cell-value"><span class="badge <?= e(badge_class($order["order_status"])) ?>"><?= e($order["order_status"]) ?></span></div></td>
                    <td class="text-end" data-column-label="Action"><div class="table-cell-value"><a class="btn btn-sm btn-light" href="<?= e(url("portal/index.php?page=order&id=" . $order["order_id"])) ?>">Manage</a></div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?>
                <tr class="table-empty-row"><td colspan="8" class="empty-state"><i class="bi bi-search d-block fs-3 mb-2" aria-hidden="true"></i><strong><?= $activeFilterCount ? "No orders match these filters." : "No orders yet." ?></strong><div class="small mt-1"><?= $activeFilterCount ? "Clear the filters to see all orders." : "Create a walk-in order or wait for a customer order." ?></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
