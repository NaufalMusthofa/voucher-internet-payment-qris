<?php
include 'db.php';
session_start();

$user_id = $_SESSION['user']['id'] ?? 1;

// Get latest billing for this user
$stmt = $pdo->prepare("SELECT id, billing_code, status, midtrans_response FROM billings WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$user_id]);
$billing = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$billing) {
    echo "No billing found\n";
    exit;
}

echo "Billing ID: " . $billing['id'] . "\n";
echo "Code: " . $billing['billing_code'] . "\n";
echo "Status: " . $billing['status'] . "\n";
echo "Response stored: " . ($billing['midtrans_response'] ? 'YES' : 'NO') . "\n";

if ($billing['midtrans_response']) {
    $response = json_decode($billing['midtrans_response'], true);
    echo "Response keys: " . implode(', ', array_keys($response)) . "\n";
    
    // Check for QR
    if (isset($response['qr_string'])) {
        echo "✓ QR String: " . substr($response['qr_string'], 0, 50) . "...\n";
    }
    if (isset($response['actions'])) {
        echo "✓ Actions count: " . count($response['actions']) . "\n";
    }
}
