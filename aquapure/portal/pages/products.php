<?php
$editId = positive_int($_GET["edit"] ?? 0);
$edit = null;
if ($editId) {
    $statement = db()->prepare("SELECT * FROM products WHERE product_id = ?");
    $statement->execute([$editId]);
    $edit = $statement->fetch();
}
$products = db()
    ->query("SELECT * FROM products ORDER BY product_name")
    ->fetchAll();
$formValues = aq_form_values('save_product', $edit ?: [], (int) ($edit['product_id'] ?? 0));
$formRetry = aq_form_retry('save_product', (int) ($edit['product_id'] ?? 0));
?>
<header class="page-header">
    <div>
        <p class="eyebrow mb-1">Product catalog</p>
        <h1 class="section-title mb-1">Products</h1>
        <p class="text-secondary mb-0">Manage sellable water products, stock, images, and availability.</p>
    </div>
    <?php if (is_admin()): ?><div class="page-header__actions">
        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#productForm" aria-controls="productForm" aria-expanded="<?= ($edit || $formRetry) ? "true" : "false" ?>">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>Add product
        </button>
    </div><?php endif; ?>
</header>

<?php if (is_admin()): ?>
    <section class="panel-card collapse <?= ($edit || $formRetry) ? "show" : "" ?> mb-4" id="productForm">
        <h2 class="h5 mb-3"><?= $edit ? "Edit product" : "New product" ?></h2>
        <form method="post" enctype="multipart/form-data" class="row g-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_product">
            <input type="hidden" name="product_id" value="<?= (int) ($edit["product_id"] ?? 0) ?>">
            <div class="col-md-6">
                <label class="form-label" for="product_name">Product name <span class="text-danger" aria-hidden="true">*</span></label>
                <input class="form-control" id="product_name" name="product_name" value="<?= e($formValues["product_name"] ?? "") ?>" maxlength="120" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="category">Category</label>
                <select class="form-select" id="category" name="category">
                    <?php foreach (["Refill", "Bottled Water", "Container", "Accessory"] as $category): ?>
                        <option value="<?= e($category) ?>" <?= ($formValues["category"] ?? "") === $category ? "selected" : "" ?>><?= e($category) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="status">Availability</label>
                <select class="form-select" id="status" name="status">
                    <option value="Available" <?= ($formValues["status"] ?? "Available") === "Available" ? "selected" : "" ?>>Available</option>
                    <option value="Unavailable" <?= ($formValues["status"] ?? "") === "Unavailable" ? "selected" : "" ?>>Unavailable</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="price">Price <span class="text-danger" aria-hidden="true">*</span></label>
                <input class="form-control" id="price" type="number" step="0.01" min="0" name="price" value="<?= e($formValues["price"] ?? "0.00") ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="quantity_available">Quantity available <span class="text-danger" aria-hidden="true">*</span></label>
                <input class="form-control" id="quantity_available" type="number" min="0" name="quantity_available" value="<?= e($formValues["quantity_available"] ?? "0") ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="product_image">Product image</label>
                <input class="form-control" id="product_image" type="file" name="product_image" accept="image/jpeg,image/png,image/webp">
                <div class="form-text">Optional JPG, PNG, or WebP image up to 2 MB.<?php if ($formRetry): ?> Select your image again before saving.<?php endif; ?></div>
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="2" maxlength="255"><?= e($formValues["description"] ?? "") ?></textarea>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary">Save product</button>
                <a class="btn btn-light" href="<?= e(url("portal/index.php?page=products")) ?>">Cancel</a>
            </div>
        </form>
    </section>
<?php endif; ?>

<section class="panel-card">
    <div class="section-header">
        <div><h2 class="h5 mb-1">Product list</h2><p class="small text-secondary mb-0"><?= number_format(count($products)) ?> product<?= count($products) === 1 ? "" : "s" ?></p></div>
        <div class="search-box local-table-search"><label class="form-label" for="productsTableSearch">Filter displayed products</label><div class="search-input"><i class="bi bi-search" aria-hidden="true"></i><input id="productsTableSearch" type="search" class="form-control" placeholder="Filter displayed products" aria-label="Filter displayed products" data-table-search="#productsTable"></div></div>
    </div>
    <div class="table-responsive mobile-table-cards" role="region" tabindex="0" aria-label="Products table">
        <table data-layout="cards" role="table" class="table align-middle mb-0" id="productsTable"><caption class="visually-hidden">Product list</caption>
            <thead>
                <tr><th scope="col">Product</th><th scope="col">Category</th><th scope="col">Price</th><th scope="col">Quantity</th><th scope="col">Status</th><th scope="col" class="text-end">Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td class="table-primary-cell" data-column-label="Product"><div class="table-cell-value">
                            <div class="d-flex align-items-center gap-2">
                                <?php if (!empty($product["image_path"])): ?>
                                    <img class="product-thumb" src="<?= e(url($product["image_path"])) ?>" alt="<?= e($product["product_name"]) ?>" loading="lazy" decoding="async">
                                <?php else: ?>
                                    <span class="product-thumb product-thumb-placeholder"><i aria-hidden="true" class="bi bi-droplet-fill"></i></span>
                                <?php endif; ?>
                                <div>
                                    <strong><?= e($product["product_name"]) ?></strong>
                                    <div class="small text-secondary"><?= e($product["description"] ?? "") ?></div>
                                </div>
                            </div>
                        </div></td>
                        <td data-column-label="Category"><div class="table-cell-value"><?= e($product["category"]) ?></div></td>
                        <td data-column-label="Price"><div class="table-cell-value"><?= money($product["price"]) ?></div></td>
                        <td data-column-label="Quantity"><div class="table-cell-value"><?= (int) $product["quantity_available"] ?></div></td>
                        <td data-column-label="Status"><div class="table-cell-value"><span class="badge <?= e(badge_class($product["status"])) ?>"><?= e($product["status"]) ?></span></div></td>
                        <td class="text-end" data-column-label="Actions"><div class="table-cell-value">
                            <?php if (is_admin()): ?>
                                <div class="table-actions">
                                    <a class="btn btn-sm btn-light" href="<?= e(url("portal/index.php?page=products&edit=" . $product["product_id"])) ?>">Edit</a>
                                    <button type="button" class="btn btn-sm btn-light text-danger" data-confirm="Delete <?= e($product["product_name"]) ?>? Products with order history will be marked unavailable instead of removed." data-confirm-title="Delete product" data-confirm-label="Delete product" data-action="<?= e(url("portal/index.php?page=products")) ?>" data-form-action="delete_product" data-id="<?= (int) $product["product_id"] ?>">Delete</button>
                                </div>
                            <?php else: ?>
                                <span class="small text-secondary">View only</span>
                            <?php endif; ?>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$products): ?>
                    <tr class="table-empty-row"><td colspan="6" class="empty-state"><i class="bi bi-droplet d-block fs-3 mb-2" aria-hidden="true"></i><strong>No products found.</strong><div class="small mt-1"><?= is_admin() ? "Add the first sellable product." : "Ask an Admin to add products." ?></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
