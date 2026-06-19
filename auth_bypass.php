<?php

function ensureDashboardSession(PDO $pdo)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (isset($_SESSION['user']) && !empty($_SESSION['user']['id'])) {
        return;
    }

    $stmt = $pdo->query("SELECT * FROM users ORDER BY id ASC LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $name = 'Guest User';
        $email = 'guest@example.com';

        $insert = $pdo->prepare("INSERT INTO users (name, email) VALUES (?, ?)");
        $insert->execute([$name, $email]);

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$pdo->lastInsertId()]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $_SESSION['user'] = $user;
}

