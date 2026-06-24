<?php
// admin/user_update.php — handler EDIT pengguna.
// KEAMANAN: admin-only + wajib POST + CSRF + prepared statement.
// Sebelumnya file ini TANPA auth & TANPA CSRF → siapa pun bisa mengambil alih akun.
require_once __DIR__ . '/../includes/epoin_security.php';
epoin_staff_guard(true);                 // admin-only (tutup account takeover)
epoin_require_post();                     // hanya menerima POST
if (!epoin_csrf_validate()) {             // validasi CSRF (tanpa argumen)
    epoin_csrf_fail_redirect('manajemen_pengguna.php');
}
include '../koneksi.php';

$id         = (int)($_POST['id'] ?? 0);
$nama       = trim($_POST['nama'] ?? '');
$username   = trim($_POST['username'] ?? '');
$password   = $_POST['password'] ?? '';
$roles      = $_POST['roles'] ?? []; // array of role_id
$removeFoto = (int)($_POST['remove_foto'] ?? 0);
$oldFoto    = basename(trim($_POST['old_foto'] ?? '')); // basename: cegah path traversal
$resetDef   = (int)($_POST['reset_default'] ?? 0);
$redirect   = trim($_POST['redirect_to'] ?? 'manajemen_pengguna.php');
// cegah open-redirect ke domain luar
if ($redirect === '' || preg_match('#^(https?:)?//#i', $redirect)) {
  $redirect = 'manajemen_pengguna.php';
}

if (!$id || $nama === '' || $username === '' || empty($roles)) {
  die("Input tidak lengkap.");
}

// validasi username unik (kecuali dirinya) — prepared
$stmt = $koneksi->prepare("SELECT COUNT(*) c FROM user WHERE user_username=? AND user_id<>?");
$stmt->bind_param("si", $username, $id);
$stmt->execute();
$exists = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();
if ($exists) { die("Username sudah dipakai."); }

// ===== handle foto =====
$newFotoName = $oldFoto;
$uploadDir = realpath(__DIR__ . '/../gambar/user');
if (!$uploadDir) { $uploadDir = __DIR__ . '/../gambar/user'; @mkdir($uploadDir, 0775, true); }

if ($removeFoto === 1) {
  if ($oldFoto && file_exists("$uploadDir/$oldFoto")) @unlink("$uploadDir/$oldFoto");
  $newFotoName = '';
} else if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
  $f = $_FILES['foto'];
  $mime = @mime_content_type($f['tmp_name']);
  if (strpos((string)$mime, 'image/') !== 0) { die("File foto tidak valid."); }
  if ($f['size'] > 2 * 1024 * 1024) { die("Ukuran foto maksimal 2MB."); }
  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) { die("Format foto harus JPG/PNG."); }
  $newFotoName = 'user_' . $id . '_' . time() . '.' . $ext;
  move_uploaded_file($f['tmp_name'], "$uploadDir/$newFotoName");
  if ($oldFoto && file_exists("$uploadDir/$oldFoto")) @unlink("$uploadDir/$oldFoto");
}

// ===== password: reset default / manual / kosong = tidak diubah =====
// (Hash MD5 dipertahankan utk kompatibilitas login lama; migrasi bcrypt = Tahap 2)
$pwHash = null;
if ($resetDef === 1) {
  $pwHash = md5('12345678');
} else if (strlen($password) >= 5) {
  $pwHash = md5($password);
}

// ===== legacy user_level dari role utama (perilaku DIPERTAHANKAN) — prepared =====
$ids = array_values(array_filter(array_map('intval', $roles), function ($v) { return $v > 0; }));
if (empty($ids)) { die("Role tidak valid."); }
$place = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));
$stmt  = $koneksi->prepare(
  "SELECT role_key FROM roles WHERE role_id IN ($place)
   ORDER BY FIELD(role_key,'administrator','guru','tas','sekretaris') DESC, role_key ASC"
);
$stmt->bind_param($types, ...$ids);
$stmt->execute();
$rs = $stmt->get_result();
$roleKeys = [];
while ($r = $rs->fetch_assoc()) { $roleKeys[] = $r['role_key']; }
$stmt->close();
$legacyLevel = $roleKeys ? $roleKeys[0] : 'user';

// ===== UPDATE user — prepared (password opsional) =====
if ($pwHash !== null) {
  $stmt = $koneksi->prepare("UPDATE user SET user_nama=?, user_username=?, user_foto=?, user_level=?, user_password=? WHERE user_id=?");
  $stmt->bind_param("sssssi", $nama, $username, $newFotoName, $legacyLevel, $pwHash, $id);
} else {
  $stmt = $koneksi->prepare("UPDATE user SET user_nama=?, user_username=?, user_foto=?, user_level=? WHERE user_id=?");
  $stmt->bind_param("ssssi", $nama, $username, $newFotoName, $legacyLevel, $id);
}
$stmt->execute();
$stmt->close();

// ===== refresh user_roles — prepared =====
$del = $koneksi->prepare("DELETE FROM user_roles WHERE user_id=?");
$del->bind_param("i", $id);
$del->execute();
$del->close();
$ins = $koneksi->prepare("INSERT INTO user_roles(user_id, role_id) VALUES(?,?)");
foreach ($ids as $rid) {
  $ins->bind_param("ii", $id, $rid);
  $ins->execute();
}
$ins->close();

header("Location: " . $redirect . "?msg=updated");
exit;
