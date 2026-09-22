<?php
/**
 * FILE: pelanggan_daftar.php
 * VTCC - Registrasi Pelanggan
 */
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['id_pelanggan'])) { header('Location: pelanggan_beranda.php'); exit; }

$error = ''; $success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama       = clean($_POST['nama_pelanggan'] ?? '');
    $email      = clean($_POST['email'] ?? '');
    $no_hp      = clean($_POST['no_hp'] ?? '');
    $no_ktp     = clean($_POST['no_ktp'] ?? '');
    $alamat     = clean($_POST['alamat'] ?? '');
    $password   = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi'] ?? '';

    if (!$nama || !$email || !$no_hp || !$password) {
        $error = 'Nama, email, no HP, dan password wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $konfirmasi) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $cek = $koneksi->prepare('SELECT id_pelanggan FROM pelanggan WHERE email=?');
        $cek->bind_param('s', $email); $cek->execute();
        if ($cek->get_result()->num_rows > 0) {
            $error = 'Email sudah terdaftar. Silakan login.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $koneksi->prepare('INSERT INTO pelanggan (nama_pelanggan, email, no_hp, no_ktp, alamat, password) VALUES (?,?,?,?,?,?)');
            $stmt->bind_param('ssssss', $nama, $email, $no_hp, $no_ktp, $alamat, $hash);
            if ($stmt->execute()) {
                header('Location: pelanggan_login.php?registered=1'); exit;
            } else {
                $error = 'Gagal mendaftar: ' . $koneksi->error;
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
<title>Daftar Pelanggan - VTCC</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="login-page">
  <div class="login-container" style="max-width:520px;">
    <div class="login-box">
      <div class="login-logo">
        <div class="login-logo-icon">📝</div>
        <div class="login-brand">Daftar Akun</div>
        <div class="login-brand-sub">VTCC - Vehicle Towing Service</div>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger">❌ <?= clean($error) ?></div>
      <?php endif; ?>

      <form method="post" action="pelanggan_daftar.php">
        <div class="form-group">
          <label>🧑 Nama Lengkap *</label>
          <input type="text" name="nama_pelanggan" placeholder="Nama sesuai KTP" required value="<?= clean($_POST['nama_pelanggan']??'') ?>">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>📧 Email *</label>
            <input type="email" name="email" placeholder="email@anda.com" required value="<?= clean($_POST['email']??'') ?>">
          </div>
          <div class="form-group">
            <label>📱 No. HP *</label>
            <input type="tel" name="no_hp" placeholder="08xxxxxxxxxx" required value="<?= clean($_POST['no_hp']??'') ?>">
          </div>
        </div>
        <div class="form-group">
          <label>🪪 No. KTP (Opsional)</label>
          <input type="text" name="no_ktp" placeholder="16 digit nomor KTP" maxlength="16" value="<?= clean($_POST['no_ktp']??'') ?>">
        </div>
        <div class="form-group">
          <label>📍 Alamat (Opsional)</label>
          <textarea name="alamat" rows="2" placeholder="Alamat lengkap Anda"><?= clean($_POST['alamat']??'') ?></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>🔐 Password *</label>
            <input type="password" name="password" placeholder="min. 6 karakter" required>
          </div>
          <div class="form-group">
            <label>🔐 Konfirmasi *</label>
            <input type="password" name="konfirmasi" placeholder="Ulangi password" required>
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;font-size:15px;">
          Daftar Sekarang →
        </button>
      </form>

      <div class="login-links mt-2">
        <p>Sudah punya akun? <a href="pelanggan_login.php">Login di sini</a></p>
      </div>
    </div>
  </div>
</div>
</body>
</html>
