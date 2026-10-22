<?php
$customerId = (int) current_account()["id"];
$filter = trim((string) ($_GET["status"] ?? ""));
$sql = "SELECT o.*, GROUP_CONCAT(CONCAT(oi.quantity, '× ', p.product_name) SEPARATOR ', ') AS products
        FROM orders o
        JOIN order_items oi ON oi.order_id = o.order_id
        JOIN products p ON p.product_id = oi.product_id
        WHERE o.customer_id = ?";
$params = [$customerId];
if ($filter !== "") {
    $sql .= " AND o.order_status = ?";
    $params[] = $filter;
}
$sql .= " GROUP BY o.order_id ORDER BY o.order_date DESC";
$statement = db()->prepare($sql);
$statement->execute($params);
$orders = $statement->fetchAll();
?>
<header class="page-header">
    <div><p class="eyebrow mb-1">Order history</p><h1 class="section-title mb-1">My orders</h1><p class="text-secondary mb-0">Track current orders and review completed purchases.</p></div>
    <div class="page-header__actions"><a class="btn btn-primary" href="<?= e(url("customer/index.php?page=products")) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i>New order</a></div>
</header>

<nav class="filter-tabs" aria-label="Order status filters">
    <?php foreach (["" => "All", "Pending" => "Pending", "Confirmed" => "Confirmed", "Preparing" => "Preparing", "Ready for Pickup" => "Ready", "Out for Delivery" => "Delivering", "Delivered" => "Delivered", "Completed" => "Completed", "Cancelled" => "Cancelled"] as $value => $label): ?>
        <a class="btn btn-sm <?= $filter === $value ? "btn-primary" : "btn-light" ?>" href="<?= e(url("customer/index.php?page=orders&status=" . urlencode($value))) ?>" <?= $filter === $value ? 'aria-current="page"' : "" ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<section class="panel-card">
    <div class="section-header"><div><h2 class="h5 mb-1">Order list</h2><p class="small text-secondary mb-0"><?= number_format(count($orders)) ?> matching order<?= count($orders) === 1 ? "" : "s" ?></p></div></div>
    <div class="table-responsive mobile-table-cards" role="region" tabindex="0" aria-label="Orders table">
        <table data-layout="cards" role="table" class="table align-middle"><caption class="visually-hidden">Order list</caption>
            <thead><tr><th scope="col">Order</th><th scope="col">Products</th><th scope="col" class="text-end">Total</th><th scope="col">Type</th><th scope="col">Payment</th><th scope="col">Status</th><th scope="col" class="text-end">Action</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td class="table-primary-cell" data-column-label="Order"><div class="table-cell-value"><strong><?= e(order_number((int) $order["order_id"])) ?></strong><div class="small text-secondary"><?= e(date("M d, Y · g:i A", strtotime($order["order_date"]))) ?></div></div></td>
                    <td class="table-copy" data-column-label="Products"><div class="table-cell-value"><?= e($order["products"]) ?></div></td>
                    <td class="text-end money" data-column-label="Total"><div class="table-cell-value"><?= money($order["total_amount"]) ?></div></td>
                    <td data-column-label="Type"><div class="table-cell-value"><?= e($order["order_type"]) ?></div></td>
                    <td data-column-label="Payment"><div class="table-cell-value"><span class="badge <?= e(badge_class($order["payment_status"])) ?>"><?= e($order["payment_status"]) ?></span></div></td>
                    <td data-column-label="Status"><div class="table-cell-value"><span class="badge <?= e(badge_class($order["order_status"])) ?>"><?= e($order["order_status"]) ?></span></div></td>
                    <td class="text-end" data-column-label="Action"><div class="table-cell-value"><a class="btn btn-sm btn-light" href="<?= e(url("customer/index.php?page=order&id=" . $order["order_id"])) ?>">View details</a></div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?>
                <tr class="table-empty-row"><td colspan="7" class="empty-state"><i class="bi bi-receipt d-block fs-3 mb-2" aria-hidden="true"></i><strong><?= $filter === "" ? "No orders yet." : "No matching orders." ?></strong><div class="small mt-1"><?= $filter === "" ? "Place your first water order to see it here." : "Choose All to clear this status filter." ?></div><?php if ($filter === ""): ?><a class="btn btn-primary btn-sm mt-3" href="<?= e(url("customer/index.php?page=products")) ?>">Browse products</a><?php endif; ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
