<?php
include '../koneksi.php';
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['id'])) {
    header("location:../login.php");
    exit;
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
?>
