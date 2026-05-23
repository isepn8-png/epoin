<?php 
include '../koneksi.php';
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['id'])) {
    header("location:../login.php");
    exit;
}

// Ambil data dari form
$nama_kelas  = $_POST['nama'];
$tingkatan  = $_POST['tingkatan'];
$ta  = $_POST['ta'];

// Simpan data kelas ke tabel 'kelas'
mysqli_query($koneksi, "INSERT INTO kelas (kelas_nama, kelas_tingkatan, kelas_ta) VALUES ('$nama_kelas', '$tingkatan', '$ta')");

// Redirect kembali ke halaman data kelas
header("location:kelas.php");
?>