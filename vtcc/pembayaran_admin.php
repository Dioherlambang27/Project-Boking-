<?php
/**
 * FILE: pembayaran_admin.php
 * VTCC - Verifikasi Pembayaran Admin
 */
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_login();

$page_title   = 'Verifikasi Pembayaran';
$page_heading = '💳 Verifikasi Pembayaran';
$page_subtitle = 'Cek dan verifikasi pembayaran pelanggan';
$active_menu  = 'pembayaran';

$pesan = '';
$error = '';

// Handle verifikasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id_pem  = (int)($_POST['id_pembayaran'] ?? 0);
    $action  = clean($_POST['action']);
    $catatan = clean($_POST['catatan_admin'] ?? '');

    if ($id_pem && in_array($action, ['lunas','tolak'])) {
        // Ambil id_booking dulu
        $getPem = $koneksi->prepare("SELECT id_booking FROM pembayaran WHERE id_pembayaran = ?");
        $getPem->bind_param('i', $id_pem); $getPem->execute();
        $pem    = $getPem->get_result()->fetch_assoc();

        if ($pem) {
            $id_booking  = $pem['id_booking'];
            $new_status  = $action === 'lunas' ? 'Lunas' : 'Ditolak';
            $now         = date('Y-m-d H:i:s');

            $updPem = $koneksi->prepare(
                "UPDATE pembayaran SET status_bayar=?, catatan_admin=?, verified_at=? WHERE id_pembayaran=?"
            );
            $updPem->bind_param('sssi', $new_status, $catatan, $now, $id_pem);
            $updPem->execute();

            if ($action === 'lunas') {
                // Auto update status booking → Diproses
                $updBkg = $koneksi->prepare("UPDATE booking SET status='Diproses' WHERE id_booking=?");
                $updBkg->bind_param('i', $id_booking); $updBkg->execute();

                // Ambil info booking untuk notif
                $infoBkg = $koneksi->prepare(
                    "SELECT b.kode_booking, p.nama_pelanggan FROM booking b
                     JOIN pelanggan p ON p.id_pelanggan = b.id_pelanggan WHERE b.id_booking = ?"
                );
                $infoBkg->bind_param('i', $id_booking); $infoBkg->execute();
                $bkgRow = $infoBkg->get_result()->fetch_assoc();

                // Notif admin (log)
                $judul = "✅ Pembayaran Terverifikasi: " . ($bkgRow['kode_booking'] ?? '');
                $isi   = "Pembayaran dari {$bkgRow['nama_pelanggan']} telah diverifikasi LUNAS. Status booking otomatis berubah ke DIPROSES.";
                tambah_notifikasi($koneksi, $judul, $isi, 'pembayaran', $id_booking);

                // Notif PELANGGAN — pembayaran diterima
                $id_pel_bkg = null;
                $infoP = $koneksi->prepare("SELECT id_pelanggan FROM booking WHERE id_booking=?");
                $infoP->bind_param('i', $id_booking); $infoP->execute();
                $rowP  = $infoP->get_result()->fetch_assoc();
                if ($rowP) $id_pel_bkg = $rowP['id_pelanggan'];

                if ($id_pel_bkg) {
                    $judul_p = "✅ Pembayaran Diterima!";
                    $isi_p   = "Pembayaran Anda untuk booking " . ($bkgRow['kode_booking'] ?? '') . " telah DIVERIFIKASI dan diterima. Status booking Anda sekarang DIPROSES — tim kami segera menghubungi Anda.";
                    tambah_notifikasi_pelanggan($koneksi, $id_pel_bkg, $judul_p, $isi_p, 'pembayaran', $id_booking);
                }
                $pesan = "✅ Pembayaran diverifikasi LUNAS. Booking otomatis DIPROSES!";
            } else {
                // Notif PELANGGAN — pembayaran ditolak
                $infoP2 = $koneksi->prepare("SELECT b.id_pelanggan, b.kode_booking FROM booking b WHERE b.id_booking=?");
                $infoP2->bind_param('i', $id_booking); $infoP2->execute();
                $rowP2  = $infoP2->get_result()->fetch_assoc();
                if ($rowP2 && $rowP2['id_pelanggan']) {
                    $judul_p2 = "❌ Pembayaran Ditolak";
                    $isi_p2   = "Maaf, pembayaran Anda untuk booking " . $rowP2['kode_booking'] . " ditolak oleh admin." .
                                ($catatan ? " Catatan: {$catatan}" : " Silakan kirim ulang bukti pembayaran yang valid.");
                    tambah_notifikasi_pelanggan($koneksi, $rowP2['id_pelanggan'], $judul_p2, $isi_p2, 'pembayaran', $id_booking);
                }
                $pesan = "❌ Pembayaran ditolak. Pelanggan perlu mengirim ulang.";
            }
        }
    }
}

// Ambil semua pembayaran
$filter = clean($_GET['filter'] ?? 'semua');
$where  = '';
if ($filter === 'menunggu') $where = "WHERE pm.status_bayar = 'Menunggu Verifikasi'";
if ($filter === 'lunas')    $where = "WHERE pm.status_bayar = 'Lunas'";
if ($filter === 'ditolak')  $where = "WHERE pm.status_bayar = 'Ditolak'";

$result = $koneksi->query(
    "SELECT pm.*, b.kode_booking, b.total_bayar, b.status AS status_booking,
            p.nama_pelanggan, p.no_hp, p.email
     FROM pembayaran pm
     JOIN booking b ON b.id_booking = pm.id_booking
     JOIN pelanggan p ON p.id_pelanggan = b.id_pelanggan
     {$where}
     ORDER BY pm.created_at DESC"
);

