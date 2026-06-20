<?php
session_start();
include 'db.php';
require_once 'auth_helpers.php';

ensureUserRoleSchema($pdo);
refreshSessionUser($pdo);
requireAdmin();

include 'views/header.php';

$stmt = $pdo->query("SELECT * FROM users");
$users = $stmt->fetchAll();
?>

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
   padding: 30px;
   box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
   backdrop-filter: blur(10px);
}

.header {
   display: flex;
   justify-content: space-between;
   align-items: center;
   margin-bottom: 30px;
   padding-bottom: 20px;
   border-bottom: 2px solid #e0e0e0;
}

.header h2 {
   color: #2c3e50;
   font-size: 2.5rem;
   font-weight: 700;
   background: linear-gradient(45deg, #667eea, #764ba2);
   -webkit-background-clip: text;
   -webkit-text-fill-color: transparent;
   background-clip: text;
}

.header-actions {
   display: flex;
   gap: 15px;
}

.btn {
   padding: 12px 24px;
   border: none;
   border-radius: 50px;
   text-decoration: none;
   font-weight: 600;
   font-size: 14px;
   transition: all 0.3s ease;
   cursor: pointer;
   display: inline-flex;
   align-items: center;
   gap: 8px;
   text-transform: uppercase;
   letter-spacing: 0.5px;
}

.btn-primary {
   background: linear-gradient(45deg, #667eea, #764ba2);
   color: white;
   box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.btn-primary:hover {
   transform: translateY(-2px);
   box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
}

.btn-secondary {
   background: linear-gradient(45deg, #2ecc71, #27ae60);
   color: white;
   box-shadow: 0 4px 15px rgba(46, 204, 113, 0.4);
}

.btn-secondary:hover {
   transform: translateY(-2px);
   box-shadow: 0 8px 25px rgba(46, 204, 113, 0.6);
}

.table-container {
   background: white;
   border-radius: 15px;
   overflow: hidden;
   box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
   margin-top: 20px;
}

.table {
   width: 100%;
   border-collapse: collapse;
   font-size: 16px;
}

.table thead {
   background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
   color: white;
}

.table th {
   padding: 20px;
   text-align: left;
   font-weight: 600;
   font-size: 14px;
   text-transform: uppercase;
   letter-spacing: 1px;
}

.table td {
   padding: 18px 20px;
   border-bottom: 1px solid #f0f0f0;
   color: #2c3e50;
   font-weight: 500;
}

.table tbody tr {
   transition: all 0.3s ease;
}

.table tbody tr:hover {
   background: linear-gradient(90deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
   transform: scale(1.01);
}

.table tbody tr:last-child td {
   border-bottom: none;
}

.user-id {
   background: linear-gradient(45deg, #667eea, #764ba2);
   color: white;
   padding: 8px 12px;
   border-radius: 20px;
   font-weight: 600;
   font-size: 12px;
   display: inline-block;
   min-width: 40px;
   text-align: center;
}

.user-name {
   font-weight: 600;
   color: #2c3e50;
}

.user-email {
   color: #7f8c8d;
   font-style: italic;
}

.role-badge {
   display: inline-block;
   padding: 7px 12px;
   border-radius: 999px;
   font-size: 12px;
   font-weight: 800;
   text-transform: uppercase;
   letter-spacing: 0.4px;
}

.role-admin {
   background: #e8f0ff;
   color: #1d4ed8;
}

.role-pelanggan {
   background: #e7f7ee;
   color: #137333;
}

.stats {
   display: flex;
   justify-content: space-between;
   margin-bottom: 30px;
   gap: 20px;
}

.stat-card {
   flex: 1;
   background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.7));
   padding: 25px;
   border-radius: 15px;
   text-align: center;
   box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
   backdrop-filter: blur(10px);
   border: 1px solid rgba(255, 255, 255, 0.2);
}

.stat-number {
   font-size: 2.5rem;
   font-weight: 700;
   background: linear-gradient(45deg, #667eea, #764ba2);
   -webkit-background-clip: text;
   -webkit-text-fill-color: transparent;
   background-clip: text;
}

.stat-label {
   color: #7f8c8d;
   font-weight: 500;
   text-transform: uppercase;
   letter-spacing: 1px;
   font-size: 14px;
   margin-top: 5px;
}

.empty-state {
   text-align: center;
   padding: 60px 20px;
   color: #7f8c8d;
}

.empty-state h3 {
   font-size: 1.5rem;
   margin-bottom: 10px;
   color: #2c3e50;
}

@media (max-width: 768px) {
   .container {
      padding: 20px;
      margin: 10px;
   }

   .header {
      flex-direction: column;
      gap: 20px;
      text-align: center;
   }

   .header-actions {
      flex-direction: column;
      width: 100%;
   }

   .btn {
      width: 100%;
      justify-content: center;
   }

   .stats {
      flex-direction: column;
   }

   .table-container {
      overflow-x: auto;
   }

   .table {
      min-width: 500px;
   }
}

.animate-fade-in {
   animation: fadeIn 0.6s ease-out;
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

<div class="container animate-fade-in">
   <div class="header">
      <h2>🚀 User Management</h2>
      <div class="header-actions">
         <a href="create_user.php" class="btn btn-primary">
            ➕ Tambah User
         </a>
         <a href="dashboard.php" class="btn btn-secondary">
            📊 Dashboard
         </a>
      </div>
   </div>

   <div class="stats">
      <div class="stat-card">
         <div class="stat-number"><?= count($users) ?></div>
         <div class="stat-label">Total Users</div>
      </div>
      <div class="stat-card">
         <div class="stat-number"><?= count(array_filter($users, function($u) { return ($u['role'] ?? 'pelanggan') === 'admin'; })) ?></div>
         <div class="stat-label">Admin</div>
      </div>
      <div class="stat-card">
         <div class="stat-number"><?= count(array_filter($users, function($u) { return ($u['role'] ?? 'pelanggan') === 'pelanggan'; })) ?></div>
         <div class="stat-label">Pelanggan</div>
      </div>
   </div>

   <?php if (count($users) > 0): ?>
   <div class="table-container">
      <table class="table">
         <thead>
            <tr>
               <th>🆔 ID</th>
               <th>👤 Nama</th>
               <th>📧 Email</th>
               <th>Role</th>
            </tr>
         </thead>
         <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
               <td>
                  <span class="user-id"><?= htmlspecialchars($u['id']) ?></span>
               </td>
               <td>
                  <span class="user-name"><?= htmlspecialchars($u['name']) ?></span>
               </td>
               <td>
                  <span class="user-email"><?= htmlspecialchars($u['email']) ?></span>
               </td>
               <td>
                  <?php $role = $u['role'] ?? 'pelanggan'; ?>
                  <span class="role-badge role-<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></span>
               </td>
            </tr>
            <?php endforeach; ?>
         </tbody>
      </table>
   </div>
   <?php else: ?>
   <div class="empty-state">
      <h3>🤷‍♂️ Belum ada user</h3>
      <p>Klik tombol "Tambah User" untuk menambahkan user pertama.</p>
   </div>
   <?php endif; ?>
</div>

<?php include 'views/footer.php'; ?>

<script>
// Auto refresh every 30 seconds
setTimeout(() => {
   location.reload();
}, 15000);
</script>
