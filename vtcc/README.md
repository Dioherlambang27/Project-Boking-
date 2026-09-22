# 🚗 VTCC - Vehicle Testing And Certification Center
## Sistem Booking Mobil Towing

---

## 📋 Deskripsi
Aplikasi web booking mobil towing berbasis **PHP Native** untuk layanan VTCC. Pelanggan dapat memesan layanan towing secara online, sementara admin dapat mengelola seluruh proses booking.

---

## 🚀 Instalasi

### 1. Persyaratan
- PHP 7.4+ (dengan ekstensi `mysqli`)
- MySQL 5.7+ / MariaDB 10.3+
- Web server: Apache / Nginx / XAMPP / Laragon

### 2. Setup Database
1. Buka phpMyAdmin atau MySQL CLI
2. Import file `database.sql`:
   ```sql
   source /path/to/vtcc/database.sql;
   ```
   Atau lewat phpMyAdmin: **Import → pilih `database.sql` → Go**

### 3. Konfigurasi
Edit file `config/db.php` sesuaikan dengan environment Anda:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // username MySQL Anda
define('DB_PASS', '');           // password MySQL Anda
define('DB_NAME', 'vtcc_db');

// Kode khusus untuk daftar admin — GANTI ini!
define('KODE_DAFTAR_ADMIN', 'VTCC-ADMIN-2024');
```

### 4. Jalankan
Tempatkan folder `vtcc/` di dalam `htdocs/` (XAMPP) atau `www/` (Laragon), lalu akses:
```
http://localhost/vtcc/
```

---

## 🔑 Akun Default

### Admin
| Username | Password |
|----------|----------|
| `admin`  | `password` |

> ⚠️ Segera ganti password setelah login pertama!

> Untuk membuat admin baru, akses `admin_daftar.php` dan masukkan **Kode Admin**: `VTCC-ADMIN-2024`

### Pelanggan Demo
| Email | Password |
|-------|----------|
| `budi.hartono@email.com` | `pelanggan123` |

---

## 🗂️ Struktur File

```
vtcc/
├── config/
│   └── db.php                  # Konfigurasi database & konstanta
├── css/
│   └── style.css               # Stylesheet utama (putih + kuning + biru)
├── js/
│   └── main.js                 # JavaScript (sidebar, kalkulasi biaya)
├── includes/
│   ├── auth.php                # Guard session admin
│   ├── auth_pelanggan.php      # Guard session pelanggan
│   ├── functions.php           # Helper functions
│   ├── header.php              # Header admin (sidebar)
│   ├── footer.php              # Footer admin
│   ├── header_pelanggan.php    # Header pelanggan (topbar)
│   └── footer_pelanggan.php    # Footer pelanggan
│
├── [ADMIN PAGES]
├── login.php                   # Login admin
├── admin_daftar.php            # Daftar akun admin (butuh kode khusus)
├── logout.php                  # Logout admin
├── index.php                   # Dashboard admin
├── booking.php                 # Daftar semua booking
├── booking_tambah.php          # Tambah booking baru
├── booking_edit.php            # Edit booking
├── booking_detail.php          # Detail & update status booking
├── mobil.php                   # Manajemen armada (6 mobil)
├── pelanggan.php               # Manajemen data pelanggan
├── laporan.php                 # Laporan bulanan
│
├── [PELANGGAN PAGES]
├── pelanggan_login.php         # Login pelanggan
├── pelanggan_daftar.php        # Registrasi pelanggan baru
├── pelanggan_logout.php        # Logout pelanggan
├── pelanggan_beranda.php       # Beranda & katalog armada
├── pelanggan_booking.php       # Form booking towing
├── pelanggan_riwayat.php       # Riwayat booking
├── pelanggan_cancel.php        # Batalkan booking
│
└── database.sql                # SQL lengkap (buat DB + seed data)
```

---

## 🚛 6 Jenis Armada Mobil Towing

| Kode | Nama | Jenis | Kapasitas | Tarif/km | Min. Tarif |
|------|------|-------|-----------|----------|------------|
| TWG-001 | Toyota Hilux Towing Ringan | Light Duty | 2.000 kg | Rp 8.000 | Rp 150.000 |
| TWG-002 | Isuzu Panther Medium Tow | Medium Duty | 5.000 kg | Rp 12.000 | Rp 250.000 |
| TWG-003 | Mitsubishi Colt Diesel Heavy | Heavy Duty | 10.000 kg | Rp 18.000 | Rp 400.000 |
| TWG-004 | Hino Ranger Super Heavy | Heavy Duty | 20.000 kg | Rp 25.000 | Rp 650.000 |
| TWG-005 | Daihatsu Gran Max Flatbed | Light Duty | 1.500 kg | Rp 7.000 | Rp 120.000 |
| TWG-006 | Ford Ranger Double Cabin | Medium Duty | 3.500 kg | Rp 10.000 | Rp 200.000 |

---

## ✨ Fitur Utama

### Panel Admin
- ✅ Login aman dengan password hash bcrypt
- ✅ Daftar admin dengan **kode rahasia khusus**
- ✅ Dashboard dengan statistik harian & status armada real-time
- ✅ CRUD booking lengkap (tambah, edit, detail, update status)
- ✅ Timeline status booking (Menunggu → Diproses → Selesai)
- ✅ Manajemen 6 armada (aktif/nonaktif)
- ✅ Data pelanggan lengkap
- ✅ Laporan bulanan per armada

### Portal Pelanggan
- ✅ Registrasi & login mandiri (via email)
- ✅ Katalog 6 armada towing dengan detail lengkap
- ✅ Kalkulasi biaya otomatis (real-time saat input jarak)
- ✅ Form booking interaktif dengan pilih armada visual
- ✅ Riwayat booking dengan filter status
- ✅ Pembatalan booking (status Menunggu)
- ✅ Responsive untuk mobile & desktop

---

## 🎨 Tema CSS
- **Dominan Putih** — background bersih dan profesional
- **Kuning** (`#FACC15`) — aksen CTA, badge aktif, highlight
- **Biru** (`#1D4ED8`, `#2563EB`) — sidebar, tombol utama, brand
- Font: **Plus Jakarta Sans** (Google Fonts)

---

## 🔒 Keamanan
- Password disimpan dengan `password_hash()` bcrypt
- Input dibersihkan dengan `htmlspecialchars()` via fungsi `clean()`
- Query menggunakan **Prepared Statements** (anti SQL injection)
- Kode rahasia admin terpisah dari proses login biasa
- Session check di setiap halaman terproteksi

---

## 📞 Kontak
**VTCC - Vehicle Testing And Certification Center**  
📞 (021) 1234-5678 | 🌐 vtcc.co.id