// Hitung badge
$cnt = $koneksi->query("SELECT status_bayar, COUNT(*) c FROM pembayaran GROUP BY status_bayar")->fetch_all(MYSQLI_ASSOC);
$cnt_map = array_column($cnt, 'c', 'status_bayar');

require_once 'includes/header.php';
?>
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<?php if ($pesan): ?>
  <div class="alert alert-success alert-auto">✅ <?= clean($pesan) ?></div>
<?php endif; ?>

<div class="action-bar" style="margin-bottom:20px;">
  <div class="gap-2">
    <?php foreach (['semua'=>'Semua','menunggu'=>'⏳ Menunggu','lunas'=>'✅ Lunas','ditolak'=>'❌ Ditolak'] as $f => $lbl): ?>
      <a href="pembayaran_admin.php?filter=<?= $f ?>"
         class="btn btn-sm <?= $filter===$f ? 'btn-primary' : 'btn-outline' ?>">
        <?= $lbl ?>
        <?php if ($f==='menunggu' && !empty($cnt_map['Menunggu Verifikasi'])): ?>
          <span style="background:#ef4444;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:4px;">
            <?= $cnt_map['Menunggu Verifikasi'] ?>
          </span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <div class="card-title">💳 Daftar Pembayaran</div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Booking</th><th>Pelanggan</th><th>Metode</th>
        <th>Jumlah</th><th>Bukti</th><th>Status</th><th>Tgl Kirim</th><th>Aksi</th>
      </tr></thead>
      <tbody>
      <?php if ($result->num_rows === 0): ?>
        <tr><td colspan="8" class="empty-state">📭 Tidak ada data pembayaran.</td></tr>
      <?php else: while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td>
            <strong style="color:var(--blue-700);"><?= clean($row['kode_booking']) ?></strong><br>
            <small class="text-muted"><?= badge_status($row['status_booking']) ?></small>
          </td>
          <td>
            <?= clean($row['nama_pelanggan']) ?><br>
            <small class="text-muted"><?= clean($row['no_hp']) ?></small>
          </td>
          <td>
            <strong><?= clean($row['metode']) ?></strong>
            <?php if ($row['bank_tujuan']): ?>
              <br><small class="text-muted"><?= clean($row['bank_tujuan']) ?></small>
            <?php endif; ?>
            <?php if ($row['nama_pengirim']): ?>
              <br><small class="text-muted">a.n. <?= clean($row['nama_pengirim']) ?></small>
            <?php endif; ?>
          </td>
          <td><strong><?= rupiah($row['jumlah_bayar']) ?></strong></td>
          <td>
            <?php if ($row['bukti_bayar']): ?>
              <a href="uploads/bukti_bayar/<?= clean($row['bukti_bayar']) ?>" target="_blank"
                 class="btn btn-outline btn-sm">📎 Lihat</a>
            <?php else: ?>
              <span class="text-muted">Cash / Tidak ada</span>
            <?php endif; ?>
          </td>
          <td><?= badge_bayar($row['status_bayar']) ?></td>
          <td><small><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></small></td>
          <td>
            <?php if ($row['status_bayar'] === 'Menunggu Verifikasi'): ?>
              <button class="btn btn-sm btn-primary"
                      onclick="showVerifModal(<?= $row['id_pembayaran'] ?>, '<?= clean($row['kode_booking']) ?>', '<?= clean($row['nama_pelanggan']) ?>')">
                🔍 Verifikasi
              </button>
            <?php elseif ($row['status_bayar'] === 'Lunas'): ?>
              <span class="badge badge-success">✓ Terverifikasi</span>
              <?php if ($row['verified_at']): ?>
                <br><small class="text-muted"><?= date('d/m/Y', strtotime($row['verified_at'])) ?></small>
              <?php endif; ?>
            <?php else: ?>
              <span class="badge badge-danger">✕ Ditolak</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Verifikasi -->
<div id="verifModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:16px;padding:32px;max-width:440px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
    <div style="font-size:20px;font-weight:800;margin-bottom:6px;">🔍 Verifikasi Pembayaran</div>
    <div id="verifInfo" style="color:var(--text-mid);margin-bottom:20px;font-size:13.5px;"></div>
    <form method="post" action="pembayaran_admin.php?filter=<?= $filter ?>">
      <input type="hidden" name="id_pembayaran" id="verifId">
      <div class="form-group">
        <label>Catatan Admin (opsional)</label>
        <textarea name="catatan_admin" rows="2" placeholder="Catatan untuk pelanggan..."></textarea>
      </div>
      <div class="gap-2" style="margin-top:16px;">
        <button type="submit" name="action" value="lunas" class="btn btn-primary">✅ Verifikasi LUNAS</button>
        <button type="submit" name="action" value="tolak" class="btn btn-danger"
                onclick="return confirm('Yakin menolak pembayaran ini?')">❌ Tolak</button>
        <button type="button" class="btn btn-outline" onclick="closeModal()">Batal</button>
      </div>
    </form>
  </div>
</div>

<script>
function showVerifModal(id, kode, pelanggan) {
  document.getElementById('verifId').value = id;
  document.getElementById('verifInfo').innerHTML =
    `Booking: <strong>${kode}</strong> &nbsp;|&nbsp; Pelanggan: <strong>${pelanggan}</strong>`;
  document.getElementById('verifModal').style.display = 'flex';
}
function closeModal() {
  document.getElementById('verifModal').style.display = 'none';
}
</script>

<?php require_once 'includes/footer.php'; ?>
