<?php
include 'db.php';

$success = false;
$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    
    // Basic validation
    if (empty($name) || empty($email)) {
        $error = "Semua field harus diisi";
    } else {
        // Check if email already exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$email]);
        
        if ($checkStmt->fetch()) {
            $error = "Email sudah terdaftar";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO users (name, email) VALUES (?, ?)");
                $stmt->execute([$name, $email]);
                $success = true;
            } catch (PDOException $e) {
                $error = "Terjadi kesalahan saat menyimpan data";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Buat Akun Baru - Sistem Tagihan</title>
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
      position: relative;
      overflow: hidden;
   }

   /* Animated background elements */
   body::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background:
         radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
         radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
         radial-gradient(circle at 40% 40%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
      animation: float 20s ease-in-out infinite;
   }

   @keyframes float {

      0%,
      100% {
         transform: translateY(0px);
      }

      50% {
         transform: translateY(-20px);
      }
   }

   .container {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      border-radius: 24px;
      box-shadow:
         0 25px 50px rgba(0, 0, 0, 0.15),
         0 0 0 1px rgba(255, 255, 255, 0.2);
      padding: 50px 40px;
      width: 100%;
      max-width: 500px;
      position: relative;
      z-index: 1;
      animation: slideUp 0.8s ease-out;
   }

   @keyframes slideUp {
      from {
         opacity: 0;
         transform: translateY(50px);
      }

      to {
         opacity: 1;
         transform: translateY(0);
      }
   }

   .header {
      text-align: center;
      margin-bottom: 40px;
   }

   .header-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      color: white;
      font-size: 36px;
      box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
      animation: pulse 2s infinite;
   }

   @keyframes pulse {
      0% {
         transform: scale(1);
      }

      50% {
         transform: scale(1.05);
      }

      100% {
         transform: scale(1);
      }
   }

   .header h1 {
      color: #333;
      font-size: 32px;
      font-weight: 700;
      margin-bottom: 8px;
   }

   .header p {
      color: #666;
      font-size: 16px;
      font-weight: 400;
   }

   .form-group {
      margin-bottom: 25px;
   }

   .form-label {
      display: block;
      margin-bottom: 8px;
      color: #333;
      font-weight: 600;
      font-size: 16px;
   }

   .input-group {
      position: relative;
   }

   .form-input {
      width: 100%;
      padding: 18px 24px 18px 55px;
      border: 2px solid #e1e5e9;
      border-radius: 16px;
      font-size: 16px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      background: rgba(255, 255, 255, 0.9);
      color: #333;
   }

   .form-input:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
      transform: translateY(-2px);
      background: white;
   }

   .form-input::placeholder {
      color: #999;
      transition: all 0.3s ease;
   }

   .form-input:focus::placeholder {
      opacity: 0.7;
      transform: translateX(10px);
   }

   .input-icon {
      position: absolute;
      left: 20px;
      top: 50%;
      transform: translateY(-50%);
      color: #999;
      font-size: 18px;
      transition: all 0.3s ease;
   }

   .form-input:focus+.input-icon {
      color: #667eea;
   }

   .submit-btn {
      width: 100%;
      padding: 18px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      border-radius: 16px;
      font-size: 18px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
      position: relative;
      overflow: hidden;
      margin-bottom: 20px;
   }

   .submit-btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.5s;
   }

   .submit-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4);
   }

   .submit-btn:hover::before {
      left: 100%;
   }

   .submit-btn:active {
      transform: translateY(-1px);
   }

   .login-link {
      text-align: center;
      padding: 20px;
      background: rgba(102, 126, 234, 0.1);
      border-radius: 12px;
      margin-bottom: 20px;
   }

   .login-link a {
      color: #667eea;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
   }

   .login-link a:hover {
      color: #764ba2;
      text-decoration: underline;
      transform: translateX(-5px);
   }

   .success-message {
      background: linear-gradient(135deg, #4CAF50, #66BB6A);
      color: white;
      padding: 20px;
      border-radius: 12px;
      margin-bottom: 20px;
      text-align: center;
      font-weight: 500;
      box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
      animation: bounceIn 0.6s ease-out;
   }

   .error-message {
      background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
      color: white;
      padding: 15px 20px;
      border-radius: 12px;
      margin-bottom: 20px;
      text-align: center;
      font-weight: 500;
      box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
      animation: shake 0.5s ease-in-out;
   }

   @keyframes bounceIn {
      0% {
         opacity: 0;
         transform: scale(0.3);
      }

      50% {
         opacity: 1;
         transform: scale(1.05);
      }

      70% {
         transform: scale(0.9);
      }

      100% {
         opacity: 1;
         transform: scale(1);
      }
   }

   @keyframes shake {

      0%,
      100% {
         transform: translateX(0);
      }

      25% {
         transform: translateX(-5px);
      }

      75% {
         transform: translateX(5px);
      }
   }

   .action-buttons {
      display: flex;
      gap: 10px;
   }

   .btn-secondary {
      flex: 1;
      padding: 15px;
      background: rgba(102, 126, 234, 0.1);
      color: #667eea;
      border: 2px solid #667eea;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
   }

   .btn-secondary:hover {
      background: #667eea;
      color: white;
      transform: translateY(-2px);
   }

   @media (max-width: 480px) {
      .container {
         padding: 40px 30px;
         margin: 10px;
      }

      .header h1 {
         font-size: 28px;
      }

      .header-icon {
         width: 70px;
         height: 70px;
         font-size: 32px;
      }

      .action-buttons {
         flex-direction: column;
      }
   }

   .loading {
      display: none;
   }

   .submit-btn.loading {
      opacity: 0.8;
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
         <div class="header-icon">
            👤
         </div>
         <h1>Buat Akun Baru</h1>
         <p>Daftar untuk mengakses sistem tagihan</p>
      </div>

      <div class="login-link">
         <a href="login.php">
            🔙 Kembali ke Halaman Login
         </a>
      </div>

      <?php if ($success): ?>
      <div class="success-message">
         ✅ Akun berhasil dibuat! Anda dapat login sekarang.
      </div>
      <div class="action-buttons">
         <a href="login.php" class="btn-secondary">
            🚀 Login Sekarang
         </a>
         <a href="user_management.php" class="btn-secondary">
            👥 Kelola User
         </a>
      </div>
      <?php else: ?>
      <?php if (!empty($error)): ?>
      <div class="error-message">
         ❌ <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" id="createUserForm">
         <div class="form-group">
            <label class="form-label" for="name">Nama Lengkap</label>
            <div class="input-group">
               <input type="text" name="name" id="name" class="form-input" placeholder="Masukkan nama lengkap"
                  value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" required
                  autocomplete="name">
               <span class="input-icon">👤</span>
            </div>
         </div>

         <div class="form-group">
            <label class="form-label" for="email">Email Address</label>
            <div class="input-group">
               <input type="email" name="email" id="email" class="form-input" placeholder="Masukkan alamat email"
                  value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required
                  autocomplete="email">
               <span class="input-icon">📧</span>
            </div>
         </div>

         <button type="submit" class="submit-btn" id="submitBtn">
            ✨ Buat Akun
         </button>
      </form>
      <?php endif; ?>
   </div>

   <script>
   // Handle form submission dengan loading state
   const form = document.getElementById('createUserForm');
   const submitBtn = document.getElementById('submitBtn');

   if (form) {
      form.addEventListener('submit', function(e) {
         // Add loading state
         submitBtn.classList.add('loading');
         submitBtn.innerHTML = '⏳ Memproses...';
         submitBtn.disabled = true;
      });
   }

   // Auto focus on name input
   const nameInput = document.getElementById('name');
   if (nameInput) {
      nameInput.focus();
   }

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

   // Real-time email validation
   const emailInput = document.getElementById('email');
   if (emailInput) {
      emailInput.addEventListener('input', function() {
         const email = this.value;
         const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

         if (email && !emailRegex.test(email)) {
            this.style.borderColor = '#ff6b6b';
         } else {
            this.style.borderColor = '#e1e5e9';
         }
      });
   }
   </script>
</body>

</html>