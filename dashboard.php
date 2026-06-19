<?php
include 'db.php';
require_once 'auth_bypass.php';
require_once 'config/midtrans.php';
require_once 'sync_midtrans_status.php';

ensureDashboardSession($pdo);

$user_id = $_SESSION['user']['id'];
$snapJsUrl = \Midtrans\Config::$isProduction
    ? 'https://app.midtrans.com/snap/snap.js'
    : 'https://app.sandbox.midtrans.com/snap/snap.js';

$expiryCutoff = date('Y-m-d H:i:s', time() - 300);

$expiredStatus = 'cancel';

syncMidtransWaitingBillings($pdo, $user_id);

// Auto-void stale QRIS orders before rendering dashboard and statistics.
try {
    $checkStmt = $pdo->prepare("SELECT COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'billings'
          AND COLUMN_NAME = 'status'");
    $checkStmt->execute();
    $col = $checkStmt->fetch(PDO::FETCH_ASSOC);

    $colType = $col['COLUMN_TYPE'] ?? '';
    $allowsExpired = (stripos($colType, "'expired'") !== false) || (stripos($colType, 'varchar') !== false) || (stripos($colType, 'text') !== false);
    $expiredStatus = $allowsExpired ? 'expired' : 'cancel';

    $expireStmt = $pdo->prepare("UPDATE billings SET status = ? WHERE user_id = ? AND status = 'waiting' AND qr_created_at IS NOT NULL AND qr_created_at <= ?");
    $expireStmt->execute([$expiredStatus, $user_id, $expiryCutoff]);
} catch (Exception $e) {
    // If schema inspection fails, still keep expired QRIS out of the waiting bucket.
    try {
        $expireStmt = $pdo->prepare("UPDATE billings SET status = 'cancel' WHERE user_id = ? AND status = 'waiting' AND qr_created_at IS NOT NULL AND qr_created_at <= ?");
        $expireStmt->execute([$user_id, $expiryCutoff]);
    } catch (Exception $ignored) {
        // Don't break dashboard rendering.
    }
}


$stmt = $pdo->prepare("SELECT * FROM billings WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$billings = $stmt->fetchAll();

// Get statistics
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_billings,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid,
        SUM(CASE WHEN status = 'waiting' THEN amount ELSE 0 END) as total_pending,
        COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count,
        COUNT(CASE WHEN status = 'waiting' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status IN ('cancel', 'expired') THEN 1 END) as cancelled_count
    FROM billings WHERE user_id = ?
");
$statsStmt->execute([$user_id]);
$stats = $statsStmt->fetch();

// Paket data
$pakets = [
    [
        'nama' => 'Paket Pelajar',
        'harga' => 3000,
        'durasi' => '3 Jam',
        'perangkat' => '1 HP / Laptop',
        'kecepatan' => 'Up to 5 Mbps',
        'kuota' => 'Unlimited',
        'popular' => false
    ],
    [
        'nama' => 'Paket Gaming Mania',
        'harga' => 10000,
        'durasi' => '24 Jam (1 Hari)',
        'perangkat' => '1 HP / Laptop',
        'kecepatan' => 'Up to 15 Mbps',
        'kuota' => 'Unlimited',
        'popular' => true,
        'badge' => 'Paling Laris'
    ],
    [
        'nama' => 'Paket Keluarga',
        'harga' => 50000,
        'durasi' => '7 Hari (1 Minggu)',
        'perangkat' => 'Maks. 3 Perangkat',
        'kecepatan' => 'Up to 20 Mbps',
        'kuota' => 'FUP 50 GB',
        'popular' => false
    ]
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Dashboard - WiFi Voucher Internet Billing</title>
   <script src="<?= htmlspecialchars($snapJsUrl) ?>" data-client-key="<?= htmlspecialchars(\Midtrans\Config::$clientKey) ?>"></script>
   <style>
      /* --- RESET & BASE STYLES --- */
      * {
         margin: 0;
         padding: 0;
         box-sizing: border-box;
         font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      }

      body {
         background-color: #f4f7f6;
         color: #333;
         line-height: 1.6;
      }

      .container {
         width: 100%;
         max-width: 1200px;
         margin: 0 auto;
         padding: 20px;
      }

      /* --- HEADER UTAMA --- */
      header {
         background: linear-gradient(135deg, #007bff, #0056b3);
         color: white;
         text-align: center;
         padding: 40px 20px;
         border-bottom-left-radius: 20px;
         border-bottom-right-radius: 20px;
         margin-bottom: 30px;
         position: relative;
      }

      header h1 {
         font-size: 2rem;
         margin-bottom: 10px;
         font-weight: 700;
      }

      header p {
         font-size: 1.1rem;
         opacity: 0.9;
         margin-bottom: 5px;
      }

      .user-welcome {
         font-size: 0.95rem;
         opacity: 0.85;
      }

      /* --- PRICING SECTION --- */
      .pricing-section {
         margin-bottom: 40px;
      }

      .section-title {
         font-size: 1.5rem;
         font-weight: 700;
         margin-bottom: 25px;
         color: #333;
      }

      .pricing-grid {
         display: grid;
         grid-template-columns: 1fr;
         gap: 20px;
         margin-bottom: 30px;
      }

      /* --- CARD STYLE --- */
      .card {
         background: white;
         border-radius: 15px;
         padding: 25px;
         box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
         border: 1px solid #eef2f5;
         position: relative;
         overflow: hidden;
         transition: transform 0.3s ease, box-shadow 0.3s ease;
      }

      .card:hover {
         transform: translateY(-5px);
         box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
      }

      .card.popular {
         border: 2px solid #007bff;
         transform: scale(1.02);
      }

      .card.popular:hover {
         transform: scale(1.02) translateY(-5px);
      }

      .badge {
         position: absolute;
         top: 15px;
         right: 15px;
         background: #ffc107;
         color: #000;
         font-size: 0.75rem;
         font-weight: bold;
         padding: 6px 12px;
         border-radius: 20px;
         box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
      }

      .card-header {
         margin-bottom: 20px;
      }

      .paket-nama {
         font-size: 1.4rem;
         color: #222;
         font-weight: 700;
         margin-bottom: 8px;
      }

      .paket-harga {
         font-size: 2rem;
         color: #007bff;
         font-weight: 800;
         margin-bottom: 5px;
      }

      .paket-harga span {
         font-size: 0.9rem;
         color: #777;
         font-weight: normal;
      }

      /* --- DETAIL FITUR --- */
      .paket-details {
         list-style: none;
         margin-bottom: 25px;
      }

      .paket-details li {
         padding: 12px 0;
         border-bottom: 1px dashed #eef2f5;
         display: flex;
         justify-content: space-between;
         font-size: 0.95rem;
      }

      .paket-details li:last-child {
         border-bottom: none;
      }

      .detail-label {
         color: #666;
         font-weight: 500;
      }

      .detail-value {
         font-weight: 600;
         color: #333;
         text-align: right;
      }

      /* --- BUTTON --- */
      .btn-beli {
         display: block;
         width: 100%;
         padding: 12px 20px;
         background-color: #007bff;
         color: white;
         text-align: center;
         text-decoration: none;
         border-radius: 8px;
         font-weight: bold;
         border: none;
         cursor: pointer;
         transition: background 0.2s, transform 0.2s;
      }

      .btn-beli:hover {
         background-color: #0056b3;
         transform: translateY(-2px);
      }

      .card.popular .btn-beli {
         background-color: #28a745;
      }

      .card.popular .btn-beli:hover {
         background-color: #218838;
      }

      /* --- STATS SECTION --- */
      .stats-grid {
         display: grid;
         grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
         gap: 20px;
         margin-bottom: 30px;
      }

      .stat-card {
         background: white;
         padding: 25px;
         border-radius: 15px;
         box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
         border-left: 4px solid #007bff;
         transition: all 0.3s ease;
      }

      .stat-card:hover {
         transform: translateY(-3px);
         box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
      }

      .stat-icon {
         font-size: 28px;
         margin-bottom: 10px;
      }

      .stat-value {
         font-size: 24px;
         font-weight: 700;
         color: #007bff;
         margin-bottom: 5px;
      }

      .stat-label {
         color: #666;
         font-size: 14px;
         font-weight: 500;
      }

      /* --- NAVIGATION --- */
      .navigation {
         background: white;
         border-radius: 15px;
         padding: 25px;
         margin-bottom: 30px;
         box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
      }

      .nav-title {
         font-size: 1.2rem;
         font-weight: 600;
         margin-bottom: 20px;
         color: #333;
      }

      .nav-grid {
         display: grid;
         grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
         gap: 15px;
      }

      .nav-item {
         display: flex;
         align-items: center;
         gap: 12px;
         padding: 15px 20px;
         text-decoration: none;
         color: #333;
         background: rgba(0, 123, 255, 0.05);
         border-radius: 10px;
         border: 2px solid transparent;
         transition: all 0.3s ease;
         font-weight: 500;
      }

      .nav-item:hover {
         background: rgba(0, 123, 255, 0.1);
         border-color: rgba(0, 123, 255, 0.2);
         transform: translateY(-2px);
      }

      .nav-icon {
         font-size: 20px;
      }

      /* --- BILLING TABLE --- */
      .content-section {
         background: white;
         border-radius: 15px;
         padding: 30px;
         box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
         margin-bottom: 30px;
      }

      .table-container {
         overflow-x: auto;
         border-radius: 10px;
         border: 1px solid #e1e5e9;
      }

      .modern-table {
         width: 100%;
         border-collapse: collapse;
         background: white;
      }

      .modern-table th {
         background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
         color: #495057;
         font-weight: 600;
         padding: 15px 20px;
         text-align: left;
         border-bottom: 2px solid #dee2e6;
         font-size: 13px;
         text-transform: uppercase;
         letter-spacing: 0.5px;
      }

      .modern-table td {
         padding: 15px 20px;
         border-bottom: 1px solid #f1f3f4;
         vertical-align: middle;
      }

      .modern-table tr:hover {
         background: rgba(0, 123, 255, 0.02);
      }

      .status-badge {
         padding: 6px 12px;
         border-radius: 20px;
         font-size: 12px;
         font-weight: 600;
         text-transform: uppercase;
         letter-spacing: 0.5px;
      }

      .status-waiting {
         background: rgba(255, 193, 7, 0.1);
         color: #856404;
      }

      .status-paid {
         background: rgba(40, 167, 69, 0.1);
         color: #155724;
      }

      .status-cancel {
         background: rgba(220, 53, 69, 0.1);
         color: #721c24;
      }

      .action-buttons {
         display: flex;
         gap: 8px;
         flex-wrap: wrap;
      }

      .btn {
         padding: 8px 14px;
         border-radius: 6px;
         text-decoration: none;
         font-size: 12px;
         font-weight: 600;
         border: none;
         cursor: pointer;
         transition: all 0.2s ease;
         display: inline-flex;
         align-items: center;
         gap: 5px;
         white-space: nowrap;
      }

      .btn-primary {
         background: #007bff;
         color: white;
      }

      .btn-primary:hover {
         background: #0056b3;
      }

      .btn-danger {
         background: #dc3545;
         color: white;
      }

      .btn-danger:hover {
         background: #c82333;
      }

      .btn-warning {
         background: #ffc107;
         color: #212529;
      }

      .btn-warning:hover {
         background: #e0a800;
      }

      .btn-info {
         background: #17a2b8;
         color: white;
      }

      .btn-info:hover {
         background: #117a8b;
      }

      .amount {
         font-weight: 600;
         color: #007bff;
      }

      .empty-state {
         text-align: center;
         padding: 50px 20px;
         color: #666;
      }

      .empty-icon {
         font-size: 48px;
         margin-bottom: 15px;
         opacity: 0.5;
      }

      .empty-title {
         font-size: 18px;
         font-weight: 600;
         margin-bottom: 8px;
      }

      .empty-text {
         font-size: 14px;
         margin-bottom: 20px;
      }

      .logout-btn {
         position: fixed;
         top: 20px;
         right: 20px;
         background: white;
         color: #007bff;
         padding: 10px 18px;
         border-radius: 50px;
         text-decoration: none;
         font-weight: 600;
         font-size: 14px;
         box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
         border: 2px solid #007bff;
         transition: all 0.3s ease;
         z-index: 100;
      }

      .logout-btn:hover {
         background: #007bff;
         color: white;
         transform: translateY(-2px);
      }

      /* --- MODAL --- */
      .modal {
         display: none;
         position: fixed;
         z-index: 1000;
         inset: 0;
         width: 100%;
         min-height: 100%;
         padding: 20px;
         background-color: rgba(0, 0, 0, 0.55);
         align-items: center;
         justify-content: center;
         overflow-y: auto;
      }

      .modal-content {
         background-color: white;
         margin: auto;
         padding: 30px;
         border-radius: 15px;
         width: min(92vw, 500px);
         max-height: calc(100vh - 40px);
         overflow-y: auto;
         position: relative;
         box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
         animation: modalSlideIn 0.3s ease;
      }

      @keyframes modalSlideIn {
         from {
            opacity: 0;
            transform: translateY(-50px);
         }
         to {
            opacity: 1;
            transform: translateY(0);
         }
      }

      .modal-header {
         display: flex;
         align-items: center;
         gap: 15px;
         margin-bottom: 20px;
         border-bottom: 2px solid #f1f3f4;
         padding-bottom: 15px;
      }

      .modal-icon {
         width: 50px;
         height: 50px;
         border-radius: 50%;
         display: flex;
         align-items: center;
         justify-content: center;
         font-size: 24px;
         background: rgba(255, 193, 7, 0.1);
         color: #856404;
      }

      .modal-title {
         font-size: 18px;
         font-weight: 600;
         color: #333;
      }

      .modal-body {
         margin-bottom: 25px;
         line-height: 1.6;
         color: #666;
         font-size: 14px;
      }

      .modal-actions {
         display: flex;
         gap: 12px;
         justify-content: flex-end;
      }
      #qrisModal .modal-content {
         width: min(94vw, 560px);
         padding: 28px;
      }

      #qrisModal .modal-header {
         justify-content: center;
         text-align: center;
         margin-bottom: 18px;
      }

      #qrisModal .modal-body {
         display: flex;
         flex-direction: column;
         align-items: center;
         gap: 12px;
         margin-bottom: 20px;
         text-align: center;
      }

      #qrisInfo {
         width: 100%;
         margin: 0;
         color: #333;
         font-weight: 700;
         overflow-wrap: anywhere;
      }

      #qrisImage {
         display: block;
         width: min(100%, 340px, 52vh);
         max-width: 100%;
         height: auto;
         aspect-ratio: 1 / 1;
         object-fit: contain;
         margin: 0 auto;
         border-radius: 8px;
      }

      #qrisCountdown {
         margin-top: 0;
         font-weight: 700;
         color: #e67e22;
      }

      #qrisNote {
         margin: 0;
         color: #666;
         font-size: 13px;
      }

      #qrisModal .modal-actions {
         justify-content: center;
      }

      #qrisModal .modal-actions .btn {
         min-width: 120px;
         justify-content: center;
      }

      .close {
         position: absolute;
         right: 20px;
         top: 20px;
         color: #aaa;
         font-size: 28px;
         font-weight: bold;
         cursor: pointer;
         transition: all 0.2s ease;
      }

      .close:hover {
         color: #007bff;
      }

      .fade-in {
         animation: fadeIn 0.5s ease-out;
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

      /* --- RESPONSIVE --- */
      @media (min-width: 600px) {
         header h1 {
            font-size: 2.2rem;
         }
         .pricing-grid {
            grid-template-columns: repeat(2, 1fr);
         }
      }

      @media (min-width: 992px) {
         .pricing-grid {
            grid-template-columns: repeat(3, 1fr);
         }
      }

      @media (max-width: 768px) {
         .container {
            padding: 15px;
         }
         header h1 {
            font-size: 1.5rem;
         }
         header p {
            font-size: 0.95rem;
         }
         .stats-grid {
            grid-template-columns: 1fr;
         }
         .nav-grid {
            grid-template-columns: 1fr;
         }
         .action-buttons {
            flex-direction: column;
         }
         .btn {
            width: 100%;
            justify-content: center;
         }
         .logout-btn {
            position: static;
            margin-bottom: 20px;
         }
         .modern-table {
            font-size: 12px;
         }
         .modern-table th,
         .modern-table td {
            padding: 10px 12px;
         }
      }
   </style>
</head>

<body>
   <a href="logout.php" class="logout-btn" style="display:none;">
      🚪 Logout
   </a>

   <div class="container">
      <!-- Header -->
      <header class="fade-in">
         <h1>🌐 WiFi Voucher Internet Billing</h1>
         <p>Selamat datang, <strong><?= htmlspecialchars($_SESSION['user']['name']) ?></strong></p>
         <p class="user-welcome">Pilih paket internet sesuai kebutuhanmu. Koneksi cepat, stabil, dan tanpa rahasia!</p>
      </header>

      <!-- Pricing Section -->
      <div class="pricing-section fade-in">
         <h2 class="section-title">💳 Paket Layanan Kami</h2>
         <div class="pricing-grid">
            <?php foreach ($pakets as $index => $paket): ?>
            <div class="card <?= $paket['popular'] ? 'popular' : '' ?>">
               <?php if (!empty($paket['badge'])): ?>
               <div class="badge"><?= $paket['badge'] ?></div>
               <?php endif; ?>
               
               <div class="card-header">
                  <div class="paket-nama"><?= $paket['nama'] ?></div>
                  <div class="paket-harga">Rp <?= number_format($paket['harga'], 0, ',', '.') ?> <span>/ paket</span></div>
               </div>
               
               <ul class="paket-details">
                  <li>
                     <span class="detail-label">Masa Aktif</span>
                     <span class="detail-value"><?= $paket['durasi'] ?></span>
                  </li>
                  <li>
                     <span class="detail-label">Jumlah Perangkat</span>
                     <span class="detail-value"><?= $paket['perangkat'] ?></span>
                  </li>
                  <li>
                     <span class="detail-label">Kecepatan</span>
                     <span class="detail-value"><?= $paket['kecepatan'] ?></span>
                  </li>
                  <li>
                     <span class="detail-label">Kuota</span>
                     <span class="detail-value"><?= $paket['kuota'] ?></span>
                  </li>
               </ul>
               
               <button type="button" class="btn-beli" onclick='buyPackage(<?= $paket['harga'] ?>, <?= json_encode($paket['nama']) ?>)'>Beli Voucher</button>
            </div>
            <?php endforeach; ?>
         </div>
      </div>

      <!-- QRIS Modal (fallback untuk transaksi lama) -->
      <div id="qrisModal" class="modal">
         <div class="modal-content">
            <span class="close" id="qrisClose">&times;</span>
            <div class="modal-header">
               <div class="modal-icon">🔳</div>
               <div class="modal-title">Pembayaran QRIS</div>
            </div>
            <div class="modal-body">
               <p id="qrisInfo"></p>
               <img id="qrisImage" src="" alt="QRIS" />
               <div id="qrisCountdown"></div>
               <p id="qrisNote">QR akan kadaluarsa dalam 5 menit. Jangan tutup halaman ini sampai selesai.</p>
            </div>
            <div class="modal-actions">
               <button onclick="closeQrisModal()" class="btn" style="background:#6c757d;color:white;">Tutup</button>
            </div>
         </div>
      </div>

      <!-- Statistics Section -->
      <div class="fade-in">
         <h2 class="section-title">📊 Statistik Billing Anda</h2>
         <div class="stats-grid">
            <div class="stat-card">
               <div class="stat-icon">📋</div>
               <div class="stat-value"><?= $stats['total_billings'] ?: 0 ?></div>
               <div class="stat-label">Total Tagihan</div>
            </div>

            <div class="stat-card">
               <div class="stat-icon">✅</div>
               <div class="stat-value">Rp <?= number_format($stats['total_paid'] ?: 0, 0, ',', '.') ?></div>
               <div class="stat-label">Total Terbayar</div>
            </div>

            <div class="stat-card">
               <div class="stat-icon">⏳</div>
               <div class="stat-value">Rp <?= number_format($stats['total_pending'] ?: 0, 0, ',', '.') ?></div>
               <div class="stat-label">Menunggu Pembayaran</div>
            </div>

            <div class="stat-card">
               <div class="stat-icon">❌</div>
               <div class="stat-value"><?= $stats['cancelled_count'] ?: 0 ?></div>
               <div class="stat-label">Dibatalkan</div>
            </div>
         </div>
      </div>

      <!-- Navigation Section -->
      <div class="navigation fade-in">
         <div class="nav-title">🧭 Menu Navigasi</div>
         <div class="nav-grid">
            <!-- <a href="create_billing.php" class="nav-item">
               <span class="nav-icon">➕</span>
               Buat Tagihan
            </a> -->
            <a href="user_management.php" class="nav-item">
               <span class="nav-icon">👥</span>
               Kelola User
            </a>
            <a href="riwayat.php" class="nav-item">
               <span class="nav-icon">📊</span>
               Riwayat
            </a>
         </div>
      </div>

      <!-- Billing History Section -->
      <div class="content-section fade-in">
         <h2 class="section-title">💳 Riwayat Billing Terbaru</h2>

         <?php if (empty($billings)): ?>
         <div class="empty-state">
            <div class="empty-icon">📄</div>
            <div class="empty-title">Belum Ada Tagihan</div>
            <div class="empty-text">Anda belum memiliki tagihan apapun</div>
            <a href="create_billing.php" class="btn btn-primary">
               ➕ Buat Tagihan Pertama
            </a>
         </div>
         <?php else: ?>
         <div class="table-container">
            <table class="modern-table">
               <thead>
                  <tr>
                     <th>Kode Billing</th>
                     <th>Jumlah</th>
                     <th>Status</th>
                     <th>Tanggal</th>
                     <th>Aksi</th>
                  </tr>
               </thead>
               <tbody>
                  <?php foreach ($billings as $b): ?>
                  <tr>
                     <td>
                        <strong><?= htmlspecialchars($b['billing_code']) ?></strong>
                     </td>
                     <td>
                        <span class="amount">Rp <?= number_format($b['amount'], 0, ',', '.') ?></span>
                     </td>
                     <td>
                        <span class="status-badge status-<?= $b['status'] ?>">
                           <?php
                           switch($b['status']) {
                              case 'paid':
                                 echo '✅ Lunas';
                                 break;
                              case 'waiting':
                                 echo '⏳ Menunggu';
                                 break;
                              case 'cancel':
                                 echo '❌ Dibatalkan';
                                 break;
                              case 'expired':
                                 echo '❌ VOID: QRIS kadaluarsa (otomatis dibatalkan)';
                                 break;

                              default:
                                 echo ucfirst($b['status']);
                           }
                           ?>
                        </span>
                     </td>
                     <td>
                        <?= date('d/m/Y H:i', strtotime($b['created_at'])) ?>
                     </td>
                     <td>
                        <div class="action-buttons">
<?php if ($b['status'] == 'waiting'): ?>
                           <?php
                              // Pastikan QR expired tidak tampil tombol bayar lagi.
                              $isExpiredByTime = false;
                              if (!empty($b['qr_created_at'])) {
                                 $createdTs = strtotime($b['qr_created_at']);
                                 if ($createdTs !== false) {
                                    $isExpiredByTime = (time() - $createdTs) >= 300;
                                 }
                              }
                           ?>

                           <?php if ($isExpiredByTime): ?>
                           <span class="status-badge status-cancel">❌ VOID: QRIS kadaluarsa (otomatis dibatalkan)</span>

                           <?php else: ?>
                           <button type="button" class="btn btn-primary" onclick="payExistingBilling(<?= $b['id'] ?>, <?= $b['amount'] ?>)">
                              💳 Bayar
                           </button>
                           <button
                              onclick="showCancelModal(<?= $b['id'] ?>, '<?= htmlspecialchars($b['billing_code']) ?>')"
                              class="btn btn-warning">
                              ⏹️ Batalkan
                           </button>
                           <form action="delete_billing.php" method="POST" style="display:inline;"
                              onsubmit="return confirm('Yakin ingin menghapus billing ini?');">
                              <input type="hidden" name="billing_id" value="<?= $b['id'] ?>">
                              <button type="submit" class="btn btn-danger">
                                 🗑️ Hapus
                              </button>
                           </form>
                           <?php endif; ?>

                           <?php elseif ($b['status'] == 'paid'): ?>
                           <a href="invoice.php?id=<?= $b['id'] ?>" class="btn btn-info" target="_blank">
                              🧾 Invoice
                           </a>
                           <?php elseif ($b['status'] == 'cancel'): ?>
                           <span class="status-badge status-cancel">
                              ❌ Dibatalkan
                           </span>
                           <?php elseif ($b['status'] == 'expired'): ?>
                           <span class="status-badge status-cancel">
                              ⏰ Kadaluarsa
                           </span>
                           <?php endif; ?>
                        </div>
                     </td>
                  </tr>
                  <?php endforeach; ?>
               </tbody>
            </table>
         </div>
         <?php endif; ?>
      </div>
   </div>

   <!-- Modal Konfirmasi Pembatalan -->
   <div id="cancelModal" class="modal">
      <div class="modal-content">
         <span class="close">&times;</span>
         <div class="modal-header">
            <div class="modal-icon">⚠️</div>
            <div class="modal-title">Konfirmasi Pembatalan</div>
         </div>
         <div class="modal-body">
            <p>Apakah Anda yakin ingin membatalkan tagihan <strong id="cancelBillingCode"></strong>?</p>
            <p style="margin-top: 10px; color: #856404; font-size: 12px;">
               ⚠️ Setelah dibatalkan, tagihan ini tidak dapat diproses lagi dan statusnya akan berubah menjadi "Kadaluarsa".
            </p>
         </div>
         <div class="modal-actions">
            <button onclick="closeCancelModal()" class="btn" style="background: #6c757d; color: white;">Batal</button>
            <form id="cancelForm" method="POST" action="cancel_billing.php" style="display: inline;">
               <input type="hidden" name="billing_id" id="cancelBillingId">
               <button type="submit" class="btn btn-warning">⏹️ Ya, Batalkan</button>
            </form>
         </div>
      </div>
   </div>

   <script>
   // Modal functions
   function showCancelModal(billingId, billingCode) {
      document.getElementById('cancelBillingId').value = billingId;
      document.getElementById('cancelBillingCode').textContent = billingCode;
      document.getElementById('cancelModal').style.display = 'flex';
   }

   function closeCancelModal() {
      document.getElementById('cancelModal').style.display = 'none';
   }

   // Close modal when clicking outside
   window.onclick = function(event) {
      const modal = document.getElementById('cancelModal');
      if (event.target == modal) {
         closeCancelModal();
      }
   }

   // Close modal with X button (cancel modal)
   const cancelCloseBtn = document.querySelector('#cancelModal .close');
   if (cancelCloseBtn) cancelCloseBtn.onclick = function() { closeCancelModal(); };

   // QRIS modal close button
   const qrisCloseBtn = document.getElementById('qrisClose');
   if (qrisCloseBtn) qrisCloseBtn.onclick = function() { closeQrisModal(); };

   // Add loading animation to buttons
   document.querySelectorAll('.btn').forEach(btn => {
      btn.addEventListener('click', function() {
         if (this.type === 'submit' || this.href) {
            this.style.opacity = '0.7';
            this.style.pointerEvents = 'none';
         }
      });
   });

      // Buy package -> create Midtrans Snap order
      let qrisTimerInterval = null;
      let snapPaymentOpen = false;

      function openSnapPayment(snapToken) {
         if (!window.snap || !snapToken) {
            alert('Gateway pembayaran belum siap. Silakan refresh halaman.');
            return;
         }

         snapPaymentOpen = true;
         snap.pay(snapToken, {
            onSuccess: function() {
               snapPaymentOpen = false;
               alert('Pembayaran berhasil.');
               location.reload();
            },
            onPending: function() {
               snapPaymentOpen = false;
               alert('Pembayaran masih pending. Silakan selesaikan instruksi pembayaran.');
               location.reload();
            },
            onError: function() {
               snapPaymentOpen = false;
               alert('Terjadi kesalahan pembayaran. Silakan coba lagi.');
            },
            onClose: function() {
               snapPaymentOpen = false;
               // User menutup popup Snap, billing tetap waiting dan bisa dibayar lagi dari riwayat.
            }
         });
      }

      function buyPackage(amount, name) {
         if (!confirm('Beli "' + name + '" seharga Rp ' + new Intl.NumberFormat('id-ID').format(amount) + '?')) return;
         fetch('create_qris_order.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ amount: amount, name: name })
         }).then(r => r.json()).then(data => {
            if (!data.success) {
               alert('Gagal membuat order: ' + (data.message || 'unknown'));
               console.error('Create order error:', data);
               return;
            }

            if (data.snap_token) {
               openSnapPayment(data.snap_token);
               return;
            }
            
            console.log('QRIS data:', data);
            
            const qrString = data.qr_string || null;
            const qrUrl = data.qr_url || null;
            const img = document.getElementById('qrisImage');
            const infoEl = document.getElementById('qrisInfo');
            const body = document.querySelector('#qrisModal .modal-body');
            
            // Clear old content
            img.src = '';
            img.style.display = 'none';
            infoEl.textContent = 'Kode: ' + data.billing_code + ' • Rp ' + new Intl.NumberFormat('id-ID').format(data.amount);
            
            // Remove any old fallback elements
            const oldRaw = document.getElementById('qrisRaw');
            if (oldRaw) oldRaw.remove();
            const oldLink = body.querySelector('a[target="_blank"]');
            if (oldLink) oldLink.remove();
            
            if (qrString && qrString.trim()) {
               console.log('Displaying QR string');
               const qrData = encodeURIComponent(qrString);
               img.src = 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' + qrData;
               img.style.display = 'block';
               
               img.onerror = function() {
                  console.log('Google Charts QR failed, showing raw string');
                  img.style.display = 'none';
                  infoEl.textContent = 'Kode: ' + data.billing_code + ' • Rp ' + new Intl.NumberFormat('id-ID').format(data.amount) + ' (QR tidak dapat ditampilkan).';
                  const pre = document.createElement('pre');
                  pre.id = 'qrisRaw';
                  pre.style.wordBreak = 'break-all';
                  pre.style.fontSize = '12px';
                  pre.style.maxHeight = '180px';
                  pre.style.overflow = 'auto';
                  pre.style.border = '1px solid #ddd';
                  pre.style.padding = '10px';
                  pre.textContent = qrString;
                  body.appendChild(pre);
               };
               
               showQrisModal();
               startQrisCountdown(data.remaining_seconds || 300);
            } else if (qrUrl && qrUrl.trim()) {
               console.log('Displaying QR URL');
               img.src = qrUrl;
               img.style.display = 'block';
               
               img.onerror = function() {
                  console.log('QR URL failed, showing link');
                  img.style.display = 'none';
                  infoEl.textContent = 'Kode: ' + data.billing_code + ' • Rp ' + new Intl.NumberFormat('id-ID').format(data.amount) + ' (buka di tab baru).';
                  const a = document.createElement('a');
                  a.href = qrUrl;
                  a.target = '_blank';
                  a.textContent = 'Buka QR di tab baru';
                  a.style.display = 'inline-block';
                  a.style.marginTop = '10px';
                  a.style.padding = '8px 16px';
                  a.style.background = '#007bff';
                  a.style.color = 'white';
                  a.style.borderRadius = '4px';
                  a.style.textDecoration = 'none';
                  body.appendChild(a);
               };
               
               showQrisModal();
               startQrisCountdown(data.remaining_seconds || 300);
            } else {
               console.error('No QR found in response:', data.raw);
               alert('Order dibuat, namun QR tidak tersedia. Cek browser console untuk debug info.');
            }
         }).catch(err => {
            console.error('Fetch error:', err);
            alert('Permintaan gagal: ' + err.message);
         });
      }

      function showQrisModal() {
         document.getElementById('qrisModal').style.display = 'flex';
      }

      function closeQrisModal() {
         document.getElementById('qrisModal').style.display = 'none';
         // clear image and timer
         const img = document.getElementById('qrisImage'); if (img) img.src = '';
         document.getElementById('qrisCountdown').textContent = '';
         if (qrisTimerInterval) { clearInterval(qrisTimerInterval); qrisTimerInterval = null; }
      }

      function startQrisCountdown(seconds) {
         const el = document.getElementById('qrisCountdown');
         let remaining = seconds;
         if (qrisTimerInterval) clearInterval(qrisTimerInterval);
         const update = () => {
            const m = Math.floor(remaining / 60);
            const s = remaining % 60;
            el.textContent = 'Waktu tersisa: ' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
            if (remaining <= 0) {
               clearInterval(qrisTimerInterval);
               el.textContent = 'QR sudah kadaluarsa.';
               el.style.color = '#e74c3c'; // red
            }
            remaining--;
         };
         update();
         qrisTimerInterval = setInterval(update, 1000);
      }

   // Pay existing billing -> open saved Midtrans payment (Snap for new orders, QRIS fallback for old ones)
