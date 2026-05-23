<?php
// ==== Matikan mysqli exception (hindari HTTP 500) ====
if (function_exists('mysqli_report')) { @mysqli_report(MYSQLI_REPORT_OFF); }

/**
 * EPS — LEGER STS (PTS) per Kelas
 * v2025-10-17+sort (rev: center header vertical + full-row hover + neutral cell bg + SORT tiap kolom)
 * REVISI TERBARU: Full Support Multi-Semester (Filter UI, URL Param, Auto Deteksi, Sinkronisasi Export)
 */

include 'header.php'; // diasumsikan start session & $koneksi (mysqli)

// ===== Helpers =====
if (!function_exists('esc')) {
  function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('db_one_row')) {
  function db_one_row($db, $sql){
    $r = @mysqli_query($db, $sql);
    if($r && ($row = mysqli_fetch_assoc($r))){ @mysqli_free_result($r); return $row; }
    return null;
  }
}
if (!function_exists('db_all')) {
  function db_all($db, $sql){
    $out=[]; $r = @mysqli_query($db, $sql);
    if($r){ while($row = mysqli_fetch_assoc($r)){ $out[]=$row; } @mysqli_free_result($r); }
    return $out;
  }
}
if (!function_exists('db_val')) {
  function db_val($db, $sql, $default=null){
    $r = @mysqli_query($db, $sql);
    if(!$r) return $default;
    $row = mysqli_fetch_row($r);
    return $row ? $row[0] : $default;
  }
}
if (!function_exists('current_semester')) {
  function current_semester(){
    $m = (int)date('n'); // 1-12
    return ($m >= 7 && $m <= 12) ? 1 : 2; // Jul–Des = 1, Jan–Jun = 2
  }
}
if (!function_exists('table_exists')) {
  function table_exists($db, $name){
    if(!$db) return false;
    $name = @mysqli_real_escape_string($db, (string)$name);
    $r = @mysqli_query($db, "SHOW TABLES LIKE '{$name}'");
    return ($r && mysqli_num_rows($r) > 0);
  }
}
if (!function_exists('table_columns')) {
  function table_columns($db, $name){
    $cols=[]; if(!$db) return $cols;
    $name = @mysqli_real_escape_string($db, (string)$name);
    $r = @mysqli_query($db, "SHOW COLUMNS FROM `{$name}`");
    if($r){ while($row = mysqli_fetch_assoc($r)){ $cols[] = $row['Field']; } }
    return $cols;
  }
}
if (!function_exists('pick_col')) {
  function pick_col($cols, $cands){ foreach($cands as $c){ if(in_array($c,$cols,true)) return $c; } return null; }
}
if (!function_exists('get_user_id')) {
  function get_user_id(){
    foreach (['id','user_id','uid'] as $k){ if(isset($_SESSION[$k])) return (int)$_SESSION[$k]; }
    return 0;
  }
}

// ==== TA Aktif ====
$TA_ID = (int)db_val($koneksi, "SELECT ta_id FROM ta WHERE ta_status=1 ORDER BY ta_id DESC LIMIT 1", 0);
if ($TA_ID <= 0) $TA_ID = (int)db_val($koneksi, "SELECT ta_id FROM ta ORDER BY ta_id DESC LIMIT 1", 0);

// --- PENYEMPURNAAN SEMESTER: Prioritaskan GET parameter, fallback ke current_semester() ---
$default_semester_auto = current_semester();
$SEMESTER = isset($_GET['semester']) ? (int)$_GET['semester'] : $default_semester_auto;
if (!in_array($SEMESTER, [1, 2], true)) { $SEMESTER = $default_semester_auto; }
// ------------------------------------------------------------------------------------------

// ==== Tabel & kolom toleran ====
$HAS_KELAS       = table_exists($koneksi,'kelas');
$HAS_KELAS_SISWA = table_exists($koneksi,'kelas_siswa');
$HAS_SISWA       = table_exists($koneksi,'siswa');
$HAS_MAPEL       = table_exists($koneksi,'mapel');
$HAS_USER        = table_exists($koneksi,'user');
$HAS_PTS_SET     = table_exists($koneksi,'nilai_pts_set');
$HAS_PTS         = table_exists($koneksi,'nilai_pts');
$HAS_PENGAMPU    = table_exists($koneksi,'pengampu_mapel');

$colsKelas   = $HAS_KELAS ? table_columns($koneksi,'kelas') : [];
$colsKS      = $HAS_KELAS_SISWA ? table_columns($koneksi,'kelas_siswa') : [];
$colsSiswa   = $HAS_SISWA ? table_columns($koneksi,'siswa') : [];
$colsMapel   = $HAS_MAPEL ? table_columns($koneksi,'mapel') : [];
$colsUser    = $HAS_USER ? table_columns($koneksi,'user') : [];
$colsSet     = $HAS_PTS_SET ? table_columns($koneksi,'nilai_pts_set') : [];
$colsNilai   = $HAS_PTS ? table_columns($koneksi,'nilai_pts') : [];
$colsPeng    = $HAS_PENGAMPU ? table_columns($koneksi,'pengampu_mapel') : [];

