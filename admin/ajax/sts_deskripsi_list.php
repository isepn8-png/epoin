<?php
// admin/ajax/sts_deskripsi_list.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../helpers/deskripsi_sts_helper.php';

$kelas_id   = (int)($_GET['kelas_id'] ?? 0);
$mapel_id   = (int)($_GET['mapel_id'] ?? 0);
$semester   = (int)($_GET['semester'] ?? 1);

if(!$kelas_id || !$mapel_id){
  echo json_encode(['ok'=>false,'msg'=>'Parameter kurang']); exit;
}

// Ambil TA aktif
$ta = db()->query("SELECT ta_id FROM ta WHERE ta_status=1 LIMIT 1")->fetch_assoc();
$ta_id = (int)($ta['ta_id'] ?? 0);

// Cari set PTS untuk kombinasi tersebut
$sqlSet = "SELECT * FROM nilai_pts_set WHERE kelas_id=? AND mapel_id=? AND semester=? ORDER BY pts_set_id DESC LIMIT 1";
$stmt = db()->prepare($sqlSet);
$stmt->bind_param('iii', $kelas_id, $mapel_id, $semester);
$stmt->execute();
$set = $stmt->get_result()->fetch_assoc();
$stmt->close();
if(!$set){ echo json_encode(['ok'=>true,'pts_set_id'=>null,'rows'=>[]]); exit; }
$pts_set_id = (int)$set['pts_set_id'];

// Ambil daftar nilai_pts di set ini + siswa + (jika ada) deskripsi tersimpan + 2 daftar dari view
$sql = "
  SELECT np.pts_id, np.siswa_id, s.siswa_nama AS nama_siswa, s.siswa_nisn AS nisn, np.nilai,
         rsd.deskripsi, rsd.source_enum,
         v.deskripsi_optimal, v.deskripsi_perlu
  FROM nilai_pts np
  JOIN siswa s        ON s.siswa_id = np.siswa_id
  LEFT JOIN rapor_sts_deskripsi rsd ON rsd.pts_id = np.pts_id
  LEFT JOIN v_rapor_pts_deskripsi v ON v.pts_id = np.pts_id
  WHERE np.pts_set_id = ?
  ORDER BY s.siswa_nama ASC";
$stmt = db()->prepare($sql);
$stmt->bind_param('i', $pts_set_id);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while($r = $res->fetch_assoc()){
  $deskripsi = $r['deskripsi'];
  $source    = $r['source_enum'] ?: 'generated';
  if (!$deskripsi) {
    $opt = $r['deskripsi_optimal'] ? preg_split("/\r?\n/", $r['deskripsi_optimal']) : [];
    $per = $r['deskripsi_perlu']   ? preg_split("/\r?\n/", $r['deskripsi_perlu'])   : [];
    $deskripsi = build_deskripsi_final($opt, $per);
    $source = 'generated';
  }
  $rows[] = [
    'pts_id' => (int)$r['pts_id'],
    'siswa_id' => (int)$r['siswa_id'],
    'nama_siswa' => $r['nama_siswa'],
    'nisn' => $r['nisn'],
    'nilai' => $r['nilai'],
    'deskripsi' => $deskripsi,
    'source_enum' => $source,
  ];
}
$stmt->close();

echo json_encode(['ok'=>true,'pts_set_id'=>$pts_set_id,'rows'=>$rows]);