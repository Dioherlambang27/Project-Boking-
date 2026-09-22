<?php
/**
 * FILE: booking_edit.php - FIXED v2
 * VTCC - Edit Booking
 */
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: booking.php'); exit; }

$stmt = $koneksi->prepare("SELECT * FROM booking WHERE id_booking = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$b = $stmt->get_result()->fetch_assoc();
if (!$b) { header('Location: booking.php'); exit; }

$page_title   = 'Edit Booking';
$page_heading = '✏️ Edit Booking';
$page_subtitle = $b['kode_booking'];
$active_menu  = 'booking';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_mobil      = (int)($_POST['id_mobil'] ?? 0);
    $id_pelanggan  = (int)($_POST['id_pelanggan'] ?? 0);
    $tanggal       = clean($_POST['tanggal_booking'] ?? '');
    $jam           = clean($_POST['jam_booking'] ?? '');
    $lok_jemput    = clean($_POST['lokasi_jemput'] ?? '');
    $lok_tujuan    = clean($_POST['lokasi_tujuan'] ?? '');
    $estimasi_km   = (float)($_POST['estimasi_km'] ?? 0);
    $status        = clean($_POST['status'] ?? '');
    $catatan       = clean($_POST['catatan'] ?? '');

    if (!$id_mobil || !$id_pelanggan || !$tanggal || !$jam || !$lok_jemput || !$lok_tujuan || $estimasi_km <= 0) {
        $error = 'Semua field wajib diisi dengan benar.';
    } else {
        $stmt2 = $koneksi->prepare('SELECT harga_per_km, harga_min FROM mobil_towing WHERE id_mobil = ?');
        $stmt2->bind_param('i', $id_mobil);
        $stmt2->execute();
        $mob = $stmt2->get_result()->fetch_assoc();

        if (!$mob) {
            $error = 'Armada tidak ditemukan.';
        } else {
            $total = hitung_total_bayar($estimasi_km, $mob['harga_per_km'], $mob['harga_min']);

            $upd = $koneksi->prepare(
                "UPDATE booking SET
                    id_mobil = ?,
                    id_pelanggan = ?,
                    tanggal_booking = ?,
                    jam_booking = ?,
                    lokasi_jemput = ?,
                    lokasi_tujuan = ?,
                    estimasi_km = ?,
                    total_bayar = ?,
                    status = ?,
                    catatan = ?
                 WHERE id_booking = ?"
            );
            $upd->bind_param(
                'iissssddsi' . 'i',
                $id_mobil, $id_pelanggan,
                $tanggal, $jam,
                $lok_jemput, $lok_tujuan,
                $estimasi_km, $total,
                $status, $catatan,
                $id
            );

            if ($upd->execute()) {
                header('Location: booking_detail.php?id=' . $id . '&pesan=' . urlencode('Booking berhasil diperbarui!'));
                exit;
            } else {
                $error = 'Gagal update: ' . $koneksi->error;
            }
        }
    }

    // Refresh $b from POST for repopulate
    $b['id_mobil']        = $_POST['id_mobil'] ?? $b['id_mobil'];
    $b['id_pelanggan']    = $_POST['id_pelanggan'] ?? $b['id_pelanggan'];
    $b['tanggal_booking'] = $_POST['tanggal_booking'] ?? $b['tanggal_booking'];
    $b['jam_booking']     = $_POST['jam_booking'] ?? $b['jam_booking'];
    $b['lokasi_jemput']   = $_POST['lokasi_jemput'] ?? $b['lokasi_jemput'];
    $b['lokasi_tujuan']   = $_POST['lokasi_tujuan'] ?? $b['lokasi_tujuan'];
    $b['estimasi_km']     = $_POST['estimasi_km'] ?? $b['estimasi_km'];
    $b['status']          = $_POST['status'] ?? $b['status'];
    $b['catatan']         = $_POST['catatan'] ?? $b['catatan'];
}

