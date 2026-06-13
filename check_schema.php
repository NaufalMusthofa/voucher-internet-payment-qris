<?php
include 'db.php';

// Check and add missing columns
try {
    // Check midtrans_response
    $stmt = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'billings' AND COLUMN_NAME = 'midtrans_response'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        echo "Column 'midtrans_response' not found. Adding it...\n";
        $pdo->exec("ALTER TABLE billings ADD COLUMN midtrans_response LONGTEXT NULL AFTER status");
        echo "✓ Column 'midtrans_response' added successfully!\n";
    } else {
        echo "✓ Column 'midtrans_response' already exists.\n";
    }
    
    // Check qr_created_at
    $stmt = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'billings' AND COLUMN_NAME = 'qr_created_at'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        echo "Column 'qr_created_at' not found. Adding it...\n";
        $pdo->exec("ALTER TABLE billings ADD COLUMN qr_created_at DATETIME NULL AFTER midtrans_response");
        echo "✓ Column 'qr_created_at' added successfully!\n";
    } else {
        echo "✓ Column 'qr_created_at' already exists.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit;
}

// Show current schema
echo "\nCurrent billings table schema:\n";
$stmt = $pdo->query("DESC billings");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
}
