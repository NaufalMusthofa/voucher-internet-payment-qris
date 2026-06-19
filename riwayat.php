<?php
include 'db.php';
require_once 'auth_bypass.php';
require_once 'config/midtrans.php';
require_once 'sync_midtrans_status.php';

ensureDashboardSession($pdo);

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
      padding: 20px;
   }

   .container {
      max-width: 1200px;
      margin: 0 auto;
      background: rgba(255, 255, 255, 0.95);
      border-radius: 20px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
      backdrop-filter: blur(10px);
      overflow: hidden;
   }

   .header {
      background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
      color: white;
      padding: 30px;
      text-align: center;
   }

   .header h2 {
      font-size: 2.5em;
      font-weight: 300;
      margin-bottom: 10px;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
   }

   .header p {
      opacity: 0.9;
      font-size: 1.1em;
   }

   .content {
      padding: 40px;
   }

   .stats-card {
      background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
      border-radius: 15px;
      padding: 20px;
      margin-bottom: 30px;
      text-align: center;
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
   }

   .stats-card h3 {
      color: #333;
      margin-bottom: 10px;
      font-size: 1.2em;
   }

   .stats-number {
      font-size: 2.5em;
      font-weight: bold;
      color: #4facfe;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
   }

   .table-container {
      background: white;
      border-radius: 15px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      margin-top: 20px;
   }

   .table-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px;
   }

   .table-header h3 {
      font-size: 1.5em;
      font-weight: 400;
   }

   table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
   }

   th {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      color: white;
      padding: 15px;
      text-align: left;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-size: 12px;
   }

   td {
      padding: 15px;
      border-bottom: 1px solid #f0f0f0;
      transition: background-color 0.3s ease;
   }

   tr:hover td {
      background-color: #f8f9ff;
   }

   tr:nth-child(even) td {
      background-color: #fafbff;
   }

   .billing-code {
      font-family: 'Courier New', monospace;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 5px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: bold;
      display: inline-block;
   }

   .amount {
      color: #27ae60;
      font-weight: bold;
      font-size: 1.1em;
   }

   .status {
      background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
      color: #333;
      padding: 5px 15px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: bold;
      text-transform: uppercase;
      display: inline-block;
   }

   .no-data {
      text-align: center;
      padding: 60px 20px;
      color: #666;
   }

   .no-data-icon {
      font-size: 4em;
      margin-bottom: 20px;
      opacity: 0.3;
   }

   .no-data h3 {
      margin-bottom: 10px;
      color: #333;
   }

   .back-button {
      display: inline-block;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 12px 30px;
      border-radius: 25px;
      text-decoration: none;
      font-weight: 500;
      margin-top: 30px;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
   }

   .back-button:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(102, 126, 234, 0.6);
   }

   @media (max-width: 768px) {
      .container {
         margin: 10px;
         border-radius: 15px;
      }

      .content {
         padding: 20px;
      }

      .header h2 {
         font-size: 2em;
      }

      table {
         font-size: 12px;
      }

      th,
      td {
         padding: 10px 8px;
      }
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

   .table-container {
      animation: fadeIn 0.6s ease;
   }
   </style>
</head>

<body>
   <div class="container">
      <div class="header">
         <h2>💳 Riwayat Pembayaran</h2>
         <p>Lihat semua transaksi yang telah berhasil dibayar</p>
      </div>

      <div class="content">
         <div class="stats-card">
            <h3>Total Transaksi Berhasil</h3>
            <div class="stats-number"><?= count($data) ?></div>
         </div>

         <div class="table-container">
            <div class="table-header">
               <h3>📋 Daftar Pembayaran</h3>
            </div>

            <?php if (count($data) > 0): ?>
            <table>
               <thead>
                  <tr>
                     <th>🔖 Kode Billing</th>
                     <th>💰 Jumlah</th>
                     <th>✅ Status</th>
                     <th>📅 Tanggal</th>
                  </tr>
               </thead>
               <tbody>
<?php foreach ($data as $d): ?>
                  <tr>
                     <td>
                        <span class="billing-code"><?= htmlspecialchars($d['billing_code']) ?></span>
                     </td>
                     <td>
                        <span class="amount">Rp <?= number_format($d['amount'], 0, ',', '.') ?></span>
                     </td>
                     <td>
                        <?php
                           $status = $d['status'];
                           if ($status === 'expired') {
                              echo '<span class="status" style="background: rgba(220, 53, 69, 0.12); color: #721c24;">VOID (Kadaluarsa / Otomatis dibatalkan)</span>';
                           } elseif ($status === 'cancel') {
                              echo '<span class="status" style="background: rgba(220, 53, 69, 0.12); color: #721c24;">Dibatalkan</span>';
                           } elseif ($status === 'paid') {
                              echo '<span class="status" style="background: rgba(40, 167, 69, 0.14); color: #155724;">Lunas</span>';
                           } elseif ($status === 'waiting') {
                              echo '<span class="status" style="background: rgba(255, 193, 7, 0.18); color: #856404;">Menunggu</span>';
                           } else {
                              echo '<span class="status">' . ucfirst($status) . '</span>';
                           }
                        ?>
                     </td>
                     <td>
                        <?= date('d/m/Y H:i', strtotime($d['created_at'])) ?>
                     </td>
                  </tr>
                  <?php endforeach; ?>
               </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">
               <div class="no-data-icon">📄</div>
               <h3>Belum Ada Riwayat Pembayaran</h3>
               <p>Anda belum memiliki transaksi yang berhasil dibayar</p>
            </div>
            <?php endif; ?>
         </div>

         <div style="text-align: center;">
            <a href="dashboard.php" class="back-button">← Kembali ke Dashboard</a>
         </div>
      </div>
   </div>
</body>

</html>
