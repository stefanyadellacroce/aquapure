<?php
$orderId = positive_int($_GET["id"] ?? 0);
$statement = db()
    ->prepare("SELECT o.*, p.payment_method, p.amount_due, p.amount_paid, p.change_amount, p.reference_number, p.payment_date,
    d.recipient_name, d.contact_number, d.delivery_address, d.preferred_delivery_date, d.delivery_status
    FROM orders o LEFT JOIN payments p ON p.order_id = o.order_id LEFT JOIN deliveries d ON d.order_id = o.order_id
    WHERE o.order_id = ? AND o.customer_id = ?");
$statement->execute([$orderId, (int) current_account()["id"]]);
$order = $statement->fetch();
if (!$order) {
    echo '<section class="panel-card empty-state"><h1 class="h5">Order not found</h1></section>';
    return;
}
$items = db()->prepare(
    "SELECT oi.*, p.product_name FROM order_items oi JOIN products p ON p.product_id = oi.product_id WHERE oi.order_id = ?",
);
$items->execute([$orderId]);
$steps =
    $order["order_type"] === "Delivery"
        ? [
            "Pending",
            "Confirmed",
            "Preparing",
            "Out for Delivery",
            "Delivered",
            "Completed",
        ]
        : [
            "Pending",
            "Confirmed",
            "Preparing",
            "Ready for Pickup",
            "Completed",
        ];
$currentIndex = array_search($order["order_status"], $steps, true);
$netPaid = max(0, (float) $order["amount_paid"] - (float) $order["change_amount"]);
$remainingBalance = max(0, (float) $order["amount_due"] - $netPaid);
?>
<header class="page-header"><div><a class="small" href="<?= e(
    url("customer/index.php?page=orders"),
) ?>"><i aria-hidden="true" class="bi bi-arrow-left me-1"></i>My Orders</a><h1 class="h2 section-title mt-2 mb-1"><?= e(
    order_number($orderId),
) ?></h1><p class="text-secondary mb-0">Placed <?= e(
    date("F d, Y g:i A", strtotime($order["order_date"])),
) ?></p></div><div class="d-flex flex-wrap align-items-center gap-2"><span class="badge <?= e(
    badge_class($order["payment_status"]),
) ?>"><?= e($order["payment_status"]) ?></span><span class="badge <?= e(
    badge_class($order["order_status"]),
) ?>"><?= e($order["order_status"]) ?></span><button class="btn btn-light" type="button" data-print-page><i class="bi bi-printer" aria-hidden="true"></i>Print order</button></div></header>
<?php if ($order["order_status"] === "Cancelled"): ?>
    <div class="alert alert-danger mb-4" role="status"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>This order was cancelled. Its progress timeline is no longer active.</div>
<?php endif; ?>
<?php if (
    $order["payment_method"] === "PayMongo" &&
    $order["payment_status"] !== "Paid" &&
    $order["order_status"] !== "Cancelled"
): ?><section class="panel-card mb-3 d-flex flex-wrap align-items-center justify-content-between gap-3"><div><h2 class="h5 mb-1">Online payment</h2><p class="small text-secondary mb-0">Complete this order through PayMongo's secure checkout.</p></div><form method="post" class="m-0"><?= csrf_field() ?><input type="hidden" name="action" value="start_paymongo_checkout"><input type="hidden" name="order_id" value="<?= $orderId ?>"><button class="btn btn-primary"><i aria-hidden="true" class="bi bi-lock me-1"></i>Pay online</button></form></section><?php endif; ?>
<?php if (
    $order["order_status"] !== "Cancelled"
): ?><section class="panel-card mb-4"><h2 class="h5 mb-3">Order progress</h2><div class="timeline"><?php foreach (
    $steps
    as $index => $step
): ?><div class="timeline-step <?= $currentIndex !== false &&
$index <= $currentIndex
    ? "done"
    : "" ?>"><?= e(
    $step,
) ?></div><?php endforeach; ?></div></section><?php endif; ?>
<div class="row g-3"><div class="col-lg-8"><section class="panel-card h-100"><h2 class="h5 mb-3">Items</h2><div class="table-responsive" role="region" tabindex="0" aria-label="Order table"><table data-layout="scroll" role="table" class="table"><caption class="visually-hidden">Items</caption><thead><tr><th scope="col">Product</th><th scope="col">Quantity</th><th scope="col">Unit price</th><th scope="col">Subtotal</th></tr></thead><tbody><?php foreach (
    $items->fetchAll()
    as $item
): ?><tr><td data-column-label="Product"><div class="table-cell-value"><?= e($item["product_name"]) ?></div></td><td data-column-label="Quantity"><div class="table-cell-value"><?= (int) $item[
    "quantity"
] ?></div></td><td data-column-label="Unit price"><div class="table-cell-value"><?= money($item["unit_price"]) ?></div></td><td data-column-label="Subtotal"><div class="table-cell-value"><?= money(
    $item["subtotal"],
) ?></div></td></tr><?php endforeach; ?></tbody><tfoot><tr><th colspan="3" class="text-end">Grand total</th><th><?= money(
    $order["total_amount"],
) ?></th></tr></tfoot></table></div></section></div>
<div class="col-lg-4"><section class="panel-card h-100"><h2 class="h5 mb-3">Order information</h2><dl class="detail-list"><dt>Type</dt><dd><?= e(
    $order["order_type"],
) ?></dd><dt>Payment method</dt><dd><?= e(
    $order["payment_method"],
) ?></dd><dt>Amount due</dt><dd class="money"><?= money(
    $order["amount_due"],
) ?></dd><dt>Amount paid</dt><dd class="money"><?= money(
    $netPaid,
) ?></dd><dt>Remaining</dt><dd class="money"><?= money(
    $remainingBalance,
) ?></dd><?php if (
    $order["order_type"] === "Delivery"
): ?><dt>Recipient</dt><dd><?= e(
    $order["recipient_name"],
) ?></dd><dt>Address</dt><dd><?= e(
    $order["delivery_address"],
) ?></dd><dt>Preferred date</dt><dd><?= e(
    $order["preferred_delivery_date"],
) ?></dd><?php endif; ?></dl><?php if (
    in_array($order["order_status"], ["Pending", "Confirmed"], true)
): ?><button type="button" class="btn btn-outline-danger w-100 mt-3" data-confirm="Cancel <?= e(
    order_number($orderId),
) ?>? Reserved product quantities will be returned to stock." data-action="<?= e(
    url("customer/index.php?page=order&id=" . $orderId),
) ?>" data-confirm-title="Cancel order" data-confirm-label="Cancel order" data-form-action="cancel_order" data-id="<?= $orderId ?>">Cancel order</button><?php endif; ?></section></div></div>
