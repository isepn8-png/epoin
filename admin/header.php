<?php

// ==== START: BOOTSTRAP AUTH & SESSION ====
if (session_status() === PHP_SESSION_NONE) session_start();
include '../koneksi.php';

// === Usage hook bootstrap ===
require_once __DIR__ . '/../includes/usage_helper.php';
require_once __DIR__ . '/../includes/epoin_security.php';

// Ambil sekolah_id aktif (satu tenant/satu DB)
$_r = mysqli_query($koneksi,"SELECT sekolah_id FROM sekolah LIMIT 1");
$SEKOLAH_ID = 0;
if ($_r && $row = mysqli_fetch_assoc($q=$_r)) $SEKOLAH_ID = (int)$row['sekolah_id'];

// Pastikan tabel siap & baris kuota ada
usage_bootstrap($koneksi, $SEKOLAH_ID);

// (opsional) simpan global agar mudah dipakai di file lain
$GLOBALS['SEKOLAH_ID'] = $SEKOLAH_ID;
require_once __DIR__ . '/../includes/theme_brand.php';


// ==== BASE URL ADMIN (dinamis) ====
$__is_https   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] == 443);
$__scheme     = $__is_https ? 'https' : 'http';
$__admin_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');   // contoh: /epoin/admin
$__ADMIN_URL  = $__scheme.'://'.$_SERVER['HTTP_HOST'].$__admin_path.'/'; // contoh: https://domain/epoin/admin/

// >>> REVISI KHUSUS MENU (tidak mengubah bagian lain):
// URL Absolut (protocol-relative) untuk Absensi Harian agar tidak terpengaruh <base> & skema
$__ABSENSI_HARIAN_URL = '//' . $_SERVER['HTTP_HOST'] . $__admin_path . '/absensi_harian.php';

// Opsi integrasi auth eksternal
$user_id = 0;
$__auth_file = __DIR__ . '/../includes/auth.php';
if (is_file($__auth_file)) {
  require_once $__auth_file;
  if (function_exists('ensure_logged_in')) { @ensure_logged_in(); }
  if (function_exists('current_user_id')) { $user_id = (int) @current_user_id(); }
}

// Fallback ke session
if ($user_id <= 0) {
  if (empty($_SESSION['id'])) {
    header("Location: ../admin.php?alert=belum_login");
    exit;
  }
  $user_id = (int) $_SESSION['id'];
} else {
  if (empty($_SESSION['id'])) $_SESSION['id'] = $user_id;
}

