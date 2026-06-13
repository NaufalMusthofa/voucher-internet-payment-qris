<?php
require_once 'vendor/autoload.php';
include 'db.php';
include 'config/midtrans.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user']['id'];
$name = $_SESSION['user']['name'];
$email = isset($_SESSION['user']['email']) ? $_SESSION['user']['email'] : '';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['amount']) || !isset($input['name'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$amount = (int)$input['amount'];
$packageName = substr($input['name'], 0, 100);

// Create a unique billing code
$billing_code = 'BILL-' . time() . '-' . rand(1000,9999);

try {
    $stmt = $pdo->prepare("INSERT INTO billings (user_id, billing_code, amount, status, created_at) VALUES (?, ?, ?, 'waiting', NOW())");
    $stmt->execute([$user_id, $billing_code, $amount]);
    $billing_id = $pdo->lastInsertId();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: '.$e->getMessage()]);
    exit;
}

// Midtrans config sudah di-load dari config/midtrans.php
// (serverKey, isProduction, isSanitized, is3ds)

// Prepare transaction data with 5 minutes expiry
$expiry = [
    'start_time' => date('Y-m-d H:i:s O'),
    'unit' => 'minute',
    'duration' => 5
];

$params = [
    'payment_type' => 'qris',
    'transaction_details' => [
        'order_id' => $billing_code,
        'gross_amount' => $amount
    ],
    'customer_details' => [
        'first_name' => $name,
        'email' => $email
    ],
    'expiry' => $expiry
];

try {
    // Retry mechanism for transient errors (e.g., HTTP 502 from payment partner)
    $maxRetries = 3;
    $attempt = 0;
    $charge = null;
    while ($attempt < $maxRetries) {
        try {
            $charge = \Midtrans\CoreApi::charge($params);
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
    echo json_encode(['success' => false, 'message' => 'Midtrans error: '.$e->getMessage()]);
    exit;
}

// Save midtrans response to billing
try {
    $responseJson = json_encode($charge);
    $now = date('Y-m-d H:i:s');
    $upd = $pdo->prepare("UPDATE billings SET midtrans_response = ?, qr_created_at = ? WHERE id = ?");
    $upd->execute([$responseJson, $now, $billing_id]);
    file_put_contents(__DIR__ . '/create_qris_order.log', date('c') . " Saved midtrans response and qr_created_at=$now for billing_id=$billing_id" . PHP_EOL, FILE_APPEND);
} catch (Exception $e) {
    file_put_contents(__DIR__ . '/create_qris_order.log', date('c') . " Failed to save response: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
}

// Try to locate qr_string or action url

$qr_payload = null;
$qr_url = null;

// normalize charge to array for flexible searching
$chargeArr = json_decode(json_encode($charge), true);

// log the full charge response for debugging
file_put_contents(__DIR__ . '/create_qris_order.log', date('c') . " SUCCESS RESPONSE: " . json_encode($chargeArr) . PHP_EOL, FILE_APPEND);

// helper: recursive search for keys containing 'qr' or 'qr_string'
function find_qr_recursive($arr) {
    if (!is_array($arr)) return null;
    foreach ($arr as $k => $v) {
        $lk = strtolower($k);
        if ($lk === 'qr_string' || strpos($lk, 'qr_string') !== false) return $v;
        if ($lk === 'qr' || strpos($lk, 'qr') === 0 && is_string($v)) return $v;
        if ($lk === 'url' && is_string($v) && (strpos($v, 'http') === 0 || strpos($v, 'data:image') === 0)) return $v;
        if (is_array($v)) {
            $found = find_qr_recursive($v);
            if ($found) return $found;
        }
    }
    return null;
}

$qr_payload = find_qr_recursive($chargeArr);

// if payload looks like a URL, set as qr_url instead
if ($qr_payload && is_string($qr_payload) && (strpos($qr_payload, 'http') === 0 || strpos($qr_payload, 'data:image') === 0)) {
    $qr_url = $qr_payload;
    $qr_payload = null;
}

// fallback: check actions array for url
if (empty($qr_url) && isset($chargeArr['actions']) && is_array($chargeArr['actions'])) {
    foreach ($chargeArr['actions'] as $a) {
        if (!empty($a['url'])) { $qr_url = $a['url']; break; }
    }
}

echo json_encode([
    'success' => true,
    'billing_id' => $billing_id,
    'billing_code' => $billing_code,
    'amount' => $amount,
    'qr_string' => $qr_payload,
    'qr_url' => $qr_url,
    'remaining_seconds' => 300, // 5 minutes for newly created QRIS
    'raw' => $chargeArr
]);

exit;
