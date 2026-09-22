<?php
/**
 * FILE: pelanggan_pembayaran.php - FIXED v3
 * VTCC - Metode Pembayaran Pelanggan
 */
require_once 'config/db.php';
require_once 'includes/auth_pelanggan.php';
require_once 'includes/functions.php';
require_login_pelanggan();

$id_booking = (int)($_GET['id'] ?? 0);
$id_pel     = (int)$_SESSION['id_pelanggan'];

// Validasi booking
$stmt = $koneksi->prepare(
    "SELECT b.*, m.nama_mobil, m.jenis FROM booking b
     JOIN mobil_towing m ON m.id_mobil = b.id_mobil
     WHERE b.id_booking = ? AND b.id_pelanggan = ? AND b.status = 'Menunggu Pembayaran'"
);
$stmt->bind_param('ii', $id_booking, $id_pel);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header('Location: pelanggan_riwayat.php?pesan=' . urlencode('Booking tidak ditemukan atau sudah dibayar.'));
    exit;
}

// Cek pembayaran sudah ada
$cekP = $koneksi->prepare("SELECT * FROM pembayaran WHERE id_booking = ?");
$cekP->bind_param('i', $id_booking);
$cekP->execute();
$existing = $cekP->get_result()->fetch_assoc();

$page_title    = 'Pembayaran';
$active_menu_p = 'riwayat';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing) {
    $metode        = clean($_POST['metode']        ?? '');
    $bank_tujuan   = clean($_POST['bank_tujuan']   ?? '');
    $no_rekening   = clean($_POST['no_rekening']   ?? '');
    $nama_pengirim = clean($_POST['nama_pengirim'] ?? '');
    $jumlah        = (float)$booking['total_bayar'];
    $bukti_bayar   = null;

    $allowed_metode = ['Cash', 'QRIS', 'Transfer Bank'];

    // Validasi dasar
    if (!in_array($metode, $allowed_metode)) {
        $error = 'Pilih salah satu metode pembayaran terlebih dahulu.';
    } elseif (empty($nama_pengirim)) {
        $error = 'Nama lengkap pembayar wajib diisi.';
    } elseif ($metode === 'Transfer Bank' && empty($bank_tujuan)) {
        $error = 'Pilih bank tujuan transfer.';
    } else {
        // Upload bukti (wajib untuk QRIS & Transfer Bank)
        $butuh_bukti = in_array($metode, ['QRIS', 'Transfer Bank']);

        if ($butuh_bukti) {
            if (empty($_FILES['bukti_bayar']['name'])) {
                $error = 'Upload bukti pembayaran wajib untuk metode ' . $metode . '.';
            } else {
                $ext     = strtolower(pathinfo($_FILES['bukti_bayar']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','pdf'];
                if (!in_array($ext, $allowed)) {
                    $error = 'Format file bukti harus JPG/PNG/PDF.';
                } elseif ($_FILES['bukti_bayar']['size'] > 3 * 1024 * 1024) {
                    $error = 'Ukuran file maksimal 3 MB.';
                } elseif ($_FILES['bukti_bayar']['error'] !== UPLOAD_ERR_OK) {
                    $error = 'Terjadi kesalahan saat upload file. Coba lagi.';
                } else {
                    $nama_file  = 'BUKTI-' . $booking['kode_booking'] . '-' . time() . '.' . $ext;
                    $upload_dir = __DIR__ . '/uploads/bukti_bayar/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    if (!move_uploaded_file($_FILES['bukti_bayar']['tmp_name'], $upload_dir . $nama_file)) {
                        $error = 'Gagal menyimpan file bukti. Periksa izin folder.';
                    } else {
                        $bukti_bayar = $nama_file;
                    }
                }
            }
        }

        if (!$error) {
            // FIX: types = i s s s s d s = 7 params (semua benar)
            $ins = $koneksi->prepare(
                "INSERT INTO pembayaran
                 (id_booking, metode, bank_tujuan, no_rekening, nama_pengirim, jumlah_bayar, bukti_bayar)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $ins->bind_param('issssds',
                $id_booking,
                $metode,
                $bank_tujuan,
                $no_rekening,
                $nama_pengirim,
                $jumlah,
                $bukti_bayar
            );

            if ($ins->execute()) {
                // Notifikasi admin
                $judul_notif = "💳 Pembayaran Baru: " . $booking['kode_booking'];
                $isi_notif   = "Pelanggan " . $_SESSION['nama_pelanggan'] .
                               " mengajukan pembayaran {$metode} untuk booking " .
                               $booking['kode_booking'] . " senilai " . rupiah($jumlah) . ". Silakan verifikasi.";
                tambah_notifikasi($koneksi, $judul_notif, $isi_notif, 'pembayaran', $id_booking);

                header('Location: pelanggan_riwayat.php?pesan=' .
                       urlencode('Pembayaran berhasil dikirim! Menunggu verifikasi admin.'));
                exit;
            } else {
                $error = 'Gagal menyimpan pembayaran: ' . $koneksi->error;
            }
        }
    }
}

$rekening_bank = [
    'BCA'     => ['no' => '1234567890', 'nama' => 'PT VTCC Indonesia'],
    'BNI'     => ['no' => '0987654321', 'nama' => 'PT VTCC Indonesia'],
    'Mandiri' => ['no' => '1122334455', 'nama' => 'PT VTCC Indonesia'],
];

require_once 'includes/header_pelanggan.php';
?>

<div style="max-width:700px;margin:0 auto;">
  <div class="card">
    <div class="card-title">💳 Pembayaran Booking</div>

    <!-- Ringkasan Pesanan -->
    <div style="background:var(--blue-50);border:1px solid var(--blue-100);border-radius:12px;padding:20px;margin-bottom:24px;">
      <div style="font-weight:700;font-size:16px;color:var(--blue-900);margin-bottom:12px;">📋 Ringkasan Pesanan</div>
      <div class="detail-grid">
        <div class="detail-item">
          <label>Kode Booking</label>
          <div class="value" style="color:var(--blue-700);"><?= clean($booking['kode_booking']) ?></div>
        </div>
        <div class="detail-item">
          <label>Armada</label>
          <div class="value"><?= clean($booking['nama_mobil']) ?></div>
        </div>
        <div class="detail-item">
          <label>Tanggal</label>
          <div class="value"><?= date('d F Y', strtotime($booking['tanggal_booking'])) ?></div>
        </div>
        <div class="detail-item">
          <label>Jam</label>
          <div class="value"><?= substr($booking['jam_booking'], 0, 5) ?> WIB</div>
        </div>
        <div class="detail-item" style="grid-column:1/-1;">
          <label>Rute</label>
          <div class="value">📍 <?= clean($booking['lokasi_jemput']) ?> → 🏁 <?= clean($booking['lokasi_tujuan']) ?></div>
        </div>
      </div>
      <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--blue-100);
                  display:flex;justify-content:space-between;align-items:center;">
        <span style="font-weight:600;color:var(--text-mid);">Total yang harus dibayar:</span>
        <span style="font-size:24px;font-weight:800;color:var(--blue-700);"><?= rupiah($booking['total_bayar']) ?></span>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger">❌ <?= clean($error) ?></div>
    <?php endif; ?>

    <?php if ($existing): ?>
      <div class="alert alert-warning">
        ⏳ Pembayaran Anda sudah dikirim dengan metode <strong><?= clean($existing['metode']) ?></strong>.<br>
        Status: <?= badge_bayar($existing['status_bayar']) ?><br>
        <small>Menunggu verifikasi admin. Harap bersabar.</small>
      </div>
      <div style="margin-top:16px;">
        <a href="pelanggan_riwayat.php" class="btn btn-outline">← Kembali ke Riwayat</a>
      </div>
    <?php else: ?>

    <!-- FORM - satu form tunggal, semua field pakai nama unik -->
    <form method="post" action="pelanggan_pembayaran.php?id=<?= $id_booking ?>" enctype="multipart/form-data" id="formBayar">
      <!-- Hidden: metode dipilih via JS -->
      <input type="hidden" name="metode" id="input_metode" value="">
      <input type="hidden" name="bank_tujuan" id="input_bank">
      <input type="hidden" name="no_rekening" id="input_no_rek">

      <!-- Pilih Metode -->
      <div class="form-group">
        <label style="font-size:15px;font-weight:700;color:var(--text-dark);">Pilih Metode Pembayaran</label>
        <div class="metode-grid">
          <div class="metode-card" id="mc-Cash" onclick="pilihMetode('Cash')">
            <div class="metode-icon">💵</div>
            <div class="metode-label">Cash</div>
          </div>
          <div class="metode-card" id="mc-QRIS" onclick="pilihMetode('QRIS')">
            <div class="metode-icon">📱</div>
            <div class="metode-label">QRIS</div>
          </div>
          <div class="metode-card" id="mc-Transfer" onclick="pilihMetode('Transfer Bank')">
            <div class="metode-icon">🏦</div>
            <div class="metode-label">Transfer Bank</div>
          </div>
        </div>
        <div id="error-metode" style="display:none;color:var(--danger);font-size:13px;margin-top:6px;">
          ⚠️ Pilih metode pembayaran terlebih dahulu.
        </div>
      </div>

      <!-- Panel: Cash -->
      <div id="panel-Cash" class="metode-panel" style="display:none;">
        <div class="info-box">
          💵 <strong>Pembayaran Cash</strong><br>
          Siapkan uang tunai sebesar <strong><?= rupiah($booking['total_bayar']) ?></strong>
          saat tim kami tiba di lokasi Anda. Tidak diperlukan bukti transfer.
        </div>
      </div>

      <!-- Panel: QRIS -->
      <div id="panel-QRIS" class="metode-panel" style="display:none;">
        <div class="info-box yellow">
          📱 <strong>Pembayaran QRIS</strong> — Scan QR di bawah dengan aplikasi dompet digital Anda.
        </div>
        <div style="text-align:center;padding:20px;">
          <div style="width:180px;height:180px;background:var(--light-gray);border:2px dashed var(--gray);
                      border-radius:12px;display:inline-flex;align-items:center;justify-content:center;
                      font-size:64px;margin-bottom:12px;">📱</div>
          <div style="font-weight:600;color:var(--text-mid);font-size:13px;">
            VTCC - PT Vehicle Testing<br>
            Nominal: <strong style="color:var(--blue-700);"><?= rupiah($booking['total_bayar']) ?></strong>
          </div>
        </div>
        <div class="form-group">
          <label>Upload Bukti Bayar <span id="lbl-bukti-qris">*</span>
            <small class="text-muted">(JPG/PNG/PDF, maks 3MB)</small>
          </label>
          <input type="file" name="bukti_bayar" id="input_bukti" accept=".jpg,.jpeg,.png,.pdf">
        </div>
      </div>

      <!-- Panel: Transfer Bank -->
      <div id="panel-Transfer" class="metode-panel" style="display:none;">
        <div class="info-box">
          🏦 <strong>Transfer Bank</strong> — Pilih bank dan transfer ke rekening berikut:
        </div>
        <div class="form-group">
          <label>Pilih Bank Tujuan *</label>
          <select id="sel_bank" onchange="pilihBank(this.value)"
                  style="width:100%;padding:10px 14px;border:1.5px solid var(--gray);border-radius:8px;font-family:var(--font);font-size:14px;">
            <option value="">-- Pilih Bank --</option>
            <option value="BCA">Bank BCA</option>
            <option value="BNI">Bank BNI</option>
            <option value="Mandiri">Bank Mandiri</option>
          </select>
        </div>
        <?php foreach ($rekening_bank as $bank => $info): ?>
        <div id="rek-<?= $bank ?>" class="rek-info"
             style="display:none;background:var(--yellow-50);border:1.5px solid var(--yellow-300);
                    border-radius:10px;padding:16px;margin-bottom:16px;">
          <div style="font-weight:700;margin-bottom:6px;">Rekening <?= $bank ?></div>
          <div style="font-size:22px;font-weight:800;color:var(--blue-700);letter-spacing:2px;margin:8px 0;">
            <?= $info['no'] ?>
          </div>
          <div style="font-size:13px;color:var(--text-mid);">a.n. <?= $info['nama'] ?></div>
          <div style="margin-top:8px;font-weight:700;">Transfer tepat: <?= rupiah($booking['total_bayar']) ?></div>
        </div>
        <?php endforeach; ?>
        <div class="form-group">
          <label>Upload Bukti Transfer * <small class="text-muted">(JPG/PNG/PDF, maks 3MB)</small></label>
          <input type="file" name="bukti_bayar" id="input_bukti_tf" accept=".jpg,.jpeg,.png,.pdf">
        </div>
      </div>

      <!-- Nama Pengirim — selalu tampil setelah metode dipilih -->
      <div id="wrap-nama" style="display:none;" class="form-group">
        <label>Nama Lengkap Pembayar *</label>
        <input type="text" name="nama_pengirim" id="input_nama"
               placeholder="Nama sesuai identitas / rekening"
               value="<?= clean($_SESSION['nama_pelanggan']) ?>">
      </div>

      <!-- Tombol Submit -->
      <div class="gap-2" style="margin-top:24px;">
        <button type="button" onclick="submitBayar()"
                class="btn btn-primary" style="padding:13px 28px;font-size:15px;">
          ✅ Kirim Pembayaran
        </button>
        <a href="pelanggan_riwayat.php" class="btn btn-outline">← Batal</a>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>

<style>
.metode-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin-top: 8px;
}
.metode-card {
  border: 2px solid var(--gray);
  border-radius: 12px;
  padding: 20px 12px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
  user-select: none;
}
.metode-card:hover { border-color: var(--blue-500); background: var(--blue-50); }
.metode-card.active {
  border-color: var(--blue-600);
  background: var(--blue-50);
  box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
}
.metode-icon { font-size: 36px; margin-bottom: 8px; }
.metode-label { font-weight: 700; font-size: 13px; color: var(--text-dark); }
.metode-panel { margin-top: 4px; }
@media (max-width:480px) { .metode-grid { grid-template-columns: 1fr; } }
</style>

