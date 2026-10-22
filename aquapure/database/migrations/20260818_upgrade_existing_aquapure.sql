-- ============================================================
-- AquaPure SAFE EXISTING-DATABASE MIGRATION
-- Version: 2026-08-18
-- ============================================================
-- IMPORTANT:
-- 1) EXPORT/BACK UP your existing AquaPure database first.
-- 2) In phpMyAdmin, CLICK the existing AquaPure database before running this.
-- 3) Run this file ONCE. It does NOT DROP your database or core data tables.
-- 4) This replaces the older separate migration files in this project.
--
-- This migration standardizes known legacy AquaPure schemas, including:
-- - products.availability -> products.status compatibility
-- - payments.method -> payments.payment_method
-- - older id/status column names
-- - unified email-specific user_invitations
-- - settings, inventory_transactions, and missing audit fields
-- ============================================================

DELIMITER //
DROP PROCEDURE IF EXISTS aquapure_upgrade_existing//
CREATE PROCEDURE aquapure_upgrade_existing()
BEGIN
    -- Refuse to run without an explicitly selected database.
    IF DATABASE() IS NULL OR DATABASE() = '' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Select the existing AquaPure database in phpMyAdmin before running this migration.';
    END IF;

    -- Core tables must exist. If they do not, use the fresh installer instead.
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
    ) OR NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'
    ) OR NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'
    ) OR NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
    ) OR NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Core AquaPure tables are missing. Use database/aquapure.sql for a fresh empty installation instead.';
    END IF;

    -- --------------------------------------------------------
    -- users
    -- --------------------------------------------------------
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'id'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'user_id'
    ) THEN
        ALTER TABLE users
            CHANGE COLUMN id user_id INT UNSIGNED NOT NULL AUTO_INCREMENT;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'contact_number'
    ) THEN
        ALTER TABLE users ADD COLUMN contact_number VARCHAR(30) NULL AFTER email;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'status'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'account_status'
    ) THEN
        ALTER TABLE users MODIFY COLUMN status VARCHAR(30) NULL;
        UPDATE users
        SET status = CASE
            WHEN status = 'Active' THEN 'Active'
            ELSE 'Inactive'
        END;
        ALTER TABLE users
            CHANGE COLUMN status account_status
            ENUM('Active','Inactive') NOT NULL DEFAULT 'Active';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'account_status'
    ) THEN
        ALTER TABLE users
            ADD COLUMN account_status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'updated_at'
    ) THEN
        ALTER TABLE users
            ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
    END IF;

    -- --------------------------------------------------------
    -- customers
    -- --------------------------------------------------------
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'id'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'customer_id'
    ) THEN
        ALTER TABLE customers
            CHANGE COLUMN id customer_id INT UNSIGNED NOT NULL AUTO_INCREMENT;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'account_type'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'customer_type'
    ) THEN
        ALTER TABLE customers MODIFY COLUMN account_type VARCHAR(30) NOT NULL DEFAULT 'Online Customer';
        UPDATE customers
        SET account_type = CASE
            WHEN account_type = 'Online' THEN 'Online Customer'
            WHEN account_type = 'Walk-in' THEN 'Walk-in Customer'
            WHEN account_type IN ('Online Customer','Walk-in Customer','Residential','Business') THEN account_type
            ELSE 'Online Customer'
        END;
        ALTER TABLE customers
            CHANGE COLUMN account_type customer_type
            ENUM('Online Customer','Walk-in Customer','Residential','Business')
            NOT NULL DEFAULT 'Online Customer';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'customer_type'
    ) THEN
        ALTER TABLE customers
            ADD COLUMN customer_type
            ENUM('Online Customer','Walk-in Customer','Residential','Business')
            NOT NULL DEFAULT 'Online Customer';
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'status'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'account_status'
    ) THEN
        ALTER TABLE customers MODIFY COLUMN status VARCHAR(30) NULL;
        UPDATE customers
        SET status = CASE WHEN status = 'Active' THEN 'Active' ELSE 'Inactive' END;
        ALTER TABLE customers
            CHANGE COLUMN status account_status
            ENUM('Active','Inactive') NOT NULL DEFAULT 'Active';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'account_status'
    ) THEN
        ALTER TABLE customers
            ADD COLUMN account_status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'updated_at'
    ) THEN
        ALTER TABLE customers
            ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
    END IF;

    -- --------------------------------------------------------
    -- products: standardize on products.status.
    -- Legacy products.availability is retained only when both columns already
    -- exist, so no legacy data is dropped by this migration.
    -- --------------------------------------------------------
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'id'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'product_id'
    ) THEN
        ALTER TABLE products
            CHANGE COLUMN id product_id INT UNSIGNED NOT NULL AUTO_INCREMENT;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'status'
    ) AND EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'availability'
    ) THEN
        ALTER TABLE products MODIFY COLUMN availability VARCHAR(30) NULL;
        UPDATE products
        SET availability = CASE
            WHEN availability = 'Unavailable' THEN 'Unavailable'
            ELSE 'Available'
        END;
        ALTER TABLE products
            CHANGE COLUMN availability status
            ENUM('Available','Unavailable') NOT NULL DEFAULT 'Available';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'status'
    ) THEN
        ALTER TABLE products
            ADD COLUMN status ENUM('Available','Unavailable') NOT NULL DEFAULT 'Available'
            AFTER quantity_available;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'status'
    ) THEN
        ALTER TABLE products MODIFY COLUMN status VARCHAR(30) NULL;
        IF EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'availability'
        ) THEN
            UPDATE products
            SET status = availability
            WHERE availability IN ('Available','Unavailable');
        END IF;
        UPDATE products
        SET status = CASE WHEN status = 'Unavailable' THEN 'Unavailable' ELSE 'Available' END;
        ALTER TABLE products
            MODIFY COLUMN status ENUM('Available','Unavailable') NOT NULL DEFAULT 'Available';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'image_path'
    ) THEN
        ALTER TABLE products ADD COLUMN image_path VARCHAR(255) NULL AFTER quantity_available;
        IF EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'product_image'
        ) THEN
            UPDATE products
            SET image_path = product_image
            WHERE product_image IS NOT NULL AND product_image <> '';
        END IF;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'updated_at'
    ) THEN
        ALTER TABLE products
            ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
    END IF;

    -- --------------------------------------------------------
    -- orders
    -- --------------------------------------------------------
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'id'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'order_id'
    ) THEN
        ALTER TABLE orders
            CHANGE COLUMN id order_id INT UNSIGNED NOT NULL AUTO_INCREMENT;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'created_by_user_id'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'processed_by'
    ) THEN
        ALTER TABLE orders
            CHANGE COLUMN created_by_user_id processed_by INT UNSIGNED NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'processed_by'
    ) THEN
        ALTER TABLE orders ADD COLUMN processed_by INT UNSIGNED NULL AFTER customer_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'grand_total'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'total_amount'
    ) THEN
        ALTER TABLE orders
            CHANGE COLUMN grand_total total_amount DECIMAL(10,2) UNSIGNED NOT NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'total_amount'
    ) THEN
        ALTER TABLE orders
            ADD COLUMN total_amount DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'status'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'order_status'
    ) THEN
        ALTER TABLE orders CHANGE COLUMN status order_status VARCHAR(40) NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'order_status'
    ) THEN
        ALTER TABLE orders ADD COLUMN order_status VARCHAR(40) NULL DEFAULT 'Pending';
    END IF;

    ALTER TABLE orders MODIFY COLUMN order_status VARCHAR(40) NULL;
    UPDATE orders
    SET order_status = CASE
        WHEN order_status IN ('Pending','Confirmed','Preparing','Ready for Pickup','Out for Delivery','Delivered','Completed','Cancelled') THEN order_status
        WHEN order_status = 'Ready' THEN 'Ready for Pickup'
        ELSE 'Pending'
    END;
    ALTER TABLE orders
        MODIFY COLUMN order_status
        ENUM('Pending','Confirmed','Preparing','Ready for Pickup','Out for Delivery','Delivered','Completed','Cancelled')
        NOT NULL DEFAULT 'Pending';

    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'created_at'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'order_date'
    ) THEN
        ALTER TABLE orders
            CHANGE COLUMN created_at order_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'order_date'
    ) THEN
        ALTER TABLE orders
            ADD COLUMN order_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'order_source'
    ) THEN
        ALTER TABLE orders
            ADD COLUMN order_source ENUM('Online','Walk-in') NOT NULL DEFAULT 'Online'
            AFTER processed_by;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'notes'
    ) THEN
        ALTER TABLE orders ADD COLUMN notes TEXT NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'updated_at'
    ) THEN
        ALTER TABLE orders
            ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'order_code' AND IS_NULLABLE = 'NO'
    ) THEN
        ALTER TABLE orders MODIFY COLUMN order_code VARCHAR(32) NULL;
    END IF;

    ALTER TABLE orders
        MODIFY COLUMN order_type ENUM('Walk-in','Pickup','Delivery') NOT NULL;
    ALTER TABLE orders MODIFY COLUMN payment_status VARCHAR(30) NULL;
    UPDATE orders
    SET payment_status = CASE
        WHEN payment_status IN ('Unpaid','Partial','Paid','Refunded') THEN payment_status
        ELSE 'Unpaid'
    END;
    ALTER TABLE orders
        MODIFY COLUMN payment_status ENUM('Unpaid','Partial','Paid','Refunded') NOT NULL DEFAULT 'Unpaid';

    -- --------------------------------------------------------
    -- order_items
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS order_items (
        order_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id INT UNSIGNED NOT NULL,
        product_id INT UNSIGNED NOT NULL,
        quantity INT UNSIGNED NOT NULL,
        unit_price DECIMAL(10,2) UNSIGNED NOT NULL,
        subtotal DECIMAL(10,2) UNSIGNED NOT NULL,
        CONSTRAINT fk_order_items_order
            FOREIGN KEY (order_id) REFERENCES orders(order_id)
            ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_order_items_product
            FOREIGN KEY (product_id) REFERENCES products(product_id)
            ON UPDATE CASCADE ON DELETE RESTRICT,
        INDEX idx_order_items_order (order_id),
        INDEX idx_order_items_product (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items'
    ) THEN
        IF EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME = 'id'
        ) AND NOT EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME = 'order_item_id'
        ) THEN
            ALTER TABLE order_items
                CHANGE COLUMN id order_item_id INT UNSIGNED NOT NULL AUTO_INCREMENT;
        END IF;

        IF EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME = 'product_name' AND IS_NULLABLE = 'NO'
        ) THEN
            ALTER TABLE order_items MODIFY COLUMN product_name VARCHAR(120) NULL;
        END IF;
    END IF;

    -- --------------------------------------------------------
    -- payments
    -- --------------------------------------------------------
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'id'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'payment_id'
    ) THEN
        ALTER TABLE payments
            CHANGE COLUMN id payment_id INT UNSIGNED NOT NULL AUTO_INCREMENT;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'method'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'payment_method'
    ) THEN
        ALTER TABLE payments
            CHANGE COLUMN method payment_method VARCHAR(30) NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'payment_method'
    ) THEN
        ALTER TABLE payments ADD COLUMN payment_method VARCHAR(30) NULL DEFAULT 'Cash';
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'method'
    ) THEN
        UPDATE payments
        SET payment_method = method
        WHERE (payment_method IS NULL OR payment_method = '') AND method IS NOT NULL;
    END IF;

    ALTER TABLE payments MODIFY COLUMN payment_method VARCHAR(30) NULL;
    UPDATE payments
    SET payment_method = CASE
        WHEN payment_method IN ('Cash','GCash','Maya','PayMongo') THEN payment_method
        ELSE 'Cash'
    END;
    ALTER TABLE payments
        MODIFY COLUMN payment_method
        ENUM('Cash','GCash','Maya','PayMongo') NOT NULL DEFAULT 'Cash';

    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'amount'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'amount_due'
    ) THEN
        ALTER TABLE payments
            CHANGE COLUMN amount amount_due DECIMAL(10,2) UNSIGNED NOT NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'amount_due'
    ) THEN
        ALTER TABLE payments
            ADD COLUMN amount_due DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0 AFTER order_id;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'amount_paid'
    ) THEN
        ALTER TABLE payments
            ADD COLUMN amount_paid DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0 AFTER amount_due;
        UPDATE payments SET amount_paid = amount_due;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'change_amount'
    ) THEN
        ALTER TABLE payments
            ADD COLUMN change_amount DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0 AFTER amount_paid;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'recorded_by_user_id'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'recorded_by'
    ) THEN
        ALTER TABLE payments
            CHANGE COLUMN recorded_by_user_id recorded_by INT UNSIGNED NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'recorded_by'
    ) THEN
        ALTER TABLE payments ADD COLUMN recorded_by INT UNSIGNED NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'reference_number'
    ) THEN
        ALTER TABLE payments ADD COLUMN reference_number VARCHAR(100) NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'payment_status'
    ) THEN
        ALTER TABLE payments
            ADD COLUMN payment_status ENUM('Unpaid','Partial','Paid','Refunded') NOT NULL DEFAULT 'Unpaid';
        UPDATE payments
        SET payment_status = CASE
            WHEN amount_paid <= 0 THEN 'Unpaid'
            WHEN amount_paid < amount_due THEN 'Partial'
            ELSE 'Paid'
        END;
    ELSE
        ALTER TABLE payments MODIFY COLUMN payment_status VARCHAR(30) NULL;
        UPDATE payments
        SET payment_status = CASE
            WHEN payment_status IN ('Unpaid','Partial','Paid','Refunded') THEN payment_status
            WHEN amount_paid <= 0 THEN 'Unpaid'
            WHEN amount_paid < amount_due THEN 'Partial'
            ELSE 'Paid'
        END;
        ALTER TABLE payments
            MODIFY COLUMN payment_status ENUM('Unpaid','Partial','Paid','Refunded') NOT NULL DEFAULT 'Unpaid';
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'payment_date'
    ) THEN
        ALTER TABLE payments MODIFY COLUMN payment_date DATETIME NULL;
    ELSE
        ALTER TABLE payments ADD COLUMN payment_date DATETIME NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'created_at'
    ) THEN
        ALTER TABLE payments ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'updated_at'
    ) THEN
        ALTER TABLE payments
            ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
    END IF;

    -- --------------------------------------------------------
    -- deliveries
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS deliveries (
        delivery_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id INT UNSIGNED NOT NULL UNIQUE,
        recipient_name VARCHAR(100) NOT NULL,
        contact_number VARCHAR(20) NOT NULL,
        delivery_address VARCHAR(255) NOT NULL,
        preferred_delivery_date DATE NOT NULL,
        additional_instructions TEXT NULL,
        assigned_staff_id INT UNSIGNED NULL,
        delivery_status ENUM('Pending','Confirmed','Preparing','Out for Delivery','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
        delivered_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_deliveries_order
            FOREIGN KEY (order_id) REFERENCES orders(order_id)
            ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_deliveries_staff
            FOREIGN KEY (assigned_staff_id) REFERENCES users(user_id)
            ON UPDATE CASCADE ON DELETE SET NULL,
        INDEX idx_deliveries_date (preferred_delivery_date),
        INDEX idx_deliveries_status (delivery_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries'
    ) THEN
        IF EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'id'
        ) AND NOT EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'delivery_id'
        ) THEN
            ALTER TABLE deliveries
                CHANGE COLUMN id delivery_id INT UNSIGNED NOT NULL AUTO_INCREMENT;
        END IF;

        IF EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'instructions'
        ) AND NOT EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'additional_instructions'
        ) THEN
            ALTER TABLE deliveries
                CHANGE COLUMN instructions additional_instructions TEXT NULL;
        END IF;

        IF EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'assigned_to_user_id'
        ) AND NOT EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'assigned_staff_id'
        ) THEN
            ALTER TABLE deliveries
                CHANGE COLUMN assigned_to_user_id assigned_staff_id INT UNSIGNED NULL;
        END IF;

        IF EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'status'
        ) AND NOT EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'delivery_status'
        ) THEN
            ALTER TABLE deliveries CHANGE COLUMN status delivery_status VARCHAR(40) NULL;
        END IF;

        IF NOT EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'delivery_status'
        ) THEN
            ALTER TABLE deliveries ADD COLUMN delivery_status VARCHAR(40) NULL DEFAULT 'Pending';
        END IF;

        ALTER TABLE deliveries MODIFY COLUMN delivery_status VARCHAR(40) NULL;
        UPDATE deliveries
        SET delivery_status = CASE
            WHEN delivery_status IN ('Pending','Confirmed','Preparing','Out for Delivery','Delivered','Cancelled') THEN delivery_status
            WHEN delivery_status = 'Completed' THEN 'Delivered'
            ELSE 'Pending'
        END;
        ALTER TABLE deliveries
            MODIFY COLUMN delivery_status
            ENUM('Pending','Confirmed','Preparing','Out for Delivery','Delivered','Cancelled')
            NOT NULL DEFAULT 'Pending';

        IF NOT EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'delivered_at'
        ) THEN
            ALTER TABLE deliveries ADD COLUMN delivered_at DATETIME NULL;
        END IF;

        IF NOT EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'created_at'
        ) THEN
            ALTER TABLE deliveries ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
        END IF;
        IF NOT EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deliveries' AND COLUMN_NAME = 'updated_at'
        ) THEN
            ALTER TABLE deliveries
                ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
        END IF;
    END IF;

    -- --------------------------------------------------------
    -- inventory + inventory_transactions
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS inventory (
        inventory_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        item_name VARCHAR(120) NOT NULL UNIQUE,
        category VARCHAR(80) NOT NULL,
        quantity INT UNSIGNED NOT NULL DEFAULT 0,
        unit VARCHAR(30) NOT NULL,
        reorder_level INT UNSIGNED NOT NULL DEFAULT 10,
        status ENUM('In Stock','Low Stock','Out of Stock') NOT NULL DEFAULT 'In Stock',
        updated_by INT UNSIGNED NULL,
        last_updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_inventory_user
            FOREIGN KEY (updated_by) REFERENCES users(user_id)
            ON UPDATE CASCADE ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory'
    ) THEN
        IF EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory' AND COLUMN_NAME = 'id'
        ) AND NOT EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory' AND COLUMN_NAME = 'inventory_id'
        ) THEN
            ALTER TABLE inventory
                CHANGE COLUMN id inventory_id INT UNSIGNED NOT NULL AUTO_INCREMENT;
        END IF;

        IF NOT EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory' AND COLUMN_NAME = 'status'
        ) THEN
            ALTER TABLE inventory
                ADD COLUMN status ENUM('In Stock','Low Stock','Out of Stock') NOT NULL DEFAULT 'In Stock';
        END IF;
        UPDATE inventory
        SET status = CASE
            WHEN quantity = 0 THEN 'Out of Stock'
            WHEN quantity <= reorder_level THEN 'Low Stock'
            ELSE 'In Stock'
        END;

        IF NOT EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory' AND COLUMN_NAME = 'updated_by'
        ) THEN
            ALTER TABLE inventory ADD COLUMN updated_by INT UNSIGNED NULL;
        END IF;
    END IF;

    CREATE TABLE IF NOT EXISTS inventory_transactions (
        transaction_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        inventory_id INT UNSIGNED NOT NULL,
        recorded_by INT UNSIGNED NULL,
        transaction_type ENUM('Stock In','Stock Out','Adjustment') NOT NULL,
        quantity INT UNSIGNED NOT NULL,
        remarks VARCHAR(255) NULL,
        transaction_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_inventory_transactions_item
            FOREIGN KEY (inventory_id) REFERENCES inventory(inventory_id)
            ON UPDATE CASCADE ON DELETE RESTRICT,
        CONSTRAINT fk_inventory_transactions_user
            FOREIGN KEY (recorded_by) REFERENCES users(user_id)
            ON UPDATE CASCADE ON DELETE SET NULL,
        INDEX idx_inventory_transactions_item_date (inventory_id, transaction_date),
        INDEX idx_inventory_transactions_date (transaction_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Upgrade the known older CASCADE rule when it uses AquaPure's standard FK name.
    IF EXISTS (
        SELECT 1
        FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'inventory_transactions'
          AND CONSTRAINT_NAME = 'fk_inventory_transactions_item'
          AND DELETE_RULE = 'CASCADE'
    ) THEN
        ALTER TABLE inventory_transactions
            DROP FOREIGN KEY fk_inventory_transactions_item;
        ALTER TABLE inventory_transactions
            ADD CONSTRAINT fk_inventory_transactions_item
            FOREIGN KEY (inventory_id) REFERENCES inventory(inventory_id)
            ON UPDATE CASCADE ON DELETE RESTRICT;
    END IF;

    -- --------------------------------------------------------
    -- activity_logs + notifications
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS activity_logs (
        activity_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NULL,
        action VARCHAR(100) NOT NULL,
        details VARCHAR(255) NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_activity_user
            FOREIGN KEY (user_id) REFERENCES users(user_id)
            ON UPDATE CASCADE ON DELETE SET NULL,
        INDEX idx_activity_date (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS notifications (
        notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        customer_id INT UNSIGNED NULL,
        user_id INT UNSIGNED NULL,
        order_id INT UNSIGNED NULL,
        title VARCHAR(120) NOT NULL,
        message VARCHAR(255) NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_notifications_customer
            FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
            ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_notifications_user
            FOREIGN KEY (user_id) REFERENCES users(user_id)
            ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_notifications_order
            FOREIGN KEY (order_id) REFERENCES orders(order_id)
            ON UPDATE CASCADE ON DELETE SET NULL,
        INDEX idx_notifications_customer_read (customer_id, is_read, created_at),
        INDEX idx_notifications_user_read (user_id, is_read, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activity_logs'
    ) THEN
        IF EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activity_logs' AND COLUMN_NAME = 'id'
        ) AND NOT EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activity_logs' AND COLUMN_NAME = 'activity_id'
        ) THEN
            ALTER TABLE activity_logs
                CHANGE COLUMN id activity_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;
        END IF;
        IF EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activity_logs' AND COLUMN_NAME = 'actor_user_id'
        ) AND NOT EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activity_logs' AND COLUMN_NAME = 'user_id'
        ) THEN
            ALTER TABLE activity_logs
                CHANGE COLUMN actor_user_id user_id INT UNSIGNED NULL;
        END IF;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications'
    ) THEN
        IF EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'id'
        ) AND NOT EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'notification_id'
        ) THEN
            ALTER TABLE notifications
                CHANGE COLUMN id notification_id INT UNSIGNED NOT NULL AUTO_INCREMENT;
        END IF;
        IF NOT EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'order_id'
        ) THEN
            ALTER TABLE notifications ADD COLUMN order_id INT UNSIGNED NULL;
        END IF;
    END IF;

    -- --------------------------------------------------------
    -- settings
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS settings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(80) NOT NULL UNIQUE,
        setting_value TEXT NULL,
        updated_by_user_id INT UNSIGNED NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_settings_user
            FOREIGN KEY (updated_by_user_id) REFERENCES users(user_id)
            ON UPDATE CASCADE ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    INSERT INTO settings (setting_key, setting_value, updated_by_user_id)
    VALUES
        ('station_name', 'AquaPure Water Refilling Station', NULL),
        ('station_contact', '', NULL),
        ('station_address', '', NULL),
        ('business_hours', 'Monday to Saturday, 8:00 AM-6:00 PM', NULL)
    ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

    -- --------------------------------------------------------
    -- Unified, secure registration invitations
    -- --------------------------------------------------------
    CREATE TABLE IF NOT EXISTS user_invitations (
        invitation_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        token_hash CHAR(64) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL,
        role ENUM('Admin','Staff') NOT NULL,
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        used_by INT UNSIGNED NULL,
        revoked_at DATETIME NULL,
        status ENUM('Active','Used','Expired','Revoked') NOT NULL DEFAULT 'Active',
        CONSTRAINT fk_user_invitation_creator
            FOREIGN KEY (created_by) REFERENCES users(user_id)
            ON UPDATE CASCADE ON DELETE RESTRICT,
        CONSTRAINT fk_user_invitation_used_by
            FOREIGN KEY (used_by) REFERENCES users(user_id)
            ON UPDATE CASCADE ON DELETE SET NULL,
        INDEX idx_user_invitation_status_expiry (status, expires_at),
        INDEX idx_user_invitation_email_role (email, role),
        INDEX idx_user_invitation_creator (created_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Copy only email-specific legacy invitations. Old unrestricted codes with
    -- NULL email are intentionally NOT activated in the new secure system.
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff_invitations'
    ) THEN
        INSERT IGNORE INTO user_invitations
            (token_hash, email, role, created_by, created_at, expires_at, used_at, status)
        SELECT
            token_hash,
            LOWER(email),
            'Staff',
            created_by,
            created_at,
            expires_at,
            used_at,
            CASE
                WHEN used_at IS NOT NULL OR status = 'Used' THEN 'Used'
                WHEN status = 'Revoked' THEN 'Revoked'
                WHEN expires_at <= NOW() OR status = 'Expired' THEN 'Expired'
                ELSE 'Active'
            END
        FROM staff_invitations
        WHERE email IS NOT NULL AND TRIM(email) <> '';
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_invitations'
    ) THEN
        INSERT IGNORE INTO user_invitations
            (token_hash, email, role, created_by, created_at, expires_at, used_at, status)
        SELECT
            token_hash,
            LOWER(email),
            'Admin',
            created_by,
            created_at,
            expires_at,
            used_at,
            CASE
                WHEN used_at IS NOT NULL OR status = 'Used' THEN 'Used'
                WHEN status = 'Revoked' THEN 'Revoked'
                WHEN expires_at <= NOW() OR status = 'Expired' THEN 'Expired'
                ELSE 'Active'
            END
        FROM admin_invitations
        WHERE email IS NOT NULL AND TRIM(email) <> '';
    END IF;

    UPDATE user_invitations
    SET status = 'Expired'
    WHERE status = 'Active' AND expires_at <= NOW();

    -- --------------------------------------------------------
    -- Add critical uniqueness/indexes only when safe.
    -- Existing duplicate data is left untouched rather than deleted.
    -- --------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'username' AND NON_UNIQUE = 0
    ) AND NOT EXISTS (
        SELECT username FROM users GROUP BY username HAVING COUNT(*) > 1 LIMIT 1
    ) THEN
        ALTER TABLE users ADD UNIQUE INDEX uq_users_username (username);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'email' AND NON_UNIQUE = 0
    ) AND NOT EXISTS (
        SELECT email FROM users GROUP BY email HAVING COUNT(*) > 1 LIMIT 1
    ) THEN
        ALTER TABLE users ADD UNIQUE INDEX uq_users_email (email);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'
          AND COLUMN_NAME = 'email' AND NON_UNIQUE = 0
    ) AND NOT EXISTS (
        SELECT email FROM customers
        WHERE email IS NOT NULL
        GROUP BY email HAVING COUNT(*) > 1 LIMIT 1
    ) THEN
        ALTER TABLE customers ADD UNIQUE INDEX uq_customers_email (email);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments'
          AND COLUMN_NAME = 'order_id' AND NON_UNIQUE = 0
    ) AND NOT EXISTS (
        SELECT order_id FROM payments GROUP BY order_id HAVING COUNT(*) > 1 LIMIT 1
    ) THEN
        ALTER TABLE payments ADD UNIQUE INDEX uq_payments_order_id (order_id);
    END IF;

    -- Normalize engine/charset on the current core tables without deleting rows.
    ALTER TABLE users ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ALTER TABLE customers ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ALTER TABLE products ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ALTER TABLE orders ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ALTER TABLE payments ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

END//
DELIMITER ;

CALL aquapure_upgrade_existing();
DROP PROCEDURE IF EXISTS aquapure_upgrade_existing;

-- Verification summary. Review these rows after the migration completes.
SELECT DATABASE() AS migrated_database;
SELECT
    (SELECT COUNT(*) FROM users) AS users_count,
    (SELECT COUNT(*) FROM customers) AS customers_count,
    (SELECT COUNT(*) FROM products) AS products_count,
    (SELECT COUNT(*) FROM orders) AS orders_count,
    (SELECT COUNT(*) FROM payments) AS payments_count,
    (SELECT COUNT(*) FROM user_invitations) AS invitations_count;

SELECT 'AquaPure migration completed. Do not run database/aquapure.sql over this existing database.' AS result;
