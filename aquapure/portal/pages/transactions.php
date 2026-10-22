<?php
$range = $_GET["range"] ?? "month";
$from = $_GET["from"] ?? "";
$to = $_GET["to"] ?? "";
$condition = match ($range) {
    "today" => "DATE(o.order_date) = CURDATE()",
    "week" => "YEARWEEK(o.order_date, 1) = YEARWEEK(CURDATE(), 1)",
    "custom" => "DATE(o.order_date) BETWEEN ? AND ?",
    default => "YEAR(o.order_date) = YEAR(CURDATE()) AND MONTH(o.order_date) = MONTH(CURDATE())",
};
$params = $range === "custom" ? [$from ?: date("Y-m-01"), $to ?: date("Y-m-d")] : [];
$statement = db()->prepare(
    "SELECT o.*, c.full_name, p.payment_method,
            GROUP_CONCAT(CONCAT(oi.quantity, '× ', pr.product_name) SEPARATOR ', ') AS products,
            SUM(oi.quantity) AS total_quantity
     FROM orders o
     JOIN customers c ON c.customer_id = o.customer_id
     LEFT JOIN payments p ON p.order_id = o.order_id
     JOIN order_items oi ON oi.order_id = o.order_id
     JOIN products pr ON pr.product_id = oi.product_id
     WHERE {$condition}
     GROUP BY o.order_id
     ORDER BY o.order_date DESC",
);
$statement->execute($params);
$transactions = $statement->fetchAll();
?>
<header class="page-header">
    <div>
        <p class="eyebrow mb-1">Audit-friendly history</p>
        <h1 class="section-title mb-1">Transaction history</h1>
        <p class="text-secondary mb-0">Review order, product, payment, and fulfillment information by date.</p>
    </div>
</header>

<section class="filter-bar mb-3">
    <form method="get" class="row g-2 align-items-end">
        <input type="hidden" name="page" value="transactions">
        <div class="col-md-4 col-xl-3">
            <label class="form-label" for="transactionRange">Date range</label>
            <select class="form-select" id="transactionRange" name="range" data-date-range>
                <option value="today" <?= $range === "today" ? "selected" : "" ?>>Today</option>
                <option value="week" <?= $range === "week" ? "selected" : "" ?>>This week</option>
                <option value="month" <?= $range === "month" ? "selected" : "" ?>>This month</option>
                <option value="custom" <?= $range === "custom" ? "selected" : "" ?>>Custom dates</option>
            </select>
        </div>
        <div class="col-md-8 col-xl-6">
            <div class="row g-2" data-custom-range-fields>
                <div class="col-6"><label class="form-label" for="transactionFrom">From</label><input type="date" class="form-control" id="transactionFrom" name="from" value="<?= e($from ?: date("Y-m-01")) ?>"></div>
                <div class="col-6"><label class="form-label" for="transactionTo">To</label><input type="date" class="form-control" id="transactionTo" name="to" value="<?= e($to ?: date("Y-m-d")) ?>"></div>
            </div>
        </div>
        <div class="col-xl-3"><button class="btn btn-primary w-100">Apply date range</button></div>
    </form>
</section>

<section class="panel-card">
    <div class="section-header">
        <div><h2 class="h5 mb-1">Transactions</h2><p class="small text-secondary mb-0"><?= number_format(count($transactions)) ?> record<?= count($transactions) === 1 ? "" : "s" ?> in this range</p></div>
        <div class="search-box local-table-search"><label class="form-label" for="transactionsTableSearch">Filter displayed transactions</label><div class="search-input"><i class="bi bi-search" aria-hidden="true"></i><input id="transactionsTableSearch" type="search" class="form-control" placeholder="Filter displayed transactions" aria-label="Filter displayed transactions" data-table-search="#transactionsTable"></div></div>
    </div>
    <div class="table-responsive mobile-table-cards" role="region" tabindex="0" aria-label="Transactions table">
        <table data-layout="cards" role="table" class="table align-middle" id="transactionsTable"><caption class="visually-hidden">Transactions</caption>
            <thead><tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Products</th><th scope="col" class="text-end">Qty</th><th scope="col" class="text-end">Total</th><th scope="col">Payment</th><th scope="col">Type</th><th scope="col">Status</th></tr></thead>
            <tbody>
            <?php foreach ($transactions as $item): ?>
                <tr>
                    <td class="table-primary-cell" data-column-label="Order"><div class="table-cell-value"><strong><?= e(order_number((int) $item["order_id"])) ?></strong><div class="small text-secondary"><?= e(date("M d, Y", strtotime($item["order_date"]))) ?></div></div></td>
                    <td data-column-label="Customer"><div class="table-cell-value"><?= e($item["full_name"]) ?></div></td>
                    <td class="table-copy" data-column-label="Products"><div class="table-cell-value"><?= e($item["products"]) ?></div></td>
                    <td class="text-end" data-column-label="Qty"><div class="table-cell-value"><?= number_format((int) $item["total_quantity"]) ?></div></td>
                    <td class="text-end money" data-column-label="Total"><div class="table-cell-value"><?= money($item["total_amount"]) ?></div></td>
                    <td data-column-label="Payment"><div class="table-cell-value"><strong><?= e($item["payment_method"]) ?></strong><div><span class="badge <?= e(badge_class($item["payment_status"])) ?>"><?= e($item["payment_status"]) ?></span></div></div></td>
                    <td data-column-label="Type"><div class="table-cell-value"><?= e($item["order_type"]) ?></div></td>
                    <td data-column-label="Status"><div class="table-cell-value"><span class="badge <?= e(badge_class($item["order_status"])) ?>"><?= e($item["order_status"]) ?></span></div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$transactions): ?>
                <tr class="table-empty-row"><td colspan="8" class="empty-state"><i class="bi bi-clock-history d-block fs-3 mb-2" aria-hidden="true"></i><strong>No transactions in this date range.</strong><div class="small mt-1">Choose a broader range to see more records.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
