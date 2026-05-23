<?php 
include '../koneksi.php';
$nama  = $_POST['nama'];
$status = $_POST['status'];


if($status == 1){
	mysqli_query($koneksi,"update ta set ta_status='0'");
}

mysqli_query($koneksi, "insert into ta values (NULL,'$nama ','$status')");
header("location:ta.php");