<?php
/**
 * FILE: pelanggan_notifikasi.php
 * VTCC - Notifikasi Pelanggan
 */
require_once 'config/db.php';
require_once 'includes/auth_pelanggan.php';
require_once 'includes/functions.php';
require_login_pelanggan();

$page_title    = 'Notifikasi';
$active_menu_p = 'notifikasi';
$id_pel        = (int)$_SESSION['id_pelanggan'];

// Tandai semua dibaca
if (isset($_GET['mark_all'])) {
    $stmt = $koneksi->prepare("UPDATE notifikasi SET is_read = 1 WHERE id_pelanggan = ?");
    $stmt->bind_param('i', $id_pel);
    $stmt->execute();
    header('Location: pelanggan_notifikasi.php');
    exit;
}

// Tandai satu dibaca
if (isset($_GET['mark']) && is_numeric($_GET['mark'])) {
    $nid  = (int)$_GET['mark'];
    $stmt = $koneksi->prepare("UPDATE notifikasi SET is_read = 1 WHERE id_notif = ? AND id_pelanggan = ?");
    $stmt->bind_param('ii', $nid, $id_pel);
    $stmt->execute();
}

// Ambil semua notif pelanggan ini
$stmt = $koneksi->prepare(
    "SELECT * FROM notifikasi WHERE id_pelanggan = ? ORDER BY created_at DESC LIMIT 100"
);
$stmt->bind_param('i', $id_pel);
$stmt->execute();
$notif_list = $stmt->get_result();

$total_unread = hitung_notif_pelanggan($koneksi, $id_pel);

require_once 'includes/header_pelanggan.php';
?>

<div style="max-width:760px;margin:0 auto;">

  <!-- Header bar -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
    <div>
      <h2 style="font-size:22px;font-weight:800;color:var(--text-dark);margin:0;">🔔 Notifikasi Saya</h2>
      <?php if ($total_unread > 0): ?>
        <p style="color:var(--text-light);margin:4px 0 0;font-size:13px;">
          Ada <strong style="color:var(--blue-700);"><?= $total_unread ?></strong> notifikasi belum dibaca
        </p>
      <?php else: ?>
        <p style="color:var(--text-light);margin:4px 0 0;font-size:13px;">Semua notifikasi sudah dibaca</p>
      <?php endif; ?>
    </div>
    <?php if ($total_unread > 0): ?>
      <a href="pelanggan_notifikasi.php?mark_all=1" class="btn btn-outline btn-sm">✓ Tandai Semua Dibaca</a>
    <?php endif; ?>
  </div>

  <div class="card" style="padding:0;overflow:hidden;">
    <?php if ($notif_list->num_rows === 0): ?>
      <div class="empty-state" style="padding:64px 24px;">
        <div style="font-size:56px;margin-bottom:16px;">🔔</div>
        <div style="font-size:16px;font-weight:700;margin-bottom:8px;">Belum ada notifikasi</div>
        <div style="color:var(--text-light);font-size:13px;">
          Notifikasi akan muncul saat status booking atau pembayaran Anda berubah.
        </div>
      </div>
    <?php else: ?>
      <?php while ($n = $notif_list->fetch_assoc()):
        $is_read   = (bool)$n['is_read'];
        $icon_map  = [
          'booking_baru'  => ['icon' => '📋', 'bg' => '#EFF6FF', 'border' => '#BFDBFE'],
          'pembayaran'    => ['icon' => '💳', 'bg' => '#FEFCE8', 'border' => '#FDE68A'],
          'status_update' => ['icon' => '🚛', 'bg' => '#F0FDF4', 'border' => '#BBF7D0'],
          'info'          => ['icon' => 'ℹ️',  'bg' => '#F8FAFC', 'border' => '#E2E8F0'],
        ];
        $style = $icon_map[$n['tipe']] ?? $icon_map['info'];
        $link  = $n['id_booking'] ? 'pelanggan_riwayat.php' : '#';
      ?>
      <a href="<?= $link ?>"
         onclick="markRead(<?= $n['id_notif'] ?>)"
         style="display:flex;gap:16px;align-items:flex-start;padding:18px 20px;
                border-bottom:1px solid var(--gray);text-decoration:none;color:inherit;
                background:<?= $is_read ? 'transparent' : 'var(--blue-50)' ?>;
                transition:background 0.15s;"
         onmouseover="this.style.background='var(--light-gray)'"
         onmouseout="this.style.background='<?= $is_read ? 'transparent' : 'var(--blue-50)' ?>'">

        <!-- Icon bubble -->
        <div style="width:44px;height:44px;border-radius:12px;flex-shrink:0;
                    background:<?= $style['bg'] ?>;border:1.5px solid <?= $style['border'] ?>;
                    display:flex;align-items:center;justify-content:center;font-size:20px;">
          <?= $style['icon'] ?>
        </div>

        <!-- Konten -->
        <div style="flex:1;min-width:0;">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
            <div style="font-weight:<?= $is_read ? '500' : '700' ?>;font-size:14px;color:var(--text-dark);
                        line-height:1.4;">
              <?= clean($n['judul']) ?>
            </div>
            <?php if (!$is_read): ?>
              <span style="width:8px;height:8px;border-radius:50%;background:var(--blue-600);
                           flex-shrink:0;margin-top:5px;"></span>
            <?php endif; ?>
          </div>
          <div style="font-size:13px;color:var(--text-mid);margin:5px 0;line-height:1.6;">
            <?= clean($n['isi']) ?>
          </div>
          <div style="font-size:11.5px;color:var(--text-light);display:flex;align-items:center;gap:6px;">
            🕐 <?= date('d M Y, H:i', strtotime($n['created_at'])) ?> WIB
            <?php if (!$is_read): ?>
              <span style="background:var(--blue-100);color:var(--blue-700);border-radius:8px;
                           padding:1px 8px;font-size:10px;font-weight:700;">BARU</span>
            <?php endif; ?>
          </div>
        </div>
      </a>
      <?php endwhile; ?>
    <?php endif; ?>
  </div>

</div>

<script>
function markRead(id) {
    fetch('pelanggan_notifikasi.php?mark=' + id);
}
</script>

<?php require_once 'includes/footer_pelanggan.php'; ?>
