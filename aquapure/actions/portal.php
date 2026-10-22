<?php
declare(strict_types=1);

if (!is_post()) {
    return;
}

verify_csrf();
$action = input("action");
unset($_SESSION["aq_failed_form"]);
$userId = (int) current_account()["id"];

if ($action === "save_customer") {
    $id = positive_int($_POST["customer_id"] ?? 0);
    $name = input("full_name");
    $email = strtolower(input("email")) ?: null;
    $phone = preg_replace("/[\s-]/", "", input("contact_number"));
    $address = input("address");
    $type = input("customer_type");
    $status = is_admin() ? input("account_status", "Active") : "Active";
    $errors = [];
    if (mb_strlen($name) < 3 || mb_strlen($name) > 100) {
        $errors[] = "Enter a customer name between 3 and 100 characters.";
    }
    if (
        $email !== null &&
        (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100)
    ) {
        $errors[] = "Enter a valid email of at most 100 characters, or leave it blank.";
    }
    if (!valid_phone($phone)) {
        $errors[] = "Enter a valid Philippine mobile number.";
    }
    if (mb_strlen($address) < 5 || mb_strlen($address) > 255) {
        $errors[] = "Enter a customer address between 5 and 255 characters.";
    }
    if (
        !in_array(
            $type,
            ["Online Customer", "Walk-in Customer", "Residential", "Business"],
            true,
        )
    ) {
        $errors[] = "Choose a valid customer type.";
    }
    if (!in_array($status, ["Active", "Inactive"], true)) {
        $status = "Active";
    }
    if ($email !== null) {
        $check = db()->prepare(
            "SELECT customer_id FROM customers WHERE email = ? AND customer_id <> ?",
        );
        $check->execute([$email, $id]);
        if ($check->fetch()) {
            $errors[] = "That customer email already exists.";
        }
        $systemEmail = db()->prepare(
            "SELECT user_id FROM users WHERE email = ? LIMIT 1",
        );
        $systemEmail->execute([$email]);
        if ($systemEmail->fetch()) {
            $errors[] = "That email address is already used by a system account.";
        }
    }
    if ($errors) {
        foreach ($errors as $error) {
            flash("danger", $error);
        }
        redirect(
            "portal/index.php?page=customers" . ($id ? "&edit=" . $id : ""),
        );
    }

    try {
        if ($id) {
            if (is_admin()) {
                $statement = db()->prepare(
                    "UPDATE customers SET full_name = ?, email = ?, contact_number = ?, address = ?, customer_type = ?, account_status = ? WHERE customer_id = ?",
                );
                $statement->execute([
                    $name,
                    $email,
                    $phone,
                    $address,
                    $type,
                    $status,
                    $id,
                ]);
            } else {
                // Staff may edit customer details but cannot activate/deactivate accounts.
                $statement = db()->prepare(
                    "UPDATE customers SET full_name = ?, email = ?, contact_number = ?, address = ?, customer_type = ? WHERE customer_id = ?",
                );
                $statement->execute([$name, $email, $phone, $address, $type, $id]);
            }
            if ($statement->rowCount() === 0) {
                $exists = db()->prepare("SELECT customer_id FROM customers WHERE customer_id = ?");
                $exists->execute([$id]);
                if (!$exists->fetch()) {
                    throw new RuntimeException("Customer record not found.");
                }
            }
            $message = "Customer record updated.";
        } else {
            $statement = db()->prepare(
                "INSERT INTO customers (full_name, email, contact_number, address, password_hash, customer_type, account_status) VALUES (?, ?, ?, ?, NULL, ?, 'Active')",
            );
            $statement->execute([$name, $email, $phone, $address, $type]);
            $id = (int) db()->lastInsertId();
            $message = "Customer record created.";
        }
        log_activity($userId, "Customer saved", "Customer ID " . $id);
        flash("success", $message);
    } catch (RuntimeException $exception) {
        flash("warning", $exception->getMessage());
    } catch (PDOException $exception) {
        error_log("AquaPure customer save failed: " . $exception->getMessage());
        flash("danger", "The customer record could not be saved. Check for a duplicate email and try again.");
    }
    redirect("portal/index.php?page=customers" . (aq_form_retry("save_customer", $id) && $id ? "&edit=" . $id : ""));
}

if ($action === "toggle_customer") {
    if (!is_admin()) {
        http_response_code(403);
        exit("Admin access required.");
    }
    $id = positive_int($_POST["id"] ?? 0);
    if (!$id) {
        flash("danger", "Invalid customer account.");
        redirect("portal/index.php?page=customers");
    }
    $statement = db()->prepare(
        "UPDATE customers SET account_status = IF(account_status = 'Active', 'Inactive', 'Active') WHERE customer_id = ?",
    );
    $statement->execute([$id]);
    if ($statement->rowCount() !== 1) {
        flash("warning", "Customer account not found.");
        redirect("portal/index.php?page=customers");
    }
    log_activity($userId, "Customer status changed", "Customer ID " . $id);
    flash("success", "Customer account status updated.");
    redirect("portal/index.php?page=customers");
}

