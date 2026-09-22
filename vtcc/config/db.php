<?php
/**
 * FILE: config/db.php
 * VTCC - Vehicle Testing And Certification Center
 * Konfigurasi koneksi database
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'vtcc_db');

// Kode rahasia pendaftaran admin - GANTI sebelum production!
define('KODE_DAFTAR_ADMIN', 'VTCC-ADMIN-2024');

// Nama aplikasi
define('APP_NAME', 'VTCC');
define('APP_FULLNAME', 'Vehicle Testing And Certification Center');

date_default_timezone_set('Asia/Jakarta');

$koneksi = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($koneksi->connect_error) {
    die('Koneksi database gagal: ' . $koneksi->connect_error);
}

$koneksi->set_charset('utf8mb4');
