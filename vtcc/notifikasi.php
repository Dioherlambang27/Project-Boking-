<?php
/**
 * FILE: notifikasi.php
 * VTCC - Notifikasi Admin
 */
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_login();

$page_title   = 'Notifikasi';
$page_heading = '🔔 Notifikasi';
$page_subtitle = 'Pemberitahuan booking dan pembayaran terbaru';
$active_menu  = 'notifikasi';

// Tandai semua sebagai sudah dibaca
if (isset($_GET['mark_all'])) {
    $koneksi->query("UPDATE notifikasi SET is_read=1");
    header('Location: notifikasi.php?pesan=Semua+notifikasi+telah+ditandai+dibaca');
    exit;
}
if (isset($_GET['mark'])) {
    $nid = (int)$_GET['mark'];
    $koneksi->query("UPDATE notifikasi SET is_read=1 WHERE id_notif={$nid}");
}

$pesan = clean($_GET['pesan'] ?? '');
$notif_list = $koneksi->query(
    "SELECT * FROM notifikasi ORDER BY created_at DESC LIMIT 100"
);

require_once 'includes/header.php';
?>
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<?php if ($pesan): ?>
  <div class="alert alert-success alert-auto"><?= $pesan ?></div>
<?php endif; ?>

<div class="action-bar" style="margin-bottom:16px;">
  <div></div>
  <a href="notifikasi.php?mark_all=1" class="btn btn-outline btn-sm">✓ Tandai Semua Dibaca</a>
</div>

<div class="card">
  <div class="card-title">🔔 Semua Notifikasi</div>
  <?php if ($notif_list->num_rows === 0): ?>
    <div class="empty-state" style="padding:48px;">
      <div style="font-size:48px;margin-bottom:12px;">🔔</div>
      <div style="font-weight:600;">Belum ada notifikasi</div>
    </div>
  <?php else: while ($n = $notif_list->fetch_assoc()): ?>
    <a href="<?= $n['id_booking'] ? 'booking_detail.php?id='.$n['id_booking'] : '#' ?>&from_notif=1"
       onclick="markRead(<?= $n['id_notif'] ?>)"
       style="display:flex;gap:16px;align-items:flex-start;padding:16px;border-bottom:1px solid var(--gray);
              background:<?= $n['is_read'] ? 'transparent' : 'var(--blue-50)' ?>;
              text-decoration:none;color:inherit;transition:background 0.15s;"
       onmouseover="this.style.background='var(--light-gray)'"
       onmouseout="this.style.background='<?= $n['is_read'] ? 'transparent' : 'var(--blue-50)' ?>'">
      <div style="font-size:28px;flex-shrink:0;line-height:1;">
        <?= $n['tipe'] === 'booking_baru' ? '📋' : ($n['tipe'] === 'pembayaran' ? '💳' : 'ℹ️') ?>
      </div>
      <div style="flex:1;">
        <div style="font-weight:<?= $n['is_read'] ? '500' : '700' ?>;font-size:14px;margin-bottom:4px;">
          <?= clean($n['judul']) ?>
          <?php if (!$n['is_read']): ?>
            <span class="badge badge-blue" style="font-size:10px;padding:2px 7px;">Baru</span>
          <?php endif; ?>
        </div>
        <div style="font-size:13px;color:var(--text-mid);line-height:1.5;"><?= clean($n['isi']) ?></div>
        <div style="font-size:11px;color:var(--text-light);margin-top:6px;">
          🕐 <?= date('d M Y, H:i', strtotime($n['created_at'])) ?> WIB
        </div>
      </div>
      <?php if (!$n['is_read']): ?>
        <div style="width:10px;height:10px;background:var(--blue-600);border-radius:50%;flex-shrink:0;margin-top:6px;"></div>
      <?php endif; ?>
    </a>
  <?php endwhile; endif; ?>
</div>

<script>
function markRead(id) {
  fetch('notifikasi.php?mark=' + id);
}
</script>

<?php require_once 'includes/footer.php'; ?>
