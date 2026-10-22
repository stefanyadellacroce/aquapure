<?php
$cart = cart_details();
$customer = db()->prepare("SELECT * FROM customers WHERE customer_id = ?");
$customer->execute([(int) current_account()["id"]]);
$customer = $customer->fetch();
$checkout = aq_form_values('checkout', [
    'order_type' => 'Pickup', 'recipient_name' => $customer['full_name'],
    'contact_number' => $customer['contact_number'], 'delivery_address' => $customer['address'],
    'preferred_date' => date('Y-m-d'), 'payment_method' => 'Cash',
    'reference_number' => '', 'instructions' => '',
]);
$cartIssues = [];
foreach ($cart['items'] as $item) {
    if ($item['status'] !== 'Available' || (int) $item['quantity_available'] === 0) {
        $cartIssues[] = $item['product_name'] . ' is currently unavailable. Remove it before placing your order.';
    } elseif ((int) $item['cart_quantity'] > (int) $item['quantity_available']) {
        $cartIssues[] = $item['product_name'] . ': only ' . (int) $item['quantity_available'] . ' available. Update the quantity to continue.';
    }
}
?>
<header class="page-header">
    <div><p class="eyebrow mb-1">Order water</p><h1 class="section-title mb-1">Review your order</h1><p class="text-secondary mb-0">Check your items, choose pickup or delivery, and place your order.</p></div>
    <div class="page-header__actions"><a class="btn btn-light" href="<?= e(url('customer/index.php?page=products')) ?>">Continue shopping</a></div>
</header>
<?php if ($cart['items'] === []): ?>
    <section class="panel-card empty-state"><i class="bi bi-bag display-5 d-block mb-3" aria-hidden="true"></i><h2 class="h5">Your order is empty</h2><p>Add products before checking out.</p><a class="btn btn-primary" href="<?= e(url('customer/index.php?page=products')) ?>">Browse products</a></section>
<?php else: ?>
<nav class="checkout-steps" aria-label="Order sections">
    <a class="checkout-step" href="#cartItems"><span aria-hidden="true">1</span>Review items</a>
    <a class="checkout-step" href="#fulfillmentTitle"><span aria-hidden="true">2</span>Pickup or delivery</a>
    <a class="checkout-step" href="#paymentTitle"><span aria-hidden="true">3</span>Payment</a>
    <a class="checkout-step" href="#orderTotal"><span aria-hidden="true">4</span>Place order</a>
</nav>
<?php if ($cartIssues): ?>
    <section class="alert alert-warning" role="alert"><h2 class="h6">Please update your items</h2><ul class="mb-0"><?php foreach ($cartIssues as $issue): ?><li><?= e($issue) ?></li><?php endforeach; ?></ul></section>
