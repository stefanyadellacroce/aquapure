<?php
$isAdmin = is_admin();
$editId = positive_int($_GET["edit"] ?? 0);
$edit = null;
if ($editId) {
    $statement = db()->prepare(
        "SELECT * FROM inventory WHERE inventory_id = ?",
    );
    $statement->execute([$editId]);
    $edit = $statement->fetch() ?: null;
}
$items = db()
    ->query(
        "SELECT i.*, u.full_name AS updated_by_name
         FROM inventory i
         LEFT JOIN users u ON u.user_id = i.updated_by
         ORDER BY (i.quantity <= i.reorder_level) DESC, i.item_name",
    )
    ->fetchAll();
$formValues = aq_form_values('save_inventory', $edit ?: [], (int) ($edit['inventory_id'] ?? 0));
$formRetry = aq_form_retry('save_inventory', (int) ($edit['inventory_id'] ?? 0));
?>
<header class="page-header">
    <div>
        <p class="eyebrow mb-1">Station supplies</p>
        <h1 class="section-title mb-1">Inventory</h1>
        <p class="text-secondary mb-0">Low stock is calculated automatically from quantity and reorder level.</p>
    </div>
    <?php if ($isAdmin): ?><div class="page-header__actions">
        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#inventoryForm" aria-controls="inventoryForm" aria-expanded="<?= ($edit || $formRetry) ? "true" : "false" ?>">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>Add item
        </button>
    </div><?php endif; ?>
</header>

<?php if ($isAdmin): ?>
    <section class="panel-card collapse <?= ($edit || $formRetry) ? "show" : "" ?> mb-4" id="inventoryForm">
        <h2 class="h5 mb-3"><?= $edit ? "Edit item" : "New inventory item" ?></h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_inventory">
            <input type="hidden" name="inventory_id" value="<?= (int) ($edit["inventory_id"] ?? 0) ?>">
            <div class="row g-3">
                <div class="col-md-7"><label class="form-label" for="item_name">Item name <span class="text-danger" aria-hidden="true">*</span></label><input class="form-control" id="item_name" name="item_name" value="<?= e($formValues["item_name"] ?? "") ?>" required></div>
                <div class="col-md-5"><label class="form-label" for="category">Category <span class="text-danger" aria-hidden="true">*</span></label><input class="form-control" id="category" name="category" value="<?= e($formValues["category"] ?? "") ?>" placeholder="Packaging" required></div>
                <div class="col-md-4"><label class="form-label" for="quantity">Quantity <span class="text-danger" aria-hidden="true">*</span></label><input type="number" min="0" class="form-control" id="quantity" name="quantity" value="<?= e($formValues["quantity"] ?? "0") ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="unit">Unit <span class="text-danger" aria-hidden="true">*</span></label><input class="form-control" id="unit" name="unit" value="<?= e($formValues["unit"] ?? "pcs") ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="reorder_level">Reorder level <span class="text-danger" aria-hidden="true">*</span></label><input type="number" min="0" class="form-control" id="reorder_level" name="reorder_level" value="<?= e($formValues["reorder_level"] ?? "10") ?>" required></div>
            </div>
            <div class="d-flex gap-2 mt-3"><button class="btn btn-primary">Save item</button><a class="btn btn-light" href="<?= e(url("portal/index.php?page=inventory")) ?>">Cancel</a></div>
        </form>
    </section>
<?php elseif ($edit): ?>
    <section class="panel-card mb-4" id="inventoryForm">
        <h2 class="h5 mb-1">Update stock quantity</h2>
        <p class="text-secondary small mb-3">Enter the new total quantity on hand. This replaces the current quantity; it does not add to it.</p>
        <form method="post" class="row g-3 align-items-end">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_inventory">
            <input type="hidden" name="inventory_id" value="<?= (int) $edit["inventory_id"] ?>">
            <div class="col-md-5"><label class="form-label" for="stockItem">Item</label><input id="stockItem" class="form-control" value="<?= e($edit["item_name"]) ?> (<?= e($edit["unit"]) ?>)" readonly></div>
            <div class="col-md-3"><label class="form-label" for="quantity">New quantity <span class="text-danger" aria-hidden="true">*</span></label><input type="number" min="0" class="form-control" id="quantity" name="quantity" value="<?= e($formValues["quantity"]) ?>" required></div>
            <div class="col-md-auto"><button class="btn btn-primary">Update quantity</button></div>
            <div class="col-md-auto"><a class="btn btn-light" href="<?= e(url("portal/index.php?page=inventory")) ?>">Cancel</a></div>
        </form>
    </section>