if ($action === "save_product") {
    if (!is_admin()) {
        http_response_code(403);
        exit("Admin access required.");
    }
    $id = positive_int($_POST["product_id"] ?? 0);
    $name = input("product_name");
    $category = input("category");
    $description = input("description");
    $price = filter_var($_POST["price"] ?? null, FILTER_VALIDATE_FLOAT);
    $quantity = filter_var(
        $_POST["quantity_available"] ?? null,
        FILTER_VALIDATE_INT,
    );
    $status = input("status");
    try {
        $imagePath = store_product_image(
            is_array($_FILES["product_image"] ?? null)
                ? $_FILES["product_image"]
                : [],
        );
    } catch (RuntimeException $exception) {
        flash("danger", $exception->getMessage());
        redirect(
            "portal/index.php?page=products" . ($id ? "&edit=" . $id : ""),
        );
    }
    if (
        $name === "" ||
        mb_strlen($name) > 120 ||
        mb_strlen($description) > 255 ||
        !in_array(
            $category,
            ["Refill", "Bottled Water", "Container", "Accessory"],
            true,
        ) ||
        $price === false ||
        !is_finite((float) $price) ||
        $price < 0 ||
        $quantity === false ||
        $quantity < 0 ||
        !in_array($status, ["Available", "Unavailable"], true)
    ) {
        flash(
            "danger",
            "Enter valid product details, price, quantity, and status.",
        );
        redirect(
            "portal/index.php?page=products" . ($id ? "&edit=" . $id : ""),
        );
    }
    try {
    if ($id) {
        if ($imagePath !== null) {
            $statement = db()->prepare(
                "UPDATE products SET product_name = ?, description = ?, category = ?, price = ?, quantity_available = ?, status = ?, image_path = ? WHERE product_id = ?",
            );
            $statement->execute([
                $name,
                $description,
                $category,
                $price,
                $quantity,
                $status,
                $imagePath,
                $id,
            ]);
        } else {
            $statement = db()->prepare(
                "UPDATE products SET product_name = ?, description = ?, category = ?, price = ?, quantity_available = ?, status = ? WHERE product_id = ?",
            );
            $statement->execute([
                $name,
                $description,
                $category,
                $price,
                $quantity,
                $status,
                $id,
            ]);
        }
    } else {
        $statement = db()->prepare(
            "INSERT INTO products (product_name, description, category, price, quantity_available, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?)",
        );
        $statement->execute([
            $name,
            $description,
            $category,
            $price,
            $quantity,
            $imagePath,
            $status,
        ]);
        $id = (int) db()->lastInsertId();
    }
    log_activity($userId, "Product saved", "Product ID " . $id);
    flash("success", "Product saved successfully.");
    } catch (PDOException $exception) {
        error_log("AquaPure product save failed: " . $exception->getMessage());
        flash("danger", "The product could not be saved. Product names must be unique.");
    }
    redirect("portal/index.php?page=products" . (aq_form_retry("save_product", $id) && $id ? "&edit=" . $id : ""));
}

if ($action === "delete_product") {
    if (!is_admin()) {
        http_response_code(403);
        exit("Admin access required.");
    }
    $id = positive_int($_POST["id"] ?? 0);
    if (!$id) {
        flash("danger", "Invalid product.");
        redirect("portal/index.php?page=products");
    }
    $product = db()->prepare("SELECT product_id FROM products WHERE product_id = ?");
    $product->execute([$id]);
    if (!$product->fetch()) {
        flash("warning", "Product not found.");
        redirect("portal/index.php?page=products");
    }
    $check = db()->prepare(
        "SELECT COUNT(*) FROM order_items WHERE product_id = ?",
    );
    $check->execute([$id]);
    if ((int) $check->fetchColumn() > 0) {
        db()
            ->prepare(
                "UPDATE products SET status = 'Unavailable' WHERE product_id = ?",
            )
            ->execute([$id]);
        flash(
            "warning",
            "The product has order history, so it was marked unavailable instead of deleted.",
        );
        $auditAction = "Product marked unavailable";
    } else {
        db()
            ->prepare("DELETE FROM products WHERE product_id = ?")
            ->execute([$id]);
        flash("success", "Product deleted.");
        $auditAction = "Product deleted";
    }
    log_activity($userId, $auditAction, "Product ID " . $id);
    redirect("portal/index.php?page=products");
}

if ($action === "update_product_availability") {
    if (!is_admin()) {
        http_response_code(403);
        exit("Admin access required.");
    }
    $id = positive_int($_POST["product_id"] ?? 0);
    $status = input("status");
    if (!$id || !in_array($status, ["Available", "Unavailable"], true)) {
        flash("danger", "Invalid product status.");
        redirect("portal/index.php?page=products");
    }
    try {
        $statement = db()->prepare(
            "UPDATE products SET status = ? WHERE product_id = ?",
        );
        $statement->execute([$status, $id]);
        if ($statement->rowCount() !== 1) {
            flash("warning", "Product was not found or already has that availability.");
        } else {
            log_activity(
                $userId,
                "Product availability updated",
                "Product ID " . $id . " set to " . $status,
            );
            flash("success", "Product availability updated.");
        }
    } catch (Throwable $exception) {
        error_log("AquaPure product availability update failed: " . $exception->getMessage());
        flash("danger", "The product availability could not be updated.");
    }
    redirect("portal/index.php?page=products");
}

