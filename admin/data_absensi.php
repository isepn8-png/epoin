<?php
// admin/data_absensi.php — v2.7 (FIX 500 + konsistensi #Mapel/Guru & daftar Mapel–Guru semua JP final)

ini_set('display_errors',1); error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../koneksi.php';

/* ===== Fallback mbstring ===== */
if (!function_exists('mb_strlen')) { function mb_strlen($s){ return strlen($s); } }
if (!function_exists('mb_substr')) { function mb_substr($s,$start,$len=null){ return isset($len)? substr($s,$start,$len): substr($s,$start); } }

/* ===== RBAC (kompatibel) ===== */
$__auth = __DIR__ . '/../includes/auth.php';
if (is_file($__auth)) {
  require_once $__auth;
  if (function_exists('ensure_logged_in')) { ensure_logged_in(); }
  if (function_exists('current_user_id')) { $user_id = (int) current_user_id(); }
  if (!function_exists('_is_admin') && function_exists('user_has_any_role')) { function _is_admin(){ return user_has_any_role(array('administrator','superadmin')); } }
  if (!function_exists('_is_guru') && function_exists('user_has_role')) { function _is_guru(){ return user_has_role('guru'); } }
  if (!function_exists('_is_tas') && function_exists('user_has_role')) { function _is_tas(){ return user_has_role('tas'); } }
  if (!function_exists('_is_sekretaris')) {
    if (function_exists('user_has_any_role')) {
      function _is_sekretaris(){ return user_has_any_role(array('sekretaris','sekretaris_kelas','sekretariskelas')); }
    } elseif (function_exists('user_has_role')) {
      function _is_sekretaris(){ return user_has_role('sekretaris') || user_has_role('sekretaris_kelas') || user_has_role('sekretariskelas'); }
    }
  }
} else {
  $user_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
  if (!function_exists('_is_admin')) { function _is_admin(){ return (isset($_SESSION['level']) && $_SESSION['level']==='administrator'); } }
  if (!function_exists('_is_guru'))  { function _is_guru(){  return false; } }
  if (!function_exists('_is_tas'))   { function _is_tas(){   return false; } }
  if (!function_exists('_is_sekretaris')) {
    function _is_sekretaris(){
      $roles = isset($_SESSION['roles']) ? $_SESSION['roles'] : array();
      foreach($roles as $r){
        $rn = str_replace(array(' ','-'),array('',''), strtolower((string)$r));
        if (in_array($rn, array('sekretaris','sekretaris_kelas','sekretariskelas'), true)) return true;
      }
      return false;
    }
  }
}
$IS_ADMIN = function_exists('_is_admin') ? _is_admin() : false;
$IS_GURU  = function_exists('_is_guru')  ? _is_guru()  : false;
$IS_TAS   = function_exists('_is_tas')   ? _is_tas()   : false;
$IS_SEK   = function_exists('_is_sekretaris') ? _is_sekretaris() : false;
$CAN_VIEW_ALL = function_exists('user_can') ? user_can('attendance.view_all') : false;

