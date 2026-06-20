<?php

function getDefaultVoucherPackages()
{
    return [
        [
            'key' => 'pelajar',
            'nama' => 'Paket Pelajar',
            'harga' => 3000,
            'durasi' => '3 Jam',
            'perangkat' => '1 HP / Laptop',
            'kecepatan' => 'Up to 5 Mbps',
            'kuota' => 'Unlimited',
            'popular' => false,
            'initial_stock' => 10
        ],
        [
            'key' => 'gaming',
            'nama' => 'Paket Gaming Mania',
            'harga' => 10000,
            'durasi' => '24 Jam (1 Hari)',
            'perangkat' => '1 HP / Laptop',
            'kecepatan' => 'Up to 15 Mbps',
            'kuota' => 'Unlimited',
            'popular' => true,
            'badge' => 'Paling Laris',
            'initial_stock' => 10
        ],
        [
            'key' => 'keluarga',
            'nama' => 'Paket Keluarga',
            'harga' => 50000,
            'durasi' => '7 Hari (1 Minggu)',
            'perangkat' => 'Maks. 3 Perangkat',
            'kecepatan' => 'Up to 20 Mbps',
            'kuota' => 'FUP 50 GB',
            'popular' => false,
            'initial_stock' => 10
        ]
    ];
}

function ensureVoucherStockSchema(PDO $pdo)
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS voucher_stocks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        package_key VARCHAR(50) NOT NULL UNIQUE,
        package_name VARCHAR(100) NOT NULL,
        price INT NOT NULL,
        stock INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS stock_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        package_key VARCHAR(50) NOT NULL,
        billing_id INT NULL,
        type VARCHAR(30) NOT NULL,
        quantity INT NOT NULL,
        stock_before INT NOT NULL,
        stock_after INT NOT NULL,
        note VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_package_key (package_key),
        INDEX idx_billing_id (billing_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    addColumnIfMissing($pdo, 'billings', 'package_key', "VARCHAR(50) NULL AFTER amount");
    addColumnIfMissing($pdo, 'billings', 'package_name', "VARCHAR(100) NULL AFTER package_key");
    addColumnIfMissing($pdo, 'billings', 'stock_reserved', "TINYINT(1) NOT NULL DEFAULT 0 AFTER qr_created_at");

    seedVoucherStocks($pdo);
}

function addColumnIfMissing(PDO $pdo, $table, $column, $definition)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);

    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }
}

function seedVoucherStocks(PDO $pdo)
{
    foreach (getDefaultVoucherPackages() as $package) {
        $stmt = $pdo->prepare("SELECT id FROM voucher_stocks WHERE package_key = ?");
        $stmt->execute([$package['key']]);

        if (!$stmt->fetchColumn()) {
            $insert = $pdo->prepare("INSERT INTO voucher_stocks (package_key, package_name, price, stock) VALUES (?, ?, ?, ?)");
            $insert->execute([$package['key'], $package['nama'], $package['harga'], $package['initial_stock']]);
        } else {
            $update = $pdo->prepare("UPDATE voucher_stocks SET package_name = ?, price = ? WHERE package_key = ?");
            $update->execute([$package['nama'], $package['harga'], $package['key']]);
        }
    }
}

function getVoucherPackagesWithStock(PDO $pdo)
{
    ensureVoucherStockSchema($pdo);

    $stmt = $pdo->query("SELECT package_key, stock FROM voucher_stocks");
    $stockRows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $packages = getDefaultVoucherPackages();
    foreach ($packages as &$package) {
        $package['stock'] = isset($stockRows[$package['key']]) ? (int)$stockRows[$package['key']] : 0;
    }

    return $packages;
}

function findVoucherPackage($packageKey, $packageName = null, $amount = null)
{
    foreach (getDefaultVoucherPackages() as $package) {
        if ($packageKey && $package['key'] === $packageKey) {
            return $package;
        }

        if ($packageName && $package['nama'] === $packageName && ((int)$amount === 0 || (int)$package['harga'] === (int)$amount)) {
            return $package;
        }
    }

    return null;
}

