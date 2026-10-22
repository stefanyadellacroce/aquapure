<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/bootstrap.php";

$orderId = positive_int($_GET["id"] ?? 0);
redirect("customer/index.php?page=order" . ($orderId ? "&id=" . $orderId : ""));
