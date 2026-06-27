<?php
session_start();
require_once __DIR__ . '/../includes/epoin_security.php';
epoin_staff_guard(true);
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('user_tambah.php');
}
include '../koneksi.php';
require_once __DIR__ . '/../includes/auth.php';   // [M-1] sediakan epoin_rbac_bump_version()

// ----------------------------------------------------
// Konfigurasi umum
// ----------------------------------------------------
$MAX_FOTO = 2 * 1024 * 1024; // 2MB
$ALLOWED_EXT = ['jpg','jpeg','png'];
$REDIRECT_DEFAULT = 'manajemen_pengguna.php';

// Pastikan koneksi melempar exception (jika tersedia)
if (function_exists('mysqli_report')) {
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}
if (isset($koneksi) && $koneksi instanceof mysqli) {
  @$koneksi->set_charset('utf8mb4');
}

// ----------------------------------------------------
// Ambil input
// ----------------------------------------------------
$nama        = isset($_POST['nama']) ? trim($_POST['nama']) : '';
$username    = isset($_POST['username']) ? trim($_POST['username']) : '';
$password    = isset($_POST['password']) ? (string)$_POST['password'] : '';
$rolesPost   = isset($_POST['roles']) && is_array($_POST['roles']) ? $_POST['roles'] : [];
$resetDef    = isset($_POST['reset_default']) ? (int)$_POST['reset_default'] : 0;
$redirect_to = isset($_POST['redirect_to']) && $_POST['redirect_to'] !== '' ? $_POST['redirect_to'] : $REDIRECT_DEFAULT;

// Legacy level dari form (sudah dihitung di sisi Add Form)
// Akan dipakai fallback bila gagal baca dari tabel roles
$legacy_from_form = isset($_POST['level']) ? trim(strtolower($_POST['level'])) : '';

// Validasi dasar
if ($nama === '' || $username === '') {
  exit('Input tidak lengkap: nama/username wajib diisi.');
}
if (empty($rolesPost)) {
  exit('Input tidak lengkap: minimal pilih satu peran (role).');
}

// Password baru:
// - Jika tombol reset default ditekan -> pakai '12345678'
// - Jika field password ada -> pakai nilai itu
// Catatan: di halaman Tambah, field password sudah required
if ($resetDef === 1) {
  $password = '12345678';
}
if (strlen($password) < 5) {
  exit('Password minimal 5 karakter.');
}

// Hash password (kompatibel lama). Rekomendasi: migrasi ke password_hash() di sistem login.
$pwHash = password_hash($password, PASSWORD_DEFAULT);

