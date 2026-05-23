<?php 
include '../koneksi.php';
?>
<select class="form-control pilih_kelas" name="kelas" required="required">
	<option value="">- Pilih Kelas</option>
	<?php 
	$id = $_POST['ta'];
	$kelas = mysqli_query($koneksi,"select * from kelas, jurusan where kelas_jurusan=jurusan_id and kelas_ta='$id' order by kelas_jurusan asc");
	while($k = mysqli_fetch_array($kelas)){
		?>
		<option value="<?php echo $k['kelas_id'] ?>"><?php echo $k['jurusan_nama'] ?> | <?php echo $k['kelas_nama'] ?></option>
		<?php 
	}
	?>
</select>