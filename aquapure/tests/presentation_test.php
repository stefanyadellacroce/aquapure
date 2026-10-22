<?php
declare(strict_types=1);

// CLI-only, no database, sessions on disk, or application records are changed.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/presentation.php';

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION = [];
$_POST = [
    'action' => 'save_user', 'user_id' => '42', 'full_name' => '<script>test</script>',
    'email' => 'example@example.test', 'username' => 'example', 'contact_number' => '09171234567',
    'password' => 'do-not-retain', 'current_password' => 'do-not-retain',
    'csrf_token' => 'do-not-retain', 'role' => 'Admin', 'invitation_code' => 'do-not-retain',
];
flash('danger', 'Correct the account details.');
$values = aq_form_values('save_user', ['full_name' => 'Original', 'role' => 'Staff'], 42);
check(aq_form_retry('save_user', 42), 'Failure is scoped to its action and record.');
check(!aq_form_retry('save_user', 43), 'Another record must not inherit values.');
check(!aq_form_retry('save_customer', 42), 'Another action must not inherit values.');
check($values['full_name'] === '<script>test</script>', 'Form recovery preserves original text.');
check(e($values['full_name']) === '&lt;script&gt;test&lt;/script&gt;', 'The existing output helper escapes recovered text.');
check($values['role'] === 'Staff', 'Authoritative role defaults are preserved.');
check(!isset($values['user_id']), 'Hidden record ID is never a recovered editable field.');
foreach (['password', 'current_password', 'csrf_token', 'invitation_code'] as $field) {
    check(!isset($values[$field]), "Sensitive field {$field} is not retained.");
}
check(aq_form_values('save_user', ['full_name' => 'Other'], 43) === ['full_name' => 'Other'], 'Unrelated record keeps defaults.');

$_SESSION = [];
$_POST = ['action' => 'save_customer', 'full_name' => ['unexpected'], 'address' => str_repeat('a', 3000)];
aq_remember_failed_form();
$values = aq_form_values('save_customer', ['full_name' => 'Default']);
check($values['full_name'] === 'Default', 'Unexpected arrays cannot replace scalar fields.');
check(strlen($values['address']) === 2000, 'Recovery payload is bounded.');

$_SESSION = [];
$_POST = ['action' => 'create_walkin_order', 'customer_id' => '3', 'product_id' => ['5','6','7','8','9'], 'quantity' => ['2',['bad'],'4','5','6'], 'total_amount' => '0.01'];
aq_remember_failed_form();
$values = aq_form_values('create_walkin_order');
check($values['customer_id'] === '3', 'Editable customer selection is retained.');
check(count($values['product_id']) === 4, 'Only four existing product rows are retained.');
check($values['quantity'][0] === '2' && !isset($values['quantity'][1]) && $values['quantity'][2] === '4', 'Malformed quantities do not shift other rows.');
check(!isset($values['total_amount']), 'Submitted totals cannot replace server totals.');

$_SESSION = [];
$_POST = ['action' => 'record_payment', 'order_id' => '9', 'amount_paid' => '500', 'payment_method' => 'Cash', 'amount_due' => '1'];
aq_remember_failed_form();
check(aq_form_values('record_payment', ['amount_due' => '250'], 9)['amount_due'] === '250', 'Payment amount due stays authoritative.');
check(aq_form_values('record_payment', [], 10) === [], 'Payment recovery cannot leak to another order.');

$_SESSION = [];
$_POST = ['action' => 'delete_inventory', 'item_name' => 'Not a record form'];
aq_remember_failed_form();
check(!isset($_SESSION['aq_failed_form']), 'Unsupported actions are not remembered.');

keep_old(['full_name' => 'Example', 'password' => 'secret', 'confirm_password' => 'secret', 'current_password' => 'secret', 'csrf_token' => 'secret', 'invitation_code' => 'secret']);
check($_SESSION['old'] === ['full_name' => 'Example'], 'Legacy auth recovery also excludes all secrets.');
check(badge_class('Partial') === 'text-bg-warning', 'Partial payment remains distinct from completed payment.');
check(badge_class('Unexpected state') === 'text-bg-secondary', 'Unknown statuses have a neutral presentation.');

fwrite(STDOUT, "PASS: {$checks} presentation checks.\n");
