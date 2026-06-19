<?php
require_once 'vendor/autoload.php';
require_once 'db.php';
require_once 'config/midtrans.php';

// Log semua callback yang masuk
file_put_contents('callback_log.txt', date('Y-m-d H:i:s') . " - Callback received: " . file_get_contents('php://input') . PHP_EOL, FILE_APPEND);

$raw = file_get_contents('php://input');
$notification = json_decode($raw, true);

if ($notification) {
    $order_id = $notification['order_id'] ?? null;
    $transaction_status = $notification['transaction_status'] ?? null;
    $payment_type = $notification['payment_type'] ?? null;
    $fraud_status = $notification['fraud_status'] ?? null;

    if ($order_id && $transaction_status) {
        // Log tambahan untuk debug
        file_put_contents('callback_log.txt', date('Y-m-d H:i:s') . " - Order ID: {$order_id}, Status: {$transaction_status}" . PHP_EOL, FILE_APPEND);

        $status = 'waiting'; // default

        if ($transaction_status == 'capture') {
            if ($payment_type == 'credit_card') {
                if ($fraud_status == 'challenge') {
                    $status = 'waiting';
                } else {
                    $status = 'paid';
                }
            }
        } elseif ($transaction_status == 'settlement') {
            $status = 'paid';
        } elseif ($transaction_status == 'pending') {
            $status = 'waiting';
        } elseif ($transaction_status == 'expire') {
            // Treat expired payment as void/cancelled in billing statistics.
            $status = 'cancel';
        } elseif (in_array($transaction_status, ['deny', 'cancel'])) {
            $status = 'cancel';
        }

        try {
            // Update status di database
            $stmt = $pdo->prepare("UPDATE billings SET status = ? WHERE billing_code = ?");
            $stmt->execute([$status, $order_id]);

            if ($stmt->rowCount() > 0) {
                file_put_contents('callback_log.txt', date('Y-m-d H:i:s') . " - Billing {$order_id} updated to {$status}" . PHP_EOL, FILE_APPEND);
                
                // Jika status cancel, lakukan tindakan tambahan jika diperlukan
                if ($status === 'cancel') {
                    // Misalnya: kirim notifikasi email, dll.
                }
            } else {
                file_put_contents('callback_log.txt', date('Y-m-d H:i:s') . " - Billing {$order_id} NOT FOUND in database!" . PHP_EOL, FILE_APPEND);
            }
        } catch (PDOException $e) {
            file_put_contents('callback_log.txt', date('Y-m-d H:i:s') . " - Database Error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
    }
}

echo "OK";
?>
