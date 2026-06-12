-- ============================================================
-- Database: projectuask3
-- E-Procurement System
-- ============================================================

DROP DATABASE IF EXISTS `projectuask3`;
CREATE DATABASE `projectuask3`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `projectuask3`;

-- ============================================================
-- Tabel: divisions
-- ============================================================
CREATE TABLE `divisions` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `division_name`   VARCHAR(100)    NOT NULL,
    `budget_annual`   DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `budget_used`     DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_division_name` (`division_name`)
) ENGINE=InnoDB;

-- ============================================================
-- Tabel: users
-- ============================================================
CREATE TABLE `users` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `username`        VARCHAR(50)     NOT NULL,
    `email`           VARCHAR(100)    NOT NULL,
    `password_hash`   VARCHAR(255)    NOT NULL,
    `role`            ENUM('admin','manager','staff') NOT NULL DEFAULT 'staff',
    `division_id`     INT UNSIGNED    NOT NULL,
    `is_active`       TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_username` (`username`),
    UNIQUE KEY `uq_email` (`email`),
    CONSTRAINT `fk_users_division` FOREIGN KEY (`division_id`) REFERENCES `divisions`(`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- Tabel: vendors
-- ============================================================
CREATE TABLE `vendors` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `vendor_name`     VARCHAR(150)    NOT NULL,
    `contact_person`  VARCHAR(100)    NULL,
    `phone`           VARCHAR(20)     NULL,
    `email`           VARCHAR(100)    NULL,
    `address`         TEXT            NULL,
    `is_active`       TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_vendor_name` (`vendor_name`)
) ENGINE=InnoDB;

-- ============================================================
-- Tabel: procurement_orders (Master / Header)
-- ============================================================
CREATE TABLE `procurement_orders` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `po_number`       VARCHAR(30)     NOT NULL,
    `division_id`     INT UNSIGNED    NOT NULL,
    `vendor_id`       INT UNSIGNED    NOT NULL,
    `requested_by`    INT UNSIGNED    NOT NULL,
    `approved_by`     INT UNSIGNED    NULL,
    `order_date`      DATE            NOT NULL,
    `expected_date`   DATE            NULL,
    `total_amount`    DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `status`          ENUM('Pending','Approved by Manager','Ordered to Vendor','Received','Rejected')
                      NOT NULL DEFAULT 'Pending',
    `notes`           TEXT            NULL,
    `rejection_note`  TEXT            NULL,
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_po_number` (`po_number`),
    INDEX `idx_status` (`status`),
    INDEX `idx_division` (`division_id`),
    INDEX `idx_order_date` (`order_date`),
    CONSTRAINT `fk_po_division` FOREIGN KEY (`division_id`) REFERENCES `divisions`(`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_po_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_po_requester` FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_po_approver` FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- Tabel: procurement_items (Detail)
-- ============================================================
CREATE TABLE `procurement_items` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `order_id`        INT UNSIGNED    NOT NULL,
    `item_name`       VARCHAR(200)    NOT NULL,
    `specification`   VARCHAR(500)    NULL DEFAULT '',
    `unit`            VARCHAR(20)     NOT NULL DEFAULT 'pcs',
    `quantity`        INT UNSIGNED    NOT NULL DEFAULT 1,
    `unit_price`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_order_id` (`order_id`),
    CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `procurement_orders`(`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- Data Awal: Divisions
-- ============================================================
INSERT INTO `divisions` (`division_name`, `budget_annual`, `budget_used`) VALUES
    ('IT & Teknologi',       500000000.00, 0.00),
    ('Human Resources',      300000000.00, 0.00),
    ('Finance & Accounting', 250000000.00, 0.00),
    ('Marketing & Sales',    400000000.00, 0.00),
    ('Operations',           350000000.00, 0.00);

-- ============================================================
-- Data Awal: Vendors
-- ============================================================
INSERT INTO `vendors` (`vendor_name`, `contact_person`, `phone`, `email`, `address`) VALUES
    ('PT Sumber Tekno',     'Budi Santoso',    '081234567890', 'budi@sumbertekno.co.id',    'Jl. Sudirman No. 10, Jakarta'),
    ('CV Maju Bersama',     'Siti Aminah',     '082198765432', 'siti@majubersama.com',      'Jl. Gatot Subroto No. 25, Bandung'),
    ('PT Global Supply',    'Andi Wijaya',     '085312345678', 'andi@globalsupply.id',      'Jl. HR Rasuna Said No. 5, Jakarta'),
    ('UD Berkah Makmur',    'Dewi Lestari',    '087654321098', 'dewi@berkahmakmur.co.id',   'Jl. Ahmad Yani No. 88, Surabaya'),
    ('PT Indo Material',    'Reza Pratama',    '089876543210', 'reza@indomaterial.com',     'Jl. Diponegoro No. 15, Semarang');

-- ============================================================
-- Data Awal: Users
-- Password semua user: "password123"
-- ============================================================
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `division_id`) VALUES
    ('admin',       'admin@eprocurement.local',      '482c811da5d5b4bc6d497ffa98491e38', 'admin',   1),
    ('manager_it',  'manager.it@eprocurement.local', '482c811da5d5b4bc6d497ffa98491e38', 'manager', 1),
    ('staff_it',    'staff.it@eprocurement.local',   '482c811da5d5b4bc6d497ffa98491e38', 'staff',   1),
    ('manager_hr',  'manager.hr@eprocurement.local', '482c811da5d5b4bc6d497ffa98491e38', 'manager', 2),
    ('staff_hr',    'staff.hr@eprocurement.local',   '482c811da5d5b4bc6d497ffa98491e38', 'staff',   2),
    ('manager_fin', 'manager.fin@eprocurement.local','482c811da5d5b4bc6d497ffa98491e38', 'manager', 3),
    ('staff_fin',   'staff.fin@eprocurement.local',  '482c811da5d5b4bc6d497ffa98491e38', 'staff',   3),
    ('manager_mkt', 'manager.mkt@eprocurement.local','482c811da5d5b4bc6d497ffa98491e38', 'manager', 4),
    ('staff_mkt',   'staff.mkt@eprocurement.local',  '482c811da5d5b4bc6d497ffa98491e38', 'staff',   4),
    ('manager_ops', 'manager.ops@eprocurement.local','482c811da5d5b4bc6d497ffa98491e38', 'manager', 5),
    ('staff_ops',   'staff.ops@eprocurement.local',  '482c811da5d5b4bc6d497ffa98491e38', 'staff',   5);
