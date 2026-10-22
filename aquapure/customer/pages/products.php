<?php
$category = trim((string) ($_GET["category"] ?? ""));
$sql = "SELECT * FROM products WHERE 1=1";
$params = [];
if ($category !== "") {
    $sql .= " AND category = ?";
    $params[] = $category;
}
$sql .= " ORDER BY status DESC, product_name";
$statement = db()->prepare($sql);
$statement->execute($params);
$products = $statement->fetchAll();
$categories = db()
    ->query("SELECT DISTINCT category FROM products ORDER BY category")
    ->fetchAll(PDO::FETCH_COLUMN);
?>
<header class="page-header">
    <div>
        <p class="eyebrow mb-1">Order water</p>
        <h1 class="section-title mb-1">Available products</h1>
        <p class="text-secondary mb-0">Choose a product and quantity to build your order.</p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn-primary" href="<?= e(url("customer/index.php?page=cart")) ?>"><i class="bi bi-bag" aria-hidden="true"></i>Review order <span class="badge text-bg-light ms-1"><?= cart_count() ?></span></a>
    </div>
</header>

<nav class="filter-tabs" aria-label="Product categories">
    <a class="btn btn-sm <?= $category === "" ? "btn-primary" : "btn-light" ?>" href="<?= e(url("customer/index.php?page=products")) ?>" <?= $category === "" ? 'aria-current="page"' : "" ?>>All</a>
    <?php foreach ($categories as $item): ?>
        <a class="btn btn-sm <?= $category === $item ? "btn-primary" : "btn-light" ?>" href="<?= e(url("customer/index.php?page=products&category=" . urlencode($item))) ?>" <?= $category === $item ? 'aria-current="page"' : "" ?>><?= e($item) ?></a>
    <?php endforeach; ?>
</nav>

<div class="row g-3">
    <?php foreach ($products as $product): ?>
        <?php $isAvailable = $product["status"] === "Available" && (int) $product["quantity_available"] > 0; ?>
        <div class="col-sm-6 col-lg-4">
            <article class="product-card">
                <div class="product-art">
                    <?php if (!empty($product["image_path"])): ?>
                        <img src="<?= e(url($product["image_path"])) ?>" alt="<?= e($product["product_name"]) ?>" loading="lazy" decoding="async">
                    <?php else: ?>
                        <i class="bi bi-droplet-fill" aria-hidden="true"></i>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between gap-2 mb-2">
                        <span class="badge text-bg-light"><?= e($product["category"]) ?></span>
                        <span class="badge <?= e(badge_class($isAvailable ? "Available" : "Unavailable")) ?>"><?= $isAvailable ? "Available" : "Unavailable" ?></span>
                    </div>
                    <h2 class="h5 mb-2"><?= e($product["product_name"]) ?></h2>
                    <p class="small text-secondary flex-grow-1"><?= e($product["description"]) ?></p>
                    <div class="d-flex justify-content-between mb-3">
                        <strong class="text-primary fs-5 money"><?= money($product["price"]) ?></strong>
                        <span class="small text-secondary align-self-center"><?= (int) $product["quantity_available"] ?> available</span>
                    </div>
                    <form method="post" class="d-flex gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_cart">
                        <input type="hidden" name="product_id" value="<?= (int) $product["product_id"] ?>">
                        <input type="number" class="form-control quantity-input" name="quantity" value="1" min="1" max="<?= max(1, (int) $product["quantity_available"]) ?>" aria-label="Quantity of <?= e($product["product_name"]) ?>" <?= !$isAvailable ? "disabled" : "" ?>>
                        <button class="btn btn-primary flex-grow-1" <?= !$isAvailable ? "disabled" : "" ?>>Add to order</button>
                    </form>
                </div>
            </article>
        </div>
    <?php endforeach; ?>
    <?php if (!$products): ?>
        <div class="col-12"><section class="panel-card empty-state"><i class="bi bi-droplet d-block fs-2 mb-2" aria-hidden="true"></i><h2 class="h5">No products found</h2><p class="mb-0">Try another category or check back when products are available.</p></section></div>
    <?php endif; ?>
</div>
