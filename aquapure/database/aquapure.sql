-- ============================================================
-- AquaPure Water Refilling Station Management System
-- FRESH INSTALLATION DATABASE - MySQL 8 / MariaDB / XAMPP
-- ============================================================
-- SAFETY WARNING:
--   Use this file only for a fresh/empty AquaPure installation.
--   It NEVER drops an existing database or table.
--   For an existing AquaPure database, back it up and run:
--   database/migrations/20260818_upgrade_existing_aquapure.sql
--
-- Demo password for seeded login accounts: AquaPure123!
-- Change demo passwords after first login before real deployment.
-- ============================================================

CREATE DATABASE IF NOT EXISTS aquapure_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE aquapure_db;

-- Refuse to silently overwrite a populated AquaPure database.
DELIMITER //
DROP PROCEDURE IF EXISTS aquapure_assert_fresh_install//
CREATE PROCEDURE aquapure_assert_fresh_install()
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME IN ('users','customers','orders','products')
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Fresh AquaPure installer stopped: existing tables found. Use the migration SQL instead.';
    END IF;
END//
DELIMITER ;
CALL aquapure_assert_fresh_install();
DROP PROCEDURE aquapure_assert_fresh_install;


CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(30) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    contact_number VARCHAR(30) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Admin','Staff') NOT NULL,
    account_status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role_status (role, account_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_invitations (
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

CREATE TABLE customers (
    customer_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NULL UNIQUE,
    contact_number VARCHAR(20) NOT NULL,
    address VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NULL,
    customer_type ENUM('Online Customer','Walk-in Customer','Residential','Business') NOT NULL DEFAULT 'Online Customer',
    account_status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customers_status (account_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    product_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    category ENUM('Refill','Bottled Water','Container','Accessory') NOT NULL,
    price DECIMAL(10,2) UNSIGNED NOT NULL,
    quantity_available INT UNSIGNED NOT NULL DEFAULT 0,
    image_path VARCHAR(255) NULL,
    status ENUM('Available','Unavailable') NOT NULL DEFAULT 'Available',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_products_status_stock (status, quantity_available),
    INDEX idx_products_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    order_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    processed_by INT UNSIGNED NULL,
    order_source ENUM('Online','Walk-in') NOT NULL DEFAULT 'Online',
    order_type ENUM('Walk-in','Pickup','Delivery') NOT NULL,
    total_amount DECIMAL(10,2) UNSIGNED NOT NULL,
    payment_status ENUM('Unpaid','Partial','Paid','Refunded') NOT NULL DEFAULT 'Unpaid',
    order_status ENUM('Pending','Confirmed','Preparing','Ready for Pickup','Out for Delivery','Delivered','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
    notes TEXT NULL,
    order_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_orders_processor FOREIGN KEY (processed_by) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_orders_date (order_date),
    INDEX idx_orders_status (order_status),
    INDEX idx_orders_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
    order_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(10,2) UNSIGNED NOT NULL,
    subtotal DECIMAL(10,2) UNSIGNED NOT NULL,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_order_items_order (order_id),
    INDEX idx_order_items_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
    payment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL UNIQUE,
    amount_due DECIMAL(10,2) UNSIGNED NOT NULL,
    amount_paid DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
    change_amount DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
    payment_method ENUM('Cash','GCash','Maya','PayMongo') NOT NULL DEFAULT 'Cash',
    reference_number VARCHAR(100) NULL UNIQUE,
    payment_date DATETIME NULL,
    payment_status ENUM('Unpaid','Partial','Paid','Refunded') NOT NULL DEFAULT 'Unpaid',
    recorded_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_payments_recorder FOREIGN KEY (recorded_by) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_payments_status_date (payment_status, payment_date),
    INDEX idx_payments_method (payment_method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE deliveries (
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
    CONSTRAINT fk_deliveries_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_deliveries_staff FOREIGN KEY (assigned_staff_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_deliveries_date (preferred_delivery_date),
    INDEX idx_deliveries_status (delivery_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory (
    inventory_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(120) NOT NULL UNIQUE,
    category VARCHAR(80) NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    unit VARCHAR(30) NOT NULL,
    reorder_level INT UNSIGNED NOT NULL DEFAULT 10,
    status ENUM('In Stock','Low Stock','Out of Stock') NOT NULL DEFAULT 'In Stock',
    updated_by INT UNSIGNED NULL,
    last_updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_user FOREIGN KEY (updated_by) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_transactions (
    transaction_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT UNSIGNED NOT NULL,
    recorded_by INT UNSIGNED NULL,
    transaction_type ENUM('Stock In','Stock Out','Adjustment') NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    remarks VARCHAR(255) NULL,
    transaction_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_transactions_item FOREIGN KEY (inventory_id) REFERENCES inventory(inventory_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_transactions_user FOREIGN KEY (recorded_by) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_inventory_transactions_item_date (inventory_id, transaction_date),
    INDEX idx_inventory_transactions_date (transaction_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    order_id INT UNSIGNED NULL,
    title VARCHAR(120) NOT NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_notifications_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_notifications_customer_read (customer_id, is_read, created_at),
    INDEX idx_notifications_user_read (user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_logs (
    activity_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    details VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_activity_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(80) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_by_user_id INT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_settings_user FOREIGN KEY (updated_by_user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3 system users only (demo password: AquaPure123!)
INSERT INTO users (full_name, username, email, password_hash, role, account_status) VALUES
('Maria Aquino', 'admin', 'admin@aquapure.local', '$2y$12$c5HO20hmWT9OaUkUQ9WldeDA7L2ihvG1LC01f1qFoNaXqJf3EKLLG', 'Admin', 'Active'),
('John Mercado', 'staff1', 'staff1@aquapure.local', '$2y$12$c5HO20hmWT9OaUkUQ9WldeDA7L2ihvG1LC01f1qFoNaXqJf3EKLLG', 'Staff', 'Active'),
('Liza Garcia', 'staff2', 'staff2@aquapure.local', '$2y$12$c5HO20hmWT9OaUkUQ9WldeDA7L2ihvG1LC01f1qFoNaXqJf3EKLLG', 'Staff', 'Active');

-- 10 customers (demo password: AquaPure123! for online sample accounts)
INSERT INTO customers (full_name, email, contact_number, address, password_hash, customer_type, account_status) VALUES
('Angela Reyes', 'angela@example.com', '09201110001', 'Poblacion, Buenavista, Bohol', '$2y$12$c5HO20hmWT9OaUkUQ9WldeDA7L2ihvG1LC01f1qFoNaXqJf3EKLLG', 'Online Customer', 'Active'),
('Brian Santos', 'brian@example.com', '09201110002', 'Cantores, Buenavista, Bohol', '$2y$12$c5HO20hmWT9OaUkUQ9WldeDA7L2ihvG1LC01f1qFoNaXqJf3EKLLG', 'Residential', 'Active'),
('Carla Mendoza', 'carla@example.com', '09201110003', 'Poblacion, Inabanga, Bohol', '$2y$12$c5HO20hmWT9OaUkUQ9WldeDA7L2ihvG1LC01f1qFoNaXqJf3EKLLG', 'Online Customer', 'Active'),
('Daniel Flores', 'daniel@example.com', '09201110004', 'Cangawa, Buenavista, Bohol', '$2y$12$c5HO20hmWT9OaUkUQ9WldeDA7L2ihvG1LC01f1qFoNaXqJf3EKLLG', 'Residential', 'Active'),
('Erika Ramos', 'erika@example.com', '09201110005', 'Putingbato, Buenavista, Bohol', '$2y$12$c5HO20hmWT9OaUkUQ9WldeDA7L2ihvG1LC01f1qFoNaXqJf3EKLLG', 'Online Customer', 'Active'),
('Francis Lim', NULL, '09201110006', 'Poblacion, Getafe, Bohol', NULL, 'Walk-in Customer', 'Active'),
('Grace Villanueva', 'grace@example.com', '09201110007', 'Overland, Buenavista, Bohol', '$2y$12$c5HO20hmWT9OaUkUQ9WldeDA7L2ihvG1LC01f1qFoNaXqJf3EKLLG', 'Business', 'Active'),
('Henry Cruz', NULL, '09201110008', 'Lapacan Norte, Inabanga, Bohol', NULL, 'Walk-in Customer', 'Active'),
('Irene Bautista', 'irene@example.com', '09201110009', 'Rufo Hill, Buenavista, Bohol', '$2y$12$c5HO20hmWT9OaUkUQ9WldeDA7L2ihvG1LC01f1qFoNaXqJf3EKLLG', 'Online Customer', 'Active'),
('Joshua Dela Cruz', 'joshua@example.com', '09201110010', 'Poblacion, Talibon, Bohol', '$2y$12$c5HO20hmWT9OaUkUQ9WldeDA7L2ihvG1LC01f1qFoNaXqJf3EKLLG', 'Business', 'Active');

INSERT INTO settings (setting_key, setting_value, updated_by_user_id) VALUES
('station_name', 'AquaPure Water Refilling Station', NULL),
('station_contact', '', NULL),
('station_address', '', NULL),
('business_hours', 'Monday to Saturday, 8:00 AM-6:00 PM', NULL);

-- 10 products
INSERT INTO products (product_name, description, category, price, quantity_available, status) VALUES
('5 Gallon Purified Water Refill', 'Purified water refill for a standard gallon container.', 'Refill', 50.00, 120, 'Available'),
('5 Gallon Mineral Water Refill', 'Mineral water refill for a standard gallon container.', 'Refill', 55.00, 90, 'Available'),
('5 Gallon Alkaline Water Refill', 'Alkaline water refill for a standard gallon container.', 'Refill', 60.00, 80, 'Available'),
('5 Gallon Container with Water', 'New reusable gallon container filled with purified water.', 'Container', 500.00, 25, 'Available'),
('1 Liter Bottled Purified Water', 'Sealed one-liter bottle of purified water.', 'Bottled Water', 25.00, 150, 'Available'),
('500ml Bottled Purified Water', 'Sealed 500ml bottle of purified water.', 'Bottled Water', 15.00, 240, 'Available'),
('350ml Bottled Mineral Water', 'Sealed 350ml bottle of mineral water.', 'Bottled Water', 12.00, 300, 'Available'),
('Empty 5 Gallon Container', 'New empty reusable five-gallon container.', 'Container', 450.00, 30, 'Available'),
('Manual Gallon Pump', 'Manual pump dispenser for a gallon container.', 'Accessory', 350.00, 20, 'Available'),
('Electric Gallon Pump', 'Rechargeable electric pump dispenser.', 'Accessory', 950.00, 12, 'Available');

-- 15 sample orders: 10 delivery, 3 pickup, and 2 walk-in
INSERT INTO orders (customer_id, processed_by, order_source, order_type, total_amount, payment_status, order_status, notes, order_date) VALUES
(1, 2, 'Online', 'Delivery', 100.00, 'Paid', 'Completed', 'Deliver before noon.', '2026-08-01 08:15:00'),
(2, 2, 'Online', 'Delivery', 165.00, 'Paid', 'Completed', 'Call upon arrival.', '2026-08-01 09:10:00'),
(3, 3, 'Online', 'Delivery', 120.00, 'Paid', 'Out for Delivery', NULL, '2026-08-02 10:00:00'),
(4, 2, 'Online', 'Delivery', 300.00, 'Paid', 'Preparing', NULL, '2026-08-03 11:20:00'),
(5, 2, 'Online', 'Delivery', 360.00, 'Unpaid', 'Confirmed', 'Official receipt requested.', '2026-08-04 13:05:00'),
(6, 1, 'Online', 'Delivery', 360.00, 'Partial', 'Pending', 'Waiting for balance.', '2026-08-05 14:30:00'),
(7, 3, 'Online', 'Delivery', 500.00, 'Paid', 'Completed', NULL, '2026-08-06 15:10:00'),
(8, 2, 'Online', 'Delivery', 450.00, 'Paid', 'Preparing', NULL, '2026-08-07 16:00:00'),
(9, 1, 'Online', 'Delivery', 350.00, 'Paid', 'Confirmed', 'Afternoon delivery.', '2026-08-08 08:45:00'),
(10, 2, 'Online', 'Delivery', 950.00, 'Refunded', 'Cancelled', 'Cancelled by customer.', '2026-08-09 09:25:00'),
(1, 2, 'Online', 'Pickup', 110.00, 'Paid', 'Ready for Pickup', NULL, '2026-08-10 08:00:00'),
(3, 3, 'Online', 'Pickup', 150.00, 'Unpaid', 'Confirmed', NULL, '2026-08-10 09:15:00'),
(5, 2, 'Online', 'Pickup', 500.00, 'Paid', 'Completed', NULL, '2026-08-10 10:30:00'),
(6, 2, 'Walk-in', 'Walk-in', 75.00, 'Paid', 'Completed', NULL, '2026-08-11 11:20:00'),
(8, 3, 'Walk-in', 'Walk-in', 60.00, 'Paid', 'Completed', NULL, '2026-08-11 13:40:00');

INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) VALUES
(1,1,2,50.00,100.00),
(2,2,3,55.00,165.00),
(3,3,2,60.00,120.00),
(4,5,12,25.00,300.00),
(5,6,24,15.00,360.00),
(6,7,30,12.00,360.00),
(7,4,1,500.00,500.00),
(8,8,1,450.00,450.00),
(9,9,1,350.00,350.00),
(10,10,1,950.00,950.00),
(11,1,1,50.00,50.00),
(11,3,1,60.00,60.00),
(12,5,6,25.00,150.00),
(13,4,1,500.00,500.00),
(14,5,3,25.00,75.00),
(15,6,4,15.00,60.00);

INSERT INTO payments (order_id, amount_due, amount_paid, change_amount, payment_method, reference_number, payment_date, payment_status, recorded_by) VALUES
(1,100.00,100.00,0.00,'Cash',NULL,'2026-08-01 11:30:00','Paid',2),
(2,165.00,165.00,0.00,'GCash','GCASH-20260801-0002','2026-08-01 09:20:00','Paid',2),
(3,120.00,120.00,0.00,'Maya','MAYA-20260802-0003','2026-08-02 10:05:00','Paid',3),
(4,300.00,300.00,0.00,'GCash','GCASH-20260803-0004','2026-08-03 11:35:00','Paid',2),
(5,360.00,0.00,0.00,'Cash',NULL,NULL,'Unpaid',NULL),
(6,360.00,180.00,0.00,'GCash','GCASH-20260805-0006','2026-08-05 14:35:00','Partial',1),
(7,500.00,500.00,0.00,'Cash',NULL,'2026-08-06 17:40:00','Paid',3),
(8,450.00,450.00,0.00,'Maya','MAYA-20260807-0008','2026-08-07 16:10:00','Paid',2),
(9,350.00,350.00,0.00,'GCash','GCASH-20260808-0009','2026-08-08 08:55:00','Paid',1),
(10,950.00,950.00,0.00,'GCash','GCASH-20260809-0010','2026-08-09 09:35:00','Refunded',2),
(11,110.00,110.00,0.00,'Cash',NULL,'2026-08-10 08:10:00','Paid',2),
(12,150.00,0.00,0.00,'Cash',NULL,NULL,'Unpaid',NULL),
(13,500.00,500.00,0.00,'Maya','MAYA-20260810-0013','2026-08-10 10:40:00','Paid',2),
(14,75.00,100.00,25.00,'Cash',NULL,'2026-08-11 11:22:00','Paid',2),
(15,60.00,60.00,0.00,'Cash',NULL,'2026-08-11 13:42:00','Paid',3);

-- 10 delivery records
INSERT INTO deliveries (order_id, recipient_name, contact_number, delivery_address, preferred_delivery_date, additional_instructions, assigned_staff_id, delivery_status, delivered_at) VALUES
(1,'Angela Reyes','09201110001','Poblacion, Buenavista, Bohol','2026-08-01','Deliver before noon.',2,'Delivered','2026-08-01 11:25:00'),
(2,'Brian Santos','09201110002','Cantores, Buenavista, Bohol','2026-08-01','Call upon arrival.',3,'Delivered','2026-08-01 14:20:00'),
(3,'Carla Mendoza','09201110003','Poblacion, Inabanga, Bohol','2026-08-02',NULL,3,'Out for Delivery',NULL),
(4,'Daniel Flores','09201110004','Cangawa, Buenavista, Bohol','2026-08-03',NULL,2,'Preparing',NULL),
(5,'Erika Ramos','09201110005','Putingbato, Buenavista, Bohol','2026-08-04','Official receipt requested.',2,'Confirmed',NULL),
(6,'Francis Lim','09201110006','Poblacion, Getafe, Bohol','2026-08-05','Waiting for balance.',NULL,'Pending',NULL),
(7,'Grace Villanueva','09201110007','Overland, Buenavista, Bohol','2026-08-06',NULL,3,'Delivered','2026-08-06 17:38:00'),
(8,'Henry Cruz','09201110008','Lapacan Norte, Inabanga, Bohol','2026-08-07',NULL,2,'Preparing',NULL),
(9,'Irene Bautista','09201110009','Rufo Hill, Buenavista, Bohol','2026-08-08','Afternoon delivery.',1,'Confirmed',NULL),
(10,'Joshua Dela Cruz','09201110010','Poblacion, Talibon, Bohol','2026-08-09','Cancelled by customer.',2,'Cancelled',NULL);

-- 10 inventory items
INSERT INTO inventory (item_name, category, quantity, unit, reorder_level, status, updated_by) VALUES
('5 Gallon Containers','Containers',40,'pcs',10,'In Stock',2),
('1 Liter Bottles','Bottles',200,'pcs',50,'In Stock',2),
('500ml Bottles','Bottles',45,'pcs',50,'Low Stock',3),
('Bottle Caps','Packaging',500,'pcs',100,'In Stock',2),
('Safety Seals','Packaging',0,'pcs',100,'Out of Stock',1),
('Cleaning Solution','Cleaning Materials',12,'liters',5,'In Stock',3),
('Sediment Filters','Filters',6,'pcs',8,'Low Stock',2),
('Carbon Filters','Filters',10,'pcs',5,'In Stock',2),
('Plastic Carry Bags','Packaging',150,'pcs',40,'In Stock',3),
('Sanitizing Cloths','Cleaning Materials',25,'pcs',10,'In Stock',1);

INSERT INTO inventory_transactions (inventory_id, recorded_by, transaction_type, quantity, remarks, transaction_date) VALUES
(1,2,'Stock In',40,'Initial gallon container count.','2026-07-28 08:00:00'),
(2,2,'Stock In',200,'One-liter bottle delivery.','2026-07-28 08:15:00'),
(3,3,'Adjustment',45,'Physical count adjustment.','2026-07-28 08:30:00'),
(4,2,'Stock In',500,'Bottle caps received.','2026-07-29 09:00:00'),
(5,1,'Stock Out',100,'Safety seals consumed.','2026-07-29 09:30:00'),
(6,3,'Stock In',12,'Cleaning solution received.','2026-07-30 10:00:00'),
(7,2,'Adjustment',6,'Filter count verified.','2026-07-30 10:30:00'),
(8,2,'Stock In',10,'Carbon filters received.','2026-07-31 11:00:00'),
(9,3,'Stock In',150,'Carry bags received.','2026-07-31 11:30:00'),
(10,1,'Stock In',25,'Sanitizing cloths received.','2026-07-31 12:00:00');

-- 10 customer and system notifications
INSERT INTO notifications (customer_id, user_id, order_id, title, message, is_read, created_at) VALUES
(1,NULL,1,'Order completed','AQ-000001 was delivered and completed.',1,'2026-08-01 11:26:00'),
(2,NULL,2,'Order completed','AQ-000002 was delivered and completed.',1,'2026-08-01 14:21:00'),
(3,NULL,3,'Out for delivery','AQ-000003 is on the way.',0,'2026-08-02 14:50:00'),
(4,NULL,4,'Order preparing','AQ-000004 is being prepared.',0,'2026-08-03 12:00:00'),
(5,NULL,5,'Order confirmed','AQ-000005 was confirmed.',0,'2026-08-04 13:20:00'),
(6,NULL,6,'Payment pending','Please complete the balance for AQ-000006.',0,'2026-08-05 14:40:00'),
(NULL,1,NULL,'Low inventory','Safety Seals are out of stock.',0,'2026-08-06 08:00:00'),
(NULL,2,8,'Pending delivery','AQ-000008 needs delivery processing.',0,'2026-08-07 16:20:00'),
(NULL,NULL,9,'New online order','AQ-000009 was submitted for delivery.',0,'2026-08-08 08:45:00'),
(10,NULL,10,'Order cancelled','AQ-000010 was cancelled and marked for refund.',0,'2026-08-09 09:40:00');

-- 10 system activity records
INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) VALUES
(1,'Login','Admin signed in.','127.0.0.1','2026-08-01 07:50:00'),
(2,'Order status updated','Order ID 1 set to Completed.','127.0.0.1','2026-08-01 11:26:00'),
(3,'Delivery updated','Delivery ID 2 set to Delivered.','127.0.0.1','2026-08-01 14:21:00'),
(3,'Order status updated','Order ID 3 set to Out for Delivery.','127.0.0.1','2026-08-02 14:50:00'),
(2,'Product saved','Product ID 5 stock reviewed.','127.0.0.1','2026-08-03 08:00:00'),
(2,'Payment recorded','Order ID 4 marked Paid.','127.0.0.1','2026-08-03 11:35:00'),
(1,'Inventory saved','Inventory ID 5 marked Out of Stock.','127.0.0.1','2026-08-06 08:00:00'),
(2,'Customer saved','Customer ID 8 updated.','127.0.0.1','2026-08-07 09:00:00'),
(1,'Order status updated','Order ID 9 confirmed.','127.0.0.1','2026-08-08 09:00:00'),
(2,'Order status updated','Order ID 10 cancelled.','127.0.0.1','2026-08-09 09:40:00');

-- Verification: users should show 3; every other table shows at least 10.
SELECT 'users' AS table_name, COUNT(*) AS sample_rows FROM users
UNION ALL SELECT 'customers', COUNT(*) FROM customers
UNION ALL SELECT 'products', COUNT(*) FROM products
UNION ALL SELECT 'orders', COUNT(*) FROM orders
UNION ALL SELECT 'order_items', COUNT(*) FROM order_items
UNION ALL SELECT 'payments', COUNT(*) FROM payments
UNION ALL SELECT 'deliveries', COUNT(*) FROM deliveries
UNION ALL SELECT 'inventory', COUNT(*) FROM inventory
UNION ALL SELECT 'inventory_transactions', COUNT(*) FROM inventory_transactions
UNION ALL SELECT 'notifications', COUNT(*) FROM notifications
UNION ALL SELECT 'activity_logs', COUNT(*) FROM activity_logs;