if ($action === "create_walkin_order") {
    $customerId = positive_int($_POST["customer_id"] ?? 0);
    $productIds = $_POST["product_id"] ?? [];
    $quantities = $_POST["quantity"] ?? [];
    $paymentMethod = input("payment_method");
    $amountPaid = filter_var($_POST["amount_paid"] ?? null, FILTER_VALIDATE_FLOAT);
    $reference = input("reference_number") ?: null;
    if (
        !$customerId ||
        !is_array($productIds) ||
        !is_array($quantities) ||
        !in_array($paymentMethod, ["Cash", "GCash", "Maya"], true) ||
        $amountPaid === false ||
        !is_finite((float) $amountPaid) ||
        $amountPaid < 0 ||
        ($reference !== null && mb_strlen($reference) > 100) ||
        (in_array($paymentMethod, ["GCash", "Maya"], true) && !$reference)
    ) {
        flash("danger", "Complete the walk-in order form.");
        redirect("portal/index.php?page=order-create");
    }
    $amountPaid = round((float) $amountPaid, 2);

    $requestedQuantities = [];
    foreach ($productIds as $index => $rawId) {
        $rawQuantity = $quantities[$index] ?? null;
        if (
            ($rawId !== null && !is_scalar($rawId)) ||
            ($rawQuantity !== null && !is_scalar($rawQuantity))
        ) {
            flash("danger", "Each selected product needs a valid quantity.");
            redirect("portal/index.php?page=order-create");
        }
        $productId = positive_int($rawId);
        $quantity = positive_int($rawQuantity);
        if (
            !$productId &&
            ($rawId === null || (is_string($rawId) && trim($rawId) === ""))
        ) {
            continue;
        }
        if (!$productId || !$quantity) {
            flash("danger", "Each selected product needs a positive quantity.");
            redirect("portal/index.php?page=order-create");
        }
        $requestedQuantities[$productId] =
            ($requestedQuantities[$productId] ?? 0) + $quantity;
    }
    if ($requestedQuantities === []) {
        flash("danger", "Add at least one product to the walk-in order.");
        redirect("portal/index.php?page=order-create");
    }
    ksort($requestedQuantities, SORT_NUMERIC);

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $customer = $pdo->prepare(
            "SELECT customer_id FROM customers WHERE customer_id = ? AND account_status = 'Active' FOR UPDATE",
        );
        $customer->execute([$customerId]);
        if (!$customer->fetch()) {
            throw new RuntimeException("Select an active, registered customer.");
        }

        $lines = [];
        $total = 0.0;
        $lock = $pdo->prepare(
            "SELECT * FROM products WHERE product_id = ? AND status = 'Available' FOR UPDATE",
        );
        foreach ($requestedQuantities as $productId => $quantity) {
            $lock->execute([$productId]);
            $product = $lock->fetch();
            if (!$product || (int) $product["quantity_available"] < $quantity) {
                throw new RuntimeException(
                    "One selected product has insufficient stock.",
                );
            }
            $product["order_quantity"] = $quantity;
            $product["line_total"] = $quantity * (float) $product["price"];
            $total += $product["line_total"];
            $lines[] = $product;
        }
        if (
            in_array($paymentMethod, ["GCash", "Maya"], true) &&
            $amountPaid > $total
        ) {
            throw new RuntimeException(
                "GCash and Maya payments cannot exceed the order total.",
            );
        }
        $paymentStatus =
            $amountPaid <= 0
                ? "Unpaid"
                : ($amountPaid < $total
                    ? "Partial"
                    : "Paid");
        $change =
            $paymentMethod === "Cash" ? max(0, $amountPaid - $total) : 0;
        $pdo->prepare(
            "INSERT INTO orders (customer_id, processed_by, order_source, order_type, total_amount, payment_status, order_status) VALUES (?, ?, 'Walk-in', 'Walk-in', ?, ?, 'Completed')",
        )->execute([$customerId, $userId, $total, $paymentStatus]);
        $orderId = (int) $pdo->lastInsertId();
        $insert = $pdo->prepare(
            "INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)",
        );
        $stock = $pdo->prepare(
            "UPDATE products SET quantity_available = quantity_available - ? WHERE product_id = ?",
        );
        foreach ($lines as $line) {
            $insert->execute([
                $orderId,
                $line["product_id"],
                $line["order_quantity"],
                $line["price"],
                $line["line_total"],
            ]);
            $stock->execute([$line["order_quantity"], $line["product_id"]]);
        }
        $pdo->prepare(
            "INSERT INTO payments (order_id, amount_due, amount_paid, change_amount, payment_method, reference_number, payment_date, payment_status, recorded_by) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)",
        )->execute([
            $orderId,
            $total,
            $amountPaid,
            $change,
            $paymentMethod,
            $reference,
            $paymentStatus,
            $userId,
        ]);
        notify_customer(
            $customerId,
            "Walk-in purchase recorded",
            "Transaction " . order_number($orderId) . " was recorded.",
            $orderId,
        );
        log_activity($userId, "Walk-in order created", "Order ID " . $orderId);
        $pdo->commit();
        flash("success", "Walk-in order " . order_number($orderId) . " saved.");
        redirect("portal/index.php?page=order&id=" . $orderId);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash(
            "danger",
            $exception instanceof RuntimeException
                ? $exception->getMessage()
                : "The walk-in order could not be saved.",
        );
        redirect("portal/index.php?page=order-create");
    }
}

