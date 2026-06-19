<?php
session_start();
require_once 'vendor/autoload.php';
include 'db.php';
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

// Get billing data
try {
    $stmt = $pdo->prepare("SELECT b.*, u.name, u.email FROM billings b JOIN users u ON b.user_id = u.id WHERE b.id = ? AND b.user_id = ?");
    $stmt->execute([$billing_id, $user_id]);
    $billing = $stmt->fetch();
    
    if (!$billing) {
        echo json_encode(['success' => false, 'message' => 'Billing not found']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: '.$e->getMessage()]);
    exit;
}

// If status is not 'waiting', user can't pay
if ($billing['status'] !== 'waiting') {
    echo json_encode(['success' => false, 'message' => 'Billing status is ' . $billing['status'] . ', cannot create QRIS']);
    exit;
}

// Configure Midtrans
\Midtrans\Config::$serverKey = 'SB-Mid-server-PSWB9o2r972l7ryrMYv0EjZ0';
\Midtrans\Config::$isProduction = false;
\Midtrans\Config::$isSanitized = true;
\Midtrans\Config::$is3ds = true;

// Check if we already have a valid midtrans response for this billing
$charge = null;
$fromCache = false;
if (!empty($billing['midtrans_response'])) {
    try {
        $decoded = json_decode($billing['midtrans_response'], true);
        if (is_array($decoded)) {
            $charge = $decoded;
            $fromCache = true;
            file_put_contents(__DIR__ . '/get_qris_for_billing.log', date('c') . " [BILLING #$billing_id] Using cached response" . PHP_EOL, FILE_APPEND);
        } else {
            file_put_contents(__DIR__ . '/get_qris_for_billing.log', date('c') . " [BILLING #$billing_id] Cached response not array, creating new" . PHP_EOL, FILE_APPEND);
        }
    } catch (Exception $e) {
        $charge = null;
        file_put_contents(__DIR__ . '/get_qris_for_billing.log', date('c') . " [BILLING #$billing_id] Failed to parse cached: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    }
}

// If no cached response, create new one
if (!$charge) {
    // Prepare transaction data with 5 minutes expiry
    $expiry = [
        'start_time' => date('Y-m-d H:i:s O'),
        'unit' => 'minute',
        'duration' => 5
    ];

    $params = [
        'payment_type' => 'qris',
        'transaction_details' => [
            'order_id' => $billing['billing_code'],
            'gross_amount' => (int)$billing['amount']
        ],
        'customer_details' => [
            'first_name' => $billing['name'],
            'email' => $billing['email']
        ],
        'expiry' => $expiry
    ];

    try {
        // Retry mechanism for transient errors
        $maxRetries = 3;
        $attempt = 0;
        while ($attempt < $maxRetries) {
            try {
                $charge = \Midtrans\CoreApi::charge($params);
                break;
            } catch (Exception $e) {
                $attempt++;
                $errMsg = $e->getMessage();
                file_put_contents(__DIR__ . '/get_qris_for_billing.log', date('c') . " Attempt {$attempt} error: " . $errMsg . PHP_EOL, FILE_APPEND);
                
                // If 406 conflict (order already exists), try to retrieve existing instead
                if (strpos($errMsg, '406') !== false || strpos($errMsg, 'conflict') !== false) {
                    try {
                        file_put_contents(__DIR__ . '/get_qris_for_billing.log', date('c') . " 406 conflict detected, trying to retrieve existing transaction... " . PHP_EOL, FILE_APPEND);
                        $status = \Midtrans\Transaction::status($billing['billing_code']);
                        $charge = json_decode(json_encode($status), true);
                        file_put_contents(__DIR__ . '/get_qris_for_billing.log', date('c') . " Retrieved existing transaction successfully" . PHP_EOL, FILE_APPEND);
                        break;
                    } catch (Exception $retryE) {
                        file_put_contents(__DIR__ . '/get_qris_for_billing.log', date('c') . " Failed to retrieve: " . $retryE->getMessage() . PHP_EOL, FILE_APPEND);
                        throw $e;
                    }
                }
                
                if ($attempt >= $maxRetries) {
                    throw $e;
                }
                sleep(1 * $attempt);
            }
        }
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/get_qris_for_billing.log', date('c') . " Final error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Midtrans error: '.$e->getMessage()]);
        exit;
    }

    // Save midtrans response
    try {
        $upd = $pdo->prepare("UPDATE billings SET midtrans_response = ? WHERE id = ?");
        $upd->execute([json_encode($charge), $billing_id]);
    } catch (Exception $e) {
        // ignore
    }
}

$chargeArr = json_decode(json_encode($charge), true);
file_put_contents(__DIR__ . '/get_qris_for_billing.log', date('c') . " [BILLING #$billing_id] RESPONSE (from " . ($fromCache ? 'cache' : 'new') . "): " . json_encode($chargeArr) . PHP_EOL, FILE_APPEND);

// Helper: recursive search for QR (same logic as create_qris_order.php)
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
file_put_contents(__DIR__ . '/get_qris_for_billing.log', date('c') . " [BILLING #$billing_id] find_qr_recursive result: " . ($qr_payload ? 'FOUND' : 'NOT FOUND') . PHP_EOL, FILE_APPEND);

$qr_string = null;
$qr_url = null;

// If payload looks like a URL, set as qr_url instead
if ($qr_payload && is_string($qr_payload) && (strpos($qr_payload, 'http') === 0 || strpos($qr_payload, 'data:image') === 0)) {
    $qr_url = $qr_payload;
    file_put_contents(__DIR__ . '/get_qris_for_billing.log', date('c') . " [BILLING #$billing_id] Detected as QR URL" . PHP_EOL, FILE_APPEND);
} else if ($qr_payload) {
    $qr_string = $qr_payload;
    file_put_contents(__DIR__ . '/get_qris_for_billing.log', date('c') . " [BILLING #$billing_id] Detected as QR string" . PHP_EOL, FILE_APPEND);
}

// Fallback: check actions array for URL
if (empty($qr_url) && empty($qr_string) && isset($chargeArr['actions']) && is_array($chargeArr['actions'])) {
    file_put_contents(__DIR__ . '/get_qris_for_billing.log', date('c') . " [BILLING #$billing_id] Checking actions array..." . PHP_EOL, FILE_APPEND);
    foreach ($chargeArr['actions'] as $a) {
        file_put_contents(__DIR__ . '/get_qris_for_billing.log', date('c') . " [BILLING #$billing_id] Action: " . json_encode($a) . PHP_EOL, FILE_APPEND);
        if (!empty($a['url'])) {
            $qr_url = $a['url'];
            file_put_contents(__DIR__ . '/get_qris_for_billing.log', date('c') . " [BILLING #$billing_id] ✓ Found URL in actions" . PHP_EOL, FILE_APPEND);
            break;
        }
    }
}

file_put_contents(__DIR__ . '/get_qris_for_billing.log', date('c') . " [BILLING #$billing_id] FINAL RESULT: qr_string=" . ($qr_string ? 'YES' : 'NO') . " qr_url=" . ($qr_url ? 'YES' : 'NO') . PHP_EOL, FILE_APPEND);

// Return response
$response = [
    'success' => true,
    'billing_id' => $billing_id,
    'billing_code' => $billing['billing_code'],
    'amount' => (int)$billing['amount'],
    'qr_string' => $qr_string,
    'qr_url' => $qr_url,
    'raw' => $chargeArr
];

file_put_contents(__DIR__ . '/get_qris_for_billing.log', date('c') . " [BILLING #$billing_id] Returning: " . json_encode([
    'billing_code' => $response['billing_code'],
    'qr_string_found' => !empty($qr_string),
    'qr_url_found' => !empty($qr_url)
]) . PHP_EOL, FILE_APPEND);

echo json_encode($response);

exit;