$mobil_list = $koneksi->query("SELECT * FROM mobil_towing ORDER BY jenis, nama_mobil");
$pel_list   = $koneksi->query("SELECT id_pelanggan, nama_pelanggan, no_hp FROM pelanggan ORDER BY nama_pelanggan");

require_once 'includes/header.php';
?>
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<?php if ($error): ?>
  <div class="alert alert-danger">❌ <?= clean($error) ?></div>
<?php endif; ?>

<div class="card" style="max-width:700px;">
  <div class="card-title">✏️ Edit Booking: <?= clean($b['kode_booking']) ?></div>
  <form method="post" action="booking_edit.php?id=<?= $id ?>">

    <div class="form-row">
      <div class="form-group">
        <label>🚛 Armada *</label>
        <select name="id_mobil" id="id_mobil" required>
          <option value="">-- Pilih Armada --</option>
          <?php while ($m = $mobil_list->fetch_assoc()): ?>
          <option value="<?= $m['id_mobil'] ?>"
                  data-harga-km="<?= $m['harga_per_km'] ?>"
                  data-harga-min="<?= $m['harga_min'] ?>"
                  <?= $b['id_mobil'] == $m['id_mobil'] ? 'selected' : '' ?>>
            <?= clean($m['nama_mobil']) ?>
          </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="form-group">
        <label>👥 Pelanggan *</label>
        <select name="id_pelanggan" required>
          <option value="">-- Pilih Pelanggan --</option>
          <?php while ($p = $pel_list->fetch_assoc()): ?>
          <option value="<?= $p['id_pelanggan'] ?>"
                  <?= $b['id_pelanggan'] == $p['id_pelanggan'] ? 'selected' : '' ?>>
            <?= clean($p['nama_pelanggan']) ?> (<?= clean($p['no_hp']) ?>)
          </option>
          <?php endwhile; ?>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>📅 Tanggal *</label>
        <input type="date" name="tanggal_booking" required
               value="<?= clean($b['tanggal_booking']) ?>">
      </div>
      <div class="form-group">
        <label>⏰ Jam *</label>
        <input type="time" name="jam_booking" required
               value="<?= substr(clean($b['jam_booking']), 0, 5) ?>">
      </div>
    </div>

    <div class="form-group">
      <label>📍 Lokasi Jemput *</label>
      <input type="text" name="lokasi_jemput" required
             value="<?= clean($b['lokasi_jemput']) ?>">
    </div>

    <div class="form-group">
      <label>🏁 Lokasi Tujuan *</label>
      <input type="text" name="lokasi_tujuan" required
             value="<?= clean($b['lokasi_tujuan']) ?>">
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>📏 Est. Jarak (km) *</label>
        <input type="number" name="estimasi_km" id="estimasi_km"
               min="1" step="0.5" required
               value="<?= clean($b['estimasi_km']) ?>">
      </div>
      <div class="form-group">
        <label>💰 Estimasi Biaya</label>
        <div id="info-total"
             style="padding:10px 14px;background:var(--yellow-50);border:1.5px solid var(--yellow-100);
                    border-radius:8px;font-weight:700;color:var(--blue-900);font-size:15px;">
          <?= rupiah($b['total_bayar']) ?>
        </div>
      </div>
    </div>

    <div class="form-group">
      <label>🚦 Status</label>
      <select name="status">
        <?php foreach (['Menunggu Pembayaran','Menunggu','Diproses','Selesai','Dibatalkan'] as $s): ?>
          <option <?= $b['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label>📝 Catatan</label>
      <textarea name="catatan" rows="2"><?= clean($b['catatan'] ?? '') ?></textarea>
    </div>

    <div class="gap-2">
      <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
      <a href="booking_detail.php?id=<?= $id ?>" class="btn btn-outline">Batal</a>
    </div>
  </form>
</div>

<?php require_once 'includes/footer.php'; ?>
