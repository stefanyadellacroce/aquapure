<?php
declare(strict_types=1);

function url(string $path = ""): string
{
    return BASE_URL . ($path === "" ? "" : "/" . ltrim($path, "/"));
}

function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8",
    );
}

function redirect(string $path): never
{
    header(
        "Location: " . (str_starts_with($path, "http") ? $path : url($path)),
    );
    exit();
}

function is_post(): bool
{
    return ($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST";
}

function csrf_token(): string
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' .
        e(csrf_token()) .
        '">';
}

function verify_csrf(): void
{
    $submitted = $_POST["csrf_token"] ?? "";
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        exit(
            "Your form session expired. Please go back, refresh the page, and try again."
        );
    }
}

function flash(string $type, string $message): void
{
    if ($type === "danger" && is_post()) {
        aq_remember_failed_form();
    }
    $_SESSION["flash"][] = ["type" => $type, "message" => $message];
}

function pull_flashes(): array
{
    $messages = $_SESSION["flash"] ?? [];
    unset($_SESSION["flash"]);
    return is_array($messages) ? $messages : [];
}

function old(string $key, string $default = ""): string
{
    return e($_SESSION["old"][$key] ?? $default);
}

function keep_old(array $input): void
{
    unset($input["password"], $input["current_password"], $input["confirm_password"], $input["csrf_token"], $input["invitation_code"]);
    $_SESSION["old"] = $input;
}

function clear_old(): void
{
    unset($_SESSION["old"]);
}

function current_account(): ?array
{
    static $resolved = false;
    static $account = null;

    if ($resolved) {
        return $account;
    }
    $resolved = true;

    $sessionAccount = $_SESSION["account"] ?? null;
    if (!is_array($sessionAccount)) {
        return null;
    }

    $id = positive_int($sessionAccount["id"] ?? 0);
    $type = $sessionAccount["type"] ?? "";
    if ($id === 0 || !in_array($type, ["customer", "user"], true)) {
        unset($_SESSION["account"], $_SESSION["cart"]);
        return null;
    }

    if ($type === "customer") {
        $statement = db()->prepare(
            "SELECT customer_id, full_name, email
             FROM customers
             WHERE customer_id = ? AND account_status = 'Active'
             LIMIT 1",
        );
        $statement->execute([$id]);
        $record = $statement->fetch();
        if (!$record) {
            unset($_SESSION["account"], $_SESSION["cart"]);
            return null;
        }

        $account = [
            "id" => (int) $record["customer_id"],
            "name" => $record["full_name"],
            "email" => $record["email"],
            "role" => "Customer",
            "type" => "customer",
        ];
    } else {
        $statement = db()->prepare(
            "SELECT user_id, full_name, email, role
             FROM users
             WHERE user_id = ? AND account_status = 'Active'
             LIMIT 1",
        );
        $statement->execute([$id]);
        $record = $statement->fetch();
        if (
            !$record ||
            !in_array($record["role"], ["Admin", "Staff"], true)
        ) {
            unset($_SESSION["account"], $_SESSION["cart"]);
            return null;
        }

        $account = [
            "id" => (int) $record["user_id"],
            "name" => $record["full_name"],
            "email" => $record["email"],
            "role" => $record["role"],
            "type" => "user",
        ];
    }

    $_SESSION["account"] = $account;
    return $account;
}

function is_customer(): bool
{
    return (current_account()["type"] ?? null) === "customer";
}

function is_system_user(): bool
{
    return (current_account()["type"] ?? null) === "user";
}

function is_admin(): bool
{
    return is_system_user() && (current_account()["role"] ?? null) === "Admin";
}

function require_customer(): void
{
    if (!is_customer()) {
        flash("warning", "Please log in as a customer to continue.");
        redirect("login.php");
    }
}

function require_system_user(array $roles = ["Admin", "Staff"]): void
{
    $account = current_account();
    if (
        ($account["type"] ?? null) !== "user" ||
        !in_array($account["role"] ?? "", $roles, true)
    ) {
        if ($account === null) {
            flash("warning", "Please log in to continue.");
            redirect("login.php");
        }

        http_response_code(403);
        exit("You are not authorized to access this page.");
    }
}

function input(string $key, string $default = ""): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function positive_int(mixed $value): int
{
    $validated = filter_var($value, FILTER_VALIDATE_INT, [
        "options" => ["min_range" => 1],
    ]);
    return $validated === false ? 0 : (int) $validated;
}

function valid_phone(string $phone): bool
{
    return (bool) preg_match(
        '/^(?:\+63|0)9\d{9}$/',
        preg_replace("/[\s-]/", "", $phone),
    );
}

