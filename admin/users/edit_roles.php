<?php
require_once __DIR__ . '/../../includes/auth.php';
ensure_logged_in();
if (!user_has_any_role(['administrator','superadmin'])) { http_response_code(403); exit('Akses ditolak'); }

$uid = isset($_GET['uid'])?(int)$_GET['uid']:0;
$u = mysqli_query($koneksi,"SELECT * FROM user WHERE user_id=$uid"); $user=mysqli_fetch_assoc($u);
if(!$user){ exit('User tidak ditemukan'); }

$all = mysqli_query($koneksi,"SELECT role_id, role_key, role_name FROM roles ORDER BY role_id");
$mineQ = mysqli_query($koneksi,"SELECT role_id FROM user_roles WHERE user_id=$uid");
$mine = []; while($r=mysqli_fetch_assoc($mineQ)) $mine[]=(int)$r['role_id'];

if($_SERVER['REQUEST_METHOD']==='POST'){
  $selected = array_map('intval', $_POST['roles'] ?? []);
  mysqli_query($koneksi,"DELETE FROM user_roles WHERE user_id=$uid");
  foreach($selected as $rid){
    mysqli_query($koneksi,"INSERT INTO user_roles (user_id, role_id) VALUES ($uid, $rid)");
  }
  header("Location: index.php"); exit;
}
?>
<!doctype html><meta charset="utf-8"><title>Kelola Role</title>
<h3>Kelola Role: <?=htmlspecialchars($user['user_nama'])?></h3>
<form method="post">
  <?php while($r=mysqli_fetch_assoc($all)): ?>
    <label style="display:block;margin:4px 0">
      <input type="checkbox" name="roles[]" value="<?=$r['role_id']?>" <?= in_array((int)$r['role_id'],$mine,true) ? 'checked' : '' ?>>
      <?=$r['role_name']?> (<?=$r['role_key']?>)
    </label>
  <?php endwhile; ?>
  <button type="submit">Simpan</button>
  <a href="index.php">Batal</a>
</form>
