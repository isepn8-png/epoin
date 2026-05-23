<?php
// exam_gform.php — Fullscreen + Countdown + GForm + Fullscreen Guard + Pelanggaran (Alt/Ctrl+Tab + Win key + Mobile)
// - Modal konfirmasi modern (PUTIH, teks hitam, backdrop blur).
// - “Clear history” akhir ujian: local/session storage + Cache Storage + header Clear-Site-Data.
// - Fullscreen Guard: blokir/cegah kombinasi tombol umum; tampilkan overlay untuk memulihkan FS.
// - Pelanggaran: pindah tab/jendela/aplikasi (Alt+Tab/Ctrl+Tab) & keluar fullscreen & Win key & mobile app switcher → 3× = diskualifikasi.
// - Responsif + dukung mobile fullscreen jika tersedia.
// - [INTEGRASI] Ambil otomatis gform_url dari DB via ?ujian_id / uid / id (DB > aktif sekarang > fallback), create attempt, AJAX heartbeat/event/finish.
// - [LOADER] Dipercepat: hilang saat iframe load ATAU maksimal 2 detik.

session_start();
date_default_timezone_set('Asia/Jakarta');

// ==== Konfigurasi dasar (boleh dioverride DB) ====
$DURATION_MIN   = max(1, (int)($_GET['dur'] ?? 15));
$GRACE_SECONDS  = max(0, (int)($_GET['grace'] ?? 20));
$SECRET         = 'ubah_ke_secret_anda';
$DQ_REDIRECT    = 'index.php';

// =====================================================================================
// [DB Bootstrap] — fleksibel: app/bootstrap.php (PDO via db()) → koneksi.php (mysqli)
// =====================================================================================
$pdo = null; $mysqli = null;
try {
  $bootstrap1 = __DIR__ . '/../app/bootstrap.php';
  $bootstrap2 = __DIR__ . '/../../app/bootstrap.php';
  if (is_file($bootstrap1)) { require_once $bootstrap1; }
  elseif (is_file($bootstrap2)) { require_once $bootstrap2; }
  if (function_exists('db')) $pdo = db();
} catch (Throwable $e) {}
if (!$pdo) {
  // fallback koneksi.php (mysqli)
  $kfile1 = __DIR__ . '/../koneksi.php';
  $kfile2 = __DIR__ . '/koneksi.php';
  if (is_file($kfile1)) require_once $kfile1; elseif (is_file($kfile2)) require_once $kfile2;
  if (isset($koneksi) && $koneksi instanceof mysqli) $mysqli = $koneksi;
}

// ===== Helpers DB ringkas =====
function _str($s){ return is_string($s)?trim($s):''; }
function db_row($sql, $params = []){
  global $pdo, $mysqli;
  if ($pdo) { $st=$pdo->prepare($sql); $st->execute($params); return $st->fetch(PDO::FETCH_ASSOC); }
  if ($mysqli) { $st=$mysqli->prepare($sql); if($params){ $types=str_repeat('s',count($params)); $st->bind_param($types,...$params); }
    $st->execute(); $res=$st->get_result(); return $res?$res->fetch_assoc():null; }
  return null;
}
function db_exec($sql,$params=[]){
  global $pdo,$mysqli;
  if($pdo){ $st=$pdo->prepare($sql); return $st->execute($params); }
  if($mysqli){ $st=$mysqli->prepare($sql); if($params){ $types=str_repeat('s',count($params)); $st->bind_param($types,...$params); } return $st->execute(); }
  return false;
}
function db_last_id(){ global $pdo,$mysqli; return $pdo? $pdo->lastInsertId() : ($mysqli?$mysqli->insert_id:null); }

function normalize_gform_url(string $u): string {
  $u = trim($u);
  if ($u === '') return $u;
  // Jika URL docs.google.com/forms/.../viewform → tambahkan embedded=true agar rapi di iframe.
  if (stripos($u, 'docs.google.com/forms') !== false && stripos($u, 'viewform') !== false) {
    $sep = (parse_url($u, PHP_URL_QUERY) === null) ? '?' : '&';
    if (stripos($u, 'embedded=true') === false) $u .= $sep . 'embedded=true';
  }
  return $u;
}

// =====================================================================================
// [AMBIL DATA UJIAN] — SELALU berdasar ?ujian_id (alias uid/id). Jika tidak ketemu, fallback: ujian aktif sekarang.
// =====================================================================================
$ujian_id = 0;
if (isset($_GET['ujian_id'])) $ujian_id = (int)$_GET['ujian_id'];
elseif (isset($_GET['uid']))  $ujian_id = (int)$_GET['uid'];
elseif (isset($_GET['id']))   $ujian_id = (int)$_GET['id'];

$now  = date('Y-m-d H:i:s');
$U = null;
if ($ujian_id > 0) {
  $U = db_row("SELECT * FROM ujian_gform WHERE id=? LIMIT 1", [ (string)$ujian_id ]);
}
if (!$U) {
  // fallback: cari yang active pada window saat ini
  $U = db_row("SELECT * FROM ujian_gform
               WHERE is_active=1 AND ? BETWEEN mulai_at AND selesai_at
               ORDER BY updated_at DESC, id DESC LIMIT 1", [ $now ]);
}
if (!$U) { http_response_code(404); exit('Ujian tidak ditemukan / nonaktif.'); }

// =====================================================================================
// [PAKAI URL DARI DB] — prioritas mutlak DB; ?g diabaikan (kecuali DB benar-benar kosong, yang tidak terjadi di sini).
// =====================================================================================
$GFORM_EMBED_URL = normalize_gform_url(_str($U['gform_url'] ?? ''));
if ($GFORM_EMBED_URL === '') {
  // fallback terakhir (seharusnya nyaris tidak terpakai)
  $GFORM_EMBED_URL = normalize_gform_url('https://forms.gle/hxWLDoMiwdy3kLdY9');
}

// Override durasi/grace dari DB jika tersedia
if (isset($U['durasi_menit']) && is_numeric($U['durasi_menit'])) $DURATION_MIN = max(1,(int)$U['durasi_menit']);
if (isset($U['allow_grace_sec']) && is_numeric($U['allow_grace_sec'])) $GRACE_SECONDS = max(0,(int)$U['allow_grace_sec']);

// ====== Validasi jadwal (izinkan masuk sampai akhir + grace), kecuali mode review
$review = isset($_GET['review']) ? 1 : 0;
if (!$review) {
  $now_ts = time();
  $mulai_ts = strtotime($U['mulai_at'] ?? $now);
  $selesai_ts = strtotime($U['selesai_at'] ?? $now) + (int)$GRACE_SECONDS;
  if ($now_ts < $mulai_ts) { http_response_code(403); exit('Belum waktu ujian.'); }
  if ($now_ts > $selesai_ts) { http_response_code(403); exit('Waktu ujian telah berakhir.'); }
}

// =====================================================================================
// [ATTEMPT find-or-create] — tokenized; heartbeat/event/finish endpoint tersedia di bawah.
// =====================================================================================
$siswa_id = (int)($_SESSION['id'] ?? 0); // sesuaikan dengan sistem auth Anda
if (!$siswa_id && function_exists('current_user')) { $cu=current_user(); $siswa_id = (int)($cu['id'] ?? 0); }

$ATT = null;
if ($siswa_id > 0) {
  $ATT = db_row("SELECT * FROM ujian_gform_attempt WHERE ujian_id=? AND siswa_id=? LIMIT 1",
                [ (string)$U['id'], (string)$siswa_id ]);
  if (!$ATT) {
    $token = bin2hex(random_bytes(16));
    db_exec("INSERT INTO ujian_gform_attempt (ujian_id,siswa_id,started_at,status,token,user_agent,ip_addr,last_seen_at)
             VALUES (?,?,?,?,?,?,?,NOW())", [
               (string)$U['id'], (string)$siswa_id, date('Y-m-d H:i:s'),
               'mulai', $token, ($_SERVER['HTTP_USER_AGENT']??''), ($_SERVER['REMOTE_ADDR']??'')
             ]);
    $ATT = db_row("SELECT * FROM ujian_gform_attempt WHERE id=?", [ (string)db_last_id() ]);
  }
}
// Token sesi untuk attempt; jika tidak ada row attempt (siswa belum login), gunakan hash session (tetap aman sisi UI)
$TOKEN = $ATT['token'] ?? hash('sha1', session_id().microtime(true).$SECRET);

