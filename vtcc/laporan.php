<?php
/**
 * FILE: laporan.php - v2 with PDF export
 */
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_login();

$page_title   = 'Laporan';
$page_heading = '📈 Laporan';
$page_subtitle = 'Ringkasan performa dan pendapatan VTCC';
$active_menu  = 'laporan';

$bulan = clean($_GET['bulan'] ?? date('Y-m'));
$arr   = explode('-', $bulan);
$y     = $arr[0] ?? date('Y');
$m     = $arr[1] ?? date('m');

$stmt = $koneksi->prepare(
    "SELECT COUNT(*) total, COALESCE(SUM(total_bayar),0) pendapatan,
     SUM(CASE WHEN status='Selesai' THEN 1 ELSE 0 END) selesai,
     SUM(CASE WHEN status='Dibatalkan' THEN 1 ELSE 0 END) batal,
     SUM(CASE WHEN status='Diproses' THEN 1 ELSE 0 END) diproses
     FROM booking WHERE YEAR(tanggal_booking)=? AND MONTH(tanggal_booking)=?"
);
$stmt->bind_param('ii', $y, $m); $stmt->execute();
$stat = $stmt->get_result()->fetch_assoc();

// Per armada
$stmt2 = $koneksi->prepare(
    "SELECT m.nama_mobil, m.jenis, COUNT(*) total, SUM(b.total_bayar) pendapatan
     FROM booking b JOIN mobil_towing m ON m.id_mobil=b.id_mobil
     WHERE YEAR(b.tanggal_booking)=? AND MONTH(b.tanggal_booking)=? AND b.status='Selesai'
     GROUP BY b.id_mobil ORDER BY pendapatan DESC"
);
$stmt2->bind_param('ii', $y, $m); $stmt2->execute();
$per_mobil = $stmt2->get_result();

// Per metode pembayaran
$stmt3 = $koneksi->prepare(
    "SELECT pm.metode, COUNT(*) total, SUM(pm.jumlah_bayar) pendapatan
     FROM pembayaran pm
     JOIN booking b ON b.id_booking = pm.id_booking
     WHERE YEAR(b.tanggal_booking)=? AND MONTH(b.tanggal_booking)=? AND pm.status_bayar='Lunas'
     GROUP BY pm.metode"
);
$stmt3->bind_param('ii', $y, $m); $stmt3->execute();
$per_metode = $stmt3->get_result();

// Harian
$stmt4 = $koneksi->prepare(
    "SELECT DAY(tanggal_booking) hari, COUNT(*) total, SUM(total_bayar) pendapatan
     FROM booking WHERE YEAR(tanggal_booking)=? AND MONTH(tanggal_booking)=? AND status='Selesai'
     GROUP BY hari ORDER BY hari"
);
$stmt4->bind_param('ii', $y, $m); $stmt4->execute();
$harian = $stmt4->get_result();

require_once 'includes/header.php';
?>
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="card">
  <div class="action-bar">
    <form method="get" style="display:flex;gap:10px;align-items:center;">
      <label style="font-weight:700;margin:0;">📅 Periode:</label>
      <input type="month" name="bulan" value="<?= $bulan ?>"
             style="padding:8px 14px;border:1.5px solid var(--gray);border-radius:8px;font-family:var(--font);">
      <button type="submit" class="btn btn-primary btn-sm">Tampilkan</button>
    </form>
    <a href="laporan_pdf.php?bulan=<?= $bulan ?>" target="_blank"
       class="btn btn-danger" style="gap:8px;">
      📄 Cetak PDF
    </a>
  </div>
</div>

<div class="grid-stats">
  <div class="stat-card">
    <div class="stat-icon">📋</div>
    <div class="stat-label">Total Booking</div>
    <div class="stat-value"><?= $stat['total'] ?></div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon">✅</div>
    <div class="stat-label">Selesai</div>
    <div class="stat-value"><?= $stat['selesai'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🚛</div>
    <div class="stat-label">Sedang Diproses</div>
    <div class="stat-value"><?= $stat['diproses'] ?></div>
  </div>
  <div class="stat-card success">
    <div class="stat-icon">💰</div>
    <div class="stat-label">Total Pendapatan</div>
    <div class="stat-value" style="font-size:17px;"><?= rupiah($stat['pendapatan'] ?? 0) ?></div>
  </div>
</div>

<div class="grid-3" style="align-items:start;">
  <!-- Per Armada -->
  <div class="card">
    <div class="card-title">🚛 Per Armada (Selesai)</div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Armada</th><th>Order</th><th>Pendapatan</th></tr></thead>
        <tbody>
        <?php if ($per_mobil->num_rows===0): ?>
          <tr><td colspan="3" class="empty-state">Tidak ada data.</td></tr>
        <?php else: while ($r = $per_mobil->fetch_assoc()): ?>
          <tr>
            <td><strong><?= clean($r['nama_mobil']) ?></strong><br><?= badge_jenis($r['jenis']) ?></td>
            <td><?= $r['total'] ?></td>
            <td><strong><?= rupiah($r['pendapatan']) ?></strong></td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Per Metode Pembayaran -->
  <div class="card">
    <div class="card-title">💳 Per Metode Bayar</div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Metode</th><th>Transaksi</th><th>Nominal</th></tr></thead>
        <tbody>
        <?php if ($per_metode->num_rows===0): ?>
          <tr><td colspan="3" class="empty-state">Tidak ada data.</td></tr>
        <?php else: while ($r = $per_metode->fetch_assoc()): ?>
          <tr>
            <td><strong><?= clean($r['metode']) ?></strong></td>
            <td><?= $r['total'] ?></td>
            <td><strong><?= rupiah($r['pendapatan']) ?></strong></td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Harian -->
  <div class="card">
    <div class="card-title">📅 Rincian Harian (Selesai)</div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Tgl</th><th>Order</th><th>Pendapatan</th></tr></thead>
        <tbody>
        <?php if ($harian->num_rows===0): ?>
          <tr><td colspan="3" class="empty-state">Tidak ada data.</td></tr>
        <?php else: while ($r = $harian->fetch_assoc()): ?>
          <tr>
            <td><?= str_pad($r['hari'],2,'0',STR_PAD_LEFT) ?>/<?= $m ?>/<?= $y ?></td>
            <td><?= $r['total'] ?></td>
            <td><?= rupiah($r['pendapatan']) ?></td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