if ($action === "update_order_status") {
    $id = positive_int($_POST["order_id"] ?? 0);
    $status = input("order_status");
    $allowed = [
        "Pending",
        "Confirmed",
        "Preparing",
        "Ready for Pickup",
        "Out for Delivery",
        "Delivered",
        "Completed",
        "Cancelled",
    ];
    if (!$id || !in_array($status, $allowed, true)) {
        flash("danger", "Invalid order status.");
        redirect("portal/index.php?page=order&id=" . $id);
    }
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $order = $pdo->prepare(
            "SELECT customer_id, order_type, order_status, payment_status FROM orders WHERE order_id = ? FOR UPDATE",
        );
        $order->execute([$id]);
        $record = $order->fetch();
        if (!$record) {
            throw new RuntimeException("Order not found.");
        }

        $progressions = [
            "Walk-in" => ["Completed"],
            "Pickup" => [
                "Pending",
                "Confirmed",
                "Preparing",
                "Ready for Pickup",
                "Completed",
            ],
            "Delivery" => [
                "Pending",
                "Confirmed",
                "Preparing",
                "Out for Delivery",
                "Delivered",
                "Completed",
            ],
        ];
        $orderType = (string) $record["order_type"];
        $progression = $progressions[$orderType] ?? null;
        if ($progression === null) {
            throw new RuntimeException("This order has an unsupported order type.");
        }

        $delivery = null;
        if ($orderType === "Delivery") {
            $deliveryStatement = $pdo->prepare(
                "SELECT delivery_id, delivery_status FROM deliveries WHERE order_id = ? FOR UPDATE",
            );
            $deliveryStatement->execute([$id]);
            $delivery = $deliveryStatement->fetch();
            if (!$delivery) {
                throw new RuntimeException("The delivery record for this order is missing.");
            }
        }

        $currentStatus = (string) $record["order_status"];
        $currentIndex = array_search($currentStatus, $progression, true);
        if ($currentIndex === false) {
            throw new RuntimeException("This order has an invalid current status.");
        }
        if ($currentStatus === $status) {
            throw new RuntimeException("This order is already " . $status . ".");
        }
        if (in_array($currentStatus, ["Cancelled", "Completed"], true)) {
            throw new RuntimeException("A final order cannot be changed.");
        }

        if ($status === "Cancelled") {
            if (
                $delivery !== null &&
                (!in_array(
                    $delivery["delivery_status"],
                    ["Pending", "Confirmed", "Preparing", "Out for Delivery"],
                    true,
                ) ||
                    $delivery["delivery_status"] !== $currentStatus)
            ) {
                throw new RuntimeException(
                    "This delivery is already final or out of sync and cannot be cancelled safely.",
                );
            }

            $payment = $pdo->prepare(
                "SELECT payment_id, payment_method, payment_status FROM payments WHERE order_id = ? FOR UPDATE",
            );
            $payment->execute([$id]);
            $paymentRecord = $payment->fetch();
            if (!$paymentRecord) {
                throw new RuntimeException("The payment record for this order is missing.");
            }
            if (
                $paymentRecord["payment_method"] === "PayMongo" &&
                $paymentRecord["payment_status"] === "Paid"
            ) {
                throw new RuntimeException(
                    "A paid PayMongo order must be refunded through the payment provider before it can be cancelled here.",
                );
            }

            $items = $pdo->prepare(
                "SELECT product_id, quantity FROM order_items WHERE order_id = ? ORDER BY product_id FOR UPDATE",
            );
            $items->execute([$id]);
            $restoreQuantities = [];
            foreach ($items->fetchAll() as $item) {
                $productId = (int) $item["product_id"];
                $restoreQuantities[$productId] =
                    ($restoreQuantities[$productId] ?? 0) + (int) $item["quantity"];
            }
            if ($restoreQuantities === []) {
                throw new RuntimeException("The order has no items to restore.");
            }
            ksort($restoreQuantities, SORT_NUMERIC);
            $restore = $pdo->prepare(
                "UPDATE products SET quantity_available = quantity_available + ? WHERE product_id = ?",
            );
            foreach ($restoreQuantities as $productId => $quantity) {
                $restore->execute([$quantity, $productId]);
                if ($restore->rowCount() !== 1) {
                    throw new RuntimeException("A product needed for this cancellation was not found.");
                }
            }

            $pdo->prepare(
                "UPDATE payments SET payment_status = IF(payment_status = 'Paid', 'Refunded', payment_status) WHERE payment_id = ?",
            )->execute([(int) $paymentRecord["payment_id"]]);
            if ($delivery !== null) {
                $pdo->prepare(
                    "UPDATE deliveries SET delivery_status = 'Cancelled' WHERE delivery_id = ?",
                )->execute([(int) $delivery["delivery_id"]]);
            }
            $pdo->prepare(
                "UPDATE orders SET order_status = 'Cancelled', processed_by = ?, payment_status = IF(payment_status = 'Paid', 'Refunded', payment_status) WHERE order_id = ?",
            )->execute([$userId, $id]);
        } else {
            $nextIndex = array_search($status, $progression, true);
            if ($nextIndex === false) {
                throw new RuntimeException(
                    "That status does not apply to this " . strtolower($orderType) . " order.",
                );
            }
            if ($nextIndex !== $currentIndex + 1) {
                throw new RuntimeException("Orders must move forward one stage at a time.");
            }

            if ($delivery !== null) {
                // deliveries.delivery_status intentionally ends at Delivered;
                // the order itself may then move from Delivered to Completed.
                if ($status === "Completed") {
                    if (
                        $currentStatus !== "Delivered" ||
                        $delivery["delivery_status"] !== "Delivered"
                    ) {
                        throw new RuntimeException(
                            "A delivery order can be completed only after it is delivered.",
                        );
                    }
                } else {
                    if ($delivery["delivery_status"] !== $currentStatus) {
                        throw new RuntimeException(
                            "The delivery status is out of sync. Update it before changing the order.",
                        );
                    }
                    $pdo->prepare(
                        "UPDATE deliveries
                         SET delivery_status = ?,
                             delivered_at = IF(? = 'Delivered', COALESCE(delivered_at, NOW()), delivered_at)
                         WHERE delivery_id = ?",
                    )->execute([$status, $status, (int) $delivery["delivery_id"]]);
                }
            }
            $pdo->prepare(
                "UPDATE orders SET order_status = ?, processed_by = ? WHERE order_id = ?",
            )->execute([$status, $userId, $id]);
        }

        notify_customer(
            (int) $record["customer_id"],
            "Order status updated",
            order_number($id) . " is now " . $status . ".",
            $id,
        );
        log_activity(
            $userId,
            "Order status updated",
            "Order ID " . $id . " set to " . $status,
        );
        $pdo->commit();
        flash("success", "Order status updated.");
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (!($exception instanceof RuntimeException)) {
            error_log("AquaPure order status update failed: " . $exception->getMessage());
        }
        flash(
            "danger",
            $exception instanceof RuntimeException
                ? $exception->getMessage()
                : "The order status could not be saved. Please try again.",
        );
    }
    redirect("portal/index.php?page=order&id=" . $id);
}

if ($action === "record_payment") {
    $orderId = positive_int($_POST["order_id"] ?? 0);
    $method = input("payment_method");
    $amountPaid = filter_var($_POST["amount_paid"] ?? null, FILTER_VALIDATE_FLOAT);
    $reference = input("reference_number") ?: null;
    if (
        !$orderId ||
        !in_array($method, ["Cash", "GCash", "Maya"], true) ||
        $amountPaid === false ||
        !is_finite((float) $amountPaid) ||
        $amountPaid < 0 ||
        ($reference !== null && mb_strlen($reference) > 100)
    ) {
        flash("danger", "Enter valid payment details.");
        redirect("portal/index.php?page=order&id=" . $orderId);
    }
    $amountPaid = round((float) $amountPaid, 2);
    if ($method !== "Cash" && !$reference) {
        flash("danger", "Enter the e-wallet reference number.");
        redirect("portal/index.php?page=order&id=" . $orderId);
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $order = $pdo->prepare(
            "SELECT customer_id, order_status, payment_status FROM orders WHERE order_id = ? FOR UPDATE",
        );
        $order->execute([$orderId]);
        $orderRecord = $order->fetch();
        if (!$orderRecord) {
            throw new RuntimeException("Order not found.");
        }
        if ($orderRecord["order_status"] === "Cancelled") {
            throw new RuntimeException("Payments cannot be changed after an order is cancelled.");
        }
        if ($orderRecord["payment_status"] === "Refunded") {
            throw new RuntimeException("Refunded payments cannot be changed manually.");
        }

        $payment = $pdo->prepare(
            "SELECT payment_id, amount_due, payment_method, payment_status FROM payments WHERE order_id = ? FOR UPDATE",
        );
        $payment->execute([$orderId]);
        $paymentRecord = $payment->fetch();
        if (!$paymentRecord) {
            throw new RuntimeException("Payment record not found.");
        }
        if (
            $paymentRecord["payment_status"] === "Refunded" ||
            $paymentRecord["payment_method"] === "PayMongo"
        ) {
            throw new RuntimeException(
                "This payment is managed by a completed refund or online checkout and cannot be changed manually.",
            );
        }

        $due = (float) $paymentRecord["amount_due"];
        if ($due <= 0) {
            throw new RuntimeException("Payment record has an invalid amount due.");
        }
        if ($method !== "Cash" && $amountPaid > $due) {
            throw new RuntimeException("E-wallet payments cannot exceed the amount due.");
        }
        $status =
            $amountPaid <= 0
                ? "Unpaid"
                : ($amountPaid < $due ? "Partial" : "Paid");
        $change = $method === "Cash" ? max(0, $amountPaid - $due) : 0;

        $pdo->prepare(
            "UPDATE payments SET amount_paid = ?, change_amount = ?, payment_method = ?, reference_number = ?, payment_status = ?, payment_date = NOW(), recorded_by = ? WHERE payment_id = ?",
        )->execute([
            $amountPaid,
            $change,
            $method,
            $reference,
            $status,
            $userId,
            (int) $paymentRecord["payment_id"],
        ]);
        $pdo->prepare("UPDATE orders SET payment_status = ? WHERE order_id = ?")->execute([
            $status,
            $orderId,
        ]);
        notify_customer(
            (int) $orderRecord["customer_id"],
            "Payment updated",
            "Payment for " . order_number($orderId) . " is now " . $status . ".",
            $orderId,
        );
        log_activity(
            $userId,
            "Payment recorded",
            "Order ID " . $orderId . ", status " . $status,
        );
        $pdo->commit();
        flash("success", "Payment information saved.");
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (!($exception instanceof RuntimeException)) {
            error_log("AquaPure payment update failed: " . $exception->getMessage());
        }
        flash(
            "danger",
            $exception instanceof RuntimeException
                ? $exception->getMessage()
                : "The payment could not be saved. Please try again.",
        );
    }
    redirect("portal/index.php?page=order&id=" . $orderId);
}

