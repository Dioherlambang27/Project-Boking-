<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_login();

$page_title    = 'Dashboard';
$page_heading  = '📊 Dashboard';
$page_subtitle = 'Ringkasan operasional VTCC hari ini';
$active_menu   = 'dashboard';
$hari_ini      = date('Y-m-d');

$total_mobil      = $koneksi->query("SELECT COUNT(*) c FROM mobil_towing WHERE status='Tersedia'")->fetch_assoc()['c'];
$total_pelanggan  = $koneksi->query("SELECT COUNT(*) c FROM pelanggan")->fetch_assoc()['c'];

$stmt = $koneksi->prepare("SELECT COUNT(*) c FROM booking WHERE tanggal_booking=? AND status NOT IN ('Dibatalkan')");
$stmt->bind_param('s', $hari_ini); $stmt->execute();
$booking_hari_ini = $stmt->get_result()->fetch_assoc()['c'];

$stmt = $koneksi->prepare("SELECT COALESCE(SUM(total_bayar),0) t FROM booking WHERE tanggal_booking=? AND status='Selesai'");
$stmt->bind_param('s', $hari_ini); $stmt->execute();
$pendapatan_hari_ini = $stmt->get_result()->fetch_assoc()['t'];

$menunggu_bayar = $koneksi->query("SELECT COUNT(*) c FROM pembayaran WHERE status_bayar='Menunggu Verifikasi'")->fetch_assoc()['c'];
$notif_count    = hitung_notif_belum_baca($koneksi);

// Booking hari ini
$stmt2 = $koneksi->prepare(
    "SELECT b.*, m.nama_mobil, m.jenis, p.nama_pelanggan, p.no_hp,
            pm.metode, pm.status_bayar
     FROM booking b
     JOIN mobil_towing m ON m.id_mobil=b.id_mobil
     JOIN pelanggan p ON p.id_pelanggan=b.id_pelanggan
     LEFT JOIN pembayaran pm ON pm.id_booking=b.id_booking
     WHERE b.tanggal_booking=?
     ORDER BY b.jam_booking ASC LIMIT 10"
);
$stmt2->bind_param('s', $hari_ini); $stmt2->execute();
$booking_list = $stmt2->get_result();

// Armada
$mobil_list = $koneksi->query("SELECT * FROM mobil_towing ORDER BY jenis, nama_mobil");

// Notifikasi terbaru
$notif_recent = $koneksi->query("SELECT * FROM notifikasi ORDER BY created_at DESC LIMIT 5");

require_once 'includes/header.php';
?>
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- Stats -->
<div class="grid-stats">
  <div class="stat-card">
    <div class="stat-icon">🚛</div>
    <div class="stat-label">Armada Tersedia</div>
    <div class="stat-value"><?= $total_mobil ?></div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon">📋</div>
    <div class="stat-label">Booking Hari Ini</div>
    <div class="stat-value"><?= $booking_hari_ini ?></div>
  </div>
  <div class="stat-card success">
    <div class="stat-icon">💰</div>
    <div class="stat-label">Pendapatan Hari Ini</div>
    <div class="stat-value" style="font-size:18px;"><?= rupiah($pendapatan_hari_ini) ?></div>
  </div>
  <div class="stat-card danger">
    <div class="stat-icon">💳</div>
    <div class="stat-label">Menunggu Verifikasi Bayar</div>
    <div class="stat-value"><?= $menunggu_bayar ?></div>
    <?php if ($menunggu_bayar > 0): ?>
      <a href="pembayaran_admin.php?filter=menunggu" class="btn btn-danger btn-sm" style="margin-top:8px;">Verifikasi →</a>
    <?php endif; ?>
  </div>
</div>

<!-- Notif banner -->
<?php if ($notif_count > 0): ?>
<div class="alert alert-info" style="margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;">
  <span>🔔 Ada <strong><?= $notif_count ?></strong> notifikasi baru yang belum dibaca.</span>
  <a href="notifikasi.php" class="btn btn-primary btn-sm">Lihat Notifikasi</a>
</div>
<?php endif; ?>

