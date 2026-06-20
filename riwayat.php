<?php
include 'db.php';
require_once 'config/midtrans.php';
require_once 'sync_midtrans_status.php';
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user']['id'];

syncMidtransWaitingBillings($pdo, $user_id);

$stmt = $pdo->prepare("SELECT * FROM billings WHERE user_id = ? AND status IN ('paid','expired','cancel','waiting') ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$data = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Riwayat Pembayaran</title>
   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
   <style>
   :root {
      --color-bg: #eef1f6;
      --color-surface: #ffffff;
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
      --color-neutral-bg: #eef0f3;
      --color-neutral-text: #475467;
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

   .container {
      max-width: 1080px;
      margin: 0 auto;
   }

   .header {
      background: linear-gradient(135deg, var(--color-accent-dark) 0%, var(--color-accent) 100%);
      color: #ffffff;
      border-radius: var(--radius-lg);
      padding: 36px 40px;
      box-shadow: var(--shadow-card);
      display: flex;
      align-items: center;
      gap: 18px;
   }

   .header-icon {
      width: 52px;
      height: 52px;
      flex-shrink: 0;
      border-radius: 14px;
      background: rgba(255, 255, 255, 0.14);
      display: flex;
      align-items: center;
      justify-content: center;
   }

   .header-icon svg {
      width: 26px;
      height: 26px;
      stroke: #ffffff;
   }

   .header h2 {
      font-size: 1.6em;
      font-weight: 700;
      letter-spacing: -0.01em;
   }

   .header p {
      margin-top: 4px;
      color: rgba(255, 255, 255, 0.78);
      font-size: 0.95em;
      font-weight: 400;
   }

   .content {
      padding-top: 24px;
   }

   .stats-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr);
      gap: 16px;
      margin-bottom: 24px;
   }

   .stats-card {
      background: var(--color-surface);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-lg);
      padding: 22px 26px;
      box-shadow: var(--shadow-card);
      display: flex;
      align-items: center;
      justify-content: space-between;
   }

   .stats-card h3 {
      color: var(--color-text-muted);
      font-size: 0.88em;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.04em;
   }

   .stats-number {
      font-size: 2.1em;
      font-weight: 700;
      color: var(--color-accent);
      letter-spacing: -0.02em;
      margin-top: 4px;
   }

   .stats-card-badge {
      width: 46px;
      height: 46px;
      border-radius: 12px;
      background: var(--color-success-bg);
      display: flex;
      align-items: center;
      justify-content: center;
   }

   .stats-card-badge svg {
      width: 22px;
      height: 22px;
      stroke: var(--color-success-text);
   }

   .table-container {
      background: var(--color-surface);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-lg);
      overflow: hidden;
      box-shadow: var(--shadow-card);
   }

   .table-header {
      padding: 20px 26px;
      border-bottom: 1px solid var(--color-border);
   }

   .table-header h3 {
      font-size: 1.05em;
      font-weight: 600;
      color: var(--color-text);
   }

   table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
   }

   th {
      background: #f8f9fb;
      color: var(--color-text-muted);
      padding: 14px 26px;
      text-align: left;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      font-size: 11.5px;
      border-bottom: 1px solid var(--color-border);
   }

   td {
      padding: 16px 26px;
      border-bottom: 1px solid var(--color-border);
      color: var(--color-text);
   }

   tbody tr:last-child td {
      border-bottom: none;
   }

   tbody tr:hover td {
      background-color: #f8f9ff;
   }

   .billing-code {
      font-family: var(--font-mono);
      background: var(--color-neutral-bg);
      color: var(--color-text);
      padding: 5px 12px;
      border-radius: 8px;
      font-size: 12.5px;
      font-weight: 600;
      display: inline-block;
      border: 1px solid var(--color-border);
   }

   .amount {
      color: var(--color-text);
      font-weight: 600;
      font-variant-numeric: tabular-nums;
   }

   .status {
      padding: 5px 13px;
      border-radius: 20px;
      font-size: 11.5px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      display: inline-block;
      white-space: nowrap;
   }

   .status-success {
      background: var(--color-success-bg);
      color: var(--color-success-text);
   }

   .status-warning {
      background: var(--color-warning-bg);
      color: var(--color-warning-text);
   }

   .status-danger {
      background: var(--color-danger-bg);
      color: var(--color-danger-text);
   }

   .status-neutral {
      background: var(--color-neutral-bg);
      color: var(--color-neutral-text);
   }

   .date-cell {
      color: var(--color-text-muted);
      font-variant-numeric: tabular-nums;
   }

   .no-data {
      text-align: center;
      padding: 70px 20px;
      color: var(--color-text-muted);
   }

   .no-data-icon {
      width: 56px;
      height: 56px;
      margin: 0 auto 18px;
      border-radius: 14px;
      background: var(--color-neutral-bg);
      display: flex;
      align-items: center;
      justify-content: center;
   }

   .no-data-icon svg {
      width: 26px;
      height: 26px;
      stroke: var(--color-text-muted);
   }

   .no-data h3 {
      margin-bottom: 6px;
      color: var(--color-text);
      font-size: 1.05em;
      font-weight: 600;
   }

   .no-data p {
      font-size: 0.92em;
   }

   .back-button-wrap {
      text-align: center;
      margin-top: 28px;
   }

   .back-button {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: var(--color-surface);
      color: var(--color-accent);
      border: 1px solid var(--color-border);
      padding: 11px 24px;
      border-radius: 10px;
      text-decoration: none;
      font-weight: 600;
      font-size: 0.92em;
      transition: border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
      box-shadow: var(--shadow-card);
   }

   .back-button svg {
      width: 16px;
      height: 16px;
      stroke: var(--color-accent);
   }

   .back-button:hover {
      transform: translateY(-1px);
      border-color: var(--color-accent);
   }

   @media (max-width: 680px) {
      body {
         padding: 16px 12px;
      }

      .header {
         padding: 24px 22px;
      }

      .header h2 {
         font-size: 1.3em;
      }

      .table-header {
         padding: 16px 18px;
      }

      thead {
         display: none;
      }

      table, tbody, tr, td {
         display: block;
         width: 100%;
      }

      tbody tr {
         padding: 14px 18px;
         border-bottom: 1px solid var(--color-border);
      }

      td {
         display: flex;
         align-items: center;
         justify-content: space-between;
         gap: 12px;
         padding: 7px 0;
         border-bottom: none;
      }

      td::before {
         content: attr(data-label);
         font-size: 11px;
         font-weight: 600;
         text-transform: uppercase;
         letter-spacing: 0.04em;
         color: var(--color-text-muted);
      }
   }

   @keyframes fadeIn {
      from {
         opacity: 0;
         transform: translateY(10px);
      }

      to {
         opacity: 1;
         transform: translateY(0);
      }
   }

   .table-container,
   .stats-card {
      animation: fadeIn 0.5s ease;
   }
   </style>
