<?php
require_once __DIR__ . '/../../includes/auth.php';
ensure_logged_in();
if (!user_has_any_role(['administrator','superadmin'])) { http_response_code(403); exit('Akses ditolak'); }

$users = mysqli_query($koneksi, "
  SELECT u.user_id, u.user_nama, u.user_username,
         GROUP_CONCAT(r.role_key ORDER BY r.role_key SEPARATOR ', ') AS roles
  FROM user u
  LEFT JOIN user_roles ur ON ur.user_id = u.user_id
  LEFT JOIN roles r ON r.role_id = ur.role_id
  GROUP BY u.user_id
  ORDER BY u.user_nama
");
?>
<!doctype html><meta charset="utf-8"><title>Pengaturan Pengguna</title>
<h2>Pengaturan Pengguna</h2>
<table border="1" cellpadding="6">
<tr><th>Nama</th><th>Username</th><th>Roles</th><th>Aksi</th></tr>
<?php while($u=mysqli_fetch_assoc($users)): ?>
<tr>
  <td><?=htmlspecialchars($u['user_nama'])?></td>
  <td><?=htmlspecialchars($u['user_username'])?></td>
  <td><?=htmlspecialchars($u['roles'] ?: '-')?></td>
  <td><a href="edit_roles.php?uid=<?=$u['user_id']?>">Kelola Role</a></td>
</tr>
<?php endwhile; ?>
</table>
<p><a href="../">Kembali</a></p>
