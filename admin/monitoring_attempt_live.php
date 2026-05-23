<?php
// monitoring_attempt_live.php
// Standalone — Panel "Pusat Kendali Ujian (Live)"
// Fokus menampilkan dashboard monitoring attempts (tanpa header/footer eksternal).

header('Content-Type: text/html; charset=utf-8');

// ====== Session cookie hardening (sebelum session_start) ======
if (session_status() === PHP_SESSION_NONE) {
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  @session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
  session_start();
}

date_default_timezone_set('Asia/Jakarta');

// ====== Security headers (anti-iframe, CSP, no-cache, dll) ======
if (!headers_sent()) {
  header('X-Frame-Options: DENY');
  header("Content-Security-Policy: default-src 'self'; connect-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
  header('Referrer-Policy: strict-origin-when-cross-origin');
  header('X-Content-Type-Options: nosniff');
  header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
}

/* ================== KONEKSI DB ================== */
$koneksi = null;
$try = [__DIR__ . '/../koneksi.php', __DIR__ . '/koneksi.php', dirname(__DIR__) . '/koneksi.php'];
foreach ($try as $f) { if (is_file($f)) { require_once $f; break; } }
// Fallback (silakan sesuaikan jika perlu)
if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
  $koneksi = @new mysqli('localhost', 'root', '', 'smpn1gun_epoint');
}
if ($koneksi->connect_errno) {
  http_response_code(500);
  echo "<h1 style='font-family:system-ui,sans-serif;padding:24px;color:#fff;background:#111;margin:0'>Gagal koneksi DB</h1>";
  echo "<pre style='padding:12px;background:#111;color:#9cf;margin:0'>".$koneksi->connect_error."</pre>";
  exit;
}

/* ================== HELPERS ================== */
function esc($s){ return htmlspecialchars($s??'', ENT_QUOTES, 'UTF-8'); }
function table_exists($mysqli,$table){
  $t = $mysqli->real_escape_string($table);
  $r = $mysqli->query("SHOW TABLES LIKE '{$t}'");
  return $r && $r->num_rows>0;
}
function col_exists($mysqli,$table,$col){
  $t = $mysqli->real_escape_string($table);
  $c = $mysqli->real_escape_string($col);
  $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='{$t}' AND column_name='{$c}' LIMIT 1";
  $r = $mysqli->query($sql);
  return $r && $r->num_rows>0;
}
function rows_all($res){ $rows=[]; if($res){ while($r=$res->fetch_assoc()) $rows[]=$r; } return $rows; }

