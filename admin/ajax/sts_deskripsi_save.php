<?php
// admin/ajax/sts_deskripsi_save.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/koneksi.php';

$pts_set_id = (int)($_POST['pts_set_id'] ?? 0);
$items = json_decode($_POST['items'] ?? '[]', true);
if(!$pts_set_id || !is_array($items)){
  echo json_encode(['ok'=>false,'msg'=>'Payload tidak valid']); exit;
}

// Ambil metadata set untuk diisi ke tabel rapor_sts_deskripsi
$set = db()->query("SELECT * FROM nilai_pts_set WHERE pts_set_id=".(int)$pts_set_id)->fetch_assoc();
if(!$set){ echo json_encode(['ok'=>false,'msg'=>'PTS set tidak ditemukan']); exit; }

// Siapkan batch UPSERT
$sql = "INSERT INTO rapor_sts_deskripsi (pts_id, pts_set_id, siswa_id, kelas_id, mapel_id, ta_id, semester, deskripsi, source_enum)
        VALUES (?, ?, (SELECT siswa_id FROM nilai_pts WHERE pts_id=?), ?, ?, ?, ?, ?, 'manual')
        ON DUPLICATE KEY UPDATE deskripsi=VALUES(deskripsi), source_enum='manual', updated_at=NOW()";
$stmt = db()->prepare($sql);

$kelas_id = (int)$set['kelas_id'];
$mapel_id = (int)$set['mapel_id'];
$ta_id    = (int)$set['ta_id'];
$semester = (int)$set['semester'];

foreach($items as $it){
  $pid = (int)$it['pts_id'];
  $desc= trim($it['deskripsi'] ?? '');
  if($desc==='') continue;
  $stmt->bind_param('iii iiiis', $pid, $pts_set_id, $pid, $kelas_id, $mapel_id, $ta_id, $semester, $desc);
  //    types:  i   i    i   i   i   i   i   s
  $stmt->execute();
}
$stmt->close();

echo json_encode(['ok'=>true]);