<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
require_once 'auth_helpers.php';
require_once 'stock_helpers.php';

ensureUserRoleSchema($pdo);
refreshSessionUser($pdo);
requireAdmin();
ensureVoucherStockSchema($pdo);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $packageKey = $_POST['package_key'] ?? '';
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $note = trim($_POST['note'] ?? '');

    try {
        restockVoucher($pdo, $packageKey, $quantity, $note ?: 'Restok manual');
        $success = 'Stok berhasil ditambahkan.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$packages = getVoucherPackagesWithStock($pdo);
$movementStmt = $pdo->query("SELECT sm.*, vs.package_name
    FROM stock_movements sm
    LEFT JOIN voucher_stocks vs ON vs.package_key = sm.package_key
    ORDER BY sm.created_at DESC
    LIMIT 30");
$movements = $movementStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Master Stok Voucher</title>
   <style>
      * {
         margin: 0;
         padding: 0;
         box-sizing: border-box;
         font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      }

      body {
         background: #f4f7fb;
         color: #243044;
         min-height: 100vh;
         padding: 24px;
      }

      .container {
         max-width: 1180px;
         margin: 0 auto;
      }

      .header {
         background: #ffffff;
         border: 1px solid #e5e9f2;
         border-radius: 14px;
         padding: 24px;
         box-shadow: 0 8px 22px rgba(18, 38, 63, 0.06);
         display: flex;
         justify-content: space-between;
         gap: 16px;
         align-items: center;
         margin-bottom: 22px;
      }

      .header h1 {
         font-size: 1.6rem;
         margin-bottom: 6px;
      }

      .header p {
         color: #667085;
      }

      .btn {
         display: inline-flex;
         align-items: center;
         justify-content: center;
         border: none;
         border-radius: 8px;
         padding: 10px 16px;
         font-weight: 700;
         text-decoration: none;
         cursor: pointer;
      }

      .btn-primary {
         background: #2563eb;
         color: #ffffff;
      }

      .btn-secondary {
         background: #eef2ff;
         color: #1d4ed8;
      }

      .alert {
         border-radius: 10px;
         padding: 13px 16px;
         margin-bottom: 18px;
         font-weight: 700;
      }

      .alert-success {
         background: #e7f7ee;
         color: #137333;
      }

      .alert-error {
         background: #fdecec;
         color: #b42318;
      }

      .stock-grid {
         display: grid;
         grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
         gap: 18px;
         margin-bottom: 24px;
      }

      .stock-card {
         background: #ffffff;
         border: 1px solid #e5e9f2;
         border-radius: 14px;
         padding: 20px;
         box-shadow: 0 8px 22px rgba(18, 38, 63, 0.06);
      }

      .stock-top {
         display: flex;
         justify-content: space-between;
         gap: 12px;
         align-items: flex-start;
         margin-bottom: 16px;
      }

      .stock-name {
         font-size: 1.1rem;
         font-weight: 800;
      }

      .stock-price {
         color: #667085;
         margin-top: 4px;
      }

      .stock-count {
         min-width: 74px;
         text-align: center;
         border-radius: 12px;
         padding: 10px;
         background: #e7f7ee;
         color: #137333;
         font-weight: 900;
      }

      .stock-count.empty {
         background: #fdecec;
         color: #b42318;
      }

      .stock-count span {
         display: block;
         font-size: 0.72rem;
         text-transform: uppercase;
         letter-spacing: 0.04em;
         opacity: 0.75;
      }

      .restock-form {
         display: grid;
         grid-template-columns: 100px 1fr auto;
         gap: 10px;
      }

      input {
         width: 100%;
         border: 1px solid #d0d5dd;
         border-radius: 8px;
         padding: 10px 12px;
         font-size: 0.95rem;
      }

      .section {
         background: #ffffff;
         border: 1px solid #e5e9f2;
         border-radius: 14px;
         overflow: hidden;
         box-shadow: 0 8px 22px rgba(18, 38, 63, 0.06);
      }

      .section-title {
         padding: 18px 20px;
         border-bottom: 1px solid #e5e9f2;
         font-size: 1rem;
         font-weight: 800;
      }

      table {
         width: 100%;
         border-collapse: collapse;
      }

      th, td {
         padding: 13px 20px;
         border-bottom: 1px solid #eef1f5;
         text-align: left;
         font-size: 0.92rem;
      }

      th {
         background: #f8fafc;
         color: #667085;
         text-transform: uppercase;
         font-size: 0.75rem;
         letter-spacing: 0.04em;
      }

      tr:last-child td {
         border-bottom: none;
      }

      .type-pill {
         display: inline-flex;
         border-radius: 999px;
         padding: 5px 10px;
         font-size: 0.78rem;
         font-weight: 800;
         background: #eef2ff;
         color: #1d4ed8;
      }

      .empty-state {
         padding: 30px 20px;
         color: #667085;
         text-align: center;
      }

      @media (max-width: 760px) {
         body {
            padding: 14px;
         }

         .header {
            display: block;
         }

         .header .btn {
            margin-top: 14px;
         }

         .restock-form {
            grid-template-columns: 1fr;
         }

         .section {
            overflow-x: auto;
         }
      }
   </style>
</head>

<body>
   <div class="container">
      <div class="header">
         <div>
            <h1>Master Stok Voucher</h1>
            <p>Kelola stok paket voucher dan lakukan restok saat persediaan menipis.</p>
         </div>
         <a href="dashboard.php" class="btn btn-secondary">Kembali ke Dashboard</a>
      </div>

      <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="stock-grid">
         <?php foreach ($packages as $package): ?>
         <div class="stock-card">
            <div class="stock-top">
               <div>
                  <div class="stock-name"><?= htmlspecialchars($package['nama']) ?></div>
                  <div class="stock-price">Rp <?= number_format($package['harga'], 0, ',', '.') ?></div>
               </div>
               <div class="stock-count <?= $package['stock'] <= 0 ? 'empty' : '' ?>">
                  <?= (int)$package['stock'] ?>
                  <span>Stok</span>
               </div>
            </div>

            <form method="POST" class="restock-form">
               <input type="hidden" name="package_key" value="<?= htmlspecialchars($package['key']) ?>">
               <input type="number" name="quantity" min="1" placeholder="Jumlah" required>
               <input type="text" name="note" maxlength="255" placeholder="Catatan">
               <button type="submit" class="btn btn-primary">Restok</button>
            </form>
         </div>
         <?php endforeach; ?>
      </div>

      <div class="section">
         <div class="section-title">Histori Mutasi Stok</div>
         <?php if (!empty($movements)): ?>
         <table>
            <thead>
               <tr>
                  <th>Tanggal</th>
                  <th>Paket</th>
                  <th>Tipe</th>
                  <th>Qty</th>
                  <th>Sebelum</th>
                  <th>Sesudah</th>
                  <th>Catatan</th>
               </tr>
            </thead>
            <tbody>
               <?php foreach ($movements as $movement): ?>
               <tr>
                  <td><?= date('d/m/Y H:i', strtotime($movement['created_at'])) ?></td>
                  <td><?= htmlspecialchars($movement['package_name'] ?: $movement['package_key']) ?></td>
                  <td><span class="type-pill"><?= htmlspecialchars($movement['type']) ?></span></td>
                  <td><?= (int)$movement['quantity'] ?></td>
                  <td><?= (int)$movement['stock_before'] ?></td>
                  <td><?= (int)$movement['stock_after'] ?></td>
                  <td><?= htmlspecialchars($movement['note'] ?: '-') ?></td>
               </tr>
               <?php endforeach; ?>
            </tbody>
         </table>
         <?php else: ?>
         <div class="empty-state">Belum ada mutasi stok.</div>
         <?php endif; ?>
      </div>
   </div>
</body>

</html>
