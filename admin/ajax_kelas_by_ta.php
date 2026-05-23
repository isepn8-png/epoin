<?php
// admin/ajax_kelas_by_ta.php
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

require_once '../koneksi.php';

$ta = isset($_GET['ta']) ? (int)$_GET['ta'] : 0;
if ($ta <= 0) {
  echo '<select class="form-control" disabled><option>Pilih tahun ajaran dulu…</option></select>';
  exit;
}

$sql = "SELECT kelas_id, TRIM(kelas_nama) AS nama
        FROM kelas
        WHERE kelas_ta = ?
        ORDER BY TRIM(kelas_nama) ASC";
$stmt = mysqli_prepare($koneksi, $sql);
mysqli_stmt_bind_param($stmt, "i", $ta);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

echo '<select id="kelas_select" name="kelas" class="form-control" required>';
echo '<option value="">— Pilih kelas —</option>';
while ($row = mysqli_fetch_assoc($res)) {
  $id = (int)$row['kelas_id'];
  $nm = htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8');
  echo "<option value=\"$id\">$nm</option>";
}
echo '</select>';
