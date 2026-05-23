<?php
// admin/siswa_act.php
include '../koneksi.php';

// Ambil data
$nama     = trim($_POST['nama'] ?? '');
$nis      = trim($_POST['nis'] ?? '');
$jurusan  = trim($_POST['jurusan'] ?? '');
$password = md5((string)($_POST['password'] ?? '')); // mengikuti pola MD5 di DB seed
$status   = trim($_POST['status'] ?? 'aktif');

// Validasi sederhana
if ($nama === '' || $nis === '' || $jurusan === '' || $password === '') {
  header("Location: siswa.php"); exit;
}

// Upload foto (opsional)
$fotoFile = null;
if (!empty($_FILES['foto']['name'])) {
  $allowed = ['jpg','jpeg','png','gif'];
  $name = $_FILES['foto']['name'];
  $tmp  = $_FILES['foto']['tmp_name'];
  $size = (int)$_FILES['foto']['size'];
  $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

  if (in_array($ext, $allowed, true) && $size <= 2*1024*1024) {
    $clean = preg_replace('/[^A-Za-z0-9_\.-]/','_', pathinfo($name, PATHINFO_FILENAME));
    $new   = mt_rand(100000,999999) . '_' . $clean . '.' . $ext;
    $dir   = "../gambar/siswa";
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    if (@move_uploaded_file($tmp, $dir . "/" . $new)) {
      $fotoFile = $new;
    }
  }
}

// Insert ke tabel siswa
$sql = "INSERT INTO siswa 
        (siswa_nama, siswa_nis, siswa_jurusan, siswa_status, siswa_password, siswa_foto, last_login, status_login)
        VALUES (?, ?, ?, ?, ?, ?, NULL, 'offline')";

$stmt = mysqli_prepare($koneksi, $sql);
if ($stmt === false) { die('DB Error (prepare).'); }

mysqli_stmt_bind_param($stmt, "ssssss",
  $nama, $nis, $jurusan, $status, $password, $fotoFile
);

mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Selesai
header("Location: siswa.php");
exit;
