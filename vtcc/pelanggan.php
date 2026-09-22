<?php
/**
 * FILE: pelanggan.php
 * VTCC - Manajemen Pelanggan (Admin)
 */
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_login();

$page_title   = 'Data Pelanggan';
$page_heading = '👥 Data Pelanggan';
$page_subtitle = 'Kelola data pelanggan VTCC';
$active_menu  = 'pelanggan';

$search = clean($_GET['q'] ?? '');
$where  = '1=1'; $types = ''; $params = [];

if ($search) {
    $where = "(nama_pelanggan LIKE ? OR email LIKE ? OR no_hp LIKE ?)";
    $s = "%$search%"; $params = [$s,$s,$s]; $types = 'sss';
}

$stmt = $koneksi->prepare("SELECT * FROM pelanggan WHERE {$where} ORDER BY nama_pelanggan");
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$pesan = clean($_GET['pesan'] ?? '');
require_once 'includes/header.php';
?>
<button class="hamburger" onclick="document.querySelector('.sidebar').classList.toggle('open');document.querySelector('.sidebar-overlay').classList.toggle('show')">☰</button>
<div class="sidebar-overlay"></div>

<?php if ($pesan): ?><div class="alert alert-success alert-auto">✅ <?= $pesan ?></div><?php endif; ?>

<div class="card">
  <div class="action-bar">
    <form method="get" style="display:flex;gap:10px;">
      <input type="text" class="search-input" name="q" placeholder="Cari nama, email, no HP..." value="<?= $search ?>">
      <button type="submit" class="btn btn-outline btn-sm">Cari</button>
      <?php if ($search): ?><a href="pelanggan.php" class="btn btn-sm" style="background:var(--light-gray);color:var(--text-mid);">Reset</a><?php endif; ?>
    </form>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>#</th><th>Nama</th><th>Email</th><th>No. HP</th><th>Alamat</th>
        <th>Akun</th><th>Bergabung</th><th>Total Booking</th>
      </tr></thead>
      <tbody>
      <?php $no=1; while ($p = $result->fetch_assoc()):
        $stmt2 = $koneksi->prepare("SELECT COUNT(*) c FROM booking WHERE id_pelanggan=?");
        $stmt2->bind_param('i', $p['id_pelanggan']); $stmt2->execute();
        $total_bkg = $stmt2->get_result()->fetch_assoc()['c'];
      ?>
        <tr>
          <td><?= $no++ ?></td>
          <td><strong><?= clean($p['nama_pelanggan']) ?></strong></td>
          <td><?= clean($p['email']) ?></td>
          <td><?= clean($p['no_hp']) ?></td>
          <td style="max-width:160px;font-size:12px;"><?= clean($p['alamat'] ?: '-') ?></td>
          <td><?= $p['password'] ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-warning">Belum Daftar</span>' ?></td>
          <td><small class="text-muted"><?= date('d/m/Y', strtotime($p['created_at'])) ?></small></td>
          <td><strong><?= $total_bkg ?></strong> booking</td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