<?php endif; ?>
<div class="row g-4">
    <div class="col-lg-7">
        <section class="panel-card" aria-labelledby="cartItems">
            <h2 class="h5 mb-2" id="cartItems" tabindex="-1">Order items</h2>
            <p class="small text-secondary">After editing, select Update quantities to refresh your total. Set a quantity to zero to remove an item.</p>
            <form method="post" data-cart-quantities>
                <?= csrf_field() ?><input type="hidden" name="action" value="update_cart">
                <div class="table-responsive mobile-table-cards" role="region" tabindex="0" aria-label="Cart items table">
                    <table class="table" role="table" data-layout="cards">
                        <caption class="visually-hidden">Order items and quantities</caption>
                        <thead><tr><th scope="col">Product</th><th scope="col" class="text-end">Price</th><th scope="col">Quantity</th><th scope="col" class="text-end">Subtotal</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                        <tbody><?php foreach ($cart['items'] as $item): ?>
                            <tr>
                                <td class="table-primary-cell" data-column-label="Product"><div class="table-cell-value"><strong><?= e($item['product_name']) ?></strong><div class="small text-secondary"><?= e($item['category']) ?></div></div></td>
                                <td class="text-end money" data-column-label="Price"><div class="table-cell-value"><?= money($item['price']) ?></div></td>
                                <td data-column-label="Quantity"><div class="table-cell-value"><input class="form-control quantity-input" type="number" name="quantities[<?= (int) $item['product_id'] ?>]" value="<?= (int) $item['cart_quantity'] ?>" min="0" max="<?= (int) $item['quantity_available'] ?>" step="1" required aria-label="Quantity of <?= e($item['product_name']) ?>"></div></td>
                                <td class="text-end money" data-column-label="Subtotal"><div class="table-cell-value"><?= money($item['subtotal']) ?></div></td>
                                <td data-column-label="Actions"><div class="table-cell-value"><button type="button" class="btn btn-sm btn-light text-danger" data-confirm="Remove <?= e($item['product_name']) ?> from your order?" data-action="<?= e(url('customer/index.php?page=cart')) ?>" data-form-action="remove_cart" data-id="<?= (int) $item['product_id'] ?>" aria-label="Remove <?= e($item['product_name']) ?>" data-confirm-title="Remove product" data-confirm-label="Remove"><i class="bi bi-trash" aria-hidden="true"></i></button></div></td>
                            </tr>
                        <?php endforeach; ?></tbody>
                    </table>
                </div>
                <div class="form-actions justify-content-end"><button class="btn btn-outline-primary" id="updateCartButton" type="submit">Update quantities</button></div>
            </form>
        </section>
    </div>
    <div class="col-lg-5">
        <section class="panel-card">
            <form method="post" data-cart-checkout>
                <?= csrf_field() ?><input type="hidden" name="action" value="checkout">
                <div class="form-section">
                    <h2 class="h5 mb-3" id="fulfillmentTitle" tabindex="-1">Pickup or delivery</h2>
                    <div class="mb-3"><label class="form-label" for="order_type">Order type <span class="text-danger" aria-hidden="true">*</span></label><select class="form-select" id="order_type" name="order_type" data-order-type="#deliveryFields" required>
                        <?php foreach (['Pickup', 'Delivery'] as $type): ?><option value="<?= e($type) ?>" <?= $checkout['order_type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?>
                    </select></div>
                    <div id="deliveryFields">
                        <p class="form-text">All four delivery fields are required for delivery orders. Your preferred date is a request.</p>
                        <div class="mb-3"><label class="form-label" for="recipient_name">Recipient name</label><input class="form-control" id="recipient_name" name="recipient_name" maxlength="100" autocomplete="shipping name" value="<?= e($checkout['recipient_name']) ?>"></div>
                        <div class="mb-3"><label class="form-label" for="contact_number">Contact number</label><input class="form-control" id="contact_number" name="contact_number" maxlength="20" inputmode="tel" autocomplete="shipping tel" value="<?= e($checkout['contact_number']) ?>"></div>
                        <div class="mb-3"><label class="form-label" for="delivery_address">Delivery address</label><textarea class="form-control" id="delivery_address" name="delivery_address" rows="2" maxlength="255" autocomplete="shipping street-address"><?= e($checkout['delivery_address']) ?></textarea></div>
                        <div class="mb-3"><label class="form-label" for="preferred_date">Preferred delivery date</label><input type="date" class="form-control" id="preferred_date" name="preferred_date" min="<?= date('Y-m-d') ?>" value="<?= e($checkout['preferred_date']) ?>"></div>
                    </div>
                </div>
                <div class="form-section">
                    <h2 class="h5 mb-3" id="paymentTitle" tabindex="-1">Payment</h2>
                    <div class="mb-3"><label class="form-label" for="payment_method">Payment method <span class="text-danger" aria-hidden="true">*</span></label><select class="form-select" id="payment_method" name="payment_method" data-payment-method required>
                        <?php foreach (['Cash', 'GCash', 'Maya'] as $method): ?><option value="<?= e($method) ?>" <?= $checkout['payment_method'] === $method ? 'selected' : '' ?>><?= e($method) ?></option><?php endforeach; ?>
                        <?php if (paymongo_is_configured()): ?><option value="PayMongo" <?= $checkout['payment_method'] === 'PayMongo' ? 'selected' : '' ?>>Pay online (PayMongo)</option><?php endif; ?>
                    </select><div class="form-text">The station verifies GCash and Maya references.<?php if (paymongo_is_configured()): ?> PayMongo opens a hosted checkout after you place your order.<?php endif; ?></div></div>
                    <div class="mb-3" data-reference-field><label class="form-label" for="reference_number">GCash / Maya reference number</label><input class="form-control" id="reference_number" name="reference_number" maxlength="100" value="<?= e($checkout['reference_number']) ?>"></div>
                    <div class="mb-3"><label class="form-label" for="instructions">Additional instructions <span class="text-secondary">(optional)</span></label><textarea class="form-control" id="instructions" name="instructions" rows="2"><?= e($checkout['instructions']) ?></textarea></div>
                </div>
                <div class="order-summary pt-3 mb-3" id="orderTotal" tabindex="-1">
                    <div class="d-flex justify-content-between gap-3 small text-secondary mb-2"><span>Items subtotal</span><span class="money"><?= money($cart['total']) ?></span></div>
                    <div class="d-flex justify-content-between gap-3 total"><strong>Order total</strong><strong class="money"><?= money($cart['total']) ?></strong></div>
                </div>
                <p class="alert alert-warning small" data-cart-dirty hidden role="status">Your quantities have changed. <a href="#updateCartButton">Update quantities</a> to review the new total before placing this order.</p>
                <button class="btn btn-primary w-100" type="submit" <?= $cartIssues ? 'disabled' : '' ?>>Place order</button>
            </form>
        </section>
    </div>
</div>
<?php endif; ?>
