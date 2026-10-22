<?php
declare(strict_types=1);

/**
 * PayMongo hosted checkout helpers.
 *
 * Keep PAYMONGO_SECRET_KEY in Apache/PHP environment variables, never in this
 * repository or client-side JavaScript. Test keys begin with sk_test_ and live
 * keys begin with sk_live_.
 */
function paymongo_secret_key(): string
{
    return trim((string) (getenv("PAYMONGO_SECRET_KEY") ?: ""));
}

function paymongo_is_configured(): bool
{
    $key = paymongo_secret_key();
    if (!preg_match('/^sk_(test|live)_[A-Za-z0-9_]+$/', $key)) {
        return false;
    }

    if (str_starts_with($key, "sk_live_") && !str_starts_with(APP_URL, "https://")) {
        return false;
    }

    return function_exists("curl_init");
}

function paymongo_configuration_error(): string
{
    if (!function_exists("curl_init")) {
        return "Online payments are temporarily unavailable. Please contact the station.";
    }

    if (str_starts_with(paymongo_secret_key(), "sk_live_") && !str_starts_with(APP_URL, "https://")) {
        return "Live online payments require a secure HTTPS station URL.";
    }

    return "Online payments are not configured yet. Please choose another payment method or contact the station.";
}

/** @return array<string, mixed> */
function paymongo_request(string $method, string $path, ?array $payload = null): array
{
    if (!paymongo_is_configured()) {
        throw new RuntimeException(paymongo_configuration_error());
    }

    $curl = curl_init("https://api.paymongo.com/v1" . $path);
    if ($curl === false) {
        throw new RuntimeException("Unable to start secure online checkout.");
    }

    $headers = ["Accept: application/json"];
    if ($payload !== null) {
        $headers[] = "Content-Type: application/json";
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_USERPWD => paymongo_secret_key() . ":",
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_POSTFIELDS => $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR),
    ]);

    $raw = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if (!is_string($raw) || $error !== "" || $status < 200 || $status >= 300) {
        error_log("PayMongo request failed (HTTP " . $status . "): " . $error);
        throw new RuntimeException(
            "Unable to start secure online checkout. Please try again.",
            $status > 0 ? $status : 0,
        );
    }

    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        error_log("PayMongo returned an invalid response.");
        throw new RuntimeException("Unable to verify the online payment. Please try again.");
    }

    if (!is_array($decoded)) {
        throw new RuntimeException("Unable to verify the online payment. Please try again.");
    }

    return $decoded;
}

/** @return array<string, mixed> */
function paymongo_order_data(int $orderId, ?int $customerId = null): array
{
    $sql = "SELECT o.*, c.full_name, c.email, c.contact_number, p.payment_id, p.amount_due, p.amount_paid,
                   p.payment_method, p.payment_status, p.reference_number
            FROM orders o
            JOIN customers c ON c.customer_id = o.customer_id
            JOIN payments p ON p.order_id = o.order_id
            WHERE o.order_id = ?";
    $params = [$orderId];
    if ($customerId !== null) {
        $sql .= " AND o.customer_id = ?";
        $params[] = $customerId;
    }

    $statement = db()->prepare($sql);
    $statement->execute($params);
    $order = $statement->fetch();
    if (!$order) {
        throw new RuntimeException("Order not found.");
    }

    return $order;
}

/** @return list<array<string, mixed>> */
function paymongo_order_items(int $orderId): array
{
    $items = db()->prepare(
        "SELECT oi.quantity, oi.unit_price, p.product_name, p.description
         FROM order_items oi JOIN products p ON p.product_id = oi.product_id
         WHERE oi.order_id = ?",
    );
    $items->execute([$orderId]);
    return $items->fetchAll();
}

/**
 * A checkout URL is deliberately kept only in the customer's server-side PHP
 * session. It is short lived so a second tab can continue the same checkout
 * without turning a repeat click into a second chargeable session.
 */
const PAYMONGO_CHECKOUT_SESSION_TTL = 1800;

function paymongo_checkout_session_id_is_safe(mixed $sessionId): bool
{
    return is_string($sessionId) &&
        preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,99}$/D', $sessionId) === 1;
}

function paymongo_checkout_url_is_safe(mixed $checkoutUrl): bool
{
    if (!is_string($checkoutUrl) || strlen($checkoutUrl) > 2048) {
        return false;
    }

    $parts = parse_url($checkoutUrl);
    return is_array($parts) &&
        ($parts["scheme"] ?? "") === "https" &&
        isset($parts["host"]) &&
        is_string($parts["host"]) &&
        $parts["host"] !== "" &&
        !isset($parts["user"], $parts["pass"]);
}

