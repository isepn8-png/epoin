<?php
// admin/ujian_gform.php – Enhanced Panel: Kelola Ujian + Tab Pusat Kendali (deskripsi + tombol buka window baru)
// UI gelap + kartu soft + chips + Select2 + Pagination(10)
// ENHANCED: Filter canggih, bulk actions, detail modal, sound notification (API dipertahankan)
// FIX: Dynamic PK (ujian_gform.id vs ujian_gform.ujian_id) + dynamic allow_grace_sec + attempt_count
// REVISE: Hero-features jadi alur jelas + warna soft per kartu; "Ujian Kolektif" mini-callout; Header judul + ikon disesuaikan

// --- DEBUG (opsional saat error 500). Boleh dimatikan lagi jika sudah normal.
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Jakarta');

/* ====== include auth + koneksi ====== */
$__auth = __DIR__ . '/../includes/auth.php';
$__db   = __DIR__ . '/../koneksi.php';
if (is_file($__auth)) require_once $__auth;
if (!isset($koneksi) && is_file($__db)) require_once $__db;

/* ====== Import secret preview (AMAN) ======
   - Coba ambil dari config/env bila tersedia
   - Jika belum ada, gunakan default (silakan ganti ke nilai rahasia yang kuat)
*/
$__cfg = __DIR__ . '/../config.php';
if (is_file($__cfg)) require_once $__cfg;
if (!defined('UJIAN_PREVIEW_SECRET')) {
  $envSecret = getenv('UJIAN_PREVIEW_SECRET');
  // Ganti nilai default berikut dengan secret acak minimal 32 karakter
  define('UJIAN_PREVIEW_SECRET', $envSecret ?: 'ganti-ini-dengan-secret-yang-kuat-32char-atau-lebih');
}

/* ====== Fallback RBAC ====== */
if (!function_exists('ensure_logged_in')) {
  function ensure_logged_in(){ if (empty($_SESSION['id'])) { header('Location: ../admin.php?alert=belum_login'); exit; } }
}
if (!function_exists('current_user')) {
  function current_user(){ return ['user_id'=>(int)($_SESSION['id'] ?? 0), 'level'=>($_SESSION['level'] ?? '')]; }
}
if (!function_exists('user_has_any_role')) {
  function user_has_any_role($roles) {
    $role = strtolower(trim((string)($_SESSION['level'] ?? '')));
    if ($role === 'admin') $role = 'administrator';
    $roles = array_map('strtolower', $roles);
    return in_array($role, $roles, true);
  }
}
if (!function_exists('guard_roles')) {
  function guard_roles($roles){ if (!user_has_any_role($roles)) { http_response_code(403); exit('Forbidden'); } }
}

ensure_logged_in();
guard_roles(['administrator','guru']);

/* ====== Helpers umum ====== */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function post($k,$d=null){ return isset($_POST[$k]) ? (is_array($_POST[$k]) ? $_POST[$k] : trim($_POST[$k])) : $d; }
function intv($v){ return (int)$v; }

/* ====== Deteksi driver DB ====== */
$DB = ['driver'=>null,'pdo'=>null,'mysqli'=>null];
if (isset($koneksi)) {
  if ($koneksi instanceof mysqli){ $DB['driver']='mysqli'; $DB['mysqli']=$koneksi; }
  elseif ($koneksi instanceof PDO){ $DB['driver']='pdo'; $DB['pdo']=$koneksi; }
}
if (!$DB['driver'] && function_exists('db')) { $pdo = db(); if ($pdo instanceof PDO){ $DB['driver']='pdo'; $DB['pdo']=$pdo; } }
if (!$DB['driver'] && isset($pdo) && $pdo instanceof PDO) { $DB['driver']='pdo'; $DB['pdo']=$pdo; }
if (!$DB['driver']) { error_log('[ujian_gform] DB connection missing'); http_response_code(500); exit('Koneksi DB tidak tersedia.'); }

/* ====== Wrapper DB ====== */
if (!function_exists('mysqli_stmt_fetch_all_assoc')) {
  function mysqli_stmt_fetch_all_assoc($stmt) {
    $meta = $stmt->result_metadata();
    if (!$meta) return array();
    $row = array(); $bind = array();
    while ($field = $meta->fetch_field()) { $row[$field->name] = null; $bind[] = &$row[$field->name]; }
    call_user_func_array(array($stmt, 'bind_result'), $bind);
    $rows = array(); while ($stmt->fetch()) { $copy = array(); foreach ($row as $k => $v) { $copy[$k] = $v; } $rows[] = $copy; }
    return $rows;
  }
}
function db_all($sql,$params=array()){ global $DB;
  if ($DB['driver']==='pdo'){ $st=$DB['pdo']->prepare($sql); $st->execute($params); return $st->fetchAll(PDO::FETCH_ASSOC); }
  $link=$DB['mysqli'];
  if ($params){
    $stmt=mysqli_prepare($link,$sql); if(!$stmt) throw new Exception(mysqli_error($link));
    $types=''; $bind=array(); foreach($params as $v){ $types.=is_int($v)?'i':(is_float($v)?'d':'s'); $bind[]=$v; }
    $refs=array(&$types); foreach($bind as $k=>&$v){ $refs[]=&$v; } call_user_func_array(array($stmt,'bind_param'),$refs);
    if(!$stmt->execute()) throw new Exception($stmt->error);
    if (method_exists($stmt,'get_result')) { $res = $stmt->get_result(); $rows=array(); if($res){ while($r=$res->fetch_assoc()) $rows[]=$r; } $stmt->close(); return $rows; }
    $rows = mysqli_stmt_fetch_all_assoc($stmt); $stmt->close(); return $rows;
  } else {
    $res=mysqli_query($link,$sql); if(!$res) throw new Exception(mysqli_error($link));
    $rows=array(); while($r=$res->fetch_assoc()) $rows[]=$r; return $rows;
  }
}
function db_one($sql,$params=array()){ $a=db_all($sql,$params); return $a? $a[0]:null; }
function db_exec($sql,$params=array()){ global $DB;
  if ($DB['driver']==='pdo'){ $st=$DB['pdo']->prepare($sql); return $st->execute($params); }
  $link=$DB['mysqli'];
  if ($params){
    $stmt=mysqli_prepare($link,$sql); if(!$stmt) throw new Exception(mysqli_error($link));
    $types=''; $bind=array(); foreach($params as $v){ $types.=is_int($v)?'i':(is_float($v)?'d':'s'); $bind[]=$v; }
    $refs=array(&$types); foreach($bind as $k=>&$v){ $refs[]=&$v; } call_user_func_array(array($stmt,'bind_param'),$refs);
    $ok=$stmt->execute(); if(!$ok) throw new Exception($stmt->error); $stmt->close(); return $ok;
  } else {
    $ok=mysqli_query($link,$sql); if(!$ok) throw new Exception(mysqli_error($link)); return true;
  }
}

/* ====== Cek tabel/kolom ====== */
if (!function_exists('gformp_table_exists')) {
  function gformp_table_exists($t){
    try{
      $r = db_one("SELECT 1 AS x FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1",array($t));
      return !empty($r);
    }catch(Exception $e){ return false; }
  }
}
if (!function_exists('gformp_col_exists')) {
  function gformp_col_exists($t,$c){
    try{
      $r = db_one("SELECT 1 AS x FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=? LIMIT 1",array($t,$c));
      return !empty($r);
    }catch(Exception $e){ return false; }
  }
}

/* ====== User + TA ====== */
if (!function_exists('gformp_role')) {
  function gformp_role(){
    if (function_exists('is_admin') && is_admin()) return 'admin';
    if (function_exists('is_guru') && is_guru())   return 'guru';
    $lvl = strtolower(trim((string)$_SESSION['level'] ?? ''));
    if ($lvl==='administrator') return 'admin';
    if ($lvl==='guru') return 'guru';
    return 'siswa';
  }
}
if (!function_exists('gformp_user_id')) {
  function gformp_user_id(){
    if (function_exists('current_user')) { $cu = current_user(); if (!empty($cu['user_id'])) return (int)$cu['user_id']; }
    if (!empty($_SESSION['id'])) return (int)$_SESSION['id'];
    return 0;
  }
}
$me      = function_exists('current_user') ? current_user() : ['user_id'=>0,'level'=>''];
$myId    = (int)($me['user_id'] ?? 0);
$isAdmin = user_has_any_role(array('administrator'));

