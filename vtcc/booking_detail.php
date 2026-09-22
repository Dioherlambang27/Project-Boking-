<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: booking.php'); exit; }

$stmt = $koneksi->prepare(
    "SELECT b.*, m.nama_mobil, m.jenis, m.kapasitas_kg, m.harga_per_km, m.harga_min,
            p.nama_pelanggan, p.no_hp, p.email, p.alamat,
            u.nama_lengkap AS nama_admin
     FROM booking b
     JOIN mobil_towing m ON m.id_mobil = b.id_mobil
     JOIN pelanggan p ON p.id_pelanggan = b.id_pelanggan
     LEFT JOIN users u ON u.id_user = b.created_by
     WHERE b.id_booking = ?"
);
$stmt->bind_param('i', $id); $stmt->execute();
$b = $stmt->get_result()->fetch_assoc();
if (!$b) { header('Location: booking.php'); exit; }

// Ambil data pembayaran
$stmtP = $koneksi->prepare("SELECT * FROM pembayaran WHERE id_booking = ?");
$stmtP->bind_param('i', $id); $stmtP->execute();
$pem = $stmtP->get_result()->fetch_assoc();

$pesan = '';
// Update status booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $new_status = clean($_POST['status']);
    $allowed    = ['Menunggu Pembayaran','Menunggu','Diproses','Selesai','Dibatalkan'];
    if (in_array($new_status, $allowed)) {
        $upd = $koneksi->prepare('UPDATE booking SET status=? WHERE id_booking=?');
        $upd->bind_param('si', $new_status, $id); $upd->execute();

        // Notif admin (log)
        $judul_n = ($new_status === 'Selesai' ? "✅" : ($new_status === 'Dibatalkan' ? "❌" : "🔄")) .
                   " Booking {$new_status}: " . $b['kode_booking'];
        $isi_n   = "Status booking {$b['kode_booking']} milik {$b['nama_pelanggan']} diubah menjadi {$new_status}.";
        tambah_notifikasi($koneksi, $judul_n, $isi_n, 'status_update', $id);

        // Notif PELANGGAN — status berubah
        $notif_pel_map = [
            'Diproses'   => ['🚛 Booking Sedang Diproses!',
                             "Tim kami sedang dalam perjalanan menuju lokasi Anda untuk booking {$b['kode_booking']}. Mohon siapkan kendaraan Anda."],
            'Selesai'    => ['✅ Layanan Towing Selesai!',
                             "Booking {$b['kode_booking']} telah selesai dilayani. Terima kasih telah menggunakan layanan VTCC. Kami siap melayani Anda kembali!"],
            'Dibatalkan' => ['❌ Booking Dibatalkan',
                             "Booking {$b['kode_booking']} telah dibatalkan oleh admin. Hubungi kami di (021) 1234-5678 jika ada pertanyaan."],
        ];
        if (isset($notif_pel_map[$new_status])) {
            [$judul_p, $isi_p] = $notif_pel_map[$new_status];
            tambah_notifikasi_pelanggan($koneksi, $b['id_pelanggan'], $judul_p, $isi_p, 'status_update', $id);
        }
        header('Location: booking_detail.php?id=' . $id . '&pesan=' . urlencode("Status diperbarui ke: {$new_status}"));
        exit;
    }
}
$pesan = clean($_GET['pesan'] ?? '');

$page_title   = 'Detail Booking';
$page_heading = '📋 Detail Booking';
$page_subtitle = $b['kode_booking'];
$active_menu  = 'booking';

$step_map = ['Menunggu Pembayaran' => -1, 'Menunggu' => 0, 'Diproses' => 1, 'Selesai' => 2, 'Dibatalkan' => -2];
$current_step = $step_map[$b['status']] ?? 0;

