<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

include 'db.php';

if (!isset($_GET['id'])) {
    echo "Invoice ID tidak ditemukan.";
    exit;
}

$invoice_id = $_GET['id'];

$stmt = $pdo->prepare("SELECT b.*, u.name FROM billings b JOIN users u ON b.user_id = u.id WHERE b.id = ?");
$stmt->execute([$invoice_id]);
$billing = $stmt->fetch();

if (!$billing) {
    echo "Invoice tidak ditemukan.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Invoice Detail - <?= htmlspecialchars($billing['billing_code']) ?></title>
   <style>
   * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
   }

   body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 50%, #fecfef 100%);
      min-height: 100vh;
      padding: 20px;
   }

   .invoice-container {
      max-width: 800px;
      margin: 0 auto;
      background: white;
      border-radius: 20px;
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
      overflow: hidden;
      position: relative;
   }

   .invoice-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 40px;
      text-align: center;
      position: relative;
      overflow: hidden;
   }

   .invoice-header::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
      animation: float 6s ease-in-out infinite;
   }

   @keyframes float {

      0%,
      100% {
         transform: translateY(0px) rotate(0deg);
      }

      50% {
         transform: translateY(-20px) rotate(180deg);
      }
   }

   .invoice-header h1 {
      font-size: 2.5em;
      font-weight: 300;
      margin-bottom: 10px;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
   }

   .invoice-code {
      background: rgba(255, 255, 255, 0.2);
      padding: 10px 20px;
      border-radius: 25px;
      font-size: 1.2em;
      font-weight: bold;
      display: inline-block;
      margin-top: 10px;
      backdrop-filter: blur(10px);
   }

   .invoice-body {
      padding: 40px;
   }

   .status-badge {
      text-align: center;
      margin-bottom: 30px;
   }

   .badge {
      padding: 10px 25px;
      border-radius: 25px;
      font-weight: bold;
      font-size: 1.1em;
      text-transform: uppercase;
      letter-spacing: 1px;
      display: inline-block;
   }

   .badge-waiting {
      background: linear-gradient(135deg, #ffd89b 0%, #19547b 100%);
      color: white;
   }

   .badge-paid {
      background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
      color: #2d5016;
   }

   .badge-cancel {
      background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
      color: #8b0000;
   }

   .invoice-details {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      border-radius: 15px;
      padding: 30px;
      margin-bottom: 30px;
      color: white;
   }

   .detail-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 20px;
   }

   .detail-item {
      background: rgba(255, 255, 255, 0.1);
      padding: 15px;
      border-radius: 10px;
      backdrop-filter: blur(10px);
   }

   .detail-label {
      font-size: 0.9em;
      opacity: 0.8;
      margin-bottom: 5px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
   }

   .detail-value {
      font-size: 1.2em;
      font-weight: bold;
   }

   .amount-highlight {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px;
      border-radius: 15px;
      text-align: center;
      margin: 20px 0;
      box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
   }

   .amount-highlight .label {
      font-size: 1em;
      opacity: 0.9;
      margin-bottom: 10px;
   }

   .amount-highlight .value {
      font-size: 2.5em;
      font-weight: bold;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
   }

   .action-buttons {
      display: flex;
      gap: 15px;
      justify-content: center;
      margin-top: 30px;
      flex-wrap: wrap;
   }

   .btn {
      padding: 12px 30px;
      border: none;
      border-radius: 25px;
      font-size: 1em;
      font-weight: 500;
      text-decoration: none;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s ease;
      display: inline-block;
      min-width: 150px;
   }

   .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
   }

   .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(102, 126, 234, 0.6);
   }

   .btn-success {
      background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
      color: #2d5016;
      box-shadow: 0 5px 15px rgba(168, 237, 234, 0.4);
   }

   .btn-danger {
      background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
      color: #8b0000;
      box-shadow: 0 5px 15px rgba(255, 154, 158, 0.4);
   }

   .btn-secondary {
      background: linear-gradient(135deg, #ffd89b 0%, #19547b 100%);
      color: white;
      box-shadow: 0 5px 15px rgba(255, 216, 155, 0.4);
   }

   .btn-secondary:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(255, 216, 155, 0.6);
   }

   .print-section {
      border-top: 2px dashed #e0e0e0;
      padding-top: 30px;
      margin-top: 30px;
      text-align: center;
   }

   @media (max-width: 768px) {
      .invoice-container {
         margin: 10px;
         border-radius: 15px;
      }

      .invoice-header,
      .invoice-body {
         padding: 20px;
      }

      .detail-grid {
         grid-template-columns: 1fr;
      }

      .invoice-header h1 {
         font-size: 2em;
      }

      .amount-highlight .value {
         font-size: 2em;
      }

      .action-buttons {
         flex-direction: column;
         align-items: center;
      }
   }

   @keyframes slideIn {
      from {
         opacity: 0;
         transform: translateY(30px);
      }

      to {
         opacity: 1;
         transform: translateY(0);
      }
   }

   .invoice-container {
      animation: slideIn 0.6s ease;
   }

   .watermark {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%) rotate(-45deg);
      font-size: 4em;
      color: rgba(0, 0, 0, 0.05);
      font-weight: bold;
      pointer-events: none;
      z-index: 1;
   }
   </style>
