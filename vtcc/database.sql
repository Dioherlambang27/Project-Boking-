-- =====================================================================
-- FILE   : database.sql
-- PROYEK : VTCC - Vehicle Testing And Certification Center
-- Versi  : 2.0 (dengan pembayaran & notifikasi)
-- =====================================================================

DROP DATABASE IF EXISTS vtcc_db;
CREATE DATABASE vtcc_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vtcc_db;

-- Tabel: users (Admin)
CREATE TABLE users (
    id_user      INT AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(50)  NOT NULL UNIQUE,
    password     VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    role         ENUM('admin') NOT NULL DEFAULT 'admin',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabel: pelanggan
CREATE TABLE pelanggan (
    id_pelanggan   INT AUTO_INCREMENT PRIMARY KEY,
    nama_pelanggan VARCHAR(100) NOT NULL,
    no_hp          VARCHAR(20)  NOT NULL,
    email          VARCHAR(100) NOT NULL UNIQUE,
    password       VARCHAR(255) NULL,
    no_ktp         VARCHAR(20)  NULL,
    alamat         VARCHAR(255),
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabel: mobil_towing
CREATE TABLE mobil_towing (
    id_mobil     INT AUTO_INCREMENT PRIMARY KEY,
    kode_mobil   VARCHAR(10)  NOT NULL UNIQUE,
    nama_mobil   VARCHAR(100) NOT NULL,
    jenis        ENUM('Light Duty','Medium Duty','Heavy Duty') NOT NULL,
    kapasitas_kg INT NOT NULL,
    harga_per_km DECIMAL(10,2) NOT NULL,
    harga_min    DECIMAL(10,2) NOT NULL,
    deskripsi    TEXT,
    status       ENUM('Tersedia','Tidak Tersedia') NOT NULL DEFAULT 'Tersedia',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabel: booking
CREATE TABLE booking (
    id_booking      INT AUTO_INCREMENT PRIMARY KEY,
    kode_booking    VARCHAR(20) NOT NULL UNIQUE,
    id_mobil        INT NOT NULL,
    id_pelanggan    INT NOT NULL,
    tanggal_booking DATE NOT NULL,
    jam_booking     TIME NOT NULL,
    lokasi_jemput   VARCHAR(255) NOT NULL,
    lokasi_tujuan   VARCHAR(255) NOT NULL,
    estimasi_km     DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_bayar     DECIMAL(10,2) NOT NULL DEFAULT 0,
    status          ENUM('Menunggu Pembayaran','Menunggu','Diproses','Selesai','Dibatalkan') NOT NULL DEFAULT 'Menunggu Pembayaran',
    catatan         TEXT,
    created_by      INT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_booking_mobil     FOREIGN KEY (id_mobil)     REFERENCES mobil_towing(id_mobil)   ON DELETE RESTRICT,
    CONSTRAINT fk_booking_pelanggan FOREIGN KEY (id_pelanggan) REFERENCES pelanggan(id_pelanggan)  ON DELETE RESTRICT,
    CONSTRAINT fk_booking_user      FOREIGN KEY (created_by)   REFERENCES users(id_user)            ON DELETE SET NULL,
    INDEX idx_booking_tanggal (id_mobil, tanggal_booking, jam_booking)
) ENGINE=InnoDB;

-- Tabel: pembayaran
CREATE TABLE pembayaran (
    id_pembayaran    INT AUTO_INCREMENT PRIMARY KEY,
    id_booking       INT NOT NULL UNIQUE,
    metode           ENUM('Cash','QRIS','Transfer Bank') NOT NULL,
    bank_tujuan      VARCHAR(50) NULL COMMENT 'Untuk Transfer Bank: BCA/BNI/Mandiri',
    no_rekening      VARCHAR(30) NULL,
    nama_pengirim    VARCHAR(100) NULL,
    jumlah_bayar     DECIMAL(10,2) NOT NULL,
    bukti_bayar      VARCHAR(255) NULL COMMENT 'Nama file bukti upload',
    status_bayar     ENUM('Menunggu Verifikasi','Lunas','Ditolak') NOT NULL DEFAULT 'Menunggu Verifikasi',
    catatan_admin    TEXT NULL,
    verified_at      TIMESTAMP NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pem_booking FOREIGN KEY (id_booking) REFERENCES booking(id_booking) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabel: notifikasi
CREATE TABLE notifikasi (
    id_notif      INT AUTO_INCREMENT PRIMARY KEY,
    judul         VARCHAR(150) NOT NULL,
    isi           TEXT NOT NULL,
    tipe          ENUM('booking_baru','pembayaran','status_update','info') NOT NULL DEFAULT 'info',
    id_booking    INT NULL,
    id_pelanggan  INT NULL COMMENT 'NULL = notifikasi untuk admin; diisi = notifikasi untuk pelanggan',
    is_read       TINYINT(1) NOT NULL DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_booking    FOREIGN KEY (id_booking)   REFERENCES booking(id_booking)     ON DELETE SET NULL,
    CONSTRAINT fk_notif_pelanggan  FOREIGN KEY (id_pelanggan) REFERENCES pelanggan(id_pelanggan) ON DELETE CASCADE,
    INDEX idx_notif_pelanggan (id_pelanggan, is_read),
    INDEX idx_notif_admin     (id_pelanggan, created_at)
) ENGINE=InnoDB;

-- =====================================================================
-- SEED DATA
-- =====================================================================

-- Admin: username=admin / password=vtcc2024
INSERT INTO users (username, password, nama_lengkap, role) VALUES
('admin', '$2y$10$SIyNxhjGRnsKEy6hKSPE6OXHc3dXub.ougp0wA9RV0TH.yrso7Xhu', 'Administrator VTCC', 'admin');

-- 6 Armada
INSERT INTO mobil_towing (kode_mobil, nama_mobil, jenis, kapasitas_kg, harga_per_km, harga_min, deskripsi, status) VALUES
('TWG-001', 'Toyota Hilux Towing Ringan',   'Light Duty',  2000,  8000,  150000, 'Cocok untuk mobil sedan, hatchback, dan kendaraan ringan hingga 2 ton. Dilengkapi winch 3 ton dan lampu hazard lengkap.', 'Tersedia'),
('TWG-002', 'Isuzu Panther Medium Tow',     'Medium Duty', 5000,  12000, 250000, 'Ideal untuk SUV, MPV, dan kendaraan medium hingga 5 ton. Dilengkapi flatbed dan sistem pengaman kendaraan canggih.', 'Tersedia'),
('TWG-003', 'Mitsubishi Colt Diesel Heavy', 'Heavy Duty',  10000, 18000, 400000, 'Untuk kendaraan berat, truk kecil, dan alat berat hingga 10 ton. Dilengkapi crane hidrolik dan rantai pengaman.', 'Tersedia'),
('TWG-004', 'Hino Ranger Super Heavy',      'Heavy Duty',  20000, 25000, 650000, 'Solusi towing kendaraan sangat berat, bus, dan truk besar hingga 20 ton. Armada paling kuat kami.', 'Tersedia'),
('TWG-005', 'Daihatsu Gran Max Flatbed',    'Light Duty',  1500,  7000,  120000, 'Flatbed khusus untuk kendaraan mewah, sport car, dan modifikasi yang perlu penanganan ekstra hati-hati.', 'Tersedia'),
('TWG-006', 'Ford Ranger Double Cabin Tow', 'Medium Duty', 3500,  10000, 200000, 'Towing serbaguna untuk pickup, minibus, dan kendaraan medium. Cocok untuk medan perkotaan dan pegunungan.', 'Tersedia');

-- Pelanggan demo (password: pelanggan123)
INSERT INTO pelanggan (nama_pelanggan, no_hp, email, password, no_ktp, alamat) VALUES
('Budi Hartono', '081234567890', 'budi.hartono@email.com', '$2y$10$bR3QN3z2999.sGZsxejSzuRHK7PHMVqgwRZHEy2wwd.YdbRgEYiAm', '3201234567890001', 'Jl. Sudirman No. 10, Jakarta Pusat'),
('Siti Rahayu',  '081298765432', 'siti.rahayu@email.com',  NULL, NULL, 'Jl. Gatot Subroto No. 25, Jakarta Selatan');