require_once 'includes/header.php';
?>
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>
<?php if ($pesan): ?><div class="alert alert-success alert-auto">✅ <?= $pesan ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start;">
  <div>
    <!-- Status Timeline -->
    <?php if ($b['status'] === 'Dibatalkan'): ?>
      <div class="alert alert-danger">❌ Booking ini telah <strong>Dibatalkan</strong>.</div>
    <?php elseif ($b['status'] === 'Menunggu Pembayaran'): ?>
      <div class="alert alert-warning">
        💳 Booking ini <strong>Menunggu Pembayaran</strong> dari pelanggan.
        <?php if (!$pem): ?>
          Pelanggan belum mengirim pembayaran.
        <?php else: ?>
          Pembayaran sudah dikirim via <strong><?= clean($pem['metode']) ?></strong> — Status: <?= badge_bayar($pem['status_bayar']) ?>
          <br><a href="pembayaran_admin.php" class="btn btn-primary btn-sm" style="margin-top:8px;">🔍 Verifikasi Pembayaran</a>
        <?php endif; ?>
      </div>
    <?php else: ?>
    <div class="card">
      <div class="card-title">🚦 Status Booking</div>
      <div class="status-steps">
        <?php $steps = [['icon'=>'📋','label'=>'Menunggu'],['icon'=>'🚛','label'=>'Diproses'],['icon'=>'✅','label'=>'Selesai']];
        foreach ($steps as $i => $s): ?>
        <div class="step <?= $i < $current_step ? 'done' : ($i === $current_step ? 'active' : '') ?>">
          <div class="step-dot"><?= $i < $current_step ? '✓' : $s['icon'] ?></div>
          <div class="step-label"><?= $s['label'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <form method="post" style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;">
        <?php
        $next_map = ['Menunggu'=>'Diproses','Diproses'=>'Selesai'];
        $next = $next_map[$b['status']] ?? null;
        if ($next): ?>
          <button type="submit" name="status" value="<?= $next ?>" class="btn btn-primary">→ Tandai: <?= $next ?></button>
        <?php endif; ?>
        <?php if (!in_array($b['status'], ['Dibatalkan','Selesai'])): ?>
          <button type="submit" name="status" value="Dibatalkan" class="btn btn-danger"
                  onclick="return confirm('Yakin batalkan booking ini?')">✕ Batalkan</button>
        <?php endif; ?>
      </form>
    </div>
    <?php endif; ?>

    <!-- Info Pembayaran -->
    <?php if ($pem): ?>
    <div class="card">
      <div class="card-title">💳 Info Pembayaran</div>
      <div class="detail-grid">
        <div class="detail-item"><label>Metode</label><div class="value"><?= clean($pem['metode']) ?></div></div>
        <div class="detail-item"><label>Status</label><div class="value"><?= badge_bayar($pem['status_bayar']) ?></div></div>
        <div class="detail-item"><label>Jumlah Dibayar</label><div class="value" style="color:var(--blue-700);font-size:16px;"><?= rupiah($pem['jumlah_bayar']) ?></div></div>
        <div class="detail-item"><label>Pengirim</label><div class="value"><?= clean($pem['nama_pengirim'] ?: '-') ?></div></div>
        <?php if ($pem['bank_tujuan']): ?>
        <div class="detail-item"><label>Bank</label><div class="value"><?= clean($pem['bank_tujuan']) ?></div></div>
        <?php endif; ?>
        <div class="detail-item"><label>Dikirim</label><div class="value"><?= date('d/m/Y H:i', strtotime($pem['created_at'])) ?></div></div>
        <?php if ($pem['catatan_admin']): ?>
        <div class="detail-item" style="grid-column:1/-1;"><label>Catatan Admin</label><div class="value"><?= clean($pem['catatan_admin']) ?></div></div>
        <?php endif; ?>
        <?php if ($pem['bukti_bayar']): ?>
        <div class="detail-item" style="grid-column:1/-1;">
          <label>Bukti Bayar</label>
          <div class="value">
            <a href="uploads/bukti_bayar/<?= clean($pem['bukti_bayar']) ?>" target="_blank" class="btn btn-outline btn-sm">📎 Lihat Bukti</a>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($pem['status_bayar'] === 'Menunggu Verifikasi'): ?>
        <div style="margin-top:12px;">
          <a href="pembayaran_admin.php" class="btn btn-primary btn-sm">🔍 Pergi ke Verifikasi</a>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Info Booking -->
    <div class="card">
      <div class="card-title">📋 Informasi Booking</div>
      <div class="detail-grid">
        <div class="detail-item"><label>Kode Booking</label><div class="value" style="color:var(--blue-700);font-size:16px;"><?= clean($b['kode_booking']) ?></div></div>
        <div class="detail-item"><label>Status</label><div class="value"><?= badge_status($b['status']) ?></div></div>
        <div class="detail-item"><label>Tanggal</label><div class="value"><?= date('d F Y', strtotime($b['tanggal_booking'])) ?></div></div>
        <div class="detail-item"><label>Jam Pickup</label><div class="value"><?= substr($b['jam_booking'],0,5) ?> WIB</div></div>
        <div class="detail-item" style="grid-column:1/-1;"><label>Lokasi Jemput</label><div class="value">📍 <?= clean($b['lokasi_jemput']) ?></div></div>
        <div class="detail-item" style="grid-column:1/-1;"><label>Lokasi Tujuan</label><div class="value">🏁 <?= clean($b['lokasi_tujuan']) ?></div></div>
        <div class="detail-item"><label>Estimasi Jarak</label><div class="value"><?= number_format($b['estimasi_km'],0) ?> km</div></div>
        <div class="detail-item"><label>Total Bayar</label><div class="value" style="color:var(--blue-700);font-size:18px;"><?= rupiah($b['total_bayar']) ?></div></div>
        <?php if ($b['catatan']): ?>
        <div class="detail-item" style="grid-column:1/-1;"><label>Catatan</label><div class="value"><?= clean($b['catatan']) ?></div></div>
        <?php endif; ?>
        <div class="detail-item"><label>Dibuat Oleh</label><div class="value"><?= clean($b['nama_admin'] ?? 'Pelanggan Mandiri') ?></div></div>
        <div class="detail-item"><label>Dibuat Pada</label><div class="value"><?= date('d/m/Y H:i', strtotime($b['created_at'])) ?></div></div>
      </div>
    </div>
  </div>

  <div>
    <!-- Armada -->
    <div class="card">
      <div class="card-title">🚛 Armada</div>
      <div style="text-align:center;font-size:48px;margin-bottom:12px;">🚛</div>
      <div style="font-weight:700;font-size:15px;text-align:center;margin-bottom:8px;"><?= clean($b['nama_mobil']) ?></div>
      <div style="text-align:center;margin-bottom:12px;"><?= badge_jenis($b['jenis']) ?></div>
      <table style="width:100%;font-size:12.5px;">
        <tr><td style="color:var(--text-light);padding:4px 0;">Kapasitas</td><td style="font-weight:600;text-align:right;"><?= number_format($b['kapasitas_kg']) ?> kg</td></tr>
        <tr><td style="color:var(--text-light);padding:4px 0;">Tarif/km</td><td style="font-weight:600;text-align:right;"><?= rupiah($b['harga_per_km']) ?></td></tr>
        <tr><td style="color:var(--text-light);padding:4px 0;">Min. Bayar</td><td style="font-weight:600;text-align:right;"><?= rupiah($b['harga_min']) ?></td></tr>
      </table>
    </div>
    <!-- Pelanggan -->
    <div class="card">
      <div class="card-title">👤 Pelanggan</div>
      <div style="font-weight:700;margin-bottom:6px;"><?= clean($b['nama_pelanggan']) ?></div>
      <div style="font-size:13px;color:var(--text-mid);margin-bottom:4px;">📱 <?= clean($b['no_hp']) ?></div>
      <div style="font-size:13px;color:var(--text-mid);margin-bottom:4px;">📧 <?= clean($b['email']) ?></div>
      <?php if ($b['alamat']): ?><div style="font-size:12px;color:var(--text-light);">📍 <?= clean($b['alamat']) ?></div><?php endif; ?>
    </div>
    <div class="gap-2">
      <a href="booking_edit.php?id=<?= $b['id_booking'] ?>" class="btn btn-yellow" style="flex:1;justify-content:center;">✏️ Edit</a>
      <a href="booking.php" class="btn btn-outline" style="flex:1;justify-content:center;">← Kembali</a>
    </div>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>
