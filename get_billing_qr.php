<?php
include 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user']['id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['billing_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$billing_id = (int)$input['billing_id'];

// Get billing data with midtrans response from DB
try {
    $stmt = $pdo->prepare("SELECT id, billing_code, amount, status, midtrans_response, qr_created_at FROM billings WHERE id = ? AND user_id = ? AND status = 'waiting'");
    $stmt->execute([$billing_id, $user_id]);
    $billing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$billing) {
        echo json_encode(['success' => false, 'message' => 'Billing not found or not waiting']);
        exit;
    }
    
    file_put_contents(__DIR__ . '/get_billing_qr.log', date('c') . " Found billing #$billing_id" . PHP_EOL, FILE_APPEND);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: '.$e->getMessage()]);
    file_put_contents(__DIR__ . '/get_billing_qr.log', date('c') . " DB error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    exit;
}

// Stop reusing an expired QRIS. User must buy a new voucher package.
$remaining_seconds = 300;
if (!empty($billing['qr_created_at'])) {
    try {
        $created = new DateTime($billing['qr_created_at']);
        $now = new DateTime();
        $diff = $now->getTimestamp() - $created->getTimestamp();
        $remaining_seconds = max(0, 300 - $diff);

        if ($diff >= 300) {
            $expireStmt = $pdo->prepare("UPDATE billings SET status = 'expired' WHERE id = ? AND user_id = ? AND status = 'waiting'");
            $expireStmt->execute([$billing_id, $user_id]);
            file_put_contents(__DIR__ . '/get_billing_qr.log', date('c') . " Billing #$billing_id expired after {$diff}s" . PHP_EOL, FILE_APPEND);
            echo json_encode([
                'success' => false,
                'status' => 'expired',
                'message' => 'QRIS sudah kadaluarsa. Silakan pilih paket voucher lagi untuk membuat QRIS baru.'
            ]);
            exit;
        }

        file_put_contents(__DIR__ . '/get_billing_qr.log', date('c') . " QR created at: " . $billing['qr_created_at'] . ", elapsed: {$diff}s, remaining: {$remaining_seconds}s" . PHP_EOL, FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/get_billing_qr.log', date('c') . " Error calculating remaining time: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    }
}
// Check if we have midtrans response stored
if (empty($billing['midtrans_response'])) {
    file_put_contents(__DIR__ . '/get_billing_qr.log', date('c') . " No midtrans_response stored for billing #$billing_id" . PHP_EOL, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'QRIS not yet created for this billing']);
    exit;
}

file_put_contents(__DIR__ . '/get_billing_qr.log', date('c') . " Found midtrans_response for billing #$billing_id, parsing..." . PHP_EOL, FILE_APPEND);

// Parse and extract QR from stored response
try {
    $chargeArr = json_decode($billing['midtrans_response'], true);
    if (!is_array($chargeArr)) {
        file_put_contents(__DIR__ . '/get_billing_qr.log', date('c') . " Stored response is not array: " . gettype($chargeArr) . PHP_EOL, FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Invalid stored response']);
        exit;
    }
    file_put_contents(__DIR__ . '/get_billing_qr.log', date('c') . " Response parsed successfully" . PHP_EOL, FILE_APPEND);
} catch (Exception $e) {
    file_put_contents(__DIR__ . '/get_billing_qr.log', date('c') . " Parse error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Failed to parse stored response']);
    exit;
}

// Helper: recursive search for QR (same as create_qris_order.php)
function find_qr_recursive($arr) {
    if (!is_array($arr)) return null;
    foreach ($arr as $k => $v) {
        $lk = strtolower($k);
        if ($lk === 'qr_string' || strpos($lk, 'qr_string') !== false) return $v;
        if ($lk === 'qr' && is_string($v)) return $v;
        if ($lk === 'url' && is_string($v) && (strpos($v, 'http') === 0 || strpos($v, 'data:image') === 0)) return $v;
        if (is_array($v)) {
            $found = find_qr_recursive($v);
            if ($found) return $found;
        }
    }
    return null;
}

$qr_payload = find_qr_recursive($chargeArr);

$qr_string = null;
$qr_url = null;

file_put_contents(__DIR__ . '/get_billing_qr.log', date('c') . " QR payload found: " . ($qr_payload ? 'YES' : 'NO') . PHP_EOL, FILE_APPEND);

// If payload looks like a URL, set as qr_url instead
if ($qr_payload && is_string($qr_payload) && (strpos($qr_payload, 'http') === 0 || strpos($qr_payload, 'data:image') === 0)) {
    $qr_url = $qr_payload;
    file_put_contents(__DIR__ . '/get_billing_qr.log', date('c') . " QR is URL format" . PHP_EOL, FILE_APPEND);
} else if ($qr_payload) {
    $qr_string = $qr_payload;
    file_put_contents(__DIR__ . '/get_billing_qr.log', date('c') . " QR is string format" . PHP_EOL, FILE_APPEND);
}

// Fallback: check actions array for URL
if (empty($qr_url) && empty($qr_string) && isset($chargeArr['actions']) && is_array($chargeArr['actions'])) {
    file_put_contents(__DIR__ . '/get_billing_qr.log', date('c') . " Checking actions array..." . PHP_EOL, FILE_APPEND);
    foreach ($chargeArr['actions'] as $a) {
        if (!empty($a['url'])) { 
            $qr_url = $a['url'];
            file_put_contents(__DIR__ . '/get_billing_qr.log', date('c') . " Found URL in actions" . PHP_EOL, FILE_APPEND);
            break; 
        }
    }
}

file_put_contents(__DIR__ . '/get_billing_qr.log', date('c') . " FINAL: qr_string=" . ($qr_string ? 'YES' : 'NO') . " qr_url=" . ($qr_url ? 'YES' : 'NO') . PHP_EOL, FILE_APPEND);

file_put_contents(__DIR__ . '/get_billing_qr.log', date('c') . " Returned response successfully" . PHP_EOL, FILE_APPEND);

// Calculate remaining seconds for QRIS expiry (5 minutes from creation)
$remaining_seconds = 300; // default 5 minutes
if (!empty($billing['qr_created_at'])) {
    try {
        $created = new DateTime($billing['qr_created_at']);
        $now = new DateTime();
        $diff = $now->getTimestamp() - $created->getTimestamp();
        $remaining_seconds = max(0, 300 - $diff); // 5 minutes = 300 seconds
        file_put_contents(__DIR__ . '/get_billing_qr.log', date('c') . " QR created at: " . $billing['qr_created_at'] . ", elapsed: {$diff}s, remaining: {$remaining_seconds}s" . PHP_EOL, FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/get_billing_qr.log', date('c') . " Error calculating remaining time: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    }
}

echo json_encode([
    'success' => true,
    'billing_id' => $billing_id,
    'billing_code' => $billing['billing_code'],
    'amount' => (int)$billing['amount'],
    'qr_string' => $qr_string,
    'qr_url' => $qr_url,
    'remaining_seconds' => (int)$remaining_seconds
]);

exit;

