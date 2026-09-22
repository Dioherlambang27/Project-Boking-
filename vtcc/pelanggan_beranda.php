<?php
require_once 'config/db.php';
require_once 'includes/auth_pelanggan.php';
require_once 'includes/functions.php';
require_login_pelanggan();

$page_title    = 'Beranda';
$active_menu_p = 'beranda';
$id_pel        = (int)$_SESSION['id_pelanggan'];

// Hitung notif belum baca
$notif_unread_count = hitung_notif_pelanggan($koneksi, $id_pel);

// Booking menunggu pembayaran
$stmtWP = $koneksi->prepare(
    "SELECT b.id_booking, b.kode_booking, b.total_bayar, b.status
     FROM booking b
     LEFT JOIN pembayaran pm ON pm.id_booking = b.id_booking
     WHERE b.id_pelanggan = ? AND b.status = 'Menunggu Pembayaran' AND pm.id_pembayaran IS NULL"
);
$stmtWP->bind_param('i', $id_pel); $stmtWP->execute();
$booking_belum_bayar = $stmtWP->get_result();

// Statistik
$stmt2 = $koneksi->prepare("SELECT COUNT(*) total, COALESCE(SUM(total_bayar),0) pengeluaran FROM booking WHERE id_pelanggan=? AND status='Selesai'");
$stmt2->bind_param('i', $id_pel); $stmt2->execute();
$stat = $stmt2->get_result()->fetch_assoc();

// Riwayat terbaru
$stmt3 = $koneksi->prepare(
    "SELECT b.*, m.nama_mobil, m.jenis, pm.metode, pm.status_bayar
     FROM booking b
     JOIN mobil_towing m ON m.id_mobil = b.id_mobil
     LEFT JOIN pembayaran pm ON pm.id_booking = b.id_booking
     WHERE b.id_pelanggan = ?
     ORDER BY b.created_at DESC LIMIT 5"
);
$stmt3->bind_param('i', $id_pel); $stmt3->execute();
$riwayat = $stmt3->get_result();

$mobil_list = $koneksi->query("SELECT * FROM mobil_towing ORDER BY jenis, nama_mobil");
$icons      = ['Light Duty'=>'🚗','Medium Duty'=>'🚐','Heavy Duty'=>'🚛'];

require_once 'includes/header_pelanggan.php';
?>

<!-- Alert Pembayaran Pending -->
<?php while ($wp = $booking_belum_bayar->fetch_assoc()): ?>
<div class="alert alert-warning" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
  <span>💳 Booking <strong><?= clean($wp['kode_booking']) ?></strong> menunggu pembayaran sebesar <strong><?= rupiah($wp['total_bayar']) ?></strong>.</span>
  <a href="pelanggan_pembayaran.php?id=<?= $wp['id_booking'] ?>" class="btn btn-primary btn-sm">Bayar Sekarang →</a>
</div>
<?php endwhile; ?>

<!-- Notif unread banner -->
<?php if ($notif_unread_count > 0): ?>
<div class="alert alert-info" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
  <span>🔔 Ada <strong><?= $notif_unread_count ?></strong> notifikasi baru untuk Anda.</span>
  <a href="pelanggan_notifikasi.php" class="btn btn-primary btn-sm">Lihat Notifikasi</a>
</div>
<?php endif; ?>

<!-- Hero -->
<div class="hero-section">
  <h1>Halo, <?= clean(explode(' ',$_SESSION['nama_pelanggan'])[0]) ?>! 👋</h1>
  <p>Butuh bantuan towing kendaraan? Kami siap 24 jam untuk melayani Anda di seluruh wilayah.</p>
  <a href="pelanggan_booking.php" class="btn btn-yellow">🚛 Booking Towing Sekarang</a>
</div>