</head>

<body>
   <div class="container">
      <div class="header">
         <div class="header-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
               <rect x="2" y="5" width="20" height="14" rx="2.5"></rect>
               <line x1="2" y1="9.5" x2="22" y2="9.5"></line>
               <line x1="6" y1="14.5" x2="10" y2="14.5"></line>
            </svg>
         </div>
         <div>
            <h2>Riwayat Pembayaran</h2>
            <p>Lihat semua transaksi yang pernah Anda lakukan</p>
         </div>
      </div>

      <div class="content">
         <div class="stats-row">
            <div class="stats-card">
               <div>
                  <h3>Total Transaksi</h3>
                  <div class="stats-number"><?= count($data) ?></div>
               </div>
               <div class="stats-card-badge">
                  <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                     <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>
               </div>
            </div>
         </div>

         <div class="table-container">
            <div class="table-header">
               <h3>Daftar Pembayaran</h3>
            </div>

            <?php if (count($data) > 0): ?>
            <table>
               <thead>
                  <tr>
                     <th>Kode Billing</th>
                     <th>Jumlah</th>
                     <th>Status</th>
                     <th>Tanggal</th>
                  </tr>
               </thead>
               <tbody>
<?php foreach ($data as $d): ?>
                  <tr>
                     <td data-label="Kode Billing">
                        <span class="billing-code"><?= htmlspecialchars($d['billing_code']) ?></span>
                     </td>
                     <td data-label="Jumlah">
                        <span class="amount">Rp <?= number_format($d['amount'], 0, ',', '.') ?></span>
                     </td>
                     <td data-label="Status">
                        <?php
                           $status = $d['status'];
                           if ($status === 'expired') {
                              echo '<span class="status status-danger">Kedaluwarsa (Otomatis Dibatalkan)</span>';
                           } elseif ($status === 'cancel') {
                              echo '<span class="status status-danger">Dibatalkan</span>';
                           } elseif ($status === 'paid') {
                              echo '<span class="status status-success">Lunas</span>';
                           } elseif ($status === 'waiting') {
                              echo '<span class="status status-warning">Menunggu</span>';
                           } else {
                              echo '<span class="status status-neutral">' . ucfirst($status) . '</span>';
                           }
                        ?>
                     </td>
                     <td data-label="Tanggal" class="date-cell">
                        <?= date('d/m/Y H:i', strtotime($d['created_at'])) ?>
                     </td>
                  </tr>
                  <?php endforeach; ?>
               </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">
               <div class="no-data-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                     <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                     <polyline points="14 2 14 8 20 8"></polyline>
                  </svg>
               </div>
               <h3>Belum Ada Riwayat Pembayaran</h3>
               <p>Anda belum memiliki transaksi yang tercatat</p>
            </div>
            <?php endif; ?>
         </div>

         <div class="back-button-wrap">
            <a href="dashboard.php" class="back-button">
               <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="19" y1="12" x2="5" y2="12"></line>
                  <polyline points="12 19 5 12 12 5"></polyline>
               </svg>
               Kembali ke Dashboard
            </a>
         </div>
      </div>
   </div>
</body>

</html>
