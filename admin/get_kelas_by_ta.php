<?php
// get_kelas_by_ta.php
include '../koneksi.php';
header('Content-Type: application/json');
$ta_id = isset($_POST['ta_id']) ? mysqli_real_escape_string($koneksi, $_POST['ta_id']) : '';
$kelas_data = [];
if ($ta_id) {
    $query = mysqli_query($koneksi, "SELECT * FROM kelas WHERE kelas_ta = '$ta_id' ORDER BY kelas_nama ASC");
    if ($query) {
        while ($kelas = mysqli_fetch_assoc($query)) {
            $kelas_data[] = [
                'kelas_id' => $kelas['kelas_id'],
                'kelas_nama' => $kelas['kelas_nama']
            ];
        }
    }
}
echo json_encode($kelas_data);
?>