/* ================== AJAX API ================== */
if (isset($_GET['ajax'])) {
  header('Content-Type: application/json; charset=utf-8');

  $act = $_GET['ajax'];
  $need_csrf = in_array($act, ['reset','dq'], true);
  if ($need_csrf) {
    $hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$hdr || !hash_equals($_SESSION['csrf_attempt'] ?? '', $hdr)) { echo json_encode(['ok'=>0,'err'=>'CSRF mismatch']); exit; }
  }

  if (!table_exists($koneksi,'ujian_gform_attempt')) { echo json_encode(['ok'=>0,'err'=>'Tabel ujian_gform_attempt tidak ditemukan']); exit; }

  $hasUjian = table_exists($koneksi,'ujian_gform');
  $hasSiswa = table_exists($koneksi,'siswa');
  $hasKelas = table_exists($koneksi,'kelas');
  $hasMapel = table_exists($koneksi,'mapel');
  $ujPk     = ($hasUjian && col_exists($koneksi,'ujian_gform','id')) ? 'id' : 'ujian_id';
  $hasGrace = $hasUjian && col_exists($koneksi,'ujian_gform','allow_grace_sec');
  $graceDefault = 40;

  /* ---- LIST UJIAN ---- */
  if ($act === 'list_ujian') {
    if ($hasUjian) {
      $sel = "SELECT a.ujian_id,
                     COALESCE(u.judul, CONCAT('Ujian #',a.ujian_id)) AS judul,
                     ".($hasUjian?"u.jenis":"NULL AS jenis").",
                     ".($hasKelas && $hasUjian ? "k.kelas_nama" : "NULL AS kelas_nama").",
                     ".($hasMapel && $hasUjian ? "m.mapel_kode, m.mapel_nama" : "NULL AS mapel_kode, NULL AS mapel_nama").",
                     COUNT(*) AS jml,
                     MAX(COALESCE(a.last_seen_at,a.started_at)) AS recent
              FROM ujian_gform_attempt a
              LEFT JOIN ujian_gform u ON u.{$ujPk}=a.ujian_id ".
              ($hasKelas ? "LEFT JOIN kelas k ON k.kelas_id=u.kelas_id " : "").
              ($hasMapel ? "LEFT JOIN mapel m ON m.mapel_id=u.mapel_id " : "").
              "GROUP BY a.ujian_id, u.jenis, u.judul".
              ($hasKelas ? ", k.kelas_nama" : "").
              ($hasMapel ? ", m.mapel_kode, m.mapel_nama" : "").
              " ORDER BY recent DESC, a.ujian_id DESC";
    } else {
      $sel = "SELECT a.ujian_id,
                     CONCAT('Ujian #',a.ujian_id) AS judul,
                     NULL AS jenis, NULL AS kelas_nama, NULL AS mapel_kode, NULL AS mapel_nama,
                     COUNT(*) AS jml, MAX(COALESCE(a.last_seen_at,a.started_at)) AS recent
              FROM ujian_gform_attempt a
              GROUP BY a.ujian_id
              ORDER BY recent DESC, a.ujian_id DESC";
    }
    $res = $koneksi->query($sel);
    echo json_encode(['ok'=>1,'data'=>rows_all($res)]); exit;
  }

  /* ---- STATUS RINGKASAN ---- */
  if ($act === 'status') {
    $ujian_id = (int)($_GET['ujian_id'] ?? 0);
    if ($ujian_id<=0) { echo json_encode(['ok'=>1,'data'=>null]); exit; }

    $row = ['total'=>0,'sedang'=>0,'selesai'=>0,'dq'=>0,'online'=>0,'pelanggaran_total'=>0,'progress'=>0,'grace'=>$graceDefault];
    if ($hasGrace) {
      $gr = $koneksi->query("SELECT COALESCE(allow_grace_sec,{$graceDefault}) g FROM ujian_gform WHERE {$ujPk}={$ujian_id} LIMIT 1");
      if ($gr && $gr->num_rows) $row['grace'] = (int)$gr->fetch_assoc()['g'];
    }

    $sql = "SELECT COUNT(*) total,
                   SUM(CASE WHEN status='mulai' THEN 1 ELSE 0 END) sedang,
                   SUM(CASE WHEN status='selesai' THEN 1 ELSE 0 END) selesai,
                   SUM(CASE WHEN status='diskualifikasi' THEN 1 ELSE 0 END) dq,
                   SUM(pelanggaran) pelanggaran_total
            FROM ujian_gform_attempt WHERE ujian_id={$ujian_id}";
    $st = $koneksi->query($sql);
    if ($st && $st->num_rows) $row = array_merge($row,$st->fetch_assoc());

    $sqlOn = "SELECT SUM(CASE
                  WHEN status='mulai' AND last_seen_at IS NOT NULL
                       AND TIMESTAMPDIFF(SECOND,last_seen_at,NOW()) <= {$row['grace']}
                  THEN 1 ELSE 0 END) online
              FROM ujian_gform_attempt WHERE ujian_id={$ujian_id}";
    $on = $koneksi->query($sqlOn);
    if ($on && $on->num_rows) $row['online'] = (int)$on->fetch_assoc()['online'];

    $row['progress'] = ($row['total']>0) ? round(($row['selesai']/$row['total'])*100,1) : 0.0;
    echo json_encode(['ok'=>1,'data'=>$row]); exit;
  }

  /* ---- DATA ROWS ---- */
  if ($act === 'rows') {
    $ujian_id = (int)($_GET['ujian_id'] ?? 0);
    if ($ujian_id<=0) { echo json_encode(['ok'=>1,'rows'=>[]]); exit; }

    $status = trim($_GET['status'] ?? '');
    $pel    = trim($_GET['pel'] ?? '');
    $conn   = trim($_GET['conn'] ?? '');
    $q      = trim($_GET['q'] ?? '');

    $where = ["a.ujian_id={$ujian_id}"];
    $grace = $graceDefault;
    if ($hasGrace) {
      $g = $koneksi->query("SELECT COALESCE(allow_grace_sec,{$graceDefault}) g FROM ujian_gform WHERE {$ujPk}={$ujian_id} LIMIT 1");
      if ($g && $g->num_rows) $grace = (int)$g->fetch_assoc()['g'];
    }

    if ($status!=='') { $stEsc = $koneksi->real_escape_string($status); $where[] = "a.status='{$stEsc}'"; }
    if ($pel==='0') $where[]="a.pelanggaran=0";
    elseif ($pel==='1-2') $where[]="(a.pelanggaran BETWEEN 1 AND 2)";
    elseif ($pel==='3+') $where[]="a.pelanggaran>=3";

    if ($conn==='online')      $where[]="a.status='mulai' AND a.last_seen_at IS NOT NULL AND TIMESTAMPDIFF(SECOND,a.last_seen_at,NOW()) <= {$grace}";
    elseif ($conn==='offline') $where[]="(a.last_seen_at IS NULL OR TIMESTAMPDIFF(SECOND,a.last_seen_at,NOW()) > {$grace})";

    $bindLike = '';
    if ($q!=='') {
      $qesc = $koneksi->real_escape_string($q);
      $like = "%{$qesc}%";
      $bindLike = " AND (s.siswa_nama LIKE '{$like}' OR s.siswa_nis LIKE '{$like}' OR k.kelas_nama LIKE '{$like}' OR a.ip_addr LIKE '{$like}' OR a.token LIKE '{$like}' OR a.user_agent LIKE '{$like}')";
    }

    $sql = "SELECT a.id,a.ujian_id,a.siswa_id,a.siswa_user_id,a.started_at,a.finished_at,a.status,a.pelanggaran,a.ip_addr,a.user_agent,a.last_seen_at,
                   ".($hasSiswa ? "s.siswa_nama,s.siswa_nis," : "NULL AS siswa_nama, NULL AS siswa_nis,")."
                   ".($hasKelas && $hasUjian ? "k.kelas_nama" : "NULL AS kelas_nama").",
                   ".($hasUjian ? "COALESCE(u.allow_grace_sec,{$graceDefault}) AS grace" : "{$graceDefault} AS grace")."
            FROM ujian_gform_attempt a ".
            ($hasSiswa ? "LEFT JOIN siswa s ON s.siswa_id=a.siswa_id " : "").
            ($hasUjian ? "LEFT JOIN ujian_gform u ON u.{$ujPk}=a.ujian_id " : "").
            ($hasKelas && $hasUjian ? "LEFT JOIN kelas k ON k.kelas_id=u.kelas_id " : "").
            "WHERE ".implode(' AND ',$where).$bindLike."
            ORDER BY (a.last_seen_at IS NULL) ASC, a.last_seen_at DESC, a.started_at DESC
            LIMIT 2000";
    $res = $koneksi->query($sql);
    echo json_encode(['ok'=>1,'rows'=>rows_all($res),'grace'=>$grace]); exit;
  }

  /* ---- DETAIL ---- */
  if ($act === 'detail') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id<=0) { echo json_encode(['ok'=>0,'err'=>'id kosong']); exit; }
    $sql = "SELECT a.*,".
           ($hasUjian ? "u.judul,u.durasi_menit,COALESCE(u.allow_grace_sec,{$graceDefault}) AS allow_grace_sec," : "NULL AS judul,NULL AS durasi_menit,{$graceDefault} AS allow_grace_sec,").
           ($hasSiswa ? "s.siswa_nama,s.siswa_nis," : "NULL AS siswa_nama,NULL AS siswa_nis,").
           ($hasKelas && $hasUjian ? "k.kelas_nama" : "NULL AS kelas_nama")."
           FROM ujian_gform_attempt a ".
           ($hasUjian ? "LEFT JOIN ujian_gform u ON u.{$ujPk}=a.ujian_id " : "").
           ($hasKelas && $hasUjian ? "LEFT JOIN kelas k ON k.kelas_id=u.kelas_id " : "").
           ($hasSiswa ? "LEFT JOIN siswa s ON s.siswa_id=a.siswa_id " : "").
           "WHERE a.id={$id} LIMIT 1";
    $res = $koneksi->query($sql);
    $row = $res ? $res->fetch_assoc() : null;
    echo json_encode(['ok'=>(int)!!$row,'data'=>$row]); exit;
  }

  /* ---- RESET / DQ (terima JSON body maupun form POST) ---- */
  if ($act === 'reset' || $act === 'dq') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
      $raw = file_get_contents('php://input');
      if ($raw) {
        $j = json_decode($raw, true);
        if (isset($j['id'])) $id = (int)$j['id'];
      }
    }
    if ($id<=0) { echo json_encode(['ok'=>0,'err'=>'id kosong']); exit; }

    if ($act === 'reset') {
      $koneksi->query("DELETE FROM ujian_gform_attempt WHERE id={$id} LIMIT 1");
      echo json_encode(['ok'=>1]); exit;
    }
    if ($act === 'dq') {
      $koneksi->query("UPDATE ujian_gform_attempt SET status='diskualifikasi', finished_at=IFNULL(finished_at,NOW()) WHERE id={$id} LIMIT 1");
      echo json_encode(['ok'=>1]); exit;
    }
  }

  /* ---- EXPORT CSV ---- */
  if ($act === 'export') {
    header_remove('Content-Type');
    header('Content-Type: text/csv; charset=utf-8');
    $ujian_id = (int)($_GET['ujian_id'] ?? 0);
    header("Content-Disposition: attachment; filename=\"attempts_ujian_{$ujian_id}.csv\"");
    $out = fopen('php://output','w');
    fputcsv($out,['id','ujian_id','siswa_id','siswa_user_id','status','pelanggaran','started_at','finished_at','last_seen_at','ip_addr','user_agent']);
    if ($ujian_id>0) {
      $q = $koneksi->query("SELECT id,ujian_id,siswa_id,siswa_user_id,status,pelanggaran,started_at,finished_at,last_seen_at,ip_addr,user_agent FROM ujian_gform_attempt WHERE ujian_id={$ujian_id} ORDER BY id ASC");
      if ($q) { while($r=$q->fetch_assoc()) fputcsv($out,$r); }
    }
    fclose($out); exit;
  }

  echo json_encode(['ok'=>0,'err'=>'Unknown action']); exit;
}

