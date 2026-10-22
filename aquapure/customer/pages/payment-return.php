<?php
$orderId = positive_int($_GET["id"] ?? 0);
$syncStatus = "pending";
$syncMessage = "We are waiting for PayMongo to confirm your payment.";

if (!$orderId) {
    echo '<section class="panel-card empty-state"><h1 class="h5">Payment order not found</h1><p class="mb-3">Return to your order history and select the order you want to pay for.</p><a class="btn btn-primary" href="' .
        e(url("customer/index.php?page=orders")) .
        '">My orders</a></section>';
    return;
}

try {
    $syncStatus = paymongo_sync_order_payment(
        $orderId,
        (int) current_account()["id"],
    );
    if ($syncStatus === "Paid") {
        $syncMessage = "Your payment was received and the order is ready for processing.";
    } elseif ($syncStatus === "unavailable") {
        $syncMessage = "This payment session is no longer available. Open the order to start a new checkout session if the order is still active.";
    }
} catch (Throwable $exception) {
    error_log("PayMongo return sync failed: " . $exception->getMessage());
    $syncMessage = "We could not verify the payment yet. Please return to the order and try again shortly.";
}

$order = db()->prepare(
    "SELECT order_id, total_amount, payment_status, order_status
     FROM orders WHERE order_id = ? AND customer_id = ?",
);
$order->execute([$orderId, (int) current_account()["id"]]);
$order = $order->fetch();
if (!$order) {
    echo '<section class="panel-card empty-state"><h1 class="h5">Payment order not found</h1></section>';
    return;
}
$paid = $order["payment_status"] === "Paid";
?>
<section class="panel-card max-w-640">
    <div class="icon-box mb-3"><i aria-hidden="true" class="bi <?= $paid ? "bi-check-lg" : "bi-clock" ?>"></i></div>
    <p class="eyebrow mb-1">PayMongo checkout</p>
    <h1 class="h3 section-title mb-2"><?= $paid ? "Payment received" : "Payment status pending" ?></h1>
    <p class="text-secondary mb-4"><?= e($syncMessage) ?></p>
    <div class="d-flex justify-content-between border-top pt-3 mb-4"><span>Order total</span><strong><?= money($order["total_amount"]) ?></strong></div>
    <a class="btn btn-primary" href="<?= e(
        url("customer/index.php?page=order&id=" . $orderId),
    ) ?>">View order</a>
</section>
