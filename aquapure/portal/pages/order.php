<?php
$orderId = positive_int($_GET["id"] ?? 0);
$statement = db()->prepare(
    "SELECT o.*, c.full_name, c.contact_number AS customer_contact, c.address AS customer_address, p.payment_id, p.amount_due, p.amount_paid, p.change_amount, p.payment_method, p.reference_number, p.payment_date, d.delivery_id, d.recipient_name, d.contact_number AS delivery_contact, d.delivery_address, d.preferred_delivery_date, d.delivery_status FROM orders o JOIN customers c ON c.customer_id = o.customer_id LEFT JOIN payments p ON p.order_id = o.order_id LEFT JOIN deliveries d ON d.order_id = o.order_id WHERE o.order_id = ?",
);
$statement->execute([$orderId]);
$order = $statement->fetch();
if (!$order) {
    echo '<section class="panel-card empty-state"><h1 class="h5">Order not found</h1></section>';
    return;
}
$items = db()->prepare(
    "SELECT oi.*, p.product_name FROM order_items oi JOIN products p ON p.product_id = oi.product_id WHERE oi.order_id = ?",
);
$items->execute([$orderId]);
$items = $items->fetchAll();
$progression =
    $order["order_type"] === "Delivery"
        ? [
            "Pending",
            "Confirmed",
            "Preparing",
            "Out for Delivery",
            "Delivered",
            "Completed",
        ]
        : ($order["order_type"] === "Pickup"
            ? [
                "Pending",
                "Confirmed",
                "Preparing",
                "Ready for Pickup",
                "Completed",
            ]
            : ["Completed"]);
$currentIndex = array_search($order["order_status"], $progression, true);
$nextStatus =
    $currentIndex !== false && isset($progression[$currentIndex + 1])
        ? $progression[$currentIndex + 1]
        : null;
$isFinal = in_array($order["order_status"], ["Completed", "Cancelled"], true);
$paymentValues = aq_form_values('record_payment', $order, $orderId);
$nextActionLabels = [
    "Confirmed" => "Confirm order",
    "Preparing" => "Start preparing",
    "Ready for Pickup" => "Mark ready for pickup",
    "Out for Delivery" => "Send out for delivery",
    "Delivered" => "Mark delivered",
    "Completed" => "Complete order",
];
?>
<header class="page-header"><div><a class="small" href="<?= e(
    url("portal/index.php?page=orders"),
) ?>"><i aria-hidden="true" class="bi bi-arrow-left me-1"></i>Orders</a><h1 class="h2 section-title mt-2 mb-1"><?= e(
    order_number($orderId),
) ?></h1><p class="text-secondary mb-0"><?= e($order["full_name"]) ?> · <?= e(
     date("F d, Y g:i A", strtotime($order["order_date"])),
 ) ?></p></div><div><span class="badge <?= e(
    badge_class($order["payment_status"]),
) ?> me-1"><?= e($order["payment_status"]) ?></span><span class="badge <?= e(
    badge_class($order["order_status"]),
) ?>"><?= e($order["order_status"]) ?></span><button class="btn btn-light ms-2" type="button" data-print-page><i class="bi bi-printer" aria-hidden="true"></i>Print order</button></div></header>
<?php if ($order["order_status"] === "Cancelled"): ?>
    <div class="alert alert-danger mb-4" role="status"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>This order was cancelled. Its workflow can no longer be changed.</div>
<?php else: ?>
    <section class="panel-card mb-4" aria-labelledby="order-progress-title">
        <div class="section-header"><div><h2 class="h5 mb-1" id="order-progress-title">Order progress</h2><p class="small text-secondary mb-0">Stages are completed one step at a time.</p></div></div>
        <div class="timeline">
            <?php foreach ($progression as $index => $step): ?>
                <div class="timeline-step <?= $currentIndex !== false && $index <= $currentIndex ? "done" : "" ?>"><?= e($step) ?></div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
