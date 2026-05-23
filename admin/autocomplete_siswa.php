<?php
include 'koneksi.php';

$term = mysqli_real_escape_string($koneksi, $_GET['term']);
$query = mysqli_query($koneksi, "SELECT * FROM siswa 
  WHERE siswa_nama LIKE '%$term%' OR siswa_nis LIKE '%$term%' 
  ORDER BY siswa_nama ASC LIMIT 10");

$data = [];
while($row = mysqli_fetch_assoc($query)) {
  $data[] = [
    "label" => $row['siswa_nama'] . " | " . $row['siswa_nis'],
    "value" => $row['siswa_id']
  ];
}

echo json_encode($data);
?>
