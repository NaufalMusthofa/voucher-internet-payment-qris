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
   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
   <style>
   :root {
      --color-bg: #eef1f6;
      --color-surface: #ffffff;
      --color-surface-muted: #f8f9fb;
      --color-text: #1c2333;
      --color-text-muted: #667085;
      --color-border: #e6e8ee;
      --color-accent: #2f3e8c;
      --color-accent-dark: #1c2454;
      --color-success-bg: #e7f6ec;
      --color-success-text: #1a7f37;
      --color-warning-bg: #fdf3da;
      --color-warning-text: #8a5a08;
      --color-danger-bg: #fbe9ea;
      --color-danger-text: #b3261e;
      --radius-lg: 16px;
      --radius-md: 10px;
      --shadow-card: 0 1px 2px rgba(16, 24, 40, 0.04), 0 8px 24px rgba(16, 24, 40, 0.06);
      --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      --font-mono: 'JetBrains Mono', 'Courier New', monospace;
   }

   * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
   }

   body {
      font-family: var(--font-sans);
      background: var(--color-bg);
      color: var(--color-text);
      min-height: 100vh;
      padding: 32px 20px;
      -webkit-font-smoothing: antialiased;
   }

   .invoice-container {
      max-width: 760px;
      margin: 0 auto;
      background: var(--color-surface);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-card);
      overflow: hidden;
      position: relative;
   }

   .invoice-header {
      background: linear-gradient(135deg, var(--color-accent-dark) 0%, var(--color-accent) 100%);
      color: #ffffff;
      padding: 40px;
      text-align: center;
   }

   .invoice-header-icon {
      width: 48px;
      height: 48px;
      margin: 0 auto 14px;
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.14);
      display: flex;
      align-items: center;
      justify-content: center;
   }

   .invoice-header-icon svg {
      width: 24px;
      height: 24px;
      stroke: #ffffff;
   }

   .invoice-header h1 {
      font-size: 1.6em;
      font-weight: 700;
      letter-spacing: -0.01em;
   }

   .invoice-code {
      font-family: var(--font-mono);
      background: rgba(255, 255, 255, 0.14);
      border: 1px solid rgba(255, 255, 255, 0.2);
      padding: 8px 18px;
      border-radius: 20px;
      font-size: 0.95em;
      font-weight: 600;
      letter-spacing: 0.02em;
      display: inline-block;
      margin-top: 14px;
   }

   .invoice-body {
      padding: 40px;
      position: relative;
      z-index: 2;
   }

   .status-badge {
      text-align: center;
      margin-bottom: 28px;
   }

   .status {
      padding: 7px 18px;
      border-radius: 20px;
      font-weight: 700;
      font-size: 0.85em;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      display: inline-flex;
      align-items: center;
      gap: 7px;
   }

   .status svg {
      width: 15px;
      height: 15px;
   }

   .status-warning {
      background: var(--color-warning-bg);
      color: var(--color-warning-text);
   }

   .status-warning svg {
      stroke: var(--color-warning-text);
   }

   .status-success {
      background: var(--color-success-bg);
      color: var(--color-success-text);
   }

   .status-success svg {
      stroke: var(--color-success-text);
   }

   .status-danger {
      background: var(--color-danger-bg);
      color: var(--color-danger-text);
   }

   .status-danger svg {
      stroke: var(--color-danger-text);
   }

   .invoice-details {
      background: var(--color-surface-muted);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-lg);
      padding: 26px;
      margin-bottom: 24px;
   }

   .detail-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
   }

   .detail-item {
      background: var(--color-surface);
      border: 1px solid var(--color-border);
      padding: 16px;
      border-radius: var(--radius-md);
   }

   .detail-label {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 0.78em;
      color: var(--color-text-muted);
      margin-bottom: 6px;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      font-weight: 600;
   }

   .detail-label svg {
      width: 14px;
      height: 14px;
      stroke: var(--color-text-muted);
   }

   .detail-value {
      font-size: 1.05em;
      font-weight: 600;
      color: var(--color-text);
   }

   .amount-highlight {
      background: linear-gradient(135deg, var(--color-accent-dark) 0%, var(--color-accent) 100%);
      color: white;
      padding: 24px;
      border-radius: var(--radius-lg);
      text-align: center;
      margin-bottom: 28px;
      box-shadow: 0 10px 24px rgba(47, 62, 138, 0.25);
   }

   .amount-highlight .label {
      font-size: 0.85em;
      opacity: 0.85;
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      font-weight: 600;
   }

   .amount-highlight .value {
      font-size: 2.2em;
      font-weight: 700;
      letter-spacing: -0.01em;
      font-variant-numeric: tabular-nums;
   }

   .action-buttons {
      display: flex;
      gap: 14px;
      justify-content: center;
      flex-wrap: wrap;
   }

   .btn {
      padding: 12px 26px;
      border: 1px solid transparent;
      border-radius: 10px;
      font-size: 0.95em;
      font-weight: 600;
      font-family: inherit;
      text-decoration: none;
      text-align: center;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      min-width: 170px;
      transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
   }

   .btn svg {
      width: 16px;
      height: 16px;
   }

   .btn-primary {
      background: var(--color-accent);
      color: #ffffff;
      box-shadow: 0 6px 16px rgba(47, 62, 138, 0.3);
   }

   .btn-primary svg {
      stroke: #ffffff;
   }

   .btn-primary:hover {
      box-shadow: 0 10px 22px rgba(47, 62, 138, 0.4);
   }

   .btn-outline {
      background: var(--color-surface);
      color: var(--color-accent);
      border-color: var(--color-border);
      box-shadow: var(--shadow-card);
   }

   .btn-outline svg {
      stroke: var(--color-accent);
   }

   .btn-outline:hover {
      border-color: var(--color-accent);
   }

   .print-section {
      border-top: 1px dashed var(--color-border);
      padding-top: 26px;
      margin-top: 28px;
      text-align: center;
   }

   .print-section h3 {
      color: var(--color-text-muted);
      font-size: 0.92em;
      font-weight: 600;
      margin-bottom: 14px;
   }

   @media (max-width: 680px) {
      body {
         padding: 16px 12px;
      }

      .invoice-header,
      .invoice-body {
         padding: 24px;
      }

      .detail-grid {
         grid-template-columns: 1fr;
      }

      .invoice-header h1 {
         font-size: 1.3em;
      }

      .amount-highlight .value {
         font-size: 1.7em;
      }

      .action-buttons {
         flex-direction: column;
         align-items: stretch;
      }
   }

   @keyframes slideIn {
      from {
         opacity: 0;
         transform: translateY(16px);
      }

      to {
         opacity: 1;
         transform: translateY(0);
      }
   }

   .invoice-container {
      animation: slideIn 0.5s ease;
   }

   .watermark {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%) rotate(-30deg);
      font-size: 4em;
      font-weight: 700;
      letter-spacing: 0.05em;
      color: rgba(28, 35, 51, 0.04);
      pointer-events: none;
      z-index: 1;
      white-space: nowrap;
   }
   </style>
