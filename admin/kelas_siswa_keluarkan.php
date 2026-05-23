<?php 
include '../koneksi.php';
$siswa  = $_GET['siswa'];
$kelas  = $_GET['kelas'];
mysqli_query($koneksi, "delete from kelas_siswa where ks_siswa='$siswa' and ks_kelas='$kelas'");
header("location:kelas_siswa.php?id=$kelas");