function badge_class(string $status): string
{
    return match ($status) {
        "Paid",
        "Completed",
        "Delivered",
        "Available",
        "Active",
        "In Stock"
            => "text-bg-success",
        "Pending", "Unpaid", "Partial", "Low Stock" => "text-bg-warning",
        "Confirmed", "Preparing", "Out for Delivery", "Assigned" => "text-bg-info",
        "Ready for Pickup" => "text-bg-primary",
        "Cancelled",
        "Out of Stock",
        "Failed"
            => "text-bg-danger",
        "Inactive", "Unavailable", "Refunded" => "text-bg-secondary",
        default => "text-bg-secondary",
    };
}

function inventory_status(int $quantity, int $reorderLevel): string
{
    if ($quantity === 0) {
        return "Out of Stock";
    }
    return $quantity <= $reorderLevel ? "Low Stock" : "In Stock";
}

function money(float|string|int $amount): string
{
    return "₱" . number_format((float) $amount, 2);
}

/** @return array{station_name:string,station_contact:string,station_address:string,business_hours:string} */
function station_settings(): array
{
    static $settings = null;
    if (is_array($settings)) {
        return $settings;
    }

    $settings = [
        'station_name' => APP_NAME,
        'station_contact' => '',
        'station_address' => '',
        'business_hours' => '',
    ];

    try {
        $rows = db()->query(
            "SELECT setting_key, setting_value FROM settings
             WHERE setting_key IN ('station_name','station_contact','station_address','business_hours')",
        )->fetchAll();
        foreach ($rows as $row) {
            $key = (string) ($row['setting_key'] ?? '');
            if (array_key_exists($key, $settings)) {
                $settings[$key] = trim((string) ($row['setting_value'] ?? ''));
            }
        }
    } catch (Throwable $exception) {
        error_log('AquaPure settings lookup failed: ' . $exception->getMessage());
    }

    return $settings;
}

function log_activity(?int $userId, string $action, string $details = ""): void
{
    $statement = db()->prepare(
        "INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)",
    );
    $statement->execute([
        $userId,
        $action,
        $details,
        $_SERVER["REMOTE_ADDR"] ?? null,
    ]);
}

function notify_customer(
    int $customerId,
    string $title,
    string $message,
    ?int $orderId = null,
): void {
    $statement = db()->prepare(
        "INSERT INTO notifications (customer_id, order_id, title, message) VALUES (?, ?, ?, ?)",
    );
    $statement->execute([$customerId, $orderId, $title, $message]);
}

function cart_count(): int
{
    return array_sum(array_map("intval", $_SESSION["cart"] ?? []));
}

function cart_details(): array
{
    $cart = $_SESSION["cart"] ?? [];
    if (!is_array($cart) || $cart === []) {
        return ["items" => [], "total" => 0.0];
    }

    $ids = array_values(
        array_filter(array_map("positive_int", array_keys($cart))),
    );
    if ($ids === []) {
        return ["items" => [], "total" => 0.0];
    }

    $placeholders = implode(",", array_fill(0, count($ids), "?"));
    $statement = db()->prepare(
        "SELECT * FROM products WHERE product_id IN ({$placeholders})",
    );
    $statement->execute($ids);
    $products = $statement->fetchAll();
    $items = [];
    $total = 0.0;

    foreach ($products as $product) {
        $quantity = max(1, (int) ($cart[$product["product_id"]] ?? 1));
        $subtotal = $quantity * (float) $product["price"];
        $product["cart_quantity"] = $quantity;
        $product["subtotal"] = $subtotal;
        $items[] = $product;
        $total += $subtotal;
    }

    return ["items" => $items, "total" => $total];
}

function order_number(int $id): string
{
    return "AQ-" . str_pad((string) $id, 6, "0", STR_PAD_LEFT);
}

function store_product_image(array $upload): ?string
{
    $error = $upload["error"] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK || !isset($upload["tmp_name"])) {
        throw new RuntimeException("The product image could not be uploaded.");
    }
    if (($upload["size"] ?? 0) > 2 * 1024 * 1024) {
        throw new RuntimeException("Product images must be 2 MB or smaller.");
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload["tmp_name"]);
    $extensions = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp",
    ];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException("Use a JPG, PNG, or WebP product image.");
    }

    $directory = dirname(__DIR__) . "/assets/uploads/products";
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException("The product image folder is unavailable.");
    }

    $filename = "product-" . bin2hex(random_bytes(16)) . "." . $extensions[$mime];
    $destination = $directory . "/" . $filename;
    if (!move_uploaded_file($upload["tmp_name"], $destination)) {
        throw new RuntimeException("The product image could not be saved.");
    }

    return "assets/uploads/products/" . $filename;
}