function paymongo_forget_checkout_session(int $orderId): void
{
    if (isset($_SESSION["paymongo_checkout_sessions"]) && is_array($_SESSION["paymongo_checkout_sessions"])) {
        unset($_SESSION["paymongo_checkout_sessions"][(string) $orderId]);
    }
}

/** @return array{session_id:string,checkout_url:string,created_at:int}|null */
function paymongo_recent_checkout_session(int $orderId, int $customerId): ?array
{
    $sessions = $_SESSION["paymongo_checkout_sessions"] ?? null;
    $state = is_array($sessions) ? ($sessions[(string) $orderId] ?? null) : null;
    if (!is_array($state)) {
        return null;
    }

    $sessionId = $state["session_id"] ?? null;
    $checkoutUrl = $state["checkout_url"] ?? null;
    $createdAt = $state["created_at"] ?? null;
    $storedCustomerId = $state["customer_id"] ?? null;
    $now = time();
    $valid = is_int($createdAt) &&
        $createdAt > 0 &&
        $createdAt <= $now + 60 &&
        $createdAt + PAYMONGO_CHECKOUT_SESSION_TTL >= $now &&
        (string) $storedCustomerId === (string) $customerId &&
        paymongo_checkout_session_id_is_safe($sessionId) &&
        paymongo_checkout_url_is_safe($checkoutUrl);

    if (!$valid) {
        paymongo_forget_checkout_session($orderId);
        return null;
    }

    return [
        "session_id" => $sessionId,
        "checkout_url" => $checkoutUrl,
        "created_at" => $createdAt,
    ];
}

function paymongo_remember_checkout_session(
    int $orderId,
    int $customerId,
    string $sessionId,
    string $checkoutUrl,
): void {
    if (!paymongo_checkout_session_id_is_safe($sessionId) || !paymongo_checkout_url_is_safe($checkoutUrl)) {
        return;
    }

    if (!isset($_SESSION["paymongo_checkout_sessions"]) || !is_array($_SESSION["paymongo_checkout_sessions"])) {
        $_SESSION["paymongo_checkout_sessions"] = [];
    }

    $_SESSION["paymongo_checkout_sessions"][(string) $orderId] = [
        "customer_id" => $customerId,
        "session_id" => $sessionId,
        "checkout_url" => $checkoutUrl,
        "created_at" => time(),
    ];
}

/**
 * Get a per-order MySQL advisory lock. PHP's normal session lock protects two
 * tabs in one session; this also closes the small gap for another active
 * session for the same account before the payment reference is committed.
 */
function paymongo_checkout_lock(int $orderId): string
{
    $lockName = "aquapure_paymongo_" . $orderId;
    $statement = db()->prepare("SELECT GET_LOCK(?, 30) AS lock_acquired");
    $statement->execute([$lockName]);
    $locked = $statement->fetch();
    if (!$locked || (int) ($locked["lock_acquired"] ?? 0) !== 1) {
        throw new RuntimeException("Another online checkout is being prepared. Please try again shortly.");
    }

    return $lockName;
}

function paymongo_release_checkout_lock(string $lockName): void
{
    try {
        db()->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
    } catch (Throwable $exception) {
        error_log("PayMongo checkout lock release failed: " . $exception->getMessage());
    }
}

function paymongo_clear_payment_reference(int $orderId, string $sessionId): void
{
    db()
        ->prepare(
            "UPDATE payments SET reference_number = NULL
             WHERE order_id = ? AND payment_method = 'PayMongo' AND reference_number = ?",
        )
        ->execute([$orderId, $sessionId]);
}

/**
 * Reuse an already-recorded PayMongo session when a checkout is resumed after
 * the local cache expires. The API response must identify the same session and
 * the same order reference before its URL is trusted.
 */
