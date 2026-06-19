<?php

function mapMidtransBillingStatus($transactionStatus, $paymentType = null, $fraudStatus = null)
{
    if ($transactionStatus === 'capture') {
        if ($paymentType === 'credit_card' && $fraudStatus === 'challenge') {
            return 'waiting';
        }

        return 'paid';
    }

    if ($transactionStatus === 'settlement') {
        return 'paid';
    }

    if ($transactionStatus === 'pending') {
        return 'waiting';
    }

    if (in_array($transactionStatus, ['expire', 'deny', 'cancel'], true)) {
        return 'cancel';
    }

    return 'waiting';
}

function syncMidtransWaitingBillings(PDO $pdo, $userId = null)
{
    $params = [];
    $sql = "SELECT id, billing_code FROM billings WHERE status = 'waiting'";

    if ($userId !== null) {
        $sql .= " AND user_id = ?";
        $params[] = $userId;
    }

    $sql .= " ORDER BY created_at DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $billings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/midtrans_status_sync.log', date('c') . " DB select error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        return;
    }

    foreach ($billings as $billing) {
        try {
            $statusResponse = \Midtrans\Transaction::status($billing['billing_code']);
            $statusData = json_decode(json_encode($statusResponse), true);

            if (!is_array($statusData)) {
                continue;
            }

            $transactionStatus = $statusData['transaction_status'] ?? null;
            if (!$transactionStatus) {
                continue;
            }

            $newStatus = mapMidtransBillingStatus(
                $transactionStatus,
                $statusData['payment_type'] ?? null,
                $statusData['fraud_status'] ?? null
            );

            $update = $pdo->prepare("UPDATE billings SET status = ? WHERE id = ? AND status = 'waiting'");
            $update->execute([$newStatus, $billing['id']]);

            file_put_contents(
                __DIR__ . '/midtrans_status_sync.log',
                date('c') . " Billing {$billing['billing_code']} Midtrans={$transactionStatus} local={$newStatus}" . PHP_EOL,
                FILE_APPEND
            );
        } catch (Exception $e) {
            file_put_contents(
                __DIR__ . '/midtrans_status_sync.log',
                date('c') . " Billing {$billing['billing_code']} sync error: " . $e->getMessage() . PHP_EOL,
                FILE_APPEND
            );
        }
    }
}

