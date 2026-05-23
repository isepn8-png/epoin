<?php 
include '../koneksi.php';
?>
<select class="form-control pilih_siswa" name="siswa" required="required">
	<option value="">- Pilih Siswa</option>
	<?php 
	$id = $_POST['kelas'];
	$siswa = mysqli_query($koneksi,"select * from siswa, kelas_siswa where ks_siswa=siswa_id and ks_kelas='$id' order by siswa_nama asc");
	while($k = mysqli_fetch_array($siswa)){
		?>
		<option value="<?php echo $k['siswa_id'] ?>"><?php echo $k['siswa_nama'] ?> | <?php echo $k['siswa_nis'] ?></option>
		<?php 
	}
	?>
</select>