<!-- Stats -->
<div class="grid-3" style="margin-bottom:28px;">
  <div class="stat-card">
    <div class="stat-icon">📋</div>
    <div class="stat-label">Order Selesai</div>
    <div class="stat-value"><?= $stat['total'] ?></div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon">💰</div>
    <div class="stat-label">Total Pengeluaran</div>
    <div class="stat-value" style="font-size:18px;"><?= rupiah($stat['pengeluaran']) ?></div>
  </div>
  <div class="stat-card" style="cursor:pointer;" onclick="location='pelanggan_booking.php'">
    <div class="stat-icon">🚀</div>
    <div class="stat-label">Booking Baru</div>
    <div class="stat-value" style="font-size:16px;color:var(--blue-600);">Klik Di Sini</div>
  </div>
</div>

<!-- Armada -->
<div class="card">
  <div class="card-title">🚛 Armada Kami</div>
  <div class="mobil-grid">
    <?php while ($m = $mobil_list->fetch_assoc()):
      $icon = $icons[$m['jenis']] ?? '🚗';
      $tersedia = $m['status'] === 'Tersedia';
    ?>
    <div class="mobil-card <?= !$tersedia ? '' : '' ?>"
         style="<?= !$tersedia ? 'opacity:0.55;cursor:not-allowed;' : 'cursor:pointer;' ?>"
         onclick="<?= $tersedia ? "location='pelanggan_booking.php?id_mobil={$m['id_mobil']}'" : '' ?>">
      <span class="mobil-icon"><?= $icon ?></span>
      <div class="mobil-nama"><?= clean($m['nama_mobil']) ?></div>
      <div class="mobil-jenis"><?= badge_jenis($m['jenis']) ?></div>
      <div class="mobil-kap">⚖️ Maks. <?= number_format($m['kapasitas_kg']) ?> kg</div>
      <div style="font-size:11.5px;color:var(--text-light);margin:6px 0;line-height:1.5;">
        <?= mb_substr(clean($m['deskripsi']), 0, 80) ?>...
      </div>
      <div class="mobil-harga">
        <?= rupiah($m['harga_per_km']) ?><span>/km</span>
        <div style="font-size:11px;color:var(--text-light);font-weight:400;">Min. <?= rupiah($m['harga_min']) ?></div>
      </div>
      <div style="margin-top:10px;">
        <span class="mobil-status-dot <?= $tersedia ? 'dot-available' : 'dot-unavailable' ?>"></span>
        <span style="font-size:12px;color:<?= $tersedia ? 'var(--success)' : 'var(--danger)' ?>;font-weight:600;">
          <?= $tersedia ? 'Tersedia' : 'Tidak Tersedia' ?>
        </span>
      </div>
    </div>
    <?php endwhile; ?>
  </div>
</div>

<!-- Riwayat -->
<?php if ($riwayat->num_rows > 0): ?>
<div class="card">
  <div class="card-title" style="display:flex;justify-content:space-between;">
    📜 Booking Terbaru
    <a href="pelanggan_riwayat.php" class="btn btn-outline btn-sm">Lihat Semua</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Kode</th><th>Armada</th><th>Tanggal</th><th>Total</th><th>Status</th><th>Bayar</th></tr></thead>
      <tbody>
      <?php while ($bk = $riwayat->fetch_assoc()): ?>
        <tr>
          <td><strong style="color:var(--blue-700);"><?= clean($bk['kode_booking']) ?></strong></td>
          <td><?= clean($bk['nama_mobil']) ?></td>
          <td><?= date('d/m/Y', strtotime($bk['tanggal_booking'])) ?></td>
          <td><?= rupiah($bk['total_bayar']) ?></td>
          <td><?= badge_status($bk['status']) ?></td>
          <td>
            <?php if ($bk['status'] === 'Menunggu Pembayaran'): ?>
              <a href="pelanggan_pembayaran.php?id=<?= $bk['id_booking'] ?>" class="btn btn-primary btn-sm">💳 Bayar</a>
            <?php elseif ($bk['status_bayar']): ?>
              <?= badge_bayar($bk['status_bayar']) ?>
            <?php else: ?>—<?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer_pelanggan.php'; ?>
