<?php
header('Content-Type: application/json');

// include koneksi
function include_first(array $paths){ foreach($paths as $p){ if(file_exists($p)){ require_once $p; return true; } } return false; }
$ok = include_first([
  __DIR__.'/../../config/koneksi.php',
  __DIR__.'/../../config/db.php',
  __DIR__.'/../config/koneksi.php',
  __DIR__.'/../config/db.php',
  __DIR__.'/../../koneksi.php',
]);
require_once __DIR__.'/../includes/deskripsi_helper.php';
if (isset($koneksi) && !isset($conn)) $conn = $koneksi;

function q_all($sql,$params=[]){ global $pdo,$conn;
  if(isset($pdo)){ $st=$pdo->prepare($sql); $st->execute($params); return $st->fetchAll(PDO::FETCH_ASSOC); }
  $st=$conn->prepare($sql); if($params){ $st->bind_param(str_repeat('s',count($params)),...array_values($params)); }
  $st->execute(); $r=$st->get_result(); return $r? $r->fetch_all(MYSQLI_ASSOC):[];
}
function q_exec($sql,$params=[]){ global $pdo,$conn;
  if(isset($pdo)){ $st=$pdo->prepare($sql); return $st->execute($params); }
  $st=$conn->prepare($sql); if($params){ $st->bind_param(str_repeat('s',count($params)),...array_values($params)); } return $st->execute();
}

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$pts_set_id = isset($_POST['pts_set_id']) ? (int)$_POST['pts_set_id'] : 0;
$pts_id     = isset($_POST['pts_id']) ? (int)$_POST['pts_id'] : 0;

try {
  if ($pts_id > 0) {
    $rows = q_all('SELECT v.*, np.pts_set_id, np.siswa_id FROM v_rapor_pts_deskripsi v JOIN nilai_pts np ON np.pts_id=v.pts_id WHERE v.pts_id=?', [$pts_id]);
    if (!$rows) throw new Exception('Data tidak ditemukan');
    $v = $rows[0];
    $final = build_deskripsi_paragraph($v['deskripsi_optimal'] ?? '', $v['deskripsi_perlu'] ?? '');
    q_exec('INSERT INTO rapor_pts_deskripsi (pts_id, pts_set_id, siswa_id, deskripsi_final, deskripsi_opt, deskripsi_perlu, is_manual, generated_by)
            VALUES (?, ?, ?, ?, ?, ?, 0, ?)
            ON DUPLICATE KEY UPDATE deskripsi_final=VALUES(deskripsi_final), deskripsi_opt=VALUES(deskripsi_opt), deskripsi_perlu=VALUES(deskripsi_perlu), is_manual=0, updated_at=NOW(), updated_by=VALUES(generated_by)',
           [$v['pts_id'], $v['pts_set_id'], $v['siswa_id'], $final, $v['deskripsi_optimal'], $v['deskripsi_perlu'], $userId]);
    echo json_encode(['ok'=>true,'deskripsi_final'=>$final]); exit;
  }

  if ($pts_set_id > 0) {
    $rs = q_all('SELECT v.*, np.pts_set_id, np.siswa_id FROM v_rapor_pts_deskripsi v JOIN nilai_pts np ON np.pts_id=v.pts_id WHERE np.pts_set_id=? ORDER BY np.pts_id', [$pts_set_id]);
    $count=0;
    foreach ($rs as $row) {
      $final = build_deskripsi_paragraph($row['deskripsi_optimal'] ?? '', $row['deskripsi_perlu'] ?? '');
      q_exec('INSERT INTO rapor_pts_deskripsi (pts_id, pts_set_id, siswa_id, deskripsi_final, deskripsi_opt, deskripsi_perlu, is_manual, generated_by)
              VALUES (?, ?, ?, ?, ?, ?, 0, ?)
              ON DUPLICATE KEY UPDATE deskripsi_final=VALUES(deskripsi_final), deskripsi_opt=VALUES(deskripsi_opt), deskripsi_perlu=VALUES(deskripsi_perlu), is_manual=0, updated_at=NOW(), updated_by=VALUES(generated_by)',
             [$row['pts_id'], $row['pts_set_id'], $row['siswa_id'], $final, $row['deskripsi_optimal'], $row['deskripsi_perlu'], $userId]);
      $count++;
    }
    echo json_encode(['ok'=>true,'message'=>"Generate selesai untuk $count siswa."]); exit;
  }

  throw new Exception('Parameter tidak valid');
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);
}
