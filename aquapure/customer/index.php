<?php
require_once __DIR__ . "/../includes/bootstrap.php";
require_customer();
require_once __DIR__ . "/../actions/customer.php";

$allowedPages = [
    "dashboard",
    "products",
    "cart",
    "orders",
    "order",
    "payment-return",
    "profile",
];
$page = $_GET["page"] ?? "dashboard";
if (!in_array($page, $allowedPages, true)) {
    $page = "dashboard";
}
$pageTitle = ucwords(str_replace("-", " ", $page));

require __DIR__ . "/../includes/customer-header.php";
require __DIR__ . "/pages/" . $page . ".php";
require __DIR__ . "/../includes/app-footer.php";
