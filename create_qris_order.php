<?php
session_start();
require_once 'vendor/autoload.php';
include 'db.php';
include 'config/midtrans.php';
require_once 'voucher_inventory_helpers.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user']['id'];
$name = $_SESSION['user']['name'];
$email = isset($_SESSION['user']['email']) ? $_SESSION['user']['email'] : '';
$phone = isset($_SESSION['user']['phone']) ? $_SESSION['user']['phone'] : '';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['amount']) || !isset($input['name'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$amount = (int)$input['amount'];
$packageName = substr($input['name'], 0, 100);
$packageKey = isset($input['package_key']) ? substr($input['package_key'], 0, 50) : null;
$package = findVoucherPackage($packageKey, $packageName, $amount);

if (!$package) {
    echo json_encode(['success' => false, 'message' => 'Paket voucher tidak valid']);
    exit;
}

ensureVoucherStockSchema($pdo);

// Create a unique billing code
$billing_code = 'BILL-' . time() . '-' . rand(1000,9999);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO billings (user_id, billing_code, amount, package_key, package_name, status, created_at) VALUES (?, ?, ?, ?, ?, 'waiting', NOW())");
    $stmt->execute([$user_id, $billing_code, $amount, $package['key'], $package['nama']]);
    $billing_id = $pdo->lastInsertId();

    reserveVoucherStock($pdo, $package['key'], $billing_id, 'Reserved for pending payment ' . $billing_code);

    $markReserved = $pdo->prepare("UPDATE billings SET stock_reserved = 1 WHERE id = ?");
    $markReserved->execute([$billing_id]);

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode(['success' => false, 'message' => 'DB error: '.$e->getMessage()]);
    exit;
}

// Midtrans config sudah di-load dari config/midtrans.php
// (serverKey, clientKey, isProduction, isSanitized, is3ds)

// Prepare Snap transaction data with 5 minutes expiry.
// enabled_payments sengaja tidak dibatasi agar Midtrans menampilkan semua metode
// yang aktif di akun merchant: QRIS, bank transfer/VA, e-wallet, retail store, dll.
$expiry = [
    'start_time' => date('Y-m-d H:i:s O'),
    'unit' => 'minute',
    'duration' => 5
];

$params = [
    'transaction_details' => [
        'order_id' => $billing_code,
        'gross_amount' => $amount
    ],
    'customer_details' => array_filter([
        'first_name' => $name,
        'email' => $email,
        'phone' => $phone
    ]),
    'item_details' => [
        [
            'id' => $billing_code,
            'price' => $amount,
            'quantity' => 1,
            'name' => $packageName
        ]
    ],
    'expiry' => $expiry
];

try {
    // Retry mechanism for transient errors (e.g., HTTP 502 from payment partner)
    $maxRetries = 3;
    $attempt = 0;
    $snapToken = null;
    while ($attempt < $maxRetries) {
        try {
            $snapToken = \Midtrans\Snap::getSnapToken($params);
            break;
        } catch (Exception $e) {
            $attempt++;
            // log error
            file_put_contents(__DIR__ . '/create_qris_order.log', date('c') . " Attempt {$attempt} error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            // if last attempt, rethrow
            if ($attempt >= $maxRetries) {
                throw $e;
            }
            // backoff
            sleep(1 * $attempt);
        }
    }
} catch (Exception $e) {
    // Log last error for debugging
    file_put_contents(__DIR__ . '/create_qris_order.log', date('c') . " Final error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    try {
        releaseVoucherStockForBilling($pdo, $billing_id, 'Release because Midtrans token failed');
        $cancel = $pdo->prepare("UPDATE billings SET status = 'cancel' WHERE id = ?");
        $cancel->execute([$billing_id]);
    } catch (Exception $releaseError) {
        file_put_contents(__DIR__ . '/create_qris_order.log', date('c') . " Failed to release stock: " . $releaseError->getMessage() . PHP_EOL, FILE_APPEND);
    }
    echo json_encode(['success' => false, 'message' => 'Midtrans error: '.$e->getMessage()]);
    exit;
}

// Save midtrans response to billing
try {
    $responsePayload = [
        'payment_gateway' => 'snap',
        'snap_token' => $snapToken,
        'params' => $params
    ];
    $responseJson = json_encode($responsePayload);
    $now = date('Y-m-d H:i:s');
    $upd = $pdo->prepare("UPDATE billings SET midtrans_response = ?, qr_created_at = ? WHERE id = ?");
    $upd->execute([$responseJson, $now, $billing_id]);
    file_put_contents(__DIR__ . '/create_qris_order.log', date('c') . " Saved midtrans response and qr_created_at=$now for billing_id=$billing_id" . PHP_EOL, FILE_APPEND);
} catch (Exception $e) {
    file_put_contents(__DIR__ . '/create_qris_order.log', date('c') . " Failed to save response: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
}

file_put_contents(__DIR__ . '/create_qris_order.log', date('c') . " SUCCESS SNAP TOKEN for billing_id=$billing_id" . PHP_EOL, FILE_APPEND);

echo json_encode([
    'success' => true,
    'billing_id' => $billing_id,
    'billing_code' => $billing_code,
    'amount' => $amount,
    'payment_gateway' => 'snap',
    'snap_token' => $snapToken,
    'remaining_seconds' => 300
]);

exit;