if ($action === "update_delivery") {
    $deliveryId = positive_int($_POST["delivery_id"] ?? 0);
    $assigned = positive_int($_POST["assigned_staff_id"] ?? 0) ?: null;
    $status = input("delivery_status");
    $allowed = [
        "Pending",
        "Confirmed",
        "Preparing",
        "Out for Delivery",
        "Delivered",
        "Cancelled",
    ];
    if (!$deliveryId || !in_array($status, $allowed, true)) {
        flash("danger", "Invalid delivery status.");
        redirect("portal/index.php?page=deliveries");
    }
    $pdo = db();
    try {
        $lookup = $pdo->prepare(
            "SELECT order_id FROM deliveries WHERE delivery_id = ?",
        );
        $lookup->execute([$deliveryId]);
        $orderId = (int) $lookup->fetchColumn();
        if (!$orderId) {
            throw new RuntimeException("Delivery not found.");
        }

        $pdo->beginTransaction();
        $order = $pdo->prepare(
            "SELECT customer_id, order_status, payment_status FROM orders WHERE order_id = ? FOR UPDATE",
        );
        $order->execute([$orderId]);
        $orderRecord = $order->fetch();
        if (!$orderRecord) {
            throw new RuntimeException("The order for this delivery was not found.");
        }
        $deliveryStatement = $pdo->prepare(
            "SELECT delivery_id, order_id, assigned_staff_id, delivery_status FROM deliveries WHERE delivery_id = ? AND order_id = ? FOR UPDATE",
        );
        $deliveryStatement->execute([$deliveryId, $orderId]);
        $delivery = $deliveryStatement->fetch();
        if (!$delivery) {
            throw new RuntimeException("Delivery not found.");
        }
        if ($assigned !== null) {
            $staff = $pdo->prepare(
                "SELECT user_id FROM users WHERE user_id = ? AND role IN ('Admin', 'Staff') AND account_status = 'Active' FOR UPDATE",
            );
            $staff->execute([$assigned]);
            if (!$staff->fetch()) {
                throw new RuntimeException("Assign delivery only to an active system staff member.");
            }
        }

        $progression = [
            "Pending",
            "Confirmed",
            "Preparing",
            "Out for Delivery",
            "Delivered",
        ];
        $currentDeliveryStatus = (string) $delivery["delivery_status"];
        $currentOrderStatus = (string) $orderRecord["order_status"];
        if (in_array($currentDeliveryStatus, ["Delivered", "Cancelled"], true)) {
            throw new RuntimeException("A final delivery cannot be changed.");
        }
        $currentIndex = array_search($currentDeliveryStatus, $progression, true);
        if ($currentIndex === false || $currentOrderStatus !== $currentDeliveryStatus) {
            throw new RuntimeException(
                "The delivery and order statuses are out of sync and cannot be changed safely.",
            );
        }

        if ($status === "Cancelled") {
            if (in_array($currentOrderStatus, ["Completed", "Delivered", "Cancelled"], true)) {
                throw new RuntimeException("A completed or delivered order cannot be cancelled through delivery.");
            }
            $payment = $pdo->prepare(
                "SELECT payment_id, payment_method, payment_status FROM payments WHERE order_id = ? FOR UPDATE",
            );
            $payment->execute([$orderId]);
            $paymentRecord = $payment->fetch();
            if (!$paymentRecord) {
                throw new RuntimeException("The payment record for this delivery is missing.");
            }
            if (
                $paymentRecord["payment_method"] === "PayMongo" &&
                $paymentRecord["payment_status"] === "Paid"
            ) {
                throw new RuntimeException(
                    "A paid PayMongo order must be refunded through the payment provider before it can be cancelled here.",
                );
            }

            $items = $pdo->prepare(
                "SELECT product_id, quantity FROM order_items WHERE order_id = ? ORDER BY product_id FOR UPDATE",
            );
            $items->execute([$orderId]);
            $restoreQuantities = [];
            foreach ($items->fetchAll() as $item) {
                $productId = (int) $item["product_id"];
                $restoreQuantities[$productId] =
                    ($restoreQuantities[$productId] ?? 0) + (int) $item["quantity"];
            }
            if ($restoreQuantities === []) {
                throw new RuntimeException("The order has no items to restore.");
            }
            ksort($restoreQuantities, SORT_NUMERIC);
            $restore = $pdo->prepare(
                "UPDATE products SET quantity_available = quantity_available + ? WHERE product_id = ?",
            );
            foreach ($restoreQuantities as $productId => $quantity) {
                $restore->execute([$quantity, $productId]);
                if ($restore->rowCount() !== 1) {
                    throw new RuntimeException("A product needed for this cancellation was not found.");
                }
            }

            $pdo->prepare(
                "UPDATE payments SET payment_status = IF(payment_status = 'Paid', 'Refunded', payment_status) WHERE payment_id = ?",
            )->execute([(int) $paymentRecord["payment_id"]]);
            $pdo->prepare(
                "UPDATE deliveries SET assigned_staff_id = ?, delivery_status = 'Cancelled' WHERE delivery_id = ?",
            )->execute([$assigned, $deliveryId]);
            $pdo->prepare(
                "UPDATE orders SET order_status = 'Cancelled', processed_by = ?, payment_status = IF(payment_status = 'Paid', 'Refunded', payment_status) WHERE order_id = ?",
            )->execute([$userId, $orderId]);
        } else {
            $requestedIndex = array_search($status, $progression, true);
            if ($requestedIndex === false) {
                throw new RuntimeException("Invalid delivery status.");
            }
            if ($requestedIndex !== $currentIndex && $requestedIndex !== $currentIndex + 1) {
                throw new RuntimeException("Deliveries must move forward one stage at a time.");
            }
            $pdo->prepare(
                "UPDATE deliveries SET assigned_staff_id = ?, delivery_status = ?, delivered_at = IF(? = 'Delivered', COALESCE(delivered_at, NOW()), delivered_at) WHERE delivery_id = ?",
            )->execute([$assigned, $status, $status, $deliveryId]);
            if ($requestedIndex !== $currentIndex) {
                $pdo->prepare(
                    "UPDATE orders SET order_status = ?, processed_by = ? WHERE order_id = ?",
                )->execute([$status, $userId, $orderId]);
            }
        }

        notify_customer(
            (int) $orderRecord["customer_id"],
            "Delivery updated",
            order_number($orderId) . " delivery is now " . $status . ".",
            $orderId,
        );
        log_activity(
            $userId,
            "Delivery updated",
            "Delivery ID " . $deliveryId . " set to " . $status,
        );
        $pdo->commit();
        flash("success", "Delivery status updated.");
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (!($exception instanceof RuntimeException)) {
            error_log("AquaPure delivery update failed: " . $exception->getMessage());
        }
        flash(
            "danger",
            $exception instanceof RuntimeException
                ? $exception->getMessage()
                : "The delivery could not be saved. Please try again.",
        );
    }
    redirect("portal/index.php?page=deliveries");
}

