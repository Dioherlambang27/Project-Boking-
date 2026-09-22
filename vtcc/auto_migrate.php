<?php
/**
 * FILE: auto_migrate.php
 * VTCC - Migrasi database otomatis dari v1 ke v2
 * Jalankan SEKALI di browser: http://localhost/vtcc/auto_migrate.php
 * Hapus file ini setelah berhasil!
 */
require_once 'config/db.php';

$log = [];
$err = [];

function run_sql($koneksi, $sql, $label, &$log, &$err) {
    if ($koneksi->query($sql)) {
        $log[] = "✅ {$label}";
    } else {
        // Ignore "duplicate" errors (key already exists, dll)
        $errno = $koneksi->errno;
        if (in_array($errno, [1060, 1061, 1062, 1068, 1091, 1826])) {
            $log[] = "⏭️ {$label} (sudah ada, dilewati)";
        } else {
            $err[] = "❌ {$label}: " . $koneksi->error . " (errno {$errno})";
        }
    }
}

// 1. Tabel pembayaran
run_sql($koneksi,
    "CREATE TABLE IF NOT EXISTS pembayaran (
        id_pembayaran INT AUTO_INCREMENT PRIMARY KEY,
        id_booking    INT NOT NULL UNIQUE,
        metode        ENUM('Cash','QRIS','Transfer Bank') NOT NULL,
        bank_tujuan   VARCHAR(50)  NULL,
        no_rekening   VARCHAR(30)  NULL,
        nama_pengirim VARCHAR(100) NULL,
        jumlah_bayar  DECIMAL(10,2) NOT NULL,
        bukti_bayar   VARCHAR(255) NULL,
        status_bayar  ENUM('Menunggu Verifikasi','Lunas','Ditolak') NOT NULL DEFAULT 'Menunggu Verifikasi',
        catatan_admin TEXT NULL,
        verified_at   TIMESTAMP NULL,
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_pem_booking FOREIGN KEY (id_booking) REFERENCES booking(id_booking) ON DELETE CASCADE
    ) ENGINE=InnoDB",
    "Tabel pembayaran", $log, $err
);

// 2. Update enum status booking
run_sql($koneksi,
    "ALTER TABLE booking MODIFY COLUMN status
     ENUM('Menunggu Pembayaran','Menunggu','Diproses','Selesai','Dibatalkan')
     NOT NULL DEFAULT 'Menunggu Pembayaran'",
    "Enum status booking (tambah 'Menunggu Pembayaran')", $log, $err
);

// 3. Tabel notifikasi
run_sql($koneksi,
    "CREATE TABLE IF NOT EXISTS notifikasi (
        id_notif     INT AUTO_INCREMENT PRIMARY KEY,
        judul        VARCHAR(150) NOT NULL,
        isi          TEXT NOT NULL,
        tipe         ENUM('booking_baru','pembayaran','status_update','info') NOT NULL DEFAULT 'info',
        id_booking   INT NULL,
        id_pelanggan INT NULL,
        is_read      TINYINT(1) NOT NULL DEFAULT 0,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",
    "Tabel notifikasi", $log, $err
);

// 4. Kolom id_pelanggan di notifikasi
$cek = $koneksi->query(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'notifikasi'
     AND COLUMN_NAME = 'id_pelanggan'"
);
if ($cek && $cek->num_rows === 0) {
    run_sql($koneksi,
        "ALTER TABLE notifikasi ADD COLUMN id_pelanggan INT NULL DEFAULT NULL AFTER id_booking",
        "Kolom id_pelanggan di notifikasi", $log, $err
    );
} else {
    $log[] = "⏭️ Kolom id_pelanggan di notifikasi (sudah ada)";
}

// 5. FK notifikasi → pelanggan
run_sql($koneksi,
    "ALTER TABLE notifikasi ADD CONSTRAINT fk_notif_pelanggan
     FOREIGN KEY (id_pelanggan) REFERENCES pelanggan(id_pelanggan) ON DELETE CASCADE",
    "FK notifikasi → pelanggan", $log, $err
);

// 6. FK notifikasi → booking
run_sql($koneksi,
    "ALTER TABLE notifikasi ADD CONSTRAINT fk_notif_booking
     FOREIGN KEY (id_booking) REFERENCES booking(id_booking) ON DELETE SET NULL",
    "FK notifikasi → booking", $log, $err
);

