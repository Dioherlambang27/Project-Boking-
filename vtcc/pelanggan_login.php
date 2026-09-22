<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['id_pelanggan'])) { header('Location: pelanggan_beranda.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = clean($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($email === '' || $password === '') {
        $error = 'Email dan password wajib diisi.';
    } else {
        $stmt = $koneksi->prepare('SELECT * FROM pelanggan WHERE email = ?');
        $stmt->bind_param('s', $email); $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $p = $result->fetch_assoc();
            if ($p['password'] && password_verify($password, $p['password'])) {
                $_SESSION['id_pelanggan']    = $p['id_pelanggan'];
                $_SESSION['nama_pelanggan']  = $p['nama_pelanggan'];
                $_SESSION['email_pelanggan'] = $p['email'];
                header('Location: pelanggan_beranda.php'); exit;
            } else { $error = 'Email atau password salah.'; }
        } else { $error = 'Email tidak ditemukan.'; }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Pelanggan - VTCC</title>
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
      <div class="login-title">Login Pelanggan</div>
      <div class="login-desc">Masuk untuk booking layanan towing kendaraan Anda</div>
      <?php if ($error): ?><div class="alert alert-danger">⚠️ <?= clean($error) ?></div><?php endif; ?>
      <?php if (isset($_GET['registered'])): ?><div class="alert alert-success">✅ Akun berhasil dibuat! Silakan login.</div><?php endif; ?>
      <form method="post" action="pelanggan_login.php">
        <div class="form-group">
          <label>📧 Email</label>
          <input type="email" name="email" placeholder="email@anda.com" required autofocus
                 value="<?= clean($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>🔐 Password</label>
          <input type="password" name="password" placeholder="Masukkan password" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px;font-size:15px;">
          Masuk 🚀
        </button>
      </form>
      <div class="login-divider">Belum punya akun?</div>
      <a href="pelanggan_daftar.php" class="btn btn-yellow" style="width:100%;justify-content:center;padding:12px;font-size:15px;">
        📝 Daftar Sekarang - Gratis!
      </a>
      <div class="login-links mt-2">
        <p><a href="login.php">← Login sebagai Admin</a></p>
      </div>
    </div>
  </div>
</div>
</body>
</html>
