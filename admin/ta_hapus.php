<?php 
include '../koneksi.php';
$id = $_GET['id'];

mysqli_query($koneksi, "delete from ta where ta_id='$id'");

$x = mysqli_query($koneksi, "select * from kelas where kelas_ta='$id'");
while($xx = mysqli_fetch_array($x)){

	$id_kelas = $xx['siswa_id'];

	mysqli_query($koneksi, "delete from kelas where kelas_ta='$id_kelas'");
	mysqli_query($koneksi, "delete from input_prestasi where kelas='$id_kelas'");
	mysqli_query($koneksi, "delete from input_pelanggaran where kelas='$id_kelas'");
	mysqli_query($koneksi, "delete from kelas_siswa where ks_kelas='$id_kelas'");

}




header("location:ta.php");
