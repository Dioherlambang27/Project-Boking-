<?php
/**
 * FILE: mobil.php
 * VTCC - Data Armada Mobil
 */
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_login();

$page_title   = 'Armada Mobil';
$page_heading = '🚛 Armada Mobil Towing';
$page_subtitle = '6 jenis armada siap beroperasi';
$active_menu  = 'mobil';

// Toggle status
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $mid = (int)$_GET['id'];
    $koneksi->query("UPDATE mobil_towing SET status = IF(status='Tersedia','Tidak Tersedia','Tersedia') WHERE id_mobil={$mid}");
    header('Location: mobil.php?pesan=Status+armada+diperbarui'); exit;
}

$pesan = clean($_GET['pesan'] ?? '');
$result = $koneksi->query("SELECT * FROM mobil_towing ORDER BY jenis, nama_mobil");

$icons = ['Light Duty'=>'🚗','Medium Duty'=>'🚐','Heavy Duty'=>'🚛'];

require_once 'includes/header.php';
?>
<button class="hamburger" onclick="document.querySelector('.sidebar').classList.toggle('open');document.querySelector('.sidebar-overlay').classList.toggle('show')">☰</button>
<div class="sidebar-overlay"></div>

<?php if ($pesan): ?><div class="alert alert-success alert-auto">✅ <?= $pesan ?></div><?php endif; ?>

<div class="mobil-grid">
  <?php while ($m = $result->fetch_assoc()):
    $icon = $icons[$m['jenis']] ?? '🚗';
    $tersedia = $m['status'] === 'Tersedia';
    // Hitung jumlah booking aktif
    $stmt2 = $koneksi->prepare("SELECT COUNT(*) c FROM booking WHERE id_mobil=? AND status IN ('Menunggu','Diproses')");
    $stmt2->bind_param('i', $m['id_mobil']); $stmt2->execute();
    $aktif = $stmt2->get_result()->fetch_assoc()['c'];
  ?>
  <div class="card" style="margin:0;position:relative;">
    <div style="font-size:40px;margin-bottom:12px;"><?= $icon ?></div>
    <div style="position:absolute;top:16px;right:16px;"><?= badge_jenis($m['jenis']) ?></div>
    <div style="font-weight:800;font-size:16px;margin-bottom:4px;"><?= clean($m['nama_mobil']) ?></div>
    <div style="color:var(--text-light);font-size:12px;margin-bottom:2px;">Kode: <strong><?= clean($m['kode_mobil']) ?></strong></div>
    <div style="font-size:12px;color:var(--text-light);margin-bottom:12px;"><?= clean($m['deskripsi']) ?></div>
    <table style="width:100%;font-size:12.5px;margin-bottom:12px;">
      <tr>
        <td style="color:var(--text-light);padding:3px 0;">Kapasitas Maks</td>
        <td style="font-weight:600;text-align:right;"><?= number_format($m['kapasitas_kg']) ?> kg</td>
      </tr>
      <tr>
        <td style="color:var(--text-light);padding:3px 0;">Tarif / km</td>
        <td style="font-weight:700;text-align:right;color:var(--blue-700);"><?= rupiah($m['harga_per_km']) ?></td>
      </tr>
      <tr>
        <td style="color:var(--text-light);padding:3px 0;">Min. Tarif</td>
        <td style="font-weight:600;text-align:right;"><?= rupiah($m['harga_min']) ?></td>
      </tr>
      <tr>
        <td style="color:var(--text-light);padding:3px 0;">Booking Aktif</td>
        <td style="font-weight:600;text-align:right;"><?= $aktif ?> order</td>
      </tr>
    </table>
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <?php if ($tersedia): ?>
        <span class="badge badge-success">✓ Tersedia</span>
      <?php else: ?>
        <span class="badge badge-danger">✕ Tidak Tersedia</span>
      <?php endif; ?>
      <a href="mobil.php?toggle=1&id=<?= $m['id_mobil'] ?>" class="btn btn-sm btn-outline"
         onclick="return confirm('Ubah status armada ini?')">
        <?= $tersedia ? 'Nonaktifkan' : 'Aktifkan' ?>
      </a>
    </div>
  </div>
  <?php endwhile; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
