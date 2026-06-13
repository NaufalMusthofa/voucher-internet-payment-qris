<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Midtrans Configuration
// Dapatkan Server Key & Client Key dari: https://dashboard.sandbox.midtrans.com/settings/config_info
// Untuk production, gunakan keys dari tab Production dan set isProduction = true

\Midtrans\Config::$serverKey = 'YOUR_SERVER_KEY_HERE'; // Ganti dengan Server Key Anda
\Midtrans\Config::$clientKey = 'YOUR_CLIENT_KEY_HERE'; // Ganti dengan Client Key Anda
\Midtrans\Config::$isProduction = false; // set to true untuk production
\Midtrans\Config::$isSanitized = true;
\Midtrans\Config::$is3ds = true;
?>