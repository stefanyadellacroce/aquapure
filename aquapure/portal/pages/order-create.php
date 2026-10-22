<?php
$customers = db()
    ->query(
        "SELECT customer_id, full_name, customer_type
         FROM customers
         WHERE account_status = 'Active'
         ORDER BY full_name",
    )
    ->fetchAll();
$products = db()
    ->query(
        "SELECT product_id, product_name, price, quantity_available
         FROM products
         WHERE status = 'Available' AND quantity_available > 0
         ORDER BY product_name",
    )
    ->fetchAll();
$canCreateOrder = $customers !== [] && $products !== [];
$walkinValues = aq_form_values('create_walkin_order', [
    'customer_id' => '', 'payment_method' => 'Cash', 'amount_paid' => '0',
    'reference_number' => '', 'product_id' => [], 'quantity' => [],
]);
?>
<header class="page-header">
    <div>
        <a class="small" href="<?= e(url("portal/index.php?page=orders")) ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Orders</a>
        <p class="eyebrow mt-2 mb-1">Point of sale</p>
        <h1 class="section-title mb-1">Create walk-in order</h1>
        <p class="text-secondary mb-0">Select a customer, add products, and record the amount received.</p>
    </div>
</header>

<?php if (!$customers): ?>
    <div class="alert alert-warning" role="alert"><strong>No active customers are available.</strong> <a href="<?= e(url("portal/index.php?page=customers")) ?>">Add a walk-in customer</a> before creating an order.</div>
<?php endif; ?>
<?php if (!$products): ?>
    <div class="alert alert-warning" role="alert"><strong>No products are available.</strong> Update product availability and quantity before creating an order.</div>
<?php endif; ?>

<section class="panel-card operations-form">
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_walkin_order">

        <div class="form-section">
            <div class="mb-3"><h2 class="h5 mb-1">Customer and payment</h2><p class="small text-secondary mb-0">Choose the customer and record how the station receives payment.</p></div>
            <div class="row g-3">
                <div class="col-lg-6">
                    <label class="form-label" for="walkinCustomer">Customer <span class="text-danger" aria-hidden="true">*</span></label>
                    <select class="form-select" id="walkinCustomer" name="customer_id" required <?= !$customers ? "disabled" : "" ?>>
                        <option value="">Select customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= (int) $customer["customer_id"] ?>" <?= (string) $customer['customer_id'] === $walkinValues['customer_id'] ? 'selected' : '' ?>><?= e($customer["full_name"]) ?> — <?= e($customer["customer_type"]) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Not listed? <a href="<?= e(url("portal/index.php?page=customers")) ?>">Register a walk-in customer.</a></div>
                </div>
                <div class="col-md-4 col-lg-3">
                    <label class="form-label" for="walkinPaymentMethod">Payment method</label>
                    <select class="form-select" id="walkinPaymentMethod" name="payment_method" data-payment-method>
                        <?php foreach (['Cash', 'GCash', 'Maya'] as $method): ?>
                            <option value="<?= e($method) ?>" <?= $walkinValues['payment_method'] === $method ? 'selected' : '' ?>><?= e($method) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 col-lg-3">
                    <label class="form-label" for="walkinAmount">Amount received <span class="text-danger" aria-hidden="true">*</span></label>
                    <input type="number" min="0" step="0.01" class="form-control" id="walkinAmount" name="amount_paid" value="<?= e($walkinValues['amount_paid']) ?>" required>
                </div>
                <div class="col-md-4 col-lg-6" data-reference-field>
                    <label class="form-label" for="walkinReference">Reference number</label>
                    <input class="form-control" id="walkinReference" name="reference_number" maxlength="100" value="<?= e($walkinValues['reference_number']) ?>">
                    <div class="form-text">Required for GCash and Maya.</div>
                </div>
            </div>
        </div>

        <div class="form-section">
            <div class="mb-3"><h2 class="h5 mb-1">Products</h2><p class="small text-secondary mb-0">Select up to four product rows. Leave unused rows blank.</p></div>
            <?php for ($row = 0; $row < 4; $row++): ?>
                <div class="row g-2 mb-2">
                    <div class="col-sm-9">
                        <label class="form-label" for="walkinProduct<?= $row ?>">Product <?= $row + 1 ?></label>
                        <select class="form-select" id="walkinProduct<?= $row ?>" name="product_id[]" <?= !$products ? "disabled" : "" ?>>
                            <option value="">Select product</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= (int) $product["product_id"] ?>" <?= (string) $product['product_id'] === ($walkinValues['product_id'][$row] ?? '') ? 'selected' : '' ?>><?= e($product["product_name"]) ?> — <?= money($product["price"]) ?> (<?= (int) $product["quantity_available"] ?> available)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label" for="walkinQuantity<?= $row ?>">Quantity for product <?= $row + 1 ?></label>
                        <input type="number" min="1" class="form-control" id="walkinQuantity<?= $row ?>" name="quantity[]" value="<?= e($walkinValues['quantity'][$row] ?? '1') ?>" aria-label="Quantity for product <?= $row + 1 ?>" <?= !$products ? "disabled" : "" ?>>
                    </div>
                </div>
            <?php endfor; ?>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit" <?= !$canCreateOrder ? "disabled" : "" ?>>Create walk-in order</button>
            <a class="btn btn-light" href="<?= e(url("portal/index.php?page=orders")) ?>">Cancel</a>
        </div>
    </form>
</section>