// 7. Update enum tipe notifikasi
run_sql($koneksi,
    "ALTER TABLE notifikasi MODIFY COLUMN tipe
     ENUM('booking_baru','pembayaran','status_update','info') NOT NULL DEFAULT 'info'",
    "Enum tipe notifikasi (tambah 'status_update')", $log, $err
);

// 8. Index performa
run_sql($koneksi,
    "ALTER TABLE notifikasi ADD INDEX idx_notif_pelanggan (id_pelanggan, is_read)",
    "Index idx_notif_pelanggan", $log, $err
);

// 9. Folder upload
$upload_dir = __DIR__ . '/uploads/bukti_bayar/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
    $log[] = "✅ Folder uploads/bukti_bayar/ dibuat";
} else {
    $log[] = "⏭️ Folder uploads/bukti_bayar/ (sudah ada)";
}

$sukses = empty($err);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Auto Migrate - VTCC</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F1F5F9; min-height: 100vh;
         display: flex; align-items: center; justify-content: center; padding: 20px; }
  .box { background: #fff; border-radius: 16px; padding: 36px; max-width: 600px; width: 100%;
         box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
  .logo { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; }
  .logo img { width: 48px; height: 48px; border-radius: 8px; }
  .logo-text { font-size: 22px; font-weight: 800; color: #1E3A5F; }
  h1 { font-size: 20px; font-weight: 800; margin-bottom: 6px; }
  .sub { color: #64748B; font-size: 13px; margin-bottom: 24px; }
  .result-bar { padding: 14px 18px; border-radius: 10px; margin-bottom: 16px; font-weight: 700; font-size: 15px; }
  .result-ok  { background: #DCFCE7; color: #14532D; border-left: 4px solid #16A34A; }
  .result-err { background: #FEE2E2; color: #7F1D1D; border-left: 4px solid #DC2626; }
  .log-list { list-style: none; margin-bottom: 24px; }
  .log-list li { padding: 8px 12px; font-size: 13px; border-bottom: 1px solid #F1F5F9;
                 display: flex; align-items: flex-start; gap: 8px; }
  .log-list li:last-child { border-bottom: none; }
  .err-list { list-style: none; margin-bottom: 24px; }
  .err-list li { padding: 8px 12px; font-size: 13px; color: #991B1B;
                 background: #FEF2F2; border-radius: 6px; margin-bottom: 4px; }
  .btn { display: inline-block; padding: 12px 24px; border-radius: 8px; font-weight: 700;
         font-size: 14px; text-decoration: none; transition: opacity 0.15s; }
  .btn:hover { opacity: 0.85; }
  .btn-primary { background: #2563EB; color: #fff; }
  .btn-yellow  { background: #FACC15; color: #1E3A5F; }
  .warn { background: #FEF3C7; border: 1px solid #FDE68A; border-radius: 8px;
          padding: 12px 16px; font-size: 13px; color: #92400E; margin-top: 16px; }
</style>
</head>
<body>
<div class="box">
  <div class="logo">
    <img src="logo_vtcc.png" alt="VTCC">
    <span class="logo-text">VTCC — Auto Migrate</span>
  </div>

  <?php if ($sukses): ?>
    <div class="result-bar result-ok">✅ Migrasi database berhasil!</div>
  <?php else: ?>
    <div class="result-bar result-err">⚠️ Ada beberapa error (cek detail di bawah)</div>
  <?php endif; ?>

  <h1>Log Migrasi</h1>
  <p class="sub">Database vtcc_db diperbarui ke versi 2.0 (notifikasi pelanggan + pembayaran)</p>

  <ul class="log-list">
    <?php foreach ($log as $l): ?>
      <li><?= htmlspecialchars($l) ?></li>
    <?php endforeach; ?>
  </ul>

  <?php if (!empty($err)): ?>
    <h2 style="font-size:15px;font-weight:700;color:#DC2626;margin-bottom:8px;">Error:</h2>
    <ul class="err-list">
      <?php foreach ($err as $e): ?>
        <li><?= htmlspecialchars($e) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <div style="display:flex;gap:12px;flex-wrap:wrap;">
    <a href="index.php" class="btn btn-primary">→ Masuk ke Dashboard Admin</a>
    <a href="pelanggan_beranda.php" class="btn btn-yellow">→ Beranda Pelanggan</a>
  </div>

  <div class="warn">
    ⚠️ <strong>Penting:</strong> Setelah migrasi berhasil, <strong>hapus file ini</strong>
    (<code>auto_migrate.php</code>) dari server untuk keamanan.
  </div>
</div>
</body>
</html>
