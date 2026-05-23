<?php 
include '../koneksi.php';
$id  = $_POST['id'];

$nama  = $_POST['nama'];
$ta = $_POST['ta'];
$jurusan = $_POST['jurusan'];

mysqli_query($koneksi, "update kelas set kelas_nama='$nama', kelas_ta='$ta', kelas_jurusan='$jurusan' where kelas_id='$id'");
header("location:kelas.php");