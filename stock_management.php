<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
require_once 'auth_helpers.php';
require_once 'voucher_inventory_helpers.php';

ensureUserRoleSchema($pdo);
refreshSessionUser($pdo);
requireAdmin();
ensureVoucherStockSchema($pdo);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? 'add';
        if ($action === 'delete') {
            deleteAvailableVoucherCode($pdo, (int)($_POST['voucher_id'] ?? 0));
            $success = 'Kode voucher berhasil dihapus.';
        } elseif ($action === 'update') {
            updateAvailableVoucherCode($pdo, (int)($_POST['voucher_id'] ?? 0), $_POST['package_key'] ?? '', $_POST['voucher_code'] ?? '');
            $success = 'Kode voucher berhasil diperbarui.';
        } else {
            $packageKeys = $_POST['package_key'] ?? [];
            $voucherCodes = $_POST['voucher_code'] ?? [];
            $rows = [];
            foreach ($packageKeys as $index => $packageKey) {
                $rows[] = ['package_key' => $packageKey, 'voucher_code' => $voucherCodes[$index] ?? ''];
            }
            $added = addVoucherCodes($pdo, $rows);
            $success = $added . ' kode voucher berhasil ditambahkan.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$packages = getVoucherPackagesWithStock($pdo);
$inventory = getVoucherInventory($pdo);
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
         gap: 12px;
      }

      .voucher-row {
         display: grid;
         grid-template-columns: minmax(220px, 1fr) minmax(240px, 1.4fr) auto;
         gap: 10px;
         align-items: center;
      }

      select {
         width: 100%; border: 1px solid #d0d5dd; border-radius: 8px;
         padding: 10px 12px; font-size: 0.95rem; background: #fff;
      }

      .form-actions { display: flex; gap: 10px; margin-top: 14px; flex-wrap: wrap; }
      .btn-danger { background: #fee4e2; color: #b42318; }
      .status-pill { display:inline-flex; padding:5px 10px; border-radius:999px; font-weight:800; font-size:.76rem; }
      .status-available { background:#e7f7ee; color:#137333; }
      .status-reserved { background:#fff4db; color:#8a5a08; }
      .status-sold { background:#eef2ff; color:#1d4ed8; }
      .code { font-family: Consolas, monospace; font-weight: 800; }

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

         .voucher-row { grid-template-columns: 1fr; padding-bottom: 14px; border-bottom: 1px solid #eef1f5; }

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
            <p>Tambahkan kode voucher asli. Satu kode yang tersedia dihitung sebagai satu stok.</p>
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
                  <?= (int)$package['stock'] ?><span>Tersedia</span>
               </div>
            </div>
         </div>
         <?php endforeach; ?>
      </div>

      <div class="section" style="padding:20px; margin-bottom:24px; overflow:visible;">
         <div class="section-title" style="padding:0 0 16px; margin-bottom:16px;">Tambah Kode Voucher</div>
         <form method="POST" class="restock-form" id="voucherForm">
            <input type="hidden" name="action" value="add">
            <div id="voucherRows">
               <div class="voucher-row">
                  <select name="package_key[]" required>
                     <option value="">Pilih paket voucher</option>
                     <?php foreach ($packages as $package): ?>
                     <option value="<?= htmlspecialchars($package['key']) ?>"><?= htmlspecialchars($package['nama']) ?></option>
                     <?php endforeach; ?>
                  </select>
                  <input type="text" name="voucher_code[]" maxlength="100" placeholder="Masukkan kode voucher dari admin" required>
                  <button type="button" class="btn btn-danger remove-row">Hapus Baris</button>
               </div>
            </div>
            <div class="form-actions">
               <button type="button" class="btn btn-secondary" id="addRow">+ Tambah Baris</button>
               <button type="submit" class="btn btn-primary">Simpan Voucher</button>
            </div>
         </form>
      </div>

      <div class="section" style="margin-bottom:24px;">
         <div class="section-title">Data Voucher</div>
         <?php if ($inventory): ?>
         <table>
            <thead><tr><th>Nama</th><th>No. HP</th><th>Paket Voucher</th><th>Kode Voucher</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
               <?php foreach ($inventory as $voucher): ?>
               <tr>
                  <td><?= htmlspecialchars($voucher['customer_name'] ?: '-') ?></td>
                  <td><?= htmlspecialchars($voucher['customer_phone'] ?: '-') ?></td>
                  <td>
                     <?php if ($voucher['status'] === 'available'): ?>
                     <select name="package_key" form="edit-voucher-<?= (int)$voucher['id'] ?>">
                        <?php foreach ($packages as $package): ?>
                        <option value="<?= htmlspecialchars($package['key']) ?>" <?= $package['key'] === $voucher['package_key'] ? 'selected' : '' ?>><?= htmlspecialchars($package['nama']) ?></option>
                        <?php endforeach; ?>
                     </select>
                     <?php else: ?><?= htmlspecialchars($voucher['package_name']) ?><?php endif; ?>
                  </td>
                  <td>
                     <?php if ($voucher['status'] === 'available'): ?>
                     <input class="code" type="text" name="voucher_code" maxlength="100" value="<?= htmlspecialchars($voucher['voucher_code']) ?>" form="edit-voucher-<?= (int)$voucher['id'] ?>" required>
                     <?php else: ?><span class="code"><?= htmlspecialchars($voucher['voucher_code']) ?></span><?php endif; ?>
                  </td>
                  <td><span class="status-pill status-<?= htmlspecialchars($voucher['status']) ?>"><?= htmlspecialchars($voucher['status']) ?></span></td>
                  <td>
                     <?php if ($voucher['status'] === 'available'): ?>
                     <form method="POST" id="edit-voucher-<?= (int)$voucher['id'] ?>" style="display:inline">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="voucher_id" value="<?= (int)$voucher['id'] ?>">
                        <button type="submit" class="btn btn-primary">Update</button>
                     </form>
                     <form method="POST" style="display:inline" onsubmit="return confirm('Hapus kode voucher ini?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="voucher_id" value="<?= (int)$voucher['id'] ?>">
                        <button type="submit" class="btn btn-danger">Hapus</button>
                     </form>
                     <?php else: ?>—<?php endif; ?>
                  </td>
               </tr>
               <?php endforeach; ?>
            </tbody>
         </table>
         <?php else: ?><div class="empty-state">Belum ada kode voucher. Tambahkan kode melalui form di atas.</div><?php endif; ?>
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
   <script>
   const rowsContainer = document.getElementById('voucherRows');
   const firstRow = rowsContainer.querySelector('.voucher-row');

   document.getElementById('addRow').addEventListener('click', function() {
      const row = firstRow.cloneNode(true);
      row.querySelector('select').value = '';
      row.querySelector('input').value = '';
      rowsContainer.appendChild(row);
   });

   rowsContainer.addEventListener('click', function(event) {
      if (!event.target.classList.contains('remove-row')) return;
      const rows = rowsContainer.querySelectorAll('.voucher-row');
      if (rows.length === 1) {
         rows[0].querySelector('select').value = '';
         rows[0].querySelector('input').value = '';
         return;
      }
      event.target.closest('.voucher-row').remove();
   });
   </script>
</body>

</html>