// ==== START: ROLE & PERMISSION HELPERS ====
if (!function_exists('normalize_role_key')) {
  function normalize_role_key($k){
    $k = strtolower(trim((string)$k));
    $k = str_replace([' ', '-'], ['',''], $k);
    return $k;
  }
}
if (!function_exists('load_user_roles')) {
  function load_user_roles($uid){
    global $koneksi;
    $roles = [];
       $q = mysqli_query($koneksi,"SELECT r.role_key
                                FROM user_roles ur
                                JOIN roles r ON r.role_id=ur.role_id
                                WHERE ur.user_id=".(int)$uid);
    if($q){ while($row=mysqli_fetch_assoc($q)){ $roles[]=$row['role_key']; } }
    if(isset($_SESSION['level']) && $_SESSION['level']==='administrator'){
      $roles[]='administrator';
    }
    return $roles;
  }
}
if (!function_exists('load_user_permissions')) {
  // FIX (Sub-fase 2): dulu return [] -> menimpa perms yang benar. Sekarang load asli dari DB.
  // Catatan: auth.php (di-load di atas) sudah mendefinisikan versi kanonik; ini fallback defensif.
  function load_user_permissions($uid){
    global $koneksi;
    $uid = (int)$uid; $perms = [];
    if ($uid <= 0 || !($koneksi instanceof mysqli)) return $perms;
    $sql = "SELECT DISTINCT p.perm_key FROM user_roles ur
            JOIN role_permissions rp ON rp.role_id=ur.role_id
            JOIN permissions p ON p.perm_id=rp.perm_id
            WHERE ur.user_id=?";
    if ($st = mysqli_prepare($koneksi,$sql)) {
      mysqli_stmt_bind_param($st,'i',$uid); mysqli_stmt_execute($st);
      $rs = mysqli_stmt_get_result($st);
      while ($rs && ($row=mysqli_fetch_assoc($rs))) $perms[$row['perm_key']]=true;
      mysqli_stmt_close($st);
    }
    return $perms;
  }
}
if (!function_exists('user_has_role')) {
  function user_has_role($roleWanted){
    $roles = $_SESSION['roles'] ?? [];
    $needle = normalize_role_key($roleWanted);
    foreach($roles as $r){ if (normalize_role_key($r) === $needle) return true; }
    return false;
  }
}
if (!function_exists('user_has_any_role')) { function user_has_any_role(array $keys){ foreach($keys as $k){ if(user_has_role($k)) return true; } return false; } }
/* REVISI: admin-like mencakup administrator/superadmin/admin */
if (!function_exists('_is_admin')){ function _is_admin(){ return user_has_any_role(['administrator','superadmin','admin']); } }
if (!function_exists('_is_guru')) { function _is_guru(){ return user_has_role('guru'); } }
if (!function_exists('_is_tas'))  { function _is_tas(){  return user_has_role('tas');  } }

// === Dukungan role SEKRETARIS (pakai sinonim)
if (!function_exists('_is_sekretaris')) {
  function _is_sekretaris(){ return user_has_any_role(['sekretaris','sekretaris_kelas','sekretariskelas']); }
}
if (!function_exists('_is_only_sekretaris')) {
  function _is_only_sekretaris(){
    $roles = $_SESSION['roles'] ?? [];
    if (empty($roles)) return false;
    $hasSek = _is_sekretaris(); if (!$hasSek) return false;
    $elevated = ['administrator','superadmin','admin','guru','tas'];
    foreach($elevated as $k){ if (user_has_role($k)) return false; }
    foreach($roles as $r){
      $rn = normalize_role_key($r);
      if (!in_array($rn, ['sekretaris','sekretaris_kelas','sekretariskelas'], true)) {
        if (!in_array($rn, array_map('normalize_role_key',$elevated), true)) return false;
      }
    }
    return true;
  }
}

if (!function_exists('_is_piket')) {
  function _is_piket(){ return user_has_role('piket'); }
}

if (!function_exists('_is_only_piket')) {
  function _is_only_piket(){
    $roles = $_SESSION['roles'] ?? [];
    if (empty($roles)) return false;
    if (!_is_piket()) return false;
    // Jika user juga administrator/superadmin/guru/tas/sekretaris -> BUKAN "piket-only"
    $elevated = ['administrator','superadmin','admin','guru','tas','sekretaris'];
    foreach($elevated as $k){ if (user_has_role($k)) return false; }
    // Pastikan seluruh role yang dimiliki termasuk 'piket' (tanpa yang lain)
    foreach($roles as $r){
      $rn = normalize_role_key($r);
      if ($rn !== 'piket') return false;
    }
    return true;
  }
}

if (empty($_SESSION['roles'])) { $_SESSION['roles'] = load_user_roles($user_id); }
if (empty($_SESSION['perms'])) { $_SESSION['perms'] = load_user_permissions($user_id); }

/* =======================================================================
   [FIX-LEVEL-BRIDGE] — JEMBATAN KOMPATIBILITAS UNTUK HALAMAN LAMA
   Beberapa file lama masih mengecek $_SESSION['level'] === 'administrator'.
   Agar GURU yang punya role administrator tidak “diusir” saat buka menu admin,
   sinkronkan level berbasis roles yang dimiliki user.
   ======================================================================= */
if (!empty($_SESSION['roles'])) {
  if (user_has_any_role(['administrator','superadmin','admin'])) {
    $_SESSION['level'] = 'administrator';
  } elseif (user_has_role('tas')) {
    $_SESSION['level'] = 'tas';
  } elseif (user_has_role('guru')) {
    $_SESSION['level'] = 'guru';
  }
}
/* =======================================================================
   CATATAN: Ini tidak mengubah UI menu (tetap berdasar roles), hanya
   memastikan halaman lama yang cek "level" tidak memaksa logout.
   ======================================================================= */

/* === GUARD: batasi akses untuk SEKRETARIS ===
   REVISI: whitelist → 'absensi_harian.php', 'rekap_kelas.php' (Monitoring), 'logout.php', 'verify_pin.php'
   (Sinkron Absensi 'data_absensi.php' DIHILANGKAN dari akses sekretaris)
*/
if (_is_only_sekretaris()) {
  $ALLOWED = [
    'absensi_harian.php',
    'rekap_kelas.php',   // <-- Monitoring Absensi
    'logout.php',
    'verify_pin.php'
  ];
  $cur = basename($_SERVER['PHP_SELF']);
  if (!in_array($cur, $ALLOWED, true)) {
    header('Location: absensi_harian.php?alert=akses_terbatas');
    exit;
  }
}

// ==== START: DB UTILS & COUNTERS ====
if (!function_exists('table_exists')) {
  function table_exists($koneksi, $name){
    $q = @mysqli_query($koneksi,"SHOW TABLES LIKE '".mysqli_real_escape_string($koneksi,$name)."'");
    return $q && mysqli_num_rows($q)>0;
  }
}
if (!function_exists('count_table')) {
  function count_table($koneksi, $table){
    $res = @mysqli_query($koneksi, "SELECT COUNT(*) AS jml FROM `$table`");
    if($res){ $row = mysqli_fetch_assoc($res); return (int)$row['jml']; }
    return 0;
  }
}
if (!function_exists('safe_count')) { function safe_count($koneksi,$table){ return table_exists($koneksi,$table) ? count_table($koneksi,$table) : 0; } }

/* Helper tambahan untuk poin baru (laporan) */
if (!function_exists('table_columns')){
  function table_columns($koneksi, $table){
    $cols=[]; $res=@mysqli_query($koneksi,"SHOW COLUMNS FROM `$table`");
    if($res){ while($r=@mysqli_fetch_assoc($res)){ if(!empty($r['Field'])) $cols[]=$r['Field']; } }
    return $cols;
  }
}
if (!function_exists('count_new_since')){
  function count_new_since($koneksi,$table,$sinceStr){
    if(!table_exists($koneksi,$table)) return 0;
    $cols = table_columns($koneksi,$table);
    $cands = ['updated_at','created_at','tanggal','tgl','date','waktu','waktu_input','inserted_at','createdat','updatedat'];
    $checks=[];
    foreach($cands as $c){ if(in_array($c,$cols,true)){ $checks[] = "`$c` IS NOT NULL AND `$c` > '".mysqli_real_escape_string($koneksi,$sinceStr)."'"; } }
    if(empty($checks)) return 0;
    $sql = "SELECT COUNT(*) AS c FROM `$table` WHERE (".implode(' OR ',$checks).")";
    $q = @mysqli_query($koneksi,$sql);
    if($q && $row=@mysqli_fetch_assoc($q)) return (int)$row['c'];
    return 0;
  }
}

/* =================================================================
   REVISI KUAT: hitung total "hari non-efektif" utk badge Kalender.
   ...
   ================================================================= */
if (!function_exists('count_hari_non_efektif_kalender')){
  function count_hari_non_efektif_kalender($koneksi){
    global $SEKOLAH_ID;
    if (!table_exists($koneksi,'kalender_akademik')) return 0;

    $cols = table_columns($koneksi,'kalender_akademik');
    if (!$cols) return 0;

    // kandidat kolom
    $boolCandidates = ['is_non_efektif','non_efektif','is_libur','libur','is_tidak_efektif','tidak_efektif'];
    $textCandidates = ['jenis','tipe','kategori','status','keterangan','deskripsi','catatan','nama','judul'];
    $startCandidates= ['tanggal_mulai','tgl_mulai','start_date','mulai','tanggal_awal','tanggal','tgl','date_from','start'];
    $endCandidates  = ['tanggal_selesai','tgl_selesai','end_date','selesai','tanggal_akhir','tgl_akhir','date_to','end'];
    $daysCandidates = ['hari_non_efektif','total_hari','jumlah_hari','jml_hari','durasi_hari','lama_hari','hari'];

    $whereParts = [];

    // filter tenant bila ada
    if (in_array('sekolah_id',$cols,true)) {
      $whereParts[] = '`sekolah_id`='.(int)$SEKOLAH_ID;
    }

    // bangun kondisi "non efektif"
    $condParts = [];
    foreach(array_values(array_intersect($boolCandidates,$cols)) as $c){
      $condParts[] = "(`$c` IN (1,'1','Y','y','T','t','true','TRUE','Ya','YA','yes','YES'))";
    }
    foreach(array_values(array_intersect($textCandidates,$cols)) as $c){
      $likes = [
        "LOWER(`$c`) LIKE '%non%efektif%'",
        "LOWER(`$c`) LIKE '%tidak%efektif%'",
        "LOWER(`$c`) LIKE '%libur%'",
        "LOWER(`$c`) LIKE '%tanggal%merah%'",
        "LOWER(`$c`) LIKE '%holiday%'",
        "LOWER(`$c`) LIKE '%cuti%bersama%'",
      ];
        $condParts[] = '(' . implode(' OR ', $likes) . ')';
    }
    $hasCond = !empty($condParts);
    if ($hasCond) $whereParts[] = '(' . implode(' OR ', $condParts) . ')';
    $whereSql = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

    // pilih kolom jumlah hari bila ada
    $dayCol = null;
    foreach($daysCandidates as $c){ if(in_array($c,$cols,true)){ $dayCol=$c; break; } }

    if ($dayCol){
      $sql = "SELECT COALESCE(SUM(NULLIF(`$dayCol`,'')),0) AS c FROM `kalender_akademik` $whereSql";
    } else {
      // pilih kolom tanggal rentang
      $startCol = null; foreach($startCandidates as $c){ if(in_array($c,$cols,true)){ $startCol=$c; break; } }
      $endCol   = null; foreach($endCandidates   as $c){ if(in_array($c,$cols,true)){ $endCol=$c; break; } }

      if ($startCol){
        $endExpr = $endCol ? "`$endCol`" : "`$startCol`";
        $sql = "SELECT COALESCE(SUM(DATEDIFF($endExpr, `$startCol`) + 1),0) AS c FROM `kalender_akademik` $whereSql";
      } else {
        $sql = "SELECT COUNT(*) AS c FROM `kalender_akademik` $whereSql";
      }
    }

    $q = @mysqli_query($koneksi,$sql);
    if ($q && $row=@mysqli_fetch_assoc($q)) {
      $val = (int)($row['c'] ?? 0);

      if ($val===0 && !$hasCond){
        $qc = @mysqli_query($koneksi,"SELECT COUNT(*) AS j FROM `kalender_akademik` $whereSql");
        if ($qc && $rr=@mysqli_fetch_assoc($qc)) return (int)$rr['j'];
      }
      return $val;
    }
    return 0;
  }
}

$jumlah_siswa             = safe_count($koneksi,'siswa');
$jumlah_rombel            = safe_count($koneksi,'jurusan');
$jumlah_kelas             = safe_count($koneksi,'kelas');
$jumlah_tahun_ajaran      = safe_count($koneksi,'ta');
$jumlah_admin             = safe_count($koneksi,'user');
$jumlah_jenis_prestasi    = safe_count($koneksi,'prestasi');
$jumlah_jenis_pelanggaran = safe_count($koneksi,'pelanggaran');
$jumlah_mapel             = safe_count($koneksi,'mapel');
$jumlah_pengampu_mapel    = safe_count($koneksi,'pengampu_mapel');
$jumlah_kalender_akademik = safe_count($koneksi,'kalender_akademik');
$jumlah_hari_non_efektif  = count_hari_non_efektif_kalender($koneksi);

// ====== MONITORING ABSENSI: badge perubahan ======
if (empty($_SESSION['monitor_seen_at'])) {
  $_SESSION['monitor_seen_at'] = time();
}
if (basename($_SERVER['PHP_SELF']) === 'rekap_kelas.php') {
  $_SESSION['monitor_seen_at'] = time();
}
$monitor_last_seen_ts  = (int)($_SESSION['monitor_seen_at'] ?? time());
$monitor_last_seen_str = date('Y-m-d H:i:s', $monitor_last_seen_ts);
$monitor_new_count = 0;
if (table_exists($koneksi,'absensi_harian_detail')) {
  $since = mysqli_real_escape_string($koneksi, $monitor_last_seen_str);
  $sqlMon = "
    SELECT COUNT(*) AS c FROM (
      SELECT d.harian_id
      FROM absensi_harian_detail d
      WHERE (d.updated_at IS NOT NULL AND d.updated_at > '$since')
      GROUP BY d.harian_id
      UNION
      SELECT h.harian_id
      FROM absensi_harian h
      WHERE ((h.updated_at IS NOT NULL AND h.updated_at > '$since')
             OR (h.created_at IS NOT NULL AND h.created_at > '$since'))
    ) x";
  $qMon = @mysqli_query($koneksi,$sqlMon);
  if ($qMon && $row=mysqli_fetch_assoc($qMon)) $monitor_new_count = (int)$row['c'];
}

/* ====== LAPORAN POIN SISWA: badge perubahan (mirip Monitoring) ====== */
if (empty($_SESSION['laporan_seen_at'])) {
  $_SESSION['laporan_seen_at'] = time();
}
if (basename($_SERVER['PHP_SELF']) === 'laporan.php') {
  $_SESSION['laporan_seen_at'] = time();
}
$laporan_last_seen_ts  = (int)($_SESSION['laporan_seen_at'] ?? time());
$laporan_last_seen_str = date('Y-m-d H:i:s', $laporan_last_seen_ts);
$laporan_new_count = 0;
$candidate_point_tables = ['poin','poin_siswa','prestasi_siswa','pelanggaran_siswa','poin_history','riwayat_poin'];
foreach($candidate_point_tables as $t){
  if(table_exists($koneksi,$t)){
    $laporan_new_count += count_new_since($koneksi,$t,$laporan_last_seen_str);
  }
}

/* ====== DETEKSI: Apakah user ini WALI KELAS? ====== */
if (!function_exists('is_wali_kelas')) {
  function is_wali_kelas($uid){
    global $koneksi;
    if (!table_exists($koneksi,'kelas_wali')) return false;
    $uid = (int)$uid;
    $cols = [];
    $res = @mysqli_query($koneksi,"SHOW COLUMNS FROM kelas_wali");
    if($res){ while($r=@mysqli_fetch_assoc($res)){ if(!empty($r['Field'])) $cols[] = $r['Field']; } }
    $candidate = ['user_id','wali_user_id','guru_id','id_user','id_guru'];
    $found = array_values(array_intersect($candidate, $cols));
    if(empty($found)) return false;
    $conds = [];
    foreach($found as $c){ $conds[] = "$c=$uid"; }
    $sql = "SELECT 1 FROM kelas_wali WHERE ".implode(' OR ', $conds)." LIMIT 1";
    $q = @mysqli_query($koneksi,$sql);
    return ($q && mysqli_fetch_row($q)) ? true : false;
  }
}
$is_wali_kelas = is_wali_kelas($user_id);

// Boleh set $PAGE_TITLE sebelum include header.php; default "Administrator"
$PAGE_TITLE = isset($PAGE_TITLE) && $PAGE_TITLE!=='' ? $PAGE_TITLE : 'Administrator';

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title><?php echo build_full_title($PAGE_TITLE); ?></title>
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
  <base href="<?php echo htmlspecialchars($__ADMIN_URL, ENT_QUOTES, 'UTF-8'); ?>">

  <!-- ANTI-FLICKER: tambahkan kelas seawal mungkin -->
  <script>document.documentElement.classList.add('init-loading');</script>

  <!-- Preconnect -->
  <link rel="preconnect" href="https://code.jquery.com" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">

  <!-- CSS Vendor -->
  <link rel="stylesheet" href="../assets/bower_components/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="../assets/bower_components/Ionicons/css/ionicons.min.css">
  <link rel="stylesheet" href="../assets/dist/css/AdminLTE.min.css">
  <link rel="stylesheet" href="../assets/dist/css/skins/_all-skins.min.css">
  <link rel="stylesheet" href="../assets/bower_components/datatables.net-bs/css/dataTables.bootstrap.min.css">
  <link rel="stylesheet" href="../assets/bower_components/morris.js/morris.css">
  <link rel="stylesheet" href="../assets/bower_components/jvectormap/jquery-jvectormap.css">
  <link rel="stylesheet" href="../assets/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css">
  <link rel="stylesheet" href="../assets/bower_components/bootstrap-daterangepicker/daterangepicker.css">
  <link rel="stylesheet" href="../assets/plugins/bootstrap-wysihtml5/bootstrap3-wysihtml5.min.css">
  <link rel="stylesheet" href="../assets/bower_components/select2/dist/css/select2.min.css">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-theme/0.1.0-beta.10/select2-bootstrap.min.css">

  <!-- Styles (dipertahankan) -->
  <style>html, body{ font-family:"Source Sans Pro", Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size:14px; }</style>

<!-- ========= CSS (letakkan di <head>) ========= -->
<style>
  /* Tombol logout header — merah elegan */
  .navbar-custom-menu .nav-logout .nav-logout-btn{display:inline-flex;align-items:center;gap:10px;padding:8px 14px;border-radius:999px;font-weight:800;color:#fff!important;line-height:1;border:0;background:linear-gradient(135deg,#ef4444,#dc2626);box-shadow:0 8px 18px rgba(239,68,68,.28);transition:transform .12s ease, box-shadow .12s ease, background .18s ease;margin:8px 12px 8px 0;}
  .navbar-custom-menu .nav-logout .nav-logout-btn:hover{transform:translateY(-1px);box-shadow:0 12px 26px rgba(185,28,28,.35);background:linear-gradient(135deg,#b91c1c,#991b1b);} 
  .nav-logout .ic-wrap{position:relative;width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;background:rgba(255,255,255,.18);} 
  .nav-logout .ic-wrap i{font-size:14px;line-height:1;} 
  .nav-logout .ic-wave{position:absolute;inset:0;border-radius:50%;background:radial-gradient(closest-side, rgba(255,255,255,.7), transparent);transform: scale(0);opacity:0;pointer-events:none;} 
  .nav-logout-btn:hover .ic-wave{animation: logoutWave .9s ease-out;} 
  @keyframes logoutWave{0%{transform:scale(.35);opacity:.55;}100%{transform:scale(1.4);opacity:0;}}
  @media (max-width: 480px){.navbar-custom-menu .nav-logout .nav-logout-btn{padding:8px 10px;margin-right:10px}.navbar-custom-menu .nav-logout .nav-logout-btn .text-label{display:none}.nav-logout .ic-wrap{margin-right:0}}
  .swal2-popup.swal2-brand{border-radius:18px!important;border:1px solid #e5e7eb!important}
  .swal2-title{font-weight:800!important;color:#111827!important}
  .swal2-styled.swal2-confirm{background:linear-gradient(135deg,#ef4444,#dc2626)!important;border:0!important;border-radius:999px!important;font-weight:800!important}
  .swal2-styled.swal2-cancel{background:#e5e7eb!important;color:#111827!important;border-radius:999px!important;font-weight:700!important}

  /* center avatar + nama user */
  .navbar-custom-menu .user.user-menu > a{display:flex!important;align-items:center!important;gap:10px;padding-top:0;padding-bottom:0;height:56px}
  .navbar-custom-menu .user.user-menu .user-image{float:none!important;width:28px;height:28px;margin:0!important;border-radius:50%;object-fit:cover;display:inline-block;vertical-align:middle}
  .navbar-custom-menu .user.user-menu .hidden-xs{line-height:1.1;font-weight:700}
  @media (max-width:480px){.navbar-custom-menu .user.user-menu > a{height:52px}}
</style>


  <!-- ANTI-FLICKER CSS -->
  <style>
    .content-wrapper,.right-side,.main-footer{border-left:0!important}
    html.init-loading .content-wrapper,html.init-loading .right-side,html.init-loading .main-footer{transition:none!important}
  </style>

  <style>
    .theme-slate{ --nav-from:#0f172a; --nav-to:#1e293b; --nav-logo:#0b1220; }
    .skin-green .main-header .navbar{ background:linear-gradient(90deg,var(--nav-from),var(--nav-to))!important;color:#fff;box-shadow:0 6px 18px rgba(0,0,0,.08);} 
    .skin-green .main-header .logo{ background:var(--nav-logo)!important;color:#fff!important;border-bottom:0;}
    .skin-green .main-header .navbar .nav>li>a,
    .skin-green .main-header .navbar .sidebar-toggle{ color:#fff!important; }
    .skin-green .main-header .navbar .sidebar-toggle:hover{ background:rgba(255,255,255,.08)!important; }

    .sidebar .header{ color:#9fb3c8; letter-spacing:.3px; font-weight:700; padding-top:8px; }
    .sidebar-menu>li>a{ display:flex; align-items:center; gap:8px; font-weight:600; letter-spacing:.2px; color:#cfd8dc; padding:10px 12px; position:relative; transition:padding-left .18s ease, background .25s ease, color .25s ease, box-shadow .25s ease, filter .25s ease; font-size:13px; line-height:1.25; }
    .sidebar-menu>li>a .menu-ic{ width:20px; height:20px; line-height:20px; display:inline-flex; align-items:center; justify-content:center; border-radius:7px; background:rgba(255,255,255,.06); transition: transform .18s ease, background .25s ease, color .25s ease; flex:0 0 20px; }
    .menu-text{ line-height:1.2; flex:1 1 auto; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .sidebar-menu>li\a .pull-right-container{ margin-left:auto; flex:0 0 auto;}
    .sidebar-menu .label{ border-radius:12px; padding:2px 6px; font-weight:700; font-size:10.5px; line-height:1; }
    .sidebar-menu>li>a:hover{ padding-left:16px; background:linear-gradient(90deg,#00d2ff 0%, #3a7bd5 100%); color:#fff!important; box-shadow:0 2px 10px rgba(3,169,244,.22); filter:saturate(1.08); }
    .sidebar-menu>li>a:hover .menu-ic{ background:rgba(255,255,255,.18); transform: translateX(1px); }
    .sidebar-menu>li.active>a{ background:linear-gradient(90deg,#10b981,#0ea5e9); color:#fff!important; box-shadow: inset 4px 0 0 #22d3ee; text-shadow:0 1px 0 rgba(0,0,0,.18); }
    .sidebar-menu .treeview-menu>li\a{ display:flex; align-items:center; gap:8px; padding:8px 12px 8px 24px; font-weight:600; color:#e2e8f0; transition: padding-left .18s ease, background .25s ease, color .25s ease, box-shadow .25s ease; font-size:12.5px; line-height:1.25; }
    .sidebar-menu .treeview-menu>li .menu-ic{ width:16px; height:16px; line-height:16px; background:transparent; flex:0 0 16px; }
    .sidebar-menu .treeview-menu>li>a:hover{ padding-left:28px; background:linear-gradient(90deg,#0ea5e9 0%, #2563eb 50%, #06b6d4 100%)!important; color:#fff!important; text-shadow:0 1px 0 rgba(0,0,0,.22); box-shadow: inset 3px 0 0 #22d3ee, 0 2px 10px rgba(2,132,199,.25); }
    .sidebar-menu .treeview-menu>li.active>a{ background:linear-gradient(90deg,#60a5fa,#a78bfa)!important; color:#0b1220!important; box-shadow: inset 3px 0 0 #22d3ee; font-weight:700; }
    .treeview > a .fa-angle-left{ transition: transform .18s ease; margin-top:3px; }
    .treeview.menu-open > a .fa-angle-left{ transform: rotate(-90deg); }
    .sidebar-menu a.menu-logout{ background:#e53935; color:#fff!important; font-weight:700; border-top:1px solid rgba(255,255,255,.08); }
    .sidebar-menu a.menu-logout:hover{ background:#d32f2f!important; color:#fff!important; box-shadow:none; padding-left:12px; }
    .sidebar-menu>li.active>a.menu-logout{ background:#e53935!important; box-shadow:none; }

    .nav-tabs-custom > .nav-tabs > li > a:hover{background:#eaf3ff!important;color:#1d4ed8!important;box-shadow:0 6px 14px rgba(29,78,216,.15)!important;}
    .nav-tabs-custom > .nav-tabs > li.active > a,
    .nav-tabs-custom > .nav-tabs > li.active > a:focus,
    .nav-tabs-custom > .nav-tabs > li.active > a:hover{background:linear-gradient(135deg,#0ea5e9,#3b82f6)!important;color:#fff!important;box-shadow:0 10px 24px rgba[59,130,246,.35]!important;}

    .label-new{ background: linear-gradient(45deg,#ff4081,#ffca28); color:#fff; border-radius:999px; padding:2px 7px; font-weight:700; font-size:10px; animation:pulseNew 1.2s ease-in-out infinite; box-shadow:0 0 0 0 rgba(255,64,129,.35); vertical-align:middle; margin-left:6px; }
    .label-new.muted{ animation:none; opacity:.75; }
    .badge-count{ margin-left:6px; }
    @keyframes pulseNew{ 0%{transform:scale(1);box-shadow:0 0 0 0 rgba(255,64,129,.35);}60%{transform:scale(1.08);box-shadow:0 0 0 10px rgba(255,64,129,0);}100%{transform:scale(1);box-shadow:0 0 0 0 rgba(255,64,129,0);} }

    @media (max-width: 767px){
      .sidebar-menu>li>a:hover{ padding-left:14px; }
      .sidebar-menu .treeview-menu>li>a:hover{ padding-left:26px; }
    }
  </style>

  <style>
    :root{ --hover-grad-green:linear-gradient(90deg,#10b981 0%, #22d3ee 100%); --active-grad-green:linear-gradient(90deg,#10b981 0%, #0ea5e9 100%); --accent:#22d3ee; }
    .skin-green .sidebar-menu>li+a:hover{ background:var(--hover-grad-green)!important;color:#fff!important;box-shadow:0 2px 10px rgba(16,185,129,.25);} 
    .skin-green .sidebar-menu>li.active>a{ background:var(--active-grad-green)!important;color:#fff!important;box-shadow: inset 4px 0 0 var(--accent);} 
    .skin-green .sidebar-menu .treeview-menu>li>a:hover{ background:var(--hover-grad-green)!important;color:#fff!important;box-shadow: inset 3px 0 0 var(--accent), 0 2px 10px rgba(16,185,129,.2);} 
    .skin-green .sidebar-menu .treeview-menu>li.active+a{ background:var(--active-grad-green)!important;color:#0b1220!important;box-shadow: inset 3px 0 0 var(--accent);} 
  </style>

  <style>
    :root{
      --sub-hover-from:#38BDF8; --sub-hover-to:#6366F1;
      --sub-active-from:#2563EB; --sub-active-to:#7C3AED; --sub-shadow:rgba(99,102,241,.28);
    }
    .skin-green .sidebar-menu .treeview-menu>li>a:hover{
      background: linear-gradient(90deg,var(--sub-hover-from),var(--sub-hover-to)) !important;
      color:#fff !important; text-shadow:0 1px 0 rgba(0,0,0,.22);
      box-shadow: inset 3px 0 0 var(--accent), 0 2px 10px var(--sub-shadow);
    }
    .skin-green .sidebar-menu .treeview-menu>li.active>a{
      background: linear-gradient(90deg,var(--sub-active-from),var(--sub-active-to)) !important;
      color:#fff !important; box-shadow: inset 3px 0 0 var(--accent), 0 2px 12px var(--sub-shadow);
      text-shadow:0 1px 0 rgba(0,0,0,.22); font-weight:700;
    }
    .skin-green .sidebar-menu .treeview-menu>li .menu-ic i{ color:#e2e8f0; }
    .skin-green .sidebar-menu .treeview-menu>li.active>a .menu-ic i{ color:#ffffff; }
    .skin-green .sidebar-menu .treeview-menu>li.active .label{ background: rgba(255,255,255,.18); color:#fff; }
  </style>

  <style>
    .sidebar-mini.sidebar-collapse .main-sidebar .user-panel{ display:none!important; }
    .sidebar-mini.sidebar-collapse .sidebar .header{ display:none!important; }
    .sidebar-mini.sidebar-collapse .sidebar-menu>li>a{ justify-content:center; padding-left:0; padding-right:0; text-align:center; }
    .sidebar-mini.sidebar-collapse .sidebar-menu>li>a .menu-text,
    .sidebar-mini.sidebar-collapse .sidebar-menu>li>a .pull-right-container,
    .sidebar-mini.sidebar-collapse .sidebar-menu>li>a .fa-angle-left{ display:none!important; }
    .sidebar-mini.sidebar-collapse .sidebar-menu>li>a .menu-ic{ display:inline-flex!important; width:22px; height:22px; line-height:22px; background:transparent; overflow:visible; opacity:1!important; visibility:visible!important; }
    .sidebar-mini.sidebar-collapse .sidebar-menu>li>a .menu-ic i{ display:inline-block!important; font-size:16px; opacity:1!important; color:#cfd8dc; }
    .sidebar-mini.sidebar-collapse .sidebar-menu>li>a .menu-ic i{ display:inline-block!important; font-size:16px; opacity:1!important; color:#cfd8dc; }
    .sidebar-mini.sidebar-collapse .sidebar-menu>li.active > a .menu-ic i{ color:#ffffff; }
    .sidebar-mini.sidebar-collapse .sidebar-menu>li.active > a{ background:var(--active-grad-green)!important; box-shadow: inset 3px 0 0 var(--accent); }
    .sidebar-mini.sidebar-collapse .sidebar-menu>li > a.menu-logout{ background:#e53935!important; }
    .sidebar-mini.sidebar-collapse .main-header .logo{ width:50px; }
  </style>

  <style>
    /* Tampilkan submenu hanya saat menu-open */
    .sidebar-menu .treeview-menu{ display:none; }
    .sidebar-menu li.menu-open > .treeview-menu{ display:block; }
  </style>

  <!-- JS Vendor -->
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="../assets/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
  <script src="../assets/dist/js/adminlte.min.js"></script>
  <script src="../assets/bower_components/select2/dist/js/select2.full.min.js"></script>
  <script src="../assets/bower_components/datatables.net/js/jquery.dataTables.min.js"></script>
  <script src="../assets/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js"></script>

  <!-- Global defaults untuk semua DataTables di aplikasi -->
  <script>
    (function () {
      if (!window.jQuery || !jQuery.fn || !jQuery.fn.dataTable) return;
      jQuery.extend(true, jQuery.fn.dataTable.defaults, {
        pageLength: 25,
        lengthChange: true,
        ordering: true,
        order: [],
        autoWidth: false,
        responsive: false,
        scrollX: false,
        deferRender: true,
        language: {
          emptyTable: "Tidak ada data untuk filter yang dipilih.",
          zeroRecords: "Tidak ada data yang cocok.",
          search: "Cari:",
          lengthMenu: "Tampil _MENU_ data",
          info: "Menampilkan _START_–_END_ dari _TOTAL_ data",
          infoEmpty: "Menampilkan 0 data",
          paginate: { previous: "Sebelumnya", next: "Berikutnya" }
        }
      });
    })();
  </script>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    (function initSelect2Auto(){
      if (!window.jQuery) return;
      var $ = jQuery;
      $(function(){
        if ($.fn.select2) {
          $('.select2').each(function(){
            if (!$(this).data('select2')) $(this).select2();
          });
        }
      });
    })();
  </script>

  <style>
    /* Brand 2 baris */
    .main-header .logo .logo-lg.brand2{ display:flex; align-items:center; gap:8px; padding:0 6px; white-space:normal; }
    .main-header .logo .brand-logo{ width:28px; height:28px; object-fit:cover; border-radius:4px; background:#fff; padding:2px; box-shadow:0 0 0 2px rgba(255,255,255,.15); }
    .main-header .logo .brand-text{ display:block; line-height:1.05; }
    .main-header .logo .brand-title{ display:block; font-weight:800; font-size:13.5px; letter-spacing:.2px; color:#fff; }
    .main-header .logo .brand-subtitle{ display:block; font-size:11px; font-weight:600; color:rgba(255,255,255,.9); margin-top:1px; }
    @media (max-width:480px){ .main-header .logo .brand-logo{ width:24px; height:24px; } .main-header .logo .brand-title{ font-size:12.5px; } .main-header .logo .brand-subtitle{ font-size:10.5px; } }
  </style>

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&family=Nunito:wght@600;700&display=swap" rel="stylesheet">

  <style>
    .main-header .navbar, .main-header .logo{ background:#3f4e61 !important; border-bottom:1px solid #304052 !important; }
    .main-header .logo{ text-align:left !important; padding-left:10px; }
    .main-header .logo .logo-lg.brand2{ display:flex; align-items:center; gap:10px; padding:0; white-space:normal; }
    .main-header .logo .brand-logo{ width:26px; height:26px; object-fit:contain; border-radius:6px; background:transparent; padding:0; box-shadow:none; }
    .main-header .logo .brand-text{ display:block; line-height:1.05; }
    .main-header .logo .brand-title{ display:block; font-weight:700; font-size:14px; letter-spacing:.1px; color:#ffffff; font-family:'Poppins','Nunito',system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; }
    .main-header .logo .brand-subtitle{ display:block; font-size:11.5px; font-weight:700; color:#cbe6ff; margin-top:1px; font-family:'Nunito','Poppins',system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; }
    .main-header .navbar .sidebar-toggle{ color:#fff !important; }
    .main-header .navbar .sidebar-toggle:hover{ background:rgba(255,255,255,.06) !important; }
    @media (max-width:480px){ .main-header .logo{ height:52px !important; padding-left:10px !important; } .main-header .logo .brand-logo{ width:24px; height:24px; } .main-header .logo .brand-title{ font-size:13px; } .main-header .logo .brand-subtitle{ font-size:11px; } }
  </style>

  <style>
    .main-header .logo{ display:flex !important; align-items:center !important; justify-content:flex-start !important; height:56px !important; padding-left:12px !important; line-height:1 !important; overflow:hidden; }
    .main-header .logo .logo-lg{ display:flex; }
    .main-header .logo .logo-lg.brand2{ display:flex; align-items:center; gap:10px; white-space:normal; }
    .main-header .logo .brand-text{ display:block; line-height:1.05; }
    .main-header .logo .logo-mini{ display:none; }
    .main-header .logo .brand-mini{ width:28px; height:28px; object-fit:contain; display:block; border-radius:6px; }
    .sidebar-mini.sidebar-collapse .main-header .logo{ justify-content:center !important; align-items:center !important; padding-left:1 !important; }
    .sidebar-mini.sidebar-collapse .main-header .logo .logo-mini{ display:block !important; }
    .sidebar-mini.sidebar-collapse .main-header .logo .logo-lg{ display:none !important; }
    @media (max-width:480px){ .main-header .logo{ height:52px !important; padding-left:10px !important; } }
  </style>

  <!-- ============== [1] Tambah CSS flyout (baru) ============== -->
  <style>
  .sidebar-flyout{position:fixed;min-width:230px;max-width:340px;background:#0b1220;color:#e5e7eb;border:1px solid rgba(255,255,255,.08);border-radius:10px;box-shadow:0 18px 48px rgba(0,0,0,.35);z-index:1060;overflow:hidden;display:none}
  .sidebar-flyout .flyout-header{display:flex;align-items:center;gap:8px;padding:10px 12px;font-weight:700;color:#e5e7eb;background:linear-gradient(90deg,#0f172a,#1e293b);cursor:default;user-select:none}
  .sidebar-flyout .flyout-header.clickable{cursor:pointer}
  .sidebar-flyout .flyout-header .ico{width:22px;height:22px;display:inline-grid;place-items:center}
  .sidebar-flyout .flyout-header .label{line-height:1.1}
  .sidebar-flyout .treeview-menu{display:block!important;position:static;background:transparent;padding:6px 0;margin:0}
  .sidebar-flyout .treeview-menu>li>a{display:flex;align-items:center;gap:8px;padding:8px 12px 8px 14px;font-weight:600;color:#e2e8f0;transition:padding-left .18s ease, background .25s ease, color .25s ease, box-shadow .25s ease}
  .sidebar-flyout .treeview-menu>li>a .menu-ic{width:16px;height:16px;line-height:16px}
  .sidebar-flyout .treeview-menu>li\a:hover{padding-left:18px;background:linear-gradient(90deg,#0ea5e9,#22d3ee)!important;color:#fff!important;box-shadow: inset 3px 0 0 #22d3ee, 0 2px 10px rgba(2,132,199,.25)}
  .sidebar-mini.sidebar-collapse .sidebar-menu>li>a[title]{ pointer-events:auto; }
  .sidebar-mini.sidebar-collapse .sidebar-menu>li{ position:relative; }
  </style>
  <!-- ============== [1] END CSS flyout (baru) ============== -->

  <!-- ============== [2] Tambahan CSS untuk MODAL WIP (lebih eye-catching) ============== -->
  <style>
    #wipModal .modal-dialog{
      filter: drop-shadow(0 24px 60px rgba(2,6,23,.35));
      margin-top: 80px;
    }
    #wipModal .modal-content{
      border-radius:18px;
      border:1px solid rgba(255,255,255,.08);
      background:
        radial-gradient(1200px 200px at 10% -20%, rgba(34,211,238,.18), transparent 70%),
        radial-gradient(1200px 200px at 110% 120%, rgba(99,102,241,.18), transparent 70%),
        linear-gradient(180deg,#0b1220 0%, #111827 100%);
      color:#e5e7eb;
      position:relative;
      overflow:hidden;
    }
    #wipModal .modal-content::before{
      content:"";
      position:absolute; inset:-2px;
      background: conic-gradient(from 180deg at 50% 50%, #22d3ee, #60a5fa, #a78bfa, #22d3ee);
      filter: blur(26px);
      opacity:.25;
      z-index:0;
      pointer-events:none; /* memastikan tidak menutup klik */
    }
    #wipModal .modal-header{
      position:relative; z-index:1;
      border:0;
      background: linear-gradient(135deg,#06b6d4 0%, #3b82f6 50%, #a855f7 100%);
      color:#fff;
      padding:16px 18px;
    }
    #wipModal .modal-title{
      font-weight:800;
      letter-spacing:.2px;
      text-shadow:0 1px 0 rgba(0,0,0,.25);
    }
    #wipModal .modal-body{
      position:relative; z-index:1;
      background: transparent;
    }
    #wipModal .modal-body p{
      font-size:14px; line-height:1.55; color:#e5e7eb; margin-bottom:10px;
    }
    #wipModal .modal-footer{
      position:relative; z-index:1;
      border-top:1px dashed rgba(148,163,184,.25);
    }
    /* Tombol OK dengan gradien elegan */
    #wipModal .btn-primary{
      border:0; font-weight:800; border-radius:999px;
      background: linear-gradient(135deg,#22d3ee,#3b82f6);
      box-shadow:0 10px 24px rgba(34,211,238,.25);
      transition: transform .12s ease, box-shadow .12s ease, filter .12s ease;
    }
    #wipModal .btn-primary:hover{ transform:translateY(-1px); filter:saturate(1.05); box-shadow:0 14px 30px rgba(59,130,246,.35);}
    #wipModal .btn-primary:active{ transform:translateY(0); filter:saturate(1); }

    /* Divider glow tipis di atas footer */
    #wipModal .modal-footer::before{
      content:""; position:absolute; left:0; right:0; top:-1px; height:1px;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,.35), transparent);
      opacity:.5;
    }
  </style>
  <!-- ============== [2] END Tambahan CSS MODAL WIP ============== -->

  <!-- ============== [NEW] CSS Modal e-Rapor (khusus) ============== -->
  <style>
    #eraporModal .modal-dialog{
      filter: drop-shadow(0 24px 60px rgba(2,6,23,.35));
      margin-top:80px;
      max-width:620px;
    }
    #eraporModal .modal-content{
      border-radius:18px;
      border:1px solid rgba(255,255,255,.08);
      background:
        radial-gradient(1200px 200px at 10% -20%, rgba(34,197,94,.18), transparent 70%),
        radial-gradient(1200px 200px at 110% 120%, rgba(14,165,233,.18), transparent 70%),
        linear-gradient(180deg,#0b1220 0%, #0f172a 100%);
      color:#e5e7eb;
      position:relative; overflow:hidden;
    }
    #eraporModal .modal-content::before{
      content:""; position:absolute; inset:-2px;
      background: conic-gradient(from 180deg at 50% 50%, #22c55e, #06b6d4, #60a5fa, #22c55e);
      filter: blur(28px); opacity:.22; z-index:0;
      pointer-events:none; /* memastikan tidak menutup klik */
    }
    #eraporModal .modal-header{
      position:relative; z-index:1; border:0;
      background: linear-gradient(135deg,#22c55e 0%, #06b6d4 50%, #2563eb 100%);
      color:#fff; padding:16px 18px;
      display:flex; align-items:center; gap:10px;
    }
    #eraporModal .modal-title{ font-weight:800; letter-spacing:.2px; text-shadow:0 1px 0 rgba(0,0,0,.25); }
    #eraporModal .modal-title .ico{
      width:28px;height:28px; display:inline-grid; place-items:center;
      background:rgba(255,255,255,.92); color:#0f766e; border-radius:8px; font-size:16px;
      box-shadow:0 4px 14px rgba(0,0,0,.15);
    }
    #eraporModal .modal-body{ position:relative; z-index:1; }
    #eraporModal .lead{
      font-size:14px; line-height:1.55; color:#e5e7eb; margin-bottom:10px;
    }
    #eraporModal .point{ display:flex; gap:10px; margin:8px 0; }
    #eraporModal .point .ic{
      width:22px; height:22px; display:inline-grid; place-items:center;
      color:#22c55e; background:rgba(34,197,94,.12); border-radius:6px;
    }
    #eraporModal .point p{ margin:0; font-size:13.5px; color:#e2e8f0; }
    #eraporModal .hint{ font-size:12px; color:#9fb3c8; margin-top:8px; }
    #eraporModal .modal-footer{ border-top:1px dashed rgba(148,163,184,.25); }
    #eraporModal .btn-grad{
      border:0; font-weight:800; border-radius:999px; padding:8px 16px;
      background: linear-gradient(135deg,#22c55e,#06b6d4);
      box-shadow:0 10px 24px rgba(6,182,212,.25);
    }
    #eraporModal .btn-ghost{
      border:1px solid rgba(148,163,184,.35);
      color:#e5e7eb; background:transparent; font-weight:700; border-radius:999px; padding:8px 14px;
    }
    #eraporModal .btn-ghost[disabled]{ opacity:.6; cursor:not-allowed; }
  </style>
  <!-- ============== [/NEW] CSS Modal e-Rapor ============== -->

</head>

<body class="hold-transition skin-green sidebar-mini theme-slate">
<div class="wrapper" role="presentation">
  <!-- Decoy autofill -->
  <form id="af-decoy" autocomplete="on" style="position:absolute; left:-9999px; top:-9999px; opacity:0; height:0; width:0; overflow:hidden;">
    <input type="text" name="username" autocomplete="username">
    <input type="password" name="current-password" autocomplete="current-password">
  </form>

  <!-- Header -->
  <header class="main-header" role="banner">
<?php echo render_theme_brand($THEEME_BRAND ?? $THEME_BRAND); ?>
    
    <nav class="navbar navbar-static-top" role="navigation" aria-label="Bar navigasi">
      <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button" aria-label="Buka menu" aria-expanded="false" aria-controls="adminSidebar">
        <span class="sr-only">Toggle navigation</span><span class="icon-bar"></span><span class="icon-bar"></span><span class="icon-bar"></span>
      </a>
      <div class="navbar-custom-menu">
        <ul class="nav navbar-nav" role="menubar" aria-label="Menu pengguna">
          <li class="dropdown user user-menu" role="none">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="menuitem" aria-haspopup="true" aria-expanded="false" aria-label="Profil">
              <?php
              $id_user = $_SESSION['id'] ?? $user_id;
              $profil = mysqli_query($koneksi,"SELECT * FROM user WHERE user_id='".(int)$id_user."'");
              $profil = $profil ? mysqli_fetch_assoc($profil) : null;
              if($profil && !empty($profil['user_foto'])){ ?>
                <img src="../gambar/user/<?php echo $profil['user_foto'] ?>" class="user-image" alt="Foto pengguna">
              <?php }else{ ?>
                <img src="../gambar/sistem/user.png" class="user-image" alt="Foto pengguna">
              <?php } ?>
              <span class="hidden-xs">
                <?php
                  $roles = $_SESSION['roles'] ?? [];
                  echo $roles ? htmlspecialchars(implode('/', $roles)) : htmlspecialchars($_SESSION['level'] ?? 'User');
                ?>
              </span>
            </a>
          </li>
<li class="nav-logout" role="none">
  <a href="logout.php"
     class="nav-logout-btn"
     id="btnLogout"
     data-logout
     role="menuitem"
     title="Keluar dari aplikasi"
     aria-label="Keluar">
    <span class="ic-wrap" aria-hidden="true">
      <i class="fa-solid fa-power-off"></i>
      <span class="ic-wave"></span>
    </span>
    <span class="text-label">Keluar</span>
  </a>
</li>
        </ul>
      </div>
    </nav>
  </header>

  <!-- Sidebar -->
  <aside class="main-sidebar" id="adminSidebar" role="complementary" aria-label="Menu samping">
    <section class="sidebar" role="navigation" aria-label="Navigasi utama">
      <div class="user-panel">
        <div class="pull-left image">
          <?php
          $id_user = $_SESSION['id'] ?? $user_id;
          $profil2 = isset($profil) ? $profil : null;
          if(!$profil2){
            $q = mysqli_query($koneksi,"SELECT * FROM user WHERE user_id='".(int)$id_user."'");
            $profil2 = $q ? mysqli_fetch_assoc($q) : null;
          }
          if($profil2 && !empty($profil2['user_foto'])){ ?>
            <img src="../gambar/user/<?php echo $profil2['user_foto'] ?>" class="img-circle" style="height:45px; width: 45px;" alt="Foto pengguna">
          <?php }else{ ?>
            <img src="../gambar/sistem/user.png" class="img-circle" style="height:45px; width: 45px;" alt="Foto pengguna">
          <?php } ?>
        </div>
        <div class="pull-left info">
          <p><?php echo htmlspecialchars($profil2['user_nama'] ?? 'Pengguna', ENT_QUOTES, 'UTF-8'); ?></p>
          <a href="#"><i class="fa-solid fa-circle text-success" aria-hidden="true"></i> <span class="sr-only">Status:</span> Online</a>
        </div>
      </div>

      <ul class="sidebar-menu" data-widget="tree" role="tree" aria-label="Daftar menu">

        <?php if (_is_only_sekretaris() || _is_only_piket()): ?>
          <!-- ============ MENU KHUSUS SEKRETARIS ============ -->
          <li role="treeitem">
            <!-- REVISI: gunakan URL absolut/protocol-relative -->
            <a href="<?php echo $__ABSENSI_HARIAN_URL; ?>" title="Absensi Harian" aria-label="Absensi Harian">
              <span class="menu-ic"><i class="fa-solid fa-user-check"></i></span>
              <span class="menu-text">Absensi Harian</span>
            </a>
          </li>
          <!-- (REVISI) Sinkron Absensi DISEMBUNYIKAN untuk Sekretaris -->
          <li role="treeitem">
            <a href="rekap_kelas.php" class="menu-monitoring" title="Monitoring Absensi" aria-label="Monitoring Absensi">
              <span class="menu-ic"><i class="fa-solid fa-magnifying-glass-chart"></i></span>
              <span class="menu-text">Monitoring Absensi</span>
              <?php if($monitor_new_count>0): ?>
                <span class="pull-right-container">
                  <small class="label bg-yellow badge-count"><?php echo $monitor_new_count; ?></small>
                </span>
              <?php endif; ?>
            </a>
          </li>

          <!-- ========== /MENU SEKRETARIS ========== -->

        <?php else: ?>
          <!-- ============ MENU LENGKAP (selain sekretaris-only) ============ -->

          <li class="header" role="presentation">UMUM</li>
          <li role="treeitem">
            <a href="index.php" title="Dashboard" aria-label="Dashboard">
              <span class="menu-ic"><i class="fa-solid fa-house"></i></span>
              <span class="menu-text">Dashboard</span>
            </a>
          </li>

          <!-- (1) Sekolah (khusus administrator/superadmin) -->
          <?php if (user_has_any_role(['administrator','superadmin'])): ?>
          <li role="treeitem">
            <a href='sekolah.php' title="Sekolah" aria-label="Sekolah">
              <span class="menu-ic"><i class="fa-solid fa-school"></i></span>
              <span class="menu-text">Sekolah</span>
            </a>
          </li>
          <?php endif; ?>

          <?php if (_is_admin()): ?>
          <li class="header" role="presentation">MASTER DATA</li>
          <li class="treeview" role="treeitem" aria-expanded="false">
            <a href="#" title="Master Data" aria-label="Master Data" aria-controls="submenu-masterdata">
              <span class="menu-ic"><i class="fa-solid fa-database"></i></span>
              <span class="menu-text">Master Data</span>
              <span class="pull-right-container"><i class="fa-solid fa-angle-left pull-right" aria-hidden="true"></i></span>
            </a>
            <ul class="treeview-menu" id="submenu-masterdata" role="group" aria-hidden="true">
              <li role="treeitem">
                <a href="siswa.php" title="Siswa" aria-label="Siswa">
                  <span class="menu-ic"><i class="fa-solid fa-user-graduate"></i></span> <span class="menu-text">Siswa</span>
                  <span class="pull-right-container"><small class="label pull-right bg-aqua"><?php echo $jumlah_siswa; ?></small></span>
                </a>
              </li>
              <li role="treeitem">
                <a href="kelas.php" title="Manajemen Kelas" aria-label="Manajemen Kelas">
                  <span class="menu-ic"><i class="fa-solid fa-chalkboard"></i></span> <span class="menu-text">Manajemen Kelas</span>
                  <span class="pull-right-container"><small class="label pull-right bg-aqua"><?php echo $jumlah_kelas; ?></small></span>
                </a>
              </li>
              <li role="treeitem">
                <a href="jurusan.php" title="Tingkat Kelas / Jurusan" aria-label="Tingkat Kelas atau Jurusan">
                  <span class="menu-ic"><i class="fa-solid fa-object-group"></i></span> <span class="menu-text">Tingkat Kelas / Jurusan</span>
                  <span class="pull-right-container"><small class="label pull-right bg-aqua"><?php echo $jumlah_rombel; ?></small></span>
                </a>
              </li>
              <li role="treeitem">
                <a href="mapel.php" title="Mata Pelajaran" aria-label="Mata Pelajaran">
                  <span class="menu-ic"><i class="fa-solid fa-book-open"></i></span> <span class="menu-text">Mata Pelajaran</span>
                  <span class="pull-right-container"><small class="label pull-right bg-aqua"><?php echo $jumlah_mapel; ?></small></span>
                </a>
              </li>
              <li role="treeitem">
                <a href="pengampu_mapel.php" title="Penugasan Guru Mapel" aria-label="Penugasan Guru Mapel">
                  <span class="menu-ic"><i class="fa-solid fa-chalkboard-user"></i></span> <span class="menu-text">Penugasan Guru Mapel</span>
                  <span class="pull-right-container"><small class="label pull-right bg-aqua"><?php echo $jumlah_pengampu_mapel; ?></small></span>
                </a>
              </li>
              <li role="treeitem">
                <a href="ta.php" title="Tahun Ajaran" aria-label="Tahun Ajaran">
                  <span class="menu-ic"><i class="fa-solid fa-calendar-days"></i></span> <span class="menu-text">Tahun Ajaran</span>
                  <span class="pull-right-container"><small class="label pull-right bg-aqua"><?php echo $jumlah_tahun_ajaran; ?></small></span>
                </a>
              </li>
              <!-- Kalender Akademik -->
              <li role="treeitem">
                <a href="kalender_akademik.php" title="Kalender Akademik" aria-label="Kalender Akademik">
                  <span class="menu-ic"><i class="fa-solid fa-calendar-check"></i></span>
                  <span class="menu-text">Kalender Akademik</span>
                  <span class="pull-right-container">
                    <!-- REVISI: badge angka total hari non-efektif + warna biru -->
                    <small class="label pull-right bg-aqua" title="Total hari non-efektif">
                      <?php echo (int)$jumlah_hari_non_efektif; ?>
                    </small>
                  </span>
                </a>
              </li>
              <li role="treeitem">
                <a href="manajemen_pengguna.php" title="Manajemen Pengguna" aria-label="Manajemen Pengguna">
                  <span class="menu-ic"><i class="fa-solid fa-user-gear"></i></span> <span class="menu-text">Manajemen Pengguna</span>
                  <span class="pull-right-container"><small class="label pull-right bg-aqua"><?php echo $jumlah_admin; ?></small></span>
                </a>
              </li>
              <li role="treeitem">
                <a href="role_permission.php" title="Role &amp; Hak Akses" aria-label="Role dan Hak Akses">
                  <span class="menu-ic"><i class="fa-solid fa-user-shield"></i></span> <span class="menu-text">Role &amp; Hak Akses</span>
                </a>
              </li>
            </ul>
          </li>
          <?php endif; ?>

          <li class="header" role="presentation">KATEGORI POIN</li>
          <li class="treeview" role="treeitem" aria-expanded="false">
            <a href="#" title="Kategori Poin" aria-label="Kategori Poin" aria-controls="submenu-kategori">
              <span class="menu-ic"><i class="fa-solid fa-tags"></i></span>
              <span class="menu-text">Kategori Poin</span>
              <span class="pull-right-container"><i class="fa-solid fa-angle-left pull-right" aria-hidden="true"></i></span>
            </a>
            <ul class="treeview-menu" id="submenu-kategori" role="group" aria-hidden="true">
              <li role="treeitem">
                <a href="prestasi.php" title="Jenis Prestasi" aria-label="Jenis Prestasi">
                  <span class="menu-ic"><i class="fa-solid fa-medal"></i></span> <span class="menu-text">Jenis Prestasi</span>
                  <span class="pull-right-container"><small class="label pull-right bg-green"><?php echo $jumlah_jenis_prestasi; ?></small></span>
                </a>
              </li>
              <li role="treeitem">
                <a href="pelanggaran.php" title="Jenis Pelanggaran" aria-label="Jenis Pelanggaran">
                  <span class="menu-ic"><i class="fa-solid fa-gavel"></i></span> <span class="menu-text">Jenis Pelanggaran</span>
                  <span class="pull-right-container"><small class="label pull-right bg-red"><?php echo $jumlah_jenis_pelanggaran; ?></small></span>
                </a>
              </li>
            </ul>
          </li>

          <li class="header" role="presentation">KELOLA POIN</li>
          <li role="treeitem">
            <a href="input_prestasi.php" title="Input Prestasi" aria-label="Input Prestasi">
              <span class="menu-ic"><i class="fa-solid fa-circle-plus"></i></span>
              <span class="menu-text">Input Prestasi</span>
            </a>
          </li>
          <li role="treeitem">
            <a href="input_pelanggaran.php" title="Input Pelanggaran" aria-label="Input Pelanggaran">
              <span class="menu-ic"><i class="fa-solid fa-circle-plus"></i></span>
              <span class="menu-text">Input Pelanggaran</span>
            </a>
          </li>
          <li role="treeitem">
            <a href="poin_kolektif.php" title="Input Kolektif" aria-label="Input Kolektif">
              <span class="menu-ic"><i class="fa-solid fa-people-group"></i></span>
              <span class="menu-text">Input Kolektif</span>
            </a>
          </li>
          <li role="treeitem">
            <a href="laporan.php" class="menu-laporan-baru" title="Laporan Poin Siswa" aria-label="Laporan Poin Siswa">
              <span class="menu-ic"><i class="fa-solid fa-file-invoice"></i></span>
              <span class="menu-text">Laporan Poin Siswa</span>
              <?php if($laporan_new_count>0): ?>
                <span class="pull-right-container">
                  <small class="label bg-yellow badge-count"><?php echo $laporan_new_count; ?></small>
                </span>
              <?php endif; ?>
            </a>
          </li>

          <!-- ===================== ABSENSI ===================== -->
          <?php
            $admin_like = _is_admin();                 // admin/superadmin/administrator/admin
            $show_wali_header = ($is_wali_kelas && !$admin_like); // wali kelas non-admin
          ?>
          <li class="header" role="presentation">ABSENSI</li>

          <?php if ($admin_like || _is_guru()): ?>
          <li role="treeitem">
            <a href="absensi_mapel.php" title="Absensi Mapel (Per JP)" aria-label="Absensi Mapel per jam pelajaran">
              <span class="menu-ic"><i class="fa-solid fa-chalkboard-user"></i></span>
              <span class="menu-text">Absensi Mapel (Per JP)</span>
            </a>
          </li>
          <?php endif; ?>

          <?php if ($admin_like): ?>
            <!-- Admin-like: semua menu wali disatukan di ABSENSI, tanpa judul WALI KELAS -->
            <li role="treeitem">
              <!-- REVISI: gunakan URL absolut/protocol-relative -->
              <a href="<?php echo $__ABSENSI_HARIAN_URL; ?>" title="Absensi Harian" aria-label="Absensi Harian">
                <span class="menu-ic"><i class="fa-solid fa-user-check"></i></span>
                <span class="menu-text">Absensi Harian</span>
              </a>
            </li>
            <li role="treeitem">
              <a href="data_absensi.php" title="Sinkron Absensi" aria-label="Sinkron Absensi">
                <span class="menu-ic"><i class="fa-solid fa-database"></i></span>
                <span class="menu-text">Sinkron Absensi</span>
              </a>
            </li>
            <li role="treeitem">
              <a href="rekap_kelas.php" class="menu-monitoring" title="Monitoring Absensi" aria-label="Monitoring Absensi">
                <span class="menu-ic"><i class="fa-solid fa-magnifying-glass-chart"></i></span>
                <span class="menu-text">Monitoring Absensi</span>
                <?php if($monitor_new_count>0): ?>
                  <span class="pull-right-container">
                    <small class="label bg-yellow badge-count"><?php echo $monitor_new_count; ?></small>
                  </span>
                <?php endif; ?>
              </a>
            </li>
            <li role="treeitem">
              <a href="laporan_absensi.php" title="Laporan Absensi" aria-label="Laporan Absensi">
                <span class="menu-ic"><i class="fa-solid fa-clipboard-list"></i></span>
                <span class="menu-text">Laporan Absensi</span>
              </a>
            </li>
          <?php else: ?>
            <?php if (!$is_wali_kelas): ?>
            <li role="treeitem">
              <a href="laporan_absensi.php" title="Laporan Absensi" aria-label="Laporan Absensi">
                <span class="menu-ic"><i class="fa-solid fa-clipboard-list"></i></span>
                <span class="menu-text">Laporan Absensi</span>
              </a>
            </li>
            <?php endif; ?>
          <?php endif; ?>

          <?php if ($show_wali_header): ?>
            <li class="header" role="presentation">WALI KELAS</li>
            <li role="treeitem">
              <a href="<?php echo $__ABSENSI_HARIAN_URL; ?>" title="Absensi Harian (Wali Kelas)" aria-label="Absensi Harian Wali Kelas">
                <span class="menu-ic"><i class="fa-solid fa-user-check"></i></span>
                <span class="menu-text">Absensi Harian</span>
              </a>
            </li>
            <li role="treeitem">
              <a href="data_absensi.php" title="Sinkron Absensi (Wali Kelas)" aria-label="Sinkron Absensi Wali Kelas">
                <span class="menu-ic"><i class="fa-solid fa-database"></i></span>
                <span class="menu-text">Sinkron Absensi</span>
              </a>
            </li>
            <li role="treeitem">
              <a href="rekap_kelas.php" class="menu-monitoring" title="Monitoring Absensi (Wali Kelas)" aria-label="Monitoring Absensi Wali Kelas">
                <span class="menu-ic"><i class="fa-solid fa-magnifying-glass-chart"></i></span>
                <span class="menu-text">Monitoring Absensi</span>
                <?php if($monitor_new_count>0): ?>
                  <span class="pull-right-container">
                    <small class="label bg-yellow badge-count"><?php echo $monitor_new_count; ?></small>
                  </span>
                <?php endif; ?>
              </a>
            </li>
            <li role="treeitem">
              <a href="laporan_absensi.php" title="Laporan Absensi (Wali Kelas)" aria-label="Laporan Absensi Wali Kelas">
                <span class="menu-ic"><i class="fa-solid fa-clipboard-list"></i></span>
                <span class="menu-text">Laporan Absensi</span>
              </a>
            </li>
          <?php endif; ?>
          <!-- =================== /ABSENSI & WALI =================== -->

          <?php if ($admin_like || _is_guru()): ?>

<?php
// ====== REVISI KHUSUS: Struktur MENU PENILAIAN BARU ======
// Halaman aktif (untuk membuka submenu otomatis)
$curr = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Aktif untuk item yang sudah ada halamannya
$isTP        = ($curr === 'tujuan_pembelajaran.php');  // 🎯
$isSTS       = ($curr === 'nilai_pts.php');            // Input Nilai STS (PTS)
/* [BARU] aktifkan auto-open jika berada di Deskripsi Rapor */
$isDeskripsi = ($curr === 'deskripsi_rapor.php');      // Deskripsi Rapor (baru)

// Submenu yang perlu auto-open
// REVISI: Tujuan Pembelajaran dipindah ke top-level, jadi tidak memicu auto-open submenu STS
$openRaporSTS = ($isSTS || $isDeskripsi);
?>

<li class="header" role="presentation">PENILAIAN</li>

<!-- [BARU - DIPINDAH] 0) Tujuan Pembelajaran — ditempatkan di atas Input Nilai Harian -->
<li role="treeitem" class="<?= $isTP ? 'active' : '' ?>">
  <a href="tujuan_pembelajaran.php" title="Tujuan Pembelajaran" aria-label="Tujuan Pembelajaran">
    <span class="menu-ic"><i class="fa-solid fa-bullseye"></i></span>
    <span class="menu-text">Tujuan Pembelajaran</span>
  </a>
</li>

<!-- 1) Input Nilai Harian -->
<li role="treeitem">
  <a href="nilai_harian.php" title="Input Nilai Harian" aria-label="Input Nilai Harian">
    <span class="menu-ic"><i class="fa-solid fa-pen-to-square"></i></span>
    <span class="menu-text">Input Nilai Harian</span>
  </a>
</li>

<!-- 2) Rapor STS -->
<li class="treeview <?= $openRaporSTS ? 'active menu-open' : '' ?>" role="treeitem"
    aria-expanded="<?= $openRaporSTS ? 'true' : 'false' ?>">
  <a href="#" title="Rapor STS" aria-label="Rapor STS" aria-controls="submenu-rapor-sts">
    <span class="menu-ic"><i class="fa-solid fa-file-lines"></i></span>
    <span class="menu-text">e-Rapor STS</span>
    <span class="pull-right-container"><i class="fa-solid fa-angle-left pull-right" aria-hidden="true"></i></span>
  </a>
  <ul class="treeview-menu" id="submenu-rapor-sts" role="group"
      aria-hidden="<?= $openRaporSTS ? 'false' : 'true' ?>"
      style="<?= $openRaporSTS ? 'display:block' : '' ?>">
    <!-- REVISI: Item 'Tujuan Pembelajaran' dipindahkan ke top-level, jadi DIHAPUS dari submenu -->
    <li class="<?= $isSTS ? 'active' : '' ?>" role="treeitem">
      <a href="nilai_pts.php" title="Input Nilai STS (PTS)" aria-label="Input Nilai STS">
        <span class="menu-ic"><i class="fa-solid fa-list-check"></i></span>
        <span class="menu-text">Input Nilai STS</span>
      </a>
    </li>
    <li role="treeitem" class="<?= $isDeskripsi ? 'active' : '' ?>">
      <a href="nilai_rapor_tersimpan.php" title="Nilai Tersimpan" aria-label="Nilai Tersimpan">
        <span class="menu-ic"><i class="fa-solid fa-file-pen"></i></span>
        <span class="menu-text">Nilai Tersimpan</span>
      </a>
    </li>
    <li role="treeitem">
      <a href="status_penilaian.php" title="Status Penilaian STS" aria-label="Status Penilaian STS">
        <span class="menu-ic"><i class="fa-solid fa-clipboard-check"></i></span>
        <span class="menu-text">Status Penilaian</span>
      </a>
    </li>
    <li role="treeitem">
      <a href="leger_rapor_sts.php" title="Leger Rapor STS" aria-label="Leger Rapor STS">
        <span class="menu-ic"><i class="fa-solid fa-receipt"></i></span>
        <span class="menu-text">Leger Rapor STS</span>
      </a>
    </li>
    <!-- REVISI #2: Cetak Rapor STS diarahkan ke rapor_sts_cetak.php (aktif, bukan WIP) -->
    <li role="treeitem">
      <a href="rapor_sts_cetak.php" title="Cetak Rapor STS" aria-label="Cetak Rapor STS">
        <span class="menu-ic"><i class="fa-solid fa-print"></i></span>
        <span class="menu-text">Cetak Rapor STS</span>
      </a>
    </li>
  </ul>
</li>

<!-- 6) Integrasi ke e-Rapor 2026 — tetap WIP namun pakai modal khusus e-Rapor -->
<li role="treeitem">
  <a href="#" class="erapor-link" title="Integrasi ke e-Rapor 2026" data-toggle="tooltip" aria-label="Integrasi ke e-Rapor 2026 (Pra-rilis)">
    <span class="menu-ic"><i class="fa-solid fa-plug"></i></span>
    <span class="menu-text">Integrasi ke e-Rapor</span>
  </a>
</li>

            <!-- =================== [REMOVED dari Penilaian] Ujian Online =================== -->
            <!-- =================== [NEW SECTION] UJIAN & CBT =================== -->
            <li class="header" role="presentation">UJIAN &amp; CBT</li>

            <li role="treeitem">
              <a href="ujian_gform.php" title="Ujian Exam GForm" aria-label="Ujian Exam GForm">
                <span class="menu-ic"><i class="fa-brands fa-google"></i></span>
                <span class="menu-text">Ujian Exam GForm</span>
              </a>
            </li>

            <?php if ((function_exists('_is_admin') && _is_admin()) || (function_exists('_is_guru') && _is_guru())): ?>
            <li role="treeitem">
              <a href="etugas.php" title="Kelola Tugas" aria-label="Kelola Tugas">
                <span class="menu-ic"><i class="fa-solid fa-file-lines"></i></span>
                <span class="menu-text">Kelola Tugas</span>
              </a>
            </li>
            <li role="treeitem">
              <a href="etugas_pengumpulan.php" title="Review Pengumpulan" aria-label="Review Pengumpulan">
                <span class="menu-ic"><i class="fa-solid fa-clipboard-check"></i></span>
                <span class="menu-text">Review Pengumpulan</span>
              </a>
            </li>
            <li role="treeitem">
              <a href="etugas_rekap.php" title="Rekap Tugas" aria-label="Rekap Tugas">
                <span class="menu-ic"><i class="fa-solid fa-table"></i></span>
                <span class="menu-text">Rekap Tugas</span>
              </a>
            </li>
            <?php endif; ?>

            <li role="treeitem">
              <a href="https://cbt.smpn1gunungtanjung.sch.id/"  title="CBT Nesagun" data-toggle="tooltip" aria-label="CBT Pro">
                <span class="menu-ic"><i class="fa-solid fa-layer-group"></i></span>
                <span class="menu-text">CBT NESAGUN</span>
                <span class="pull-right-container"><small class="label label-new">New</small></span>
              </a>
            </li>

            <!-- ======= REVISI: HAPUS menu 'Sinkronkan ke Nilai' (sesuai permintaan) ======= -->
            <!-- (Item ini dihapus) -->

          <?php endif; ?>

          <li class="header" role="presentation">PENGATURAN</li>
          <li role="treeitem">
            <a href="gantipassword.php" title="Ganti Password" aria-label="Ganti Password">
              <span class="menu-ic"><i class="fa-solid fa-key"></i></span>
              <span class="menu-text">Ganti Password</span>
            </a>
          </li>

        <?php endif; ?>

      </ul>
    </section>
  </aside>

  <!-- MODAL: WIP -->
  <div class="modal fade" id="wipModal" tabindex="-1" role="dialog" aria-labelledby="wipLabel">
    <div class="modal-dialog modal-sm" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">&times;</button>
          <h4 class="modal-title" id="wipLabel">Sedang dalam pengembangan</h4>
        </div>
        <div class="modal-body">
          <p>Fitur <b id="wipTarget"></b> saat ini masih dalam proses pengembangan. Silakan kembali lagi nanti.</p>
          <div id="wipDesc" class="text-muted" style="font-size:12px; margin-top:8px;">
            <div style="color:#fff!important; font-weight:800;">Rencana Fitur Unggulan Platform Ujian Kami:</div>
            <ul style="padding-left:16px; margin-top:6px; color:#fff">
              <li><b>Bank Soal Lengkap:</b> PG, esai, upload file/audio/video; bobot & pemetaan kompetensi.</li>
              <li><b>Penjadwalan Fleksibel:</b> Atur durasi, jadwal, token, dan kebijakan retake.</li>
              <li><b>Ujian Adil & Anti-Bocoran:</b> Acak soal/jawaban; paket unik per peserta.</li>
              <li><b>Anti Gagal & Sinyal:</b> Autosave jawaban; lanjut dari titik terakhir.</li>
              <li><b>Keamanan Terjamin:</b> Kunci layar, deteksi pindah app, watermark, audit trail.</li>
              <li><b>Live Proctoring:</b> Pantau real-time, catat pelanggaran, intervensi dari dasbor.</li>
              <li><b>Penilaian Cerdas:</b> Skor parsial, minus, rubrik multi-penilai.</li>
              <li><b>Analitik & Laporan:</b> Analisis butir, rekomendasi remedial, ekspor PDF/Excel & integrasi.</li>
            </ul>
            <br>
            <style>
              :root{--cbt-blue-1:#00c6ff;--cbt-blue-2:#0072ff;--cbt-shadow:0 12px 28px rgba(0,114,255,.35),0 2px 6px rgba(0,0,0,.25)}
              .btn-cbt-pro{--pad-y:12px;--pad-x:18px;display:inline-flex;align-items:center;gap:.6rem;padding:var(--pad-y) var(--pad-x);border-radius:16px;font-weight:700;letter-spacing:.2px;text-decoration:none;color:#001b2a;background:linear-gradient(135deg,var(--cbt-blue-2),var(--cbt-blue-1));box-shadow:var(--cbt-shadow);position:relative;overflow:hidden;isolation:isolate;transition:transform .12s ease,box-shadow .12s ease,filter .12s ease}
              .btn-cbt-pro .ico{width:22px;height:22px;display:inline-grid;place-items:center;background:rgba(255,255,255,.85);color:#0b5ed7;border-radius:10px;font-size:13px}
              .btn-cbt-pro::after{content:"";position:absolute;inset:0;border-radius:16px;box-shadow:inset 0 0 0 1px rgba(255,255,255,.35),inset 0 -8px 18px rgba(0,0,0,.15);pointer-events:none}
              .btn-cbt-pro::before{content:"";position:absolute;inset:-40%;transform:translateX(-120%);background:linear-gradient(110deg,transparent 0%,rgba(255,255,255,.0) 35%,rgba(255,255,255,.45) 48%,rgba(255,255,255,.0) 62%,transparent 100%);animation:shimmer-move 2.2s infinite;mix-blend-mode:screen}
              @keyframes shimmer-move{0%{transform:translateX(-120%) rotate(.001deg)}100%{transform:translateX(120%) rotate(.001deg)}}
              .btn-cbt-pro:hover{transform:translateY(-1px);filter:saturate(1.1)}
              .btn-cbt-pro:active{transform:translateY(0);filter:saturate(1)}
              .btn-cbt-pro:focus{outline:none;box-shadow:0 0 0 3px rgba(0,114,255,.35),var(--cbt-shadow)}
              .btn-cbt-pro .badge-mini{background:rgba(255,255,255,.9);color:#083b74;border-radius:999px;font-size:.7rem;padding:.15rem .5rem;margin-left:.2rem}
            </style>

            <center><a class="btn-cbt-pro" href="cbt_pro_demo.php" target="_blank"> 
              INUSI CBT Pro
              <span class="badge-mini">Preview</span>
            </a></center>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary btn-sm" data-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>

  <!-- [NEW] MODAL: Integrasi e-Rapor 2026 (khusus) -->
  <div class="modal fade" id="eraporModal" tabindex="-1" role="dialog" aria-labelledby="eraporLabel">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">&times;</button>
          <div class="ico"><i class="fa-solid fa-file-excel"></i></div>
          <h4 class="modal-title" id="eraporLabel">Integrasi ke e-Rapor 2026 (Pra-Rilis)</h4>
        </div>
        <div class="modal-body">
          <p class="lead">
            Fitur ini menyiapkan <b>ekspor berkas Excel (.xlsx)</b> berformat kompatibel (draft) untuk <b>e-Rapor Kemendikdasmen 2026</b>. 
            Tujuannya agar saat e-Rapor resmi dirilis, <b>guru mapel cukup impor nilai</b> tanpa input ulang.
          </p>

          <div class="point">
            <div class="ic"><i class="fa-solid fa-plug"></i></div>
            <p><b>Ekspor Nilai Rapor per Mapel & Kelas</b> dengan struktur kolom yang disiapkan untuk template impor e-Rapor 2026.</p>
          </div>
          <div class="point">
            <div class="ic"><i class="fa-solid fa-bullseye"></i></div>
            <p><b>Termasuk Tujuan Pembelajaran (TP)</b>: kode/nama TP, KKM, bobot, hingga catatan remedial tersinkron dengan data di EPS.</p>
          </div>
          <div class="point">
            <div class="ic"><i class="fa-solid fa-sliders"></i></div>
            <p><b>Filter Fleksibel</b>: pilih semester, kelas/rombongan belajar, mapel, dan opsi hanya nilai terkini.</p>
          </div>
          <div class="point">
            <div class="ic"><i class="fa-solid fa-road"></i></div>
            <p><b>Roadmap</b>: validasi struktur otomatis, template baku e-Rapor 2026, dan opsi kirim via API bila endpoint resmi tersedia.</p>
          </div>

          <p class="hint">
            Catatan: format bisa menyesuaikan kebijakan terbaru Kemendikdasmen. Sementara, gunakan berkas .xlsx 
            hasil ekspor untuk mempercepat proses pengisian saat e-Rapor 2026 dirilis.
          </p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost btn-sm" disabled title="Segera tersedia">
            <i class="fa-solid fa-file-arrow-down"></i> Lihat Template XLS
          </button>
          <button type="button" class="btn btn-ghost btn-sm" disabled title="Segera tersedia">
            <i class="fa-solid fa-arrow-right-to-bracket"></i> Panduan Impor
          </button>
          <button type="button" class="btn btn-grad btn-sm" data-dismiss="modal">
            Oke, saya paham
          </button>
        </div>
      </div>
    </div>
  </div>
  <!-- [/NEW] MODAL e-Rapor -->

<script>
(function(){
  var btn = document.querySelector('[data-logout]');
  if(!btn) return;

  btn.addEventListener('click', function(e){
    e.preventDefault();
    confirmLogout(this.getAttribute('href') || 'logout.php');
  });

  function confirmLogout(url){
    function proceed(){
      if (window.Swal){
        Swal.fire({
          title:'Mengakhiri sesi…',
          didOpen:()=>Swal.showLoading(),
          allowOutsideClick:false,
          allowEscapeKey:false,
          showConfirmButton:false
        });
      }
      setTimeout(function(){ window.location.href = url; }, 700);
    }

    if (window.Swal){
      Swal.fire({
        title:'Keluar dari E-Poin?',
        html:'Sesi Anda akan ditutup.',
        icon:'question',
        showCancelButton:true,
        confirmButtonText:'Keluar',
        cancelButtonText:'Batal',
        reverseButtons:true,
        customClass:{ popup:'swal2-brand' },
        backdrop:'rgba(17,24,39,.45)'
      }).then(function(res){ if(res.isConfirmed) proceed(); });
      return;
    }

    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
    s.onload = function(){ confirmLogout(url); };
    document.head.appendChild(s);
  }
})();
</script>

  <!-- ==== SCRIPTS: Inisialisasi & Handler ==== -->
  <script>
  (function(){
    function ready(fn){ if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }
    ready(function(){

      (function setupOptimizedTreeNav(){
        var $ = window.jQuery;
        if (!$) return;

        (function indexSubmenus(){
          var idx=0;
          $('.sidebar-menu > li.treeview').each(function(){
            var $li=$(this), $a=$li.children('a'), $sub=$li.children('.treeview-menu');
            if(!$sub.length) return;
            if(!$sub.attr('id')) $sub.attr('id','submenu-auto-'+(++idx));
            $a.attr({'aria-controls': $sub.attr('id')});
            var open = $li.hasClass('menu-open');
            $a.attr('aria-expanded', String(open));
            $sub.attr('aria-hidden', String(!open));
          });
        })();

        $(document).off('click.navTreeOptimized keydown.navTreeOptimized');

        function toggleTree($li){
          var $sub = $li.children('.treeview-menu'), $a = $li.children('a');
          if(!$sub.length) return;

          var $siblings = $li.siblings('.treeview.menu-open');
          if ($siblings.length){
            $siblings.removeClass('menu-open active')
              .children('.treeview-menu').stop(true,true).slideUp(140, function(){ $(this).css('height',''); })
              .attr('aria-hidden','true');
            $siblings.children('a').attr('aria-expanded','false');
          }

          var open = $li.hasClass('menu-open');
          if (open) {
            $sub.stop(true,true).slideUp(140, function(){ $sub.css('height',''); });
            $li.removeClass('menu-open active');
            $sub.attr('aria-hidden','true');
            $a.attr('aria-expanded','false');
          } else {
            $sub.stop(true,true).slideDown(140, function(){ $sub.css('height',''); });
            $li.addClass('menu-open active');
            $sub.attr('aria-hidden','false');
            $a.attr('aria-expanded','true');
          }
        }

        $(document).on('click.navTreeOptimized', '.sidebar-menu .treeview > a', function(e){
          e.preventDefault();
          toggleTree($(this).parent());
          return false;
        });

        $(document).on('keydown.navTreeOptimized', '.sidebar-menu .treeview > a', function(e){
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleTree($(this).parent()); }
        });
      })();

      var path = window.location.pathname.split('/').pop() || 'index.php';
      $('.sidebar-menu a').each(function(){
        var href = ($(this).attr('href')||'').split('/').pop();
        if(href === path){
          $(this).closest('li').addClass('active');
          var tree = $(this).closest('.treeview');
          if (tree.length){
            tree.addClass('menu-open active');
            tree.children('.treeview-menu').css('display','block').attr('aria-hidden','false');
            tree.children('a').attr('aria-expanded','true');
          }
          $(this).attr('aria-current','page');
        }
      });

      var pushMenuAvailable = (typeof $ !== 'undefined' && $.AdminLTE && $.AdminLTE.pushMenu);
      function setToggleAria(){
        var expanded = $('body').hasClass('sidebar-open') || !$('body').hasClass('sidebar-collapse');
        $('.sidebar-toggle').attr('aria-expanded', String(expanded));
      }
      if (!pushMenuAvailable) {
        $(document).off('click.pushFallback').on('click.pushFallback', '.sidebar-toggle', function(e){
          e.preventDefault();
          if (window.innerWidth > 768) $('body').toggleClass('sidebar-collapse');
          else $('body').toggleClass('sidebar-open');
          setToggleAria(); updateCollapsedTooltips();
        });
        $(document).off('click.closeSidebar').on('click.closeSidebar', function(ev){
          if (window.innerWidth <= 768) {
            var inside = $(ev.target).closest('.main-sidebar, .sidebar-toggle').length;
            if (!inside) {$('body').removeClass('sidebar-open'); setToggleAria();}
          }
        });
        setToggleAria();
      } else {
        $(document).off('click.syncPush').on('click.syncPush', '.sidebar-toggle', function(){
          setTimeout(function(){ setToggleAria(); updateCollapsedTooltips(); }, 0);
        });
      }

      function updateCollapsedTooltips(){
        if (!$.fn || !$.fn.tooltip) return;
        var collapsed = $('body').hasClass('sidebar-mini') && $('body').hasClass('sidebar-collapse') && window.innerWidth > 768;

        $('.sidebar-menu>li>a').each(function(){
          var $a=$(this);
          if (collapsed){
            if ($a.data('bs.tooltip')) $a.tooltip('destroy');
            $a.removeAttr('title').removeAttr('data-original-title');
          } else {
            var title = $a.find('.menu-text').text().trim();
            if(!$a.attr('title')) $a.attr('title', title);
            $a.tooltip({container:'body', placement:'right', trigger:'hover', viewport: { selector: 'body', padding: 2 }});
          }
        });
      }

      updateCollapsedTooltips();
      $(window).on('resize', updateCollapsedTooltips);

      (function setupMiniHoverFlyout(){
        var $ = window.jQuery; if(!$) return;
        var $doc=$(document), $win=$(window), $sidebar=$('.main-sidebar');
        var $panel = $('<div class="sidebar-flyout" role="menu" aria-hidden="true"></div>').appendTo('body').hide();
        var hideTimer=null, showTimer=null, currentLi=null;

        function isMiniCollapsed(){
          return $('body').hasClass('sidebar-mini') && $('body').hasClass('sidebar-collapse') && window.innerWidth > 768;
        }

        function buildPanel($li){
          $panel.empty();
          var $a=$li.children('a').first();
          var text = $.trim($a.find('.menu-text').text()) || $.trim($a.text());
          var $ico = $a.find('.menu-ic').first().clone(true,true);
          var href = $a.attr('href') || '#';
          var hasSub = $li.hasClass('treeview') && $li.children('.treeview-menu').length>0;

          var $header = $('<div class="flyout-header"></div>');
          if ($ico.length){ $header.append($('<span class="ico"></span>').append($ico)); }
          $header.append($('<span class="label"></span>').text(text));
          if (!hasSub){ $header.addClass('clickable').attr('data-href', href); }

          $panel.append($header);

          if (hasSub){
            var $clone = $li.children('.treeview-menu').first().clone(true,true).show();
            $panel.append($clone);
          }
        }

        function placePanel($li){
          var sb = $sidebar[0].getBoundingClientRect();
          var li = $li[0].getBoundingClientRect();
          $panel.css({visibility:'hidden', display:'block'});
          var ph = $panel.outerHeight();
          var top = li.top;
          var margin = 8;
          if (top + ph + margin > window.innerHeight) top = Math.max(margin, window.innerHeight - ph - margin);
          var left = sb.left + sb.width - 1;
          $panel.css({top: top, left: left, visibility:'visible'});
        }

        function showFor($li){
          if(!isMiniCollapsed()) return;
          currentLi = $li;
          clearTimeout(hideTimer); clearTimeout(showTimer);
          showTimer = setTimeout(function(){
            buildPanel($li);
            placePanel($li);
            $panel.attr('aria-hidden','false').fadeIn(80);
          }, 80);
        }

        function hidePanel(delayed){
          clearTimeout(showTimer); clearTimeout(hideTimer);
          var doHide=function(){ $panel.stop(true,true).hide().attr('aria-hidden','true'); currentLi=null; };
          if(delayed) hideTimer=setTimeout(doHide, 120); else doHide();
        }

        function bind(){
          $doc.off('.miniFlyout');
          if(!isMiniCollapsed()){ hidePanel(false); return; }

          $('.sidebar-menu>li>a').each(function(){ if($(this).data('bs.tooltip')) $(this).tooltip('destroy'); });

          $doc.on('mouseenter.miniFlyout', '.sidebar-menu>li', function(){ showFor($(this)); })
              .on('mouseleave.miniFlyout', '.sidebar-menu>li', function(){ hidePanel(true); });

          $panel.on('mouseenter.miniFlyout', function(){ clearTimeout(hideTimer); })
                .on('mouseleave.miniFlyout', function(){ hidePanel(true); });

          $panel.on('click.miniFlyout', '.flyout-header.clickable', function(){
            var href = $(this).attr('data-href'); if(href && href !== '#'){ window.location.href = href; }
          });
          $panel.on('click.miniFlyout', '.treeview-menu a', function(e){
            var href = $(this).attr('href'); if(href && href !== '#'){ window.location.href = href; }
          });
        }

        bind();
        $win.on('resize.miniFlyout', function(){ bind(); if(isMiniCollapsed() && !$panel.is(':hidden') && currentLi){ placePanel(currentLi); }});
        $(document).on('click.syncPush', '.sidebar-toggle', function(){ setTimeout(function(){ bind(); }, 20); });

      })();

      (function autoHideAfterSelect(){
        var $ = window.jQuery; if (!$) return;
        var $body = $('body');
        var LS_KEY = 'eps_sidebar_collapsed';

        function isMiniCollapsed(){
          return window.innerWidth > 768 &&
                 $body.hasClass('sidebar-mini') &&
                 $body.hasClass('sidebar-collapse');
        }

        $(document).off('click.hideAfterSelect').on('click.hideAfterSelect', '.sidebar-menu a', function(){
          var $a = $(this);
          var href = ($a.attr('href') || '').trim();
          var isTreeParent  = $a.parent().hasClass('treeview') && $a.next('.treeview-menu').length > 0;
          var isSubmenuLink = $a.closest('.treeview-menu').length > 0;
          var willNavigate  = !!(href && href !== '#' && !/^javascript:/i.test(href));

        if (isMiniCollapsed() && $body.hasClass('sidebar-expanded-on-hover') && (willNavigate || isSubmenuLink || !isTreeParent)) {
            try { localStorage.setItem(LS_KEY, '1'); } catch(e){}
            setTimeout(function(){ $body.removeClass('sidebar-expanded-on-hover'); }, 30);
          }

          if (window.innerWidth <= 768 && $body.hasClass('sidebar-open')) {
            if (isSubmenuLink || (!isTreeParent && willNavigate)) {
              setTimeout(function(){ $body.removeClass('sidebar-open'); }, 0);
            }
          }

          var $fly = $('.sidebar-flyout');
          if ($fly.length && (willNavigate || isSubmenuLink)) {
            $fly.stop(true,true).hide().attr('aria-hidden','true');
          }
        });

        $('.sidebar-toggle').off('click.persistCollapse').on('click.persistCollapse', function(){
          setTimeout(function(){
            try { localStorage.setItem(LS_KEY, $body.hasClass('sidebar-collapse') ? '1' : '0'); } catch(e){}
          }, 10);
        });
      })();

      if ($ && $.fn && $.fn.tooltip) {
        $('[data-toggle="tooltip"]').tooltip({container:'body'});
      }

      if ($.fn && $.fn.dataTable) {
        $.fn.dataTable.ext.errMode = 'console';
      }

      // Tetap: handler modal WIP generik (CBT Pro, Sinkronkan Nilai, dll.)
      $(document).on('click','.wip-link', function(e){
        e.preventDefault();
        var name = $(this).data('wip') || $(this).text().trim();
        $('#wipTarget').text(name);
        if ($.fn && $.fn.modal) { $('#wipModal').modal({backdrop:'static', keyboard:false}); }
        else { window.alert(name+" sedang dalam pengembangan. Silakan kembali lagi nanti."); }
      });

      // === [CHANGE] Hapus intercept untuk 'status_penilaian.php' agar link Rapor STS berfungsi ===
      (function autoWipPenilaianLinks(){
        var $ = window.jQuery; if(!$) return;
        var WIP_MAP = {
          'nilai_rapor.php':'Nilai Rapor',
          // 'status_penilaian.php':'Status Penilaian', // <-- DIHAPUS agar tidak diintercept
          'cetak_leger_rapor.php':'Cetak — Leger Rapor',
          'cetak_nilai_rapor.php':'Cetak — Nilai Rapor'
        };
        $(document).on('click', '.sidebar-menu a', function(ev){
          var href = ($(this).attr('href')||'').trim();
          if (WIP_MAP[href]){
            ev.preventDefault();
            $('#wipTarget').text(WIP_MAP[href]);
            if ($.fn && $.fn.modal) { $('#wipModal').modal({backdrop:'static', keyboard:false}); }
            else { window.alert(WIP_MAP[href] + ' sedang dalam pengembangan. Silakan kembali lagi nanti.'); }
          }
        });
      })();

      // [NEW] Handler khusus tombol Integrasi e-Rapor → buka modal #eraporModal
      $(document).on('click', '.erapor-link', function(e){
        e.preventDefault();
        if ($.fn && $.fn.modal) { $('#eraporModal').modal({backdrop:'static', keyboard:false}); }
        else { window.alert('Integrasi e-Rapor (Pra-Rilis): ekspor XLS + TP untuk percepat impor e-Rapor 2026.'); }
      });

      try{
        if (path === 'rekap_kelas.php') {
          localStorage.setItem('monitor_seen_at', String(Date.now()));
        }
        $(document).on('click','.menu-monitoring', function(){
          localStorage.setItem('monitor_seen_at', String(Date.now()));
        });
      }catch(e){}

    });

  })();
  </script>

  <!-- ANTI-FLICKER: lepas kelas setelah semua resource selesai dimuat -->
  <script>
    window.addEventListener('load', function () {
      document.documentElement.classList.remove('init-loading');
    });
  </script>
