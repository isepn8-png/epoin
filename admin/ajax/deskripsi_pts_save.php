<?php
header('Content-Type: application/json');

function include_first(array $paths){ foreach($paths as $p){ if(file_exists($p)){ require_once $p; return true; } } return false; }
$ok = include_first([
  __DIR__.'/../../config/koneksi.php',
  __DIR__.'/../../config/db.php',
  __DIR__.'/../config/koneksi.php',
  __DIR__.'/../config/db.php',
  __DIR__.'/../../koneksi.php',
]);
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
$pts_id = (int)($_POST['pts_id'] ?? 0);
$text   = trim($_POST['deskripsi_final'] ?? '');

if ($pts_id<=0 || $text==='') { http_response_code(400); echo json_encode(['ok'=>false,'message'=>'Data tidak lengkap']); exit; }

$row = q_all('SELECT np.pts_id, np.pts_set_id, np.siswa_id FROM nilai_pts np WHERE np.pts_id=?', [$pts_id]);
if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'message'=>'PTS tidak ditemukan']); exit; }
$row = $row[0];

q_exec('INSERT INTO rapor_pts_deskripsi (pts_id, pts_set_id, siswa_id, deskripsi_final, is_manual, generated_by, updated_by)
        VALUES (?, ?, ?, ?, 1, ?, ?)
        ON DUPLICATE KEY UPDATE deskripsi_final=VALUES(deskripsi_final), is_manual=1, updated_at=NOW(), updated_by=VALUES(updated_by)',
       [$row['pts_id'], $row['pts_set_id'], $row['siswa_id'], $text, $userId, $userId]);

echo json_encode(['ok'=>true]);
