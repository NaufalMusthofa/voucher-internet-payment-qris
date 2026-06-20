<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';
require_once 'stock_helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['billing_id'])) {
    $billing_id = $_POST['billing_id'];
    $user_id = $_SESSION['user']['id'];
    ensureVoucherStockSchema($pdo);

    // Hapus hanya jika billing milik user dan status masih 'waiting'
    $stmt = $pdo->prepare("SELECT id FROM billings WHERE id = ? AND user_id = ? AND status = 'waiting'");
    $stmt->execute([$billing_id, $user_id]);

    if ($stmt->fetchColumn()) {
        releaseVoucherStockForBilling($pdo, $billing_id, 'Release before delete waiting billing');

        $delete = $pdo->prepare("DELETE FROM billings WHERE id = ? AND user_id = ? AND status = 'waiting'");
        $delete->execute([$billing_id, $user_id]);
    }
}

header("Location: dashboard.php");
exit();
