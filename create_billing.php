<?php
include 'db.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user_id = $_POST['user_id'];
    $amount = $_POST['amount'];
    $billing_code = 'BILL' . time(); // contoh kode unik

    $stmt = $pdo->prepare("INSERT INTO billings (user_id, billing_code, amount) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $billing_code, $amount]);

    header("Location: dashboard.php");
    exit;
}

$users = $pdo->query("SELECT id, name FROM users")->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Buat Tagihan Baru</title>
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

   .container {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
      padding: 40px;
      width: 100%;
      max-width: 500px;
      border: 1px solid rgba(255, 255, 255, 0.2);
   }

   .header {
      text-align: center;
      margin-bottom: 30px;
   }

   .header h2 {
      color: #333;
      font-size: 28px;
      font-weight: 600;
      margin-bottom: 10px;
   }

   .header p {
      color: #666;
      font-size: 16px;
   }

   .form-group {
      margin-bottom: 25px;
   }

   .form-label {
      display: block;
      margin-bottom: 8px;
      color: #333;
      font-weight: 500;
      font-size: 16px;
   }

   .form-select,
   .form-input {
      width: 100%;
      padding: 15px 20px;
      border: 2px solid #e1e5e9;
      border-radius: 12px;
      font-size: 16px;
      transition: all 0.3s ease;
      background: white;
      color: #333;
   }

   .form-select:focus,
   .form-input:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
      transform: translateY(-2px);
   }

   .form-select {
      cursor: pointer;
      appearance: none;
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
      background-position: right 15px center;
      background-repeat: no-repeat;
      background-size: 16px;
      padding-right: 50px;
   }

   .form-input[type="number"] {
      -moz-appearance: textfield;
   }

   .form-input[type="number"]::-webkit-outer-spin-button,
   .form-input[type="number"]::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
   }

   .currency-input {
      position: relative;
   }

   .currency-symbol {
      position: absolute;
      left: 20px;
      top: 50%;
      transform: translateY(-50%);
      color: #666;
      font-weight: 500;
      z-index: 1;
   }

   .currency-input .form-input {
      padding-left: 50px;
   }

   .submit-btn {
      width: 100%;
      padding: 16px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 18px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
   }

   .submit-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
   }

   .submit-btn:active {
      transform: translateY(0);
   }

   .icon {
      width: 60px;
      height: 60px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 15px;
      color: white;
      font-size: 24px;
   }

   @media (max-width: 480px) {
      .container {
         padding: 30px 20px;
         margin: 10px;
      }

      .header h2 {
         font-size: 24px;
      }
   }

   .form-group:hover .form-select,
   .form-group:hover .form-input {
      border-color: #c7d2fe;
   }

   .loading {
      display: none;
   }

   .submit-btn.loading {
      opacity: 0.7;
      cursor: not-allowed;
   }

   .submit-btn.loading::after {
      content: '';
      width: 20px;
      height: 20px;
      border: 2px solid transparent;
      border-top-color: white;
      border-radius: 50%;
      display: inline-block;
      animation: spin 1s linear infinite;
      margin-left: 10px;
   }

   @keyframes spin {
      0% {
         transform: rotate(0deg);
      }

      100% {
         transform: rotate(360deg);
      }
   }
   </style>
</head>

<body>
   <div class="container">
      <div class="header">
         <div class="icon">
            📄
         </div>
         <h2>Buat Tagihan Baru</h2>
         <p>Isi form di bawah untuk membuat tagihan baru</p>
      </div>

      <form method="POST" id="billingForm">
         <div class="form-group">
            <label class="form-label" for="user_id">
               👤 Pilih Pengguna
            </label>
            <select name="user_id" id="user_id" class="form-select" required>
               <option value="">-- Pilih Pengguna --</option>
               <?php foreach ($users as $user): ?>
               <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?></option>
               <?php endforeach; ?>
            </select>
         </div>

         <div class="form-group">
            <label class="form-label" for="amount">
               💰 Jumlah Tagihan
            </label>
            <div class="currency-input">
               <span class="currency-symbol">Rp</span>
               <input type="number" name="amount" id="amount" class="form-input" placeholder="0">
            </div>
         </div>

         <button type="submit" class="submit-btn" id="submitBtn">
            ✨ Buat Tagihan
         </button>
      </form>
   </div>

   <script>
   // Format number input dengan pemisah ribuan
   const amountInput = document.getElementById('amount');

   amountInput.addEventListener('input', function(e) {
      let value = e.target.value.replace(/\D/g, '');
      if (value) {
         e.target.value = parseInt(value).toLocaleString('id-ID');
      }
   });

   // Handle form submission dengan loading state
   const form = document.getElementById('billingForm');
   const submitBtn = document.getElementById('submitBtn');

   form.addEventListener('submit', function(e) {
      // Remove formatting before submit
      const rawValue = amountInput.value.replace(/\D/g, '');
      amountInput.value = rawValue;

      // Add loading state
      submitBtn.classList.add('loading');
      submitBtn.innerHTML = '⏳ Memproses...';
      submitBtn.disabled = true;
   });

   // Add smooth animations
   const formGroups = document.querySelectorAll('.form-group');
   formGroups.forEach((group, index) => {
      group.style.opacity = '0';
      group.style.transform = 'translateY(20px)';

      setTimeout(() => {
         group.style.transition = 'all 0.5s ease';
         group.style.opacity = '1';
         group.style.transform = 'translateY(0)';
      }, index * 200);
   });
   </script>
</body>

</html>