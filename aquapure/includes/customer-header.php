<?php
$pageTitle = $pageTitle ?? "Customer Portal";
$page = $page ?? "dashboard";
$account = current_account();
$cartCount = cart_count();
$nameParts = preg_split('/\s+/', trim((string) $account["name"])) ?: [];
$accountInitials = "";
foreach (array_slice($nameParts, 0, 2) as $namePart) {
    $accountInitials .= strtoupper(substr($namePart, 0, 1));
}
$customerNav = [
    "dashboard" => ["bi-house", "Home"],
    "products" => ["bi-grid", "Products"],
    "cart" => ["bi-bag", "Order Water"],
    "orders" => ["bi-receipt", "My Orders"],
    "profile" => ["bi-person", "Profile"],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Order water and track AquaPure orders.">
    <title><?= e($pageTitle) ?> | AquaPure</title>
    <link rel="stylesheet" href="<?= e(url("assets/vendor/bootstrap/css/bootstrap.min.css")) ?>">
    <link rel="stylesheet" href="<?= e(url("assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css")) ?>">
    <link rel="stylesheet" href="<?= e(url("assets/css/style.css?v=8")) ?>">
    <noscript><style>
        .navbar-collapse.collapse, .panel-card.collapse { display: block; }
        .dropdown-menu { display: block; position: static; box-shadow: none; }
        [data-bs-toggle="dropdown"] { display: none !important; }
        .local-table-search, .navbar-toggler, [data-bs-toggle="collapse"], [data-confirm], [data-password-toggle], [data-copy-target], [data-print-page], [data-table-search] { display: none !important; }
        @media screen and (max-width: 991.98px) {
            .sidebar.offcanvas-lg { position: static; visibility: visible; transform: none; width: 100% !important; }
            .sidebar .offcanvas-header { display: none !important; }
            .app-main { margin-left: 0; }
            .mobile-menu { display: none; }
        }
    </style></noscript>
</head>
<body class="customer-page">
<a class="skip-link" href="#mainContent">Skip to main content</a>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top site-nav customer-nav" aria-label="Customer navigation">
    <div class="container">
        <a class="navbar-brand brand" href="<?= e(url("customer/index.php")) ?>">
            <img class="brand-logo" src="<?= e(url("assets/images/aquapure-logo-transparent.png")) ?>" width="40" height="40" alt="">
            <span class="brand-copy">AquaPure<small>Customer portal</small></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#customerNav" aria-controls="customerNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="customerNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <?php foreach ($customerNav as $key => [$icon, $label]): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($page === $key || ($key === "orders" && in_array($page, ["order", "payment-return"], true))) ? "active" : "" ?>"
                           href="<?= e(url("customer/index.php?page=" . $key)) ?>"
                           <?= ($page === $key || ($key === "orders" && in_array($page, ["order", "payment-return"], true))) ? 'aria-current="page"' : "" ?>>
                            <i class="bi <?= e($icon) ?> me-1" aria-hidden="true"></i>
                            <?= e($label) ?>
                            <?php if ($key === "cart" && $cartCount > 0): ?>
                                <span class="badge text-bg-primary ms-1 nav-cart-count" aria-label="<?= e($cartCount . " items in order") ?>"><?= $cartCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li class="nav-item dropdown ms-lg-2">
                    <button class="btn btn-light d-flex align-items-center gap-2 w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu for <?= e($account["name"]) ?>">
                        <span class="account-avatar" aria-hidden="true"><?= e($accountInitials ?: "AQ") ?></span>
                        <span class="text-truncate"><?= e($account["name"]) ?></span>
                        <i class="bi bi-chevron-down small ms-auto" aria-hidden="true"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= e(url("customer/index.php?page=profile")) ?>"><i class="bi bi-person" aria-hidden="true"></i>Profile</a></li>
                        <li><a class="dropdown-item text-danger" href="<?= e(url("logout.php")) ?>"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Log out</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<?php if (!empty($_SESSION['flash'])): ?>
    <div class="container mt-3"><?php require __DIR__ . '/flash-messages.php'; ?></div>
<?php endif; ?>
<main class="container customer-content" id="mainContent" tabindex="-1">
<div class="print-only aq-print-brand">AquaPure Water Refilling Station</div>
