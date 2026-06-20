<?php

function ensureUserRoleSchema(PDO $pdo)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'");
    $stmt->execute();

    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('admin','pelanggan') NOT NULL DEFAULT 'pelanggan' AFTER email");
    }

    $adminStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $adminCount = (int)$adminStmt->fetchColumn();

    if ($adminCount === 0) {
        $firstUserStmt = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1");
        $firstUserId = $firstUserStmt->fetchColumn();

        if ($firstUserId) {
            $update = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
            $update->execute([$firstUserId]);
        }
    }
}

function ensureUserPhoneSchema(PDO $pdo)
{
    $columnStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'phone'");
    $columnStmt->execute();
    if ((int)$columnStmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER email");
    }

    // Keep legacy email data, but allow new phone-only accounts.
    $emailStmt = $pdo->prepare("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email'");
    $emailStmt->execute();
    if ($emailStmt->fetchColumn() === 'NO') {
        $pdo->exec("ALTER TABLE users MODIFY email VARCHAR(255) NULL");
    }

    $indexStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'users_phone_unique'");
    $indexStmt->execute();
    if ((int)$indexStmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE users ADD UNIQUE INDEX users_phone_unique (phone)");
    }
}

function normalizePhoneNumber($phone)
{
    $phone = preg_replace('/[\s\-().]/', '', trim((string)$phone));
    if (strpos($phone, '+62') === 0) {
        $phone = substr($phone, 1);
    } elseif (strpos($phone, '0') === 0) {
        $phone = '62' . substr($phone, 1);
    }
    return $phone;
}

function isValidPhoneNumber($phone)
{
    return preg_match('/^62[0-9]{8,13}$/', $phone) === 1;
}

function refreshSessionUser(PDO $pdo)
{
    if (empty($_SESSION['user']['id'])) {
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['user'] = $user;
    }
}

function isAdmin()
{
    return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
}

function requireLogin()
{
    if (!isset($_SESSION['user'])) {
        header("Location: login.php");
        exit;
    }
}

function requireAdmin()
{
    requireLogin();

    if (!isAdmin()) {
        header("Location: dashboard.php?error=forbidden");
        exit;
    }
}

function normalizeUserRole($role)
{
    return $role === 'admin' ? 'admin' : 'pelanggan';
}
