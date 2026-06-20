<?php
session_start();
require_once 'db.php';
require_once 'auth_helpers.php';
require_once 'voucher_inventory_helpers.php';

ensureUserRoleSchema($pdo);
ensureUserPhoneSchema($pdo);
refreshSessionUser($pdo);
requireLogin();
ensureVoucherStockSchema($pdo);

$billingId = (int)($_GET['id'] ?? 0);
$params = [$billingId];
$ownerFilter = '';
if (!isAdmin()) {
    $ownerFilter = ' AND b.user_id = ?';
    $params[] = $_SESSION['user']['id'];
}

$stmt = $pdo->prepare("SELECT b.id, b.billing_code, b.package_name, b.status, b.created_at,
        u.name, u.phone, vc.voucher_code, vc.status AS voucher_status
    FROM billings b
    JOIN users u ON u.id = b.user_id
    LEFT JOIN voucher_codes vc ON vc.id = b.voucher_code_id
    WHERE b.id = ?{$ownerFilter} LIMIT 1");
$stmt->execute($params);
$voucher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$voucher) {
    http_response_code(404);
    $error = 'Data voucher tidak ditemukan.';
} elseif ($voucher['status'] !== 'paid') {
    http_response_code(403);
    $error = 'Kode voucher hanya dapat dilihat setelah pembayaran lunas.';
} elseif (empty($voucher['voucher_code'])) {
    http_response_code(404);
    $error = 'Billing ini belum memiliki kode voucher. Silakan hubungi admin.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Lihat Kode Voucher</title>
   <style>
   *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;background:linear-gradient(135deg,#eef2ff,#f8fafc);font-family:'Segoe UI',sans-serif;color:#172033}
   .card{width:100%;max-width:620px;background:#fff;border:1px solid #e5e9f2;border-radius:20px;box-shadow:0 20px 50px rgba(30,64,175,.12);padding:34px;text-align:center}
   .icon{width:64px;height:64px;border-radius:18px;background:#e8efff;color:#1d4ed8;display:grid;place-items:center;margin:0 auto 18px;font-size:30px}
   h1{margin:0 0 8px;font-size:1.65rem}.subtitle{color:#667085;margin:0 0 26px}.details{display:grid;grid-template-columns:1fr 1fr;gap:12px;text-align:left;margin-bottom:22px}
   .detail{background:#f8fafc;border:1px solid #edf0f5;border-radius:12px;padding:14px}.label{font-size:.74rem;text-transform:uppercase;color:#667085;font-weight:700;margin-bottom:5px}.value{font-weight:700;word-break:break-word}
   .code-wrap{background:#172554;color:#fff;border-radius:16px;padding:24px;margin:22px 0}.code-label{font-size:.78rem;text-transform:uppercase;opacity:.75;font-weight:700}.code{font:800 1.65rem Consolas,monospace;letter-spacing:.08em;margin:10px 0;word-break:break-all}
   .buttons{display:flex;justify-content:center;gap:10px;flex-wrap:wrap}.btn{border:0;border-radius:10px;padding:12px 18px;font-weight:800;text-decoration:none;cursor:pointer;font-size:.92rem}.primary{background:#2563eb;color:#fff}.secondary{background:#eef2ff;color:#1d4ed8}.error{padding:18px;background:#fee4e2;color:#b42318;border-radius:12px;font-weight:700}
   @media(max-width:520px){.card{padding:24px}.details{grid-template-columns:1fr}.code{font-size:1.25rem}}
   </style>
</head>
<body>
   <main class="card">
      <div class="icon">🎟️</div>
      <h1>Kode Voucher WiFi</h1>
      <?php if (!empty($error)): ?>
         <p class="error"><?= htmlspecialchars($error) ?></p>
         <div class="buttons"><a class="btn secondary" href="dashboard.php">Kembali ke Dashboard</a></div>
      <?php else: ?>
         <p class="subtitle">Pembayaran lunas. Gunakan kode berikut untuk mengakses WiFi.</p>
         <div class="details">
            <div class="detail"><div class="label">Nama</div><div class="value"><?= htmlspecialchars($voucher['name']) ?></div></div>
            <div class="detail"><div class="label">No. HP</div><div class="value"><?= htmlspecialchars($voucher['phone'] ?: '-') ?></div></div>
            <div class="detail"><div class="label">Paket Voucher</div><div class="value"><?= htmlspecialchars($voucher['package_name'] ?: '-') ?></div></div>
            <div class="detail"><div class="label">Kode Billing</div><div class="value"><?= htmlspecialchars($voucher['billing_code']) ?></div></div>
         </div>
         <div class="code-wrap">
            <div class="code-label">Kode Voucher Anda</div>
            <div class="code" id="voucherCode"><?= htmlspecialchars($voucher['voucher_code']) ?></div>
            <button type="button" class="btn primary" id="copyButton">Salin Kode</button>
         </div>
         <div class="buttons">
            <a class="btn secondary" href="dashboard.php">Kembali ke Dashboard</a>
            <a class="btn secondary" href="riwayat.php">Lihat Riwayat</a>
         </div>
      <?php endif; ?>
   </main>
   <?php if (empty($error)): ?>
   <script>
   document.getElementById('copyButton').addEventListener('click', async function() {
      const code = document.getElementById('voucherCode').textContent.trim();
      try { await navigator.clipboard.writeText(code); this.textContent = 'Berhasil Disalin'; }
      catch (error) { window.prompt('Salin kode voucher berikut:', code); }
   });
   </script>
   <?php endif; ?>
</body>
</html>