$KELAS_ID    = pick_col($colsKelas, ['kelas_id','id_kelas','id']);
$KELAS_NAMA  = pick_col($colsKelas, ['kelas_nama','nama_kelas','kelas']);
$KELAS_TA    = pick_col($colsKelas, ['kelas_ta','ta_id','id_ta','tahun_ajaran_id']);

$KS_KELAS    = pick_col($colsKS, ['ks_kelas','kelas_id','id_kelas','kelas']);
$KS_SISWA    = pick_col($colsKS, ['ks_siswa','siswa_id','id_siswa']);

/* Siswa */
$SISWA_ID    = pick_col($colsSiswa, ['siswa_id','id','id_siswa']);
$SISWA_NAMA  = pick_col($colsSiswa, ['siswa_nama','nama_siswa','nama']);
$SISWA_NISN  = pick_col($colsSiswa, ['siswa_nisn','nisn']);
$SISWA_NIS   = pick_col($colsSiswa, ['siswa_nis','nis','no_induk']);

/* Mapel */
$MAPEL_ID    = pick_col($colsMapel, ['mapel_id','id_mapel','id']);
$MAPEL_NAMA  = pick_col($colsMapel, ['mapel_nama','nama_mapel','nama','mapel']);
$MAPEL_KODE  = pick_col($colsMapel, ['mapel_kode','kode_mapel','kode']);

/* User/Guru */
$USER_ID_COL = pick_col($colsUser, ['user_id','id','id_user']);
$USER_NAMA   = pick_col($colsUser, ['user_nama','nama','name']);

/* Set */
$SET_ID      = pick_col($colsSet, ['pts_set_id','set_id','id']);
$SET_TA      = pick_col($colsSet, ['ta_id','tahunajaran_id','tahun_ajaran_id','id_ta']);
$SET_SMT     = pick_col($colsSet, ['semester','smt']);
$SET_KELAS   = pick_col($colsSet, ['kelas_id','id_kelas','kelas']);
$SET_MAPEL   = pick_col($colsSet, ['mapel_id','id_mapel','mapel']);
$SET_GURU    = pick_col($colsSet, ['guru_user_id','guru_id','user_id','id_user']);

/* Nilai */
$NILAI_ID    = pick_col($colsNilai, ['pts_id','id']);
$NILAI_SET   = pick_col($colsNilai, ['pts_set_id','set_id']);
$NILAI_SISWA = pick_col($colsNilai, ['siswa_id','id_siswa']);
$NILAI_VAL   = pick_col($colsNilai, ['nilai','nilai_akhir','skor']);

/* Pengampu */
$P_TA        = pick_col($colsPeng, ['ta_id','tahunajaran_id','id_ta']);
$P_KELAS     = pick_col($colsPeng, ['kelas_id','id_kelas','kelas']);
$P_MAPEL     = pick_col($colsPeng, ['mapel_id','id_mapel','mapel']);
$P_GURU      = pick_col($colsPeng, ['guru_user_id','user_id','pengampu_user_id','guru_id','id_user']);

