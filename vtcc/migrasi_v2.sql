-- =====================================================================
-- FILE   : migrasi_v2.sql
-- VTCC   : Migrasi database dari v1 ke v2
-- Jalankan ini jika database sudah ada (tidak perlu import ulang)
-- =====================================================================
USE vtcc_db;

-- 1. Tambah tabel pembayaran (jika belum ada)
CREATE TABLE IF NOT EXISTS pembayaran (
    id_pembayaran    INT AUTO_INCREMENT PRIMARY KEY,
    id_booking       INT NOT NULL UNIQUE,
    metode           ENUM('Cash','QRIS','Transfer Bank') NOT NULL,
    bank_tujuan      VARCHAR(50) NULL,
    no_rekening      VARCHAR(30) NULL,
    nama_pengirim    VARCHAR(100) NULL,
    jumlah_bayar     DECIMAL(10,2) NOT NULL,
    bukti_bayar      VARCHAR(255) NULL,
    status_bayar     ENUM('Menunggu Verifikasi','Lunas','Ditolak') NOT NULL DEFAULT 'Menunggu Verifikasi',
    catatan_admin    TEXT NULL,
    verified_at      TIMESTAMP NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pem_booking FOREIGN KEY (id_booking) REFERENCES booking(id_booking) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 2. Update enum status booking (tambah 'Menunggu Pembayaran')
ALTER TABLE booking
    MODIFY COLUMN status
    ENUM('Menunggu Pembayaran','Menunggu','Diproses','Selesai','Dibatalkan')
    NOT NULL DEFAULT 'Menunggu Pembayaran';

-- 3. Tambah tabel notifikasi (jika belum ada)
CREATE TABLE IF NOT EXISTS notifikasi (
    id_notif      INT AUTO_INCREMENT PRIMARY KEY,
    judul         VARCHAR(150) NOT NULL,
    isi           TEXT NOT NULL,
    tipe          ENUM('booking_baru','pembayaran','status_update','info') NOT NULL DEFAULT 'info',
    id_booking    INT NULL,
    id_pelanggan  INT NULL,
    is_read       TINYINT(1) NOT NULL DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_booking FOREIGN KEY (id_booking)
        REFERENCES booking(id_booking) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 4. Tambah kolom id_pelanggan ke notifikasi (jika belum ada)
-- (Aman dijalankan berulang karena pakai IF NOT EXISTS via SET)
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'notifikasi'
    AND COLUMN_NAME = 'id_pelanggan'
);

-- Jalankan ALTER hanya jika kolom belum ada
-- (copy-paste kedua baris ini ke phpMyAdmin jika cara SET tidak didukung)
ALTER TABLE notifikasi
    ADD COLUMN IF NOT EXISTS id_pelanggan INT NULL DEFAULT NULL AFTER id_booking;

-- 5. Tambah foreign key id_pelanggan (ignore error jika sudah ada)
ALTER TABLE notifikasi
    ADD CONSTRAINT fk_notif_pelanggan
    FOREIGN KEY (id_pelanggan) REFERENCES pelanggan(id_pelanggan) ON DELETE CASCADE;

-- 6. Update enum tipe notifikasi
ALTER TABLE notifikasi
    MODIFY COLUMN tipe
    ENUM('booking_baru','pembayaran','status_update','info')
    NOT NULL DEFAULT 'info';

-- Selesai!
SELECT 'Migrasi v2 berhasil!' AS status;
