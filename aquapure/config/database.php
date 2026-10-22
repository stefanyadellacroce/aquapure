<?php
declare(strict_types=1);

/**
 * AquaPure database connection.
 *
 * Configure credentials through Apache/PHP environment variables when needed:
 * AQUAPURE_DB_HOST, AQUAPURE_DB_PORT, AQUAPURE_DB_NAME,
 * AQUAPURE_DB_USER and AQUAPURE_DB_PASS.
 *
 * IMPORTANT: normal web requests never ALTER the database schema. Existing
 * installations must be upgraded once with database/migrations/
 * 20260818_upgrade_existing_aquapure.sql.
 */
function db(): PDO
{
    static $pdo = null;
    static $schemaChecked = false;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('AQUAPURE_DB_HOST') ?: '127.0.0.1';
    $port = getenv('AQUAPURE_DB_PORT') ?: '3306';
    $name = getenv('AQUAPURE_DB_NAME') ?: 'aquapure_db';
    $user = getenv('AQUAPURE_DB_USER') ?: 'root';
    $pass = getenv('AQUAPURE_DB_PASS');
    $pass = is_string($pass) ? $pass : '';

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $name,
    );

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);

    // Keep MySQL timestamps aligned with the application's Asia/Manila clock.
    $pdo->exec("SET time_zone = '+08:00'");

    if (!$schemaChecked) {
        assert_aquapure_schema_ready($pdo);
        $schemaChecked = true;
    }

    return $pdo;
}

/**
 * Detect the most common old-schema mismatches early and provide one safe,
 * actionable error instead of allowing random pages to fail with SQLSTATE
 * "unknown column" messages.
 */
function assert_aquapure_schema_ready(PDO $pdo): void
{
    $required = [
        'users' => [
            'user_id', 'full_name', 'username', 'email', 'contact_number',
            'password_hash', 'role', 'account_status', 'created_at', 'updated_at',
        ],
        'customers' => [
            'customer_id', 'full_name', 'email', 'contact_number', 'address',
            'password_hash', 'customer_type', 'account_status', 'created_at', 'updated_at',
        ],
        'products' => [
            'product_id', 'product_name', 'description', 'category', 'price',
            'quantity_available', 'image_path', 'status', 'created_at', 'updated_at',
        ],
        'orders' => [
            'order_id', 'customer_id', 'processed_by', 'order_source', 'order_type',
            'total_amount', 'payment_status', 'order_status', 'notes', 'order_date', 'updated_at',
        ],
        'order_items' => [
            'order_item_id', 'order_id', 'product_id', 'quantity', 'unit_price', 'subtotal',
        ],
        'payments' => [
            'payment_id', 'order_id', 'amount_due', 'amount_paid', 'change_amount',
            'payment_method', 'reference_number', 'payment_date', 'payment_status',
            'recorded_by', 'created_at', 'updated_at',
        ],
        'deliveries' => [
            'delivery_id', 'order_id', 'recipient_name', 'contact_number',
            'delivery_address', 'preferred_delivery_date', 'additional_instructions',
            'assigned_staff_id', 'delivery_status', 'delivered_at', 'created_at', 'updated_at',
        ],
        'inventory' => [
            'inventory_id', 'item_name', 'category', 'quantity', 'unit',
            'reorder_level', 'status', 'updated_by', 'last_updated',
        ],
        'inventory_transactions' => [
            'transaction_id', 'inventory_id', 'recorded_by', 'transaction_type',
            'quantity', 'remarks', 'transaction_date',
        ],
        'notifications' => [
            'notification_id', 'customer_id', 'user_id', 'order_id', 'title',
            'message', 'is_read', 'created_at',
        ],
        'activity_logs' => [
            'activity_id', 'user_id', 'action', 'details', 'ip_address', 'created_at',
        ],
        'settings' => [
            'id', 'setting_key', 'setting_value', 'updated_by_user_id', 'updated_at',
        ],
        'user_invitations' => [
            'invitation_id', 'token_hash', 'email', 'role', 'created_by', 'created_at',
            'expires_at', 'used_at', 'used_by', 'revoked_at', 'status',
        ],
    ];

    $statement = $pdo->prepare(
        'SELECT COLUMN_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
    );

    foreach ($required as $table => $columns) {
        $statement->execute([$table]);
        $existing = array_fill_keys($statement->fetchAll(PDO::FETCH_COLUMN), true);
        foreach ($columns as $column) {
            if (!isset($existing[$column])) {
                throw new RuntimeException(
                    'AquaPure database schema is outdated or incomplete. ' .
                    'Back up the database, then run database/migrations/' .
                    '20260818_upgrade_existing_aquapure.sql once in phpMyAdmin.',
                );
            }
        }
    }
}
