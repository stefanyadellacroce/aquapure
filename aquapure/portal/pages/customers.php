<?php
$editId = positive_int($_GET["edit"] ?? 0);
$edit = null;
if ($editId) {
    $statement = db()->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $statement->execute([$editId]);
    $edit = $statement->fetch();
}
$customers = db()
    ->query(
        "SELECT c.*, COUNT(o.order_id) AS order_count FROM customers c LEFT JOIN orders o ON o.customer_id = c.customer_id GROUP BY c.customer_id ORDER BY c.created_at DESC",
    )
    ->fetchAll();
$formValues = aq_form_values('save_customer', $edit ?: [], (int) ($edit['customer_id'] ?? 0));
$formRetry = aq_form_retry('save_customer', (int) ($edit['customer_id'] ?? 0));
?>
<header class="page-header"><div><p class="eyebrow mb-1">Customer records</p><h1 class="section-title mb-1">Customers</h1><p class="text-secondary mb-0">Register walk-in customers and keep contact details accurate.</p></div><div class="page-header__actions"><button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#customerForm" aria-controls="customerForm" aria-expanded="<?= ($edit || $formRetry) ? "true" : "false" ?>"><i class="bi bi-person-plus" aria-hidden="true"></i>Add customer</button></div></header>
<section class="panel-card collapse <?= ($edit || $formRetry)
    ? "show"
    : "" ?> mb-4" id="customerForm"><h2 class="h5 mb-3"><?= $edit
     ? "Edit customer"
     : "New customer" ?></h2><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save_customer"><input type="hidden" name="customer_id" value="<?= (int) ($edit[
    "customer_id"
] ??
    0) ?>"><div class="row g-3"><div class="col-md-6"><label class="form-label" for="customerName">Full name <span class="text-danger" aria-hidden="true">*</span></label><input class="form-control" id="customerName" name="full_name" value="<?= e(
    $formValues["full_name"] ?? "",
) ?>" required></div><div class="col-md-6"><label class="form-label" for="customerEmail">Email <span class="text-secondary">(optional for walk-in)</span></label><input type="email" class="form-control" id="customerEmail" name="email" value="<?= e(
    $formValues["email"] ?? "",
) ?>"></div><div class="col-md-4"><label class="form-label" for="customerContact">Contact number <span class="text-danger" aria-hidden="true">*</span></label><input class="form-control" id="customerContact" name="contact_number" value="<?= e(
    $formValues["contact_number"] ?? "",
) ?>" required inputmode="tel" autocomplete="tel"></div><div class="col-md-4"><label class="form-label" for="customerType">Customer type</label><select class="form-select" id="customerType" name="customer_type"><?php foreach (
    ["Walk-in Customer", "Online Customer", "Residential", "Business"]
    as $type
): ?><option <?= ($formValues["customer_type"] ?? "Walk-in Customer") === $type
    ? "selected"
    : "" ?>><?= e(
    $type,
) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label" for="customerStatus">Account status</label><select class="form-select" id="customerStatus" name="account_status" <?= !is_admin()
    ? "disabled"
    : "" ?>><option <?= ($formValues["account_status"] ?? "Active") === "Active"
    ? "selected"
    : "" ?>>Active</option><option <?= ($formValues["account_status"] ?? "") ===
"Inactive"
    ? "selected"
    : "" ?>>Inactive</option></select></div><div class="col-12"><label class="form-label" for="customerAddress">Address <span class="text-danger" aria-hidden="true">*</span></label><textarea class="form-control" id="customerAddress" name="address" rows="2" required><?= e(
    $formValues["address"] ?? "",
) ?></textarea></div></div><div class="d-flex gap-2 mt-3"><button class="btn btn-primary">Save customer</button><a class="btn btn-light" href="<?= e(
    url("portal/index.php?page=customers"),
) ?>">Cancel</a></div></form></section>
<section class="panel-card"><div class="section-header"><div><h2 class="h5 mb-1">Customer list</h2><p class="small text-secondary mb-0"><?= number_format(count($customers)) ?> customer<?= count($customers) === 1 ? "" : "s" ?></p></div><div class="search-box local-table-search"><label class="form-label" for="customersTableSearch">Filter displayed customers</label><div class="search-input"><i class="bi bi-search" aria-hidden="true"></i><input id="customersTableSearch" type="search" class="form-control" placeholder="Filter displayed customers" aria-label="Filter displayed customers" data-table-search="#customersTable"></div></div></div><div class="table-responsive mobile-table-cards" role="region" tabindex="0" aria-label="Customers table"><table data-layout="cards" role="table" class="table align-middle" id="customersTable"><caption class="visually-hidden">Customer list</caption><thead><tr><th scope="col">ID</th><th scope="col">Customer</th><th scope="col">Contact</th><th scope="col">Type</th><th scope="col">Orders</th><th scope="col">Status</th><th scope="col" class="text-end">Actions</th></tr></thead><tbody><?php foreach (
    $customers
    as $customer
): ?><tr><td data-column-label="ID"><div class="table-cell-value"><?= (int) $customer["customer_id"] ?></div></td><td class="table-primary-cell" data-column-label="Customer"><div class="table-cell-value"><strong><?= e(
    $customer["full_name"],
) ?></strong><div class="small text-secondary"><?= e(
    $customer["email"] ?: "No email",
) ?></div></div></td><td data-column-label="Contact"><div class="table-cell-value"><?= e(
    $customer["contact_number"],
) ?><div class="small text-secondary"><?= e(
    $customer["address"],
) ?></div></div></td><td data-column-label="Type"><div class="table-cell-value"><?= e(
    $customer["customer_type"],
) ?></div></td><td data-column-label="Orders"><div class="table-cell-value"><?= (int) $customer[
    "order_count"
] ?></div></td><td data-column-label="Status"><div class="table-cell-value"><span class="badge <?= e(
    badge_class($customer["account_status"]),
) ?>"><?= e(
    $customer["account_status"],
) ?></span></div></td><td data-column-label="Actions"><div class="table-cell-value"><div class="d-flex gap-1"><a class="btn btn-sm btn-light" href="<?= e(
    url("portal/index.php?page=customers&edit=" . $customer["customer_id"]),
) ?>">Edit</a><?php if (
    is_admin()
): ?><button type="button" class="btn btn-sm btn-light <?= $customer["account_status"] === "Active" ? "text-danger" : "" ?>" data-confirm="<?= $customer["account_status"] === "Active" ? "Deactivate " . e($customer["full_name"]) . "? They will no longer be able to sign in until reactivated." : "Reactivate " . e($customer["full_name"]) . "? They will be able to use the customer account again." ?>" data-action="<?= e(
    url("portal/index.php?page=customers"),
) ?>" data-form-action="toggle_customer" data-id="<?= (int) $customer[
    "customer_id"
] ?>" data-confirm-title="<?= $customer["account_status"] === "Active" ? "Deactivate customer" : "Reactivate customer" ?>" data-confirm-label="<?= $customer["account_status"] === "Active" ? "Deactivate" : "Reactivate" ?>"><?= $customer["account_status"] === "Active" ? "Deactivate" : "Reactivate" ?></button><?php endif; ?></div></div></td></tr><?php endforeach; ?>
<?php if (!$customers): ?><tr class="table-empty-row"><td colspan="7" class="empty-state"><i class="bi bi-people d-block fs-3 mb-2" aria-hidden="true"></i><strong>No customers yet.</strong><div class="small mt-1">Add a customer to create a walk-in order.</div></td></tr><?php endif; ?>
</tbody></table></div></section>