// Deadline absolut = started_at + durasi (server-side)
$deadline_ts = $ATT ? strtotime($ATT['started_at'].' +'.$DURATION_MIN.' minutes') : time()+($DURATION_MIN*60);

// =====================================================================================
// [AJAX ENDPOINTS] event/heartbeat/finish untuk pelanggaran & status (tanpa ganggu UI lama)
// =====================================================================================
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST' && isset($_POST['act'])){
  $act = $_POST['act'] ?? '';
  $token = $_POST['token'] ?? '';
  if(!$token){ http_response_code(400); exit('no token'); }
  $att = db_row("SELECT * FROM ujian_gform_attempt WHERE token=? LIMIT 1", [$token]);
  if(!$att){ http_response_code(404); exit('attempt not found'); }
  if($act==='event'){
    db_exec("UPDATE ujian_gform_attempt SET pelanggaran = pelanggaran + 1, last_seen_at = NOW() WHERE id=?", [ (string)$att['id'] ]);
    exit('ok');
  }
  if($act==='heartbeat'){
    db_exec("UPDATE ujian_gform_attempt SET last_seen_at = NOW() WHERE id=?", [ (string)$att['id'] ]);
    exit('ok');
  }
  if($act==='finish'){
    $st = in_array($_POST['status']??'', ['selesai','timeout','diskualifikasi']) ? $_POST['status'] : 'selesai';
    db_exec("UPDATE ujian_gform_attempt SET status=?, finished_at=NOW(), last_seen_at=NOW() WHERE id=?", [ $st, (string)$att['id'] ]);
    header('Clear-Site-Data: "cache, storage"'); exit('ok');
  }
}

// Token & timestamp (opsional prefill ke GForm — dibiarkan kosong agar tidak mengganggu)
$token_prefill   = $TOKEN;
$startAt_prefill = time();
$prefill = [
  // 'entry.1234567890' => $token_prefill,
  // 'entry.0987654321' => $startAt_prefill,
];
if (!empty($prefill)) {
  $sep = (parse_url($GFORM_EMBED_URL, PHP_URL_QUERY) === null) ? '?' : '&';
  $GFORM_EMBED_URL .= $sep . http_build_query($prefill) . '&usp=pp_url';
}

// ——— Security headers ———
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer-when-downgrade');
header('Permissions-Policy: fullscreen=(self); geolocation=(); microphone=(); camera=()');