</head>

<body>
   <div class="invoice-container">
      <div class="watermark">INVOICE</div>

      <div class="invoice-header">
         <h1>🧾 Invoice Detail</h1>
         <div class="invoice-code"><?= htmlspecialchars($billing['billing_code']) ?></div>
      </div>

      <div class="invoice-body">
         <div class="status-badge">
            <?php if ($billing['status'] == 'waiting'): ?>
            <span class="badge badge-waiting">⏳ Menunggu Pembayaran</span>
            <?php elseif ($billing['status'] == 'paid'): ?>
            <span class="badge badge-paid">✅ Sudah Dibayar</span>
            <?php elseif ($billing['status'] == 'cancel'): ?>
            <span class="badge badge-cancel">❌ Dibatalkan</span>
            <?php endif; ?>
         </div>

         <div class="invoice-details">
            <div class="detail-grid">
               <div class="detail-item">
                  <div class="detail-label">👤 Nama Pelanggan</div>
                  <div class="detail-value"><?= htmlspecialchars($billing['name']) ?></div>
               </div>
               <div class="detail-item">
                  <div class="detail-label">📅 Tanggal Dibuat</div>
                  <div class="detail-value"><?= date('d/m/Y H:i', strtotime($billing['created_at'])) ?></div>
               </div>
            </div>
         </div>

         <div class="amount-highlight">
            <div class="label">💰 Total Tagihan</div>
            <div class="value">Rp <?= number_format($billing['amount'], 0, ',', '.') ?></div>
         </div>

         <div class="action-buttons">
            <?php if ($billing['status'] == 'waiting'): ?>
            <a href="pay.php?id=<?= $billing['id'] ?>" class="btn btn-primary">
               💳 Bayar Sekarang
            </a>
            <?php endif; ?>

            <a href="dashboard.php" class="btn btn-secondary">
               ← Kembali ke Dashboard
            </a>
         </div>

         <?php if ($billing['status'] == 'paid'): ?>
         <div class="print-section">
            <h3 style="color: #666; margin-bottom: 15px;">📄 Cetak Invoice</h3>
            <button onclick="window.print()" class="btn btn-primary">
               🖨️ Cetak Invoice
            </button>
         </div>
         <?php endif; ?>
      </div>
   </div>

   <script>
   // Add some interactive effects
   document.querySelectorAll('.btn').forEach(btn => {
      btn.addEventListener('mouseenter', function() {
         this.style.transform = 'translateY(-2px) scale(1.05)';
      });

      btn.addEventListener('mouseleave', function() {
         this.style.transform = 'translateY(0) scale(1)';
      });
   });

   // Print styles
   const printStyles = `
            <style media="print">
                body { background: white !important; }
                .invoice-container { 
                    box-shadow: none !important; 
                    max-width: 100% !important;
                    margin: 0 !important;
                }
                .action-buttons, .print-section { display: none !important; }
                .watermark { display: none !important; }
            </style>
        `;
   document.head.insertAdjacentHTML('beforeend', printStyles);
   </script>
</body>

</html>