<?php
// epoin/siswa/profil_update.php
include '../koneksi.php';
session_start();
if (!isset($_SESSION['level']) || $_SESSION['level'] !== 'siswa') {
  header("Location: ../index.php?alert=belum_login"); exit;
}
$id = (int)$_SESSION['id'];

// Hapus foto (opsional)
if (isset($_GET['hapus'])) {
  $row = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT siswa_foto FROM siswa WHERE siswa_id=$id"));
  if (!empty($row['siswa_foto'])) {
    $path = "../gambar/siswa/".$row['siswa_foto'];
    if (is_file($path)) @unlink($path);
    mysqli_query($koneksi,"UPDATE siswa SET siswa_foto=NULL WHERE siswa_id=$id");
  }
  header("Location: profil.php?alert=ok"); exit;
}

// Upload baru
if (!isset($_FILES['foto']) || empty($_FILES['foto']['name'])) {
  header("Location: profil.php?alert=nofile"); exit;
}

$allowed = ['jpg','jpeg','png','gif'];
$name    = $_FILES['foto']['name'];
$tmp     = $_FILES['foto']['tmp_name'];
$size    = (int)$_FILES['foto']['size'];
$ext     = strtolower(pathinfo($name, PATHINFO_EXTENSION));

if (!in_array($ext, $allowed)) { header("Location: profil.php?alert=ext"); exit; }
if ($size > 2*1024*1024)       { header("Location: profil.php?alert=size"); exit; }

// nama file aman + random prefix
$clean = preg_replace('/[^A-Za-z0-9_\.-]/','_', pathinfo($name, PATHINFO_FILENAME));
$new   = mt_rand(100000,999999).'_'.$clean.'.'.$ext;

// buat folder jika belum ada
$dir = "../gambar/siswa";
if (!is_dir($dir)) @mkdir($dir, 0755, true);

if (!move_uploaded_file($tmp, "$dir/$new")) {
  header("Location: profil.php?alert=movefail"); exit;
}

// hapus foto lama
$old = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT siswa_foto FROM siswa WHERE siswa_id=$id"));
$old = $old['siswa_foto'] ?? '';
if ($old && is_file("$dir/$old")) @unlink("$dir/$old");

// simpan DB
mysqli_query($koneksi, "UPDATE siswa SET siswa_foto='".mysqli_real_escape_string($koneksi,$new)."' WHERE siswa_id=$id");

header("Location: profil.php?alert=ok");
