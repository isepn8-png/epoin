<?php 
include '../koneksi.php';
$nama  = $_POST['nama'];
$jurusan = $_POST['jurusan'];
$ta = $_POST['ta'];

mysqli_query($koneksi, "insert into kelas values (NULL,'$nama ','$jurusan','$ta')");
header("location:kelas.php");