/* ===== Helpers ===== */
function _get($k, $d=''){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function _post($k, $d=''){ return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }
function _int($v){ return (int)$v; }
function esc($c){ global $koneksi; return mysqli_real_escape_string($koneksi, $c); }

/* Cek kolom tersedia pada tabel */
function table_has_cols($table, $cols){
  global $koneksi;
  $found = array();
  $rs = mysqli_query($koneksi, "SHOW COLUMNS FROM `".$table."`");
  if ($rs) { while($r = mysqli_fetch_assoc($rs)){ $found[] = $r['Field']; } }
  $out = array();
  foreach($cols as $c){ $out[$c] = in_array($c,$found,true); }
  return $out;
}

/* Ambil TA aktif (schema-safe) */
function get_ta_aktif(){
  global $koneksi;
  $has = table_has_cols('ta', array('tahun_awal','tahun_akhir'));
  $select = "ta_id, ta_nama";
  if ($has['tahun_awal']) $select .= ", tahun_awal";
  if ($has['tahun_akhir']) $select .= ", tahun_akhir";
  $q = mysqli_query($koneksi,"SELECT $select FROM ta WHERE ta_status=1 LIMIT 1");
  if($q && $r=mysqli_fetch_assoc($q)) return $r;
  return null;
}
$TA_ROW = get_ta_aktif();
$TA = $TA_ROW ? (int)$TA_ROW['ta_id'] : 0;

/* Parse tahun ajaran jadi [y1,y2] */
function ta_years($ta_row){
  $y1=0; $y2=0;
  if(!$ta_row) return array(0,0);
  if (!empty($ta_row['tahun_awal']) && !empty($ta_row['tahun_akhir'])) {
    $y1 = (int)$ta_row['tahun_awal']; $y2 = (int)$ta_row['tahun_akhir'];
  } elseif (!empty($ta_row['ta_nama']) && preg_match('/(\d{4}).*?(\d{4})/',$ta_row['ta_nama'],$m)) {
    $y1 = (int)$m[1]; $y2 = (int)$m[2];
  } else {
    $nowY=(int)date('Y'); $nowM=(int)date('n');
    $y1 = ($nowM>=7) ? $nowY : $nowY-1; $y2=$y1+1;
  }
  if ($y2!==$y1+1) $y2=$y1+1;
  return array($y1,$y2);
}
function semester_range($ta_row,$sem){
  list($y1,$y2) = ta_years($ta_row);
  if ($sem==2) return array($y2.'-01-01',$y2.'-06-30'); // genap
  return array($y1.'-07-01',$y1.'-12-31');              // ganjil
}
function default_semester($ta_row){
  list($y1,$y2)=ta_years($ta_row);
  $t=date('Y-m-d');
  if ($t>=$y1.'-07-01' && $t<=$y1.'-12-31') return 1;
  if ($t>=$y2.'-01-01' && $t<=$y2.'-06-30') return 2;
  return 1;
}

/* ===== Params (Filter UI dihilangkan; data otomatis) ===== */
$mode       = 'harian';
$semester   = default_semester($TA_ROW);
$kelas_id   = 0;
$mapel_id   = 0;
$status     = in_array(_get('status',''), array('draft','final')) ? _get('status','') : '';
$guru_id    = 0;

list($dari,$sampai) = semester_range($TA_ROW,$semester);

/* ===== FUNGSI SINKRON ===== */
$helper_loaded=false;
$helper_path = __DIR__ . '/../includes/absensi_helper.php';
if (is_file($helper_path)) {
  require_once $helper_path;
  if (function_exists('sinkron_harian_dari_sesi')) $helper_loaded=true;
}
if (!$helper_loaded && !function_exists('sinkron_harian_dari_sesi')) {
  function sinkron_harian_dari_sesi($tanggal, $kelas, $metode='mayoritas'){
    global $koneksi, $TA, $user_id;
    $tanggal = esc($tanggal); $kelas=(int)$kelas;

    $cek = mysqli_query($koneksi,"SELECT harian_id,status FROM absensi_harian WHERE ta_id=$TA AND tanggal='$tanggal' AND kelas_id=$kelas LIMIT 1");
    if($cek && $row=mysqli_fetch_assoc($cek)){
      $harian_id=(int)$row['harian_id'];
      $hstatus = $row['status'];
    } else {
      mysqli_query($koneksi,"INSERT INTO absensi_harian(ta_id,tanggal,kelas_id,petugas_id,status,created_at) VALUES($TA,'$tanggal',$kelas,$user_id,'draft',NOW())");
      $harian_id = (int)mysqli_insert_id($koneksi);
      $hstatus = 'draft';
    }

    $sql="SELECT d.siswa_id,
                 SUM(d.status='A') a_cnt, SUM(d.status='I') i_cnt, SUM(d.status='S') s_cnt, SUM(d.status='H') h_cnt
          FROM absensi_sesi s
          JOIN absensi_sesi_detail d ON d.sesi_id=s.sesi_id
          WHERE s.ta_id=$TA AND s.tanggal='$tanggal' AND s.kelas_id=$kelas AND s.status='final'
          GROUP BY d.siswa_id";
    $res=mysqli_query($koneksi,$sql);
    $processed=0;
    while($r=mysqli_fetch_assoc($res)){
      $a=(int)$r['a_cnt']; $i=(int)$r['i_cnt']; $s=(int)$r['s_cnt']; $h=(int)$r['h_cnt'];
      if($metode==='any_alpha'){ $st = $a>0?'A':($i>0?'I':($s>0?'S':'H')); }
      else{ $rank=array('A'=>$a,'I'=>$i,'S'=>$s,'H'=>$h); arsort($rank); $keys=array_keys($rank); $st=$keys[0]; }
      mysqli_query($koneksi,"INSERT INTO absensi_harian_detail(harian_id,siswa_id,status,updated_by,updated_at)
                             VALUES($harian_id,{$r['siswa_id']},'$st',$user_id,NOW())
                             ON DUPLICATE KEY UPDATE status=VALUES(status),updated_by=$user_id,updated_at=NOW()");
      $processed++;
    }
    return array('processed'=>$processed,'harian_id'=>$harian_id,'status'=>$hstatus);
  }
}

/* ===== AJAX Endpoints ===== */
if (isset($_GET['ajax'])) {
  ini_set('display_errors', 0);
  $ajax = $_GET['ajax'];

  if ($ajax==='detail') {
    header('Content-Type: text/html; charset=utf-8');
    $tgl = esc(_get('tgl','')); $kls = _int(_get('kls',0));
    if (!$tgl || !$kls) { echo '<div class="text-danger">Parameter tidak lengkap.</div>'; exit; }
    $sql = "
      SELECT s.sesi_id, s.jam_ke, s.status, m.mapel_nama, u.user_nama AS guru,
             SUM(d.status='H') h_cnt, SUM(d.status='S') s_cnt, SUM(d.status='I') i_cnt, SUM(d.status='A') a_cnt,
             COUNT(d.siswa_id) total
      FROM absensi_sesi s
      JOIN mapel m ON m.mapel_id=s.mapel_id
      JOIN user  u ON u.user_id=s.guru_user_id
      LEFT JOIN absensi_sesi_detail d ON d.sesi_id=s.sesi_id
      WHERE s.ta_id=$TA AND s.tanggal='$tgl' AND s.kelas_id=$kls AND s.status='final'
      GROUP BY s.sesi_id, s.jam_ke, s.status, m.mapel_nama, u.user_nama
      ORDER BY s.jam_ke ASC, m.mapel_nama ASC";
    $q = mysqli_query($koneksi,$sql);
    if(!$q || mysqli_num_rows($q)==0){ echo '<div class="text-muted">Belum ada JP FINAL.</div>'; exit; }
    echo '<div class="table-responsive"><table class="table table-condensed table-striped">';
    echo '<thead><tr><th>JP</th><th>Mapel</th><th>Guru</th><th class="text-center">H</th><th class="text-center">S</th><th class="text-center">I</th><th class="text-center">A</th><th class="text-center">Total</th><th>Detail</th></tr></thead><tbody>';
    while($r=mysqli_fetch_assoc($q)){
      echo '<tr>'.
           '<td>'.(int)$r['jam_ke'].'</td>'.
           '<td>'.htmlspecialchars($r['mapel_nama']).'</td>'.
           '<td>'.htmlspecialchars($r['guru']).'</td>'.
           '<td class="text-center">'.(int)$r['h_cnt'].'</td>'.
           '<td class="text-center">'.(int)$r['s_cnt'].'</td>'.
           '<td class="text-center">'.(int)$r['i_cnt'].'</td>'.
           '<td class="text-center">'.(int)$r['a_cnt'].'</td>'.
           '<td class="text-center">'.(int)$r['total'].'</td>'.
           '<td><a class="btn btn-xs btn-info" href="absensi_mapel.php?sesi_id='.(int)$r['sesi_id'].'" target="_blank"><i class="fa fa-search"></i> Detail JP</a></td>'.
           '</tr>';
    }
    echo '</tbody></table></div>';
    exit;
  }

  if ($ajax==='sync_one' && $_SERVER['REQUEST_METHOD']==='POST') {
    header('Content-Type: application/json');
    $pair = _post('pair','');
    $met  = in_array(_post('metode','mayoritas'), array('mayoritas','any_alpha')) ? _post('metode','mayoritas') : 'mayoritas';
    $out = array('ok'=>false,'msg'=>'Parameter tidak lengkap');
    if ($pair){
      $parts = explode('|',$pair);
      if(count($parts)==2){
        $tgl = esc($parts[0]); $kls = (int)$parts[1];
        $res = sinkron_harian_dari_sesi($tgl,$kls,$met);
        $out = array('ok'=>true,'processed'=>(int)$res['processed'],'status'=>$res['status'],'tgl'=>$parts[0],'kelas_id'=>$kls);
      }
    }
    echo json_encode($out); exit;
  }

  if ($ajax==='finalkan' && $_SERVER['REQUEST_METHOD']==='POST') {
    header('Content-Type: application/json');

    $tgl = esc(_post('tgl','')); $kls = _int(_post('kls',0));
    if (!$tgl || !$kls) { echo json_encode(array('ok'=>false,'msg'=>'Parameter tidak lengkap')); exit; }

    $q = mysqli_query($koneksi,"SELECT harian_id FROM absensi_harian WHERE ta_id=$TA AND tanggal='$tgl' AND kelas_id=$kls LIMIT 1");
    if (!$q || !($row=mysqli_fetch_assoc($q))) {
      echo json_encode(array('ok'=>false,'msg'=>'Data harian tidak ditemukan')); exit;
    }

    $cols = table_has_cols('absensi_harian', array('finalized_by','finalized_at'));
    $set = "status='final'";
    if (!empty($cols['finalized_by'])) $set .= ", finalized_by=".(int)$GLOBALS['user_id'];
    if (!empty($cols['finalized_at'])) $set .= ", finalized_at=NOW()";

    $ok = mysqli_query($koneksi,"UPDATE absensi_harian SET $set WHERE harian_id=".(int)$row['harian_id']." LIMIT 1");
    if (!$ok) { echo json_encode(array('ok'=>false,'msg'=>'DB error')); exit; }

    echo json_encode(array('ok'=>true)); exit;
  }

  if ($ajax==='row_status') {
    header('Content-Type: application/json');
    $tgl = esc(_get('tgl','')); $kls = _int(_get('kls',0));
    if (!$tgl || !$kls) { echo json_encode(array('ok'=>false)); exit; }
    $q = mysqli_query($koneksi,"
      SELECT
        (SELECT h.status FROM absensi_harian h
         WHERE h.ta_id=$TA AND h.kelas_id=$kls AND h.tanggal='$tgl'
         ORDER BY FIELD(h.status,'final','draft') LIMIT 1) AS h_status
    ");
    $row = $q? mysqli_fetch_assoc($q) : null;
    echo json_encode(array('ok'=>true,'status'=>($row && $row['h_status'])?$row['h_status']:null)); exit;
  }

  http_response_code(400); echo 'Bad Request'; exit;
}

/* ===== Bulk Sync Fallback (non-JS) ===== */
if ($_SERVER['REQUEST_METHOD']==='POST' && _post('aksi')==='bulk_sync') {
  $metode = in_array(_post('metode','mayoritas'), array('mayoritas','any_alpha')) ? _post('metode','mayoritas') : 'mayoritas';
  $items  = (isset($_POST['item']) && is_array($_POST['item'])) ? $_POST['item'] : array();
  $done = 0;
  foreach($items as $pair){
    $parts = explode('|',$pair);
    if(count($parts)==2){
      $tgl = $parts[0]; $kls = (int)$parts[1];
      $r = sinkron_harian_dari_sesi($tgl,$kls,$metode);
      $done += (int)$r['processed'];
    }
  }
  $qs = http_build_query(array('alert'=>'sinkron_ok_'.$done));
  header("Location: data_absensi.php?$qs"); exit;
}

/* ===== Stop bila TA tidak aktif ===== */
if ($TA <= 0) {
  include 'header.php';
  echo '<div class="content-wrapper"><section class="content"><div class="alert alert-danger">Tahun ajaran aktif tidak ditemukan.</div></section></div>';
  include 'footer.php'; exit;
}

/* ===== Data Perlu Sinkron (schema-safe + status harian) ===== */
$unsyn_list = array();
$where_uns = "s.ta_id=$TA AND s.tanggal BETWEEN '".esc($dari)."' AND '".esc($sampai)."' AND s.status='final'";
/* Guru biasa hanya melihat baris yang ada JP miliknya.
   Tapi kolom mg_cnt & mapel_guru selalu menghitung SEMUA JP final untuk tanggal & kelas itu. */
if(!$CAN_VIEW_ALL && $IS_GURU) $where_uns.=" AND s.guru_user_id=$user_id";

$sql_uns = "
  SELECT
    s.tanggal, s.kelas_id, k.kelas_nama,
    COUNT(DISTINCT s.sesi_id) AS jp_final,

    /* Hitung jumlah unik Mapel/Guru yang ngabsen di tanggal+kelas ini (semua JP final) */
    (
      SELECT COUNT(DISTINCT s2.mapel_id, s2.guru_user_id)
      FROM absensi_sesi s2
      WHERE s2.ta_id=s.ta_id AND s2.tanggal=s.tanggal AND s2.kelas_id=s.kelas_id AND s2.status='final'
    ) AS mg_cnt,

    (SELECT h.status FROM absensi_harian h
     WHERE h.ta_id=s.ta_id AND h.kelas_id=s.kelas_id AND h.tanggal=s.tanggal
     ORDER BY FIELD(h.status,'final','draft') LIMIT 1) AS h_status,

    /* Daftar lengkap Mapel - Guru (semua JP final di tanggal+kelas ini) */
    (
      SELECT GROUP_CONCAT(DISTINCT CONCAT(m2.mapel_nama,' - ',u2.user_nama) ORDER BY m2.mapel_nama SEPARATOR ' | ')
      FROM absensi_sesi sx
      JOIN mapel m2 ON m2.mapel_id=sx.mapel_id
      JOIN user  u2 ON u2.user_id=sx.guru_user_id
      WHERE sx.ta_id=s.ta_id AND sx.tanggal=s.tanggal AND sx.kelas_id=s.kelas_id AND sx.status='final'
    ) AS mapel_guru

  FROM absensi_sesi s
  JOIN kelas k ON k.kelas_id=s.kelas_id
  WHERE $where_uns
  GROUP BY s.tanggal, s.kelas_id, k.kelas_nama
  HAVING COUNT(DISTINCT s.sesi_id) > 0
     AND (h_status IS NULL OR h_status<>'final')
  ORDER BY s.tanggal DESC, k.kelas_nama ASC";
$qu = mysqli_query($koneksi,$sql_uns);
while($r=mysqli_fetch_assoc($qu)){
  $mg = isset($r['mapel_guru']) ? (string)$r['mapel_guru'] : ''; // <- FIX typo bracket di sini
  if (mb_strlen($mg) > 220) $mg = mb_substr($mg,0,217).'…';
  $r['mapel_guru'] = $mg;
  $unsyn_list[] = $r;
}

/* ===== util tampilan ===== */
function tgl_view($ymd){ $ts=strtotime($ymd); return $ts? date('d-m-Y',$ts):''; }
list($Y1,$Y2) = ta_years($TA_ROW);
$ta_label = !empty($TA_ROW['ta_nama']) ? $TA_ROW['ta_nama'] : ($Y1.'/'.$Y2);

include 'header.php';
?>
<style>
.content-wrapper{ min-height:calc(100vh - 101px); background:linear-gradient(180deg,#f6f8fb 0%, #ffffff 100%); }
h1 small{ color:#6b7280; }
.box + .box{ margin-top:12px; }

.table-hover>tbody>tr:hover{ background:#f7fee7; }
th,td{ vertical-align:middle !important; }
#tblPending th.text-center, #tblPending td.text-center{ text-align:center; }

.notice{ background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; border-radius:8px; padding:10px 14px; margin-bottom:10px; }
.notice b{ color:#1e3a8a; }

/* Segmented metode */
.metode-toggle{ display:inline-flex; border:1px solid #cbd5e1; border-radius:999px; overflow:hidden; background:#fff; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
.metode-toggle .seg{ padding:6px 14px; border:0; background:transparent; color:#334155; font-weight:600; }
.metode-toggle .seg.active{ background:linear-gradient(90deg,#22c55e,#16a34a); color:#fff; }
.metode-info{ margin-left:10px; color:#64748b; font-weight:600; font-size:12px; }

.sync-toolbar{ display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
.sync-toolbar .help{ color:#64748b; cursor:pointer; }

.btn-sync .fa-refresh{ transition: transform .2s linear; }
.btn-sync.is-loading{ pointer-events:none; opacity:.9; }
.btn-sync.is-loading .fa-refresh{ animation: spinRotate 1s linear infinite; }
@keyframes spinRotate { from{ transform: rotate(0deg); } to{ transform: rotate(360deg); } }

.badge-status{ display:inline-block; padding:3px 8px; border-radius:999px; font-weight:700; font-size:12px; }
.badge-belum{ background:#fee2e2; color:#991b1b; }
.badge-draft{ background:#fef3c7; color:#92400e; }
.badge-final{ background:#dcfce7; color:#166534; }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <h1>Data Absensi <small>SINKRON JP → HARIAN</small></h1>
  </section>

  <section class="content">

    <div class="notice">
      <i class="fa fa-info-circle"></i>
      <b>Mayoritas</b> menetapkan status harian berdasarkan status yang paling sering muncul di semua JP FINAL (H/I/S/A).
      <b>Any-alpha</b> memprioritaskan ketidakhadiran: <b>A &gt; I &gt; S &gt; H</b> bila salah satunya muncul pada JP manapun.
    </div>

    <?php if (isset($_GET['alert'])):
      $a = $_GET['alert']; $msg = '—';
      if     ($a === 'sinkron_param')     $msg = 'Parameter sinkron tidak lengkap.';
      elseif ($a === 'sinkron_nop')       $msg = 'Tidak ada data sesi final untuk disinkron.';
      elseif ($a === 'sinkron_ok')        $msg = 'Sinkron selesai.';
      elseif (strpos($a,'sinkron_ok_')===0) { $n=(int)substr($a,11); $msg="Sinkron selesai. $n baris terproses."; }
    ?>
      <div class="alert alert-<?php echo ($a==='sinkron_param')?'danger':(($a==='sinkron_nop')?'warning':'success'); ?>">
        <?php echo htmlspecialchars($msg); ?>
      </div>
    <?php endif; ?>

    <!-- PANEL: Perlu Sinkron -->
    <div class="box box-accent">
      <div class="box-header with-border">
        <h3 class="box-title">
          <i class="fa fa-exchange"></i> Perlu Sinkron (JP→Harian)
          <span class="badge-soft"><?php echo count($unsyn_list); ?> item</span>
        </h3>

        <div class="box-tools">
          <form method="post" id="formBulkSync" class="sync-toolbar">
            <input type="hidden" name="aksi" value="bulk_sync">
            <input type="hidden" id="metode" name="metode" value="mayoritas">
            <div class="metode-toggle" role="group" aria-label="Metode Sinkron">
              <button type="button" class="seg active" data-met="mayoritas">Mayoritas</button>
              <button type="button" class="seg" data-met="any_alpha">Any-alpha</button>
            </div>
            <span class="metode-info" id="metodeInfo">Metode terpilih: <b>Mayoritas</b></span>
            <span class="help" data-toggle="tooltip" data-html="true"
              title="<b>Mayoritas</b>: status terbanyak per siswa.<br><b>Any-alpha</b>: jika ada A/I/S di salah satu JP, tetapkan A&gt;I&gt;S&gt;H.">
              <i class="fa fa-info-circle"></i>
            </span>
            <button type="submit" id="btnSyncSelected" class="btn btn-success btn-sync" <?php echo count($unsyn_list)==0?'disabled':''; ?>>
              <i class="fa fa-refresh"></i> Sinkron Terpilih
            </button>

            <button type="button" id="btnFinalkanAllDraft" class="btn btn-primary btn-sync" style="margin-left:6px;">
              <i class="fa fa-check-square-o"></i> Finalkan semua DRAFT
            </button>
          </form>
        </div>
      </div>

      <div class="box-body">
        <div class="table-responsive">
          <table id="tblPending" class="table table-bordered table-hover" style="width:100%">
            <thead>
              <tr>
                <th style="width:32px"><input type="checkbox" id="chkAll"></th>
                <th>Tanggal</th>
                <th>Kelas</th>
                <th class="text-center"># JP Final</th>
                <th class="text-center"># Mapel/Guru</th>
                <th>Status Sinkron</th>
                <th>Mapel - Guru (JP Final)</th>
                <th style="width:200px;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($unsyn_list as $r):
                $status_txt = $r['h_status'] ? strtoupper($r['h_status']) : 'BELUM';
                $badge_class = $r['h_status']==='draft' ? 'badge-draft' : 'badge-belum';
                $is_draft = ($r['h_status']==='draft');
                $pair = htmlspecialchars($r['tanggal'].'|'.$r['kelas_id']);
              ?>
              <tr data-pair="<?php echo $pair; ?>">
                <td>
                  <input type="checkbox" name="item[]" form="formBulkSync" value="<?php echo $pair; ?>" <?php echo $is_draft?'disabled':''; ?>>
                </td>
                <td class="tgl"><?php echo htmlspecialchars($r['tanggal']); ?></td>
                <td class="kelas"><?php echo htmlspecialchars($r['kelas_nama']); ?></td>
                <td class="text-center"><span class="label label-primary" style="display:inline-block;min-width:34px;"><?php echo (int)$r['jp_final']; ?></span></td>
                <td class="text-center"><span class="label label-info" style="display:inline-block;min-width:34px;"><?php echo (int)$r['mg_cnt']; ?></span></td>
                <td class="status">
                  <span class="badge-status <?php echo $badge_class; ?>">
                    <?php echo htmlspecialchars($status_txt); ?>
                  </span>
                </td>
                <td><?php echo htmlspecialchars( $r['mapel_guru'] ? $r['mapel_guru'] : '-' ); ?></td>
                <td>
                  <div class="btn-group">
                    <button class="btn btn-info btn-xs btn-detail"
                            data-tgl="<?php echo htmlspecialchars($r['tanggal']); ?>"
                            data-kls="<?php echo (int)$r['kelas_id']; ?>">
                      <i class="fa fa-search"></i> Detail
                    </button>
                    <?php if ($is_draft): ?>
                      <button class="btn btn-success btn-xs btn-finalkan"><i class="fa fa-check-circle"></i> Finalkan</button>
                    <?php else: ?>
                      <button class="btn btn-warning btn-xs btn-sync-one"><i class="fa fa-refresh"></i> Sinkron</button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if(empty($unsyn_list)): ?>
          <p class="text-muted" style="margin-top:8px"><i class="fa fa-check-circle text-success"></i> Tidak ada item yang perlu sinkron pada periode ini.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Tips -->
    <div class="box box-default">
      <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-lightbulb-o"></i> Tips & Rekomendasi</h3>
        <div class="box-tools pull-right">
          <button class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
        </div>
      </div>
      <div class="box-body">
        <ul style="margin-left:18px">
          <li><b>Bulk Sync</b> menampilkan progres global yang halus dan real-time.</li>
          <li>Item <b>Draft</b> diberi tanda dan tidak ikut terpilih lagi.</li>
          <li>Gunakan <b>Finalkan</b> atau <b>Finalkan semua DRAFT</b> agar hilang dari daftar.</li>
        </ul>
      </div>
    </div>

  </section>
</div>

<!-- GLOBAL PROGRESS OVERLAY -->
<div id="overlaySync" role="dialog" aria-modal="true" aria-label="Progres Sinkron">
  <div id="overlayCard">
    <button id="btnBatalSync" type="button"><i class="fa fa-times"></i></button>
    <div class="title"><i class="fa fa-bolt"></i> Proses Sedang Berjalan</div>
    <div class="subtitle" id="ovlSubtitle">Menyiapkan…</div>
    <div class="bar"><span id="ovlBar"></span></div>
    <div class="stats">
      <div><i class="fa fa-check"></i> Selesai: <span id="ovlOk">0</span></div>
      <div><i class="fa fa-exclamation-triangle"></i> Gagal: <span id="ovlErr">0</span></div>
      <div><i class="fa fa-list"></i> Total: <span id="ovlTotal">0</span></div>
    </div>
    <span class="pill" id="ovlPct">0%</span>
  </div>
</div>

<!-- Modal Detail -->
<div class="modal fade" id="modalDetail" tabindex="-1">
  <div class="modal-dialog" style="width:900px;max-width:98%;">
    <div class="modal-content">
      <div class="modal-header" style="background:#eef2ff;border-bottom-color:#c7d2fe">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title"><i class="fa fa-list"></i> Detail JP FINAL</h4>
      </div>
      <div class="modal-body" id="modalDetailBody">
        <div class="text-muted">Memuat…</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Datepicker -->
<script src="../assets/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js"></script>

<script>
$(function(){
  try {
    $('.select2').select2({
      width: '100%',
      minimumResultsForSearch: Infinity,
      language: { noResults: function(){ return ' '; } },
      dropdownAutoWidth: true
    });
  } catch(e){}

  if ($.fn.tooltip) { $('[data-toggle="tooltip"]').tooltip({container:'body', html:true, trigger:'hover focus'}); }

  var dt = $('#tblPending').DataTable({
    dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>t<"row"<"col-sm-5"i><"col-sm-7"p>>',
    responsive: true, autoWidth:false, pageLength: 15, lengthChange:true,
    order:[[1,'desc']],
    columnDefs:[
      {targets:[0,7], orderable:false, searchable:false},
      {targets:[3,4], className:'text-center'}
    ],
    language:{ search:"_INPUT_", searchPlaceholder:"Cari tanggal/kelas/mapel/guru…" }
  });

  function countDraftVisible(){
    var n=0;
    $('#tblPending tbody tr').each(function(){
      var txt = $(this).find('.status .badge-status').text().trim().toUpperCase();
      if (txt==='DRAFT') n++;
    });
    return n;
  }
  function setFinalkanAllButton(){
    $('#btnFinalkanAllDraft').prop('disabled', countDraftVisible()===0);
  }
  setFinalkanAllButton();

  $('#chkAll').on('change', function(){
    var on = $(this).is(':checked');
    $('#tblPending tbody tr').each(function(){
      var $cb = $(this).find('input[type=checkbox]');
      if (!$cb.prop('disabled')) $cb.prop('checked', on);
    });
  });

  $(document).on('click','.btn-detail',function(){
    var tgl=$(this).data('tgl'), kls=$(this).data('kls');
    $('#modalDetail').modal('show');
    $('#modalDetailBody').html('<div class="text-muted">Memuat…</div>');
    $.get('data_absensi.php',{ajax:'detail', tgl: tgl, kls: kls}, function(html){
      $('#modalDetailBody').html(html||'<div class="text-muted">Tidak ada data.</div>');
    }).fail(function(){
      $('#modalDetailBody').html('<div class="text-danger">Gagal memuat detail.</div>');
    });
  });

  var $btnApply = $('#btnTerapkan');
  $('#kelas_id').on('change', function(){ $btnApply.addClass('attn'); });
  $('#absenFilter').on('submit', function(){ $btnApply.removeClass('attn'); });

  function setMetode(m){
    $('#metode').val(m);
    $('.metode-toggle .seg').removeClass('active');
    $('.metode-toggle .seg[data-met="'+m+'"]').addClass('active');
    var label = (m==='any_alpha')?'Any-alpha':'Mayoritas';
    $('#metodeInfo').html('Metode terpilih: <b>'+label+'</b>');
  }
  $(document).on('click','.metode-toggle .seg', function(){ setMetode($(this).data('met')); });
  setMetode($('#metode').val()||'mayoritas');

  var abortBulk = false;
  function showOverlay(total, titleTxt){
    abortBulk = false;
    $('#overlaySync').css('display','flex');
    $('#ovlTotal').text(total||0);
    $('#ovlOk').text(0); $('#ovlErr').text(0);
    $('#ovlBar').css('width','0%'); $('#ovlPct').text('0%');
    $('#ovlSubtitle').text(titleTxt || 'Menyiapkan…');
    $('#btnBatalSync').prop('disabled', false);
  }
  function hideOverlay(){ $('#overlaySync').hide(); }
  $('#btnBatalSync').on('click', function(){
    abortBulk = true;
    $(this).prop('disabled', true);
    $('#ovlSubtitle').text('Membatalkan setelah langkah saat ini…');
  });
  function updateOverlay(done, total, ok, err, currentLabel){
    var pct = total? Math.round((done/total)*100) : 0;
    $('#ovlBar').css('width', pct+'%');
    $('#ovlPct').text(pct+'%');
    $('#ovlOk').text(ok); $('#ovlErr').text(err);
    $('#ovlSubtitle').text(currentLabel||('Memproses '+done+'/'+total));
  }

  $(document).on('click','.btn-sync-one',function(){
    var $tr = $(this).closest('tr');
    var pair = $tr.data('pair');
    var metode = $('#metode').val()||'mayoritas';
    var $btn = $(this);
    $btn.prop('disabled', true).addClass('is-loading');
    $.post('data_absensi.php?ajax=sync_one', {pair: pair, metode: metode}, function(res){
      if(res && res.ok){
        $tr.find('.status .badge-status').removeClass('badge-belum').addClass('badge-draft').text('DRAFT');
        $tr.find('input[type=checkbox]').prop('checked', false).prop('disabled', true);
        $btn.replaceWith('<button class="btn btn-success btn-xs btn-finalkan"><i class="fa fa-check-circle"></i> Finalkan</button>');
        setFinalkanAllButton();
      } else {
        alert('Gagal sinkron: '+(res && res.msg ? res.msg : 'Unknown error'));
        $btn.prop('disabled', false).removeClass('is-loading');
      }
    }, 'json').fail(function(){
      alert('Gagal sinkron (koneksi).');
      $btn.prop('disabled', false).removeClass('is-loading');
    });
  });

  $(document).on('click','.btn-finalkan', function(){
    var $tr = $(this).closest('tr');
    var pair = ($tr.data('pair')||'').toString();
    var parts = pair.split('|'); var tgl = parts[0]||''; var kls = parts[1]||'';
    var $btn = $(this);
    $btn.prop('disabled', true);
    $.post('data_absensi.php?ajax=finalkan', {tgl: tgl, kls: kls}, function(res){
      if(res && res.ok){
        dt.row($tr).remove().draw(false);
        setFinalkanAllButton();
      } else {
        alert('Gagal finalkan.'+(res && res.msg ? ' ('+res.msg+')' : ''));
        $btn.prop('disabled', false);
      }
    }, 'json').fail(function(){
      alert('Gagal finalkan (koneksi).');
      $btn.prop('disabled', false);
    });
  });

  $('#formBulkSync').on('submit', function(e){
    var pairs = [];
    $('#tblPending tbody tr').each(function(){
      var $cb = $(this).find('input[type=checkbox]');
      if ($cb.length && $cb.is(':checked') && !$cb.prop('disabled')) {
        pairs.push($(this).data('pair'));
      }
    });
    if (pairs.length === 0) { return; }

    e.preventDefault();
    var metode = $('#metode').val()||'mayoritas';
    var total = pairs.length, done = 0, ok=0, err=0;
    showOverlay(total, 'Sinkron terpilih…');

    function step(){
      if (abortBulk){ hideOverlay(); return; }
      if (done >= total){ hideOverlay(); return location.reload(); }
      var pair = pairs[done];
      var label = 'Memproses '+(done+1)+' dari '+total+' ('+pair+')';
      updateOverlay(done, total, ok, err, label);

      $.post('data_absensi.php?ajax=sync_one', {pair: pair, metode: metode}, function(res){
        done++;
        if(res && res.ok){
          ok++;
          var $tr = $('#tblPending tbody tr[data-pair="'+pair.replace(/([.*+?^${}()|\[\]\/\\])/g, "\\$1")+'"]');
          $tr.find('.status .badge-status').removeClass('badge-belum').addClass('badge-draft').text('DRAFT');
          $tr.find('input[type=checkbox]').prop('checked', false).prop('disabled', true);
          var $btnSync = $tr.find('.btn-sync-one');
          if ($btnSync.length){
            $btnSync.replaceWith('<button class="btn btn-success btn-xs btn-finalkan"><i class="fa fa-check-circle"></i> Finalkan</button>');
          }
        } else { err++; }
        updateOverlay(done, total, ok, err, label);
        setTimeout(step, 120);
      }, 'json').fail(function(){
        done++; err++;
        updateOverlay(done, total, ok, err, label);
        setTimeout(step, 120);
      });
    }
    step();
  });

  $('#btnFinalkanAllDraft').on('click', function(){
    var rows = [];
    $('#tblPending tbody tr').each(function(){
      var $tr = $(this);
      var statusTxt = $tr.find('.status .badge-status').text().trim().toUpperCase();
      if (statusTxt==='DRAFT') rows.push($tr);
    });
    if (rows.length===0) { setFinalkanAllButton(); return; }

    showOverlay(rows.length, 'Memfinalkan seluruh DRAFT…');
    var done=0, ok=0, err=0;

    function step(){
      if (done>=rows.length){ hideOverlay(); setFinalkanAllButton(); return; }
      var $tr = $(rows[done]);
      var pair = ($tr.data('pair')||'').toString();
      var parts = pair.split('|'); var tgl = parts[0]||''; var kls = parts[1]||'';
      var label='Finalkan '+(done+1)+' dari '+rows.length+' ('+pair+')';
      updateOverlay(done, rows.length, ok, err, label);

      $.post('data_absensi.php?ajax=finalkan', {tgl: tgl, kls: kls}, function(res){
        done++;
        if(res && res.ok){
          ok++;
          dt.row($tr).remove().draw(false);
        } else { err++; }
        updateOverlay(done, rows.length, ok, err, label);
        setTimeout(step, 120);
      }, 'json').fail(function(){
        done++; err++;
        updateOverlay(done, rows.length, ok, err, label);
        setTimeout(step, 120);
      });
    }
    step();
  });

});
</script>

<?php include 'footer.php'; ?>