function reserveVoucherStock(PDO $pdo, $packageKey, $billingId, $note = null)
{
    $stmt = $pdo->prepare("SELECT stock FROM voucher_stocks WHERE package_key = ? FOR UPDATE");
    $stmt->execute([$packageKey]);
    $stockBefore = $stmt->fetchColumn();

    if ($stockBefore === false) {
        throw new Exception('Paket voucher tidak ditemukan.');
    }

    $stockBefore = (int)$stockBefore;
    if ($stockBefore <= 0) {
        throw new Exception('Stok voucher habis. Silakan pilih paket lain atau restok terlebih dahulu.');
    }

    $stockAfter = $stockBefore - 1;
    $update = $pdo->prepare("UPDATE voucher_stocks SET stock = ? WHERE package_key = ?");
    $update->execute([$stockAfter, $packageKey]);

    $movement = $pdo->prepare("INSERT INTO stock_movements (package_key, billing_id, type, quantity, stock_before, stock_after, note) VALUES (?, ?, 'reserve', 1, ?, ?, ?)");
    $movement->execute([$packageKey, $billingId, $stockBefore, $stockAfter, $note]);
}

function releaseVoucherStockForBilling(PDO $pdo, $billingId, $note = null)
{
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id, package_key, stock_reserved FROM billings WHERE id = ? FOR UPDATE");
        $stmt->execute([$billingId]);
        $billing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$billing || empty($billing['package_key']) || (int)$billing['stock_reserved'] !== 1) {
            $pdo->commit();
            return false;
        }

        $stockStmt = $pdo->prepare("SELECT stock FROM voucher_stocks WHERE package_key = ? FOR UPDATE");
        $stockStmt->execute([$billing['package_key']]);
        $stockBefore = (int)$stockStmt->fetchColumn();
        $stockAfter = $stockBefore + 1;

        $updateStock = $pdo->prepare("UPDATE voucher_stocks SET stock = ? WHERE package_key = ?");
        $updateStock->execute([$stockAfter, $billing['package_key']]);

        $updateBilling = $pdo->prepare("UPDATE billings SET stock_reserved = 0 WHERE id = ?");
        $updateBilling->execute([$billingId]);

        $movement = $pdo->prepare("INSERT INTO stock_movements (package_key, billing_id, type, quantity, stock_before, stock_after, note) VALUES (?, ?, 'release', 1, ?, ?, ?)");
        $movement->execute([$billing['package_key'], $billingId, $stockBefore, $stockAfter, $note]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function releaseExpiredVoucherReservations(PDO $pdo, $userId = null)
{
    $params = [];
    $sql = "SELECT id FROM billings WHERE stock_reserved = 1 AND status IN ('cancel', 'expired')";

    if ($userId !== null) {
        $sql .= " AND user_id = ?";
        $params[] = $userId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $billingIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($billingIds as $billingId) {
        releaseVoucherStockForBilling($pdo, $billingId, 'Release after cancel/expired');
    }
}

function restockVoucher(PDO $pdo, $packageKey, $quantity, $note = null)
{
    if ($quantity <= 0) {
        throw new Exception('Jumlah restok harus lebih dari 0.');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT stock FROM voucher_stocks WHERE package_key = ? FOR UPDATE");
        $stmt->execute([$packageKey]);
        $stockBefore = $stmt->fetchColumn();

        if ($stockBefore === false) {
            throw new Exception('Paket voucher tidak ditemukan.');
        }

        $stockBefore = (int)$stockBefore;
        $stockAfter = $stockBefore + $quantity;

        $update = $pdo->prepare("UPDATE voucher_stocks SET stock = ? WHERE package_key = ?");
        $update->execute([$stockAfter, $packageKey]);

        $movement = $pdo->prepare("INSERT INTO stock_movements (package_key, billing_id, type, quantity, stock_before, stock_after, note) VALUES (?, NULL, 'restock', ?, ?, ?, ?)");
        $movement->execute([$packageKey, $quantity, $stockBefore, $stockAfter, $note]);

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

