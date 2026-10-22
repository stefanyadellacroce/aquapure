<?php
$status = trim((string) ($_GET["status"] ?? ""));
$sql =
    "SELECT p.*, o.order_id, c.full_name
     FROM payments p
     JOIN orders o ON o.order_id = p.order_id
     JOIN customers c ON c.customer_id = o.customer_id";
$params = [];
if ($status !== "") {
    $sql .= " WHERE p.payment_status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY COALESCE(p.payment_date, o.order_date) DESC";
$statement = db()->prepare($sql);
$statement->execute($params);
$payments = $statement->fetchAll();
$totalDue = 0.0;
$totalCollected = 0.0;
$totalOutstanding = 0.0;
foreach ($payments as $payment) {
    $due = (float) $payment["amount_due"];
    $isRefunded = $payment["payment_status"] === "Refunded";
    $collected = $isRefunded
        ? 0.0
        : max(0, (float) $payment["amount_paid"] - (float) $payment["change_amount"]);
    if (!$isRefunded) {
        $totalDue += $due;
        $totalCollected += $collected;
        $totalOutstanding += max(0, $due - $collected);
    }
}
?>
<header class="page-header">
    <div>
        <p class="eyebrow mb-1">Payment records</p>
        <h1 class="section-title mb-1">Payments</h1>
        <p class="text-secondary mb-0">Review amounts due, collected funds, balances, methods, and references.</p>
    </div>
</header>

<div class="row g-3 mb-4">
    <div class="col-sm-4"><article class="metric-card"><div class="metric-label">Amount due</div><div class="metric-value"><?= e(money($totalDue)) ?></div><div class="metric-context">Across the selected status</div></article></div>
    <div class="col-sm-4"><article class="metric-card"><div class="metric-label">Collected</div><div class="metric-value"><?= e(money($totalCollected)) ?></div><div class="metric-context">After cash change</div></article></div>
    <div class="col-sm-4"><article class="metric-card <?= $totalOutstanding > 0 ? "metric-card--attention" : "" ?>"><div class="metric-label">Remaining balance</div><div class="metric-value"><?= e(money($totalOutstanding)) ?></div><div class="metric-context">Unpaid or partial balance</div></article></div>
</div>

<nav class="filter-tabs" aria-label="Payment status filters">
    <?php foreach (["" => "All", "Unpaid" => "Unpaid", "Partial" => "Partial", "Paid" => "Paid", "Refunded" => "Refunded"] as $value => $label): ?>
        <a class="btn btn-sm <?= $status === $value ? "btn-primary" : "btn-light" ?>" href="<?= e(url("portal/index.php?page=payments&status=" . urlencode($value))) ?>" <?= $status === $value ? 'aria-current="page"' : "" ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<section class="panel-card">
    <div class="section-header"><div><h2 class="h5 mb-1">Payment list</h2><p class="small text-secondary mb-0"><?= number_format(count($payments)) ?> matching record<?= count($payments) === 1 ? "" : "s" ?></p></div></div>
    <div class="table-responsive mobile-table-cards" role="region" tabindex="0" aria-label="Payments table">
        <table data-layout="cards" role="table" class="table align-middle"><caption class="visually-hidden">Payment list</caption>
            <thead><tr><th scope="col">Payment</th><th scope="col">Order / customer</th><th scope="col" class="text-end">Due</th><th scope="col" class="text-end">Paid</th><th scope="col" class="text-end">Change</th><th scope="col">Method / reference</th><th scope="col">Status</th><th scope="col" class="text-end">Action</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $payment): ?>
                <tr>
                    <td class="table-primary-cell" data-column-label="Payment"><div class="table-cell-value"><strong>#<?= (int) $payment["payment_id"] ?></strong><div class="small text-secondary"><?= $payment["payment_date"] ? e(date("M d, Y", strtotime($payment["payment_date"]))) : "Not paid" ?></div></div></td>
                    <td data-column-label="Order / customer"><div class="table-cell-value"><a class="fw-semibold" href="<?= e(url("portal/index.php?page=order&id=" . $payment["order_id"])) ?>"><?= e(order_number((int) $payment["order_id"])) ?></a><div class="small text-secondary"><?= e($payment["full_name"]) ?></div></div></td>
                    <td class="text-end money" data-column-label="Due"><div class="table-cell-value"><?= money($payment["amount_due"]) ?></div></td>
                    <td class="text-end money" data-column-label="Paid"><div class="table-cell-value"><?= money($payment["amount_paid"]) ?></div></td>
                    <td class="text-end money" data-column-label="Change"><div class="table-cell-value"><?= money($payment["change_amount"]) ?></div></td>
                    <td data-column-label="Method / reference"><div class="table-cell-value"><strong><?= e($payment["payment_method"]) ?></strong><div class="small text-secondary overflow-wrap-anywhere"><?= e($payment["reference_number"] ?: "No reference") ?></div></div></td>
                    <td data-column-label="Status"><div class="table-cell-value"><span class="badge <?= e(badge_class($payment["payment_status"])) ?>"><?= e($payment["payment_status"]) ?></span></div></td>
                    <td class="text-end" data-column-label="Action"><div class="table-cell-value"><a class="btn btn-sm btn-light" href="<?= e(url("portal/index.php?page=order&id=" . $payment["order_id"])) ?>">Manage</a></div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$payments): ?>
                <tr class="table-empty-row"><td colspan="8" class="empty-state"><i class="bi bi-credit-card d-block fs-3 mb-2" aria-hidden="true"></i><strong>No payment records found.</strong><div class="small mt-1"><?= $status !== "" ? "Choose All to clear this filter." : "Payment records appear when an order is created." ?></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
