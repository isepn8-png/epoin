<?php 
include '../koneksi.php';
$id = $_GET['id'];

mysqli_query($koneksi, "delete from kelas where kelas_id='$id'");
mysqli_query($koneksi, "delete from kelas_siswa where ks_kelas='$id'");
mysqli_query($koneksi, "delete from input_prestasi where kelas='$id'");
mysqli_query($koneksi, "delete from input_pelanggaran where kelas='$id'");
header("location:kelas.php");