<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_login();

$page_title   = 'Data Booking';
$page_heading = '📋 Data Booking';
$page_subtitle = 'Kelola semua pesanan booking mobil towing';
$active_menu  = 'booking';

$filter_status  = clean($_GET['status'] ?? '');
$filter_tanggal = clean($_GET['tanggal'] ?? '');
$search         = clean($_GET['q'] ?? '');

$where = ['1=1']; $params = []; $types = '';
if ($filter_status)  { $where[] = 'b.status = ?';           $params[] = $filter_status;  $types .= 's'; }
if ($filter_tanggal) { $where[] = 'b.tanggal_booking = ?';  $params[] = $filter_tanggal; $types .= 's'; }
if ($search) {
    $where[] = "(b.kode_booking LIKE ? OR p.nama_pelanggan LIKE ? OR m.nama_mobil LIKE ?)";
    $s = "%$search%"; $params[] = $s; $params[] = $s; $params[] = $s; $types .= 'sss';
}

$sql = "SELECT b.*, m.nama_mobil, m.jenis, p.nama_pelanggan, p.no_hp,
               pm.metode, pm.status_bayar
        FROM booking b
        JOIN mobil_towing m ON m.id_mobil=b.id_mobil
        JOIN pelanggan p ON p.id_pelanggan=b.id_pelanggan
        LEFT JOIN pembayaran pm ON pm.id_booking=b.id_booking
        WHERE " . implode(' AND ', $where) . "
        ORDER BY b.created_at DESC";
$stmt = $koneksi->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$pesan = clean($_GET['pesan'] ?? '');
require_once 'includes/header.php';
?>
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>
<?php if ($pesan): ?><div class="alert alert-success alert-auto">✅ <?= $pesan ?></div><?php endif; ?>

<div class="card">
  <div class="action-bar">
    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
      <input type="text" class="search-input" name="q" placeholder="Cari kode, pelanggan, armada..." value="<?= $search ?>">
      <select name="status" style="padding:9px 14px;border:1.5px solid var(--gray);border-radius:8px;font-family:var(--font);font-size:13px;">
        <option value="">Semua Status</option>
        <?php foreach(['Menunggu Pembayaran','Menunggu','Diproses','Selesai','Dibatalkan'] as $s): ?>
          <option <?= $filter_status===$s?'selected':'' ?> value="<?= $s ?>"><?= $s ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="tanggal" value="<?= $filter_tanggal ?>"
             style="padding:9px 14px;border:1.5px solid var(--gray);border-radius:8px;font-family:var(--font);font-size:13px;">
      <button type="submit" class="btn btn-outline btn-sm">Filter</button>
      <?php if ($search||$filter_status||$filter_tanggal): ?>
        <a href="booking.php" class="btn btn-sm" style="background:var(--light-gray);color:var(--text-mid);">Reset</a>
      <?php endif; ?>
    </form>
    <a href="booking_tambah.php" class="btn btn-primary">➕ Tambah Booking</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Kode</th><th>Armada</th><th>Pelanggan</th><th>Tgl & Jam</th>
        <th>Rute</th><th>Total</th><th>Status</th><th>Pembayaran</th><th>Aksi</th>
      </tr></thead>
      <tbody>
      <?php if ($result->num_rows === 0): ?>
        <tr><td colspan="9" class="empty-state">📭 Tidak ada data booking.</td></tr>
      <?php else: while ($bk = $result->fetch_assoc()): ?>
        <tr>
          <td><strong style="color:var(--blue-700);"><?= clean($bk['kode_booking']) ?></strong></td>
          <td><?= clean($bk['nama_mobil']) ?><br><small class="text-muted"><?= clean($bk['jenis']) ?></small></td>
          <td><?= clean($bk['nama_pelanggan']) ?><br><small class="text-muted"><?= clean($bk['no_hp']) ?></small></td>
          <td><?= date('d/m/Y', strtotime($bk['tanggal_booking'])) ?><br>
              <small class="text-muted"><?= substr($bk['jam_booking'],0,5) ?> WIB</small></td>
          <td style="max-width:160px;font-size:12px;line-height:1.5;">
            📍<?= clean($bk['lokasi_jemput']) ?><br>🏁<?= clean($bk['lokasi_tujuan']) ?>
          </td>
          <td><strong><?= rupiah($bk['total_bayar']) ?></strong></td>
          <td><?= badge_status($bk['status']) ?></td>
          <td>
            <?php if ($bk['metode']): ?>
              <span style="font-size:12px;"><?= clean($bk['metode']) ?></span><br>
              <?= badge_bayar($bk['status_bayar']) ?>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
          <td>
            <div class="gap-2">
              <a href="booking_detail.php?id=<?= $bk['id_booking'] ?>" class="btn btn-outline btn-sm">Detail</a>
              <a href="booking_edit.php?id=<?= $bk['id_booking'] ?>" class="btn btn-sm"
                 style="background:var(--yellow-100);color:var(--warning);">Edit</a>
            </div>
          </td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>
