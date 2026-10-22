<?php
require_once __DIR__ . "/includes/bootstrap.php";

$pageTitle = "Clean water made simple";
$publicPage = "home";
$products = db()
    ->query(
        "SELECT * FROM products WHERE status = 'Available' ORDER BY product_id LIMIT 6",
    )
    ->fetchAll();
$station = station_settings();

require __DIR__ . "/includes/public-header.php";
?>
<main id="mainContent" tabindex="-1">
    <section class="hero">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <p class="eyebrow mb-3">Clean water. Clear service.</p>
                    <h1 class="display-title mb-4">Your next refill, made simple.</h1>
                    <p class="lead-copy mb-4">Browse water products for pickup or local delivery. AquaPure keeps every order simple, visible, and easy to track.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-primary btn-lg" href="<?= e(
                            is_customer()
                                ? url("customer/index.php?page=products")
                                : url("login.php"),
                        ) ?>">Order water</a>
                        <a class="btn btn-outline-primary btn-lg" href="#products">View products</a>
                    </div>
                    <div class="d-flex flex-wrap gap-4 mt-4 small text-secondary" aria-label="Service highlights">
                        <span><i class="bi bi-shield-check text-primary me-1" aria-hidden="true"></i> Easy ordering</span>
                        <span><i class="bi bi-clock text-primary me-1" aria-hidden="true"></i> Clear order tracking</span>
                        <span><i class="bi bi-truck text-primary me-1" aria-hidden="true"></i> Pickup or delivery</span>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="water-visual" role="img" aria-label="A clean AquaPure water gallon illustration">
                        <div class="gallon"><i aria-hidden="true" class="bi bi-droplet-fill"></i></div>
                        <div class="visual-note" aria-hidden="true">
                            <span><i aria-hidden="true" class="bi bi-check2-circle me-1"></i>Simple ordering</span>
                            <span><i aria-hidden="true" class="bi bi-eye me-1"></i>Visible progress</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section-pad" id="services">
        <div class="container">
            <div class="text-center mx-auto mb-5 max-w-640">
                <p class="eyebrow mb-2">Our services</p>
                <h2 class="section-title">Water service without the guesswork</h2>
                <p class="text-secondary">Choose how you receive your order, then follow its status from confirmation to completion.</p>
            </div>
            <div class="row g-3">
                <div class="col-md-4"><article class="soft-card"><div class="icon-box mb-3"><i class="bi bi-droplet" aria-hidden="true"></i></div><h3 class="h5">Water refills</h3><p class="text-secondary mb-0">Browse available refills and containers for your home or business.</p></article></div>
                <div class="col-md-4"><article class="soft-card"><div class="icon-box mb-3"><i class="bi bi-bag-check" aria-hidden="true"></i></div><h3 class="h5">Easy pickup</h3><p class="text-secondary mb-0">Order ahead and collect once your order is marked ready for pickup.</p></article></div>
                <div class="col-md-4"><article class="soft-card"><div class="icon-box mb-3"><i class="bi bi-truck" aria-hidden="true"></i></div><h3 class="h5">Local delivery</h3><p class="text-secondary mb-0">Add your address and preferred date, then follow each delivery stage.</p></article></div>
            </div>
        </div>
    </section>

    <section class="section-pad section-muted" aria-labelledby="how-title">
        <div class="container">
            <div class="row align-items-start g-4">
                <div class="col-lg-4">
                    <p class="eyebrow mb-2">How it works</p>
                    <h2 class="section-title" id="how-title">From refill to receiving your order</h2>
                    <p class="text-secondary mb-0">A short, predictable flow keeps ordering clear on any device.</p>
                </div>
                <div class="col-lg-8">
                    <div class="row g-4">
                        <div class="col-md-4"><div class="process-step"><span class="process-number">1</span><h3 class="h6 mb-2">Choose products</h3><p class="small text-secondary mb-0">Select the water or container you need and set the quantity.</p></div></div>
                        <div class="col-md-4"><div class="process-step"><span class="process-number">2</span><h3 class="h6 mb-2">Select service</h3><p class="small text-secondary mb-0">Choose pickup or delivery and review the complete total.</p></div></div>
                        <div class="col-md-4"><div class="process-step"><span class="process-number">3</span><h3 class="h6 mb-2">Track progress</h3><p class="small text-secondary mb-0">See order and payment status from confirmation to completion.</p></div></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section-pad" id="products">
        <div class="container">
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
                <div><p class="eyebrow mb-2">Available today</p><h2 class="section-title mb-0">Water and refill products</h2></div>
                <a href="<?= e(
                    is_customer()
                        ? url("customer/index.php?page=products")
                        : url("login.php"),
                ) ?>" class="btn btn-outline-primary">Browse all</a>
            </div>
            <div class="row g-3">
                <?php foreach ($products as $product): ?>
                    <div class="col-sm-6 col-lg-4">
                        <article class="product-card">
                            <div class="product-art">
                                <?php if (!empty($product["image_path"])): ?>
                                    <img src="<?= e(url($product["image_path"])) ?>" alt="<?= e($product["product_name"]) ?>" loading="lazy" decoding="async">
                                <?php else: ?>
                                    <i aria-hidden="true" class="bi bi-droplet-fill"></i>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <span class="badge text-bg-light mb-2"><?= e(
                                    $product["category"],
                                ) ?></span>
                                <h3 class="h5 mb-2"><?= e(
                                    $product["product_name"],
                                ) ?></h3>
                                <div class="d-flex align-items-center justify-content-between">
                                    <strong class="text-primary"><?= money(
                                        $product["price"],
                                    ) ?></strong>
                                    <span class="small text-secondary"><?= (int) $product[
                                        "quantity_available"
                                    ] ?> available</span>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
                <?php if (!$products): ?>
                    <div class="col-12">
                        <div class="empty-state"><i class="bi bi-droplet d-block fs-2 mb-2" aria-hidden="true"></i><strong>No products are available right now.</strong><p class="mb-0 mt-1">Please check again soon or contact the station.</p></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="section-pad pt-0" aria-labelledby="trust-title">
        <div class="container">
            <div class="trust-panel">
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <p class="eyebrow mb-2">Dependable service</p>
                        <h2 class="section-title mb-3" id="trust-title">Know what is happening with every order.</h2>
                        <p class="text-secondary mb-0">AquaPure keeps product availability, payment status, pickup readiness, and delivery progress in one clear customer view.</p>
                    </div>
                    <div class="col-lg-5">
                        <div class="row g-3">
                            <div class="col-6"><div class="d-flex gap-2"><i class="bi bi-eye text-primary" aria-hidden="true"></i><div><strong class="d-block small">Visible status</strong><span class="small text-secondary">Follow each order stage.</span></div></div></div>
                            <div class="col-6"><div class="d-flex gap-2"><i class="bi bi-shield-check text-primary" aria-hidden="true"></i><div><strong class="d-block small">Secure accounts</strong><span class="small text-secondary">Protected customer access.</span></div></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section-pad pt-0">
        <div class="container">
            <div class="cta-band d-flex flex-wrap align-items-center justify-content-between gap-4">
                <div>
                    <h2 class="h3 mb-2">Ready for your next refill?</h2>
                    <p class="mb-0"><?= e($station["business_hours"] !== "" ? $station["business_hours"] : "Create an account to order for pickup or delivery.") ?></p>
                </div>
                <a class="btn btn-light" href="<?= e(is_customer() ? url("customer/index.php?page=products") : url("register.php")) ?>">
                    <?= is_customer() ? "Order water" : "Create customer account" ?>
                </a>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . "/includes/public-footer.php"; ?>
