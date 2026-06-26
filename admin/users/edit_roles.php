<?php
require_once __DIR__ . '/../../includes/auth.php';            // $koneksi + RBAC helpers
require_once __DIR__ . '/../../includes/epoin_security.php';  // CSRF helpers
ensure_logged_in();
// [SECURITY H-1/C-1] Kelola role = SUPERADMIN-ONLY. Mencegah administrator non-super
// meng-assign role (termasuk superadmin) ke user mana pun → eskalasi privilege.
if (!epoin_is_superadmin_session()) { http_response_code(403); exit('Akses ditolak: khusus Super Admin'); }

$uid = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;

// ambil user — prepared
$stmt = $koneksi->prepare("SELECT user_id, user_nama FROM user WHERE user_id=? LIMIT 1");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) { exit('User tidak ditemukan'); }

// semua role (tanpa parameter)
$all = mysqli_query($koneksi, "SELECT role_id, role_key, role_name FROM roles ORDER BY role_id");

// role milik user — prepared
$mine = [];
$stmt = $koneksi->prepare("SELECT role_id FROM user_roles WHERE user_id=?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$resMine = $stmt->get_result();
while ($r = $resMine->fetch_assoc()) { $mine[] = (int)$r['role_id']; }
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!epoin_csrf_validate()) { http_response_code(419); exit('CSRF token invalid'); }

  $selected = array_values(array_filter(array_map('intval', $_POST['roles'] ?? []), function ($v) { return $v > 0; }));

  // refresh user_roles — prepared
  $del = $koneksi->prepare("DELETE FROM user_roles WHERE user_id=?");
  $del->bind_param("i", $uid);
  $del->execute();
  $del->close();

  if ($selected) {
    $ins = $koneksi->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?,?)");
    foreach ($selected as $rid) {
      $ins->bind_param("ii", $uid, $rid);
      $ins->execute();
    }
    $ins->close();
  }

  header("Location: index.php");
  exit;
}
?>
<!doctype html><meta charset="utf-8"><title>Kelola Role</title>
<h3>Kelola Role: <?= htmlspecialchars($user['user_nama']) ?></h3>
<form method="post">
  <?= epoin_csrf_field() ?>
  <?php while ($r = mysqli_fetch_assoc($all)): ?>
    <label style="display:block;margin:4px 0">
      <input type="checkbox" name="roles[]" value="<?= (int)$r['role_id'] ?>" <?= in_array((int)$r['role_id'], $mine, true) ? 'checked' : '' ?>>
      <?= htmlspecialchars($r['role_name']) ?> (<?= htmlspecialchars($r['role_key']) ?>)
    </label>
  <?php endwhile; ?>
  <button type="submit">Simpan</button>
  <a href="index.php">Batal</a>
</form>