function paymongo_recorded_checkout_url(
    int $orderId,
    int $customerId,
    string $sessionId,
): ?string {
    try {
        $session = paymongo_request(
            "GET",
            "/checkout_sessions/" . rawurlencode($sessionId),
        );
    } catch (RuntimeException $exception) {
        if ($exception->getCode() === 404) {
            paymongo_forget_checkout_session($orderId);
            paymongo_clear_payment_reference($orderId, $sessionId);
            return null;
        }

        throw new RuntimeException(
            "Unable to verify the existing online checkout. Please try again.",
            0,
            $exception,
        );
    }

    $returnedId = $session["data"]["id"] ?? null;
    $attributes = $session["data"]["attributes"] ?? null;
    if (!is_string($returnedId) || !hash_equals($sessionId, $returnedId) || !is_array($attributes)) {
        paymongo_forget_checkout_session($orderId);
        throw new RuntimeException("Unable to verify the existing online checkout. Please try again.");
    }

    $remoteReference = $attributes["reference_number"] ?? null;
    if (!is_string($remoteReference) || !hash_equals(order_number($orderId), $remoteReference)) {
        // A non-matching session cannot be used for this order, even if a
        // customer previously submitted text that looked like a session ID.
        paymongo_forget_checkout_session($orderId);
        paymongo_clear_payment_reference($orderId, $sessionId);
        return null;
    }

    $checkoutStatus = strtolower(trim((string) ($attributes["status"] ?? "")));
    if (in_array($checkoutStatus, ["expired", "cancelled", "canceled"], true)) {
        paymongo_forget_checkout_session($orderId);
        paymongo_clear_payment_reference($orderId, $sessionId);
        return null;
    }

    $intentStatus = paymongo_payment_intent_status($session);
    if (in_array($intentStatus, ["succeeded", "paid"], true)) {
        paymongo_forget_checkout_session($orderId);
        paymongo_sync_order_payment($orderId, $customerId);
        throw new RuntimeException("This order has already been paid.");
    }

    $checkoutUrl = $attributes["checkout_url"] ?? null;
    if (!paymongo_checkout_url_is_safe($checkoutUrl)) {
        paymongo_forget_checkout_session($orderId);
        throw new RuntimeException("Unable to verify the existing online checkout. Please try again.");
    }

    paymongo_remember_checkout_session($orderId, $customerId, $sessionId, $checkoutUrl);
    return $checkoutUrl;
}

function paymongo_checkout_url(int $orderId, int $customerId): string
{
    $lockName = paymongo_checkout_lock($orderId);
    try {
        $order = paymongo_order_data($orderId, $customerId);
        if ($order["payment_method"] !== "PayMongo") {
            throw new RuntimeException("This order does not use online payment.");
        }
        if ($order["payment_status"] === "Paid") {
            paymongo_forget_checkout_session($orderId);
            throw new RuntimeException("This order has already been paid.");
        }
        if ($order["order_status"] === "Cancelled") {
            paymongo_forget_checkout_session($orderId);
            throw new RuntimeException("A cancelled order cannot be paid online.");
        }

        $recentSession = paymongo_recent_checkout_session($orderId, $customerId);
        $reference = trim((string) ($order["reference_number"] ?? ""));
        if ($recentSession !== null && hash_equals($reference, $recentSession["session_id"])) {
            return $recentSession["checkout_url"];
        }
        if ($recentSession !== null) {
            paymongo_forget_checkout_session($orderId);
        }

        if ($reference !== "" && paymongo_checkout_session_id_is_safe($reference)) {
            $recordedUrl = paymongo_recorded_checkout_url($orderId, $customerId, $reference);
            if ($recordedUrl !== null) {
                return $recordedUrl;
            }
            $order = paymongo_order_data($orderId, $customerId);
            $reference = trim((string) ($order["reference_number"] ?? ""));
        } elseif ($reference !== "") {
            // PayMongo references are server generated. Discard an old or
            // user-supplied value before creating the first real session.
            db()
                ->prepare(
                    "UPDATE payments SET reference_number = NULL
                     WHERE order_id = ? AND payment_method = 'PayMongo' AND reference_number = ?",
                )
                ->execute([$orderId, $reference]);
            $reference = "";
        }

        if ($reference !== "") {
            throw new RuntimeException("Unable to verify the existing online checkout. Please try again.");
        }

        $lineItems = [];
        foreach (paymongo_order_items($orderId) as $item) {
            $lineItems[] = [
                "amount" => (int) round((float) $item["unit_price"] * 100),
                "currency" => "PHP",
                "name" => mb_substr((string) $item["product_name"], 0, 120),
                "quantity" => (int) $item["quantity"],
            ];
        }
        if ($lineItems === []) {
            throw new RuntimeException("This order has no items to pay for.");
        }

        $returnUrl = APP_URL . "/customer/index.php?page=payment-return&id=" . $orderId;
        $payload = [
            "data" => [
                "attributes" => [
                    "cancel_url" => $returnUrl,
                    "description" => "AquaPure water order " . order_number($orderId),
                    "line_items" => $lineItems,
                    "merchant" => APP_NAME,
                    "payment_method_types" => ["card", "gcash", "paymaya"],
                    "reference_number" => order_number($orderId),
                    "send_email_receipt" => false,
                    "show_description" => true,
                    "show_line_items" => true,
                    "success_url" => $returnUrl,
                ],
            ],
        ];

        $response = paymongo_request("POST", "/checkout_sessions", $payload);
        $sessionId = $response["data"]["id"] ?? null;
        $checkoutUrl = $response["data"]["attributes"]["checkout_url"] ?? null;
        if (!paymongo_checkout_session_id_is_safe($sessionId) || !paymongo_checkout_url_is_safe($checkoutUrl)) {
            error_log("PayMongo checkout response was missing session details.");
            throw new RuntimeException("Unable to start secure online checkout. Please try again.");
        }

        $saved = db()
            ->prepare(
                "UPDATE payments SET reference_number = ?
                 WHERE order_id = ? AND payment_method = 'PayMongo'
                 AND payment_status <> 'Paid' AND (reference_number IS NULL OR reference_number = '')",
            );
        $saved->execute([$sessionId, $orderId]);
        if ($saved->rowCount() !== 1) {
            throw new RuntimeException("The order payment changed before checkout could start. Please reopen the order and try again.");
        }

        paymongo_remember_checkout_session($orderId, $customerId, $sessionId, $checkoutUrl);
        return $checkoutUrl;
    } finally {
        paymongo_release_checkout_lock($lockName);
    }
}

