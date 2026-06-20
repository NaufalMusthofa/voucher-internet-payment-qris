<?php
require_once 'vendor/autoload.php';
require_once 'db.php';
require_once 'config/midtrans.php';
require_once 'sync_midtrans_status.php';
require_once 'voucher_inventory_helpers.php';

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

        $status = mapMidtransBillingStatus($transaction_status, $payment_type, $fraud_status);

        try {
            ensureVoucherStockSchema($pdo);

            // Update status di database
            $stmt = $pdo->prepare("UPDATE billings SET status = ? WHERE billing_code = ?");
            $stmt->execute([$status, $order_id]);

            $billingStmt = $pdo->prepare("SELECT id FROM billings WHERE billing_code = ?");
            $billingStmt->execute([$order_id]);
            $billingId = $billingStmt->fetchColumn();

            if ($billingId && $status === 'paid') {
                finalizeVoucherForBilling($pdo, $billingId);
            }

            if ($stmt->rowCount() > 0) {
                file_put_contents('callback_log.txt', date('Y-m-d H:i:s') . " - Billing {$order_id} updated to {$status}" . PHP_EOL, FILE_APPEND);
                
                if ($status === 'cancel') {
                    if ($billingId) {
                        releaseVoucherStockForBilling($pdo, $billingId, 'Release after Midtrans callback ' . $transaction_status);
                    }
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