<?php endif; ?>

<section class="panel-card">
    <div class="section-header">
        <div><h2 class="h5 mb-1">Inventory list</h2><p class="small text-secondary mb-0"><?= number_format(count($items)) ?> tracked item<?= count($items) === 1 ? "" : "s" ?></p></div>
        <div class="search-box local-table-search"><label class="form-label" for="inventoryTableSearch">Filter displayed inventory</label><div class="search-input"><i class="bi bi-search" aria-hidden="true"></i><input id="inventoryTableSearch" type="search" class="form-control" placeholder="Filter displayed inventory" aria-label="Filter displayed inventory" data-table-search="#inventoryTable"></div></div>
    </div>
    <div class="table-responsive mobile-table-cards" role="region" tabindex="0" aria-label="Inventory table">
        <table data-layout="cards" role="table" class="table align-middle" id="inventoryTable"><caption class="visually-hidden">Inventory list</caption>
            <thead><tr><th scope="col">ID</th><th scope="col">Item</th><th scope="col">Category</th><th scope="col">Quantity</th><th scope="col">Reorder level</th><th scope="col">Status</th><th scope="col">Updated</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
            <tbody>
                <?php if (!$items): ?>
                    <tr class="table-empty-row"><td colspan="8" class="empty-state"><i class="bi bi-box-seam d-block fs-3 mb-2" aria-hidden="true"></i><strong>No inventory items yet.</strong><div class="small mt-1"><?= $isAdmin ? "Add the first item to begin tracking stock." : "Ask an Admin to add inventory items." ?></div></td></tr>
                <?php endif; ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td data-column-label="ID"><div class="table-cell-value"><?= (int) $item["inventory_id"] ?></div></td>
                        <td class="table-primary-cell" data-column-label="Item"><div class="table-cell-value"><strong><?= e($item["item_name"]) ?></strong></div></td>
                        <td data-column-label="Category"><div class="table-cell-value"><?= e($item["category"]) ?></div></td>
                        <td data-column-label="Quantity"><div class="table-cell-value"><?= (int) $item["quantity"] ?> <?= e($item["unit"]) ?></div></td>
                        <td data-column-label="Reorder level"><div class="table-cell-value"><?= (int) $item["reorder_level"] ?></div></td>
                        <td data-column-label="Status"><div class="table-cell-value"><span class="badge <?= e(badge_class($item["status"])) ?>"><?= e($item["status"]) ?></span></div></td>
                        <td data-column-label="Updated"><div class="table-cell-value"><div><?= e(date("M d, Y", strtotime($item["last_updated"]))) ?></div><div class="small text-secondary"><?= e($item["updated_by_name"] ?? "System") ?></div></div></td>
                        <td data-column-label="Actions"><div class="table-cell-value">
                            <div class="d-flex gap-1">
                                <a class="btn btn-sm btn-light" href="<?= e(url("portal/index.php?page=inventory&edit=" . $item["inventory_id"])) ?>"><?= $isAdmin ? "Edit" : "Update stock" ?></a>
                                <?php if ($isAdmin): ?>
                                    <button type="button" class="btn btn-sm btn-light text-danger" data-confirm="Delete <?= e($item["item_name"]) ?>? Items with transaction history cannot be deleted." data-confirm-title="Delete inventory item" data-confirm-label="Delete item" data-action="<?= e(url("portal/index.php?page=inventory")) ?>" data-form-action="delete_inventory" data-id="<?= (int) $item["inventory_id"] ?>">Delete</button>
                                <?php endif; ?>
                            </div>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