if ($action === "save_inventory") {
    $id = positive_int($_POST["inventory_id"] ?? 0);
    $quantity = filter_var($_POST["quantity"] ?? null, FILTER_VALIDATE_INT);
    if ($quantity === false || $quantity < 0) {
        flash("danger", "Enter a valid non-negative inventory quantity.");
        redirect(
            "portal/index.php?page=inventory" . ($id ? "&edit=" . $id : ""),
        );
    }

    $isAdmin = is_admin();
    $name = "";
    $category = "";
    $unit = "";
    $level = null;
    if ($isAdmin) {
        $name = input("item_name");
        $category = input("category");
        $unit = input("unit");
        $level = filter_var($_POST["reorder_level"] ?? null, FILTER_VALIDATE_INT);
        if (
            $name === "" ||
            mb_strlen($name) > 120 ||
            $category === "" ||
            mb_strlen($category) > 80 ||
            $unit === "" ||
            mb_strlen($unit) > 30 ||
            $level === false ||
            $level < 0
        ) {
            flash("danger", "Enter valid inventory details.");
            redirect(
                "portal/index.php?page=inventory" . ($id ? "&edit=" . $id : ""),
            );
        }
    } elseif (!$id) {
        flash("danger", "Only an administrator can add inventory items.");
        redirect("portal/index.php?page=inventory");
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        if ($id) {
            $existingStatement = $pdo->prepare(
                "SELECT inventory_id, item_name, category, quantity, unit, reorder_level FROM inventory WHERE inventory_id = ? FOR UPDATE",
            );
            $existingStatement->execute([$id]);
            $existing = $existingStatement->fetch();
            if (!$existing) {
                throw new RuntimeException("Inventory item not found.");
            }
            $previousQuantity = (int) $existing["quantity"];
            if (!$isAdmin) {
                $name = (string) $existing["item_name"];
                $category = (string) $existing["category"];
                $unit = (string) $existing["unit"];
                $level = (int) $existing["reorder_level"];
                $status = inventory_status((int) $quantity, (int) $level);
                $pdo->prepare(
                    "UPDATE inventory SET quantity = ?, status = ?, updated_by = ? WHERE inventory_id = ?",
                )->execute([$quantity, $status, $userId, $id]);
            } else {
                $status = inventory_status((int) $quantity, (int) $level);
                $pdo->prepare(
                    "UPDATE inventory SET item_name = ?, category = ?, quantity = ?, unit = ?, reorder_level = ?, status = ?, updated_by = ? WHERE inventory_id = ?",
                )->execute([
                    $name,
                    $category,
                    $quantity,
                    $unit,
                    $level,
                    $status,
                    $userId,
                    $id,
                ]);
            }
            $transactionType =
                (int) $quantity > $previousQuantity
                    ? "Stock In"
                    : ((int) $quantity < $previousQuantity
                        ? "Stock Out"
                        : "Adjustment");
            $transactionQuantity = abs((int) $quantity - $previousQuantity);
            $remarks = "Quantity adjusted from " . $previousQuantity . " to " . $quantity . ".";
        } else {
            $status = inventory_status((int) $quantity, (int) $level);
            $pdo->prepare(
                "INSERT INTO inventory (item_name, category, quantity, unit, reorder_level, status, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)",
            )->execute([
                $name,
                $category,
                $quantity,
                $unit,
                $level,
                $status,
                $userId,
            ]);
            $id = (int) $pdo->lastInsertId();
            $transactionType = "Stock In";
            $transactionQuantity = (int) $quantity;
            $remarks = "Inventory item created.";
        }
        if ($transactionQuantity > 0 || $transactionType === "Stock In") {
            $pdo->prepare(
                "INSERT INTO inventory_transactions (inventory_id, recorded_by, transaction_type, quantity, remarks) VALUES (?, ?, ?, ?, ?)",
            )->execute([$id, $userId, $transactionType, $transactionQuantity, $remarks]);
        }
        if ($status !== "In Stock") {
            $pdo->prepare(
                "INSERT INTO notifications (title, message) VALUES (?, ?)",
            )->execute([
                "Low inventory",
                $name .
                " is " .
                strtolower($status) .
                " (" .
                $quantity .
                " " .
                $unit .
                " remaining).",
            ]);
        }
        log_activity($userId, "Inventory saved", "Inventory ID " . $id);
        $pdo->commit();
        flash("success", "Inventory item saved.");
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (!($exception instanceof RuntimeException)) {
            error_log("AquaPure inventory save failed: " . $exception->getMessage());
        }
        flash(
            "danger",
            $exception instanceof RuntimeException
                ? $exception->getMessage()
                : "The inventory item could not be saved. Please try again.",
        );
    }
    redirect("portal/index.php?page=inventory" . (aq_form_retry("save_inventory", $id) && $id ? "&edit=" . $id : ""));
}

