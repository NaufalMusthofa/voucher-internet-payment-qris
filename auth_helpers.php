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