/* TA aktif */
$rowTA = db_one("SELECT ta_id FROM `ta` WHERE ta_status=1 ORDER BY ta_id DESC LIMIT 1");
if(!$rowTA){ exit('TA aktif tidak ditemukan (tabel `ta`).'); }
$ta_id = (int)$rowTA['ta_id'];

/* ====== Dynamic PK & kolom ====== */
$UJ_PK     = gformp_col_exists('ujian_gform','id') ? 'id' : (gformp_col_exists('ujian_gform','ujian_id') ? 'ujian_id' : 'id');
$HAS_GRACE = gformp_col_exists('ujian_gform','allow_grace_sec');

/* ====== Data Pengampu ====== */
$cond = $isAdmin ? "1=1" : "pm.guru_user_id=".$myId;
$sqlPengampu = "
  SELECT pm.id AS pengampu_id, pm.kelas_id, k.kelas_id AS k_id, k.kelas_nama, pm.mapel_id, m.mapel_kode, m.mapel_nama,
         pm.guru_user_id, u.user_nama
  FROM `pengampu_mapel` pm
  JOIN `kelas` k ON k.kelas_id=pm.kelas_id
  JOIN `mapel` m ON m.mapel_id=pm.mapel_id
  JOIN `user`  u ON u.user_id=pm.guru_user_id
  WHERE pm.ta_id=? AND {$cond}
  ORDER BY k.kelas_nama, m.mapel_nama, u.user_nama";
$PENGAMPU = db_all($sqlPengampu,array($ta_id));
$pengampu_by_kelas = array();
foreach ($PENGAMPU as $p) $pengampu_by_kelas[$p['kelas_nama']][] = $p;

/* ====== CSRF ====== */
if (empty($_SESSION['csrf_gformp'])) {
  $_SESSION['csrf_gformp'] = function_exists('random_bytes') ? bin2hex(random_bytes(16)) : bin2hex(openssl_random_pseudo_bytes(16));
}
$__CSRF = $_SESSION['csrf_gformp'];

/* ====== Cek tabel attempt (API tetap ada walau panel dihapus) ====== */
$__has_attempt = gformp_table_exists('ujian_gform_attempt');

