<?php
// get_siswa_by_kelas.php
include '../koneksi.php';
header('Content-Type: application/json');
$kelas_id = isset($_POST['kelas_id']) ? mysqli_real_escape_string($koneksi, $_POST['kelas_id']) : '';
$siswa_data = [];
if ($kelas_id) {
    $query = mysqli_query($koneksi, "SELECT * FROM siswa WHERE siswa_kelas = '$kelas_id' ORDER BY siswa_nama ASC");
    if ($query) {
        while ($siswa = mysqli_fetch_assoc($query)) {
            $siswa_data[] = [
                'siswa_id' => $siswa['siswa_id'],
                'siswa_nama' => $siswa['siswa_nama'],
                'siswa_nis' => $siswa['siswa_nis']
            ];
        }
    }
}
echo json_encode($siswa_data);
?>