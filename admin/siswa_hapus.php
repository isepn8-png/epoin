<?php 
include '../koneksi.php';
$id = $_GET['id'];

mysqli_query($koneksi, "delete from siswa where siswa_id='$id'");
mysqli_query($koneksi, "delete from input_prestasi where siswa='$id'");
mysqli_query($koneksi, "delete from input_pelanggaran where siswa='$id'");
mysqli_query($koneksi, "delete from kelas_siswa where ks_siswa='$id'");
header("location:siswa.php");