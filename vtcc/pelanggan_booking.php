<?php
/**
 * FILE: pelanggan_booking.php v2
 * VTCC - Form Booking Pelanggan + Notifikasi Admin
 */
require_once 'config/db.php';
require_once 'includes/auth_pelanggan.php';
require_once 'includes/functions.php';
require_login_pelanggan();

$page_title    = 'Booking Towing';
$active_menu_p = 'booking';

$error = '';
$form  = [
    'id_mobil'        => (int)($_GET['id_mobil'] ?? 0),
    'tanggal_booking' => date('Y-m-d'),
    'jam_booking'     => '08:00',
    'lokasi_jemput'   => '',
    'lokasi_tujuan'   => '',
    'estimasi_km'     => '',
    'catatan'         => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_mobil    = (int)($_POST['id_mobil'] ?? 0);
    $tanggal     = clean($_POST['tanggal_booking'] ?? '');
    $jam         = clean($_POST['jam_booking'] ?? '');
    $lok_jemput  = clean($_POST['lokasi_jemput'] ?? '');
    $lok_tujuan  = clean($_POST['lokasi_tujuan'] ?? '');
    $estimasi_km = (float)($_POST['estimasi_km'] ?? 0);
    $catatan     = clean($_POST['catatan'] ?? '');

    $form = array_merge($form, [
        'id_mobil' => $id_mobil, 'tanggal_booking' => $tanggal,
        'jam_booking' => $jam, 'lokasi_jemput' => $lok_jemput,
        'lokasi_tujuan' => $lok_tujuan, 'estimasi_km' => $estimasi_km, 'catatan' => $catatan,
    ]);

    if (!$id_mobil || !$tanggal || !$jam || !$lok_jemput || !$lok_tujuan || $estimasi_km <= 0) {
        $error = 'Semua field bertanda * wajib diisi dengan benar.';
    } elseif ($tanggal < date('Y-m-d')) {
        $error = 'Tanggal booking tidak boleh di masa lalu.';
    } else {
        $cekM = $koneksi->prepare("SELECT status, harga_per_km, harga_min, nama_mobil FROM mobil_towing WHERE id_mobil=?");
        $cekM->bind_param('i', $id_mobil); $cekM->execute();
        $mob = $cekM->get_result()->fetch_assoc();

        if (!$mob || $mob['status'] !== 'Tersedia') {
            $error = 'Armada yang dipilih sedang tidak tersedia.';
        } else {
            $total  = hitung_total_bayar($estimasi_km, $mob['harga_per_km'], $mob['harga_min']);
            $kode   = generate_kode_booking($koneksi);
            $id_pel = (int)$_SESSION['id_pelanggan'];

            $ins = $koneksi->prepare(
                "INSERT INTO booking
                 (kode_booking, id_mobil, id_pelanggan, tanggal_booking, jam_booking,
                  lokasi_jemput, lokasi_tujuan, estimasi_km, total_bayar, catatan, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Menunggu Pembayaran')"
            );
            $ins->bind_param('siissssdds',
                $kode, $id_mobil, $id_pel, $tanggal, $jam,
                $lok_jemput, $lok_tujuan, $estimasi_km, $total, $catatan
            );

            if ($ins->execute()) {
                $id_new_booking = $koneksi->insert_id;

                // Notif ADMIN — booking baru
                $nama_pel = $_SESSION['nama_pelanggan'];
                $judul_n  = "📋 Booking Baru: {$kode}";
                $isi_n    = "Pelanggan {$nama_pel} membuat booking baru armada {$mob['nama_mobil']} pada " .
                            date('d/m/Y', strtotime($tanggal)) . " pukul " . substr($jam,0,5) . " WIB. " .
                            "Total: " . rupiah($total) . ". Menunggu pembayaran.";
                tambah_notifikasi($koneksi, $judul_n, $isi_n, 'booking_baru', $id_new_booking);

                // Notif PELANGGAN — konfirmasi booking berhasil dibuat
                $judul_p = "📋 Booking Berhasil Dibuat!";
                $isi_p   = "Booking Anda dengan kode {$kode} untuk armada {$mob['nama_mobil']} pada " .
                           date('d F Y', strtotime($tanggal)) . " pukul " . substr($jam,0,5) . " WIB telah tercatat. " .
                           "Total: " . rupiah($total) . ". Silakan lanjutkan ke halaman pembayaran.";
                tambah_notifikasi_pelanggan($koneksi, $id_pel, $judul_p, $isi_p, 'booking_baru', $id_new_booking);

                // Redirect ke halaman pembayaran
                header('Location: pelanggan_pembayaran.php?id=' . $id_new_booking);
                exit;
            } else {
                $error = 'Gagal menyimpan booking: ' . $koneksi->error;
            }
        }
    }
}

$mobil_rs  = $koneksi->query("SELECT * FROM mobil_towing WHERE status='Tersedia' ORDER BY jenis, nama_mobil");
$mobil_arr = [];
while ($m = $mobil_rs->fetch_assoc()) $mobil_arr[] = $m;
$icons = ['Light Duty' => '🚗', 'Medium Duty' => '🚐', 'Heavy Duty' => '🚛'];

require_once 'includes/header_pelanggan.php';
?>

<div style="max-width:860px;margin:0 auto;">
  <?php if ($error): ?>
    <div class="alert alert-danger">❌ <?= clean($error) ?></div>
  <?php endif; ?>

  <!-- Pilih Armada -->
  <div class="card">
    <div class="card-title">🚛 Pilih Armada Towing</div>
    <div class="mobil-grid">
      <?php foreach ($mobil_arr as $m):
        $icon = $icons[$m['jenis']] ?? '🚗';
        $selected = $form['id_mobil'] == $m['id_mobil'];
      ?>
      <div class="mobil-card <?= $selected ? 'selected' : '' ?>"
           id="card-mobil-<?= $m['id_mobil'] ?>"
           onclick="pilihMobil(<?= $m['id_mobil'] ?>, <?= $m['harga_per_km'] ?>, <?= $m['harga_min'] ?>)">
        <span class="mobil-icon"><?= $icon ?></span>
        <div class="mobil-nama"><?= clean($m['nama_mobil']) ?></div>
        <div class="mobil-jenis"><?= badge_jenis($m['jenis']) ?></div>
        <div class="mobil-kap">⚖️ Maks. <?= number_format($m['kapasitas_kg']) ?> kg</div>
        <div style="font-size:11.5px;color:var(--text-light);margin:6px 0;line-height:1.5;">
          <?= mb_substr(clean($m['deskripsi']), 0, 72) ?>...
        </div>
        <div class="mobil-harga">
          <?= rupiah($m['harga_per_km']) ?><span>/km</span>
          <div style="font-size:11px;color:var(--text-light);font-weight:400;">Min. <?= rupiah($m['harga_min']) ?></div>
        </div>
        <div style="margin-top:10px;">
          <span class="badge <?= $selected ? 'badge-blue' : 'badge-success' ?>">
            <?= $selected ? '✓ Dipilih' : '✓ Tersedia' ?>
          </span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Form Detail -->
  <div class="card">
    <div class="card-title">📋 Detail Pesanan</div>
    <?php if (!$form['id_mobil']): ?>
      <div class="info-box">ℹ️ Silakan pilih armada towing di atas terlebih dahulu.</div>
    <?php endif; ?>

    <form method="post" action="pelanggan_booking.php">
      <input type="hidden" name="id_mobil" id="id_mobil_hidden" value="<?= (int)$form['id_mobil'] ?>">

      <div class="form-row">
        <div class="form-group">
          <label>📅 Tanggal Booking *</label>
          <input type="date" name="tanggal_booking" min="<?= date('Y-m-d') ?>"
                 value="<?= clean($form['tanggal_booking']) ?>" required>
        </div>
        <div class="form-group">
          <label>⏰ Jam Pickup *</label>
          <input type="time" name="jam_booking" value="<?= clean($form['jam_booking']) ?>" required>
        </div>
      </div>
      <div class="form-group">
        <label>📍 Lokasi Jemput / Posisi Kendaraan *</label>
        <input type="text" name="lokasi_jemput"
               placeholder="Alamat lengkap lokasi kendaraan yang akan ditowing"
               value="<?= clean($form['lokasi_jemput']) ?>" required>
      </div>
      <div class="form-group">
        <label>🏁 Lokasi Tujuan *</label>
        <input type="text" name="lokasi_tujuan"
               placeholder="Bengkel / tujuan pengiriman kendaraan"
               value="<?= clean($form['lokasi_tujuan']) ?>" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>📏 Estimasi Jarak (km) *</label>
          <input type="number" name="estimasi_km" id="estimasi_km"
                 min="1" step="0.5" placeholder="Contoh: 15"
                 value="<?= $form['estimasi_km'] ?: '' ?>" required>
        </div>
        <div class="form-group">
          <label>💰 Estimasi Total Biaya</label>
          <div id="info-total"
               style="padding:12px 14px;background:var(--yellow-50);border:1.5px solid var(--yellow-300);
                      border-radius:8px;font-weight:800;color:var(--blue-900);font-size:18px;">
            <?php
            if ($form['id_mobil'] && $form['estimasi_km']) {
                foreach ($mobil_arr as $m) {
                    if ($m['id_mobil'] == $form['id_mobil']) {
                        echo rupiah(hitung_total_bayar($form['estimasi_km'], $m['harga_per_km'], $m['harga_min']));
                        break;
                    }
                }
            } else { echo 'Pilih armada &amp; isi jarak'; }
            ?>
          </div>
          <div class="text-muted mt-1">*Estimasi, belum termasuk biaya tambahan</div>
        </div>
      </div>
      <div class="form-group">
        <label>📝 Catatan (Opsional)</label>
        <textarea name="catatan" rows="2"
                  placeholder="Kondisi kendaraan, info tambahan..."><?= clean($form['catatan']) ?></textarea>
      </div>
      <div class="info-box yellow">
        ⚠️ Setelah mengkonfirmasi booking, Anda akan diarahkan ke halaman <strong>pembayaran</strong>.
        Pilih metode: Cash, QRIS, atau Transfer Bank.
      </div>
      <div class="gap-2" style="margin-top:20px;">
        <button type="submit" class="btn btn-primary" style="padding:13px 28px;font-size:15px;">
          Lanjut ke Pembayaran →
        </button>
        <a href="pelanggan_beranda.php" class="btn btn-outline">← Batal</a>
      </div>
    </form>
  </div>
</div>

<script>
const mobilData = {};
<?php foreach ($mobil_arr as $m): ?>
mobilData[<?= $m['id_mobil'] ?>] = { hargaKm: <?= $m['harga_per_km'] ?>, hargaMin: <?= $m['harga_min'] ?> };
<?php endforeach; ?>
let selectedMobil = <?= (int)$form['id_mobil'] ?>;

function formatRp(n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); }

function pilihMobil(id) {
    document.querySelectorAll('.mobil-card').forEach(c => {
        c.classList.remove('selected');
        const b = c.querySelector('.badge');
        if (b) { b.className = 'badge badge-success'; b.textContent = '✓ Tersedia'; }
    });
    const card = document.getElementById('card-mobil-' + id);
    if (card) {
        card.classList.add('selected');
        const b = card.querySelector('.badge');
        if (b) { b.className = 'badge badge-blue'; b.textContent = '✓ Dipilih'; }
    }
    selectedMobil = id;
    document.getElementById('id_mobil_hidden').value = id;
    hitungTotal();
}

function hitungTotal() {
    if (!selectedMobil || !mobilData[selectedMobil]) return;
    const km = parseFloat(document.getElementById('estimasi_km').value) || 0;
    const { hargaKm, hargaMin } = mobilData[selectedMobil];
    const total = Math.max(km * hargaKm, hargaMin);
    const el = document.getElementById('info-total');
    if (el) el.textContent = km > 0 ? formatRp(total) : 'Isi estimasi jarak terlebih dahulu';
}
document.getElementById('estimasi_km').addEventListener('input', hitungTotal);
hitungTotal();
</script>

<?php require_once 'includes/footer_pelanggan.php'; ?>
