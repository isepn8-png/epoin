<?php
require_once __DIR__ . '/../includes/epoin_security.php';
include '../koneksi.php';
epoin_ensure_session();

// Cek apakah user sudah login
if (!isset($_SESSION['id'])) {
    header("location:../login.php");
    exit;
}

epoin_require_post();
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('kelas_tambah.php');
}

// Ambil data dari form
$nama_kelas = (string) ($_POST['nama'] ?? '');
$tingkatan  = (string) ($_POST['tingkatan'] ?? '');
$ta         = (string) ($_POST['ta'] ?? '');

// Simpan data kelas ke tabel 'kelas' — prepared statement (anti SQL injection)
$stmt = mysqli_prepare(
    $koneksi,
    'INSERT INTO kelas (kelas_nama, kelas_tingkatan, kelas_ta) VALUES (?, ?, ?)'
);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'sss', $nama_kelas, $tingkatan, $ta);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Redirect kembali ke halaman data kelas
header("location:kelas.php");