<script>
let metodeTerpilih = '';

const rekeningSLot = {
  BCA: { no: '1234567890', nama: 'PT VTCC Indonesia' },
  BNI: { no: '0987654321', nama: 'PT VTCC Indonesia' },
  Mandiri: { no: '1122334455', nama: 'PT VTCC Indonesia' }
};

function pilihMetode(metode) {
  metodeTerpilih = metode;

  // Set hidden input
  document.getElementById('input_metode').value = metode;

  // Reset semua card & panel
  document.querySelectorAll('.metode-card').forEach(c => c.classList.remove('active'));
  document.querySelectorAll('.metode-panel').forEach(p => p.style.display = 'none');
  document.getElementById('error-metode').style.display = 'none';

  // Aktifkan card yang dipilih
  const cardMap = { 'Cash': 'mc-Cash', 'QRIS': 'mc-QRIS', 'Transfer Bank': 'mc-Transfer' };
  const panelMap = { 'Cash': 'panel-Cash', 'QRIS': 'panel-QRIS', 'Transfer Bank': 'panel-Transfer' };

  const cardEl  = document.getElementById(cardMap[metode]);
  const panelEl = document.getElementById(panelMap[metode]);
  if (cardEl)  cardEl.classList.add('active');
  if (panelEl) panelEl.style.display = 'block';

  // Tampilkan nama pengirim
  document.getElementById('wrap-nama').style.display = 'block';

  // Reset bank pilihan jika bukan transfer
  if (metode !== 'Transfer Bank') {
    document.getElementById('input_bank').value  = '';
    document.getElementById('input_no_rek').value = '';
  }
}

