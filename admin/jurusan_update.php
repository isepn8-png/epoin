<?php 
include '../koneksi.php';
$id  = $_POST['id'];
$nama  = $_POST['nama'];

mysqli_query($koneksi, "update jurusan set jurusan_nama='$nama' where jurusan_id='$id'");
header("location:jurusan.php");