<div class="row g-3"><div class="col-xl-8"><section class="panel-card mb-3"><h2 class="h5 mb-3">Products</h2><div class="table-responsive" role="region" tabindex="0" aria-label="Order table"><table data-layout="scroll" role="table" class="table"><caption class="visually-hidden">Products</caption><thead><tr><th scope="col">Product</th><th scope="col">Quantity</th><th scope="col">Price</th><th scope="col">Subtotal</th></tr></thead><tbody><?php foreach (
    $items
    as $item
): ?><tr><td data-column-label="Product"><div class="table-cell-value"><?= e($item["product_name"]) ?></div></td><td data-column-label="Quantity"><div class="table-cell-value"><?= (int) $item[
    "quantity"
] ?></div></td><td data-column-label="Price"><div class="table-cell-value"><?= money($item["unit_price"]) ?></div></td><td data-column-label="Subtotal"><div class="table-cell-value"><?= money(
    $item["subtotal"],
) ?></div></td></tr><?php endforeach; ?></tbody><tfoot><tr><th colspan="3" class="text-end">Grand total</th><th><?= money(
    $order["total_amount"],
) ?></th></tr></tfoot></table></div></section>
<section class="panel-card"><h2 class="h5 mb-3">Customer and service details</h2><div class="row g-3"><div class="col-md-6"><div class="small text-secondary">Customer</div><strong><?= e(
    $order["full_name"],
) ?></strong><div><?= e($order["customer_contact"]) ?></div><div><?= e(
    $order["customer_address"],
) ?></div></div><div class="col-md-6"><div class="small text-secondary">Order type</div><strong><?= e(
    $order["order_type"],
) ?></strong><?php if ($order["order_type"] === "Delivery"): ?><div><?= e(
    $order["recipient_name"],
) ?> · <?= e($order["delivery_contact"]) ?></div><div><?= e(
    $order["delivery_address"],
) ?></div><div>Preferred: <?= e(
    $order["preferred_delivery_date"],
) ?></div><?php endif; ?></div></div></section></div>
<div class="col-xl-4">
<section class="panel-card next-action-card mb-3">
    <p class="eyebrow mb-1">Workflow action</p>
    <?php if (!$isFinal && $nextStatus !== null): ?>
        <h2 class="h5 mb-2"><?= e($nextActionLabels[$nextStatus] ?? ("Move to " . $nextStatus)) ?></h2>
        <p class="small text-secondary mb-3">Current status: <strong><?= e($order["order_status"]) ?></strong>. The order will move to <strong><?= e($nextStatus) ?></strong>.</p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_order_status">
            <input type="hidden" name="order_id" value="<?= $orderId ?>">
            <input type="hidden" name="order_status" value="<?= e($nextStatus) ?>">
            <button class="btn btn-primary w-100" type="submit"><?= e($nextActionLabels[$nextStatus] ?? ("Move to " . $nextStatus)) ?></button>
        </form>
        <?php if (in_array($order["order_status"], ["Pending", "Confirmed", "Preparing", "Ready for Pickup", "Out for Delivery"], true)): ?>
            <hr>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_order_status">
                <input type="hidden" name="order_id" value="<?= $orderId ?>">
                <input type="hidden" name="order_status" value="Cancelled">
                <button class="btn btn-outline-danger w-100" type="submit" data-inline-confirm="Cancel <?= e(order_number($orderId)) ?>? Reserved product quantities will be returned to stock, and eligible payments may be marked refunded.">Cancel order</button>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <h2 class="h5 mb-2">Workflow complete</h2>
        <p class="small text-secondary mb-0">This order is <?= e(strtolower($order["order_status"])) ?> and has no further operational action.</p>
    <?php endif; ?>
</section>
<section class="panel-card">
    <h2 class="h5 mb-3">Payment</h2>
    <dl class="detail-list print-only">
        <dt>Method</dt><dd><?= e($order['payment_method']) ?></dd>
        <dt>Amount due</dt><dd><?= money($order['amount_due']) ?></dd>
        <dt>Amount received</dt><dd><?= money($order['amount_paid']) ?></dd>
        <dt>Change</dt><dd><?= money($order['change_amount']) ?></dd>
        <?php if ($order['reference_number']): ?><dt>Reference</dt><dd><?= e($order['reference_number']) ?></dd><?php endif; ?>
    </dl>
    <?php if ($order["payment_method"] === "PayMongo"): ?>
        <div class="alert alert-info mb-0">
            <div class="fw-semibold mb-1"><i aria-hidden="true" class="bi bi-shield-lock me-1"></i>PayMongo online payment</div>
            <div class="small">This payment is verified through the PayMongo checkout flow and cannot be manually marked as paid from this form.</div>
            <div class="small mt-2">Current status: <span class="badge <?= e(badge_class($order["payment_status"])) ?>"><?= e($order["payment_status"]) ?></span></div>
        </div>
    <?php else: ?>
        <form method="post" data-cash-calculator data-amount-due="<?= e($order["amount_due"]) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="record_payment">
            <input type="hidden" name="order_id" value="<?= $orderId ?>">
            <div class="mb-3">
                <label class="form-label" for="paymentAmountDue">Amount due</label>
                <input class="form-control" id="paymentAmountDue" value="<?= money($order["amount_due"]) ?>" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label" for="amount_paid">Amount received/paid <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="number" min="0" step="0.01" class="form-control" id="amount_paid" name="amount_paid" value="<?= e($paymentValues["amount_paid"]) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="payment_method">Method <span class="text-danger" aria-hidden="true">*</span></label>
                <select class="form-select" id="payment_method" name="payment_method" data-payment-method required>
                    <?php foreach (["Cash", "GCash", "Maya"] as $method): ?>
                        <option value="<?= e($method) ?>" <?= $paymentValues["payment_method"] === $method ? "selected" : "" ?>><?= e($method) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3" data-reference-field>
                <label class="form-label" for="reference_number">Reference number</label>
                <input class="form-control" id="reference_number" name="reference_number" maxlength="100" value="<?= e($paymentValues["reference_number"]) ?>">
                <div class="form-text">Required for GCash and Maya.</div>
            </div>
            <div class="d-flex justify-content-between small mb-3">
                <span>Calculated change</span>
                <strong data-change-output role="status" aria-live="polite" aria-atomic="true"><?= money($order["change_amount"]) ?></strong>
            </div>
            <button class="btn btn-primary w-100" type="submit">Save payment</button>
        </form>
    <?php endif; ?>
</section></div></div>