function pilihBank(bank) {
  // Update hidden inputs
  document.getElementById('input_bank').value = bank;
  document.getElementById('input_no_rek').value = bank ? (rekeningSLot[bank]?.no || '') : '';

  // Tampilkan info rekening
  document.querySelectorAll('.rek-info').forEach(r => r.style.display = 'none');
  if (bank) {
    const el = document.getElementById('rek-' + bank);
    if (el) el.style.display = 'block';
  }
}

function submitBayar() {
  // Validasi metode dipilih
  if (!metodeTerpilih) {
    document.getElementById('error-metode').style.display = 'block';
    document.querySelector('.metode-grid').scrollIntoView({ behavior: 'smooth' });
    return;
  }

  // Validasi nama pengirim
  const nama = document.getElementById('input_nama').value.trim();
  if (!nama) {
    alert('Nama lengkap pembayar wajib diisi.');
    document.getElementById('input_nama').focus();
    return;
  }

  // Validasi bank jika Transfer
  if (metodeTerpilih === 'Transfer Bank') {
    const bank = document.getElementById('sel_bank').value;
    if (!bank) {
      alert('Pilih bank tujuan transfer terlebih dahulu.');
      document.getElementById('sel_bank').focus();
      return;
    }
    // Cek upload bukti
    const fileTf = document.getElementById('input_bukti_tf');
    if (!fileTf || !fileTf.files.length) {
      alert('Upload bukti transfer wajib dilakukan.');
      fileTf.focus();
      return;
    }
  }

  // Validasi upload bukti untuk QRIS
  if (metodeTerpilih === 'QRIS') {
    const fileQ = document.getElementById('input_bukti');
    if (!fileQ || !fileQ.files.length) {
      alert('Upload bukti pembayaran QRIS wajib dilakukan.');
      fileQ.focus();
      return;
    }
  }

  // Submit form
  document.getElementById('formBayar').submit();
}
</script>

<?php require_once 'includes/footer_pelanggan.php'; ?>
