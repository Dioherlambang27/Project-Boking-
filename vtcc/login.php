<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['id_user'])) { header('Location: index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $koneksi->prepare('SELECT id_user, username, password, nama_lengkap FROM users WHERE username = ?');
        $stmt->bind_param('s', $username); $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['id_user']      = $user['id_user'];
                $_SESSION['username']     = $user['username'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                header('Location: index.php'); exit;
            } else { $error = 'Username atau password salah.'; }
        } else { $error = 'Username atau password salah.'; }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Admin - VTCC</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="login-page">
  <div class="login-container">
    <div class="login-box">
      <div class="login-logo" style="text-align:center;margin-bottom:28px;">
        <img src="logo_vtcc.png" alt="VTCC Logo" class="login-logo-img">
        <div class="login-brand">VTCC</div>
        <div class="login-brand-sub">Vehicle Testing And Certification Center</div>
      </div>
      <div class="login-title">Login Administrator</div>
      <div class="login-desc">Masukkan kredensial admin Anda untuk mengakses dashboard</div>
      <?php if ($error): ?><div class="alert alert-danger">⚠️ <?= clean($error) ?></div><?php endif; ?>
      <form method="post" action="login.php">
        <div class="form-group">
          <label>👤 Username Admin</label>
          <input type="text" name="username" placeholder="Masukkan username" required autofocus
                 value="<?= clean($_POST['username'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>🔐 Password</label>
          <input type="password" name="password" placeholder="Masukkan password" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px;font-size:15px;">
          Masuk ke Dashboard →
        </button>
      </form>
      <div class="login-divider">atau</div>
      <div class="login-links">
        <p><a href="admin_daftar.php">📝 Daftar akun admin baru</a> <span style="color:#ccc">|</span>
        <a href="pelanggan_login.php">🚗 Portal Pelanggan</a></p>
      </div>
    </div>
  </div>
</div>
</body>
</html>
