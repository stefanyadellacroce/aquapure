<?php
$pageTitle = $pageTitle ?? "Operations Portal";
$page = $page ?? "dashboard";
$account = current_account();
$isNavActive = static fn (string $key): bool =>
    $page === $key ||
    ($key === "orders" && in_array($page, ["order", "order-create"], true));

$navGroups = [
    "Overview" => [
        "dashboard" => ["bi-speedometer2", "Dashboard"],
    ],
    "Operations" => [
        "orders" => ["bi-receipt-cutoff", "Orders"],
        "deliveries" => ["bi-truck", "Deliveries"],
        "customers" => ["bi-people", "Customers"],
    ],
    "Catalog" => [
        "products" => ["bi-droplet", "Products"],
        "inventory" => ["bi-box-seam", "Inventory"],
    ],
    "Finance" => [
        "payments" => ["bi-credit-card", "Payments"],
        "transactions" => ["bi-clock-history", "Transactions"],
        "reports" => ["bi-bar-chart", "Reports"],
    ],
];
if (is_admin()) {
    $navGroups["Administration"] = [
        "users" => ["bi-person-gear", "Users"],
        "invitations" => ["bi-envelope-plus", "Invitations"],
        "activity-logs" => ["bi-journal-text", "Activity logs"],
        "settings" => ["bi-gear", "Settings"],
    ];
}

$unreadCount = null;
try {
    $unreadStatement = db()->prepare(
        "SELECT COUNT(*) FROM notifications
         WHERE is_read = 0
           AND (user_id = ? OR (user_id IS NULL AND customer_id IS NULL))",
    );
    $unreadStatement->execute([(int) $account["id"]]);
    $unreadCount = (int) $unreadStatement->fetchColumn();
} catch (Throwable $exception) {
    error_log("AquaPure notification count failed: " . $exception->getMessage());
}

$nameParts = preg_split('/\s+/', trim((string) $account["name"])) ?: [];
$accountInitials = "";
foreach (array_slice($nameParts, 0, 2) as $namePart) {
    $accountInitials .= strtoupper(substr($namePart, 0, 1));
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="AquaPure operations management portal.">
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
<body class="operations-page">
<a class="skip-link" href="#mainContent">Skip to main content</a>
<div class="app-shell">
    <aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="operationsSidebar" aria-labelledby="operationsSidebarLabel">
        <div class="offcanvas-header d-lg-none">
            <div class="brand" id="operationsSidebarLabel">
                <img class="brand-logo" src="<?= e(url("assets/images/aquapure-logo-transparent.png")) ?>" width="40" height="40" alt="">
                <span class="brand-copy">AquaPure<small>Operations portal</small></span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#operationsSidebar" aria-label="Close navigation"></button>
        </div>
        <div class="sidebar-content offcanvas-body">
            <a class="brand d-none d-lg-inline-flex" href="<?= e(url("portal/index.php")) ?>">
                <img class="brand-logo" src="<?= e(url("assets/images/aquapure-logo-transparent.png")) ?>" width="40" height="40" alt="">
                <span class="brand-copy">AquaPure<small>Operations portal</small></span>
            </a>
            <nav class="sidebar-nav" aria-label="Operations navigation">
                <?php foreach ($navGroups as $groupLabel => $items): ?>
                    <div class="sidebar-group">
                        <div class="sidebar-label"><?= e($groupLabel) ?></div>
                        <?php foreach ($items as $key => [$icon, $label]): ?>
                            <a class="<?= $isNavActive($key) ? "active" : "" ?>"
                               href="<?= e(url("portal/index.php?page=" . $key)) ?>"
                               <?= $isNavActive($key) ? 'aria-current="page"' : "" ?>>
                                <i class="bi <?= e($icon) ?>" aria-hidden="true"></i>
                                <span><?= e($label) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-account">
                <div class="sidebar-account-card">
                    <span class="sidebar-avatar" aria-hidden="true"><?= e($accountInitials ?: "AQ") ?></span>
                    <span class="min-w-0">
                        <span class="sidebar-account-name d-block"><?= e($account["name"]) ?></span>
                        <span class="sidebar-account-role d-block"><?= e($account["role"]) ?></span>
                    </span>
                </div>
                <a class="sidebar-logout d-flex align-items-center gap-2 px-3 py-2 text-decoration-none" href="<?= e(url("logout.php")) ?>">
                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Log out</span>
                </a>
            </div>
        </div>
    </aside>
    <div class="app-main">
        <header class="topbar">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <button class="btn btn-light btn-icon mobile-menu" type="button" data-bs-toggle="offcanvas" data-bs-target="#operationsSidebar" aria-controls="operationsSidebar" aria-label="Open navigation">
                    <i class="bi bi-list fs-5" aria-hidden="true"></i>
                </button>
                <a class="topbar-mobile-brand d-lg-none" href="<?= e(url("portal/index.php")) ?>" aria-label="AquaPure operations home">
                    <img class="brand-logo" src="<?= e(url("assets/images/aquapure-logo-transparent.png")) ?>" width="32" height="32" alt="">
                </a>
                <div class="min-w-0">
                    <div class="topbar-title">AquaPure operations</div>
                    <div class="topbar-subtitle d-none d-sm-block"><?= e($account["role"]) ?> workspace</div>
                </div>
            </div>
            <div class="topbar-actions">
                <a class="btn btn-light btn-icon notification-button" href="<?= e(url("portal/index.php?page=notifications")) ?>" aria-label="<?= $unreadCount === null ? "Notifications; unread count unavailable" : ($unreadCount > 0 ? e($unreadCount . " unread notifications") : "Notifications") ?>">
                    <i class="bi bi-bell" aria-hidden="true"></i>
                    <?php if ($unreadCount > 0): ?><span class="notification-dot" aria-hidden="true"></span><?php endif; ?>
                </a>
                <div class="dropdown">
                    <button class="btn btn-light d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu for <?= e($account["name"]) ?>">
                        <span class="account-avatar" aria-hidden="true"><?= e($accountInitials ?: "AQ") ?></span>
                        <span class="d-none d-md-block text-start">
                            <span class="d-block small fw-semibold"><?= e($account["name"]) ?></span>
                            <span class="d-block topbar-subtitle"><?= e($account["role"]) ?></span>
                        </span>
                        <i class="bi bi-chevron-down small d-none d-md-inline" aria-hidden="true"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small text-secondary"><?= e($account["email"]) ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= e(url("logout.php")) ?>"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Log out</a></li>
                    </ul>
                </div>
            </div>
        </header>
        <main class="page-content" id="mainContent" tabindex="-1">
            <div class="print-only aq-print-brand">AquaPure Water Refilling Station</div>
            <?php require __DIR__ . '/flash-messages.php'; ?>
