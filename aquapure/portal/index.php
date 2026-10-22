<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_system_user();
require_once __DIR__ . "/../actions/portal.php";

$allowedPages = [
    "dashboard",
    "customers",
    "products",
    "orders",
    "order",
    "order-create",
    "deliveries",
    "inventory",
    "payments",
    "transactions",
    "reports",
    "users",
    "invitations",
    "activity-logs",
    "settings",
    "notifications",
];
$page = $_GET["page"] ?? "dashboard";
if (!in_array($page, $allowedPages, true)) {
    $page = "dashboard";
}
if (
    in_array($page, ["users", "invitations", "activity-logs", "settings"], true) &&
    !is_admin()
) {
    http_response_code(403);
    exit("Admin access required.");
}
$pageTitle = match ($page) {
    "order-create" => "Create Walk-in Order",
    "order" => "Order Details",
    "invitations" => "Registration Invitations",
    "activity-logs" => "Activity Logs",
    "settings" => "System Settings",
    default => ucwords($page),
};

require __DIR__ . "/../includes/portal-header.php";
require __DIR__ . "/pages/" . $page . ".php";
require __DIR__ . "/../includes/portal-footer.php";