// Halaman “selesai” (manual/timeout) — UI elegan (dipertahankan)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'finish_exam') {
  // Bersihkan data situs di browser yang mendukung
  header('Clear-Site-Data: "storage", "cache"');

  $_SESSION['exam_finished_at'] = date('Y-m-d H:i:s');
  $_SESSION['exam_token']       = $TOKEN;
  $_SESSION['exam_reason']      = $_POST['reason'] ?? 'manual';

  // Halaman penutup — elegan + loader 3 detik saat kembali
  echo "<!doctype html><meta charset='utf-8'><title>Ujian Diakhiri</title>
  <style>
    :root{ --bg:#0b1220; --ink:#0f172a; --brand1:#22d3ee; --brand2:#7c3aed; --stroke:#e5e7eb; }
    *{box-sizing:border-box} html,body{height:100%}
    body{margin:0;background:radial-gradient(120% 120% at -10% -30%, #0ea5e955 0%, transparent 45%),
                       radial-gradient(120% 120% at 110% -10%, #7c3aed55 0%, transparent 50%),
                       radial-gradient(120% 120% at 50% 120%, #22d3ee44 0%, transparent 55%), #0b1220;
         font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;color:#e5e7eb; display:grid; place-items:center; padding:24px;}
    .card{width:min(740px,92vw); background:#fff; color:var(--ink); border:1px solid #e5e7eb; border-radius:20px;
          box-shadow:0 28px 80px rgba(0,0,0,.35); overflow:hidden;}
    .head{display:flex; align-items:center; gap:12px; padding:16px; background:#111827; color:#e5e7eb;}
    .badge{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;
           background:linear-gradient(135deg,var(--brand1),var(--brand2)); box-shadow:0 10px 22px rgba(124,58,237,.28);}    
    .body{padding:18px 18px 8px;}
    h1{margin:.2rem 0 .2rem; font-size:clamp(22px,3.2vw,30px)}
    .meta{margin:.4rem 0 .8rem; color:#475569}
    .meta li{margin:.18rem 0}
    .clean-note{font-size:.86rem; color:#64748b}
    .actions{display:flex;justify-content:flex-end;gap:10px;padding:12px 18px 16px}
    .btn{appearance:none;border:0;border-radius:12px;padding:.8rem 1rem;font-weight:700;cursor:pointer}
    .btn.primary{color:#fff;background:linear-gradient(135deg,#22d3ee,#7c3aed);box-shadow:0 12px 28px rgba(124,58,237,.28)}
    .btn.primary:hover{transform:translateY(-1px)}
    /* Loader overlay saat kembali */
    .loader{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(11,18,32,.75);
            backdrop-filter:blur(8px);z-index:1000}
    .blob{width:14px;height:14px;margin:0 6px;border-radius:50%;background:linear-gradient(135deg,#22d3ee,#7c3aed);
          filter:drop-shadow(0 0 8px #7c3aed88);animation:bounce 1s infinite ease-in-out}
    .blob:nth-child(2){animation-delay:.15s;background:linear-gradient(135deg,#7c3aed,#22d3ee)}
    .blob:nth-child(3){animation-delay:.3s}
    @keyframes bounce{0%,80%,100%{transform:translateY(0)}40%{transform:translateY(-14px)}}
    .ltxt{margin-top:12px;opacity:.9}
  </style>
  <div class='card'>
    <div class='head'>
      <div class='badge'>✅</div>
      <div>
        <div style='opacity:.9'>Sesi Ujian</div>
        <h1>Ujian Diakhiri</h1>
      </div>
    </div>
    <div class='body'>
      <ul class='meta'>
        <li>Waktu server: <b>".htmlspecialchars($_SESSION['exam_finished_at'])."</b></li>
        <li>Token sesi: <code style=\"background:#f1f5f9;border-radius:6px;padding:.12rem .35rem;color:#0f172a\">".htmlspecialchars($TOKEN)."</code></li>
        <li>Alasan: <b>".htmlspecialchars($_SESSION['exam_reason'])."</b></li>
      </ul>
      <div class='clean-note'>Jejak situs ujian telah dibersihkan.</div>
    </div>
    <div class='actions'>
      <button id='btnBack' class='btn primary'>Kembali</button>
    </div>
  </div>
  <div id='backLoader' class='loader'>
    <div>
      <div style='display:flex;justify-content:center'><div class='blob'></div><div class='blob'></div><div class='blob'></div></div>
      <div class='ltxt'>Mengalihkan…</div>
    </div>
  </div>
  <script>
    // Bersihkan sisi-klien juga (fallback bila header tidak didukung)
    try{ localStorage.removeItem('exam_violations'); sessionStorage.clear(); }catch(e){}
    if ('caches' in window){ caches.keys().then(keys=>Promise.all(keys.map(k=>caches.delete(k)))); }
    try{ history.replaceState(null,'', location.pathname); }catch(e){}

    document.getElementById('btnBack').addEventListener('click',()=>{
      const L=document.getElementById('backLoader'); L.style.display='flex';
      setTimeout(()=>{ location.href=".json_encode($DQ_REDIRECT)." },3000);
    });
  </script>";
  exit;
}

// ======================= VIEW =========================
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>CBT — Google Form + Countdown</title>
<style>
  :root{
    --bg:#0b1220; --ink:#e5e7eb;
    --brand1:#00c6ff; --brand2:#7c3aed; --brand3:#22d3ee; --brand4:#f472b6;
    --ok:#10b981; --warn:#f59e0b; --danger:#ef4444;
    --card:#ffffff0d; --stroke:#ffffff14; --soft:#ffffff08;
    --timer-color:#0F172A;
  }
  * { box-sizing: border-box; }
  html,body { height:100%; }
  body {
    margin:0; font-family: system-ui,-apple-system,Segoe UI,Roboto,Arial;
    color:var(--ink); background: var(--bg);
    background:
      radial-gradient(120% 120% at -10% -30%, #0ea5e966 0%, transparent 52%),
      radial-gradient(120% 120% at 110% -10%, #7c3aed55 0%, transparent 50%),
      radial-gradient(120% 120% at 50% 120%, #22d3ee44 0%, transparent 55%),
      #0b1220;
  }

  /* Loader awal */
  .loader { position:fixed; inset:0; display:flex; align-items:center; justify-content:center;
    background: rgba(11,18,32,.92); backdrop-filter: blur(6px); z-index:10000; }
  .loader .blob { width: 14px; height: 14px; margin: 0 6px; border-radius:50%;
    background: linear-gradient(135deg, var(--brand1), var(--brand2));
    filter: drop-shadow(0 0 8px #7c3aed88); animation: bounce 1s infinite ease-in-out; }
  .loader .blob:nth-child(2){ animation-delay: .15s; background: linear-gradient(135deg, var(--brand3), var(--brand4)); }
  .loader .blob:nth-child(3){ animation-delay: .3s; background: linear-gradient(135deg, var(--brand2), var(--brand3)); }
  @keyframes bounce{ 0%,80%,100% { transform: translateY(0); opacity:.9; } 40% { transform: translateY(-14px); opacity:1; } }
  .loader .txt{ margin-top:14px; text-align:center; font-size:.95rem; opacity:.9 }

  .wrap { max-width: 1220px; margin:0 auto; padding: 16px; }

  header {
    position:sticky; top:0; z-index:20; display:flex; align-items:center; justify-content:space-between; gap:12px;
    padding:12px 16px; background: rgba(11,18,32,.62); backdrop-filter: blur(8px);
    border-bottom:1px solid var(--stroke); flex-wrap: wrap;
  }
  .left { display:flex; gap:10px; align-items:center; min-width:280px; flex-wrap:wrap; }
  .dot { width:12px; height:12px; border-radius:50%; background:var(--danger);
         box-shadow:0 0 0 6px rgba(239,68,68,.12); transition:.25s; }
  .status { font-weight:700; letter-spacing:.2px; }

  .badges { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }

  /* Timer kompak + ring */
  .timer-shell{ display:flex; align-items:center; gap:8px; padding:6px 10px; border-radius:12px; background:var(--soft); border:1px solid var(--stroke); }
  .ring{
    --pct:0; width:34px; height:34px; border-radius:50%;
    background: conic-gradient(from -90deg, #22d3ee 0%, #06b6d4 calc(var(--pct)*1%), #ffffff20 calc(var(--pct)*1%) 100%);
    position:relative; box-shadow: 0 0 0 3px #06b6d420 inset, 0 0 8px #06b6d455;
  }
  .ring:before{ content:""; position:absolute; inset:6px; background:#0b1220; border-radius:50%; box-shadow: inset 0 0 5px #00000055; }
  .timer{ font-variant-numeric: tabular-nums; line-height:1; font-size: clamp(16px, 1.9vw, 18px); font-weight: 800; color: #E6F0FF; text-shadow: 0 2px 8px rgba(147,197,253,.15); letter-spacing:.25px; }

  .progress { position:relative; height:8px; width:100%; background: #ffffff12; border-radius:999px; overflow:hidden; border:1px solid var(--stroke); }
  .progress > .bar { height:100%; width:0%; background: linear-gradient(90deg, #22d3ee, #7c3aed, #f472b6); box-shadow: 0 0 16px #7c3aed66; transition: width .35s ease; }

  .btn { appearance:none; border:0; cursor:pointer; padding:.75rem 1rem; border-radius:14px;
    background:linear-gradient(135deg, var(--brand1), var(--brand2)); color:#fff; font-weight:700; letter-spacing:.2px;
    box-shadow: 0 12px 28px rgba(124,58,237,.28), inset 0 0 0 1px #ffffff22; transition: transform .08s ease, box-shadow .2s ease; }
  .btn:hover{ transform: translateY(-1px); }
  .btn:active{ transform: translateY(0); }
  .btn[disabled]{opacity:.7; cursor:not-allowed}
  .btn.ghost{ background: transparent; color:#0f172a; border:1px solid #e5e7eb; box-shadow:none; }
  .btn.danger{ background:linear-gradient(135deg, #ef4444, #f59e0b); box-shadow: 0 12px 28px rgba(239,68,68,.28), inset 0 0 0 1px #ffffff22; }

  .info { color:#cbd5e1; font-size:.98rem; margin:12px 16px 8px 16px; opacity:.95; }

  .frame-wrap { position:relative; margin:12px 16px 16px 16px; border-radius:18px; overflow:hidden;
    background:linear-gradient(145deg,#ffffff10,#ffffff05); border:1px solid var(--stroke);
    box-shadow: 0 24px 60px rgba(2,6,23,.45), inset 0 1px 0 #ffffff10; }
  .gframe { display:block; width:100%; border:0; height: calc(100vh - 198px); background:#0b1220; }

  /* Overlay mulai */
  .overlay { position:fixed; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center;
    background: radial-gradient(120% 120% at 8% 0%, #0ea5e955 0%, transparent 40%) , #0b1220; z-index:9000; text-align:center; padding:24px; }
  .overlay h1 { margin:0 0 .5rem 0; font-size: clamp(22px, 4vw, 34px); }
  .hint { opacity:.9; font-size:1rem; max-width:760px; margin:0 auto 1rem; color:#dbeafe; }

  /* === Rules Card (Ketentuan + Login Google) === */
  .rules-wrap{ width:min(880px,94vw); margin-top:12px; display:grid; gap:12px; }

  /* >>> REVISI: latar kartu transparan dengan aksen lembut, teks putih kontras <<< */
  .rules-card{
    text-align:left;
    border:1px solid rgba(255,255,255,.18); border-radius:18px; padding:14px 14px 12px;
    background: rgba(255,255,255,.06);
    backdrop-filter: blur(4px) saturate(120%);
    box-shadow: 0 18px 48px rgba(2,6,23,.45), inset 0 1px 0 rgba(255,255,255,.12);
    position:relative; color:#fff;
  }
  .rules-card::after{
    content:""; position:absolute; inset:0; border-radius:18px; pointer-events:none;
    background: radial-gradient(60% 70% at 8% 0%, rgba(59,130,246,.18) 0%, transparent 60%);
  }
  .rules-card:hover{ box-shadow: 0 22px 60px rgba(2,6,23,.55), inset 0 1px 0 rgba(255,255,255,.14); }

  .rc-head{ display:flex; align-items:center; gap:10px; margin-bottom:6px; }
  .rc-ico{ width:34px; height:34px; border-radius:10px; display:grid; place-items:center;
           background:linear-gradient(135deg, var(--brand3), var(--brand2)); box-shadow:0 10px 22px rgba(124,58,237,.28); }
  .rc-title{ font-weight:800; font-size:1.02rem; letter-spacing:.2px; color:#fff; }
  .rc-badges{ display:flex; flex-wrap:wrap; gap:8px; margin:8px 0 10px; }
  .chip{ font-size:.78rem; padding:.22rem .5rem; border-radius:999px; border:1px solid #ffffff2a; background:#ffffff14; color:#fff; }
  .rc-list{ margin:.15rem 0 .35rem 0; padding-left:0; list-style:none; }
  .rc-list li{ display:grid; grid-template-columns: 20px 1fr; gap:8px; margin:.32rem 0; align-items:start; font-size:.95rem; color:#f1f5f9; }
  .rc-list .b{ font-weight:700; color:#fff; }
  .rc-note{ font-size:.85rem; color:#e2e8f0; opacity:.95; }

  /* Sub card: login Google — transparan dengan semburat biru lembut */
  .login-card{
    text-align:left;
    border:1px solid rgba(191,219,254,.35); border-radius:16px; padding:12px 14px;
    background: rgba(59,130,246,.10);
    backdrop-filter: blur(4px) saturate(120%);
    box-shadow: 0 14px 36px rgba(2,6,23,.45);
    position:relative; color:#fff;
  }
  .login-card::after{ content:""; position:absolute; inset:0; border-radius:16px; pointer-events:none;
    background: radial-gradient(70% 80% at 100% 0%, rgba(37,99,235,.18) 0%, transparent 70%); }
  .login-head{ display:flex; align-items:center; gap:10px; margin-bottom:6px; }
  .login-ico{ width:28px; height:28px; border-radius:8px; display:grid; place-items:center;
              background:linear-gradient(135deg,#22d3ee,#7c3aed); }
  .login-title{ font-weight:800; font-size:.98rem; color:#fff; }
  .login-text{ margin:.25rem 0 .15rem; color:#e6f1ff; font-size:.94rem; }
  .login-ul{ margin:.35rem 0 .15rem 1.1rem; padding:0; color:#e6f1ff; }
  .login-ul li{ margin:.18rem 0; }
  .login-note{ font-size:.84rem; color:#e2e8f0; }

  .mobile-note { margin-top:.6rem; color:#c7d2fe; font-size:.9rem; }

  /* Overlay waktu habis */
  .timeup { position:fixed; inset:0; background:rgba(11,18,32,.92); z-index:8000; display:none; align-items:center; justify-content:center; text-align:center; padding:24px; }
  .timeup-inner { max-width:720px; background:var(--card); border:1px solid var(--stroke); border-radius:18px; padding:20px; box-shadow: 0 18px 50px rgba(2,6,23,.45); }

  /* ===== Modal konfirmasi keluar (PUTIH) ===== */
  .modal-overlay{ position:fixed; inset:0; display:none; align-items:center; justify-content:center;
    background:rgba(2,6,23,.55); backdrop-filter: blur(10px) saturate(120%); z-index:10040; animation: fade .18s ease-out;}
  @keyframes fade { from{opacity:0} to{opacity:1} }
  .modal{ width:min(540px, 92vw); background:#ffffff; color:#0f172a; border:1px solid #e5e7eb; border-radius:16px;
          box-shadow:0 22px 70px rgba(0,0,0,.35); padding:0; transform: scale(.96); opacity:0; animation: pop2 .18s forwards ease-out; }
  @keyframes pop2 { to{ transform: scale(1); opacity:1; } }
  .modal header{ display:flex; align-items:center; gap:10px; background:#111827; color:#e5e7eb; padding:12px 14px; border-top-left-radius:16px; border-top-right-radius:16px; }
  .modal header .ico{ width:34px; height:34px; border-radius:10px; background:linear-gradient(135deg,#22d3ee,#7c3aed); display:grid; place-items:center; box-shadow:0 10px 22px rgba(124,58,237,.28); }
  .modal header h3{ margin:0; font-size:1.08rem; }
  .modal .body{ padding:14px 16px 6px; }
  .modal .body p{ margin:.45rem 0; color:#334155; }
  .modal .actions{ display:flex; gap:10px; justify-content:flex-end; padding:12px 16px 16px; }
  .spinner{ width:16px; height:16px; border:3px solid #cbd5e1; border-top-color:#0f172a; border-radius:50%; animation:spin .8s linear infinite; display:inline-block; vertical-align:-3px; margin-right:8px; }
  @keyframes spin{ to{ transform: rotate(360deg); } }

  /* ===== Fullscreen Guard overlay ===== */
  .fsGuard{position:fixed; inset:0; display:none; align-items:center; justify-content:center; text-align:center;
           background:rgba(2,6,23,.62); backdrop-filter:blur(8px); z-index:10050; padding:24px;}
  .fsCard{max-width:640px; background:#fff; color:#0f172a; border:1px solid #e5e7eb; border-radius:16px; padding:16px; box-shadow:0 20px 60px rgba(0,0,0,.35);}
  .fsCard.danger{ border-color: rgba(239,68,68,.35); box-shadow:0 18px 50px rgba(239,68,68,.25); }
  .fsHead{ display:inline-block; padding:8px 12px; margin-bottom:8px; border-radius:12px; font-weight:800; color:#fff;
           background:linear-gradient(135deg,#ef4444,#f59e0b); box-shadow:0 10px 24px rgba(239,68,68,.35); }
  .fsMsg{ margin:.35rem 0 .6rem; color:#334155}
  .fsCount b{ color:#b91c1c; }
  .fsCard .btn{color:#fff; background:linear-gradient(135deg,#22d3ee,#7c3aed)}
  .fsCard.shake{ animation: modalshake .5s cubic-bezier(.36,.07,.19,.97); }

  /* ===== Badge pelanggaran + tooltip + toast ===== */
  .badge { padding:.35rem .6rem; border-radius:.55rem; font-size:.86rem; background:#0f172a; border:1px solid var(--stroke); }
  .badge.warn { color:#fecaca; border-color:rgba(239,68,68,.35); }
  .tip { position:relative; cursor:help; }
  .tip .tiptext{
    position:absolute; left:0; top: calc(100% + 8px); width: 320px; max-width: 85vw;
    background:#0b1220; color:#e5e7eb; border:1px solid #ef44444a; border-radius:12px;
    padding:12px 14px; font-size:.86rem; line-height:1.35;
    box-shadow: 0 12px 30px rgba(0,0,0,.35);
    opacity:0; transform: translateY(-6px); pointer-events:none; transition: opacity .18s, transform .18s; z-index:40;
  }
  .tip:hover .tiptext, .tip:focus-within .tiptext { opacity:1; transform: translateY(0); }
  .vi-progress { height:6px; background:#ffffff14; border:1px solid var(--stroke); border-radius:999px; overflow:hidden; margin-top:.4rem; }
  .vi-progress > span { display:block; height:100%; width:0%; background:linear-gradient(90deg,#ef4444,#f59e0b); transition:width .25s ease; }

  .toast {
    position: fixed; top: 14px; left: 50%; transform: translateX(-50%);
    display: none; align-items: center; gap:10px;
    background: #b91c1c; color:#fff; border:1px solid #fecaca33; border-radius:12px;
    padding:10px 14px; z-index:9500; box-shadow: 0 8px 30px rgba(239,68,68,.35);
    font-size:.95rem;
  }
  .toast.show { display:flex; animation: pop .15s ease-out; }
  .toast .dot { width:10px; height:10px; background:#fecaca; box-shadow:none; border-radius:50%; }
  @keyframes pop { from { opacity:0; transform: translateX(-50%) scale(.98);} to { opacity:1; transform: translateX(-50%) scale(1);} }
  @keyframes shake {
    10%,90% { transform: translateX(-50%) translate3d(-1px,0,0); }
    20%,80% { transform: translateX(-50%) translate3d( 2px,0,0); }
    30%,50%,70% { transform: translateX(-50%) translate3d(-4px,0,0); }
    40%,60% { transform: translateX(-50%) translate3d( 4px,0,0); }
  }
  .toast.shake { animation: shake .45s cubic-bezier(.36,.07,.19,.97); }

  @keyframes modalshake {
    10%,90% { transform: translateX(-1px) scale(1); }
    20%,80% { transform: translateX( 2px) scale(1); }
    30%,50%,70% { transform: translateX(-4px) scale(1); }
    40%,60% { transform: translateX( 4px) scale(1); }
  }
  .modal.shake { animation: modalshake .5s cubic-bezier(.36,.07,.19,.97); }

  .secure-mask{ position:fixed; inset:0; background:#000; display:none; z-index:100000; pointer-events:none; } /* <= PATCH: no input block */

  .wm{ position:fixed; inset:0; pointer-events:none; z-index:15; opacity:.9; }

  @keyframes pulseDot { 0%,100%{ box-shadow:0 0 0 6px rgba(16,185,129,.14); } 50%{ box-shadow:0 0 0 10px rgba(16,185,129,.24); } }

  /* ====== LAST-MINUTE (60 detik) BANNER ====== */
  .lastmin{ position:fixed; top:12px; left:50%; transform:translateX(-50%); z-index:10035; display:none; }
  .lastmin.show{ display:block; animation: pop .18s ease-out; }
  .lm-card{ display:flex; align-items:center; gap:10px; background:#ffffff; color:#0f172a;
            border:1px solid #e5e7eb; border-radius:14px; padding:10px 12px;
            box-shadow:0 14px 44px rgba(0,0,0,.35); }
  .lm-ico{ width:30px; height:30px; border-radius:10px; display:grid; place-items:center;
           background:linear-gradient(135deg,#f59e0b,#ef4444); color:#fff; font-weight:900; }
  .lm-msg{ font-size:.95rem; color:#334155; }
  .lm-msg b{ color:#b91c1c; }
  .lm-actions{ display:flex; gap:8px; margin-left:6px; }
  .btn.sm{ padding:.5rem .7rem; border-radius:10px; font-size:.88rem; }
  .lm-card.shake{ animation: modalshake .5s cubic-bezier(.36,.07,.19,.97); }

  @media (max-width: 640px){
    header{ padding:10px 12px; gap:8px; }
    .badges{ width:100%; justify-content:space-between; }
    .timer-shell{ flex:1; }
    .btn{ padding:.7rem .9rem; }
    .gframe{ height: calc(100vh - 220px); }
    .rc-list li{ font-size:.92rem; }
    .login-text{ font-size:.92rem; }
  }
  @media (max-width:460px){
    .overlay{ padding:18px; }
    .rc-title, .login-title{ font-size:.96rem; }
    .rc-badges{ gap:6px; }
    .chip{ font-size:.76rem; padding:.2rem .48rem; }
    .rules-card{ padding:12px; border-radius:16px; }
    .login-card{ padding:10px 12px; border-radius:14px; }
    .btn{ border-radius:12px; }
  }
</style>
</head>
<body>

<!-- Loader awal -->
<div class="loader" id="loader" aria-live="polite">
  <div>
    <div style="display:flex;justify-content:center;align-items:center">
      <div class="blob"></div><div class="blob"></div><div class="blob"></div>
    </div>
    <div class="txt">Memuat Google Form…</div>
  </div>
</div>

<!-- Toast pelanggaran -->
<div class="toast" id="viToast" role="status" aria-live="assertive">
  <span class="dot"></span>
  <span id="viToastMsg">Peringatan.</span>
</div>

<!-- ===== Last-Minute (60 detik) Banner ===== -->
<div class="lastmin" id="lastMin" role="alert" aria-live="assertive">
  <div class="lm-card" id="lmCard">
    <div class="lm-ico">⏰</div>
    <div class="lm-msg">
      <b>60 detik terakhir!</b> Segera tekan <b>Kirim</b> pada Google Form.
      Waktu tersisa: <b id="lmLeft">60</b> detik.
    </div>
    <div class="lm-actions">
      <button class="btn sm" id="btnExt60">Tambah 60 detik</button>
      <button class="btn sm ghost" id="btnLmClose">Tutup</button>
    </div>
  </div>
</div>

<!-- Secure mask (mitigasi screenshot di Recent Apps) -->
<div id="secureMask" class="secure-mask" aria-hidden="true"></div>

<!-- Watermark overlay -->
<div id="wm" class="wm" aria-hidden="true"></div>

<!-- Overlay mulai -->
<div class="overlay" id="overlay">
  <h1>Mode Ujian (Google Form)</h1>
  <p class="hint">
    Klik tombol di bawah untuk <b>masuk Layar Penuh</b> dan memulai. Timer berjalan
    <b><?= htmlspecialchars($DURATION_MIN) ?> menit</b>.
  </p>
  <button class="btn" id="btnStart">Mulai Ujian (Fullscreen)</button>

  <!-- === KARTU KETENTUAN & LOGIN === -->
  <div class="rules-wrap" aria-live="polite">
    <section class="rules-card" id="rulesCard">
      <div class="rc-head">
        <div class="rc-ico">🛡️</div>
        <div class="rc-title">Ketentuan Ujian</div>
      </div>
      <div class="rc-badges">
        <span class="chip">Wajib Fullscreen</span>
        <span class="chip">Shortcut diblokir</span>
        <span class="chip">3× pelanggaran = Diskualifikasi</span>
      </div>
      <ul class="rc-list">
        <li><span>⛔</span><span>Anda <span class="b">wajib</span> tetap dalam tampilan layar penuh selama ujian berlangsung.</span></li>
        <li><span>⌨️</span><span>Pintasan seperti <span class="b">Esc</span>, <span class="b">F11</span>, <span class="b">Alt/Ctrl+Tab</span>, dan <span class="b">tombol Windows</span> diblokir.</span></li>
        <li><span>🔄</span><span>Setiap upaya keluar fullscreen, berpindah tab, jendela, atau aplikasi dihitung sebagai <span class="b">pelanggaran</span>.</span></li>
        <li><span>❗</span><span>Jika mencapai <span class="b">3 pelanggaran</span>, Anda akan <span class="b">didiskualifikasi</span> dan sesi ditutup <span class="b">tanpa menyimpan jawaban</span>.</span></li>
      </ul>
      <div class="rc-note">Ikuti instruksi pengawas. Klik “Kirim” pada Google Form sebelum waktu habis.</div>
    </section>

    <section class="login-card" id="loginInfo">
      <div class="login-head">
        <div class="login-ico">🔒</div>
        <div class="login-title">Login Google Disarankan</div>
      </div>
      <p class="login-text">
        Pastikan Anda sudah <b>login ke akun Google</b> sebelum memulai. Saat login, Google Form menyimpan <b>progres jawaban</b> ke akun Anda secara berkala. Jika terjadi gangguan internet, penutupan aplikasi, atau refresh, jawaban yang tersimpan dapat dipulihkan saat membuka ulang form.
      </p>
      <ul class="login-ul">
        <li><b>Login</b> → progres lebih aman (autosave oleh Google Form).</li>
        <li><b>Tidak login</b> → progres <b>tidak</b> otomatis tersimpan; ada risiko jawaban hilang jika terjadi gangguan.</li>
      </ul>
      <p class="login-note">Catatan: Mekanisme simpan otomatis sepenuhnya dikendalikan oleh Google Form.</p>
    </section>
  </div>

  <p class="mobile-note" id="mobileNote" style="display:none">Tip: Jika fullscreen gagal di HP, gunakan browser terbaru. Safari iOS lama tidak mendukung Fullscreen API penuh.</p>
</div>

<header>
  <div class="left">
    <span class="dot" id="dotState" title="Status"></span>
    <span class="status" id="statusText">Belum mulai</span>

    <!-- Badge pelanggaran + tooltip -->
    <span class="badge warn tip" id="violations">
      Pelanggaran: <b id="viNum">0</b> / 3
      <span class="tiptext">
        <b>Sisa kesempatan: <span id="viLeft">3</span> dari 3</b>
        <div class="vi-progress"><span id="viProg"></span></div>
        <div style="margin-top:.5rem">Yang dihitung sebagai pelanggaran:</div>
        <ul style="margin:.4rem 0 .2rem 1.1rem;padding:0">
          <li>Berpindah tab/jendela/aplikasi (Alt+Tab, Ctrl+Tab)</li>
          <li>Menekan tombol <b>Windows</b> (Meta/OS key)</li>
          <li>Fullscreen tertutup (Esc/F11/gesture sistem)</li>
          <li><b>Mobile</b>: menekan Home/Recent Apps/Tab Switcher/hamburger/menu sehingga tab kehilangan fokus</li>
        </ul>
        Batas 3× → <b>diskualifikasi otomatis</b>.
      </span>
    </span>
  </div>

  <div class="badges">
    <div class="timer-shell">
      <div class="ring" id="ring"></div>
      <div class="timer" id="timer">--:--</div>
    </div>
    <button class="btn" id="btnFinish">Selesai & Keluar</button>
  </div>
</header>

<div class="wrap" style="padding-top:0">
  <div class="progress" aria-hidden="true"><div class="bar" id="bar"></div></div>
</div>

<p class="info">Tips: simpan jawaban Anda secara berkala di Google Form, lalu tekan <b>Kirim</b> sebelum waktu habis.</p>

<div class="frame-wrap" id="frameWrap">
  <iframe id="gformFrame" class="gframe"
    src="<?= htmlspecialchars($GFORM_EMBED_URL) ?>"
    allow="fullscreen; clipboard-read; clipboard-write"
    sandbox="allow-forms allow-scripts allow-same-origin"
    referrerpolicy="no-referrer-when-downgrade">
  </iframe>
</div>

<!-- Overlay waktu habis -->
<div class="timeup" id="timeUp">
  <div class="timeup-inner">
    <h2 id="timeUpTitle" style="margin:.25rem 0 .75rem 0;">Waktu Ujian Habis</h2>
    <p id="timeUpMsg" style="margin:.25rem 0 .25rem 0;">Jika belum, segera klik <b>Kirim</b> pada Google Form.</p>
    <p class="small" id="graceInfo"></p>
    <div style="margin-top:12px"><button class="btn" id="btnForceFinish">Tutup & Catat Selesai</button></div>
  </div>
</div>

<!-- Modal konfirmasi keluar (normal) -->
<div class="modal-overlay" id="exitConfirm" role="dialog" aria-modal="true" aria-labelledby="exitTitle" aria-describedby="exitDesc">
  <div class="modal">
    <header>
      <div class="ico">📝</div>
      <h3 id="exitTitle">Kumpulkan & Keluar?</h3>
    </header>
    <div class="body">
      <p id="exitDesc">Pastikan Anda sudah menekan <b>“Kirim”</b> pada Google Form. Waktu tersisa: <b id="leftTimeText">--:--</b>.</p>
      <p class="small" style="opacity:.85;margin:.2rem 0 0 0">Setelah keluar, Anda tidak lagi dapat mengubah jawaban.</p>
    </div>
    <div class="actions">
      <button class="btn ghost" id="btnCancelExit">Batal</button>
      <button class="btn danger" id="btnConfirmExit"><span class="spinner" id="exitSpin" style="display:none"></span><span id="exitText">Ya, Selesai</span></button>
    </div>
  </div>
</div>

<!-- Modal konfirmasi keluar PAKSA (Alt+F4) + SHAKE -->
<div class="modal-overlay" id="forceExitConfirm" role="dialog" aria-modal="true" aria-labelledby="forceExitTitle" aria-describedby="forceExitDesc">
  <div class="modal" id="forceExitModal">
    <header>
      <div class="ico">⚠️</div>
      <h3 id="forceExitTitle">Keluar Paksa?</h3>
    </header>
    <div class="body">
      <p id="forceExitDesc">Anda menekan <b>Alt+F4</b>. Menutup paksa dapat menyebabkan jawaban tidak tersimpan.</p>
      <p class="small" style="opacity:.85;margin:.2rem 0 0 0">Pilih <b>Batal</b> untuk tetap mengerjakan, atau <b>Keluar Sekarang</b> untuk menutup sesi.</p>
    </div>
    <div class="actions">
      <button class="btn ghost" id="btnCancelForceExit">Batal</button>
      <button class="btn danger" id="btnConfirmForceExit"><span class="spinner" id="forceExitSpin" style="display:none"></span><span id="forceExitText">Keluar Sekarang</span></button>
    </div>
  </div>
</div>

<!-- Fullscreen Guard -->
<div class="fsGuard" id="fsGuard">
  <div class="fsCard danger" id="fsCard">
    <div class="fsHead">🚫 PELANGGARAN TERDETEKSI</div>
    <p class="fsMsg" id="fsMsg">Sistem mendeteksi Anda keluar dari layar penuh atau membuka aplikasi lain.<BR>Ini dihitung sebagai pelanggaran.</p>
    <p class="fsCount"><b id="fsCount">Pelanggaran: 0 / 3</b></p>
    <button id="btnRestoreFS" class="btn">Kembali ke Fullscreen</button>
  </div>
</div>

<form id="finishForm" method="post" action="" style="display:none">
  <input type="hidden" name="aksi" value="finish_exam">
  <input type="hidden" name="reason" id="finishReason" value="manual">
</form>

<script>
(function(){
  // ===== Refs
  const overlay   = document.getElementById('overlay');
  const loader    = document.getElementById('loader');
  const mobileNote= document.getElementById('mobileNote');
  const btnStart  = document.getElementById('btnStart');
  const btnFinish = document.getElementById('btnFinish');
  const btnForceFin = document.getElementById('btnForceFinish');
  const finishForm   = document.getElementById('finishForm');
  const finishReason = document.getElementById('finishReason');

  const exitConfirm   = document.getElementById('exitConfirm');
  const btnCancelExit = document.getElementById('btnCancelExit');
  const btnConfirmExit= document.getElementById('btnConfirmExit');
  const exitSpin      = document.getElementById('exitSpin');
  const exitText      = document.getElementById('exitText');
  const leftTimeText  = document.getElementById('leftTimeText');

  // Force Exit (Alt+F4)
  const forceExitConfirm = document.getElementById('forceExitConfirm');
  const forceExitModal   = document.getElementById('forceExitModal');
  const btnCancelForceExit = document.getElementById('btnCancelForceExit');
  const btnConfirmForceExit= document.getElementById('btnConfirmForceExit');
  const forceExitSpin    = document.getElementById('forceExitSpin');
  const forceExitText    = document.getElementById('forceExitText');

  const timerEl  = document.getElementById('timer');
  const ringEl   = document.getElementById('ring');
  const barEl    = document.getElementById('bar');

  const statusText = document.getElementById('statusText');
  const dotState   = document.getElementById('dotState');

  const gframe   = document.getElementById('gformFrame');
  const frameWrap= document.getElementById('frameWrap');
  const timeUp   = document.getElementById('timeUp');
  const timeUpTitle = document.getElementById('timeUpTitle');
  const graceInfo   = document.getElementById('graceInfo');

  const fsGuard  = document.getElementById('fsGuard');
  const fsCard   = document.getElementById('fsCard');
  const fsCountEl= document.getElementById('fsCount');
  const btnRestoreFS = document.getElementById('btnRestoreFS');

  // ====== Pelanggaran UI refs
  const viToast    = document.getElementById('viToast');
  const viToastMsg = document.getElementById('viToastMsg');
  const viNum      = document.getElementById('viNum');
  const viLeft     = document.getElementById('viLeft');
  const viProg     = document.getElementById('viProg');

  // ===== Last-minute refs
  const lastMin   = document.getElementById('lastMin');
  const lmCard    = document.getElementById('lmCard');
  const lmLeft    = document.getElementById('lmLeft');
  const btnExt60  = document.getElementById('btnExt60');
  const btnLmClose= document.getElementById('btnLmClose');

  // Secure mask & watermark
  const secureMask = document.getElementById('secureMask');
  const wmEl       = document.getElementById('wm');

  const DURATION_MIN  = <?= json_encode($DURATION_MIN) ?>;
  const GRACE_SECONDS = <?= json_encode($GRACE_SECONDS) ?>;
  const VIOLATION_LIMIT = 3;
  const SESSION_TOKEN = <?= json_encode($TOKEN) ?>; // untuk watermark

  let DEADLINE_TS  = <?= json_encode($deadline_ts) ?> * 1000; // <= dibuat let agar bisa ditambah 60 detik
  const NOW_SERVER   = <?= json_encode(time()) ?> * 1000;
  let skew = Date.now() - NOW_SERVER;

  let examRunning=false, startTimeMs=null, timerInt=null, graceInt=null;

  // ===== last-minute state =====
  let lastMinuteShown = false; // notif 60 detik terakhir sudah ditampilkan?
  let hasExtended60 = false;   // perpanjang +60 detik sudah dipakai?
  let totalSec = null;         // basis progress untuk ring/bar (bisa bertambah saat extend)

  // Helpers platform
  const ua = navigator.userAgent || '';
  const isMobile = /Android|iPhone|iPad|iPod/i.test(ua);
  const isWindows = /Windows/i.test(ua);

  // ====== AJAX helper untuk event/heartbeat/finish (server logging) ======
  async function postAct(act, extra={}){
    const fd = new FormData(); fd.append('act', act); fd.append('token', SESSION_TOKEN);
    for(const k in extra) fd.append(k, extra[k]);
    try{ await fetch(location.href, {method:'POST', body:fd}); }catch(e){}
  }
  // Heartbeat berkala
  setInterval(()=>postAct('heartbeat'), 8000);

  // ===== (1) BLOKIR NEW TAB/POPUP DI HALAMAN ATAS (top window) =====
  (function enforceSameTab(){
    const _open = window.open;
    window.open = function(url, name, specs){
      try {
        if (url) { location.href = url; return window; }
      } catch(e){}
      return null;
    };
    document.addEventListener('click', function(e){
      const a = e.target && e.target.closest ? e.target.closest('a') : null;
      if (!a) return;
      const middle = e.button === 1;
      const mod    = e.ctrlKey || e.metaKey || e.shiftKey;
      if (a.target === '_blank' || middle || mod) {
        e.preventDefault();
        if (a.href) location.href = a.href;
      }
    }, true);
    Object.defineProperty(window, 'open', { writable:false, configurable:false, value:window.open });
  })();

  // ===== Pelanggaran state
  let violations = Number(localStorage.getItem('exam_violations') || 0);
  function updateViolationUI(){
    viNum.textContent = String(violations);
    const left = Math.max(0, VIOLATION_LIMIT - violations);
    viLeft.textContent = String(left);
    viProg.style.width = (violations / VIOLATION_LIMIT * 100) + '%';
    if (fsCountEl) fsCountEl.textContent = `Pelanggaran: ${violations} / ${VIOLATION_LIMIT}`;
  }
  function showViolationToast(reason){
    const left = Math.max(0, VIOLATION_LIMIT - violations);
    viToastMsg.innerHTML = `<b>Peringatan</b>: ${reason}. Sisa kesempatan: ${left}`;
    viToast.classList.add('show','shake');
    clearTimeout(viToast._tid);
    viToast._tid = setTimeout(()=> viToast.classList.remove('show'), 2200);
    viToast.addEventListener('animationend', (e)=>{ if (e.animationName==='shake') viToast.classList.remove('shake'); }, {once:true});
  }
  function addViolation(reason){
    violations += 1;
    localStorage.setItem('exam_violations', String(violations));
    updateViolationUI();
    showViolationToast(reason);
    postAct('event'); // server log
    if (violations >= VIOLATION_LIMIT) disqualify(reason);
  }
  updateViolationUI();

  // Loader: hilang saat GForm siap ATAU maksimal 2 detik
  const LOADER_TIMEOUT_MS = 2000;
  const loaderKill = setTimeout(()=>{ loader.style.display='none'; }, LOADER_TIMEOUT_MS);
  gframe.addEventListener('load', ()=> { clearTimeout(loaderKill); loader.style.display = 'none'; });

  // ===== Helpers
  function pad2(n){ return n.toString().padStart(2,'0'); }
  function fmtMMSS(sec){ const m=Math.floor(sec/60), s=sec%60; return pad2(m)+':'+pad2(s); }
  function setActiveUI(active){
    if (active) { dotState.style.background='var(--ok)'; dotState.style.boxShadow='0 0 0 8px rgba(16,185,129,.18)'; dotState.style.animation='pulseDot 2s ease-in-out infinite'; statusText.textContent='Mode Ujian Aktif'; }
    else { dotState.style.background='var(--danger)'; dotState.style.boxShadow='0 0 0 6px rgba(239,68,68,.12)'; dotState.style.animation='none'; statusText.textContent='Mode Ujian Tidak Aktif'; }
  }
  function setProgress(pct){ ringEl.style.setProperty('--pct', pct.toFixed(1)); barEl.style.width = pct.toFixed(1)+'%'; }
  function blockFrameInteraction(block){ frameWrap.style.pointerEvents = block ? 'none' : 'auto'; frameWrap.style.filter = block ? 'grayscale(.2) opacity(.85)' : 'none'; }

  // ===== Watermark anti-screenshot (deterrent)
  function escapeXml(s){ return s.replace(/[<>&'"]/g, c=>({ '<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;','\'':'&apos;' }[c])); }
  function applyWatermark(){
    const now = new Date();
    const t = now.toLocaleString();
    const text = `UJIAN | Token: ${SESSION_TOKEN} | ${t}`;
    const svg = `<svg xmlns='http://www.w3.org/2000/svg' width='360' height='220'>
      <g transform='rotate(-23 0 0)'>
        <text x='10' y='80' fill='rgba(255,255,255,0.14)' font-size='18' font-family='system-ui,Segoe UI,Roboto,Arial' >
          ${escapeXml(text)}
        </text>
      </g>
    </svg>`;
    const url = 'data:image/svg+xml;base64,' + btoa(svg);
    wmEl.style.backgroundImage = `url(${url})`;
    wmEl.style.backgroundRepeat = 'repeat';
    wmEl.style.backgroundSize = '360px 220px';
  }

  // ===== Fullscreen helpers
  function isFullscreenSupported(){
    return !!(document.fullscreenEnabled || document.webkitFullscreenEnabled || document.documentElement.requestFullscreen || document.documentElement.webkitRequestFullscreen);
  }
  async function enterFullscreen(){
    const el = document.documentElement;
    if (el.requestFullscreen) { await el.requestFullscreen({navigationUI:'hide'}); }
    else if (el.webkitRequestFullscreen) { el.webkitRequestFullscreen(); }
    if (screen.orientation && screen.orientation.lock) { try{ await screen.orientation.lock('portrait'); }catch(e){} }
  }
  async function exitFullscreen(){
    try{ if (document.exitFullscreen) await document.exitFullscreen(); else if (document.webkitExitFullscreen) document.webkitExitFullscreen(); }catch(e){}
  }

  // ====== LAST-MINUTE (helpers) ======
  function showLastMinute(sec){
    lastMinuteShown = true;
    lmLeft.textContent = String(sec);
    lastMin.classList.add('show');
    // shake card agar eye-catching
    lmCard.classList.remove('shake'); void lmCard.offsetWidth; lmCard.classList.add('shake');
  }
  function hideLastMinute(){
    lastMin.classList.remove('show');
  }

  // ===== Timer berbasis server deadline
  function startCountdown(){
    startTimeMs = Date.now();
    totalSec = Math.max(1, Math.floor((DEADLINE_TS - (Date.now()-skew)) / 1000)); // basis progress (bisa bertambah)
    timerInt = setInterval(()=>{
      const now = Date.now() - skew;
      let remain = Math.floor((DEADLINE_TS - now)/1000);
      if (remain < 0) remain = 0;

      // Tampilkan banner ketika pertama kali masuk 60 detik terakhir
      if (!lastMinuteShown && remain <= 60 && remain > 0){
        showLastMinute(remain);
      }
      // Update detik tersisa di banner bila sedang tampil
      if (lastMinuteShown){
        lmLeft.textContent = String(remain);
      }

      const elapsed = totalSec - remain;
      const pct = Math.min(100, Math.max(0, (elapsed / Math.max(1,totalSec)) * 100));
      setProgress(pct);
      timerEl.textContent = fmtMMSS(remain);

      if (remain === 0) { clearInterval(timerInt); timerInt=null; hideLastMinute(); timeIsUp(false); }
    }, 250);
  }

  // ===== End / Timeout
  async function timeIsUp(force){
    try { await exitFullscreen(); } catch(e){}
    examRunning=false; setActiveUI(false);
    blockFrameInteraction(true);
    timeUp.style.display='flex';
    timeUpTitle.textContent = force ? 'Ujian Diakhiri' : 'Waktu Ujian Habis';

    if (GRACE_SECONDS > 0 && !force){
      blockFrameInteraction(false);
      let left = GRACE_SECONDS;
      graceInfo.textContent = 'Kesempatan terakhir: ' + left + ' detik untuk klik \"Kirim\" di Google Form.';
      graceInt = setInterval(async ()=>{
        left--;
        if (left <= 0) {
          clearInterval(graceInt); graceInt=null;
          graceInfo.textContent='Waktu tenggang berakhir.';
          blockFrameInteraction(true);
          finishReason.value='timeout';
          await postAct('finish', {status:'timeout'});
          clearClientData().finally(()=> finishForm.submit());
        } else { graceInfo.textContent='Kesempatan terakhir: ' + left + ' detik untuk klik \"Kirim\" di Google Form.'; }
      }, 1000);
    } else {
      graceInfo.textContent='Interaksi dengan form telah diblokir.';
    }
  }

  // ===== Clear client-side data
  async function clearClientData(){
    try{ localStorage.removeItem('exam_violations'); sessionStorage.clear(); }catch(e){}
    if ('caches' in window){ try{ const keys = await caches.keys(); await Promise.all(keys.map(k=>caches.delete(k))); }catch(e){} }
    try{ history.replaceState(null,'', location.pathname); }catch(e){}
  }

  // ===== Diskualifikasi
  async function disqualify(reason){
    try{ await exitFullscreen(); }catch(e){}
    examRunning=false; setActiveUI(false);
    blockFrameInteraction(true);
    timeUp.style.display='flex';
    timeUpTitle.textContent = 'Anda Didiskualifikasi';
    graceInfo.textContent   = 'Alasan: ' + reason + '. Sesi ditutup.';
    await postAct('finish', {status:'diskualifikasi'});
    await clearClientData();
    setTimeout(()=>{ location.href = <?= json_encode($DQ_REDIRECT) ?>; }, 2200);
  }

  // ===== Modal keluar (normal)
  function openExitConfirm(){ leftTimeText.textContent = timerEl.textContent; exitConfirm.style.display='flex'; }
  function closeExitConfirm(){ exitConfirm.style.display='none'; btnConfirmExit.disabled=false; exitSpin.style.display='none'; exitText.textContent='Ya, Selesai'; }

  btnConfirmExit.addEventListener('click', async ()=>{
    btnConfirmExit.disabled=true; exitSpin.style.display='inline-block'; exitText.textContent='Memproses...';
    try{ await exitFullscreen(); }catch(e){}
    examRunning=false; setActiveUI(false);
    finishReason.value='manual';
    await postAct('finish', {status:'selesai'});
    await clearClientData();
    setTimeout(()=> finishForm.submit(), 500);
  });
  btnCancelExit.addEventListener('click', closeExitConfirm);

  // ===== Modal keluar PAKSA (Alt+F4)
  function openForceExitConfirm(){
    forceExitConfirm.style.display='flex';
    // efek shake
    forceExitModal.classList.remove('shake');
    void forceExitModal.offsetWidth; // reflow
    forceExitModal.classList.add('shake');
  }
  function closeForceExitConfirm(){
    forceExitConfirm.style.display='none';
    btnConfirmForceExit.disabled=false;
    forceExitSpin.style.display='none';
    forceExitText.textContent='Keluar Sekarang';
  }
  btnCancelForceExit.addEventListener('click', ()=>{
    closeForceExitConfirm();
    if (!document.fullscreenElement) enterFullscreen().catch(()=>{});
  });
  btnConfirmForceExit.addEventListener('click', async ()=>{
    btnConfirmForceExit.disabled=true; forceExitSpin.style.display='inline-block'; forceExitText.textContent='Memproses...';
    try{ await exitFullscreen(); }catch(e){}
    examRunning=false; setActiveUI(false);
    finishReason.value='force_close';
    await postAct('finish', {status:'selesai'});
    await clearClientData();
    setTimeout(()=> finishForm.submit(), 300);
  });

  // ===== Event utama
  ['pointerenter','mouseenter','mouseover'].forEach(ev=>gframe.addEventListener(ev, ()=>{}, false));

  btnStart.addEventListener('click', async ()=>{
    if (!isFullscreenSupported()) { mobileNote.style.display='block'; }
    try { await enterFullscreen(); } catch(e){ alert('Gagal masuk Fullscreen. Izinkan Fullscreen di browser.'); return; }
    overlay.style.display='none';
    examRunning=true; setActiveUI(true);
    setProgress(0);
    startCountdown();
    applyWatermark(); // aktifkan watermark
    wmEl._wmInt && clearInterval(wmEl._wmInt);
    wmEl._wmInt = setInterval(applyWatermark, 60000);

    // Kunci back: dorong state dummy & blok popstate
    try{
      history.pushState({fslock:1}, '', location.href);
      window.addEventListener('popstate', ()=>{
        history.pushState({fslock:1}, '', location.href);
        showFsGuard();
        addViolation('Navigasi mundur/keluar halaman');
      });
    }catch(e){}
  });

  btnFinish.addEventListener('click', ()=> openExitConfirm());

  if (btnForceFin){
    btnForceFin.addEventListener('click', async ()=>{
      blockFrameInteraction(true);
      finishReason.value='timeout_confirm';
      await postAct('finish', {status:'selesai'});
      clearClientData().finally(()=> finishForm.submit());
    });
  }

  // ====== FULLSCREEN LOCK / GUARD ======
  function updateFsGuard(){ if (fsCountEl) fsCountEl.textContent = `Pelanggaran: ${violations} / ${VIOLATION_LIMIT}`; }
  function showFsGuard(){
    fsGuard.style.display='flex';
    updateFsGuard();
    // shake card agar eye-catching
    fsCard.classList.remove('shake'); void fsCard.offsetWidth; fsCard.classList.add('shake');
  }
  function hideFsGuard(){ fsGuard.style.display='none'; }
  async function restoreFS(){ try{ await enterFullscreen(); hideFsGuard(); }catch(e){ alert('Browser membatalkan permintaan fullscreen. Klik tombol lagi.'); } }
  btnRestoreFS.addEventListener('click', restoreFS);

  // Jika fullscreen lepas → tampilkan guard & hitung pelanggaran
  document.addEventListener('fullscreenchange', ()=>{
    if (examRunning && !document.fullscreenElement){
      addViolation('Fullscreen tertutup');
      showFsGuard();
    }
  });

  // ==== KUNCI KEYBOARD ====
  document.addEventListener('keydown', (e)=>{
    if (!examRunning) return;
    const tag = (document.activeElement && document.activeElement.tagName || '').toLowerCase();
    const inEditable = tag==='input' || tag==='textarea' || document.activeElement?.isContentEditable;
    const k = (e.key || '').toLowerCase();
    const ctrlMeta = e.ctrlKey || e.metaKey;

    // Blokir Esc & F11
    if (k==='escape' || e.key==='F11'){ e.preventDefault(); e.stopPropagation(); showFsGuard(); return; }

    // Blokir Ctrl/⌘+W atau Ctrl/⌘+F4
    if (ctrlMeta && (k==='w' || k==='f4')){ e.preventDefault(); e.stopPropagation(); showFsGuard(); return; }

    // Alt+F4 → konfirmasi keluar paksa + shake
    if (e.altKey && k==='f4'){ e.preventDefault(); e.stopPropagation(); openForceExitConfirm(); return; }

    // Blokir Backspace untuk navigasi mundur (kecuali sedang mengetik)
    if (k==='backspace' && !inEditable){ e.preventDefault(); e.stopPropagation(); return; }

    // Blokir Alt+Left/Right (navigate)
    if (e.altKey && (k==='arrowleft' || k==='arrowright')){ e.preventDefault(); e.stopPropagation(); return; }

    // Beberapa keyboard punya ‘BrowserBack/BrowserForward’
    if (k==='browserback' || k==='browserforward'){ e.preventDefault(); e.stopPropagation(); return; }

    // Tombol Windows (Meta/OS key)
    if (isWindows && (k==='meta' || k==='os')) {
      e.preventDefault(); e.stopPropagation();
      addViolation('Menekan tombol Windows (Meta/OS key)');
      showFsGuard();
      return;
    }
  }, true);

  // ==== DETEKSI ALT+TAB / CTRL+TAB & MOBILE APP SWITCHER (PATCHED) ====
  // Hanya hitung pelanggaran & tampilkan secureMask bila tab BENAR-BENAR tersembunyi.
  document.addEventListener('visibilitychange', ()=>{
    if (!examRunning) return;
    if (document.hidden) {
      if (isMobile) secureMask.style.display = 'block';
      const reason = isMobile
        ? 'Mobile: Home/Recent Apps/Tab Switcher/Menu'
        : 'Berpindah tab/jendela/aplikasi (Alt+Tab/Ctrl+Tab)';
      addViolation(reason);
      showFsGuard();
    } else {
      if (isMobile) secureMask.style.display = 'none';
    }
  });

  // Event blur sering terpicu saat fokus pindah ke dalam iframe.
  // Tunda 120ms lalu cek lagi visibilityState agar tidak false positive.
  window.addEventListener('blur', ()=>{
    if (!examRunning) return;
    setTimeout(()=>{
      if (document.visibilityState === 'hidden') {
        if (isMobile) secureMask.style.display = 'block';
        const reason = isMobile
          ? 'Mobile: Home/Recent Apps/Tab Switcher/Menu'
          : 'Fokus jendela hilang (Alt+Tab/Ctrl+Tab/Win key)';
        addViolation(reason);
        showFsGuard();
      }
    }, 120);
  });

  window.addEventListener('focus', ()=>{
    if (isMobile) secureMask.style.display = 'none';
  });

  // ==== (2) NONAKTIFKAN KLIK KANAN SAAT UJIAN ====
  window.addEventListener('contextmenu', e=>{ if (examRunning) e.preventDefault(); });

  // Jika tab mau ditutup / reload
  window.addEventListener('beforeunload', function (e) {
    if (!examRunning) return;
    e.preventDefault(); e.returnValue = '';
  });

  // Responsif tinggi iframe
  function adjustFrameHeight(){
    const vh = Math.max(document.documentElement.clientHeight, window.innerHeight || 0);
    gframe.style.height = Math.max(360, vh - 198) + 'px';
  }
  window.addEventListener('resize', adjustFrameHeight);
  adjustFrameHeight();

  // Deteksi iOS safari lama — Chrome Android mendukung Fullscreen API (UI bar mungkin auto-hide)
  (function(){
    const isIOS = /iPad|iPhone|iPod/.test(ua);
    const isOldSafari = isIOS && !document.documentElement.requestFullscreen;
    if (isOldSafari) mobileNote.style.display='block';
  })();

  // ====== Last-minute buttons ======
  if (btnExt60){
    btnExt60.addEventListener('click', ()=>{
      if (hasExtended60) return;
      hasExtended60 = true;
      DEADLINE_TS += 60000;  // +60 detik
      if (totalSec !== null) totalSec += 60; // progress base ikut bertambah
      btnExt60.disabled = true;
      btnExt60.textContent = 'Ditambah +60 detik';
      // feedback kecil
      lmCard.classList.remove('shake'); void lmCard.offsetWidth; lmCard.classList.add('shake');
      // (opsional) bisa auto-close banner setelah 1.2s
      setTimeout(()=>{ hideLastMinute(); }, 1200);
    });
  }
  if (btnLmClose){
    btnLmClose.addEventListener('click', hideLastMinute);
  }
})();
</script>
</body>
</html>
