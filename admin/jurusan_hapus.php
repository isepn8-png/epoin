<?php 
include '../koneksi.php';
$id = $_GET['id'];

// mysqli_query($koneksi, "update barang set barang_jurusan='1' where barang_jurusan='$id'");

mysqli_query($koneksi, "delete from jurusan where jurusan_id='$id'");

$x = mysqli_query($koneksi, "select * from siswa where siswa_jurusan='$id'");
while($xx = mysqli_fetch_array($x)){

	$id_siswa = $xx['siswa_id'];

	mysqli_query($koneksi, "delete from siswa where siswa_id='$id_siswa'");
	mysqli_query($koneksi, "delete from input_prestasi where siswa='$id_siswa'");
	mysqli_query($koneksi, "delete from input_pelanggaran where siswa='$id_siswa'");
	mysqli_query($koneksi, "delete from kelas_siswa where ks_siswa='$id_siswa'");

	
}

mysqli_query($koneksi, "delete from kelas where kelas_jurusan='$id'");

header("location:jurusan.php");