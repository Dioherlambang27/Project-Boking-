<?php
/**
 * FILE: admin_daftar.php
 * VTCC - Pendaftaran Admin dengan Kode Khusus
 */
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['id_user'])) { header('Location: index.php'); exit; }

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_rahasia = trim($_POST['kode_rahasia'] ?? '');
    $username     = clean($_POST['username'] ?? '');
    $nama         = clean($_POST['nama_lengkap'] ?? '');
    $password     = $_POST['password'] ?? '';
    $konfirmasi   = $_POST['konfirmasi'] ?? '';

    if ($kode_rahasia !== KODE_DAFTAR_ADMIN) {
        $error = 'Kode pendaftaran admin salah. Hubungi super-admin.';
    } elseif (strlen($username) < 4) {
        $error = 'Username minimal 4 karakter.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $konfirmasi) {
        $error = 'Konfirmasi password tidak cocok.';
    } elseif ($nama === '') {
        $error = 'Nama lengkap wajib diisi.';
    } else {
        $cek = $koneksi->prepare('SELECT id_user FROM users WHERE username = ?');
        $cek->bind_param('s', $username);
        $cek->execute();
        if ($cek->get_result()->num_rows > 0) {
            $error = "Username '{$username}' sudah dipakai.";
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $koneksi->prepare('INSERT INTO users (username, password, nama_lengkap, role) VALUES (?,?,?,?)');
            $role = 'admin';
            $stmt->bind_param('ssss', $username, $hash, $nama, $role);
            if ($stmt->execute()) {
                $success = "Akun admin '{$username}' berhasil dibuat! Silakan login.";
            } else {
                $error = 'Gagal membuat akun: ' . $koneksi->error;
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
<title>Daftar Admin - VTCC</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="login-page">
  <div class="login-container" style="max-width:480px;">
    <div class="login-box">
      <div class="login-logo">
        <div class="login-logo-icon">🔐</div>
        <div class="login-brand">VTCC</div>
        <div class="login-brand-sub">Pendaftaran Akun Admin</div>
      </div>

      <div class="alert alert-warning">
        ⚠️ Halaman ini hanya untuk administrator resmi. Diperlukan <strong>kode pendaftaran khusus</strong>.
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger">❌ <?= clean($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= clean($success) ?><br>
        <a href="login.php" class="btn btn-primary mt-2">Pergi ke Login →</a></div>
      <?php else: ?>
      <form method="post" action="admin_daftar.php">
        <div class="form-group">
          <label>🔑 Kode Pendaftaran Admin</label>
          <input type="password" name="kode_rahasia" placeholder="Masukkan kode khusus" required autofocus>
        </div>
        <div class="form-group">
          <label>👤 Username</label>
          <input type="text" name="username" placeholder="min. 4 karakter" required value="<?= clean($_POST['username']??'') ?>">
        </div>
        <div class="form-group">
          <label>🧑 Nama Lengkap</label>
          <input type="text" name="nama_lengkap" placeholder="Nama lengkap admin" required value="<?= clean($_POST['nama_lengkap']??'') ?>">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>🔐 Password</label>
            <input type="password" name="password" placeholder="min. 6 karakter" required>
          </div>
          <div class="form-group">
            <label>🔐 Konfirmasi</label>
            <input type="password" name="konfirmasi" placeholder="Ulangi password" required>
          </div>
        </div>
        <button type="submit" class="btn btn-yellow" style="width:100%;justify-content:center;padding:13px;font-size:15px;">
          Buat Akun Admin
        </button>
      </form>
      <?php endif; ?>

      <div class="login-links mt-2">
        <p><a href="login.php">← Kembali ke Login Admin</a></p>
      </div>
    </div>
  </div>
</div>
</body>
</html>