if ($action === "save_settings") {
    if (!is_admin()) {
        http_response_code(403);
        exit("Admin access required.");
    }

    $stationName = input("station_name");
    $stationContact = preg_replace("/[\s-]/", "", input("station_contact"));
    $stationAddress = input("station_address");
    $businessHours = input("business_hours");
    if (
        mb_strlen($stationName) < 3 ||
        mb_strlen($stationName) > 120 ||
        ($stationContact !== "" && !valid_phone($stationContact)) ||
        mb_strlen($stationAddress) > 255 ||
        mb_strlen($businessHours) > 120
    ) {
        flash("danger", "Enter valid station details. Contact numbers must be valid Philippine mobile numbers.");
        redirect("portal/index.php?page=settings");
    }

    $settings = [
        "station_name" => $stationName,
        "station_contact" => $stationContact,
        "station_address" => $stationAddress,
        "business_hours" => $businessHours,
    ];
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $upsert = $pdo->prepare(
            "INSERT INTO settings (setting_key, setting_value, updated_by_user_id)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by_user_id = VALUES(updated_by_user_id)",
        );
        foreach ($settings as $key => $value) {
            $upsert->execute([$key, $value, $userId]);
        }
        log_activity($userId, "System settings updated", "Station settings saved.");
        $pdo->commit();
        flash("success", "Station settings saved.");
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("AquaPure settings save failed: " . $exception->getMessage());
        flash("danger", "The station settings could not be saved. Please try again.");
    }
    redirect("portal/index.php?page=settings");
}

if ($action === "delete_inventory") {
    if (!is_admin()) {
        http_response_code(403);
        exit("Admin access required.");
    }

    $id = positive_int($_POST["id"] ?? 0);
    if (!$id) {
        flash("danger", "Invalid inventory item.");
        redirect("portal/index.php?page=inventory");
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();

        $item = $pdo->prepare(
            "SELECT inventory_id, item_name, category, quantity, unit FROM inventory WHERE inventory_id = ? FOR UPDATE",
        );
        $item->execute([$id]);
        $record = $item->fetch();
        if (!$record) {
            throw new RuntimeException("Inventory item not found.");
        }

        // Inventory transactions reference the item with ON DELETE RESTRICT.
        // Delete the item's stock-history rows first so the Admin can remove
        // an inventory item through the CRUD module without a foreign-key error.
        $historyCountStatement = $pdo->prepare(
            "SELECT COUNT(*) FROM inventory_transactions WHERE inventory_id = ?",
        );
        $historyCountStatement->execute([$id]);
        $historyCount = (int) $historyCountStatement->fetchColumn();

        if ($historyCount > 0) {
            $deleteHistory = $pdo->prepare(
                "DELETE FROM inventory_transactions WHERE inventory_id = ?",
            );
            $deleteHistory->execute([$id]);
        }

        $delete = $pdo->prepare("DELETE FROM inventory WHERE inventory_id = ?");
        $delete->execute([$id]);
        if ($delete->rowCount() !== 1) {
            throw new RuntimeException("The inventory item could not be deleted.");
        }

        log_activity(
            $userId,
            "Inventory item deleted",
            "Deleted inventory ID " .
                $id .
                " (" .
                $record["item_name"] .
                "); removed " .
                $historyCount .
                " related inventory transaction record(s).",
        );

        $pdo->commit();
        flash("success", "Inventory item deleted successfully.");
    } catch (RuntimeException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash("warning", $exception->getMessage());
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("AquaPure inventory delete failed: " . $exception->getMessage());
        flash("danger", "The inventory item could not be deleted. Please try again.");
    }
    redirect("portal/index.php?page=inventory");
}

if ($action === "create_invitation") {
    if (!is_admin()) {
        http_response_code(403);
        exit("Admin access required.");
    }

    $role = input("invitation_role");
    $email = strtolower(input("email"));
    $expiresInHours = filter_var(
        $_POST["expires_in_hours"] ?? null,
        FILTER_VALIDATE_INT,
    );

    if (
        !in_array($role, ["Admin", "Staff"], true) ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        mb_strlen($email) > 100 ||
        $expiresInHours === false ||
        !in_array((int) $expiresInHours, [24, 48, 72], true)
    ) {
        flash(
            "danger",
            "Enter a valid recipient email, account type, and a 24, 48, or 72 hour expiry.",
        );
        redirect("portal/index.php?page=invitations");
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $invitation = create_registration_invitation(
            $pdo,
            $role,
            $email,
            $userId,
            (int) $expiresInHours,
        );
        log_activity(
            $userId,
            $role . " invitation created",
            "Email: " . $email . "; expires: " . $invitation["expires_at"],
        );
        $pdo->commit();

        remember_created_invitation($invitation);
        flash(
            "success",
            "Invitation created. Copy the code now; AquaPure stores only its hash.",
        );
    } catch (RuntimeException|InvalidArgumentException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash("danger", $exception->getMessage());
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("AquaPure invitation creation failed: " . $exception->getMessage());
        flash("danger", "The invitation could not be created. Please try again.");
    }
    redirect("portal/index.php?page=invitations");
}

