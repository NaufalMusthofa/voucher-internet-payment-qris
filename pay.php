<?php
require_once 'vendor/autoload.php';
include 'db.php';

\Midtrans\Config::$serverKey = 'SB-Mid-server-PSWB9o2r972l7ryrMYv0EjZ0';
\Midtrans\Config::$isProduction = false;
\Midtrans\Config::$isSanitized = true;
\Midtrans\Config::$is3ds = true;

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT b.*, u.name, u.email FROM billings b JOIN users u ON b.user_id = u.id WHERE b.id = ?");
$stmt->execute([$id]);
$bill = $stmt->fetch();

if (!$bill) {
    die("Tagihan tidak ditemukan.");
}

$params = [
    'transaction_details' => [
        'order_id' => $bill['billing_code'],
        'gross_amount' => (int)$bill['amount']
    ],
    'customer_details' => [
        'first_name' => $bill['name'],
        'email' => $bill['email'],
    ],
    'enabled_payments' => [
        'gopay', 'qris', 'bank_transfer', 'shopeepay', 'permata_va', 'bca_va', 'bni_va'
    ],
    'callbacks' => [
        'finish' => 'https://yourdomain.com/internet_billing/dashboard.php'
    ]
];

// Buat Snap Token
try {
    $snapToken = \Midtrans\Snap::getSnapToken($params);
} catch (Exception $e) {
    die("Gagal membuat transaksi: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>💳 Pembayaran Tagihan</title>
   <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="SB-Mid-client-vFCPQEEFrOCo3WEy">
   </script>
   <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
   <style>
   * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
   }

   body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
   }

   .payment-container {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 20px;
      padding: 40px;
      max-width: 500px;
      width: 100%;
      text-align: center;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      animation: slideIn 0.6s ease-out;
   }

   @keyframes slideIn {
      from {
         opacity: 0;
         transform: translateY(-30px);
      }

      to {
         opacity: 1;
         transform: translateY(0);
      }
   }

   .payment-header {
      margin-bottom: 30px;
   }

   .payment-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(45deg, #667eea, #764ba2);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      animation: pulse 2s infinite;
   }

   @keyframes pulse {
      0% {
         transform: scale(1);
         box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.4);
      }

      70% {
         transform: scale(1.05);
         box-shadow: 0 0 0 10px rgba(102, 126, 234, 0);
      }

      100% {
         transform: scale(1);
         box-shadow: 0 0 0 0 rgba(102, 126, 234, 0);
      }
   }

   .payment-icon i {
      font-size: 2rem;
      color: white;
   }

   .payment-title {
      font-size: 2rem;
      font-weight: 700;
      background: linear-gradient(45deg, #667eea, #764ba2);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      margin-bottom: 10px;
   }

   .payment-subtitle {
      color: #7f8c8d;
      font-size: 1.1rem;
      margin-bottom: 30px;
   }

   .bill-details {
      background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
      border-radius: 15px;
      padding: 25px;
      margin-bottom: 30px;
      border: 1px solid rgba(102, 126, 234, 0.2);
   }

   .bill-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
      padding-bottom: 15px;
      border-bottom: 1px solid rgba(0, 0, 0, 0.1);
   }

   .bill-row:last-child {
      margin-bottom: 0;
      padding-bottom: 0;
      border-bottom: none;
      font-weight: 700;
      font-size: 1.2rem;
   }

   .bill-label {
      color: #2c3e50;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
   }

   .bill-value {
      color: #667eea;
      font-weight: 600;
   }

   .amount-highlight {
      background: linear-gradient(45deg, #667eea, #764ba2);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      font-size: 1.5rem;
   }

   .loading-section {
      margin-bottom: 30px;
   }

   .loading-spinner {
      width: 50px;
      height: 50px;
      border: 4px solid rgba(102, 126, 234, 0.2);
      border-left: 4px solid #667eea;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin: 0 auto 20px;
   }

   @keyframes spin {
      0% {
         transform: rotate(0deg);
      }

      100% {
         transform: rotate(360deg);
      }
   }

   .loading-text {
      color: #2c3e50;
      font-size: 1.2rem;
      font-weight: 600;
      margin-bottom: 10px;
   }

   .loading-subtext {
      color: #7f8c8d;
      font-size: 0.9rem;
   }

   .payment-methods {
      display: flex;
      justify-content: center;
      gap: 15px;
      margin-bottom: 30px;
      flex-wrap: wrap;
   }

   .payment-method {
      background: white;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      padding: 10px 15px;
      font-size: 0.8rem;
      color: #2c3e50;
      font-weight: 600;
      transition: all 0.3s ease;
   }

   .payment-method:hover {
      border-color: #667eea;
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
   }

   .progress-bar {
      width: 100%;
      height: 6px;
      background: rgba(102, 126, 234, 0.2);
      border-radius: 3px;
      overflow: hidden;
      margin-bottom: 20px;
   }

   .progress-fill {
      height: 100%;
      background: linear-gradient(45deg, #667eea, #764ba2);
      border-radius: 3px;
      animation: progressFill 3s ease-in-out infinite;
   }

   @keyframes progressFill {
      0% {
         width: 0%;
      }

      50% {
         width: 70%;
      }

      100% {
         width: 100%;
      }
   }

   .security-info {
      background: linear-gradient(135deg, rgba(46, 204, 113, 0.1), rgba(39, 174, 96, 0.1));
      border: 1px solid rgba(46, 204, 113, 0.3);
      border-radius: 10px;
      padding: 15px;
      margin-top: 20px;
   }

   .security-info i {
      color: #27ae60;
      margin-right: 8px;
   }

   .security-text {
      color: #2c3e50;
      font-size: 0.9rem;
      font-weight: 500;
   }

   .cancel-btn {
      background: linear-gradient(45deg, #e74c3c, #c0392b);
      color: white;
      border: none;
      padding: 12px 30px;
      border-radius: 25px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-block;
      margin-top: 20px;
   }

   .cancel-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(231, 76, 60, 0.4);
   }

   @media (max-width: 768px) {
      .payment-container {
         padding: 30px 20px;
         margin: 10px;
      }

      .payment-title {
         font-size: 1.5rem;
      }

      .bill-row {
         flex-direction: column;
         gap: 8px;
         text-align: center;
      }

      .payment-methods {
         gap: 8px;
      }

      .payment-method {
         padding: 8px 12px;
         font-size: 0.7rem;
      }
   }

   .fade-in {
      animation: fadeIn 0.8s ease-out;
   }

   @keyframes fadeIn {
      from {
         opacity: 0;
         transform: translateY(20px);
      }

      to {
         opacity: 1;
         transform: translateY(0);
      }
   }
   </style>
</head>

<body>
   <div class="payment-container">
      <div class="payment-header">
         <div class="payment-icon">
            <i class="fas fa-credit-card"></i>
         </div>
         <h1 class="payment-title">Pembayaran Tagihan</h1>
         <p class="payment-subtitle">Silakan tunggu, kami sedang menyiapkan pembayaran Anda</p>
      </div>

      <div class="bill-details fade-in">
         <div class="bill-row">
            <span class="bill-label">
               <i class="fas fa-user"></i>
               Nama
            </span>
            <span class="bill-value"><?= htmlspecialchars($bill['name']) ?></span>
         </div>
         <div class="bill-row">
            <span class="bill-label">
               <i class="fas fa-envelope"></i>
               Email
            </span>
            <span class="bill-value"><?= htmlspecialchars($bill['email']) ?></span>
         </div>
         <div class="bill-row">
            <span class="bill-label">
               <i class="fas fa-receipt"></i>
               Kode Tagihan
            </span>
            <span class="bill-value"><?= htmlspecialchars($bill['billing_code']) ?></span>
         </div>
         <div class="bill-row">
            <span class="bill-label">
               <i class="fas fa-money-bill-wave"></i>
               Total Pembayaran
            </span>
            <span class="bill-value amount-highlight">Rp <?= number_format($bill['amount'], 0, ',', '.') ?></span>
         </div>
      </div>

      <div class="loading-section">
         <div class="loading-spinner"></div>
         <div class="loading-text">Membuka Gateway Pembayaran...</div>
         <div class="loading-subtext">Mohon tunggu sebentar, jangan tutup halaman ini</div>

         <div class="progress-bar">
            <div class="progress-fill"></div>
         </div>
      </div>

      <div class="payment-methods fade-in">
         <div class="payment-method">💚 GoPay</div>
         <div class="payment-method">📱 QRIS</div>
         <div class="payment-method">🏦 Transfer Bank</div>
         <div class="payment-method">🛍️ ShopeePay</div>
         <div class="payment-method">🏧 Virtual Account</div>
      </div>

      <div class="security-info fade-in">
         <i class="fas fa-shield-alt"></i>
         <span class="security-text">Pembayaran Anda dilindungi dengan enkripsi SSL 256-bit</span>
      </div>

      <a href="dashboard.php" class="cancel-btn">
         <i class="fas fa-times"></i> Batal Pembayaran
      </a>
   </div>

   <script type="text/javascript">
   // Auto-trigger payment setelah 2 detik
   setTimeout(function() {
      snap.pay('<?= $snapToken ?>', {
         onSuccess: function(result) {
            // Success notification
            showNotification('✅ Pembayaran Berhasil!', 'success');
            setTimeout(function() {
               window.location.href = "dashboard.php";
            }, 2000);
         },
         onPending: function(result) {
            showNotification('⏳ Pembayaran masih pending, silakan selesaikan pembayaran Anda.',
               'warning');
         },
         onError: function(result) {
            showNotification('❌ Terjadi kesalahan pembayaran. Silakan coba lagi.', 'error');
         },
         onClose: function() {
            showNotification('ℹ️ Pembayaran dibatalkan. Anda dapat mencoba lagi kapan saja.', 'info');
         }
      });
   }, 2000);

   function showNotification(message, type) {
      // Create notification element
      const notification = document.createElement('div');
      notification.innerHTML = message;
      notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 25px;
                border-radius: 10px;
                color: white;
                font-weight: 600;
                z-index: 10000;
                animation: slideInRight 0.5s ease-out;
                max-width: 300px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            `;

      // Set background color based on type
      const colors = {
         success: 'linear-gradient(45deg, #27ae60, #2ecc71)',
         error: 'linear-gradient(45deg, #e74c3c, #c0392b)',
         warning: 'linear-gradient(45deg, #f39c12, #e67e22)',
         info: 'linear-gradient(45deg, #3498db, #2980b9)'
      };
      notification.style.background = colors[type] || colors.info;

      document.body.appendChild(notification);

      // Remove notification after 5 seconds
      setTimeout(function() {
         notification.style.animation = 'slideOutRight 0.5s ease-out';
         setTimeout(function() {
            document.body.removeChild(notification);
         }, 500);
      }, 5000);
   }

   // Add animation styles
   const style = document.createElement('style');
   style.textContent = `
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            @keyframes slideOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
        `;
   document.head.appendChild(style);
   </script>
</body>

</html>