/* ================== NON-AJAX (HALAMAN) ================== */

// === Minify output HTML aman (script/style tidak diubah) ===
function _minify_html_safe($html){
  $html = preg_replace('/^\xEF\xBB\xBF/', '', $html); // strip BOM
  $chunks = preg_split('#(<(?:script|style)\b[^>]*>.*?</(?:script|style)>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
  foreach ($chunks as $i => $chunk) {
    if ($i % 2 === 0) {
      $chunk = preg_replace('/<!--(?!\[if).*?-->/s', '', $chunk); // hapus HTML comments (kecuali conditional)
      $chunk = preg_replace('/>\s+</', '><', $chunk);             // rapatkan antar tag
      $chunk = preg_replace('/\s{2,}/', ' ', $chunk);             // collapse spaces
      $chunks[$i] = $chunk;
    } else {
      $chunks[$i] = trim($chunk);                                 // biarkan script/style apa adanya
    }
  }
  return trim(implode('', $chunks));
}
ob_start('_minify_html_safe');

if (empty($_SESSION['csrf_attempt'])) { $_SESSION['csrf_attempt'] = bin2hex(random_bytes(16)); }
$CSRF = $_SESSION['csrf_attempt'];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Pusat Kendali Ujian (Live)</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#0b1220; --panel:#0e1a2b; --panel2:#12233a; --muted:#9fb3d1; --text:#e6efff; --primary:#3b82f6;
  --green:#22c55e; --red:#ef4444; --amber:#f59e0b; --cyan:#06b6d4; --indigo:#6366f1; --border:#1f2b40;
}
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0; font-family:Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif; color:var(--text);
  background: radial-gradient(1200px 800px at 20% -10%, #13233b 0%, var(--bg) 55%);
}
.wrap{width:min(96vw,1800px); margin:18px auto; padding:0 8px}