// ==== Daftar Kelas TA Aktif ====
$kelas = [];
if ($HAS_KELAS && $KELAS_ID && $KELAS_NAMA && $KELAS_TA){
  $kelas = db_all($koneksi, "SELECT `$KELAS_ID` AS id, `$KELAS_NAMA` AS nama
                             FROM `kelas` WHERE `$KELAS_TA`={$TA_ID}
                             ORDER BY `$KELAS_NAMA` ASC");
}
if (empty($kelas)) $kelas[] = ['id'=>0,'nama'=>'(Belum ada kelas untuk TA aktif)'];

// ==== Default kelas ====
$USER_LOGIN_ID = get_user_id();
function default_kelas_for_user($db, $TA_ID, $USER_ID, $kelasList, $HAS_KELAS){
  if (isset($_GET['kelas_id'])) return (int)$_GET['kelas_id'];
  if ($USER_ID>0){
    if (table_exists($db,'kelas_wali')){
      $kwCols = table_columns($db,'kelas_wali');
      $KW_KELAS = pick_col($kwCols, ['kelas_id','id_kelas','kelas']);
      $KW_USER  = pick_col($kwCols, ['wali_user_id','user_id','guru_user_id','wali_id','wali']);
      $KW_TA    = pick_col($kwCols, ['ta_id','tahunajaran_id','tahun_ajaran_id','id_ta','ta']);
      if($KW_KELAS && $KW_USER){
        $sql = "SELECT `$KW_KELAS` FROM `kelas_wali` WHERE `$KW_USER`={$USER_ID}";
        if($KW_TA) $sql .= " AND `$KW_TA`={$TA_ID}";
        $sql .= " ORDER BY `$KW_KELAS` DESC LIMIT 1";
        $wal = (int)db_val($db,$sql,0);
        if ($wal>0) return $wal;
      }
    }
    if ($HAS_KELAS){
      $kcols = table_columns($db,'kelas');
      $WALI_COL = pick_col($kcols, ['kelas_wali','wali_user_id','wali_id','guru_wali','wali']);
      $KELAS_ID = pick_col($kcols, ['kelas_id','id_kelas','id']);
      $KELAS_TA = pick_col($kcols, ['kelas_ta','ta_id','id_ta','tahun_ajaran_id']);
      if($WALI_COL && $KELAS_ID && $KELAS_TA){
        $wal = (int)db_val($db,"SELECT `$KELAS_ID` FROM `kelas`
                                WHERE `$KELAS_TA`={$TA_ID} AND `$WALI_COL`={$USER_ID}
                                ORDER BY `$KELAS_ID` DESC LIMIT 1",0);
        if ($wal>0) return $wal;
      }
    }
  }
  return (int)($kelasList[0]['id'] ?? 0);
}
$kelas_id = default_kelas_for_user($koneksi, $TA_ID, $USER_LOGIN_ID, $kelas, $HAS_KELAS);
$kelas_nama = '';
foreach($kelas as $k){ if((int)$k['id']===$kelas_id){ $kelas_nama = $k['nama']; break; } }
if ($kelas_nama===''){ $kelas_id = (int)$kelas[0]['id']; $kelas_nama = $kelas[0]['nama']; }

// ==== Siswa kelas ====
$siswa = [];
if ($HAS_KELAS_SISWA && $HAS_SISWA && $KS_KELAS && $KS_SISWA && $SISWA_ID && $SISWA_NAMA){
  $nisnSel = $SISWA_NISN ? ", s.`$SISWA_NISN` AS nisn" : ", NULL AS nisn";
  $nisSel  = $SISWA_NIS  ? ", s.`$SISWA_NIS`  AS nis"  : ", NULL AS nis";
  $siswa = db_all($koneksi, "SELECT s.`$SISWA_ID` AS id, s.`$SISWA_NAMA` AS nama $nisnSel $nisSel
                             FROM `kelas_siswa` ks
                             JOIN `siswa` s ON s.`$SISWA_ID`=ks.`$KS_SISWA`
                             WHERE ks.`$KS_KELAS`={$kelas_id}
                             ORDER BY s.`$SISWA_NAMA` ASC");
}

// ==== Mapel (tampilkan SEMUA mapel untuk kelas ini) ====
$mapelCols = []; // ['mapel_id','set_id','mapel_nama','mapel_kode','guru_id','guru_nama']
$setLatestByMapel = [];
if ($HAS_PTS_SET && $SET_ID && $SET_TA && $SET_SMT && $SET_KELAS && $SET_MAPEL){
  $rowsSet = db_all($koneksi, "SELECT `$SET_MAPEL` AS mapel_id, MAX(`$SET_ID`) AS set_id
                                FROM `nilai_pts_set`
                                WHERE `$SET_TA`={$TA_ID} AND `$SET_SMT`={$SEMESTER} AND `$SET_KELAS`={$kelas_id}
                                GROUP BY `$SET_MAPEL`");
  foreach($rowsSet as $r){ $setLatestByMapel[(int)$r['mapel_id']] = (int)$r['set_id']; }
}
$mapelCandidates = array_keys($setLatestByMapel);
if ($HAS_PENGAMPU && $P_TA && $P_KELAS && $P_MAPEL){
  $rowsPg = db_all($koneksi, "SELECT DISTINCT `$P_MAPEL` AS mapel_id
                               FROM `pengampu_mapel`
                               WHERE `$P_TA`={$TA_ID} AND `$P_KELAS`={$kelas_id}");
  foreach($rowsPg as $r){ $mid = (int)$r['mapel_id']; if(!in_array($mid,$mapelCandidates,true)) $mapelCandidates[]=$mid; }
}
if (empty($mapelCandidates) && $HAS_MAPEL && $MAPEL_ID){
  $rowsAllM = db_all($koneksi, "SELECT `$MAPEL_ID` AS mapel_id FROM `mapel` ORDER BY `$MAPEL_ID` ASC");
  foreach($rowsAllM as $r){ $mapelCandidates[] = (int)$r['mapel_id']; }
}
if (!empty($mapelCandidates)){
  $inIds = implode(',', array_map('intval',$mapelCandidates));
  $infoM = [];
  if ($HAS_MAPEL && $MAPEL_ID && $MAPEL_NAMA){
    $qInfo = db_all($koneksi, "SELECT `$MAPEL_ID` AS id, `$MAPEL_NAMA` AS n, ".($MAPEL_KODE?"`$MAPEL_KODE` AS k":"'' AS k")." FROM `mapel` WHERE `$MAPEL_ID` IN ($inIds)");
    foreach($qInfo as $r){ $infoM[(int)$r['id']] = ['n'=>$r['n'], 'k'=>$r['k']]; }
  }
  sort($mapelCandidates);
  foreach($mapelCandidates as $mid){
    $sid = (int)($setLatestByMapel[$mid] ?? 0);
    $guru_id = 0;
    if ($sid>0){
      $setRow = db_one_row($koneksi, "SELECT * FROM `nilai_pts_set` WHERE `$SET_ID`={$sid} LIMIT 1");
      if($setRow && $SET_GURU && !empty($setRow[$SET_GURU])) $guru_id = (int)$setRow[$SET_GURU];
    }
    if ($guru_id<=0 && $HAS_PENGAMPU && $P_TA && $P_KELAS && $P_MAPEL && $P_GURU){
      $gid = (int)db_val($koneksi, "SELECT `$P_GURU` FROM `pengampu_mapel`
                                    WHERE `$P_TA`={$TA_ID} AND `$P_KELAS`={$kelas_id} AND `$P_MAPEL`={$mid}
                                    ORDER BY 1 DESC LIMIT 1", 0);
      if ($gid>0) $guru_id = $gid;
    }
    $guru_nama = '';
    if ($guru_id>0 && $HAS_USER && $USER_ID_COL && $USER_NAMA){
      $guru_nama = (string)db_val($koneksi, "SELECT `$USER_NAMA` FROM `user` WHERE `$USER_ID_COL`={$guru_id} LIMIT 1", '');
    }
    $mn = $infoM[$mid]['n'] ?? ('Mapel #'.$mid);
    $mk = $infoM[$mid]['k'] ?? '';
    $mapelCols[] = [
      'mapel_id'=>$mid,
      'set_id'=>$sid,
      'mapel_nama'=>$mn,
      'mapel_kode'=>$mk ?: ('M'.$mid),
      'guru_id'=>$guru_id,
      'guru_nama'=>$guru_nama ?: '(Pengampu?)'
    ];
  }
}

// ==== Nilai untuk semua set (pivot) ====
$nilaiMap = []; // [siswa_id][mapel_id] = nilai (int/null)
if (!empty($mapelCols) && $HAS_PTS && $NILAI_SET && $NILAI_SISWA && $NILAI_VAL){
  $setIds = array_values(array_unique(array_filter(array_map(function($c){ return (int)$c['set_id']; }, $mapelCols))));
  if ($setIds){
    $in = implode(',', array_map('intval',$setIds));
    $rows = db_all($koneksi, "SELECT `$NILAI_SET` AS set_id, `$NILAI_SISWA` AS siswa_id, `$NILAI_VAL` AS v
                               FROM `nilai_pts`
                               WHERE `$NILAI_SET` IN ($in)");
    $setToMapel = []; foreach($mapelCols as $c){ if((int)$c['set_id']>0){ $setToMapel[(int)$c['set_id']] = (int)$c['mapel_id']; } }
    foreach($rows as $r){
      $sid = (int)$r['siswa_id']; $set = (int)$r['set_id']; $val = $r['v'];
      $mid = $setToMapel[$set] ?? 0;
      if($sid && $mid){ $nilaiMap[$sid][$mid] = (is_null($val) || $val==='') ? null : (int)round((float)$val); }
    }
  }
}

// ==== Progres ====
$totalCell = count($siswa) * max(1, count($mapelCols));
$filled=0; foreach($siswa as $s){ foreach($mapelCols as $c){ $v = $nilaiMap[$s['id']][$c['mapel_id']] ?? null; if($v!==null && $v!==''){ $filled++; } } }
$percent = $totalCell? round(($filled/$totalCell)*100) : 0;

// ==== Agregasi per siswa (Jumlah/Top-3) ====
$rowAgg = []; // [sid] => ['sum'=>x,'cnt'=>y]
foreach($siswa as $s){
  $sid=(int)$s['id']; $sum=0; $cnt=0;
  foreach($mapelCols as $c){
    $mid=(int)$c['mapel_id']; $v = $nilaiMap[$sid][$mid] ?? null;
    if($v!==null && $v!==''){ $sum += (int)$v; $cnt++; }
  }
  $rowAgg[$sid] = ['sum'=>$sum,'cnt'=>$cnt];
}
$sumVals = array_map(function($a){ return (int)$a['sum']; }, $rowAgg);
rsort($sumVals);
$top1 = $sumVals[0] ?? -1;
$top2 = null; $top3 = null;
foreach($sumVals as $v){ if($v!==$top1){ $top2=$v; break; } }
if($top2===null) $top2=-1;
foreach($sumVals as $v){ if($v!==$top1 && $v!==$top2){ $top3=$v; break; } }
if($top3===null) $top3=-1;

// ==== Data untuk export XLSX ====
$exportRows = []; $noTmp=1;
foreach($siswa as $s){
  $sid = (int)$s['id'];
  $row = ['no'=>$noTmp++, 'nama'=>$s['nama'], 'nisn'=>($s['nisn'] ?? ''), 'nis'=>($s['nis'] ?? '')];
  $sum=0; $cnt=0;
  foreach($mapelCols as $c){
    $mid=(int)$c['mapel_id'];
    $v = $nilaiMap[$sid][$mid] ?? null;
    $row['m'.$mid] = ($v!==null && $v!=='') ? (int)$v : '';
    if($v!==null && $v!==''){ $sum+=(int)$v; $cnt++; }
  }
  $row['jumlah'] = $sum;
  $row['rata']   = $cnt? round($sum/$cnt) : '';
  $exportRows[] = $row;
}
$exportCols = array_map(function($c){ return ['id'=>$c['mapel_id'],'kode'=>$c['mapel_kode']]; }, $mapelCols);
?>
<style>
/* Animasi */
@keyframes epsFadeSlideUp { 0% { opacity:0; transform: translateY(8px); } 100% { opacity:1; transform: translateY(0);} }
.eps-animate-intro { animation: epsFadeSlideUp .45s ease-out both; }

/* Tabel & sticky */
.leger-wrap{ border-top:1px solid #e6efff; overflow:visible; }
.leger-table{ width:100%; }
.leger-table thead th{
  position: sticky; top: 0; z-index: 4;
  background:#0B57D0; color:#fff;
  vertical-align: middle !important;
  text-align: center;
  height: 48px;
  user-select:none;
}
.leger-table thead th.sticky-name{ text-align:left; } /* kolom nama tetap kiri */
.leger-table th.sub{ background:#094ac0; font-weight:700; }

/* HANYA kolom Nama sticky kiri */
.leger-table th.sticky-name{ position: sticky; left: 0; z-index: 5; background:#0B57D0; color:#fff; }
.leger-table td.sticky-name{ position: sticky; left: 0; z-index: 2; background:#fff; }

/* Perkecil kolom nama di desktop (~20%) */
@media (min-width: 992px){
  .col-name{ min-width:208px; width:208px; }
}

/* Padat kolom mapel */
.col-mapel-head{ min-width:58px; max-width:60px; }
.cell-nilai.col-mapel{ min-width:58px; max-width:60px; }

/* Sel, center vertical semua sel */
.leger-table td, .leger-table th{ white-space: nowrap; vertical-align: middle !important; }
.leger-table thead th, .leger-table tbody td{ padding-top:10px; padding-bottom:10px; }

/* Hover baris kontras (seluruh baris ikut, termasuk sticky) */
.leger-table tbody tr:hover > td{ background:#E6F0FF !important; }
.leger-table tbody tr:hover > td.sticky-name{ background:#E6F0FF !important; }

/* Badge & chip */
.badge-soft{ display:inline-block; padding:6px 10px; border-radius:999px; border:1px solid #D7E6FF; background:#F0F6FF; color:#0B57D0; font-weight:800; }

/* Sel nilai: netral background, hanya warna angka yang berubah */
.cell-nilai{ text-align:center; font-weight:700; border-left:1px solid #eef3ff; border-right:1px solid #eef3ff; background:transparent; }
.cell-nilai.good{ color:#047857; background:transparent; }
.cell-nilai.bad{ color:#B91C1C; background:transparent; }
.cell-nilai.nil{ color:#9CA3AF; background:transparent; }

/* Kolom Jumlah */
.cell-jumlah{ text-align:center; font-weight:800; }

/* Toolbar */
.leger-tools{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.btn-theme-primary{
  background:linear-gradient(90deg,#2563EB,#0EA5E9);
  color:#fff; border:none; border-radius:10px; padding:8px 12px; font-weight:700;
  box-shadow:0 6px 14px rgba(37,99,235,.18);
}
.btn-theme-primary:hover{ transform:translateY(-1px); box-shadow:0 10px 18px rgba(14,165,233,.22); }
.btn-theme-ghost{ background:#fff; color:#0B57D0; border:1px solid #BFD7FF; border-radius:10px; padding:8px 12px; font-weight:700; }
.input-filter{ border:1px solid #dbe3ff; border-radius:10px; padding:8px 10px; outline:none; }

/* Header Section seragam */
.content-header h1{ display:flex; align-items:center; gap:10px; font-weight:800; margin:0; }
.title-ico{ width:36px;height:36px;border-radius:10px;background:#E6EFFF;border:1px solid #D7E6FF;color:#0B57D0; display:flex;align-items:center;justify-content:center; }

/* Mapel header text */
.mapel-head{ font-weight:800; text-align:center; }

/* === SORTABLE HEADER === */
.leger-table thead th.sortable{ cursor:pointer; }
.sort-wrap{ display:inline-flex; align-items:center; gap:6px; }
.sort-label{ font-weight:800; }
.sort-caret{ font-size:12px; line-height:1; opacity:.9; }
.leger-table thead th.sort-asc .sort-caret{ content:""; }
.leger-table thead th.sort-desc .sort-caret{ content:""; }

/* Print */
@media print{
  .no-print{ display:none !important; }
  .leger-table thead th{ position: sticky; top: 0; }
  .content-header, .box, .box-header, .box-footer{ box-shadow:none !important; }
}
</style>

<div class="content-wrapper">
  <section class="content-header eps-animate-intro" style="margin-bottom:8px">
    <h1>
      <span class="title-ico"><i class="fa fa-table"></i></span>
      <span>LEGER STS (PTS) — <?php echo esc($kelas_nama); ?></span>
      <small class="label bg-light-blue" style="margin-left:8px;border-radius:999px;">TA Aktif</small>
    </h1>
  </section>

  <section class="content eps-animate-intro">
    <div class="box" style="border-radius:12px; overflow:visible;">
      <div class="box-header with-border" style="background:linear-gradient(90deg,#1976ff,#0ea5e9);color:#fff">
        <h3 class="box-title" style="font-weight:800"><i class="fa fa-sliders"></i> Filter & Aksi</h3>
      </div>
      <div class="box-body" style="background:#f8fbff;border-top:1px solid #e6efff">
        <div class="row">
          
          <div class="col-lg-3 col-md-4 col-sm-12">
            <div class="form-group">
              <label><i class="fa fa-university"></i> Pilih Kelas</label>
              <div class="input-group">
                <input id="kelasPicker" class="form-control" placeholder="Klik tombol pilih…" readonly value="<?php echo esc($kelas_nama); ?>" style="border-radius:10px 0 0 10px;">
                <span class="input-group-btn">
                  <button id="btnPickKelas" class="btn btn-default" type="button" style="border-radius:0 10px 10px 0;"><i class="fa fa-search"></i> Pilih</button>
                </span>
              </div>
              <select id="selKelas" class="form-control" style="display:none">
                <?php foreach($kelas as $k): ?>
                  <option value="<?php echo (int)$k['id']; ?>" <?php echo ((int)$k['id']===$kelas_id?'selected':''); ?>><?php echo esc($k['nama']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="col-lg-3 col-md-3 col-sm-12">
            <div class="form-group">
              <label><i class="fa fa-calendar-check-o"></i> Pilih Semester</label>
              <select id="semesterFilter" class="form-control" style="border-radius:10px; height:auto; padding:8px 10px;">
                <option value="1" <?php echo $SEMESTER == 1 ? 'selected' : ''; ?>>Semester 1 (Ganjil)</option>
                <option value="2" <?php echo $SEMESTER == 2 ? 'selected' : ''; ?>>Semester 2 (Genap)</option>
              </select>
            </div>
          </div>

          <div class="col-lg-6 col-md-5 col-sm-12">
            <div class="leger-tools" style="margin-top:24px; justify-content: flex-end;">
              <span class="badge-soft">Progres terisi: <?php echo $filled; ?>/<?php echo $totalCell; ?> (<?php echo $percent; ?>%)</span>
              <input id="filterInput" class="input-filter" placeholder="Cari siswa…" />
              <button id="btnExportXLSX" class="btn btn-theme-ghost no-print"><i class="fa fa-file-excel-o"></i> XLSX</button>
              <button id="btnPrint"      class="btn btn-theme-primary no-print"><i class="fa fa-print"></i> Cetak</button>
            </div>
          </div>

        </div>
      </div>

      <div class="box-body leger-wrap">
        <?php if(empty($mapelCols)): ?>
          <div class="alert alert-info" style="margin:0">
            Belum ada <b>Mapel/Pengampu</b> untuk kelas ini pada Semester <?php echo $SEMESTER; ?>.
          </div>
        <?php else: ?>
          <div class="table-responsive" style="overflow-x:auto; -webkit-overflow-scrolling:touch;">
            <table id="legerTable" class="table table-bordered table-striped table-hover leger-table">
              <thead>
                <tr>
                  <th style="width:56px;" class="sortable" data-type="num" aria-sort="none">
                    <span class="sort-wrap"><span class="sort-label">No</span><span class="sort-caret">⇅</span></span>
                  </th>

                  <th class="sticky-name col-name sortable" data-type="text" aria-sort="none">
                    <span class="sort-wrap">
                      <span class="sort-label">Nama Siswa</span>
                      <span class="sort-caret">⇅</span>
                    </span><br>
                    <span style="font-weight:400; font-size:11px; opacity:.85">NISN/NIS</span>
                  </th>

                  <?php foreach($mapelCols as $col): ?>
                    <th class="col-mapel-head sortable" data-type="num" title="<?php echo esc($col['mapel_nama'].' — '.$col['guru_nama']); ?>" aria-sort="none">
                      <div class="mapel-head sort-wrap">
                        <span class="sort-label"><?php echo esc($col['mapel_kode']); ?></span>
                        <span class="sort-caret">⇅</span>
                      </div>
                    </th>
                  <?php endforeach; ?>

                  <th class="sortable" data-type="num" aria-sort="none">
                    <span class="sort-wrap"><span class="sort-label">Jumlah</span><span class="sort-caret">⇅</span></span>
                  </th>
                  <th class="sortable" data-type="num" aria-sort="none">
                    <span class="sort-wrap"><span class="sort-label">Rata-rata</span><span class="sort-caret">⇅</span></span>
                  </th>
                </tr>
              </thead>
              <tbody>
                <?php
                  $no=1;
                  foreach($siswa as $s){
                    $sid=(int)$s['id'];
                    $nisStr = '';
                    if(!empty($s['nisn'])) $nisStr = esc($s['nisn']);
                    if(!empty($s['nis']))  $nisStr = $nisStr ? $nisStr.' / '.esc($s['nis']) : esc($s['nis']);
                    if(!$nisStr) $nisStr = '-';

                    $sum=0; $cnt=0;
                    echo '<tr data-name="'.esc(strtolower($s['nama'])).'" data-nisn="'.esc($s['nisn'] ?? '').'" data-nis="'.esc($s['nis'] ?? '').'">';
                    echo '<td class="text-center">'.$no++.'</td>';
                    echo '<td class="sticky-name"><b>'.esc($s['nama']).'</b><br><small class="text-muted">'.$nisStr.'</small></td>';

                    foreach($mapelCols as $col){
                      $mid = (int)$col['mapel_id'];
                      $v = $nilaiMap[$sid][$mid] ?? null;
                      $cls = 'nil';
                      if($v!==null && $v!==''){
                        $sum += (int)$v; $cnt++;
                        $cls = ((int)$v >= 75) ? 'good' : 'bad';
                      }
                      $label = ($v!==null && $v!=='') ? (int)$v : '–';
                      echo '<td class="cell-nilai col-mapel '.$cls.'" title="Nilai: '.($v!==null?$v:'-').'">'.$label.'</td>';
                    }

                    $avg = $cnt ? round($sum/$cnt) : '-';
                    $medal = '';
                    if ($rowAgg[$sid]['sum'] === $top1 && $top1>=0){ $medal = '🥇 '; }
                    elseif ($rowAgg[$sid]['sum'] === $top2 && $top2>=0){ $medal = '🥈 '; }
                    elseif ($rowAgg[$sid]['sum'] === $top3 && $top3>=0){ $medal = '🥉 '; }

                    echo '<td class="cell-jumlah" title="Total nilai">'.$medal.$sum.'</td>';
                    echo '<td class="cell-nilai '.($avg!=='-'?'good':'nil').'">'.$avg.'</td>';
                    echo '</tr>';
                  }
                ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <div class="box-footer" style="padding:12px 16px;background:#fafcff;border-top:1px solid #eef3ff">
        <div style="font-size:12px;color:#5b6b8a">
          <b>Keterangan:</b>
          <span class="label label-success" style="margin-left:6px">≥ 75</span>
          <span class="label label-danger"  style="margin-left:6px">&lt; 75</span>
          <span class="label label-default" style="margin-left:6px">Kosong</span>
          <span class="label" style="margin-left:6px;background:#fff;color:#444;border:1px solid #ddd">🥇 Top 1</span>
          <span class="label" style="margin-left:6px;background:#fff;color:#444;border:1px solid #ddd">🥈 Top 2</span>
          <span class="label" style="margin-left:6px;background:#fff;color:#444;border:1px solid #ddd">🥉 Top 3</span>
        </div>
      </div>
    </div>
  </section>
</div>

<div class="modal fade" id="kelasPickModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" style="border-radius:12px">
      <div class="modal-header" style="background:#0B57D0;color:#fff;border-top-left-radius:12px;border-top-right-radius:12px">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff;opacity:1"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Pilih Kelas</h4>
      </div>
      <div class="modal-body">
        <input id="kelasSearchModal" class="form-control" placeholder="Ketik untuk mencari…" style="margin-bottom:10px">
        <div id="kelasListModal" class="list-group" style="max-height:60vh;overflow:auto;margin-bottom:0"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-default" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script>
(function(){
  var $ = window.jQuery;

  // ====== Data utk XLSX ======
  // Karena LEG_META.semester ditarik dari $SEMESTER PHP, hasil export akan otomatis sesuai semester yang difilter!
  var LEG_META = {
    kelas: <?php echo json_encode($kelas_nama, JSON_UNESCAPED_UNICODE); ?>,
    semester: <?php echo (int)$SEMESTER; ?>,
    cols: <?php echo json_encode($exportCols, JSON_UNESCAPED_UNICODE); ?>
  };
  var LEG_ROWS = <?php echo json_encode($exportRows, JSON_UNESCAPED_UNICODE); ?>;

  // ====== Picker Kelas & Semester ======
  var kelasData = <?php echo json_encode($kelas, JSON_UNESCAPED_UNICODE); ?>;
  var sel = document.getElementById('selKelas');
  var inp = document.getElementById('kelasPicker');
  var selSmt = document.getElementById('semesterFilter');

  function escapeHtml(s){ return (''+s).replace(/[&<>"']/g, function(m){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'})[m];}).replace(/'/g,'&#39;'); }
  function rebuildList(filter){
    var list = document.getElementById('kelasListModal');
    var q = (filter||'').toLowerCase().trim();
    var html = '';
    kelasData.forEach(function(it){
      var name = String(it.nama||'');
      if(q && name.toLowerCase().indexOf(q) < 0) return;
      html += '<a class="list-group-item pickable" data-id="'+it.id+'" data-name="'+escapeHtml(name)+'">'+escapeHtml(name)+'</a>';
    });
    if(!html) html = '<div class="list-group-item text-muted">Tidak ada data.</div>';
    list.innerHTML = html;
  }
  document.getElementById('btnPickKelas').addEventListener('click', function(){
    rebuildList('');
    if (window.jQuery){ $('#kelasPickModal').modal('show'); setTimeout(function(){ var s=document.getElementById('kelasSearchModal'); if(s) s.focus(); }, 300); }
  });
  document.getElementById('kelasSearchModal').addEventListener('input', function(){ rebuildList(this.value); });
  
  // Reload URL saat Modal Kelas diKlik
  document.getElementById('kelasListModal').addEventListener('click', function(e){
    var t = e.target; while(t && !t.classList.contains('pickable')) t = t.parentElement;
    if(!t) return; var id = t.getAttribute('data-id'); var name = t.getAttribute('data-name');
    if(sel) sel.value = String(id); if(inp) inp.value = name || '';
    if (window.jQuery) $('#kelasPickModal').modal('hide');
    
    var qs = new URLSearchParams(window.location.search); 
    qs.set('kelas_id', id);
    if(selSmt) qs.set('semester', selSmt.value);
    window.location.href = window.location.pathname + '?' + qs.toString();
  });

  // Reload URL saat Dropdown Semester diganti
  if(selSmt){
    selSmt.addEventListener('change', function(){
      var qs = new URLSearchParams(window.location.search);
      var kid = sel ? sel.value : '';
      if (kid) qs.set('kelas_id', kid);
      qs.set('semester', this.value);
      window.location.href = window.location.pathname + '?' + qs.toString();
    });
  }

  // ====== Filter siswa (live) ======
  var filterInput = document.getElementById('filterInput');
  if(filterInput){
    filterInput.addEventListener('input', function(){
      var q = (this.value||'').toLowerCase().trim();
      var rows = document.querySelectorAll('#legerTable tbody tr');
      [].forEach.call(rows, function(tr){
        var nameCell = tr.querySelector('td.sticky-name');
        var n = nameCell ? (nameCell.innerText||'').toLowerCase() : (tr.getAttribute('data-name')||'');
        tr.style.display = (!q || n.indexOf(q)>=0) ? '' : 'none';
      });
    });
  }

  // ====== Export XLSX ======
  document.getElementById('btnExportXLSX').addEventListener('click', function(){
    if (typeof XLSX === 'undefined'){ alert('Library XLSX belum ter-load. Coba ulangi.'); return; }
    var wb = XLSX.utils.book_new();

    // Sheet 1: LEGER STS (Nama, NISN, NIS dipisah, kolom mapel pakai kode)
    var header = ['No','Nama Siswa','NISN','NIS'];
    LEG_META.cols.forEach(function(c){ header.push(c.kode); });
    header.push('Jumlah','Rata-rata');

    var data = [header];
    LEG_ROWS.forEach(function(r){
      var row = [r.no, r.nama || '', r.nisn || '', r.nis || ''];
      LEG_META.cols.forEach(function(c){ row.push(r['m'+c.id] === '' ? '' : Number(r['m'+c.id])); });
      row.push(Number(r.jumlah || 0), r.rata === '' ? '' : Number(r.rata));
      data.push(row);
    });

    var ws1 = XLSX.utils.aoa_to_sheet(data);
    XLSX.utils.book_append_sheet(wb, ws1, 'LEGER STS SMT '+LEG_META.semester);

    XLSX.writeFile(wb, 'LEGER_STS_'+(LEG_META.kelas||'KELAS')+'_Smt'+LEG_META.semester+'.xlsx');
  });

  // ====== Print ======
  document.getElementById('btnPrint').addEventListener('click', function(){ window.print(); });

  // ====== SORTING per kolom ======
  (function enableTableSorting(){
    var table = document.getElementById('legerTable');
    if(!table) return;

    var theadTh = table.querySelectorAll('thead th');
    var sortState = {}; // {index: 'asc'|'desc'}

    // Tambah handler click di semua TH
    [].forEach.call(theadTh, function(th, index){
      if (!th.classList.contains('sortable')) return;
      th.addEventListener('click', function(){
        var type = th.getAttribute('data-type') || inferType(index, th);
        var dir  = (sortState[index] === 'asc') ? 'desc' : 'asc';
        sortBy(index, type, dir);
        updateIndicators(index, dir);
        sortState = {}; sortState[index] = dir; // reset state kolom lain
      });
    });

    function inferType(i, th){
      if (th && th.classList.contains('sticky-name')) return 'text';
      return (i === 0) ? 'num' : 'num';
    }

    function cellVal(tr, i, type){
      var td = tr.children[i];
      if(!td) return type==='num' ? NaN : '';
      var t  = td.innerText || td.textContent || '';
      if (type === 'num'){
        // Hilangkan medal & teks non-digit
        var num = parseFloat(String(t).replace(/[^\d.-]+/g,''));
        return isNaN(num) ? -Infinity : num;
      }
      return String(t).trim().toLowerCase();
    }

    function sortBy(index, type, dir){
      var tbody = table.querySelector('tbody');
      var rows  = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
      rows.sort(function(a, b){
        var av = cellVal(a, index, type);
        var bv = cellVal(b, index, type);
        if (type === 'num'){
          return (dir === 'asc') ? (av - bv) : (bv - av);
        } else {
          return (dir === 'asc') ? (''+av).localeCompare(''+bv) : (''+bv).localeCompare(''+av);
        }
      });
      rows.forEach(function(r){ tbody.appendChild(r); });
      renumberNo();
    }

    function renumberNo(){
      var i=1;
      [].forEach.call(table.querySelectorAll('tbody tr'), function(tr){
        var c = tr.children[0];
        if(c){ c.textContent = i++; }
      });
    }

    function updateIndicators(activeIndex, dir){
      [].forEach.call(theadTh, function(th, i){
        th.classList.remove('sort-asc','sort-desc');
        th.setAttribute('aria-sort','none');
        var caret = th.querySelector('.sort-caret');
        if (caret) caret.textContent = '⇅';
        if (i === activeIndex){
          th.classList.add(dir === 'asc' ? 'sort-asc' : 'sort-desc');
          th.setAttribute('aria-sort', dir === 'asc' ? 'ascending' : 'descending');
          var c = th.querySelector('.sort-caret');
          if (c) c.textContent = (dir === 'asc') ? '▲' : '▼';
        }
      });
    }
  })();

})();
</script>

<?php include 'footer.php'; ?>