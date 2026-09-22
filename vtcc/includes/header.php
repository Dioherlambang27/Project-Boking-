<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';
$notif_count = isset($koneksi) ? hitung_notif_belum_baca($koneksi) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($page_title) ? clean($page_title) . ' - VTCC' : 'VTCC Admin' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand">
      <img src="logo_vtcc.png" alt="VTCC" class="brand-logo">
      <div>
        <div class="brand-name">VTCC</div>
        <div class="brand-sub">Admin Panel</div>
      </div>
    </div>
    <nav class="nav">
      <a href="index.php" class="nav-item <?= ($active_menu??'')==='dashboard'?'active':'' ?>">
        <span class="nav-icon">📊</span> Dashboard
      </a>
      <a href="booking.php" class="nav-item <?= ($active_menu??'')==='booking'?'active':'' ?>">
        <span class="nav-icon">📋</span> Data Booking
      </a>
      <a href="booking_tambah.php" class="nav-item <?= ($active_menu??'')==='booking_tambah'?'active':'' ?>">
        <span class="nav-icon">➕</span> Tambah Booking
      </a>
      <a href="pembayaran_admin.php" class="nav-item <?= ($active_menu??'')==='pembayaran'?'active':'' ?>">
        <span class="nav-icon">💳</span> Verifikasi Bayar
      </a>
      <a href="mobil.php" class="nav-item <?= ($active_menu??'')==='mobil'?'active':'' ?>">
        <span class="nav-icon">🚛</span> Armada Mobil
      </a>
      <a href="pelanggan.php" class="nav-item <?= ($active_menu??'')==='pelanggan'?'active':'' ?>">
        <span class="nav-icon">👥</span> Pelanggan
      </a>
      <a href="laporan.php" class="nav-item <?= ($active_menu??'')==='laporan'?'active':'' ?>">
        <span class="nav-icon">📈</span> Laporan
      </a>
      <a href="notifikasi.php" class="nav-item <?= ($active_menu??'')==='notifikasi'?'active':'' ?>" style="position:relative;">
        <span class="nav-icon">🔔</span> Notifikasi
        <?php if ($notif_count > 0): ?>
          <span class="notif-badge"><?= $notif_count ?></span>
        <?php endif; ?>
      </a>
    </nav>
    <div class="sidebar-footer">
      <div class="user-info">
        <div class="user-avatar"><?= strtoupper(substr($_SESSION['nama_lengkap']??'A',0,1)) ?></div>
        <div>
          <div class="user-name"><?= clean($_SESSION['nama_lengkap']??'Admin') ?></div>
          <div class="user-role">Administrator</div>
        </div>
      </div>
      <a href="logout.php" class="btn-logout">🚪 Logout</a>
    </div>
  </aside>
  <main class="main">
    <div class="topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="hamburger" onclick="toggleSidebar()">☰</button>
        <div>
          <h1 class="page-title"><?= isset($page_heading) ? clean($page_heading) : 'Dashboard' ?></h1>
          <?php if (isset($page_subtitle)): ?>
            <p class="page-sub"><?= clean($page_subtitle) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <div class="topbar-right">
        <a href="notifikasi.php" class="notif-btn" title="Notifikasi">
          🔔<?php if ($notif_count > 0): ?><span class="notif-pill"><?= $notif_count ?></span><?php endif; ?>
        </a>
        <span class="topbar-date">📅 <?= date('d M Y, H:i') ?> WIB</span>
      </div>
    </div>
    <div class="content">
