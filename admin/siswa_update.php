<?php
// admin/siswa_update.php
// Sinkron dengan UI baru siswa_edit.php: dukung CSRF, upload foto 2MB (JPG/PNG/GIF),
// update dinamis (nama/nis/jurusan/status), opsi ganti sandi (termasuk reset ke "12345678"),
// dan hapus foto lama jika ada foto baru.

// ---- Bootstrap ----
session_start();
if (!isset($_SESSION['_csrf'], $_POST['_csrf']) || !hash_equals($_SESSION['_csrf'], $_POST['_csrf'])) {
  http_response_code(400);
  exit('CSRF token tidak valid.');
}

include '../koneksi.php';

// ---- Ambil & validasi input ----
$id       = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$nama     = trim($_POST['nama'] ?? '');
$nis      = trim($_POST['nis'] ?? '');
$jurusan  = isset($_POST['jurusan']) ? (int)$_POST['jurusan'] : 0;
$status   = strtolower(trim($_POST['status'] ?? 'aktif'));
$pwdRaw   = trim($_POST['password'] ?? ''); // kosong = tidak ganti

// Normalisasi & validasi ringan server-side
$allowedStatus = ['aktif','tamat','pindah','dikeluarkan'];
if (!in_array($status, $allowedStatus, true)) { $status = 'aktif'; }

// Pastikan NIS hanya angka (tetap sebagai string agar leading zero aman)
if ($nis === '' || !preg_match('/^\d+$/', $nis)) {
  // fallback: ambil hanya digit (jaga-jaga jika ada spasi/karakter lain)
  $nis = preg_replace('/\D+/', '', $nis ?? '');
}

if ($id <= 0 || $nama === '' || $nis === '' || $jurusan <= 0) {
  header('Location: siswa.php?err=invalid');
  exit;
}

// ---- Ambil data lama (khususnya foto lama) ----
$fotoLama = null;
if ($stmt0 = mysqli_prepare($koneksi, "SELECT siswa_foto FROM siswa WHERE siswa_id=?")) {
  mysqli_stmt_bind_param($stmt0, "i", $id);
  mysqli_stmt_execute($stmt0);
  mysqli_stmt_bind_result($stmt0, $fotoLama);
  mysqli_stmt_fetch($stmt0);
  mysqli_stmt_close($stmt0);
}

// ---- Upload foto baru (opsional) ----
$fotoBaru = null;
if (isset($_FILES['foto']) && is_uploaded_file($_FILES['foto']['tmp_name'])) {
  $name = $_FILES['foto']['name'] ?? '';
  $tmp  = $_FILES['foto']['tmp_name'] ?? '';
  $size = (int)($_FILES['foto']['size'] ?? 0);

  $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  $allowedExt = ['jpg','jpeg','png','gif'];

  // Validasi mime + ukuran + ext + benar2 image
  $mime   = @mime_content_type($tmp);
  $okMime = is_string($mime) && preg_match('#^image/(jpe?g|png|gif)$#i', $mime);
  $okExt  = in_array($ext, $allowedExt, true);
  $okSize = ($size > 0 && $size <= 2*1024*1024);
  $okImg  = @getimagesize($tmp) ? true : false;

  if ($okExt && $okSize && $okMime && $okImg) {
    // Nama file aman & unik
    $slugName = preg_replace('/[^A-Za-z0-9_\.-]/', '_', pathinfo($name, PATHINFO_FILENAME));
    $newName  = sprintf('%s_%s.%s', date('YmdHis'), bin2hex(random_bytes(4)), $ext);
    $dir      = "../gambar/siswa";
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }

    if (@move_uploaded_file($tmp, "$dir/$newName")) {
      @chmod("$dir/$newName", 0644);
      $fotoBaru = $newName;
    }
  }
}

if (move_uploaded_file($_FILES['file']['tmp_name'], $abs_path)) {
  usage_log_file_uploaded($koneksi, $SEKOLAH_ID, $abs_path, 'upload dokumen');
}


// ---- Build UPDATE dinamis (prepared statement) ----
$sets   = "siswa_nama=?, siswa_nis=?, siswa_jurusan=?, siswa_status=?";
$params = [$nama, $nis, $jurusan, $status];
$types  = "ssis"; // s(nama) s(nis) i(jurusan) s(status)

if ($pwdRaw !== '') {
  // Catatan: tetap gunakan md5 agar konsisten dengan data lama (sesuai skrip original)
  $sets     .= ", siswa_password=?";
  $params[]  = md5($pwdRaw);
  $types    .= "s";
}
if ($fotoBaru) {
  $sets     .= ", siswa_foto=?";
  $params[]  = $fotoBaru;
  $types    .= "s";
}

$sql = "UPDATE siswa SET $sets WHERE siswa_id=?";
$params[] = $id;
$types   .= "i";

$stmt = mysqli_prepare($koneksi, $sql);
if ($stmt === false) {
  header('Location: siswa.php?err=stmt');
  exit;
}

mysqli_stmt_bind_param($stmt, $types, ...$params);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// ---- Hapus file lama jika update OK & ada foto baru ----
if ($ok && $fotoBaru && $fotoLama) {
  $oldPath = "../gambar/siswa/".$fotoLama;
  if (is_file($oldPath)) { @unlink($oldPath); }
}

// ---- Redirect selesai ----
header("Location: siswa.php?updated=1");
exit;