if ($action === "revoke_invitation") {
    if (!is_admin()) {
        http_response_code(403);
        exit("Admin access required.");
    }

    $id = positive_int($_POST["id"] ?? 0);
    if (!$id) {
        flash("danger", "Invalid invitation.");
        redirect("portal/index.php?page=invitations");
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        expire_registration_invitations($pdo);

        $lookup = $pdo->prepare(
            "SELECT role, email FROM user_invitations WHERE invitation_id = ? FOR UPDATE",
        );
        $lookup->execute([$id]);
        $record = $lookup->fetch();
        if (!$record) {
            throw new RuntimeException("Invitation not found.");
        }

        if (!revoke_registration_invitation($pdo, $id)) {
            throw new RuntimeException(
                "Only an active, unused, unexpired invitation can be revoked.",
            );
        }

        log_activity(
            $userId,
            $record["role"] . " invitation revoked",
            "Invitation ID " . $id . "; email: " . $record["email"],
        );
        $pdo->commit();
        flash("success", "Invitation revoked.");
    } catch (RuntimeException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash("warning", $exception->getMessage());
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("AquaPure invitation revoke failed: " . $exception->getMessage());
        flash("danger", "The invitation could not be revoked.");
    }
    redirect("portal/index.php?page=invitations");
}

if ($action === "save_user") {
    if (!is_admin()) {
        http_response_code(403);
        exit("Admin access required.");
    }
    $id = positive_int($_POST["user_id"] ?? 0);
    $name = input("full_name");
    $username = strtolower(input("username"));
    $email = strtolower(input("email"));
    $contactNumber = preg_replace("/[\\s-]/", "", input("contact_number"));
    $contactNumber = $contactNumber === "" ? null : $contactNumber;
    $password = isset($_POST["password"]) && is_string($_POST["password"])
        ? $_POST["password"]
        : "";
    if (
        !$id ||
        mb_strlen($name) < 3 ||
        mb_strlen($name) > 100 ||
        !preg_match('/^[a-z0-9._-]{3,30}$/', $username) ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        mb_strlen($email) > 100 ||
        ($contactNumber !== null && !valid_phone($contactNumber)) ||
        ($password !== "" && strlen($password) < 8)
    ) {
        flash("danger", "Enter valid account details. A new password must have at least 8 characters.");
        redirect("portal/index.php?page=users" . ($id ? "&edit=" . $id : ""));
    }

    $target = db()->prepare("SELECT user_id FROM users WHERE user_id = ?");
    $target->execute([$id]);
    if (!$target->fetch()) {
        flash("danger", "System user not found.");
        redirect("portal/index.php?page=users");
    }
    $check = db()->prepare(
        "SELECT user_id FROM users WHERE (email = ? OR username = ?) AND user_id <> ?",
    );
    $check->execute([$email, $username, $id]);
    if ($check->fetch()) {
        flash("danger", "Email or username already exists.");
        redirect("portal/index.php?page=users&edit=" . $id);
    }
    $customerCheck = db()->prepare(
        "SELECT customer_id FROM customers WHERE email = ? LIMIT 1",
    );
    $customerCheck->execute([$email]);
    if ($customerCheck->fetch()) {
        flash("danger", "That email address is already registered to a customer account.");
        redirect("portal/index.php?page=users&edit=" . $id);
    }

    try {
    if ($password !== "") {
        db()
            ->prepare(
                "UPDATE users
                 SET full_name = ?, username = ?, email = ?, contact_number = ?, password_hash = ?
                 WHERE user_id = ?",
            )
            ->execute([
                $name,
                $username,
                $email,
                $contactNumber,
                password_hash($password, PASSWORD_DEFAULT),
                $id,
            ]);
    } else {
        db()
            ->prepare(
                "UPDATE users SET full_name = ?, username = ?, email = ?, contact_number = ? WHERE user_id = ?",
            )
            ->execute([$name, $username, $email, $contactNumber, $id]);
    }
    log_activity($userId, "System user updated", "User ID " . $id);
    flash("success", "System user updated.");
    } catch (PDOException $exception) {
        error_log("AquaPure system user save failed: " . $exception->getMessage());
        flash("danger", "The system user could not be saved. Check for a duplicate email or username.");
    }
    redirect("portal/index.php?page=users" . (aq_form_retry("save_user", $id) && $id ? "&edit=" . $id : ""));
}

if ($action === "toggle_user") {
    if (!is_admin()) {
        http_response_code(403);
        exit("Admin access required.");
    }

    $id = positive_int($_POST["id"] ?? 0);
    if (!$id) {
        flash("danger", "Invalid system user.");
        redirect("portal/index.php?page=users");
    }
    if ($id === $userId) {
        flash("danger", "You cannot deactivate your own active session.");
        redirect("portal/index.php?page=users");
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();

        $target = $pdo->prepare(
            "SELECT user_id, full_name, role, account_status
             FROM users
             WHERE user_id = ?
             FOR UPDATE",
        );
        $target->execute([$id]);
        $targetUser = $target->fetch();
        if (!$targetUser) {
            throw new RuntimeException("System user not found.");
        }

        if (
            $targetUser["role"] === "Admin" &&
            $targetUser["account_status"] === "Active"
        ) {
            // Lock all active Admin rows so two concurrent requests cannot
            // deactivate the final two Administrators at the same time.
            $activeAdminRows = $pdo
                ->query(
                    "SELECT user_id
                     FROM users
                     WHERE role = 'Admin' AND account_status = 'Active'
                     FOR UPDATE",
                )
                ->fetchAll();
            if (count($activeAdminRows) <= 1) {
                throw new RuntimeException(
                    "The last active Administrator cannot be deactivated.",
                );
            }
        }

        $newStatus =
            $targetUser["account_status"] === "Active" ? "Inactive" : "Active";
        $update = $pdo->prepare(
            "UPDATE users SET account_status = ? WHERE user_id = ?",
        );
        $update->execute([$newStatus, $id]);

        log_activity(
            $userId,
            "User status changed",
            "User ID " . $id . " set to " . $newStatus,
        );
        $pdo->commit();
        flash("success", "User account status updated.");
    } catch (RuntimeException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash("danger", $exception->getMessage());
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("AquaPure user status update failed: " . $exception->getMessage());
        flash("danger", "The user account status could not be updated.");
    }

    redirect("portal/index.php?page=users");
}

if ($action === "mark_system_notifications") {
    db()
        ->prepare(
            "UPDATE notifications
             SET is_read = 1
             WHERE user_id = ? OR (user_id IS NULL AND customer_id IS NULL)",
        )
        ->execute([$userId]);
    flash("success", "Notifications marked as read.");
    redirect("portal/index.php?page=notifications");
}