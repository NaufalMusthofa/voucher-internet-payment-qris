<?php

function getDefaultVoucherPackages()
{
    return [
        ['key' => 'pelajar', 'nama' => 'Paket Pelajar', 'harga' => 3000, 'durasi' => '3 Jam', 'perangkat' => '1 HP / Laptop', 'kecepatan' => 'Up to 5 Mbps', 'kuota' => 'Unlimited', 'popular' => false],
        ['key' => 'gaming', 'nama' => 'Paket Gaming Mania', 'harga' => 10000, 'durasi' => '24 Jam (1 Hari)', 'perangkat' => '1 HP / Laptop', 'kecepatan' => 'Up to 15 Mbps', 'kuota' => 'Unlimited', 'popular' => true, 'badge' => 'Paling Laris'],
        ['key' => 'keluarga', 'nama' => 'Paket Keluarga', 'harga' => 50000, 'durasi' => '7 Hari (1 Minggu)', 'perangkat' => 'Maks. 3 Perangkat', 'kecepatan' => 'Up to 20 Mbps', 'kuota' => 'FUP 50 GB', 'popular' => false]
    ];
}

function addColumnIfMissing(PDO $pdo, $table, $column, $definition)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }
}

function ensureVoucherStockSchema(PDO $pdo)
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS voucher_stocks (
        id INT AUTO_INCREMENT PRIMARY KEY, package_key VARCHAR(50) NOT NULL UNIQUE,
        package_name VARCHAR(100) NOT NULL, price INT NOT NULL, stock INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS voucher_codes (
        id INT AUTO_INCREMENT PRIMARY KEY, package_key VARCHAR(50) NOT NULL,
        package_name VARCHAR(100) NOT NULL, voucher_code VARCHAR(100) NOT NULL,
        status ENUM('available','reserved','sold') NOT NULL DEFAULT 'available',
        billing_id INT NULL, customer_name VARCHAR(100) NULL, customer_phone VARCHAR(20) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        sold_at DATETIME NULL, UNIQUE KEY voucher_code_unique (voucher_code),
        INDEX idx_voucher_package_status (package_key, status), INDEX idx_voucher_billing (billing_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS stock_movements (
        id INT AUTO_INCREMENT PRIMARY KEY, package_key VARCHAR(50) NOT NULL, billing_id INT NULL,
        type VARCHAR(30) NOT NULL, quantity INT NOT NULL, stock_before INT NOT NULL,
        stock_after INT NOT NULL, note VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_package_key (package_key), INDEX idx_billing_id (billing_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    addColumnIfMissing($pdo, 'billings', 'package_key', "VARCHAR(50) NULL AFTER amount");
    addColumnIfMissing($pdo, 'billings', 'package_name', "VARCHAR(100) NULL AFTER package_key");
    addColumnIfMissing($pdo, 'billings', 'stock_reserved', "TINYINT(1) NOT NULL DEFAULT 0 AFTER qr_created_at");
    addColumnIfMissing($pdo, 'billings', 'voucher_code_id', "INT NULL AFTER package_name");

    foreach (getDefaultVoucherPackages() as $package) {
        $stmt = $pdo->prepare("INSERT INTO voucher_stocks (package_key, package_name, price, stock) VALUES (?, ?, ?, 0)
            ON DUPLICATE KEY UPDATE package_name = VALUES(package_name), price = VALUES(price)");
        $stmt->execute([$package['key'], $package['nama'], $package['harga']]);
    }
    syncVoucherStockCounts($pdo);
}

function syncVoucherStockCounts(PDO $pdo)
{
    $pdo->exec("UPDATE voucher_stocks vs SET stock = (SELECT COUNT(*) FROM voucher_codes vc WHERE vc.package_key = vs.package_key AND vc.status = 'available')");
}

function getVoucherPackagesWithStock(PDO $pdo)
{
    ensureVoucherStockSchema($pdo);
    $stockRows = $pdo->query("SELECT package_key, stock FROM voucher_stocks")->fetchAll(PDO::FETCH_KEY_PAIR);
    $packages = getDefaultVoucherPackages();
    foreach ($packages as &$package) $package['stock'] = (int)($stockRows[$package['key']] ?? 0);
    return $packages;
}

function findVoucherPackage($packageKey, $packageName = null, $amount = null)
{
    foreach (getDefaultVoucherPackages() as $package) {
        if ($packageKey && $package['key'] === $packageKey) return $package;
        if ($packageName && $package['nama'] === $packageName && ((int)$amount === 0 || (int)$package['harga'] === (int)$amount)) return $package;
    }
    return null;
}

function addVoucherCodes(PDO $pdo, array $rows)
{
    $cleanRows = [];
    foreach ($rows as $row) {
        $package = findVoucherPackage($row['package_key'] ?? '');
        $code = trim((string)($row['voucher_code'] ?? ''));
        if (!$package || $code === '') throw new Exception('Paket dan kode voucher wajib diisi pada setiap baris.');
        if (mb_strlen($code) > 100) throw new Exception('Kode voucher maksimal 100 karakter.');
        $cleanRows[] = ['package' => $package, 'code' => $code];
    }
    if (!$cleanRows) throw new Exception('Tambahkan minimal satu kode voucher.');

    try {
        $pdo->beginTransaction();
        $counts = [];
        foreach ($cleanRows as $row) {
            $key = $row['package']['key'];
            if (!isset($counts[$key])) {
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM voucher_codes WHERE package_key = ? AND status = 'available'");
                $countStmt->execute([$key]);
                $counts[$key] = ['before' => (int)$countStmt->fetchColumn(), 'added' => 0];
            }
            $pdo->prepare("INSERT INTO voucher_codes (package_key, package_name, voucher_code) VALUES (?, ?, ?)")
                ->execute([$key, $row['package']['nama'], $row['code']]);
            $counts[$key]['added']++;
        }
        foreach ($counts as $key => $count) {
            $movement = $pdo->prepare("INSERT INTO stock_movements (package_key, billing_id, type, quantity, stock_before, stock_after, note) VALUES (?, NULL, 'add_code', ?, ?, ?, 'Kode voucher ditambahkan admin')");
            $movement->execute([$key, $count['added'], $count['before'], $count['before'] + $count['added']]);
        }
        syncVoucherStockCounts($pdo);
        $pdo->commit();
        return count($cleanRows);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ((string)$e->getCode() === '23000') throw new Exception('Kode voucher sudah terdaftar. Setiap kode harus unik.');
        throw $e;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function deleteAvailableVoucherCode(PDO $pdo, $voucherId)
{
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT * FROM voucher_codes WHERE id = ? FOR UPDATE");
        $stmt->execute([(int)$voucherId]);
        $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$voucher || $voucher['status'] !== 'available') throw new Exception('Voucher tidak ditemukan atau sudah terikat transaksi.');
        $beforeStmt = $pdo->prepare("SELECT COUNT(*) FROM voucher_codes WHERE package_key = ? AND status = 'available'");
        $beforeStmt->execute([$voucher['package_key']]);
        $before = (int)$beforeStmt->fetchColumn();
        $pdo->prepare("DELETE FROM voucher_codes WHERE id = ?")->execute([$voucher['id']]);
        $movement = $pdo->prepare("INSERT INTO stock_movements (package_key, billing_id, type, quantity, stock_before, stock_after, note) VALUES (?, NULL, 'delete_code', 1, ?, ?, 'Kode voucher dihapus admin')");
        $movement->execute([$voucher['package_key'], $before, max(0, $before - 1)]);
        syncVoucherStockCounts($pdo);
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function updateAvailableVoucherCode(PDO $pdo, $voucherId, $packageKey, $voucherCode)
{
    $package = findVoucherPackage($packageKey);
    $voucherCode = trim((string)$voucherCode);
    if (!$package || $voucherCode === '') throw new Exception('Paket dan kode voucher wajib diisi.');
    if (mb_strlen($voucherCode) > 100) throw new Exception('Kode voucher maksimal 100 karakter.');

    try {
        $stmt = $pdo->prepare("UPDATE voucher_codes SET package_key = ?, package_name = ?, voucher_code = ? WHERE id = ? AND status = 'available'");
        $stmt->execute([$package['key'], $package['nama'], $voucherCode, (int)$voucherId]);
        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM voucher_codes WHERE id = ? AND status = 'available'");
            $check->execute([(int)$voucherId]);
            if (!(int)$check->fetchColumn()) throw new Exception('Voucher tidak ditemukan atau sudah terikat transaksi.');
        }
        syncVoucherStockCounts($pdo);
    } catch (PDOException $e) {
        if ((string)$e->getCode() === '23000') throw new Exception('Kode voucher sudah digunakan oleh data lain.');
        throw $e;
    }
}

function reserveVoucherStock(PDO $pdo, $packageKey, $billingId, $note = null)
{
    $stockStmt = $pdo->prepare("SELECT COUNT(*) FROM voucher_codes WHERE package_key = ? AND status = 'available'");
    $stockStmt->execute([$packageKey]);
    $stockBefore = (int)$stockStmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT id FROM voucher_codes WHERE package_key = ? AND status = 'available' ORDER BY id ASC LIMIT 1 FOR UPDATE");
    $stmt->execute([$packageKey]);
    $voucherId = $stmt->fetchColumn();
    if (!$voucherId) throw new Exception('Stok kode voucher habis. Silakan pilih paket lain.');

    $customerStmt = $pdo->prepare("SELECT u.name, u.phone FROM billings b JOIN users u ON u.id = b.user_id WHERE b.id = ?");
    $customerStmt->execute([$billingId]);
    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $update = $pdo->prepare("UPDATE voucher_codes SET status = 'reserved', billing_id = ?, customer_name = ?, customer_phone = ? WHERE id = ? AND status = 'available'");
    $update->execute([$billingId, $customer['name'] ?? null, $customer['phone'] ?? null, $voucherId]);
    if ($update->rowCount() !== 1) throw new Exception('Voucher sedang diproses pengguna lain. Silakan coba lagi.');

    $pdo->prepare("UPDATE billings SET voucher_code_id = ?, stock_reserved = 1 WHERE id = ?")->execute([$voucherId, $billingId]);
    $movement = $pdo->prepare("INSERT INTO stock_movements (package_key, billing_id, type, quantity, stock_before, stock_after, note) VALUES (?, ?, 'reserve', 1, ?, ?, ?)");
    $movement->execute([$packageKey, $billingId, $stockBefore, max(0, $stockBefore - 1), $note]);
    syncVoucherStockCounts($pdo);
    return $voucherId;
}

function releaseVoucherStockForBilling(PDO $pdo, $billingId, $note = null)
{
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id, package_key, voucher_code_id, stock_reserved FROM billings WHERE id = ? FOR UPDATE");
        $stmt->execute([$billingId]);
        $billing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$billing || !$billing['voucher_code_id'] || (int)$billing['stock_reserved'] !== 1) { $pdo->commit(); return false; }
        $beforeStmt = $pdo->prepare("SELECT COUNT(*) FROM voucher_codes WHERE package_key = ? AND status = 'available'");
        $beforeStmt->execute([$billing['package_key']]);
        $before = (int)$beforeStmt->fetchColumn();
        $release = $pdo->prepare("UPDATE voucher_codes SET status = 'available', billing_id = NULL, customer_name = NULL, customer_phone = NULL WHERE id = ? AND status = 'reserved'");
        $release->execute([$billing['voucher_code_id']]);
        $pdo->prepare("UPDATE billings SET stock_reserved = 0, voucher_code_id = NULL WHERE id = ?")->execute([$billingId]);
        if ($release->rowCount() === 1) {
            $movement = $pdo->prepare("INSERT INTO stock_movements (package_key, billing_id, type, quantity, stock_before, stock_after, note) VALUES (?, ?, 'release', 1, ?, ?, ?)");
            $movement->execute([$billing['package_key'], $billingId, $before, $before + 1, $note]);
        }
        syncVoucherStockCounts($pdo);
        $pdo->commit();
        return $release->rowCount() === 1;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function finalizeVoucherForBilling(PDO $pdo, $billingId)
{
    $stmt = $pdo->prepare("UPDATE voucher_codes vc JOIN billings b ON b.voucher_code_id = vc.id
        SET vc.status = 'sold', vc.sold_at = COALESCE(vc.sold_at, NOW()), b.stock_reserved = 0
        WHERE b.id = ? AND b.status = 'paid' AND vc.status = 'reserved'");
    $stmt->execute([$billingId]);
    syncVoucherStockCounts($pdo);
    return $stmt->rowCount() > 0;
}

function releaseExpiredVoucherReservations(PDO $pdo, $userId = null)
{
    $params = [];
    $sql = "SELECT id FROM billings WHERE stock_reserved = 1 AND status IN ('cancel', 'expired')";
    if ($userId !== null) { $sql .= " AND user_id = ?"; $params[] = $userId; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $billingId) releaseVoucherStockForBilling($pdo, $billingId, 'Release after cancel/expired');
}

function getVoucherInventory(PDO $pdo)
{
    ensureVoucherStockSchema($pdo);
    return $pdo->query("SELECT * FROM voucher_codes ORDER BY FIELD(status, 'reserved', 'sold', 'available'), updated_at DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
}
