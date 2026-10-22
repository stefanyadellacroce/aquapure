<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/bootstrap.php";

require_system_user(['Admin']);

redirect("portal/index.php?page=users");
