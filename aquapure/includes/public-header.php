<?php
$pageTitle = $pageTitle ?? APP_NAME;
$account = current_account();
$publicPage = $publicPage ?? "";
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Order clean, dependable water from AquaPure for pickup or local delivery.">
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
<body class="public-page <?= e($publicPage !== "" ? "public-" . $publicPage : "") ?>">
<a class="skip-link" href="#mainContent">Skip to main content</a>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top site-nav" aria-label="Public navigation">
    <div class="container">
        <a class="navbar-brand brand" href="<?= e(url()) ?>">
            <img class="brand-logo" src="<?= e(url("assets/images/aquapure-logo-transparent.png")) ?>" width="40" height="40" alt="">
            <span>AquaPure</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item"><a class="nav-link <?= $publicPage === "home" ? "active" : "" ?>" href="<?= e(url()) ?>" <?= $publicPage === "home" ? 'aria-current="page"' : "" ?>>Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url("index.php#services")) ?>">Services</a></li>
                <li class="nav-item"><a class="nav-link <?= $publicPage === "products" ? "active" : "" ?>" href="<?= e(url("index.php#products")) ?>">Products</a></li>
                <?php if ($account): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url(($account["type"] === "customer" ? "customer" : "portal") . "/index.php")) ?>">Dashboard</a></li>
                    <li class="nav-item"><a class="btn btn-outline-primary ms-lg-2" href="<?= e(url("logout.php")) ?>">Log out</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link <?= $publicPage === "login" ? "active" : "" ?>" href="<?= e(url("login.php")) ?>" <?= $publicPage === "login" ? 'aria-current="page"' : "" ?>>Log in</a></li>
                    <li class="nav-item"><a class="btn btn-primary ms-lg-2" href="<?= e(url("register.php")) ?>">Create account</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<?php if (!empty($_SESSION['flash'])): ?>
    <div class="container mt-3"><?php require __DIR__ . '/flash-messages.php'; ?></div>
<?php endif; ?>
