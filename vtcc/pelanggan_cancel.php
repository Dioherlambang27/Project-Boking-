<?php
require_once 'config/db.php';
require_once 'includes/auth_pelanggan.php';
require_once 'includes/functions.php';
require_login_pelanggan();

$id     = (int)($_GET['id'] ?? 0);
$id_pel = (int)$_SESSION['id_pelanggan'];

if ($id) {
    $stmt = $koneksi->prepare(
        "SELECT b.id_booking, b.status, b.kode_booking FROM booking b
         WHERE b.id_booking = ? AND b.id_pelanggan = ?"
    );
    $stmt->bind_param('ii', $id, $id_pel);
    $stmt->execute();
    $b = $stmt->get_result()->fetch_assoc();

    if ($b && $b['status'] === 'Menunggu') {
        $upd = $koneksi->prepare("UPDATE booking SET status = 'Dibatalkan' WHERE id_booking = ?");
        $upd->bind_param('i', $id);
        $upd->execute();

        // Notif admin
        $judul_a = "❌ Booking Dibatalkan Pelanggan: " . $b['kode_booking'];
        $isi_a   = "Pelanggan " . $_SESSION['nama_pelanggan'] . " membatalkan booking " . $b['kode_booking'] . ".";
        tambah_notifikasi($koneksi, $judul_a, $isi_a, 'info', $id);

        // Notif pelanggan — konfirmasi pembatalan
        $judul_p = "❌ Booking Berhasil Dibatalkan";
        $isi_p   = "Booking " . $b['kode_booking'] . " telah berhasil dibatalkan sesuai permintaan Anda. Hubungi kami jika ada pertanyaan.";
        tambah_notifikasi_pelanggan($koneksi, $id_pel, $judul_p, $isi_p, 'info', $id);

        header('Location: pelanggan_riwayat.php?pesan=' . urlencode('Booking berhasil dibatalkan.'));
        exit;
    }
}

header('Location: pelanggan_riwayat.php?pesan=' . urlencode('Gagal membatalkan booking.'));
exit;
