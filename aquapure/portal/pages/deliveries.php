<?php
$deliveries = db()
    ->query(
        "SELECT d.*, o.order_id, c.full_name, u.full_name AS assigned_name
         FROM deliveries d
         JOIN orders o ON o.order_id = d.order_id
         JOIN customers c ON c.customer_id = o.customer_id
         LEFT JOIN users u ON u.user_id = d.assigned_staff_id
         ORDER BY FIELD(d.delivery_status, 'Pending', 'Confirmed', 'Preparing', 'Out for Delivery', 'Delivered', 'Cancelled'),
                  d.preferred_delivery_date, d.delivery_id DESC",
    )
    ->fetchAll();
$staff = db()
    ->query(
        "SELECT user_id, full_name
         FROM users
         WHERE role = 'Staff' AND account_status = 'Active'
         ORDER BY full_name",
    )
    ->fetchAll();
$deliveryProgression = ["Pending", "Confirmed", "Preparing", "Out for Delivery", "Delivered"];
?>
<header class="page-header">
    <div>
        <p class="eyebrow mb-1">Delivery operations</p>
        <h1 class="section-title mb-1">Deliveries</h1>
        <p class="text-secondary mb-0">Assign a driver and move each delivery through one valid stage at a time.</p>
    </div>
</header>

<section class="panel-card">
    <div class="section-header">
        <div><h2 class="h5 mb-1">Delivery queue</h2><p class="small text-secondary mb-0"><?= number_format(count($deliveries)) ?> delivery record<?= count($deliveries) === 1 ? "" : "s" ?></p></div>
    </div>
    <div class="table-responsive mobile-table-cards" role="region" tabindex="0" aria-label="Deliveries table">
        <table data-layout="cards" role="table" class="table align-middle"><caption class="visually-hidden">Delivery queue</caption>
            <thead><tr><th scope="col">Delivery</th><th scope="col">Order</th><th scope="col">Customer / recipient</th><th scope="col">Address</th><th scope="col">Date</th><th scope="col">Status</th><th scope="col">Assignment and next step</th></tr></thead>
            <tbody>
            <?php foreach ($deliveries as $delivery): ?>
                <?php
                $currentDeliveryStatus = (string) $delivery["delivery_status"];
                $currentIndex = array_search($currentDeliveryStatus, $deliveryProgression, true);
                $isDeliveryFinal = in_array($currentDeliveryStatus, ["Delivered", "Cancelled"], true);
                $allowedStatuses = [$currentDeliveryStatus];
                if (!$isDeliveryFinal && $currentIndex !== false && isset($deliveryProgression[$currentIndex + 1])) {
                    $allowedStatuses[] = $deliveryProgression[$currentIndex + 1];
                    $allowedStatuses[] = "Cancelled";
                }
                ?>
                <tr>
                    <td class="table-primary-cell" data-column-label="Delivery"><div class="table-cell-value"><strong>#<?= (int) $delivery["delivery_id"] ?></strong></div></td>
                    <td data-column-label="Order"><div class="table-cell-value"><a class="fw-semibold" href="<?= e(url("portal/index.php?page=order&id=" . $delivery["order_id"])) ?>"><?= e(order_number((int) $delivery["order_id"])) ?></a></div></td>
                    <td data-column-label="Customer / recipient"><div class="table-cell-value"><strong><?= e($delivery["full_name"]) ?></strong><div class="small text-secondary"><?= e($delivery["recipient_name"]) ?> · <?= e($delivery["contact_number"]) ?></div></div></td>
                    <td class="table-copy" data-column-label="Address"><div class="table-cell-value"><?= e($delivery["delivery_address"]) ?></div></td>
                    <td class="text-nowrap" data-column-label="Date"><div class="table-cell-value"><?= e(date("M d, Y", strtotime($delivery["preferred_delivery_date"]))) ?></div></td>
                    <td data-column-label="Status"><div class="table-cell-value"><span class="badge <?= e(badge_class($currentDeliveryStatus)) ?>"><?= e($currentDeliveryStatus) ?></span></div></td>
                    <td class="table-min-320" data-column-label="Assignment and next step"><div class="table-cell-value">
                        <?php if ($isDeliveryFinal): ?>
                            <div class="small text-secondary"><?= e($delivery["assigned_name"] ?: "Unassigned") ?> · Workflow complete</div>
                        <?php else: ?>
                            <form method="post" class="row g-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_delivery">
                                <input type="hidden" name="delivery_id" value="<?= (int) $delivery["delivery_id"] ?>">
                                <div class="col-12 col-sm-5">
                                    <label class="form-label small" for="assignedStaff<?= (int) $delivery["delivery_id"] ?>">Assigned staff</label>
                                    <select class="form-select form-select-sm" id="assignedStaff<?= (int) $delivery["delivery_id"] ?>" name="assigned_staff_id">
                                        <option value="">Unassigned</option>
                                        <?php foreach ($staff as $person): ?>
                                            <option value="<?= (int) $person["user_id"] ?>" <?= (int) $delivery["assigned_staff_id"] === (int) $person["user_id"] ? "selected" : "" ?>><?= e($person["full_name"]) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-8 col-sm-5">
                                    <label class="form-label small" for="deliveryStatus<?= (int) $delivery["delivery_id"] ?>">Delivery status</label>
                                    <select class="form-select form-select-sm" id="deliveryStatus<?= (int) $delivery["delivery_id"] ?>" name="delivery_status">
                                        <?php foreach (array_unique($allowedStatuses) as $status): ?>
                                            <option value="<?= e($status) ?>" <?= $currentDeliveryStatus === $status ? "selected" : "" ?>><?= e($status) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-4 col-sm-2 d-flex align-items-end"><button class="btn btn-sm btn-primary w-100">Save</button></div>
                            </form>
                        <?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$deliveries): ?>
                <tr class="table-empty-row"><td colspan="7" class="empty-state"><i class="bi bi-truck d-block fs-3 mb-2" aria-hidden="true"></i><strong>No deliveries yet.</strong><div class="small mt-1">Delivery orders will appear here after checkout.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