try {
  // ----------------------------------------------------
  // Validasi username unik
  // ----------------------------------------------------
  $stmt = $koneksi->prepare("SELECT COUNT(*) AS c FROM user WHERE user_username=?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $exists = (int)$stmt->get_result()->fetch_assoc()['c'];
  if ($exists > 0) {
    exit('Username sudah dipakai. Gunakan username lain.');
  }

  // ----------------------------------------------------
  // Mulai transaksi
  // ----------------------------------------------------
  $koneksi->begin_transaction();

  // ----------------------------------------------------
  // Tentukan legacy user_level dari roles (prioritas)
  // ----------------------------------------------------
  $roleIds = array_map('intval', $rolesPost);
  $roleIds = array_values(array_filter($roleIds, function($v){ return $v > 0; })); // hanya ID valid
  $legacyLevel = 'user';

  if (!empty($roleIds)) {
    // Ambil role_key dari tabel roles
    $in = implode(',', $roleIds);
    $rs = $koneksi->query("SELECT role_key FROM roles WHERE role_id IN ($in)");
    $keys = [];
    while($r = $rs->fetch_assoc()){
      $keys[] = strtolower($r['role_key']);
    }
    // Urutan prioritas
    $prio = ['administrator','guru','tas','sekretaris'];
    $legacyLevel = $legacy_from_form ?: 'user';
    foreach ($prio as $p) {
      if (in_array($p, $keys, true)) { $legacyLevel = $p; break; }
    }
    if ($legacyLevel === 'user' && !empty($keys)) {
      $legacyLevel = $keys[0];
    }
  } else {
    // Fallback ke level dari form bila tidak ada role_id valid
    $legacyLevel = $legacy_from_form ?: 'user';
  }

  // ----------------------------------------------------
  // INSERT user (sementara tanpa foto, nanti diupdate jika upload ada)
  // ----------------------------------------------------
  $stmt = $koneksi->prepare("INSERT INTO user (user_nama, user_username, user_password, user_level, user_foto) VALUES (?,?,?,?,?)");
  $emptyFoto = '';
  $stmt->bind_param("sssss", $nama, $username, $pwHash, $legacyLevel, $emptyFoto);
  $stmt->execute();
  $newUserId = (int)$koneksi->insert_id;

  // ----------------------------------------------------
  // Handle upload foto (opsional)
  // ----------------------------------------------------
  $fotoNameFinal = '';
  if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
      throw new Exception('Upload foto gagal. Kode error: '.$_FILES['foto']['error']);
    }
    $tmp  = $_FILES['foto']['tmp_name'];
    $size = (int)$_FILES['foto']['size'];
    if ($size > $MAX_FOTO) {
      throw new Exception('Ukuran foto maksimal 2MB.');
    }

    // Validasi tipe mime
    $mime = '';
    if (function_exists('mime_content_type')) {
      $mime = @mime_content_type($tmp);
    }
    if (!$mime) {
      // fallback via getimagesize
      $imgInfo = @getimagesize($tmp);
      if ($imgInfo && isset($imgInfo['mime'])) $mime = $imgInfo['mime'];
    }
    if (!$mime || strpos($mime,'image/') !== 0) {
      throw new Exception('File foto tidak valid (bukan gambar).');
    }

    // Ekstensi aman
    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $ALLOWED_EXT, true)) {
      throw new Exception('Format foto harus JPG/PNG.');
    }

    // Siapkan direktori
    $uploadDir = realpath(__DIR__ . '/../gambar/user');
    if (!$uploadDir) {
      $uploadDir = __DIR__ . '/../gambar/user';
      if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
    }

    // Nama file unik dengan ID user
    $fotoNameFinal = 'user_'.$newUserId.'_'.time().'.'.$ext;
    if (!@move_uploaded_file($tmp, $uploadDir.'/'.$fotoNameFinal)) {
      throw new Exception('Gagal menyimpan file foto.');
    }

    // Update kolom foto
    $stmt = $koneksi->prepare("UPDATE user SET user_foto=? WHERE user_id=?");
    $stmt->bind_param("si", $fotoNameFinal, $newUserId);
    $stmt->execute();
  }

  // ----------------------------------------------------
  // Isi tabel pivot user_roles
  // ----------------------------------------------------
  if (!empty($roleIds)) {
    $ins = $koneksi->prepare("INSERT INTO user_roles (user_id, role_id) VALUES(?,?)");
    foreach ($roleIds as $rid) {
      $ins->bind_param("ii", $newUserId, $rid);
      $ins->execute();
    }
    // [M-1] user baru dgn role → invalidasi cache perms global (konsistensi; user ini login fresh).
    if (function_exists('epoin_rbac_bump_version')) { epoin_rbac_bump_version(); }
  }
  // Catatan: jika tidak ada role_id valid (misal fallback tanpa tabel roles),
  // lewati pengisian user_roles agar tidak error constraint.

  // ----------------------------------------------------
  // Commit dan redirect
  // ----------------------------------------------------
  $koneksi->commit();

  // Tambahkan pesan sukses
  $to = $redirect_to ?: $REDIRECT_DEFAULT;
  if (strpos($to, '?') === false) { $to .= '?msg=created'; }
  else { $to .= '&msg=created'; }

  header('Location: '.$to);
  exit;

} catch (Throwable $e) {
  if ($koneksi && $koneksi->errno === 0) { /* noop */ }
  // rollback jika transaksi masih berjalan
  if ($koneksi && method_exists($koneksi, 'rollback')) {
    @$koneksi->rollback();
  }
  // Tampilkan pesan error sederhana
  http_response_code(500);
  echo "Terjadi kesalahan saat menyimpan pengguna: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
  exit;
}
