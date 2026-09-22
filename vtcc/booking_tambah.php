<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_login();

$page_title   = 'Tambah Booking';
$page_heading = '➕ Tambah Booking';
$page_subtitle = 'Buat booking baru atas nama pelanggan';
$active_menu  = 'booking_tambah';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_mobil     = (int)($_POST['id_mobil']      ?? 0);
    $id_pelanggan = (int)($_POST['id_pelanggan']  ?? 0);
    $tanggal      = clean($_POST['tanggal_booking'] ?? '');
    $jam          = clean($_POST['jam_booking']     ?? '');
    $lok_jemput   = clean($_POST['lokasi_jemput']   ?? '');
    $lok_tujuan   = clean($_POST['lokasi_tujuan']   ?? '');
    $estimasi_km  = (float)($_POST['estimasi_km']   ?? 0);
    $catatan      = clean($_POST['catatan']          ?? '');

    if (!$id_mobil || !$id_pelanggan || !$tanggal || !$jam || !$lok_jemput || !$lok_tujuan || $estimasi_km <= 0) {
        $error = 'Semua field bertanda * wajib diisi.';
    } else {
        $stmt = $koneksi->prepare('SELECT harga_per_km, harga_min, nama_mobil FROM mobil_towing WHERE id_mobil=?');
        $stmt->bind_param('i', $id_mobil);
        $stmt->execute();
        $mob = $stmt->get_result()->fetch_assoc();

        if (!$mob) {
            $error = 'Armada tidak ditemukan.';
        } else {
            $total  = hitung_total_bayar($estimasi_km, $mob['harga_per_km'], $mob['harga_min']);
            $kode   = generate_kode_booking($koneksi);
            $adm_id = (int)$_SESSION['id_user'];

            // Booking admin langsung Menunggu (bypass pembayaran online)
            $ins = $koneksi->prepare(
                "INSERT INTO booking
                 (kode_booking, id_mobil, id_pelanggan, tanggal_booking, jam_booking,
                  lokasi_jemput, lokasi_tujuan, estimasi_km, total_bayar, catatan, created_by, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Menunggu')"
            );
            // types: s i i s s s s d d s i  = 11 params
            $ins->bind_param('siissssddsi',
                $kode, $id_mobil, $id_pelanggan,
                $tanggal, $jam,
                $lok_jemput, $lok_tujuan,
                $estimasi_km, $total,
                $catatan, $adm_id
            );

            if ($ins->execute()) {
                $id_new = $koneksi->insert_id;
                // Notif admin (log)
                $judul_n = "📋 Booking Baru (Admin): {$kode}";
                $isi_n   = "Admin {$_SESSION['nama_lengkap']} membuat booking {$kode} armada {$mob['nama_mobil']} untuk tanggal {$tanggal}. Total: " . rupiah($total);
                tambah_notifikasi($koneksi, $judul_n, $isi_n, 'booking_baru', $id_new);

                // Notif PELANGGAN — booking dibuat admin untuknya
                $judul_p = "📋 Booking Baru Atas Nama Anda!";
                $isi_p   = "Admin VTCC telah membuat booking untuk Anda: kode {$kode}, armada {$mob['nama_mobil']}, " .
                           "tanggal " . date('d F Y', strtotime($tanggal)) . " pukul " . substr($jam,0,5) . " WIB. " .
                           "Total: " . rupiah($total) . ". Status: Menunggu konfirmasi.";
                tambah_notifikasi_pelanggan($koneksi, $id_pelanggan, $judul_p, $isi_p, 'booking_baru', $id_new);
                header('Location: booking.php?pesan=' . urlencode("Booking {$kode} berhasil ditambahkan!"));
                exit;
            } else {
                $error = 'Gagal menyimpan: ' . $koneksi->error;
            }
        }
    }
}

$mobil_list = $koneksi->query("SELECT * FROM mobil_towing WHERE status='Tersedia' ORDER BY jenis, nama_mobil");
$pel_list   = $koneksi->query("SELECT id_pelanggan, nama_pelanggan, no_hp FROM pelanggan ORDER BY nama_pelanggan");
require_once 'includes/header.php';
?>
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>
<?php if ($error): ?><div class="alert alert-danger">❌ <?= clean($error) ?></div><?php endif; ?>

<div class="card" style="max-width:700px;">
  <div class="card-title">📋 Form Booking Baru (Admin)</div>
  <div class="info-box" style="margin-bottom:16px;">
    ℹ️ Booking oleh admin langsung berstatus <strong>Menunggu</strong> tanpa melalui proses pembayaran online.
  </div>
  <form method="post" action="booking_tambah.php">
    <div class="form-row">
      <div class="form-group">
        <label>🚛 Armada *</label>
        <select name="id_mobil" id="id_mobil" required>
          <option value="">-- Pilih Armada --</option>
          <?php while ($m = $mobil_list->fetch_assoc()):
            $pk = number_format($m['harga_per_km'],0,'.','.'); $mn = number_format($m['harga_min'],0,'.','.'); ?>
          <option value="<?= $m['id_mobil'] ?>"
                  data-harga-km="<?= $m['harga_per_km'] ?>"
                  data-harga-min="<?= $m['harga_min'] ?>">
            <?= clean($m['nama_mobil']) ?> — Rp<?= $pk ?>/km (min Rp<?= $mn ?>)
          </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="form-group">
        <label>👥 Pelanggan *</label>
        <select name="id_pelanggan" required>
          <option value="">-- Pilih Pelanggan --</option>
          <?php while ($p = $pel_list->fetch_assoc()): ?>
          <option value="<?= $p['id_pelanggan'] ?>">
            <?= clean($p['nama_pelanggan']) ?> (<?= clean($p['no_hp']) ?>)
          </option>
          <?php endwhile; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>📅 Tanggal *</label>
        <input type="date" name="tanggal_booking" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
      </div>
      <div class="form-group">
        <label>⏰ Jam *</label>
        <input type="time" name="jam_booking" value="08:00" required>
      </div>
    </div>
    <div class="form-group">
      <label>📍 Lokasi Jemput *</label>
      <input type="text" name="lokasi_jemput" placeholder="Alamat lengkap lokasi jemput kendaraan" required>
    </div>
    <div class="form-group">
      <label>🏁 Lokasi Tujuan *</label>
      <input type="text" name="lokasi_tujuan" placeholder="Bengkel / tujuan pengiriman" required>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>📏 Estimasi Jarak (km) *</label>
        <input type="number" name="estimasi_km" id="estimasi_km" min="1" step="0.5" placeholder="25" required>
      </div>
      <div class="form-group">
        <label>💰 Estimasi Biaya</label>
        <div id="info-total" style="padding:10px 14px;background:var(--yellow-50);border:1.5px solid var(--yellow-100);
             border-radius:8px;font-weight:700;color:var(--blue-900);font-size:15px;">
          Pilih armada &amp; isi jarak
        </div>
      </div>
    </div>
    <div class="form-group">
      <label>📝 Catatan</label>
      <textarea name="catatan" rows="2" placeholder="Informasi tambahan, kondisi kendaraan, dsb."></textarea>
    </div>
    <div class="gap-2">
      <button type="submit" class="btn btn-primary">✅ Simpan Booking</button>
      <a href="booking.php" class="btn btn-outline">Batal</a>
    </div>
  </form>
</div>
<?php require_once 'includes/footer.php'; ?>