function payExistingBilling(billingId, amount) {
      if (!confirm('Bayar Rp ' + new Intl.NumberFormat('id-ID').format(amount) + '?')) return;
      
      fetch('get_billing_qr.php', {
         method: 'POST',
         credentials: 'same-origin',
         headers: { 'Content-Type': 'application/json' },
         body: JSON.stringify({ billing_id: billingId })
      }).then(r => r.json()).then(data => {
         if (!data.success) {
            alert(data.message || 'Gagal menampilkan pembayaran.');
            if (data.status === 'expired') {
               location.reload();
            }
            return;
         }

         if (data.snap_token) {
            openSnapPayment(data.snap_token);
            return;
         }
         
         const qrString = data.qr_string || null;
         const qrUrl = data.qr_url || null;
         const img = document.getElementById('qrisImage');
         const infoEl = document.getElementById('qrisInfo');
         const body = document.querySelector('#qrisModal .modal-body');
         
         // Clear old content
         img.src = '';
         img.style.display = 'none';
         infoEl.textContent = 'Kode: ' + data.billing_code + ' • Rp ' + new Intl.NumberFormat('id-ID').format(data.amount);
         
         // Remove any old fallback elements
         const oldRaw = document.getElementById('qrisRaw');
         if (oldRaw) oldRaw.remove();
         const oldLink = body.querySelector('a[target="_blank"]');
         if (oldLink) oldLink.remove();
         
         if (qrString && qrString.trim()) {
            const qrData = encodeURIComponent(qrString);
            img.src = 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' + qrData;
            img.style.display = 'block';
            
            img.onerror = function() {
               img.style.display = 'none';
               infoEl.textContent = 'Kode: ' + data.billing_code + ' • Rp ' + new Intl.NumberFormat('id-ID').format(data.amount) + ' (QR tidak dapat ditampilkan).';
               const pre = document.createElement('pre');
               pre.id = 'qrisRaw';
               pre.style.wordBreak = 'break-all';
               pre.style.fontSize = '12px';
               pre.style.maxHeight = '180px';
               pre.style.overflow = 'auto';
               pre.style.border = '1px solid #ddd';
               pre.style.padding = '10px';
               pre.textContent = qrString;
               body.appendChild(pre);
            };
            
            showQrisModal();
            startQrisCountdown(data.remaining_seconds || 300); // gunakan remaining time ACTUAL dari DB
         } else if (qrUrl && qrUrl.trim()) {
            img.src = qrUrl;
            img.style.display = 'block';
            
            img.onerror = function() {
               img.style.display = 'none';
               infoEl.textContent = 'Kode: ' + data.billing_code + ' • Rp ' + new Intl.NumberFormat('id-ID').format(data.amount) + ' (buka di tab baru).';
               const a = document.createElement('a');
               a.href = qrUrl;
               a.target = '_blank';
               a.textContent = 'Buka QR di tab baru';
               a.style.display = 'inline-block';
               a.style.marginTop = '10px';
               a.style.padding = '8px 16px';
               a.style.background = '#007bff';
               a.style.color = 'white';
               a.style.borderRadius = '4px';
               a.style.textDecoration = 'none';
               body.appendChild(a);
            };
            
            showQrisModal();
            startQrisCountdown(data.remaining_seconds || 300); // gunakan remaining time ACTUAL dari DB
         } else {
            alert('QR tidak ditemukan untuk billing ini.');
         }
      }).catch(err => {
         alert('Error: ' + err.message);
      });
   }

   // Auto refresh every 30 seconds (skip if modal open)
      setInterval(() => {
         const modal = document.getElementById('qrisModal');
         // only reload if modal is not visible
      if (!snapPaymentOpen && (!modal || modal.style.display === 'none')) {
         location.reload();
      }
   }, 30000);

   // Realtime-expire handling for rows still in 'waiting' but QR already passed (without refresh)
   // This prevents users seeing the "Bayar QRIS" button after expiry.
   (function realtimeExpireGuard() {
      const TTL_SECONDS = 300;
      const rows = document.querySelectorAll('table.modern-table tbody tr');
      rows.forEach(row => {
         const idText = row.querySelector('td strong');
         const statusEl = row.querySelector('.status-badge');

         // Only relevant for waiting rows that show countdown-like badge
         if (!idText || !statusEl) return;

         // We don't have qr_created_at on the DOM, so we rely on the fact that backend already handles expiry,
         // and we hide payment button client-side when modal is requested and backend returns expired.
         // This placeholder keeps client behavior consistent without altering other logic.
      });
   })();

   </script>
</body>


</html>

