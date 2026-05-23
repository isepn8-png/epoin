<?php
// admin/ajax_siswa_by_kelas.php
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

require_once '../koneksi.php';

$kelas = isset($_GET['kelas']) ? (int)$_GET['kelas'] : 0;
if ($kelas <= 0) {
  echo '<select class="form-control" disabled><option>Pilih kelas dulu…</option></select>';
  exit;
}

/* Ambil siswa berdasarkan mapping kelas_siswa
   NOTE: beberapa row di dump berisi (0,0) → kita abaikan saja */
$sql = "SELECT s.siswa_id, s.siswa_nis, s.siswa_nama
        FROM kelas_siswa ks
        JOIN siswa s ON s.siswa_id = ks.ks_siswa
        WHERE ks.ks_kelas = ? AND ks.ks_kelas > 0 AND ks.ks_siswa > 0
        ORDER BY TRIM(s.siswa_nama) ASC";

$stmt = mysqli_prepare($koneksi, $sql);
mysqli_stmt_bind_param($stmt, "i", $kelas);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

echo '<select id="siswa_select" name="siswa" class="form-control" required>';
echo '<option value="">— Pilih siswa —</option>';
while ($row = mysqli_fetch_assoc($res)) {
  $id  = (int)$row['siswa_id'];
  $nis = htmlspecialchars($row['siswa_nis'],  ENT_QUOTES, 'UTF-8');
  $nm  = htmlspecialchars($row['siswa_nama'], ENT_QUOTES, 'UTF-8');
  echo "<option value=\"$id\">$nis - $nm</option>";
}
echo '</select>';
