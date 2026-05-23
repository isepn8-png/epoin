<?php 
include '../koneksi.php';
$nama  = $_POST['nama'];

mysqli_query($koneksi, "insert into jurusan values (NULL,'$nama')");
header("location:jurusan.php");