/* Header */
.header{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:10px}
.header .titlewrap{display:flex;flex-direction:column;gap:4px}
.header .title{font-weight:900;font-size:22px;display:flex;align-items:center;gap:8px}
.title .ic{width:28px;height:28px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;background:#13243c;color:#8ab4ff}
.subtitle{margin-top:2px;font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px;flex-wrap:wrap}

/* Chip info Google Forms CBT + tooltip */
.tip{position:relative;display:inline-flex;align-items:center;gap:6px;font-size:11px;color:#cfe;background:#0d1a2e;border:1px solid var(--border);padding:4px 8px;border-radius:999px}
.tip .tt{display:none;position:absolute;left:0;top:calc(100% + 8px);background:#0e1a2b;color:#d9e8ff;border:1px solid var(--border);border-radius:8px;padding:8px 10px;font-size:11px;max-width:280px;box-shadow:0 12px 30px rgba(0,0,0,.35);z-index:1000}
.tip:hover .tt{display:block}
.glogo{display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px}
.glogo svg{display:block;width:14px;height:14px}

/* Control bar */
.controlbar{background:var(--panel); border:1px solid var(--border); padding:10px; border-radius:12px; display:flex; gap:8px; align-items:center; flex-wrap:wrap}
.sel,.btn,.input{height:36px;border-radius:9px;border:1px solid var(--border);background:#0c182b;color:var(--text);padding:0 10px}
.sel{min-width:240px}
.input{min-width:200px}
.badge{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border:1px solid var(--border);border-radius:999px;background:#0d1a2e}
.badge.green{background:#0f2a1e;color:#b7f3cc;border-color:#153f2c}
.btn{cursor:pointer;background:#14243b}
.btn.primary{background:#19407a;border-color:#1f4d93}
.btn:hover{filter:brightness(1.05)}

/* ===== Combobox ===== */
.combobox{position:relative; min-width:420px}
.combo-toggle{width:100%; height:36px; border-radius:9px; border:1px solid var(--border); background:#0c182b; color:var(--text);
  display:flex; align-items:center; justify-content:space-between; padding:0 10px; cursor:pointer}
.combo-toggle .combo-value{overflow:hidden; text-overflow:ellipsis; white-space:nowrap}
.combo-toggle .caret{margin-left:10px; opacity:.8}
.combobox.open .combo-toggle{filter:brightness(1.06)}
.combo-panel{position:absolute; left:0; right:0; top:calc(100% + 6px); background:var(--panel); border:1px solid var(--border); border-radius:12px;
  box-shadow:0 20px 40px rgba(0,0,0,.45); padding:8px; display:none; z-index:900}
.combobox.open .combo-panel{display:block}
.combo-search{width:100%; height:34px; border-radius:8px; border:1px solid var(--border); background:#0c182b; color:var(--text);
  padding:0 10px; margin-bottom:6px; font-size:12.5px}
.combo-list{max-height:360px; overflow-y:auto; overflow-x:hidden; border-radius:8px}
.combo-item{padding:7px 10px; border-radius:8px; cursor:pointer; white-space:normal; line-height:1.25; font-size:12.5px}
.combo-item:hover, .combo-item.active{background:#14243b}
.combo-empty{padding:8px 10px; color:var(--muted)}
/* ===== end combobox ===== */

/* Grid stats */
.grid{display:grid;grid-template-columns:repeat(4,minmax(220px,1fr));gap:10px;margin-top:12px}
@media (max-width: 1100px){ .grid{grid-template-columns:repeat(2,minmax(200px,1fr));} }
@media (max-width: 560px){ .grid{grid-template-columns:1fr;} }
.card{background:var(--panel);border:1px solid var(--border);border-radius:12px;padding:14px}
.card h4{margin:0 0 6px;font-size:12px;color:var(--muted);font-weight:700}
.card .big{font-size:32px;font-weight:900;line-height:1; font-variant-numeric:tabular-nums}
.card .sub{margin-top:6px;font-size:12px;color:var(--muted)}
.progress{height:8px;background:#0b172b;border:1px solid var(--border);border-radius:6px;overflow:hidden;margin-top:8px}
.progress>span{display:block;height:100%;background:linear-gradient(90deg,var(--green),#35f);width:0%; transition:width .6s cubic-bezier(.2,.7,.2,1)}

/* Filters */
.filters{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:12px 0}
.filters .group{display:flex;gap:6px;align-items:center;background:var(--panel2);padding:8px;border:1px solid var(--border);border-radius:10px}
.filters label{color:var(--muted);font-size:12px;font-weight:700}

/* Table */
.tablewrap{background:var(--panel);border:1px solid var(--border);border-radius:12px;overflow:auto;max-height:calc(100vh - 320px)}
table{width:100%;border-collapse:collapse;font-size:13px}
thead th{position:sticky;top:0;background:#0e1a2b;border-bottom:1px solid var(--border);padding:10px;color:var(--muted);text-align:left}
tbody td{border-top:1px solid #0b172b;padding:10px;white-space:nowrap}
tbody tr:hover{background:#0f1d31}
.center{text-align:center;color:var(--muted);padding:18px}
.muted{color:var(--muted)} .small{font-size:12px}
.status{padding:.25rem .5rem;border-radius:.5rem;font-size:11px;font-weight:800}
.st-mulai{background:#fef3c7;color:#92400e}
.st-selesai{background:#d1fae5;color:#065f46}
.st-dq{background:#fee2e2;color:#991b1b}
.st-timeout{background:#e5e7eb;color:#374151}
.online{display:inline-flex;align-items:center;gap:6px;padding:.2rem .5rem;border-radius:.5rem;font-size:11px;font-weight:700}
.online.on{background:#d1fae5;color:#065f46}
.online.off{background:#fee2e2;color:#991b1b}
.pelanggaran{padding:.25rem .5rem;border-radius:.5rem;font-size:11px;font-weight:700}
.pelanggaran.safe{background:#d1fae5;color:#065f46}
.pelanggaran.warn{background:#fed7aa;color:#92400e}
.pelanggaran.dang{background:#fee2e2;color:#991b1b}

/* Responsif mobile */
@media (max-width: 900px){ .col-last, .col-ip { display:none; } }
@media (max-width: 700px){ .col-selesai, .col-mulai { display:none; } }

.help{margin-top:8px;color:var(--muted);font-size:12px}
.footer-note{margin-top:8px;color:#7691ba;font-size:11px;text-align:right}

/* Toast */
.toast{position:fixed;top:16px;right:16px;background:#19407a;color:#fff;padding:10px 14px;border-radius:8px;border:1px solid #2b5fae;box-shadow:0 10px 30px rgba(0,0,0,.4);opacity:0;transform:translateY(-8px);transition:all .25s;z-index:9999}
.toast.show{opacity:1;transform:translateY(0)}
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <div class="titlewrap">
      <div class="title"><span class="ic">📊</span><span>Pusat Kendali Ujian (Live)</span></div>
      <div class="subtitle">
        Pantau progres, reset sesi, lihat detail peserta, kelola pelanggaran, dan kontrol sesi secara real-time.
        <span class="tip">
          <span class="glogo" aria-hidden="true">
            <svg viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.2l6.6-6.6C35.7 2.4 30.2 0 24 0 14.6 0 6.6 5.4 2.7 13.3l7.8 6.1C12.5 13 17.8 9.5 24 9.5z"/><path fill="#34A853" d="M46.6 24.5c0-1.7-.2-3.4-.6-5H24v9.5h12.7c-.5 2.8-2.1 5.2-4.5 6.8l7 5.4c4.1-3.8 6.4-9.4 6.4-16.7z"/><path fill="#4285F4" d="M24 48c6 0 11-2 14.7-5.4l-7-5.4C30 38.5 27.3 39.5 24 39.5c-6.1 0-11.3-4.1-13.2-9.6l-7.9 6.1C6.8 43 14.7 48 24 48z"/><path fill="#FBBC05" d="M10.8 29.9C10.3 28.2 10 26.4 10 24.5s.3-3.7.8-5.4l-7.9-6.1C.9 16.2 0 20.2 0 24.5s.9 8.3 2.9 11.5l7.9-6.1z"/></svg>
          </span>
          Google Forms CBT
          <span class="tt">Panel ini terhubung ke <b>Google Forms</b> (mode CBT). Data attempt disinkronkan dan diperbarui otomatis untuk pemantauan real-time.</span>
        </span>
      </div>
    </div>
    <div id="loadhint" class="badge"><span>⏳ memuat…</span></div>
  </div>

  <!-- Top controls -->
  <div class="controlbar">
    <label class="small muted">Ujian</label>

    <!-- Combobox -->
    <div class="combobox" id="ujianBox">
      <button type="button" class="combo-toggle">
        <span class="combo-value">— pilih ujian —</span>
        <span class="caret">▾</span>
      </button>
      <div class="combo-panel">
        <input class="combo-search" id="ujianSearch" placeholder="Cari ujian (kelas/mapel/jenis/judul)…">
        <div class="combo-list" id="ujianList"><div class="combo-empty">Memuat…</div></div>
      </div>
      <input type="hidden" id="ujian" value="">
    </div>

    <button id="export" class="btn">⬇️ CSV</button>

    <span class="badge">
      <span>🔁 Auto</span>
      <select id="auto" class="sel" style="min-width:88px">
        <option value="0">0s</option><option value="5">5s</option><option value="10" selected>10s</option>
        <option value="15">15s</option><option value="20">20s</option><option value="30">30s</option><option value="60">60s</option>
      </select>
    </span>

    <span id="attemptInfo" class="badge green">✅ 0 attempt</span>
  </div>

  <!-- Stats -->
  <div class="grid">
    <div class="card"><h4>Total Peserta</h4><div id="st_total" class="big" data-val="0">0</div></div>
    <div class="card">
      <h4>Sedang Mengerjakan</h4><div id="st_sedang" class="big" data-val="0">0</div>
      <div class="sub">📶 <span id="st_online" data-val="0">0</span> online</div>
    </div>
    <div class="card">
      <h4>Selesai</h4><div id="st_selesai" class="big" data-val="0">0</div>
      <div class="progress"><span id="st_progress" style="width:0%"></span></div>
      <div class="sub"><span id="st_progress_label" data-val="0">0%</span> dari total</div>
    </div>
    <div class="card"><h4>Diskualifikasi</h4><div id="st_dq" class="big" data-val="0">0</div></div>
    <div class="card" style="grid-column:1/-1">
      <h4>Total Pelanggaran</h4><div id="st_vio" class="big" data-val="0">0</div>
      <div class="sub">Grace: <span id="st_grace">40</span>s</div>
    </div>
  </div>

  <!-- Filters -->
  <div class="filters">
    <div class="group"><label>🔎 Cari</label><input id="q" class="input" placeholder="Nama/NIS/Kelas/IP/Token/UA" style="width:260px"></div>
    <div class="group"><label>🧭 Status</label>
      <select id="st" class="sel" style="min-width:140px">
        <option value="">Semua</option><option value="mulai">Mulai</option><option value="selesai">Selesai</option>
        <option value="diskualifikasi">Diskualifikasi</option><option value="timeout">Timeout</option>
      </select>
    </div>
    <div class="group"><label>⚠️ Pelanggaran</label>
      <select id="pel" class="sel" style="min-width:130px">
        <option value="">Semua</option><option value="0">Bersih (0)</option><option value="1-2">Peringatan (1–2)</option><option value="3+">Kritis (3+)</option>
      </select>
    </div>
    <div class="group"><label>📶 Koneksi</label>
      <select id="conn" class="sel" style="min-width:120px">
        <option value="">Semua</option><option value="online">Online</option><option value="offline">Offline</option>
      </select>
    </div>
    <button id="clear" class="btn">✖️ Clear</button>
  </div>

  <!-- Table -->
  <div class="tablewrap">
    <table id="tbl">
      <thead>
        <tr>
          <th style="min-width:260px">Siswa</th>
          <th>Kelas</th>
          <th>Status</th>
          <th>Koneksi</th>
          <th class="col-mulai">Mulai</th>
          <th class="col-selesai">Selesai</th>
          <th>Pelanggaran</th>
          <th class="col-last">Last Seen</th>
          <th class="col-ip">IP</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody id="tbody">
        <tr><td colspan="10" class="center">Pilih ujian terlebih dahulu.</td></tr>
      </tbody>
    </table>
  </div>

  <div class="help">ℹ️ Status <b>online</b> dihitung dari <code>last_seen_at</code> ≤ <b>grace</b> (detik). Header tabel <i>sticky</i> untuk memudahkan scrolling data panjang.</div>
  <div class="footer-note">© Panel Attempt</div>
</div>

<div id="toast" class="toast"></div>

<script>
/* ============ SIMPLE FRONT-END HARDENING (Deterrent) ============ */
// Blok klik kanan
document.addEventListener('contextmenu', function(e){ e.preventDefault(); }, {capture:true});

// Blok kombinasi tombol umum (view-source/devtools/save/print)
document.addEventListener('keydown', function(e){
  const k = (e.key || '').toLowerCase();
  const ctrl = e.ctrlKey || e.metaKey; // dukung Mac (Cmd)
  const sh = e.shiftKey;

  // F12
  if (k === 'f12' || e.keyCode === 123) { e.preventDefault(); e.stopPropagation(); return false; }

  // Ctrl+U (view-source), Ctrl+S (save), Ctrl+P (print)
  if (ctrl && ['u','s','p'].includes(k)) { e.preventDefault(); e.stopPropagation(); return false; }

  // Ctrl+Shift+I/J/C (DevTools)
  if (ctrl && sh && ['i','j','c'].includes(k)) { e.preventDefault(); e.stopPropagation(); return false; }

  // Ctrl+Shift+K (Firefox web console)
  if (ctrl && sh && k === 'k') { e.preventDefault(); e.stopPropagation(); return false; }
}, {capture:true});

// Deteksi DevTools via gap window (heuristik)
(function detectDevTools(){
  let lastState = false;
  setInterval(function(){
    const wGap = Math.abs(window.outerWidth - window.innerWidth);
    const hGap = Math.abs(window.outerHeight - window.innerHeight);
    const isOpen = (wGap > 160 || hGap > 160);
    if (isOpen && !lastState) {
      lastState = true;
      // Optional: batasi aksi tertentu, tampilkan warning ringan
      console.warn('DevTools terdeteksi. Beberapa fitur dibatasi.');
    } else if (!isOpen && lastState) {
      lastState = false;
    }
  }, 1200);
})();

/* ============ APLIKASI ============ */
(function(){
  const $ = (s)=>document.querySelector(s);
  // Combobox refs
  const ujianBox   = $('#ujianBox');
  const ujianBtn   = ujianBox.querySelector('.combo-toggle');
  const ujianLbl   = ujianBox.querySelector('.combo-value');
  const ujianFind  = $('#ujianSearch');
  const ujianList  = $('#ujianList');
  const ujianValue = $('#ujian'); // hidden value (compat)

  const auto = $('#auto'), attemptInfo = $('#attemptInfo');
  const q = $('#q'), st = $('#st'), pel = $('#pel'), conn = $('#conn'), tbody = $('#tbody');
  const st_total=$('#st_total'), st_sedang=$('#st_sedang'), st_selesai=$('#st_selesai'), st_dq=$('#st_dq'),
        st_vio=$('#st_vio'), st_progress=$('#st_progress'), st_progress_label=$('#st_progress_label'),
        st_grace=$('#st_grace'), st_online=$('#st_online');
  const toast=$('#toast');
  let TIMER=null, LAST_COUNT=0, CURRENT_GRACE=40, UJIAN_OPTIONS=[], ACTIVE_INDEX=-1;

  const JENIS_LABEL = {uh:'UH', pts:'PTS', pas:'PAS', pat:'PAT', praktik:'PRAKTIK', remedial:'REMEDIAL', susulan:'SUSULAN'};

  function showToast(msg){ toast.textContent=msg; toast.classList.add('show'); setTimeout(()=>toast.classList.remove('show'),2000); }
  const esc = (s)=>String(s??'').replace(/[&<>\"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  function fmtDate(s){ if(!s) return '-'; const d=new Date(String(s).replace(' ','T')); if(isNaN(d)) return s; return d.toLocaleString('id-ID',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}); }
  function ago(s){ if(!s) return '-'; const d=new Date(String(s).replace(' ','T')); if(isNaN(d)) return '-';
    const sec=Math.max(1,Math.floor((Date.now()-d.getTime())/1000)); if(sec<60)return sec+'d'; const m=Math.floor(sec/60); if(m<60)return m+'m'; const h=Math.floor(m/60); if(h<24)return h+'j'; return Math.floor(h/24)+'h'; }
  const badgeStatus=(s)=>{ const map={mulai:'st-mulai',selesai:'st-selesai',diskualifikasi:'st-dq',timeout:'st-timeout'}; return `<span class="status ${map[s]||''}">${esc(String(s).toUpperCase())}</span>`; };
  function badgeOnline(r){ const ok = r.status==='mulai' && r.last_seen_at && ((Date.now()-new Date(String(r.last_seen_at).replace(' ','T')).getTime())/1000 <= CURRENT_GRACE);
    return `<span class="online ${ok?'on':'off'}">${ok?'● Online':'○ Offline'}</span>`; }
  function badgeVio(v){ v=parseInt(v||0,10); if(v===0) return `<span class="pelanggaran safe">${v}</span>`; if(v<3) return `<span class="pelanggaran warn">${v}</span>`; return `<span class="pelanggaran dang">${v}</span>`; }

  async function api(url,opt={}){ const r=await fetch(url,opt); const ct=r.headers.get('content-type')||''; if(!ct.includes('application/json')){ const t=await r.text(); throw new Error('Respon bukan JSON: '+t.slice(0,160)); } const j=await r.json(); if(j&&j.ok===0) throw new Error(j.err||'Gagal'); return j; }

  /* ---------- UJIAN OPTIONS ---------- */
  function buildUjianLabel(r){
    const jenis = r.jenis ? (JENIS_LABEL[r.jenis]||String(r.jenis).toUpperCase()) : '';
    const kelas = r.kelas_nama ? r.kelas_nama : '';
    const mapel = (r.mapel_kode||r.mapel_nama) ? `${r.mapel_kode||''}${r.mapel_kode&&r.mapel_nama?' - ':''}${r.mapel_nama||''}` : '';
    let left = '';
    if (kelas || mapel) left = `${kelas}${kelas&&mapel?' • ':''}${mapel}`;
    const jenisTag = jenis ? ` [${jenis}]` : '';
    return `${left ? left + ' — ' : ''}${jenis ? jenisTag + ' — ' : ''}${r.judul||('Ujian #'+r.ujian_id)} (${r.jml} attempt)`;
  }
  function renderUjianList(filter=''){
    const f = filter.trim().toLowerCase();
    const list = UJIAN_OPTIONS.filter(o => !f || o.search.includes(f));
    ujianList.innerHTML = list.length
      ? list.map((o,i)=>`<div class="combo-item${i===0?' active':''}" data-id="${o.id}">${esc(o.label)}</div>`).join('')
      : `<div class="combo-empty">Tidak ada hasil.</div>`;
    ACTIVE_INDEX = list.length ? 0 : -1;
    ujianList._currentIds = list.map(o=>String(o.id));
  }
  function setUjian(id){
    const item = UJIAN_OPTIONS.find(x=>String(x.id)===String(id));
    ujianValue.value = item ? item.id : '';
    ujianLbl.textContent = item ? item.label : '— pilih ujian —';
    ujianValue.dispatchEvent(new Event('change'));
  }
  async function loadUjian(){
    const j=await api('?ajax=list_ujian');
    UJIAN_OPTIONS = (j.data||[]).map(r=>{
      const label = buildUjianLabel(r);
      return { id: r.ujian_id, label, search: label.toLowerCase() };
    });
    renderUjianList('');
    $('#loadhint').innerHTML = '<span>✅ siap</span>';
  }

  /* ---------- ANIMASI ANGKA ---------- */
  function animateInt(el,to,ms=700){
    const from = parseInt(el.dataset.val||'0',10) || 0;
    to = parseInt(to||0,10);
    const start = performance.now();
    function step(t){
      const p = Math.min(1,(t-start)/ms);
      const val = Math.round(from + (to-from)*(1-Math.pow(1-p,3)));
      el.textContent = val.toLocaleString('id-ID');
      if(p<1){ requestAnimationFrame(step); } else { el.dataset.val = to; }
    }
    requestAnimationFrame(step);
  }
  function animatePercent(el,to,ms=700){
    const from = parseFloat(el.dataset.val||'0') || 0;
    to = parseFloat(to||0) || 0;
    const start = performance.now();
    function step(t){
      const p = Math.min(1,(t-start)/ms);
      const val = from + (to-from)*(1-Math.pow(1-p,3));
      el.textContent = val.toFixed(1) + '%';
      if(p<1){ requestAnimationFrame(step); } else { el.dataset.val = to; }
    }
    requestAnimationFrame(step);
  }

  /* ---------- STATUS ---------- */
  async function loadStatus(){
    const id=parseInt(ujianValue.value||'0',10);
    if(!id){
      [st_total,st_sedang,st_selesai,st_dq,st_vio,st_online].forEach(el=>{ if(el){ el.textContent='0'; el.dataset.val='0'; } });
      st_progress.style.width='0%'; st_progress_label.textContent='0%'; st_progress_label.dataset.val='0';
      st_grace.textContent=CURRENT_GRACE=40;
      return;
    }
    const j=await api('?ajax=status&ujian_id='+id);
    const d=j.data||{total:0,sedang:0,selesai:0,dq:0,online:0,pelanggaran_total:0,progress:0,grace:40};

    animateInt(st_total,d.total);
    animateInt(st_sedang,d.sedang);
    animateInt(st_selesai,d.selesai);
    animateInt(st_dq,d.dq);
    animateInt(st_vio,d.pelanggaran_total);
    animateInt(st_online,d.online);

    st_progress.style.width=(d.progress||0)+'%';
    animatePercent(st_progress_label,d.progress||0);

    st_grace.textContent=d.grace||40;
    CURRENT_GRACE=d.grace||40;
  }

  /* ---------- ROWS ---------- */
  async function loadRows(isInit=false){
    const id=parseInt(ujianValue.value||'0',10);
    tbody.innerHTML='<tr><td colspan="10" class="center">Memuat data…</td></tr>';
    attemptInfo.textContent='0 attempt';
    if(!id){ tbody.innerHTML='<tr><td colspan="10" class="center">Pilih ujian terlebih dahulu.</td></tr>'; return; }
    const params = new URLSearchParams({ ujian_id:id, q:q.value||'', status:st.value||'', pel:pel.value||'', conn:conn.value||'' });
    const j=await api('?ajax=rows&'+params.toString());
    const rows=j.rows||[]; CURRENT_GRACE=j.grace||CURRENT_GRACE;
    attemptInfo.textContent=`${rows.length} attempt`;

    if(!rows.length){ tbody.innerHTML='<tr><td colspan="10" class="center">Belum ada attempt untuk filter yang dipilih.</td></tr>'; return; }
    if(!isInit && rows.length>LAST_COUNT) showToast(`${rows.length-LAST_COUNT} attempt baru!`);
    LAST_COUNT=rows.length;

    tbody.innerHTML = rows.map(r=>{
      const nm = r.siswa_nama || (r.siswa_id?('#'+r.siswa_id):(r.siswa_user_id?('User#'+r.siswa_user_id):'-'));
      return `<tr>
        <td><strong>${esc(nm)}</strong>${r.siswa_nis?`<br><span class="muted small">${esc(r.siswa_nis)}</span>`:''}</td>
        <td>${esc(r.kelas_nama||'-')}</td>
        <td>${badgeStatus(r.status)}</td>
        <td>${badgeOnline(r)}</td>
        <td class="col-mulai">${fmtDate(r.started_at)}</td>
        <td class="col-selesai">${fmtDate(r.finished_at)}</td>
        <td>${badgeVio(r.pelanggaran)}</td>
        <td class="col-last" title="${esc(r.last_seen_at||'')}">${ago(r.last_seen_at)}</td>
        <td class="col-ip">${esc(r.ip_addr||'-')}</td>
        <td>
          <button class="btn small" data-act="detail" data-id="${r.id}">ℹ️ Detail</button>
          <button class="btn small" data-act="reset"  data-id="${r.id}">⟲ Reset</button>
          <button class="btn small" data-act="dq"     data-id="${r.id}">⛔ DQ</button>
        </td>
      </tr>`;
    }).join('');
  }

  /* ---------- Combobox behavior ---------- */
  function openCombo(){ ujianBox.classList.add('open'); ujianFind.value=''; renderUjianList(''); setTimeout(()=>ujianFind.focus(),10); }
  function closeCombo(){ ujianBox.classList.remove('open'); ACTIVE_INDEX=-1; }
  ujianBtn.addEventListener('click', (e)=>{ e.stopPropagation(); ujianBox.classList.contains('open') ? closeCombo() : openCombo(); });
  document.addEventListener('click', (e)=>{ if(!ujianBox.contains(e.target)) closeCombo(); });

  ujianFind.addEventListener('input', ()=> renderUjianList(ujianFind.value||''));
  ujianFind.addEventListener('keydown', (e)=>{
    const ids = ujianList._currentIds || [];
    const items = Array.from(ujianList.querySelectorAll('.combo-item'));
    if (e.key === 'ArrowDown') { e.preventDefault(); if(items.length){ ACTIVE_INDEX=(ACTIVE_INDEX+1)%items.length; items.forEach((el,i)=>el.classList.toggle('active',i===ACTIVE_INDEX)); items[ACTIVE_INDEX].scrollIntoView({block:'nearest'}); } }
    if (e.key === 'ArrowUp')   { e.preventDefault(); if(items.length){ ACTIVE_INDEX=(ACTIVE_INDEX-1+items.length)%items.length; items.forEach((el,i)=>el.classList.toggle('active',i===ACTIVE_INDEX)); items[ACTIVE_INDEX].scrollIntoView({block:'nearest'}); } }
    if (e.key === 'Enter')     { e.preventDefault(); if(items.length && ACTIVE_INDEX>=0){ const id=ids[ACTIVE_INDEX]; setUjian(id); closeCombo(); } }
    if (e.key === 'Escape')    { e.preventDefault(); closeCombo(); }
  });
  ujianList.addEventListener('click', (e)=>{
    const it = e.target.closest('.combo-item'); if(!it) return;
    setUjian(it.dataset.id); closeCombo();
  });

  /* ---------- Events ---------- */
  $('#export').addEventListener('click', ()=>{ const id=parseInt(ujianValue.value||'0',10); if(!id) return alert('Pilih ujian dulu'); window.location='?ajax=export&ujian_id='+id; });
  $('#clear').addEventListener('click', ()=>{ q.value=''; st.value=''; pel.value=''; conn.value=''; loadRows(); });
  [q,st,pel,conn].forEach(el=>el.addEventListener(el.tagName==='INPUT'?'input':'change', ()=>loadRows()));
  ujianValue.addEventListener('change', ()=>{ LAST_COUNT=0; loadStatus(); loadRows(true); });

  document.addEventListener('click', async (e)=>{
    const b=e.target.closest('button'); if(!b) return;
    const act=b.dataset.act, id=parseInt(b.dataset.id||'0',10); if(!act||!id) return;
    if(act==='detail'){
      const j=await api('?ajax=detail&id='+id);
      if(!j.data) return alert('Detail tidak ditemukan');
      const d=j.data;
      alert(
        `Siswa: ${d.siswa_nama||('- #'+(d.siswa_id||''))}\n`+
        `NIS: ${d.siswa_nis||'-'}\nKelas: ${d.kelas_nama||'-'}\nUjian: ${d.judul||('- #'+d.ujian_id)}\n`+
        `Status: ${d.status}\nPelanggaran: ${d.pelanggaran}\n`+
        `Mulai: ${d.started_at||'-'}\nSelesai: ${d.finished_at||'-'}\nLastSeen: ${d.last_seen_at||'-'}\n`+
        `IP: ${d.ip_addr||'-'}\nUA: ${(d.user_agent||'-').slice(0,160)}`
      );
    }
    if(act==='reset'){
      if(!confirm('Reset attempt ini?')) return;
      const j=await fetch('?ajax=reset',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':'<?=esc($CSRF)?>'},body:JSON.stringify({id})}).then(r=>r.json());
      if(j.ok){ (function(){const t=document.createElement('div'); t.className='toast show'; t.textContent='Attempt direset'; document.body.appendChild(t); setTimeout(()=>t.remove(),1800);})(); loadStatus(); loadRows(); } else alert(j.err||'Gagal');
    }
    if(act==='dq'){
      if(!confirm('Diskualifikasi attempt ini?')) return;
      const j=await fetch('?ajax=dq',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':'<?=esc($CSRF)?>'},body:JSON.stringify({id})}).then(r=>r.json());
      if(j.ok){ (function(){const t=document.createElement('div'); t.className='toast show'; t.textContent='Attempt didiskualifikasi'; document.body.appendChild(t); setTimeout(()=>t.remove(),1800);})(); loadStatus(); loadRows(); } else alert(j.err||'Gagal');
    }
  });

  function schedule(){ const sec=parseInt(auto.value||'10',10); if(TIMER){clearInterval(TIMER);TIMER=null;} if(sec>0) TIMER=setInterval(()=>{ loadStatus(); loadRows(); }, sec*1000); }
  auto.addEventListener('change', schedule);

  // init
  (async function(){
    try { await loadUjian(); schedule(); }
    catch(e){ $('#loadhint').innerHTML='<span>⚠️ gagal memuat</span>'; console.error(e); alert('Gagal memuat data: '+e.message); }
  })();
})();
</script>
</body>
</html>
