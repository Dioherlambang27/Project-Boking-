<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';
$notif_pel_count = 0;
if (isset($_SESSION['id_pelanggan']) && isset($koneksi)) {
    $notif_pel_count = hitung_notif_pelanggan($koneksi, (int)$_SESSION['id_pelanggan']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($page_title) ? clean($page_title) . ' - VTCC' : 'VTCC Towing' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
</head>
<body class="pelanggan-body">
<header class="top-nav">
  <div class="top-nav-inner">
    <a href="pelanggan_beranda.php" class="nav-brand">
      <img src="logo_vtcc.png" alt="VTCC" style="height:38px;width:38px;object-fit:cover;border-radius:8px;">
      <span class="brand-text">VTCC <span class="brand-thin">Towing</span></span>
    </a>
    <nav class="top-links">
      <a href="pelanggan_beranda.php"   class="top-link <?= ($active_menu_p??'')==='beranda'      ? 'active':'' ?>">🏠 Beranda</a>
      <a href="pelanggan_booking.php"   class="top-link <?= ($active_menu_p??'')==='booking'      ? 'active':'' ?>">📋 Booking</a>
      <a href="pelanggan_riwayat.php"   class="top-link <?= ($active_menu_p??'')==='riwayat'      ? 'active':'' ?>">📜 Riwayat</a>
      <a href="pelanggan_notifikasi.php" class="top-link top-link-notif <?= ($active_menu_p??'')==='notifikasi' ? 'active':'' ?>">
        🔔 Notifikasi
        <?php if ($notif_pel_count > 0): ?>
          <span class="top-notif-badge"><?= $notif_pel_count ?></span>
        <?php endif; ?>
      </a>
    </nav>
    <div class="top-user">
      <span class="top-avatar"><?= strtoupper(substr($_SESSION['nama_pelanggan']??'P', 0, 1)) ?></span>
      <span class="top-username"><?= clean($_SESSION['nama_pelanggan']??'Pelanggan') ?></span>
      <a href="pelanggan_logout.php" class="top-logout">Logout</a>
    </div>
    <!-- Mobile hamburger -->
    <button class="top-hamburger" onclick="toggleMobileNav()">☰</button>
  </div>
  <!-- Mobile menu -->
  <div class="mobile-nav" id="mobileNav">
    <a href="pelanggan_beranda.php"    class="mobile-nav-item">🏠 Beranda</a>
    <a href="pelanggan_booking.php"    class="mobile-nav-item">📋 Booking</a>
    <a href="pelanggan_riwayat.php"    class="mobile-nav-item">📜 Riwayat</a>
    <a href="pelanggan_notifikasi.php" class="mobile-nav-item">
      🔔 Notifikasi
      <?php if ($notif_pel_count > 0): ?>
        <span class="top-notif-badge" style="position:relative;top:0;right:0;margin-left:6px;"><?= $notif_pel_count ?></span>
      <?php endif; ?>
    </a>
    <a href="pelanggan_logout.php"     class="mobile-nav-item" style="color:var(--danger);">🚪 Logout</a>
  </div>
</header>
<div class="pelanggan-main">
  <div class="pelanggan-content">
