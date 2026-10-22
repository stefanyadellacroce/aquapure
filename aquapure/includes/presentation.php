<?php
declare(strict_types=1);

/** Form recovery is presentation-only. Hidden IDs, roles and secrets never come from it. */
function aq_remember_failed_form(): void
{
    $fields = [
        'save_customer' => ['full_name', 'email', 'contact_number', 'address', 'customer_type', 'account_status'],
        'save_product' => ['product_name', 'category', 'status', 'price', 'quantity_available', 'description'],
        'save_inventory' => ['item_name', 'category', 'quantity', 'unit', 'reorder_level'],
        'save_user' => ['full_name', 'username', 'email', 'contact_number'],
        'save_settings' => ['station_name', 'station_contact', 'station_address', 'business_hours'],
        'update_profile' => ['full_name', 'email', 'contact_number', 'address'],
        'checkout' => ['order_type', 'recipient_name', 'contact_number', 'delivery_address', 'preferred_date', 'payment_method', 'reference_number', 'instructions'],
        'create_invitation' => ['email', 'expires_in_hours'],
        'create_walkin_order' => ['customer_id', 'payment_method', 'amount_paid', 'reference_number'],
        'record_payment' => ['amount_paid', 'payment_method', 'reference_number'],
    ];
    $action = input('action');
    if (!isset($fields[$action])) {
        return;
    }
    $values = [];
    foreach ($fields[$action] as $field) {
        if (isset($_POST[$field]) && is_string($_POST[$field])) {
            $values[$field] = substr($_POST[$field], 0, 2000);
        }
    }
    // These are editable product selections, never authoritative prices or hidden record IDs.
    if ($action === 'create_walkin_order') {
        foreach (['product_id', 'quantity'] as $field) {
            if (!isset($_POST[$field]) || !is_array($_POST[$field])) {
                continue;
            }
            $values[$field] = [];
            foreach (array_slice($_POST[$field], 0, 4) as $index => $value) {
                if (is_string($value)) {
                    $values[$field][$index] = substr($value, 0, 20);
                }
            }
        }
    }
    $idField = match ($action) {
        'save_customer' => 'customer_id',
        'save_product' => 'product_id',
        'save_inventory' => 'inventory_id',
        'save_user' => 'user_id',
        'record_payment' => 'order_id',
        default => '',
    };
    $_SESSION['aq_failed_form'] = [
        'action' => $action,
        'record_id' => $idField !== '' ? positive_int($_POST[$idField] ?? 0) : 0,
        'values' => $values,
    ];
}

function aq_form_retry(string $action, int $recordId = 0): bool
{
    $saved = $_SESSION['aq_failed_form'] ?? null;
    return is_array($saved)
        && ($saved['action'] ?? '') === $action
        && ($saved['record_id'] ?? 0) === $recordId;
}

/** Return unescaped values; the template must continue to use e() at output. */
function aq_form_values(string $action, array $defaults = [], int $recordId = 0): array
{
    return aq_form_retry($action, $recordId)
        ? array_replace($defaults, $_SESSION['aq_failed_form']['values'])
        : $defaults;
}
