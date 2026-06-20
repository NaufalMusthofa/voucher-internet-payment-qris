<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user'] = $user;
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "User tidak ditemukan";
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Login - Sistem Tagihan</title>
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

   .login-container {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      border-radius: 24px;
      box-shadow:
         0 25px 50px rgba(0, 0, 0, 0.15),
         0 0 0 1px rgba(255, 255, 255, 0.2);
      padding: 50px 40px;
      width: 100%;
      max-width: 450px;
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

   .login-header {
      text-align: center;
      margin-bottom: 40px;
   }

   .login-icon {
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

   .login-title {
      color: #333;
      font-size: 32px;
      font-weight: 700;
      margin-bottom: 8px;
   }

   .login-subtitle {
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

   .form-input {
      width: 100%;
      padding: 18px 24px;
      border: 2px solid #e1e5e9;
      border-radius: 16px;
      font-size: 16px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      background: rgba(255, 255, 255, 0.9);
      color: #333;
      position: relative;
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

   .login-btn {
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
   }

   .login-btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.5s;
   }

   .login-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4);
   }

   .login-btn:hover::before {
      left: 100%;
   }

   .login-btn:active {
      transform: translateY(-1px);
   }

   .error-message {
      background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
      color: white;
      padding: 15px 20px;
      border-radius: 12px;
      margin-top: 20px;
      text-align: center;
      font-weight: 500;
      box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
      animation: shake 0.5s ease-in-out;
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

   .register-link {
      text-align: center;
      margin-top: 30px;
      padding-top: 20px;
      border-top: 1px solid rgba(0, 0, 0, 0.1);
   }

   .register-link a {
      color: #667eea;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
   }

   .register-link a:hover {
      color: #764ba2;
      text-decoration: underline;
   }

   @media (max-width: 480px) {
      .login-container {
         padding: 40px 30px;
         margin: 10px;
      }

      .login-title {
         font-size: 28px;
      }

      .login-icon {
         width: 70px;
         height: 70px;
         font-size: 32px;
      }
   }

   .loading {
      display: none;
   }

   .login-btn.loading {
      opacity: 0.8;
      cursor: not-allowed;
   }

   .login-btn.loading::after {
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

   /* Input field icons */
   .input-group {
      position: relative;
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

   .input-group .form-input {
      padding-left: 55px;
   }

   .input-group .form-input:focus+.input-icon {
      color: #667eea;
   }
   </style>
</head>

<body>
   <div class="login-container">
      <div class="login-header">
         <div class="login-icon">
            🔐
         </div>
         <h1 class="login-title">Selamat Datang</h1>
         <p class="login-subtitle">Masuk ke sistem tagihan Anda</p>
      </div>

      <form method="POST" id="loginForm">
         <div class="form-group">
            <label class="form-label" for="email">Email Address</label>
            <div class="input-group">
               <input type="email" name="email" id="email" class="form-input" placeholder="Masukkan email Anda" required
                  autocomplete="email">
               <span class="input-icon">📧</span>
            </div>
         </div>

         <button type="submit" class="login-btn" id="loginBtn">
            🚀 Masuk Sekarang
         </button>
      </form>

      <?php if (isset($error)): ?>
      <div class="error-message">
         ❌ <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <div class="register-link">
         <p>Belum punya akun? <a href="create_user.php">Buat akun baru</a></p>
      </div>
   </div>

   <script>
   // Handle form submission dengan loading state
   const form = document.getElementById('loginForm');
   const loginBtn = document.getElementById('loginBtn');

   form.addEventListener('submit', function(e) {
      // Add loading state
      loginBtn.classList.add('loading');
      loginBtn.innerHTML = '⏳ Memproses...';
      loginBtn.disabled = true;
   });

   // Auto focus on email input
   document.getElementById('email').focus();

   // Add enter key handler
   document.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
         form.submit();
      }
   });
   </script>
</body>

</html>