/* ====== Helper JSON ====== */
function gformp_json($arr,$code=200){ http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

/* ====== Helper URL preview bertanda tangan (diletakkan sebelum VIEW) ====== */
if (!function_exists('gformp_base64url_encode')) {
  function gformp_base64url_encode($s){
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
  }
}
if (!function_exists('gformp_preview_url')) {
  function gformp_preview_url($ujian_id){
    // token sederhana (opsional diverifikasi oleh file siswa)
    $exp = time() + 600; // 10 menit
    $uid = gformp_user_id();
    $payload = $ujian_id.'|'.$exp.'|'.$uid;
    $sig = hash_hmac('sha256', $payload, UJIAN_PREVIEW_SECRET);
    $token = gformp_base64url_encode($payload.'|'.$sig);

    // Arahkan ke file siswa (relative dari /admin/)
    $path = '../siswa/exam_gform.php';
    $qs   = http_build_query([
      'ujian_id' => (int)$ujian_id,
      'pv'       => 1,
      'tk'       => $token
    ]);
    return $path.'?'.$qs;
  }
}

/* ================== ROUTER API (dipertahankan) ================== */
if (isset($_GET['gform_api'])) {
  $action = (string)($_GET['gform_api']);
  $needCsrf = in_array($action, array('reset_attempt','force_dq','bulk_reset'), true);
  if ($needCsrf) {
    $hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$hdr || !hash_equals($__CSRF, $hdr)) gformp_json(['ok'=>false,'err'=>'CSRF mismatch'],403);
  }

  $needsAttempt = in_array($action, array('attempts','attempt_status','attempts_csv','reset_attempt','force_dq','my_attempts','bulk_reset','attempt_detail'), true);
  if ($needsAttempt && !$__has_attempt) gformp_json(['ok'=>false,'err'=>'Tabel ujian_gform_attempt belum ada.'],500);

  try {
    /* ---- daftar ujian ---- */
    if ($action==='list_ujian') {
      $where = "uj.ta_id=?";
      $par   = array($ta_id);
      if (!$isAdmin) { $where .= " AND uj.guru_user_id=?"; $par[]=$myId; }

      $selGrace = $HAS_GRACE ? "uj.allow_grace_sec" : "NULL AS allow_grace_sec";
      $rows = db_all("
        SELECT 
          uj.$UJ_PK AS id, uj.jenis, uj.mulai_at AS mulai, uj.selesai_at AS selesai, $selGrace,
          k.kelas_nama, m.mapel_kode, m.mapel_nama,
          COALESCE(COUNT(a.id),0) AS attempt_count
        FROM ujian_gform uj
        JOIN kelas k ON k.kelas_id=uj.kelas_id
        JOIN mapel m ON m.mapel_id=uj.mapel_id
        LEFT JOIN ujian_gform_attempt a ON a.ujian_id = uj.$UJ_PK
        WHERE {$where}
        GROUP BY uj.$UJ_PK, uj.jenis, uj.mulai_at, uj.selesai_at, k.kelas_nama, m.mapel_kode, m.mapel_nama".($HAS_GRACE ? ", uj.allow_grace_sec" : "")."
        ORDER BY k.kelas_nama, m.mapel_nama, uj.mulai_at DESC
        LIMIT 500
      ", $par);
      gformp_json(['ok'=>true,'data'=>$rows]);
    }

    /* ---- list attempt per ujian (dengan fallback tanpa JOIN) ---- */
    if ($action==='attempts') {
      $ujian_id = (int)($_GET['ujian_id'] ?? 0);
      if ($ujian_id<=0) gformp_json(['ok'=>true,'data'=>[]]);

      $hasUser  = gformp_table_exists('user');
      $hasSiswa = gformp_table_exists('siswa');
      $hasKelas = gformp_table_exists('kelas');

      $sql = "SELECT a.*";
      if ($hasUser)  $sql .= ", COALESCE(u.user_nama, u.nama) AS siswa_nama";
      if ($hasKelas && $hasSiswa) {
        $sql .= ", COALESCE(ks.kelas_nama, ku.kelas_nama) AS kelas_nama";
      } elseif ($hasKelas) {
        $sql .= ", ku.kelas_nama AS kelas_nama";
      } else {
        $sql .= ", NULL AS kelas_nama";
      }
      $sql .= " FROM ujian_gform_attempt a";
      if ($hasUser)  $sql .= " LEFT JOIN `user` u ON u.user_id=a.siswa_user_id";
      $sql .= " LEFT JOIN ujian_gform uj ON uj.$UJ_PK=a.ujian_id";
      if ($hasKelas) $sql .= " LEFT JOIN `kelas` ku ON ku.kelas_id=uj.kelas_id";
      if ($hasSiswa) $sql .= " LEFT JOIN `siswa` s ON s.siswa_id=a.siswa_id";
      if ($hasSiswa && $hasKelas) $sql .= " LEFT JOIN `kelas` ks ON ks.kelas_id=s.kelas_id";
      $sql .= " WHERE a.ujian_id=?";
      $sql .= " ORDER BY (a.last_seen_at IS NULL) ASC, a.last_seen_at DESC, a.started_at DESC";
      $rows = db_all($sql,array($ujian_id));

      if (empty($rows)) {
        $cnt = db_one("SELECT COUNT(*) c FROM ujian_gform_attempt WHERE ujian_id=?",[$ujian_id]);
        if ((int)($cnt['c'] ?? 0) > 0) {
          $rows = db_all("SELECT a.* FROM ujian_gform_attempt a WHERE a.ujian_id=? ORDER BY (a.last_seen_at IS NULL) ASC, a.last_seen_at DESC, a.started_at DESC",[$ujian_id]);
        }
      }
      gformp_json(['ok'=>true,'data'=>$rows]);
    }

    /* ---- status ringkasan ---- */
    if ($action==='attempt_status') {
      $ujian_id = (int)($_GET['ujian_id'] ?? 0);
      if ($ujian_id<=0) gformp_json(['ok'=>true,'data'=>null]);

      if ($HAS_GRACE) {
        $row = db_one("
          SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN a.status='mulai' THEN 1 ELSE 0 END) AS sedang,
            SUM(CASE WHEN a.status='selesai' THEN 1 ELSE 0 END) AS selesai,
            SUM(CASE WHEN a.status='diskualifikasi' THEN 1 ELSE 0 END) AS dq,
            MAX(a.last_seen_at) AS last_seen,
            COALESCE(uj.allow_grace_sec,20) AS grace
          FROM ujian_gform_attempt a
          JOIN ujian_gform uj ON uj.$UJ_PK=a.ujian_id
          WHERE a.ujian_id=?
          GROUP BY uj.allow_grace_sec
        ",array($ujian_id));
      } else {
        $row = db_one("
          SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN a.status='mulai' THEN 1 ELSE 0 END) AS sedang,
            SUM(CASE WHEN a.status='selesai' THEN 1 ELSE 0 END) AS selesai,
            SUM(CASE WHEN a.status='diskualifikasi' THEN 1 ELSE 0 END) AS dq,
            MAX(a.last_seen_at) AS last_seen
          FROM ujian_gform_attempt a
          WHERE a.ujian_id=?
        ",array($ujian_id));
        $row['grace'] = 20;
      }
      if(!$row){ $row = ['total'=>0,'sedang'=>0,'selesai'=>0,'dq'=>0,'last_seen'=>null,'grace'=>20]; }

      if ($HAS_GRACE) {
        $rowOnline = db_one("
          SELECT SUM(
            CASE 
              WHEN a.status='mulai' AND a.last_seen_at IS NOT NULL 
                   AND TIMESTAMPDIFF(SECOND, a.last_seen_at, NOW()) <= COALESCE(uj.allow_grace_sec,20)
            THEN 1 ELSE 0 END
          ) AS online
          FROM ujian_gform_attempt a
          JOIN ujian_gform uj ON uj.$UJ_PK=a.ujian_id
          WHERE a.ujian_id=?",array($ujian_id));
      } else {
        $rowOnline = db_one("
          SELECT SUM(
            CASE 
              WHEN a.status='mulai' AND a.last_seen_at IS NOT NULL 
                   AND TIMESTAMPDIFF(SECOND, a.last_seen_at, NOW()) <= 20
            THEN 1 ELSE 0 END
          ) AS online
          FROM ujian_gform_attempt a
          WHERE a.ujian_id=?",array($ujian_id));
      }
      $row['online'] = (int)($rowOnline['online'] ?? 0);

      $rowV = db_one("SELECT SUM(a.pelanggaran) AS vio FROM ujian_gform_attempt a WHERE a.ujian_id=?",array($ujian_id));
      $row['pelanggaran_total'] = (int)($rowV['vio'] ?? 0);
      $row['progress_done'] = ($row['total']>0) ? round(($row['selesai']/$row['total'])*100,1) : 0.0;

      gformp_json(['ok'=>true,'data'=>$row]);
    }

    if ($action==='reset_attempt') {
      if (!in_array(gformp_role(), array('admin','guru'), true)) gformp_json(['ok'=>false,'err'=>'Unauthorized'],403);
      $payload = json_decode(file_get_contents('php://input'), true) ?: array();
      $attempt_id = (int)($payload['attempt_id'] ?? 0);
      if ($attempt_id<=0) gformp_json(['ok'=>false,'err'=>'attempt_id kosong'],422);
      db_exec("DELETE FROM ujian_gform_attempt WHERE id=?",array($attempt_id));
      gformp_json(['ok'=>true]);
    }

    if ($action==='force_dq') {
      if (!in_array(gformp_role(), array('admin','guru'), true)) gformp_json(['ok'=>false,'err'=>'Unauthorized'],403);
      $payload = json_decode(file_get_contents('php://input'), true) ?: array();
      $attempt_id = (int)($payload['attempt_id'] ?? 0);
      if ($attempt_id<=0) gformp_json(['ok'=>false,'err'=>'attempt_id kosong'],422);
      db_exec("UPDATE ujian_gform_attempt SET status='diskualifikasi', finished_at=NOW() WHERE id=?",array($attempt_id));
      gformp_json(['ok'=>true]);
    }

    if ($action==='bulk_reset') {
      if (!in_array(gformp_role(), array('admin','guru'), true)) gformp_json(['ok'=>false,'err'=>'Unauthorized'],403);
      $payload = json_decode(file_get_contents('php://input'), true) ?: array();
      $attempt_ids = isset($payload['attempt_ids']) && is_array($payload['attempt_ids']) ? array_map('intval',$payload['attempt_ids']) : array();
      if (empty($attempt_ids)) gformp_json(['ok'=>false,'err'=>'attempt_ids kosong'],422);
      $placeholders = implode(',', array_fill(0, count($attempt_ids), '?'));
      db_exec("DELETE FROM ujian_gform_attempt WHERE id IN ($placeholders)", $attempt_ids);
      gformp_json(['ok'=>true,'count'=>count($attempt_ids)]);
    }

    if ($action==='attempt_detail') {
      $attempt_id = (int)($_GET['attempt_id'] ?? 0);
      if ($attempt_id<=0) gformp_json(['ok'=>false,'err'=>'attempt_id kosong'],422);
      $hasUser  = gformp_table_exists('user');
      $hasSiswa = gformp_table_exists('siswa');
      $selGrace = $HAS_GRACE ? "COALESCE(uj.allow_grace_sec,20) AS allow_grace_sec" : "20 AS allow_grace_sec";
      $sql = "SELECT a.*, uj.judul, uj.durasi_menit, $selGrace";
      if ($hasUser)  $sql .= ", COALESCE(u.user_nama,u.nama) AS siswa_nama, u.user_email AS siswa_email";
      if ($hasSiswa) $sql .= ", s.siswa_nis, s.siswa_nama AS siswa_nama_alt";
      $sql .= " FROM ujian_gform_attempt a JOIN ujian_gform uj ON uj.$UJ_PK=a.ujian_id";
      if ($hasUser)  $sql .= " LEFT JOIN `user` u ON u.user_id=a.siswa_user_id";
      if ($hasSiswa) $sql .= " LEFT JOIN `siswa` s ON s.siswa_id=a.siswa_id";
      $sql .= " WHERE a.id=? LIMIT 1";
      $row = db_one($sql, array($attempt_id));
      if (!$row) gformp_json(['ok'=>false,'err'=>'Attempt tidak ditemukan'],404);
      gformp_json(['ok'=>true,'data'=>$row]);
    }

    if ($action==='my_attempts') {
      $uid = gformp_user_id();
      $rows = db_all("SELECT * FROM ujian_gform_attempt WHERE siswa_user_id=? ORDER BY started_at DESC",array($uid));
      gformp_json(['ok'=>true,'data'=>$rows]);
    }

    if ($action==='attempts_csv') {
      if (!in_array(gformp_role(), array('admin','guru'), true)) { http_response_code(403); exit('Unauthorized'); }
      $ujian_id = (int)($_GET['ujian_id'] ?? 0);
      if ($ujian_id<=0){ http_response_code(422); exit('ujian_id kosong'); }
      $rows = db_all("SELECT a.id, a.ujian_id, a.siswa_id, a.siswa_user_id, a.status, a.pelanggaran, a.started_at, a.finished_at, a.last_seen_at, a.ip_addr, a.user_agent
                      FROM ujian_gform_attempt a WHERE a.ujian_id=? ORDER BY a.id ASC",array($ujian_id));
      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename="attempts_ujian_'.$ujian_id.'.csv"');
      $out = fopen('php://output','w');
      fputcsv($out, array_keys($rows? $rows[0]:array(
        'id','ujian_id','siswa_id','siswa_user_id','status','pelanggaran','started_at','finished_at','last_seen_at','ip_addr','user_agent'
      )));
      foreach($rows as $r) fputcsv($out, $r);
      fclose($out);
      exit;
    }

    gformp_json(['ok'=>false,'err'=>'Unknown action: '.$action],404);
  } catch (Exception $e) {
    gformp_json(['ok'=>false,'err'=>$e->getMessage()],500);
  }
}

/* ================== Actions (Create/Update/Delete) ================== */
$act = $_POST['__act'] ?? ($_GET['__act'] ?? '');
if ($act==='create' || $act==='update'){
  $id         = intv(post('id',0));
  $jenis      = post('jenis','uh');
  $judul      = post('judul','');
  $deskripsi  = post('deskripsi','');
  $gform_url  = post('gform_url','');
  $durasi     = max(1,intv(post('durasi_menit',45)));
  $mulai_at   = post('mulai_at','');
  $selesai_at = post('selesai_at','');

  $is_embed   = 1;
  $is_active  = 1;
  $grace_sec  = max(0,intv(post('allow_grace_sec',20)));

  if(!preg_match('~^https://(docs\.google\.com|forms\.gle)/~',$gform_url)) exit('URL GForm tidak valid.');

  if ($act==='create'){
    $pengampu_ids = post('pengampu_ids', array());
    if(empty($pengampu_ids) || !is_array($pengampu_ids)) exit('Pilih minimal satu pengampu.');
    foreach($pengampu_ids as $pengampu_id) {
      $pengampu_id = intv($pengampu_id);
      $cek = db_one("SELECT pm.* FROM `pengampu_mapel` pm
                     WHERE pm.id=? AND pm.ta_id=?".($isAdmin?'':" AND pm.guru_user_id=".$myId),
                     array($pengampu_id, $ta_id));
      if(!$cek) continue;

      if ($HAS_GRACE) {
        db_exec("INSERT INTO `ujian_gform`
          (ta_id,pengampu_id,kelas_id,mapel_id,guru_user_id,jenis,judul,deskripsi,gform_url,durasi_menit,mulai_at,selesai_at,is_embed,is_active,allow_grace_sec)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",array(
            $ta_id, $pengampu_id, (int)$cek['kelas_id'], (int)$cek['mapel_id'], (int)$cek['guru_user_id'],
            $jenis, $judul, $deskripsi, $gform_url, $durasi, $mulai_at, $selesai_at, $is_embed, $is_active, $grace_sec
        ));
      } else {
        db_exec("INSERT INTO `ujian_gform`
          (ta_id,pengampu_id,kelas_id,mapel_id,guru_user_id,jenis,judul,deskripsi,gform_url,durasi_menit,mulai_at,selesai_at,is_embed,is_active)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",array(
            $ta_id, $pengampu_id, (int)$cek['kelas_id'], (int)$cek['mapel_id'], (int)$cek['guru_user_id'],
            $jenis, $judul, $deskripsi, $gform_url, $durasi, $mulai_at, $selesai_at, $is_embed, $is_active
        ));
      }
    }
    header('Location: ujian_gform.php?alert=saved'); exit;
  } else {
    if($id<=0) exit('ID tidak valid.');
    $pengampu_id = intv(post('pengampu_id',0));

    $rowU = db_one("SELECT * FROM `ujian_gform` WHERE $UJ_PK=? LIMIT 1",array($id));
    if(!$rowU) exit('Data tidak ditemukan.');
    if(!$isAdmin && (int)$rowU['guru_user_id']!==$myId) exit('Akses ditolak.');

    $cek = db_one("SELECT pm.* FROM `pengampu_mapel` pm
                   WHERE pm.id=? AND pm.ta_id=?".($isAdmin?'':" AND pm.guru_user_id=".$myId),
                   array($pengampu_id, $ta_id));
    if(!$cek) exit('Pengampu tidak valid / akses ditolak.');

    if ($HAS_GRACE) {
      db_exec("UPDATE `ujian_gform`
        SET pengampu_id=?,kelas_id=?,mapel_id=?,guru_user_id=?,jenis=?,judul=?,deskripsi=?,gform_url=?,durasi_menit=?,mulai_at=?,selesai_at=?,is_embed=?,is_active=?,allow_grace_sec=?
        WHERE $UJ_PK=?",array(
          $pengampu_id, (int)$cek['kelas_id'], (int)$cek['mapel_id'], (int)$cek['guru_user_id'],
          $jenis, $judul, $deskripsi, $gform_url, $durasi, $mulai_at, $selesai_at, $is_embed, $is_active, $grace_sec,
          $id
      ));
    } else {
      db_exec("UPDATE `ujian_gform`
        SET pengampu_id=?,kelas_id=?,mapel_id=?,guru_user_id=?,jenis=?,judul=?,deskripsi=?,gform_url=?,durasi_menit=?,mulai_at=?,selesai_at=?,is_embed=?,is_active=?
        WHERE $UJ_PK=?",array(
          $pengampu_id, (int)$cek['kelas_id'], (int)$cek['mapel_id'], (int)$cek['guru_user_id'],
          $jenis, $judul, $deskripsi, $gform_url, $durasi, $mulai_at, $selesai_at, $is_embed, $is_active,
          $id
      ));
    }
    header('Location: ujian_gform.php?alert=updated'); exit;
  }
}
if ($act==='delete'){
  $id=(int)($_GET['id'] ?? 0);
  if($id<=0) exit('ID tidak valid.');
  $rowU = db_one("SELECT * FROM `ujian_gform` WHERE $UJ_PK=? LIMIT 1",array($id));
  if(!$rowU) exit('Data tidak ditemukan.');
  if(!$isAdmin && (int)$rowU['guru_user_id']!==$myId) exit('Akses ditolak.');
  db_exec("DELETE FROM `ujian_gform` WHERE $UJ_PK=? LIMIT 1",array($id));
  header('Location: ujian_gform.php?alert=deleted'); exit;
}

