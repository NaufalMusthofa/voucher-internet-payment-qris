<?php
session_start();
require_once 'db.php';
require_once 'vendor/autoload.php';
require_once 'voucher_inventory_helpers.php';

\Midtrans\Config::$isProduction = false;
\Midtrans\Config::$serverKey = 'SB-Mid-server-PSWB9o2r972l7ryrMYv0EjZ0';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['billing_id'])) {
    $billing_id = $_POST['billing_id'];
    ensureVoucherStockSchema($pdo);
    
    // Ambil data billing dari database
    $stmt = $pdo->prepare("SELECT * FROM billings WHERE id = ? AND user_id = ?");
    $stmt->execute([$billing_id, $_SESSION['user']['id']]);
    $billing = $stmt->fetch();
    
    if ($billing && $billing['status'] === 'waiting') {
        try {
            // Update status di database terlebih dahulu
            $updateStmt = $pdo->prepare("UPDATE billings SET status = 'cancel' WHERE id = ?");
            $updateStmt->execute([$billing_id]);
            releaseVoucherStockForBilling($pdo, $billing_id, 'Release after manual cancel');
            
            // Kirim permintaan cancel ke Midtrans
            $params = array(
                'transaction_id' => $billing['billing_code'] // Gunakan billing_code sebagai order_id
            );
            
            $response = \Midtrans\Transaction::cancel($billing['billing_code']);
            
            // Log hasil pembatalan
            file_put_contents('cancel_log.txt', date('Y-m-d H:i:s') . " - Billing ID: {$billing_id}, Response: " . json_encode($response) . PHP_EOL, FILE_APPEND);
            
            $_SESSION['success'] = "Tagihan berhasil dibatalkan";
        } catch (Exception $e) {
            $_SESSION['error'] = "Gagal membatalkan tagihan: " . $e->getMessage();
            file_put_contents('cancel_log.txt', date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
    } else {
        $_SESSION['error'] = "Tagihan tidak ditemukan atau tidak dapat dibatalkan";
    }
}

header("Location: dashboard.php");
exit();
?>
