<?php
/**
 * FILE: includes/functions.php
 * VTCC - Helper functions v3.1 (auto-migrate notifikasi table)
 */

function clean($str) {
    return htmlspecialchars(trim((string)$str), ENT_QUOTES, 'UTF-8');
}

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function generate_kode_booking($koneksi) {
    $prefix = 'BKG-' . date('Ymd') . '-';
    $result = $koneksi->query(
        "SELECT kode_booking FROM booking
         WHERE kode_booking LIKE '{$prefix}%'
         ORDER BY kode_booking DESC LIMIT 1"
    );
    $num = ($result && $result->num_rows > 0)
        ? (int)substr($result->fetch_assoc()['kode_booking'], -3) + 1
        : 1;
    return $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
}

function hitung_total_bayar($estimasi_km, $harga_per_km, $harga_min) {
    return max((float)$estimasi_km * (float)$harga_per_km, (float)$harga_min);
}

function badge_status($status) {
    $map = [
        'Menunggu Pembayaran' => 'badge-warning',
        'Menunggu'            => 'badge-blue',
        'Diproses'            => 'badge-info',
        'Selesai'             => 'badge-success',
        'Dibatalkan'          => 'badge-danger',
    ];
    return '<span class="badge ' . ($map[$status] ?? 'badge-info') . '">' . clean($status) . '</span>';
}

function badge_jenis($jenis) {
    $map = [
        'Light Duty'  => 'badge-light',
        'Medium Duty' => 'badge-medium',
        'Heavy Duty'  => 'badge-heavy',
    ];
    return '<span class="badge ' . ($map[$jenis] ?? 'badge-info') . '">' . clean($jenis) . '</span>';
}

function badge_bayar($status) {
    $map = [
        'Menunggu Verifikasi' => 'badge-warning',
        'Lunas'               => 'badge-success',
        'Ditolak'             => 'badge-danger',
    ];
    return '<span class="badge ' . ($map[$status] ?? 'badge-warning') . '">' . clean($status) . '</span>';
}

// ============================================================
// AUTO-MIGRATE: pastikan kolom id_pelanggan & tipe enum ada
// Dipanggil sekali jika kolom belum ada (database lama)
// ============================================================
function pastikan_kolom_notifikasi($koneksi) {
    // Cek apakah kolom id_pelanggan sudah ada
    $cek = $koneksi->query(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'notifikasi'
         AND COLUMN_NAME = 'id_pelanggan'"
    );
    if ($cek && $cek->num_rows === 0) {
        // Tambah kolom id_pelanggan
        $koneksi->query(
            "ALTER TABLE notifikasi
             ADD COLUMN id_pelanggan INT NULL DEFAULT NULL
             AFTER id_booking,
             ADD CONSTRAINT fk_notif_pelanggan
                 FOREIGN KEY (id_pelanggan)
                 REFERENCES pelanggan(id_pelanggan)
                 ON DELETE CASCADE"
        );
        // Tambah index
        $koneksi->query(
            "ALTER TABLE notifikasi
             ADD INDEX idx_notif_pelanggan (id_pelanggan, is_read)"
        );
        // Update enum tipe agar support status_update
        $koneksi->query(
            "ALTER TABLE notifikasi
             MODIFY COLUMN tipe
             ENUM('booking_baru','pembayaran','status_update','info')
             NOT NULL DEFAULT 'info'"
        );
    }
}

// ============================================================
// NOTIFIKASI ADMIN (id_pelanggan = NULL)
// ============================================================
function tambah_notifikasi($koneksi, $judul, $isi, $tipe, $id_booking = null) {
    // Pastikan tipe valid
    $tipe_valid = ['booking_baru', 'pembayaran', 'status_update', 'info'];
    if (!in_array($tipe, $tipe_valid)) $tipe = 'info';

    $stmt = $koneksi->prepare(
        "INSERT INTO notifikasi (judul, isi, tipe, id_booking, id_pelanggan)
         VALUES (?, ?, ?, ?, NULL)"
    );
    if (!$stmt) return; // Kolom belum ada, skip
    $stmt->bind_param('sssi', $judul, $isi, $tipe, $id_booking);
    $stmt->execute();
}

// ============================================================
// NOTIFIKASI PELANGGAN (id_pelanggan diisi)
// ============================================================
function tambah_notifikasi_pelanggan($koneksi, $id_pelanggan, $judul, $isi, $tipe, $id_booking = null) {
    $tipe_valid = ['booking_baru', 'pembayaran', 'status_update', 'info'];
    if (!in_array($tipe, $tipe_valid)) $tipe = 'info';

    $stmt = $koneksi->prepare(
        "INSERT INTO notifikasi (judul, isi, tipe, id_booking, id_pelanggan)
         VALUES (?, ?, ?, ?, ?)"
    );
    if (!$stmt) return; // Kolom belum ada, skip
    $stmt->bind_param('sssii', $judul, $isi, $tipe, $id_booking, $id_pelanggan);
    $stmt->execute();
}

// ============================================================
// HITUNG NOTIF BELUM DIBACA
// ============================================================
function hitung_notif_belum_baca($koneksi) {
    // Cek dulu apakah kolom id_pelanggan ada
    $cek = $koneksi->query(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'notifikasi'
         AND COLUMN_NAME = 'id_pelanggan'"
    );
    if (!$cek || $cek->num_rows === 0) {
        // Kolom belum ada, pakai query lama
        $r = $koneksi->query("SELECT COUNT(*) c FROM notifikasi WHERE is_read = 0");
        return $r ? (int)$r->fetch_assoc()['c'] : 0;
    }

    $r = $koneksi->query(
        "SELECT COUNT(*) c FROM notifikasi WHERE id_pelanggan IS NULL AND is_read = 0"
    );
    return $r ? (int)$r->fetch_assoc()['c'] : 0;
}

function hitung_notif_pelanggan($koneksi, $id_pelanggan) {
    // Cek dulu apakah kolom id_pelanggan ada
    $cek = $koneksi->query(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'notifikasi'
         AND COLUMN_NAME = 'id_pelanggan'"
    );
    if (!$cek || $cek->num_rows === 0) {
        // Kolom belum ada — jalankan migrasi otomatis
        pastikan_kolom_notifikasi($koneksi);
        return 0; // Belum ada notif, return 0
    }

    $stmt = $koneksi->prepare(
        "SELECT COUNT(*) c FROM notifikasi WHERE id_pelanggan = ? AND is_read = 0"
    );
    if (!$stmt) return 0;
    $stmt->bind_param('i', $id_pelanggan);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (int)$row['c'] : 0;
}