<!-- Armada Status -->
<div class="card">
  <div class="card-title">🚛 Status Armada Hari Ini</div>
  <div class="grid-3">
    <?php $mobil_list->data_seek(0); while ($m = $mobil_list->fetch_assoc()):
      $stmt3 = $koneksi->prepare("SELECT COUNT(*) c FROM booking WHERE id_mobil=? AND tanggal_booking=? AND status IN ('Menunggu','Diproses')");
      $stmt3->bind_param('is', $m['id_mobil'], $hari_ini); $stmt3->execute();
      $sedang_dipakai = $stmt3->get_result()->fetch_assoc()['c'] > 0;
      $icons = ['Light Duty'=>'🚗','Medium Duty'=>'🚐','Heavy Duty'=>'🚛'];
      $icon  = $icons[$m['jenis']] ?? '🚗';
    ?>
    <div class="card" style="margin:0;padding:16px;">
      <div style="font-size:28px;margin-bottom:8px;"><?= $icon ?></div>
      <div style="font-weight:700;font-size:13.5px;margin-bottom:4px;"><?= clean($m['nama_mobil']) ?></div>
      <div style="margin-bottom:8px;"><?= badge_jenis($m['jenis']) ?></div>
      <div style="font-size:12px;color:var(--text-light);">Maks. <?= number_format($m['kapasitas_kg']) ?> kg</div>
      <?php if ($m['status'] === 'Tidak Tersedia'): ?>
        <div style="margin-top:8px;"><span class="badge badge-danger">Tidak Tersedia</span></div>
      <?php elseif ($sedang_dipakai): ?>
        <div style="margin-top:8px;"><span class="badge badge-warning">Sedang Digunakan</span></div>
      <?php else: ?>
        <div style="margin-top:8px;"><span class="badge badge-success">✓ Tersedia</span></div>
      <?php endif; ?>
    </div>
    <?php endwhile; ?>
  </div>
</div>

<div class="grid-2" style="align-items:start;">
  <!-- Booking Hari Ini -->
  <div class="card">
    <div class="card-title" style="display:flex;justify-content:space-between;">
      📋 Jadwal Hari Ini
      <a href="booking.php" class="btn btn-outline btn-sm">Lihat Semua</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>Kode</th><th>Armada</th><th>Pelanggan</th>
          <th>Jam</th><th>Total</th><th>Status</th>
        </tr></thead>
        <tbody>
        <?php if ($booking_list->num_rows === 0): ?>
          <tr><td colspan="6" class="empty-state">🎉 Belum ada booking hari ini.</td></tr>
        <?php else: while ($bk = $booking_list->fetch_assoc()): ?>
          <tr>
            <td><strong style="color:var(--blue-700);font-size:12px;"><?= clean($bk['kode_booking']) ?></strong></td>
            <td style="font-size:12px;"><?= clean($bk['nama_mobil']) ?></td>
            <td style="font-size:12px;"><?= clean($bk['nama_pelanggan']) ?></td>
            <td style="font-size:12px;"><?= substr($bk['jam_booking'],0,5) ?></td>
            <td style="font-size:12px;font-weight:600;"><?= rupiah($bk['total_bayar']) ?></td>
            <td><?= badge_status($bk['status']) ?></td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Notif Terbaru -->
  <div class="card">
    <div class="card-title" style="display:flex;justify-content:space-between;">
      🔔 Notifikasi Terbaru
      <a href="notifikasi.php?mark_all=1" class="btn btn-outline btn-sm">✓ Baca Semua</a>
    </div>
    <?php if ($notif_recent->num_rows === 0): ?>
      <div class="empty-state" style="padding:32px;">🔔 Tidak ada notifikasi.</div>
    <?php else: while ($n = $notif_recent->fetch_assoc()): ?>
      <a href="<?= $n['id_booking'] ? 'booking_detail.php?id='.$n['id_booking'] : 'notifikasi.php' ?>"
         style="display:flex;gap:10px;padding:12px 0;border-bottom:1px solid var(--gray);
                text-decoration:none;color:inherit;
                background:<?= $n['is_read'] ? 'transparent' : 'none' ?>;">
        <span style="font-size:20px;flex-shrink:0;"><?= $n['tipe']==='booking_baru'?'📋':($n['tipe']==='pembayaran'?'💳':'ℹ️') ?></span>
        <div style="flex:1;min-width:0;">
          <div style="font-weight:<?= $n['is_read']?'500':'700' ?>;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            <?= clean($n['judul']) ?>
            <?php if (!$n['is_read']): ?><span class="badge badge-blue" style="font-size:9px;padding:1px 5px;">Baru</span><?php endif; ?>
          </div>
          <div style="font-size:11px;color:var(--text-light);"><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></div>
        </div>
      </a>
    <?php endwhile; endif; ?>
    <div style="text-align:center;margin-top:12px;">
      <a href="notifikasi.php" class="btn btn-outline btn-sm">Lihat Semua Notifikasi</a>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