function paymongo_payment_intent_status(array $session): string
{
    $attributes = $session["data"]["attributes"] ?? [];
    if (!is_array($attributes)) {
        return "";
    }

    $intent = $attributes["payment_intent"] ?? null;
    if (is_array($intent)) {
        $intentAttributes = $intent["attributes"] ?? $intent;
        if (is_array($intentAttributes) && isset($intentAttributes["status"])) {
            return strtolower((string) $intentAttributes["status"]);
        }
    }

    $intentId = is_string($intent) ? $intent : ($attributes["payment_intent_id"] ?? null);
    if (!is_string($intentId) || $intentId === "") {
        return strtolower((string) ($attributes["status"] ?? ""));
    }

    $paymentIntent = paymongo_request("GET", "/payment_intents/" . rawurlencode($intentId));
    return strtolower((string) ($paymentIntent["data"]["attributes"]["status"] ?? ""));
}

/**
 * Refresh one order from PayMongo. This is idempotent and only accepts a paid
 * status returned by PayMongo's authenticated API.
 */
function paymongo_sync_order_payment(int $orderId, int $customerId): string
{
    $order = paymongo_order_data($orderId, $customerId);
    if ($order["payment_method"] !== "PayMongo" || $order["payment_status"] === "Paid") {
        if ($order["payment_status"] === "Paid") {
            paymongo_forget_checkout_session($orderId);
        }
        return (string) $order["payment_status"];
    }
    if ($order["order_status"] === "Cancelled" || !is_string($order["reference_number"]) || $order["reference_number"] === "") {
        if ($order["order_status"] === "Cancelled") {
            paymongo_forget_checkout_session($orderId);
        }
        return "unavailable";
    }

    $session = paymongo_request(
        "GET",
        "/checkout_sessions/" . rawurlencode($order["reference_number"]),
    );
    $status = paymongo_payment_intent_status($session);
    if (!in_array($status, ["succeeded", "paid"], true)) {
        return $status === "" ? "pending" : $status;
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $locked = $pdo->prepare(
            "SELECT o.payment_status, o.order_status, p.payment_status AS recorded_payment_status
             FROM orders o JOIN payments p ON p.order_id = o.order_id
             WHERE o.order_id = ? AND o.customer_id = ? FOR UPDATE",
        );
        $locked->execute([$orderId, $customerId]);
        $current = $locked->fetch();
        if (!$current || $current["order_status"] === "Cancelled") {
            $pdo->rollBack();
            return "unavailable";
        }
        if ($current["recorded_payment_status"] !== "Paid") {
            $pdo->prepare(
                "UPDATE payments SET amount_paid = amount_due, change_amount = 0, payment_status = 'Paid', payment_date = NOW() WHERE order_id = ?",
            )->execute([$orderId]);
            $pdo->prepare("UPDATE orders SET payment_status = 'Paid' WHERE order_id = ?")->execute([$orderId]);
            notify_customer(
                $customerId,
                "Online payment received",
                "Payment for " . order_number($orderId) . " was received through PayMongo.",
                $orderId,
            );
        }
        $pdo->commit();
        paymongo_forget_checkout_session($orderId);
        return "Paid";
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("PayMongo payment sync failed: " . $exception->getMessage());
        throw new RuntimeException("Unable to verify the online payment right now. Please check again shortly.");
    }
}