</head>

<body>
   <div class="invoice-container">
      <div class="watermark">INVOICE</div>

      <div class="invoice-header">
         <div class="invoice-header-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
               <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
               <polyline points="14 2 14 8 20 8"></polyline>
               <line x1="9" y1="13" x2="15" y2="13"></line>
               <line x1="9" y1="17" x2="15" y2="17"></line>
            </svg>
         </div>
         <h1>Invoice Detail</h1>
         <div class="invoice-code"><?= htmlspecialchars($billing['billing_code']) ?></div>
      </div>

      <div class="invoice-body">
         <div class="status-badge">
            <?php if ($billing['status'] == 'waiting'): ?>
            <span class="status status-warning">
               <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="9"></circle>
                  <polyline points="12 7 12 12 15.5 14"></polyline>
               </svg>
               Menunggu Pembayaran
            </span>
            <?php elseif ($billing['status'] == 'paid'): ?>
            <span class="status status-success">
               <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="20 6 9 17 4 12"></polyline>
               </svg>
               Sudah Dibayar
            </span>
            <?php elseif ($billing['status'] == 'cancel'): ?>
            <span class="status status-danger">
               <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="18" y1="6" x2="6" y2="18"></line>
                  <line x1="6" y1="6" x2="18" y2="18"></line>
               </svg>
               Dibatalkan
            </span>
            <?php endif; ?>
         </div>

         <div class="invoice-details">
            <div class="detail-grid">
               <div class="detail-item">
                  <div class="detail-label">
                     <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                     </svg>
                     Nama Pelanggan
                  </div>
                  <div class="detail-value"><?= htmlspecialchars($billing['name']) ?></div>
               </div>
               <div class="detail-item">
                  <div class="detail-label">
                     <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                     </svg>
                     Tanggal Dibuat
                  </div>
                  <div class="detail-value"><?= date('d/m/Y H:i', strtotime($billing['created_at'])) ?></div>
               </div>
            </div>
         </div>

         <div class="amount-highlight">
            <div class="label">Total Tagihan</div>
            <div class="value">Rp <?= number_format($billing['amount'], 0, ',', '.') ?></div>
         </div>

         <div class="action-buttons">
            <?php if ($billing['status'] == 'waiting'): ?>
            <a href="pay.php?id=<?= $billing['id'] ?>" class="btn btn-primary">
               <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="2" y="5" width="20" height="14" rx="2.5"></rect>
                  <line x1="2" y1="9.5" x2="22" y2="9.5"></line>
               </svg>
               Bayar Sekarang
            </a>
            <?php endif; ?>

            <a href="dashboard.php" class="btn btn-outline">
               <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="19" y1="12" x2="5" y2="12"></line>
                  <polyline points="12 19 5 12 12 5"></polyline>
               </svg>
               Kembali ke Dashboard
            </a>
         </div>

         <?php if ($billing['status'] == 'paid'): ?>
         <div class="print-section">
            <h3>Cetak Invoice</h3>
            <button onclick="window.print()" class="btn btn-outline">
               <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="6 9 6 2 18 2 18 9"></polyline>
                  <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                  <rect x="6" y="14" width="12" height="8"></rect>
               </svg>
               Cetak Invoice
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