<?php
declare(strict_types=1);

require_once __DIR__ . "/includes/bootstrap.php";

if (is_system_user()) {
    log_activity(
        (int) current_account()["id"],
        "Logout",
        "System user signed out.",
    );
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), "", [
        "expires" => time() - 42000,
        "path" => $params["path"] ?: "/",
        "domain" => $params["domain"] ?: "",
        "secure" => (bool) $params["secure"],
        "httponly" => true,
        "samesite" => "Lax",
    ]);
}

session_destroy();
session_id("");
session_start();
session_regenerate_id(true);
flash("success", "You have been logged out safely.");
redirect("login.php");
