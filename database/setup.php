<?php
declare(strict_types=1);

$host = 'localhost';
$user = 'root';
$pass = '';
$port = 3306;

$results = [];

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo->exec("DROP DATABASE IF EXISTS `projectuask3`");
    $results[] = "✅ DROP DATABASE IF EXISTS projectuask3";

    $pdo->exec("CREATE DATABASE `projectuask3` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $results[] = "✅ CREATE DATABASE projectuask3";

    $pdo->exec("USE `projectuask3`");
    $results[] = "✅ USE projectuask3";

    $pdo->exec("
        CREATE TABLE `divisions` (
            `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
            `division_name`   VARCHAR(100)    NOT NULL,
            `budget_annual`   DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
            `budget_used`     DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
            `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_division_name` (`division_name`)
        ) ENGINE=InnoDB
    ");
    $results[] = "✅ CREATE TABLE divisions";

    $pdo->exec("
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
        ) ENGINE=InnoDB
    ");
    $results[] = "✅ CREATE TABLE users";

    $pdo->exec("
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
        ) ENGINE=InnoDB
    ");
    $results[] = "✅ CREATE TABLE vendors";

    $pdo->exec("
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
        ) ENGINE=InnoDB
    ");
    $results[] = "✅ CREATE TABLE procurement_orders";

    $pdo->exec("
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
        ) ENGINE=InnoDB
    ");
    $results[] = "✅ CREATE TABLE procurement_items";

    $pdo->exec("
        INSERT INTO `divisions` (`division_name`, `budget_annual`, `budget_used`) VALUES
            ('IT & Teknologi',       500000000.00, 0.00),
            ('Human Resources',      300000000.00, 0.00),
            ('Finance & Accounting', 250000000.00, 0.00),
            ('Marketing & Sales',    400000000.00, 0.00),
            ('Operations',           350000000.00, 0.00)
    ");
    $results[] = "✅ INSERT divisions (5 rows)";

    $pdo->exec("
        INSERT INTO `vendors` (`vendor_name`, `contact_person`, `phone`, `email`, `address`) VALUES
            ('PT Sumber Tekno',     'Budi Santoso',    '081234567890', 'budi@sumbertekno.co.id',    'Jl. Sudirman No. 10, Jakarta'),
            ('CV Maju Bersama',     'Siti Aminah',     '082198765432', 'siti@majubersama.com',      'Jl. Gatot Subroto No. 25, Bandung'),
            ('PT Global Supply',    'Andi Wijaya',     '085312345678', 'andi@globalsupply.id',      'Jl. HR Rasuna Said No. 5, Jakarta'),
            ('UD Berkah Makmur',    'Dewi Lestari',    '087654321098', 'dewi@berkahmakmur.co.id',   'Jl. Ahmad Yani No. 88, Surabaya'),
            ('PT Indo Material',    'Reza Pratama',    '089876543210', 'reza@indomaterial.com',     'Jl. Diponegoro No. 15, Semarang')
    ");
    $results[] = "✅ INSERT vendors (5 rows)";

    $hash = md5('password123');

    $pdo->exec("
        INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `division_id`) VALUES
            ('admin',       'admin@eprocurement.local',       '{$hash}', 'admin',   1),
            ('manager_it',  'manager.it@eprocurement.local',  '{$hash}', 'manager', 1),
            ('staff_it',    'staff.it@eprocurement.local',    '{$hash}', 'staff',   1),
            ('manager_hr',  'manager.hr@eprocurement.local',  '{$hash}', 'manager', 2),
            ('staff_hr',    'staff.hr@eprocurement.local',    '{$hash}', 'staff',   2),
            ('manager_fin', 'manager.fin@eprocurement.local', '{$hash}', 'manager', 3),
            ('staff_fin',   'staff.fin@eprocurement.local',   '{$hash}', 'staff',   3),
            ('manager_mkt', 'manager.mkt@eprocurement.local', '{$hash}', 'manager', 4),
            ('staff_mkt',   'staff.mkt@eprocurement.local',   '{$hash}', 'staff',   4),
            ('manager_ops', 'manager.ops@eprocurement.local', '{$hash}', 'manager', 5),
            ('staff_ops',   'staff.ops@eprocurement.local',   '{$hash}', 'staff',   5)
    ");
    $results[] = "✅ INSERT users (11 rows) — MD5 hash";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $divCount = $pdo->query("SELECT COUNT(*) FROM divisions")->fetchColumn();
    $vendorCount = $pdo->query("SELECT COUNT(*) FROM vendors")->fetchColumn();
    $success = true;

} catch (PDOException $e) {
    $results[] = "❌ FATAL: " . $e->getMessage();
    $success = false;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Database Setup - E-Procurement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #0f172a; color: #e2e8f0; font-family: 'Segoe UI', system-ui, sans-serif; }
        .log-item { font-family: monospace; font-size: 0.85rem; padding: 4px 8px; border-bottom: 1px solid rgba(255,255,255,0.05); }
    </style>
</head>
<body class="p-4">
    <div class="container" style="max-width:800px">
        <h2 class="mb-4"><i class="bi bi-database-fill-gear me-2" style="color:#6366f1"></i>Database Setup</h2>

        <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <strong>✅ Database <code>projectuask3</code> berhasil dibuat!</strong>
        </div>

        <div class="card bg-dark border-secondary mb-4">
            <div class="card-header border-secondary fw-bold">📊 Ringkasan</div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>Tabel: <strong><?= implode(', ', $tables) ?></strong></li>
                    <li>Total Divisions: <strong><?= $divCount ?></strong></li>
                    <li>Total Vendors: <strong><?= $vendorCount ?></strong></li>
                    <li>Total Users: <strong><?= $userCount ?></strong></li>
                </ul>
            </div>
        </div>

        <div class="card bg-dark border-secondary mb-4">
            <div class="card-header border-secondary fw-bold">🔐 Akun Login (password: <code>password123</code>)</div>
            <div class="card-body p-0">
                <table class="table table-dark table-sm table-striped mb-0">
                    <thead><tr><th>Username</th><th>Role</th><th>Divisi</th></tr></thead>
                    <tbody>
                        <tr><td><code>admin</code></td><td><span class="badge bg-danger">Admin</span></td><td>IT & Teknologi</td></tr>
                        <tr><td><code>manager_it</code></td><td><span class="badge bg-warning text-dark">Manager</span></td><td>IT & Teknologi</td></tr>
                        <tr><td><code>staff_it</code></td><td><span class="badge bg-info text-dark">Staff</span></td><td>IT & Teknologi</td></tr>
                        <tr><td><code>manager_hr</code></td><td><span class="badge bg-warning text-dark">Manager</span></td><td>Human Resources</td></tr>
                        <tr><td><code>staff_hr</code></td><td><span class="badge bg-info text-dark">Staff</span></td><td>Human Resources</td></tr>
                        <tr><td><code>manager_fin</code></td><td><span class="badge bg-warning text-dark">Manager</span></td><td>Finance & Accounting</td></tr>
                        <tr><td><code>staff_fin</code></td><td><span class="badge bg-info text-dark">Staff</span></td><td>Finance & Accounting</td></tr>
                        <tr><td><code>manager_mkt</code></td><td><span class="badge bg-warning text-dark">Manager</span></td><td>Marketing & Sales</td></tr>
                        <tr><td><code>staff_mkt</code></td><td><span class="badge bg-info text-dark">Staff</span></td><td>Marketing & Sales</td></tr>
                        <tr><td><code>manager_ops</code></td><td><span class="badge bg-warning text-dark">Manager</span></td><td>Operations</td></tr>
                        <tr><td><code>staff_ops</code></td><td><span class="badge bg-info text-dark">Staff</span></td><td>Operations</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-danger">
            <strong>❌ Setup gagal!</strong> Lihat log di bawah.
        </div>
        <?php endif; ?>

        <div class="card bg-dark border-secondary mb-4">
            <div class="card-header border-secondary fw-bold">📋 Execution Log</div>
            <div class="card-body p-0" style="max-height:400px;overflow-y:auto">
                <?php foreach ($results as $r): ?>
                <div class="log-item"><?= htmlspecialchars($r) ?></div>
                <?php endforeach; ?>
            </div>
        </div>

        <a href="../login.php" class="btn btn-primary btn-lg">
            <i class="bi bi-box-arrow-in-right me-2"></i>Buka Login Page
        </a>
    </div>
</body>
</html>
