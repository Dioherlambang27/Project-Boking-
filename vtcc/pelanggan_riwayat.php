<?php
require_once 'config/db.php';
require_once 'includes/auth_pelanggan.php';
require_once 'includes/functions.php';
require_login_pelanggan();

$page_title    = 'Riwayat Booking';
$active_menu_p = 'riwayat';
$id_pel        = (int)$_SESSION['id_pelanggan'];

$filter_status = clean($_GET['status'] ?? '');
$where  = 'b.id_pelanggan = ?';
$params = [$id_pel]; $types = 'i';
if ($filter_status) { $where .= ' AND b.status = ?'; $params[] = $filter_status; $types .= 's'; }

$stmt = $koneksi->prepare(
    "SELECT b.*, m.nama_mobil, m.jenis,
            pm.metode, pm.status_bayar, pm.id_pembayaran
     FROM booking b
     JOIN mobil_towing m ON m.id_mobil = b.id_mobil
     LEFT JOIN pembayaran pm ON pm.id_booking = b.id_booking
     WHERE {$where}
     ORDER BY b.created_at DESC"
);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$pesan = clean($_GET['pesan'] ?? '');
require_once 'includes/header_pelanggan.php';
?>

<?php if ($pesan): ?>
<div class="alert alert-success alert-auto" style="margin-bottom:20px;">✅ <?= $pesan ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-title" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
    📜 Riwayat Booking Saya
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <?php
      $filters = [
        '' => 'Semua',
        'Menunggu Pembayaran' => '💳 Menunggu Bayar',
        'Menunggu' => '⏳ Menunggu',
        'Diproses' => '🚛 Diproses',
        'Selesai'  => '✅ Selesai',
        'Dibatalkan' => '❌ Dibatalkan',
      ];
      foreach ($filters as $val => $label): ?>
        <a href="pelanggan_riwayat.php<?= $val ? '?status='.urlencode($val) : '' ?>"
           class="btn btn-sm <?= $filter_status===$val ? 'btn-primary' : 'btn-outline' ?>">
          <?= $label ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($result->num_rows === 0): ?>
    <div class="empty-state" style="padding:60px 20px;">
      <div style="font-size:48px;margin-bottom:12px;">📭</div>
      <div style="font-size:16px;font-weight:600;margin-bottom:8px;">Belum ada riwayat booking</div>
      <a href="pelanggan_booking.php" class="btn btn-primary">🚛 Booking Sekarang</a>
    </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Kode</th><th>Armada</th><th>Tanggal</th><th>Rute</th>
        <th>Total</th><th>Status</th><th>Pembayaran</th><th>Aksi</th>
      </tr></thead>
      <tbody>
      <?php while ($b = $result->fetch_assoc()): ?>
        <tr>
          <td>
            <strong style="color:var(--blue-700);"><?= clean($b['kode_booking']) ?></strong><br>
            <small class="text-muted"><?= date('d/m/Y', strtotime($b['created_at'])) ?></small>
          </td>
          <td><?= clean($b['nama_mobil']) ?><br><small class="text-muted"><?= clean($b['jenis']) ?></small></td>
          <td><?= date('d M Y', strtotime($b['tanggal_booking'])) ?><br>
              <small class="text-muted">⏰ <?= substr($b['jam_booking'],0,5) ?></small></td>
          <td style="max-width:180px;font-size:12px;line-height:1.6;">
            📍 <?= clean($b['lokasi_jemput']) ?><br>
            🏁 <?= clean($b['lokasi_tujuan']) ?>
          </td>
          <td><strong style="color:var(--blue-700);"><?= rupiah($b['total_bayar']) ?></strong></td>
          <td><?= badge_status($b['status']) ?></td>
          <td>
            <?php if ($b['metode']): ?>
              <?= clean($b['metode']) ?><br>
              <?= badge_bayar($b['status_bayar']) ?>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="gap-2">
              <?php if ($b['status'] === 'Menunggu Pembayaran' && !$b['id_pembayaran']): ?>
                <a href="pelanggan_pembayaran.php?id=<?= $b['id_booking'] ?>"
                   class="btn btn-primary btn-sm">💳 Bayar</a>
              <?php endif; ?>
              <?php if ($b['status'] === 'Menunggu Pembayaran' && $b['id_pembayaran']): ?>
                <span class="badge badge-warning">⏳ Verifikasi</span>
              <?php endif; ?>
              <?php if ($b['status'] === 'Menunggu'): ?>
                <a href="pelanggan_cancel.php?id=<?= $b['id_booking'] ?>"
                   onclick="return confirm('Batalkan booking ini?')"
                   class="btn btn-danger btn-sm">✕ Batal</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer_pelanggan.php'; ?>