/* ====== List data untuk tabel utama ====== */
$whereList = $isAdmin ? "1=1" : ("uj.guru_user_id=".$myId);
$DATA = db_all("
  SELECT 
    uj.*, uj.$UJ_PK AS id, 
    m.mapel_kode, m.mapel_nama, k.kelas_nama, u.user_nama
  FROM `ujian_gform` uj
  JOIN `mapel` m ON m.mapel_id=uj.mapel_id
  JOIN `kelas` k ON k.kelas_id=uj.kelas_id
  JOIN `user`  u ON u.user_id=uj.guru_user_id
  WHERE uj.ta_id=? AND {$whereList}
  ORDER BY uj.mulai_at DESC, uj.$UJ_PK DESC
",array($ta_id));

/* ====== [ADDED] Controls: search + per-page (terintegrasi pagination) ====== */
$allowed_per = array(10,25,50,100);
$per_page = (int)($_GET['per'] ?? ($_GET['per_page'] ?? 10));
if (!in_array($per_page, $allowed_per, true)) $per_page = 10;

$search = trim((string)($_GET['q'] ?? ''));
$total_all = count($DATA);
$DATA_FILTERED = $DATA;

if ($search !== '') {
  $q = mb_strtolower($search, 'UTF-8');
  $DATA_FILTERED = array_values(array_filter($DATA, function($d) use ($q){
    $hay = mb_strtolower(
      ($d['kelas_nama'] ?? '').' '.
      ($d['mapel_kode'] ?? '').' '.
      ($d['mapel_nama'] ?? '').' '.
      ($d['jenis'] ?? '').' '.
      ($d['judul'] ?? '').' '.
      ($d['deskripsi'] ?? '').' '.
      ($d['user_nama'] ?? ''),
      'UTF-8'
    );
    return (strpos($hay, $q) !== false);
  }));
}

$total_filtered = count($DATA_FILTERED);

/* ====== Pagination (mengikuti hasil filter) ====== */
$page     = max(1, (int)($_GET['page'] ?? 1));
$pages    = max(1, (int)ceil($total_filtered / max(1,$per_page)));
if ($page > $pages) $page = $pages;
$offset   = ($page - 1) * $per_page;
$DATA_PAGE = array_slice($DATA_FILTERED, $offset, $per_page);

/* ====== VIEW ====== */
$title    = 'Exam GForm';
$judul    = 'Exam GForm';
$subjudul = 'Manajemen Ujian Online';
$menu     = 'ujian_gform';
$JENIS_UJIAN = array('uh'=>'Ulangan Harian','pts'=>'PTS/STS','pas'=>'PAS/SAS','pat'=>'PAT/SAT','praktik'=>'Praktik','remedial'=>'Remedial','susulan'=>'Susulan');
$JENIS_BADGE = array('uh'=>'primary','pts'=>'success','pas'=>'warning','pat'=>'info','praktik'=>'default','remedial'=>'danger','susulan'=>'default');

include __DIR__.'/header.php'; ?>

<style>
/* (CSS tetap, tidak diubah) */
.page-title .ti.glogo{
  background: #fff url('../assets/img/google-g.png') no-repeat center;
  background-size: 20px 20px;
  border: 1px solid #e5e7eb;
  color: transparent;
  box-shadow:0 2px 6px rgba(0,0,0,.06);
}
.page-hero{position:relative;border:0;background:linear-gradient(135deg,#0b1220 0%,#111827 45%,#0f172a 100%);color:#e5e7eb;overflow:hidden;border-radius:10px;box-shadow:0 10px 24px rgba(2,6,23,.35)}
.page-hero .box-body{padding:16px 16px 12px}
.page-title{font-weight:900;font-size:26px;letter-spacing:.2px;margin:0 0 6px;display:flex;align-items:center;gap:10px;color:#0f172a}
.page-title .ti{width:32px;height:32px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#eaf2ff;border:1px solid #cfe1ff;color:#2563eb;box-shadow:0 2px 6px rgba(37,99,235,.12)}
.page-title .title-badge{display:inline-flex;align-items:center;gap:6px;background:#eff6ff;color:#1d4ed8;font-weight:700;font-size:12px;padding:4px 10px;border-radius:999px;border:1px solid #bfdbfe}
.page-title .title-badge i{color:#3b82f6}
.hero-title{font-weight:800;letter-spacing:.2px;display:flex;align-items:center;gap:8px}
.hero-title .ic{width:32px;height:32px;border-radius:10px;background:rgba(255,255,255,.08);display:inline-flex;align-items:center;justify-content:center}
.hero-title .ic i{font-size:15px;color:#c7d2fe}
.hero-chips{display:flex;gap:6px;flex-wrap:wrap;margin:8px 0 6px}
.badge-chip{display:inline-flex;align-items:center;gap:6px;font-weight:700;padding:2px 8px;border-radius:999px;font-size:10px;box-shadow:0 2px 10px rgba(0,0,0,.25)}
.badge-chip i{font-size:10.5px}
.chip-info{background:#0ea5e933;color:#93c5fd;border:1px solid #60a5fa55}
.chip-guard{background:#ef444433;color:#fecaca;border:1px solid #f8717155}
.chip-speed{background:#a855f733;color:#e9d5ff;border:1px solid #c084fc55}
.chip-safe{background:#22c55e33;color:#bbf7d0;border:1px solid #4ade8055}
.hero-features{margin-top:6px;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}
.feat{display:flex;gap:8px;align-items:flex-start;border-radius:12px;padding:12px;border:1px solid rgba(148,163,184,.25);box-shadow:0 4px 10px rgba(0,0,0,.25);transition:transform .15s,box-shadow .2s}
.feat:hover{transform:translateY(-1px);box-shadow:0 8px 14px rgba(0,0,0,.35)}
.feat .icon{width:26px;height:26px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex:0 0 26px;color:#fff}
.feat-title{font-weight:800;font-size:13.2px;line-height:1.05;color:#0f172a}
.feat-text{font-size:11.6px;color:#334155;margin-top:2px}
.feat.c1{background:#eff6ff;border-color:#bfdbfe}.feat.c1 .icon{background:#2563eb}
.feat.c2{background:#ffe4e6;border-color:#fecdd3}.feat.c2 .icon{background:#e11d48}
.feat.c3{background:#ecfdf5;border-color:#a7f3d0}.feat.c3 .icon{background:#10b981}
.feat.c4{background:#eef2ff;border-color:#c7d2fe}.feat.c4 .icon{background:#6366f1}
.feat.c5{background:#fff7ed;border-color:#fed7aa}.feat.c5 .icon{background:#f59e0b}
.feat.c6{background:#f5f3ff;border-color:#ddd6fe}.feat.c6 .icon{background:#8b5cf6}
.callout-soft{background:#e7f0ff;border-left:4px solid #60a5fa;color:#111827}
.callout-mini{background:linear-gradient(90deg,#e7f0ff 0%,#f5f3ff 100%);border-left:5px solid #60a5fa;border-radius:12px;padding:10px 12px}
.callout-mini .mini-title{display:flex;align-items:center;gap:6px;margin:0 0 4px;font-weight:800;font-size:12px;letter-spacing:.2px;color:#0f172a}
.callout-mini .mini-title i{color:#1d4ed8}
.callout-mini .mini-text{margin:0;font-size:12px;color:#475569}
.table-ujian>thead>tr>th{border-bottom:1px solid #e2e8f0;background:#f8fafc;color:#334155}
.table-ujian tbody tr:hover{background:#f0f9ff}
.table-ujian .aksi .btn{margin-left:4px}
.label.status{font-weight:700;padding:6px 8px;border-radius:6px;display:inline-flex;align-items:center;gap:6px}
.status-upcoming{background:#e0f2fe;color:#0369a1}
.status-ongoing{background:#dcfce7;color:#065f46}
.status-finished{background:#e5e7eb;color:#374151}
.box .box-title-strong{font-weight:800;letter-spacing:.2px}
.nav-tabs.ujian-tabs > li > a { font-weight:700; }
.nav-tabs.ujian-tabs > li.active > a,
.nav-tabs.ujian-tabs > li.active > a:focus,
.nav-tabs.ujian-tabs > li.active > a:hover { background:#ffffff;border-top:3px solid #2563eb; }
.tab-pane { padding-top:12px; }
.monitor-hero{position:relative;border:0;background:linear-gradient(135deg,#0ea5e9 0%,#2563eb 50%,#7c3aed 100%);color:#fff;border-radius:10px;box-shadow:0 10px 24px rgba(2,6,23,.35)}
.monitor-hero .box-body{padding:18px 18px 14px}
.monitor-title{display:flex;align-items:center;gap:10px;font-weight:900;font-size:18px;letter-spacing:.2px}
.monitor-title .ic{width:36px;height:36px;border-radius:12px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center}
.monitor-sub{opacity:.95;font-weight:600;font-size:11px;margin-top:4px}
.monitor-chips{display:flex;gap:6px;flex-wrap:wrap;margin:10px 0 4px}
.monitor-chip{display:inline-flex;align-items:center;gap:6px;font-weight:700;padding:4px 10px;border-radius:999px;font-size:11px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.35)}
.monitor-chip i{font-size:12px}
.monitor-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-top:12px}
.monitor-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 4px 14px rgba(0,0,0,.06);padding:14px;transition:transform .15s,box-shadow .2s}
.monitor-card:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(0,0,0,.1)}
.monitor-card .mc-title{display:flex;align-items:center;gap:8px;font-weight:800;color:#0f172a;margin-bottom:6px}
.monitor-card .mc-text{color:#475569;font-size:12.5px}
.kbd{display:inline-block;padding:2px 6px;border:1px solid #cbd5e1;border-bottom-width:2px;border-radius:6px;background:#f8fafc;font-weight:700;font-size:11px;color:#0f172a}
.monitor-cta{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:12px}
.btn-cta{font-weight:800;border-radius:12px;padding:11px 16px}
.btn-cta-primary{
  background:linear-gradient(135deg,#22c55e 0%, #16a34a 60%, #0f766e 100%);
  color:#fff;border:none;
  box-shadow:0 8px 22px rgba(22,163,74,.28);
}
.btn-cta-primary:hover{filter:brightness(1.05);box-shadow:0 10px 28px rgba(22,163,74,.35)}
.btn-ghost{background:#fff;border:1px solid #e5e7eb}
.small-muted{font-size:12px;color:#64748b}

/* [ADDED] mini styling controls "Tampil" & "Cari" */
.table-controls{margin:6px 0 10px}
.table-controls .form-inline{display:flex;align-items:center;gap:6px}
.table-controls label{margin:0 4px 0 0;font-weight:600;color:#475569}
.table-controls .pull-right .form-inline{justify-content:flex-end}
</style>

<div class="content-wrapper">
  <section class="content-header">
    <h1 class="page-title">
      <span class="ti glogo" aria-label="Google"></span>
      Exam GForm
      <span class="title-badge"><i class="fa fa-sliders"></i> <?= e($subjudul) ?></span>
    </h1>
    <ol class="breadcrumb">
      <li><a href="index.php"><i class="fa fa-home"></i> Home</a></li>
      <li class="active">Exam GForm</li>
    </ol>
  </section>

  <section class="content">

    <?php if(isset($_GET['alert'])): ?>
      <?php if($_GET['alert']==='saved'): ?>
        <div class="alert alert-success"><i class="fa fa-check"></i> Ujian berhasil disimpan.</div>
      <?php elseif($_GET['alert']==='updated'): ?>
        <div class="alert alert-info"><i class="fa fa-check"></i> Ujian berhasil diperbarui.</div>
      <?php elseif($_GET['alert']==='deleted'): ?>
        <div class="alert alert-warning"><i class="fa fa-trash"></i> Ujian berhasil dihapus.</div>
      <?php endif; ?>
    <?php endif; ?>

    <!-- HERO -->
    <div class="box page-hero">
      <div class="box-body">
        <div class="hero-title">
          <div>
            <div style="font-size:18px;line-height:1;">GoogleForm jadi CBT</div>
            <div class="monitor-sub" style="color:#c7d2fe;">Jadwal, durasi, guard fullscreen, dan panel guru</div>
          </div>
        </div>

        <div class="hero-chips">
          <span class="badge-chip chip-info"  data-toggle="tooltip" title="Integrasi Form + CBT"><i class="fa fa-plug"></i> Integrasi</span>
          <span class="badge-chip chip-guard" data-toggle="tooltip" title="Guard fullscreen & anti switching"><i class="fa fa-shield"></i> Guard</span>
          <span class="badge-chip chip-speed" data-toggle="tooltip" title="Proses cepat & praktis"><i class="fa fa-bolt"></i> Praktis</span>
          <span class="badge-chip chip-safe"  data-toggle="tooltip" title="Minim risiko kehilangan jawaban"><i class="fa fa-heart"></i> Aman</span>
        </div>

        <!-- ALUR (kartu ringkasan) -->
        <div class="hero-features">
          <div class="feat c1"><div class="icon"><i class="fa fa-hourglass-half"></i></div><div><div class="feat-title">Atur Jadwal</div><div class="feat-text">Mulai–Selesai + timer</div></div></div>
          <div class="feat c2"><div class="icon"><i class="fa fa-shield"></i></div><div><div class="feat-title">Pengawas Layar</div><div class="feat-text">Guard fullscreen & anti-switch</div></div></div>
          <div class="feat c3"><div class="icon"><i class="fa fa-users"></i></div><div><div class="feat-title">Pilih Kelas/Mapel</div><div class="feat-text">Multi-select cepat</div></div></div>
          <div class="feat c4"><div class="icon"><i class="fa fa-link"></i></div><div><div class="feat-title">Mode Tampilan</div><div class="feat-text">Embed atau tab baru</div></div></div>
          <div class="feat c5"><div class="icon"><i class="fa fa-shield"></i></div><div><div class="feat-title">Toleransi Koneksi</div><div class="feat-text">Grace window (detik)</div></div></div>
          <div class="feat c6"><div class="icon"><i class="fa fa-bar-chart"></i></div><div><div class="feat-title">Tinjau & Ekspor</div><div class="feat-text">Panel review + CSV</div></div></div>
        </div>
      </div>
    </div>

    <!-- ====== TAB MENU ====== -->
    <ul class="nav nav-tabs ujian-tabs" role="tablist">
      <li role="presentation" class="active">
        <a href="#tab-kelola" aria-controls="tab-kelola" role="tab" data-toggle="tab">
          <i class="fa fa-wrench"></i> Kelola Ujian
        </a>
      </li>
      <li role="presentation">
        <a href="#tab-monitoring" aria-controls="tab-monitoring" role="tab" data-toggle="tab">
          <i class="fa fa-desktop"></i> Pusat Kendali (Live)
        </a>
      </li>
    </ul>

    <div class="tab-content">
      <!-- =================== TAB: KELOLA UJIAN =================== -->
      <div role="tabpanel" class="tab-pane active" id="tab-kelola">

        <div class="row">
          <!-- FORM (kiri) -->
          <div class="col-md-4">
            <?php $editId=(int)($_GET['edit'] ?? 0); $editRow=null; foreach($DATA as $d){ if((int)$d['id']===$editId){ $editRow=$d; break; } } ?>
            <div id="form-ujian" class="box box-<?= $editRow?'warning':'success'; ?>">
              <div class="box-header with-border">
                <h3 class="box-title">
                  <i class="fa fa-<?= $editRow?'edit':'plus-circle'; ?>"></i>
                  <span class="box-title-strong"><?= $editRow?'Edit':'Tambah'; ?> Ujian</span>
                </h3>
              </div>
              <form class="form-horizontal" method="post" action="ujian_gform.php" autocomplete="off">
                <div class="box-body">
                  <input type="hidden" name="__act" value="<?= $editRow?'update':'create' ?>">
                  <?php if($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>"><?php endif; ?>

                  <?php if (!$editRow): ?>
                    <div class="callout-mini">
                      <div class="mini-title"><i class="icon fa fa-bullhorn"></i> Ujian Kolektif</div>
                      <p class="mini-text">Pilih lebih dari satu Kelas/Mapel untuk membuat beberapa ujian sekaligus dengan pengaturan yang sama.</p>
                    </div>
                  <?php endif; ?>

                  <div class="form-group">
                    <label class="col-sm-3 control-label">Pengampu</label>
                    <div class="col-sm-9">
                      <?php if ($editRow): ?>
                        <select name="pengampu_id" class="form-control" required>
                          <option value="">-- pilih --</option>
                          <?php foreach($pengampu_by_kelas as $kelas => $list_p): ?>
                            <optgroup label="<?= e($kelas) ?>">
                            <?php foreach($list_p as $pm):
                              $opt = $pm['kelas_nama']." • ".$pm['mapel_kode']." (".$pm['mapel_nama'].") • ".$pm['user_nama'];
                              $sel = ($editRow && (int)$editRow['pengampu_id']===(int)$pm['pengampu_id'])?'selected':''; ?>
                              <option value="<?= (int)$pm['pengampu_id'] ?>" <?= $sel ?>><?= e($opt) ?></option>
                            <?php endforeach; ?>
                            </optgroup>
                          <?php endforeach; ?>
                        </select>
                      <?php else: ?>
                        <select name="pengampu_ids[]" id="select-pengampu" class="form-control" multiple="multiple" required>
                          <?php foreach($pengampu_by_kelas as $kelas => $list_p): ?>
                            <optgroup label="<?= e($kelas) ?>">
                            <?php foreach($list_p as $pm):
                              $opt = $pm['kelas_nama']." • ".$pm['mapel_kode']." (".$pm['mapel_nama'].") • ".$pm['user_nama']; ?>
                              <option value="<?= (int)$pm['pengampu_id'] ?>"><?= e($opt) ?></option>
                            <?php endforeach; ?>
                            </optgroup>
                          <?php endforeach; ?>
                        </select>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="form-group">
                    <label class="col-sm-3 control-label">Jenis</label>
                    <div class="col-sm-9">
                      <select name="jenis" class="form-control">
                        <?php foreach($JENIS_UJIAN as $k=>$v): $sel=($editRow && $editRow['jenis']===$k)?'selected':''; ?>
                          <option value="<?= e($k) ?>" <?= $sel ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>

                  <div class="form-group">
                    <label class="col-sm-3 control-label">Judul</label>
                    <div class="col-sm-9">
                      <input type="text" name="judul" class="form-control" maxlength="150" placeholder="Contoh: PTS Ganjil 2025" value="<?= e($editRow['judul'] ?? '') ?>" required>
                    </div>
                  </div>

                  <div class="form-group">
                    <label class="col-sm-3 control-label">URL GForm</label>
                    <div class="col-sm-9">
                      <input type="url" name="gform_url" class="form-control" placeholder="https://forms.gle/..." value="<?= e($editRow['gform_url'] ?? '') ?>" required>
                    </div>
                  </div>

                  <div class="form-group">
                    <label class="col-sm-3 control-label">Durasi</label>
                    <div class="col-sm-9">
                      <div class="input-group">
                        <input type="number" name="durasi_menit" min="1" class="form-control" value="<?= (int)($editRow['durasi_menit'] ?? 60) ?>">
                        <span class="input-group-addon">menit</span>
                      </div>
                    </div>
                  </div>

                  <div class="form-group">
                    <label class="col-sm-3 control-label">Waktu</label>
                    <div class="col-sm-9">
                      <input type="datetime-local" name="mulai_at" class="form-control" value="<?= $editRow?e(str_replace(' ','T',$editRow['mulai_at'])):'' ?>" required style="margin-bottom:5px;">
                      <input type="datetime-local" name="selesai_at" class="form-control" value="<?= $editRow?e(str_replace(' ','T',$editRow['selesai_at'])):'' ?>" required>
                      <p class="help-block">Atas: Waktu Mulai, Bawah: Waktu Selesai</p>
                    </div>
                  </div>

                  <div class="form-group">
                    <label class="col-sm-3 control-label">Lanjutan</label>
                    <div class="col-sm-9">
                      <div class="adv-card">
                        <label>Toleransi reconnect (grace) <span class="help-muted">(detik)</span></label>
                        <div class="input-group" style="max-width:240px;">
                          <input type="number" min="0" class="form-control" name="allow_grace_sec" value="<?= (int)($editRow['allow_grace_sec'] ?? 20) ?>">
                          <span class="input-group-addon"><i class="fa fa-shield"></i></span>
                        </div>
                        <div class="help-muted" style="margin-top:6px;">
                          Saat koneksi turun/keluar fullscreen sesaat, siswa diberi toleransi beberapa detik.
                        </div>
                      </div>
                    </div>
                  </div>

                </div>
                <div class="box-footer text-right">
                  <a href="ujian_gform.php" class="btn btn-default"><i class="fa fa-refresh"></i> Batal</a>
                  <button class="btn btn-<?= $editRow?'warning':'success'; ?>" type="submit">
                    <i class="fa fa-<?= $editRow?'save':'plus'; ?>"></i> <?= $editRow ? 'Update Ujian' : 'Simpan Ujian'; ?>
                  </button>
                </div>
              </form>
            </div>
          </div>

          <!-- DAFTAR (kanan) -->
          <div class="col-md-8">
            <div class="box box-primary">
              <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-list"></i> Daftar Ujian Terjadwal</h3>
                <div class="box-tools pull-right"><span class="badge bg-blue">Total: <?= $total_all ?> Ujian</span></div>
              </div>
              <div class="box-body table-responsive">

                <!-- [ADDED] Controls: Tampil & Cari -->
                <div class="row table-controls">
                  <div class="col-sm-6">
                    <form id="ctl-per" class="form-inline" method="get" action="ujian_gform.php">
                      <input type="hidden" name="q" value="<?= e($search) ?>">
                      <input type="hidden" name="page" value="1">
                      <label for="per">Tampil</label>
                      <select name="per" id="per" class="form-control input-sm" style="width:auto;display:inline-block;">
                        <?php foreach($allowed_per as $opt): ?>
                          <option value="<?= $opt ?>" <?= $opt===$per_page?'selected':'' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                      </select>
                      <span>data</span>
                    </form>
                  </div>
                  <div class="col-sm-6">
                    <div class="pull-right">
                      <form id="ctl-search" class="form-inline" method="get" action="ujian_gform.php" autocomplete="off">
                        <input type="hidden" name="per" value="<?= (int)$per_page ?>">
                        <input type="hidden" name="page" value="1">
                        <label for="q">Cari:</label>
                        <input type="text" id="q" name="q" value="<?= e($search) ?>" class="form-control input-sm" style="width:230px;" placeholder="kelas / mapel / judul / guru ...">
                      </form>
                    </div>
                  </div>
                </div>
                <!-- /controls -->

                <table class="table table-hover table-striped table-ujian">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Kelas &amp; Mapel</th>
                      <th>Jenis</th>
                      <th>Judul</th>
                      <th>Jadwal</th>
                      <th>Durasi</th>
                      <th>Status</th>
                      <th class="text-right">Aksi</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php
                    $now = time();
                    foreach($DATA_PAGE as $k=>$d):
                      $i = $offset + $k + 1;
                      $mulaiTs   = strtotime($d['mulai_at']);
                      $selesaiTs = strtotime($d['selesai_at']);
                      $status = 'upcoming';
                      if ($now >= $mulaiTs && $now <= $selesaiTs) $status = 'ongoing';
                      elseif ($now > $selesaiTs) $status = 'finished';
                  ?>
                    <tr>
                      <td><?= $i ?></td>
                      <td>
                        <b class="text-primary"><?= e($d['kelas_nama']) ?></b><br>
                        <small class="text-muted"><?= e($d['mapel_kode']) ?> - <?= e($d['mapel_nama']) ?></small>
                      </td>
                      <td>
                        <span class="label label-<?= $JENIS_BADGE[$d['jenis']] ?? 'default' ?>" data-toggle="tooltip" title="<?= e($JENIS_UJIAN[$d['jenis']] ?? $d['jenis']) ?>">
                          <?= e(strtoupper($JENIS_UJIAN[$d['jenis']] ?? $d['jenis'])) ?>
                        </span>
                      </td>
                      <td>
                        <?= e($d['judul']) ?><br>
                        <?php if(!empty($d['deskripsi'])): ?>
                          <small class="text-muted"><i class="fa fa-info-circle"></i> <?= e($d['deskripsi']) ?></small><br>
                        <?php endif; ?>
                        <small class="text-muted">oleh: <?= e($d['user_nama']) ?></small>
                      </td>
                      <td>
                        <small>Mulai: <code><?= e(date('d/m/Y H:i', $mulaiTs)) ?></code></small><br>
                        <small>Selesai: <code><?= e(date('d/m/Y H:i', $selesaiTs)) ?></code></small>
                      </td>
                      <td><span class="badge bg-purple" data-toggle="tooltip" title="Durasi ujian"><?= (int)$d['durasi_menit'] ?> min</span></td>
                      <td>
                        <?php if ($status==='ongoing'): ?>
                          <span class="label status status-ongoing"><i class="fa fa-play-circle"></i> Berlangsung</span>
                        <?php elseif ($status==='upcoming'): ?>
                          <span class="label status status-upcoming"><i class="fa fa-hourglass-half"></i> Akan Datang</span>
                        <?php else: ?>
                          <span class="label status status-finished"><i class="fa fa-flag-checkered"></i> Selesai</span>
                        <?php endif; ?>
                        <?php if ($d['is_active']): ?>
                          <span class="badge bg-green" data-toggle="tooltip" title="Ujian aktif"><i class="fa fa-check"></i></span>
                        <?php else: ?>
                          <span class="badge bg-red" data-toggle="tooltip" title="Ujian nonaktif"><i class="fa fa-ban"></i></span>
                        <?php endif; ?>
                      </td>
                      <td class="aksi text-right" style="min-width:190px;">
                        <a class="btn btn-xs btn-primary" href="<?= e(gformp_preview_url((int)$d['id'])) ?>" target="_blank" rel="noopener" data-toggle="tooltip" title="Preview seperti tampilan siswa">
                          <i class="fa fa-eye"></i> Preview
                        </a>
                        <a class="btn btn-xs btn-warning" href="ujian_gform.php?edit=<?= (int)$d['id'] ?>#form-ujian" data-toggle="tooltip" title="Edit pengaturan">
                          <i class="fa fa-pencil"></i>
                        </a>
                        <a class="btn btn-xs btn-danger" href="ujian_gform.php?__act=delete&id=<?= (int)$d['id'] ?>" onclick="return confirm('Anda yakin ingin menghapus ujian ini?')" data-toggle="tooltip" title="Hapus">
                          <i class="fa fa-trash"></i>
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; if(empty($DATA_PAGE)): ?>
                    <tr><td colspan="8" class="text-center text-muted" style="padding:20px;">
                      <i class="fa fa-folder-open-o fa-3x"></i><br>
                      <?= $search!=='' ? 'Tidak ada data yang cocok dengan pencarian.' : 'Belum ada data ujian yang dibuat.' ?>
                    </td></tr>
                  <?php endif; ?>
                  </tbody>
                </table>

                <?php if ($pages > 1): ?>
                  <?php
                    $qParam   = $search!=='' ? '&q='.urlencode($search) : '';
                    $perParam = '&per='.(int)$per_page;
                  ?>
                  <nav aria-label="Paging Ujian" class="text-center">
                    <ul class="pagination pagination-sm" style="margin:10px 0 0;">
                      <li class="<?= $page<=1?'disabled':'' ?>">
                        <a href="ujian_gform.php?page=<?= max(1,$page-1) ?><?= $perParam.$qParam ?>" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a>
                      </li>
                      <?php for($p=1;$p<=$pages;$p++): ?>
                        <li class="<?= $p==$page?'active':'' ?>"><a href="ujian_gform.php?page=<?= $p ?><?= $perParam.$qParam ?>"><?= $p ?></a></li>
                      <?php endfor; ?>
                      <li class="<?= $page>=$pages?'disabled':'' ?>">
                        <a href="ujian_gform.php?page=<?= min($pages,$page+1) ?><?= $perParam.$qParam ?>" aria-label="Next"><span aria-hidden="true">&raquo;</span></a>
                      </li>
                    </ul>
                  </nav>
                <?php endif; ?>

              </div>
            </div>
          </div>
        </div>

      </div><!-- /tab-kelola -->

      <!-- =================== TAB: PUSAT KENDALI (LIVE) =================== -->
      <div role="tabpanel" class="tab-pane" id="tab-monitoring">

        <div class="box monitor-hero">
          <div class="box-body">
            <div class="monitor-title">
              <span class="ic"><i class="fa fa-desktop"></i></span>
              <div>
                <div>Pusat Kendali Ujian (Live)</div>
                <div class="monitor-sub">Pantau progres, reset sesi, lihat detail peserta, kelola pelanggaran, dan kontrol sesi secara real-time.</div>
              </div>
            </div>

            <div class="monitor-chips">
              <span class="monitor-chip"><i class="fa fa-bolt"></i> Real-time</span>
              <span class="monitor-chip"><i class="fa fa-shield"></i> Guard & Pelanggaran</span>
              <span class="monitor-chip"><i class="fa fa-columns"></i> Detail Attempt</span>
              <span class="monitor-chip"><i class="fa fa-download"></i> Export CSV</span>
            </div>
          </div>
        </div>

        <?php if(!$__has_attempt): ?>
          <div class="alert alert-warning">
            <i class="fa fa-exclamation-triangle"></i>
            <strong>Tabel Attempt belum tersedia.</strong> Fitur pusat kendali memerlukan tabel <code>ujian_gform_attempt</code>.
          </div>
        <?php endif; ?>

        <?php if($total_all<=0): ?>
          <div class="alert alert-info">
            <i class="fa fa-info-circle"></i>
            Belum ada ujian yang dibuat. Silakan buat ujian pada tab <strong>Kelola Ujian</strong> terlebih dahulu.
          </div>
        <?php endif; ?>

        <div class="monitor-grid">
          <div class="monitor-card">
            <div class="mc-title"><i class="fa fa-question-circle text-primary"></i> Apa itu Pusat Kendali?</div>
            <div class="mc-text">
              Layar kontrol untuk memantau attempt siswa secara langsung—siapa yang <em>online</em>, sedang mengerjakan, sudah selesai, atau terkena diskualifikasi.
            </div>
          </div>
          <div class="monitor-card">
            <div class="mc-title"><i class="fa fa-check-square-o text-success"></i> Kapan digunakan?</div>
            <div class="mc-text">
              Saat ujian berjalan. Buka pada layar terpisah (proyektor/laptop pengawas) agar pengawasan lebih <strong>efektif</strong> dan <strong>responsif</strong>.
            </div>
          </div>
          <div class="monitor-card">
            <div class="mc-title"><i class="fa fa-wrench text-info"></i> Yang perlu disiapkan</div>
            <div class="mc-text">
              Pastikan ujian <strong>aktif</strong>, siswa memiliki akses, dan koneksi stabil. Opsi <span class="kbd">allow_grace_sec</span> memberi toleransi putus koneksi singkat.
            </div>
          </div>
          <div class="monitor-card">
            <div class="mc-title"><i class="fa fa-lightbulb-o text-warning"></i> Tips singkat</div>
            <div class="mc-text">
              Gunakan <em>auto-refresh</em> bawaan; notifikasi suara muncul saat ada attempt baru. Sediakan <strong>Bulk Reset</strong> bila diperlukan.
            </div>
          </div>
        </div>

        <div class="monitor-cta">
          <a href="monitoring_attempt_live.php" target="_blank" rel="noopener" class="btn btn-cta btn-cta-primary">
            <i class="fa fa-external-link"></i> Buka Pusat Kendali (Live)
          </a>
        </div>

        <div class="box" style="margin-top:12px;">
          <div class="box-body">
            <strong>Bagaimana alurnya?</strong>
            <ol style="margin-top:6px;padding-left:18px;">
              <li>Buat & jadwalkan ujian di tab <strong>Kelola Ujian</strong>.</li>
              <li>Saat jam mulai, klik <em>Buka Pusat Kendali (Live)</em> untuk membuka dashboard pada tab/jendela baru.</li>
              <li>Pantau status peserta, pelanggaran, dan progres—serta lakukan <em>reset</em> atau <em>diskualifikasi</em> bila diperlukan.</li>
            </ol>
          </div>
        </div>

      </div><!-- /tab-monitoring -->
    </div><!-- /tab-content -->

  </section>
</div>

<?php include __DIR__.'/footer.php'; ?>

<script>
$(function () {
  if ($.fn.tooltip) { $('[data-toggle="tooltip"]').tooltip({container:'body'}); }
  if ($('#select-pengampu').length && $.fn.select2) {
    $('#select-pengampu').select2({ placeholder: 'Cari & pilih kelas/mapel...', theme: 'bootstrap', width: '100%' });
  }

  // [ADDED] Auto-submit controls (tanpa tombol)
  var fPer = document.getElementById('ctl-per');
  if (fPer) {
    fPer.addEventListener('change', function(){ this.submit(); });
  }
  var fSearch = document.getElementById('ctl-search');
  if (fSearch) {
    var q = fSearch.querySelector('input[name="q"]');
    var t;
    q && q.addEventListener('input', function(){
      clearTimeout(t);
      t = setTimeout(function(){ fSearch.submit(); }, 450);
    });
    // enter submit cepat
    q && q.addEventListener('keydown', function(ev){
      if(ev.key === 'Enter'){ ev.preventDefault(); fSearch.submit(); }
    });
  }
});
</script>
