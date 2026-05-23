<?php
// admin/rekap_kelas.php (REVISI)
// Monitoring Absensi Kelas — % Input & % Hadir per Kelas
// - Default mulai_efektif = 21-07-2025
// - Mode "Bulanan" DIHAPUS (tersisa Semester & Kustom)
// - REVISI: libur nasional + kalender_libur (via view_non_efektif) jadi pengurang hari efektif saat fallback
// - REVISI: I & %Hadir hanya menghitung tanggal efektif
// - Tambahan: deteksi input di hari non-efektif
// - Tambahan (permintaan sebelumnya):
//   1) Panel "Input di Hari Non-Efektif" diberi pagination+sorting (default 5 baris)
//   2) Panel "Tanggal Libur (Sen–Jum)" di-rename jadi "Tanggal Libur" (filter hari kerja tetap berlaku)
//   3) Panel kanan dipoles: warna soft, icon/badge
// - Tambahan (permintaan terbaru):
//   • Semua panel kanan bernomor (ordered list) dengan format:
//     - Top/Bottom: Kelas — (I/E), %
//     - Tgl terbanyak/tersedikit: dd/mm/yyyy — X kelas
//     - Tanggal Libur: dd/mm/yyyy — keterangan [badge tipe]; background merah soft, judul merah, teks item hitam
// - REVISI TERBARU: Auto-deteksi semester default berdasarkan bulan berjalan (Jan-Jun = Sem 2, Jul-Des = Sem 1)

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../koneksi.php';

function _get($k,$d=null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function escs($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function i($v){ return (int)$v; }
function valid_ymd($s){ return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$s); }
function table_exists($koneksi,$name){
  $res = @mysqli_query($koneksi,"SHOW TABLES LIKE '".mysqli_real_escape_string($koneksi,$name)."'");
  return $res && mysqli_num_rows($res)>0;
}
function fmt_dmy($ymd){ if(!$ymd) return '-'; $ts=strtotime($ymd); return $ts?date('d/m/Y',$ts):escs($ymd); }
function fmt_dmy_his($dt){ if(!$dt) return '-'; $ts=strtotime($dt); return $ts?date('d/m/Y H:i:s',$ts):escs($dt); }

$today = date('Y-m-d');

// ===== Ambil TA aktif & pilihan =====
$ta_aktif = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT ta_id, ta_nama FROM ta WHERE ta_status=1 ORDER BY ta_id DESC LIMIT 1"));
$TA = isset($_GET['ta']) ? i($_GET['ta']) : i($ta_aktif['ta_id'] ?? 0);

// Daftar TA
$tas = mysqli_query($koneksi,"SELECT ta_id, ta_nama FROM ta ORDER BY ta_id DESC");

// ======= PARAMETER FILTER =======
$mode        = _get('mode', 'semester');  // semester|kustom

// --- PENYEMPURNAAN: Deteksi otomatis semester default ---
// Jika bulan 1 s/d 6 (Jan-Jun) maka default Semester 2. Jika 7 s/d 12 (Jul-Des) maka default Semester 1.
$bulanBerjalan = (int)date('n');
$defaultSemester = ($bulanBerjalan >= 1 && $bulanBerjalan <= 6) ? 2 : 1;
$semester    = isset($_GET['semester']) ? i($_GET['semester']) : $defaultSemester;
// --------------------------------------------------------

$kustom_awal = _get('awal','');
$kustom_akhir= _get('akhir','');
$hari_sekolah= (_get('hari_sekolah','5') === '6') ? 6 : 5;
$kelas_id    = isset($_GET['kelas']) ? i($_GET['kelas']) : (isset($_GET['kelas_id'])? i($_GET['kelas_id']) : 0);
$only_final  = i(_get('only_final', 1));
$mulai_efektif = _get('mulai_efektif','2025-07-21');

// ===== Ambil nama TA untuk patok semester =====
$ta_row = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT ta_nama FROM ta WHERE ta_id=$TA"));
$ta_nama = trim($ta_row['ta_nama'] ?? '');
preg_match('/(\d{4})\D+(\d{4})/',$ta_nama,$m);
$y1 = isset($m[1]) ? (int)$m[1] : (int)date('Y');
$y2 = isset($m[2]) ? (int)$m[2] : $y1+1;

// ===== Batas semester =====
if($semester==2){ $semStart="$y2-01-01"; $semEnd="$y2-06-30"; }
else { $semStart="$y1-07-01"; $semEnd="$y1-12-31"; }

// ===== Otomatisasi + clamp mulai_efektif =====
$mulaiEf = $mulai_efektif;
$upperBound = ($today < $semEnd) ? $today : $semEnd;

if(!valid_ymd($mulaiEf)){
  $mulaiEf = $semStart;
  if (table_exists($koneksi,'hari_efektif')) {
    $qMin = mysqli_query($koneksi,"
      SELECT MIN(tanggal) AS m
      FROM hari_efektif
      WHERE ta_id=$TA AND is_efektif=1
        AND tanggal BETWEEN '".mysqli_real_escape_string($koneksi,$semStart)."' AND '".mysqli_real_escape_string($koneksi,$upperBound)."'
    ");
    if($qMin && ($rowMin=mysqli_fetch_assoc($qMin)) && $rowMin['m']){
      $mulaiEf = $rowMin['m'];
    }
  }
}
if($mulaiEf < $semStart) $mulaiEf = $semStart;
if($mulaiEf > $upperBound) $mulaiEf = $upperBound;

// ===== Tentukan rentang awal..akhir =====
if($mode==='kustom' && valid_ymd($kustom_awal) && valid_ymd($kustom_akhir)){
  $awal = $kustom_awal; $akhir= $kustom_akhir;
}else{
  $awal = $mulaiEf; $akhir= $upperBound;
}
if($awal > $akhir){ $akhir = $awal; }

$dari   = $awal;
$sampai = $akhir;

// ===== Bentuk list Hari Efektif
$efektifDates = [];
$has_hari_efektif = false;

if (table_exists($koneksi,'hari_efektif')) {
  $qhe = mysqli_query($koneksi,
    "SELECT tanggal FROM hari_efektif
     WHERE ta_id=$TA AND is_efektif=1
       AND tanggal BETWEEN '$dari' AND '$sampai'
     ORDER BY tanggal");
  if ($qhe && mysqli_num_rows($qhe)>0) {
    $has_hari_efektif = true;
    while($r=mysqli_fetch_row($qhe)){ $efektifDates[]=$r[0]; }
  }
}

if (!$has_hari_efektif) {
  // non-efektif dari view_non_efektif
  $non = [];
  $qne = mysqli_query($koneksi,"
    SELECT tgl
    FROM view_non_efektif
    WHERE tgl BETWEEN '".mysqli_real_escape_string($koneksi,$dari)."' AND '".mysqli_real_escape_string($koneksi,$sampai)."'
      AND (ta_id IS NULL OR ta_id=$TA)
  ");
  while($r=mysqli_fetch_row($qne)){ $non[$r[0]] = true; }

  // weekdays sesuai hari sekolah, kurangi non-efektif
  $d=strtotime($dari); $e=strtotime($sampai);
  while($d <= $e){
    $w=(int)date('N',$d); // 1..7
    $is_schoolday = ($hari_sekolah==6) ? ($w<=6) : ($w<=5);
    $tgl = date('Y-m-d',$d);
    if($is_schoolday && empty($non[$tgl])) $efektifDates[] = $tgl;
    $d = strtotime('+1 day',$d);
  }
}

$globalHariEfektif = count($efektifDates);

// ===== AJAX: missing dates per kelas =====
if (isset($_GET['ajax']) && $_GET['ajax']==='missing') {
  header('Content-Type: application/json; charset=utf-8');
  $kid = isset($_GET['kelas']) ? i($_GET['kelas']) : (isset($_GET['kelas_id'])? i($_GET['kelas_id']) : 0);
  if ($kid<=0 || $TA<=0) { echo json_encode(['ok'=>false,'msg'=>'invalid']); exit; }
  $eff = $efektifDates;
  $condFinal = $only_final ? "AND h.status='final'" : "";
  $resInp = mysqli_query($koneksi, "
    SELECT DISTINCT h.tanggal
    FROM absensi_harian h
    WHERE h.ta_id=$TA
      AND h.kelas_id=$kid
      AND h.tanggal BETWEEN '".mysqli_real_escape_string($koneksi,$dari)."' AND '".mysqli_real_escape_string($koneksi,$sampai)."'
      $condFinal
  ");
  $have = [];
  while($ir = mysqli_fetch_row($resInp)){ $have[$ir[0]] = true; }
  $missing = [];
  foreach($eff as $tgl){ if(!isset($have[$tgl])) $missing[]=$tgl; }
  echo json_encode(['ok'=>true,'missing'=>$missing]); exit;
}

// ===== Export CSV/Excel =====
if (isset($_GET['export']) && in_array($_GET['export'], ['csv','excel'])) {
  $exportType = $_GET['export'];
  $condKelas = $kelas_id>0 ? "AND k.kelas_id=$kelas_id" : "";
  $condFinal = $only_final ? "AND h.status='final'" : "";

  $joinEff = '';
  $whereEff = '';
  if ($has_hari_efektif) {
    $joinEff = "LEFT JOIN hari_efektif e
                  ON e.ta_id=$TA AND e.is_efektif=1
                 AND e.tanggal = h.tanggal";
    $whereEff = "AND e.tanggal IS NOT NULL";
  } else {
    if ($globalHariEfektif>0) {
      $in = array_map(function($d) use ($koneksi){ return "'".mysqli_real_escape_string($koneksi,$d)."'"; }, $efektifDates);
      $whereEff = "AND h.tanggal IN (".implode(',',$in).")";
    } else {
      $whereEff = "AND 0";
    }
  }

  $sql = "
    SELECT k.kelas_id,k.kelas_nama,
           COUNT(DISTINCT h.tanggal) AS days_input,
           MAX(h.updated_at) AS last_update,
           SUM(CASE WHEN d.status='H' THEN 1 ELSE 0 END) AS hadir_cnt,
           COUNT(d.siswa_id) AS det_cnt
    FROM kelas k
    LEFT JOIN absensi_harian h
      ON h.kelas_id=k.kelas_id
     AND h.ta_id=$TA
     AND h.tanggal BETWEEN '".mysqli_real_escape_string($koneksi,$dari)."' AND '".mysqli_real_escape_string($koneksi,$sampai)."'
     $condFinal
     $joinEff
    LEFT JOIN absensi_harian_detail d ON d.harian_id=h.harian_id
    WHERE k.kelas_ta=$TA
      $condKelas
      $whereEff
    GROUP BY k.kelas_id,k.kelas_nama
    ORDER BY k.kelas_nama ASC";
  $res = mysqli_query($koneksi,$sql);

  $filename = "rekap_kelas_{$dari}_{$sampai}.csv";
  if ($exportType==='excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
  } else {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
  }
  $out = fopen('php://output','w');
  fputcsv($out, ['Kelas','Hari Efektif','Hari Diinput','% Input','% Hadir','Update Terakhir']);
  while($r = mysqli_fetch_assoc($res)){
    $days = (int)$r['days_input'];
    $pctIn = ($globalHariEfektif>0) ? round(100*$days/$globalHariEfektif) : null;
    $hadir = (int)$r['hadir_cnt']; $det=(int)$r['det_cnt'];
    $pctHd = ($det>0) ? round(100*$hadir/$det) : null;
    fputcsv($out, [
      $r['kelas_nama'],
      $globalHariEfektif,
      $days,
      is_null($pctIn)?'-':($pctIn.'%'),
      is_null($pctHd)?'-':($pctHd.'%'),
      $r['last_update']?:'-'
    ]);
  }
  fclose($out); exit;
}

// ===== Ambil data utama (filter hari efektif di SQL) =====
$condKelas = $kelas_id>0 ? "AND k.kelas_id=$kelas_id" : "";
$condFinal = $only_final ? "AND h.status='final'" : "";

$joinEff = '';
$whereEff = '';
if ($has_hari_efektif) {
  $joinEff = "LEFT JOIN hari_efektif e
                ON e.ta_id=$TA AND e.is_efektif=1
               AND e.tanggal = h.tanggal";
  $whereEff = "AND e.tanggal IS NOT NULL";
} else {
  if ($globalHariEfektif>0) {
    $in = array_map(function($d) use ($koneksi){ return "'".mysqli_real_escape_string($koneksi,$d)."'"; }, $efektifDates);
    $whereEff = "AND h.tanggal IN (".implode(',',$in).")";
  } else {
    $whereEff = "AND 0";
  }
}

$sqlRekap = "
SELECT
  k.kelas_id,
  TRIM(k.kelas_nama) AS kelas_nama,
  COUNT(DISTINCT h.tanggal) AS days_input,
  MAX(h.updated_at) AS last_update,
  SUM(CASE WHEN d.status='H' THEN 1 ELSE 0 END) AS hadir_cnt,
  COUNT(d.siswa_id) AS det_cnt
FROM kelas k
LEFT JOIN absensi_harian h
  ON h.kelas_id = k.kelas_id
 AND h.ta_id    = $TA
 AND h.tanggal BETWEEN '".mysqli_real_escape_string($koneksi,$dari)."' AND '".mysqli_real_escape_string($koneksi,$sampai)."'
 $condFinal
 $joinEff
LEFT JOIN absensi_harian_detail d
  ON d.harian_id = h.harian_id
WHERE k.kelas_ta = $TA
$condKelas
$whereEff
GROUP BY k.kelas_id, kelas_nama
ORDER BY kelas_nama ASC";
$qRekap = mysqli_query($koneksi,$sqlRekap);

// parsing hasil & hitung persen
$rows = [];
while($r = mysqli_fetch_assoc($qRekap)){
  $daysIn = (int)$r['days_input'];
  $hadir  = (int)$r['hadir_cnt'];
  $detCnt = (int)$r['det_cnt'];
  $pctInput = ($globalHariEfektif>0) ? round(100 * $daysIn / $globalHariEfektif) : null;
  $pctHadir = ($detCnt>0) ? round(100 * $hadir / $detCnt) : null;

  $rows[] = [
    'kelas_id'     => (int)$r['kelas_id'],
    'kelas_nama'   => $r['kelas_nama'],
    'hari_efektif' => $globalHariEfektif,
    'days_input'   => $daysIn,
    'pct_input'    => $pctInput,
    'pct_hadir'    => $pctHadir,
    'last_update'  => $r['last_update']
  ];
}

// ===== Widget: tanggal paling banyak/sedikit terisi (ikut filter efektif) =====
$sqlDates = "
  SELECT h.tanggal, COUNT(DISTINCT h.kelas_id) AS kelas_terisi
  FROM absensi_harian h
  ".($has_hari_efektif ? "
      JOIN hari_efektif e ON e.ta_id=$TA AND e.is_efektif=1 AND e.tanggal=h.tanggal
    " : ( $globalHariEfektif>0 ? "" : " JOIN (SELECT 1) z ON 0 " ))."
  WHERE h.ta_id=$TA
    AND h.tanggal BETWEEN '".mysqli_real_escape_string($koneksi,$dari)."' AND '".mysqli_real_escape_string($koneksi,$sampai)."'
    $condFinal
    ".(!$has_hari_efektif && $globalHariEfektif>0 ? " AND h.tanggal IN (".implode(',',array_map(function($d) use ($koneksi){return "'".mysqli_real_escape_string($koneksi,$d)."'";},$efektifDates)).")" : "")."
  GROUP BY h.tanggal
  ORDER BY kelas_terisi DESC, h.tanggal DESC
  LIMIT 5";
$qDates = mysqli_query($koneksi,$sqlDates);
$topDates = [];
while($d=mysqli_fetch_assoc($qDates)) $topDates[]=$d;

$sqlDatesLow = "
  SELECT h.tanggal, COUNT(DISTINCT h.kelas_id) AS kelas_terisi
  FROM absensi_harian h
  ".($has_hari_efektif ? "
      JOIN hari_efektif e ON e.ta_id=$TA AND e.is_efektif=1 AND e.tanggal=h.tanggal
    " : ( $globalHariEfektif>0 ? "" : " JOIN (SELECT 1) z ON 0 " ))."
  WHERE h.ta_id=$TA
    AND h.tanggal BETWEEN '".mysqli_real_escape_string($koneksi,$dari)."' AND '".mysqli_real_escape_string($koneksi,$sampai)."'
    $condFinal
    ".(!$has_hari_efektif && $globalHariEfektif>0 ? " AND h.tanggal IN (".implode(',',array_map(function($d) use ($koneksi){return "'".mysqli_real_escape_string($koneksi,$d)."'";},$efektifDates)).")" : "")."
  GROUP BY h.tanggal
  HAVING COUNT(DISTINCT h.kelas_id) > 0
  ORDER BY kelas_terisi ASC, h.tanggal DESC
  LIMIT 5";
$qDatesLow = mysqli_query($koneksi,$sqlDatesLow);
$lowDates = [];
while($d=mysqli_fetch_assoc($qDatesLow)) $lowDates[]=$d;

// ===== Deteksi: input di hari non-efektif =====
$nonEffInputs = [];
$sqlNonEff = "
  SELECT h.tanggal, k.kelas_nama, COUNT(*) AS lembar
  FROM absensi_harian h
  JOIN kelas k ON k.kelas_id=h.kelas_id
  WHERE h.ta_id=$TA
    AND h.tanggal BETWEEN '".mysqli_real_escape_string($koneksi,$dari)."' AND '".mysqli_real_escape_string($koneksi,$sampai)."'
    ".($only_final ? " AND h.status='final' " : "")."
    ".(
      $has_hari_efektif
      ? " AND NOT EXISTS (SELECT 1 FROM hari_efektif e WHERE e.ta_id=$TA AND e.is_efektif=1 AND e.tanggal=h.tanggal) "
      : ($globalHariEfektif>0 ? " AND h.tanggal NOT IN (".implode(',',array_map(function($d) use ($koneksi){return "'".mysqli_real_escape_string($koneksi,$d)."'";},$efektifDates)).") " : "")
    )."
  GROUP BY h.tanggal, k.kelas_nama
  ORDER BY h.tanggal DESC, k.kelas_nama ASC
  LIMIT 200";
$qNonEff = mysqli_query($koneksi,$sqlNonEff);
while($r=mysqli_fetch_assoc($qNonEff)){ $nonEffInputs[]=$r; }

// ===== Ranking top/bottom 5 % Input =====
$byPct = $rows;
usort($byPct, function($a,$b){
  $pa = $a['pct_input']; $pb = $b['pct_input'];
  if ($pa === $pb) return $b['days_input'] <=> $a['days_input'];
  if (is_null($pa)) return 1;
  if (is_null($pb)) return -1;
  return $pb <=> $pa;
});
$top5    = array_slice($byPct,0,5);
$bottom5 = array_slice(array_reverse($byPct),0,5);

// ====== UI ======
include 'header.php';
?>
<style>
:root{
  --primary:#3b82f6; --primary-dark:#1d4ed8; --accent:#0ea5e9; --success:#10b981;
  --warning:#f59e0b; --danger:#ef4444; --muted:#64748b; --bg:#f5f7fb; --card:#ffffff; --border:#e5e7eb;
}
.content-wrapper{ background:var(--bg); }
.box { border-radius:14px; border:1px solid var(--border); box-shadow:0 8px 28px rgba(2,6,23,.06);}
.box .box-header{ background:linear-gradient(135deg, #eef2ff, #e0f2fe); border-bottom:1px solid var(--border); border-top-left-radius:14px; border-top-right-radius:14px; }
.filter-card .form-group{ margin-bottom:12px; }

.widget-box{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:12px;
  padding:12px;
  margin-bottom:12px;
  box-shadow:0 6px 20px rgba(2,6,23,.05);
  transition: box-shadow .15s ease, transform .1s ease;
}
.widget-box:hover{ box-shadow:0 10px 28px rgba(2,6,23,.08); transform: translateY(-1px); }
.widget-box h4{ margin:0 0 8px; font-size:14px; font-weight:800; color:#111827; display:flex; align-items:center; gap:8px; }
.widget-muted{ color:#111827; }

.badge-muted{ display:inline-flex; align-items:center; gap:6px; background:#eef2f7; color:#111827; padding:6px 10px; border-radius:9999px; font-size:12px; box-shadow:inset 0 0 0 1px #e1e7ef; }
.table > thead th{ white-space:nowrap; }
.btn-xs{ padding:6px 10px; border-radius:8px; }
.btn-apply{ position: relative; overflow: hidden; border: none; color: #fff; font-weight: 800; background: linear-gradient(135deg,#6a5af9,#0ea5e9); border-radius:10px; padding:8px 14px; box-shadow: 0 6px 18px rgba(14,165,233,.25); transition: transform .15s ease, box-shadow .2s ease, opacity .2s ease; }
.btn-apply:hover{ transform: translateY(-1px); box-shadow: 0 8px 20px rgba(14,165,233,.35); }
.btn-apply.pending{ animation: btnPulse 1.4s ease-in-out infinite; }
.btn-apply.pending:after{ content:''; position:absolute; top:0; left:-150%; width:150%; height:100%; background: linear-gradient(120deg, transparent, rgba(255,255,255,.35), transparent); animation: shimmer 1.2s linear infinite; }
@keyframes shimmer { 0%{left:-150%;} 100%{left:150%;} }
@keyframes btnPulse { 0%,100%{ box-shadow: 0 0 0 0 rgba(14,165,233,.35);} 50%{ box-shadow: 0 0 0 6px rgba(14,165,233,.12);} }
.badge-e, .badge-i{ display:inline-block; min-width:48px; text-align:center; padding:4px 8px; border-radius:9999px; font-weight:800; font-size:12px; }
.badge-e{ background:#eef6ff; color:#0b60d0; border:1px solid #cfe6ff; }
.badge-i{ background:#ecfdf5; color:#047857; border:1px solid #bbf7d0; }
.chip-pct{ display:inline-flex; align-items:center; gap:8px; padding:4px 10px; border-radius:9999px; font-weight:800; font-size:12px; color:#fff; }
.pct-low{ background:#ef4444; } .pct-mid{ background:#f59e0b; } .pct-high{ background:#10b981; }
.btn-ghost{ background:#fff; border:1px solid #dbe6ff; color:#1d4ed8; border-radius:10px; padding:6px 10px; font-weight:700; box-shadow:0 2px 8px rgba(29,78,216,.08); transition:all .15s ease; }
.btn-ghost:hover{ background:#eef4ff; box-shadow:0 6px 16px rgba(29,78,216,.15); }
.hdr-tip{ cursor:help; border-bottom:1px dotted #999; }
.dataTables_length select{ border-radius:8px; }
#tblRekap tbody tr:hover{ background:#f0f9ff; }
#tblRekap thead th{ background:#f8fafc; }
.inline-info{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-top:6px; }
.inline-info .tag{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:9999px; background:#f1f5f9; color:#0f172a; font-weight:700; font-size:12px; border:1px solid #e2e8f0; }

/* Badge tipe libur */
.badge-nasional{ background:#fee2e2; color:#991b1b; border-radius:9999px; padding:2px 8px; }
.badge-sekolah{ background:#e0f2fe; color:#0c4a6e; border-radius:9999px; padding:2px 8px; }
.badge-kegiatan{ background:#ecfccb; color:#3f6212; border-radius:9999px; padding:2px 8px; }
.badge-cuti_bersama{ background:#fff7ed; color:#9a3412; border-radius:9999px; padding:2px 8px; }
.badge-lain{ background:#ede9fe; color:#4c1d95; border-radius:9999px; padding:2px 8px; }

/* === Soft themed widget backgrounds (eye-catching) === */
.widget-soft-green  { background:linear-gradient(180deg, rgba(16,185,129,.08), rgba(255,255,255,.95)); border-color:#bbf7d0; }
.widget-soft-red    { background:linear-gradient(180deg, rgba(239,68,68,.08), rgba(255,255,255,.95));  border-color:#fecaca; }
.widget-soft-blue   { background:linear-gradient(180deg, rgba(59,130,246,.08), rgba(255,255,255,.95)); border-color:#bfdbfe; }
.widget-soft-amber  { background:linear-gradient(180deg, rgba(245,158,11,.1), rgba(255,255,255,.95));  border-color:#fde68a; }
.widget-soft-purple { background:linear-gradient(180deg, rgba(168,85,247,.08), rgba(255,255,255,.95)); border-color:#e9d5ff; }

/* list styling: numbered */
.list-themed{ padding-left: 22px; list-style: decimal; }
.list-themed li{ padding:6px 4px; border-radius:10px; color:#0f172a; }
.list-themed li:hover{ background:rgba(2,6,23,.035); }
.text-soft{ color:#0f172a; opacity:.8; font-weight:600; }

/* Non-Efektif table polishing */
#tblNonEff thead th{ background:#fff7f7; }

/* Libur panel title merah */
.widget-libur h4{ color:#b91c1c; }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <h1 style="margin-bottom:6px; font-weight:800;">
      <i class="fa fa-line-chart text-primary"></i> Monitoring Absensi Kelas
      <small class="text-muted">/ Rekap Kelas</small>
    </h1>
    <div class="inline-info">
      <span class="tag"><i class="fa fa-calendar"></i> Rentang: <b><?= escs(fmt_dmy($dari)) ?></b> s/d <b><?= escs(fmt_dmy($sampai)) ?></b></span>
      <span class="tag"><i class="fa fa-briefcase"></i> Hari Efektif: <b><?= (int)$globalHariEfektif ?></b></span>
      <span class="tag"><i class="fa fa-graduation-cap"></i> TA: <b><?= escs($ta_nama) ?></b></span>
      <span class="tag"><i class="fa fa-play-circle"></i> Mulai Efektif: <b><?= escs(fmt_dmy($mulaiEf)) ?></b></span>
    </div>
  </section>

  <section class="content">

    <div class="box box-primary filter-card">
      <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-filter"></i> Filter</h3>
      </div>
      <div class="box-body">
        <form class="form-inline js-filter-form" method="get" action="rekap_kelas.php">
          <div class="row">
            <div class="col-sm-2">
              <label>Tahun Ajaran</label>
              <select name="ta" class="form-control" style="width:100%">
                <?php mysqli_data_seek($tas,0); while($t=mysqli_fetch_assoc($tas)): ?>
                  <option value="<?=i($t['ta_id'])?>" <?=$TA==i($t['ta_id'])?'selected':'';?>><?=escs($t['ta_nama'])?></option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="col-sm-3">
              <label>Mode Rentang</label>
              <select name="mode" id="mode" class="form-control" style="width:100%">
                <option value="semester" <?=$mode==='semester'?'selected':'';?>>Semester (Mulai Efektif → Hari Ini)</option>
                <option value="kustom"   <?=$mode==='kustom'  ?'selected':'';?>>Kustom (Rentang Tanggal)</option>
              </select>
            </div>

            <div class="col-sm-3 mode-wrap mode-semester" style="display:none">
              <label>Pilih Semester</label>
              <select name="semester" class="form-control" style="width:100%">
                <option value="1" <?=$semester==1?'selected':'';?>>Semester 1 (Jul–Des)</option>
                <option value="2" <?=$semester==2?'selected':'';?>>Semester 2 (Jan–Jun)</option>
              </select>
            </div>

            <div class="col-sm-3 mode-wrap mode-semester" style="display:none">
              <label>Mulai Efektif Belajar</label>
              <input type="date" name="mulai_efektif" value="<?=escs($mulaiEf)?>" class="form-control" style="width:100%">
            </div>

            <div class="col-sm-2 mode-wrap mode-kustom" style="display:none">
              <label>Tgl Mulai</label>
              <input type="date" name="awal" value="<?=escs($awal)?>" class="form-control" style="width:100%">
            </div>
            <div class="col-sm-2 mode-wrap mode-kustom" style="display:none">
              <label>Tgl Akhir</label>
              <input type="date" name="akhir" value="<?=escs($akhir)?>" class="form-control" style="width:100%">
            </div>

            <div class="col-sm-2">
              <label>Hari Sekolah</label>
              <select name="hari_sekolah" class="form-control" style="width:100%">
                <option value="5" <?=$hari_sekolah==5?'selected':'';?>>5 hari (Sen–Jum)</option>
                <option value="6" <?=$hari_sekolah==6?'selected':'';?>>6 hari (Sen–Sab)</option>
              </select>
            </div>

            <div class="col-sm-3">
              <label>Filter Kelas (opsional)</label>
              <select name="kelas" class="form-control" style="width:100%">
                <option value="0">Semua Kelas</option>
                <?php
                  $qk = mysqli_query($koneksi,"SELECT kelas_id,kelas_nama FROM kelas WHERE kelas_ta=$TA ORDER BY kelas_nama");
                  while($k=mysqli_fetch_assoc($qk)):
                ?>
                  <option value="<?=i($k['kelas_id'])?>" <?=$kelas_id==i($k['kelas_id'])?'selected':'';?>><?=escs($k['kelas_nama'])?></option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="col-sm-3">
              <label>&nbsp;</label>
              <div style="display:flex; gap:8px; align-items:center;">
                <button id="btnTerapkan" class="btn btn-apply" type="submit">
                  <i class="fa fa-search"></i> Terapkan
                </button>
                <a class="btn btn-default" href="rekap_kelas.php" style="border-radius:10px;">
                  <i class="fa fa-undo"></i> Reset
                </a>
              </div>
              <small class="text-muted" id="hintTerapkan" style="display:none">Ada perubahan filter. Klik <b>Terapkan</b> untuk memuat data.</small>
            </div>

            <div class="col-sm-12" style="margin-top:8px;">
              <div class="checkbox">
                <label>
                  <input type="checkbox" name="only_final" value="1" <?=$only_final?'checked':'';?>> Hitung % dari FINAL saja
                </label>
              </div>
            </div>

          </div>
        </form>

        <div class="inline-info">
          <span class="badge-muted">
            <i class="fa fa-briefcase"></i> Hari efektif rentang: <b><?= (int)$globalHariEfektif ?></b>
          </span>
        </div>
      </div>
      <div class="box-footer">
        <small>
          Sumber hari efektif:
          <?=$has_hari_efektif ? 'tabel <b>hari_efektif</b>' : 'weekdays ('.$hari_sekolah.' hari) – dikurangi <b>view_non_efektif</b>'; ?>.
          TA: <?=escs($ta_nama)?>,
          Rentang: <b><?=escs(fmt_dmy($awal))?></b> s/d <b><?=escs(fmt_dmy($akhir))?></b>
          <?= $mode==='semester' ? ' (mulai efektif: <b>'.escs(fmt_dmy($mulaiEf)).'</b>)' : '' ?>.
        </small>
      </div>
    </div>

    <div class="row">
      <div class="col-md-9">
        <div class="box">
          <div class="box-header with-border">
            <h3 class="box-title" style="font-weight:800;">
              Ringkasan per Kelas
              <small class="text-muted">(E = Hari Efektif, I = Hari Diinput)</small>
            </h3>
          </div>
          <div class="box-body">
            <div class="table-responsive">
              <table id="tblRekap" class="table table-bordered table-striped table-hover">
                <thead>
                  <tr>
                    <th>No</th>
                    <th>Kelas</th>
                    <th title="Hari efektif (weekday - libur)">
                      <span class="hdr-tip" data-toggle="tooltip" title="Total hari kerja dalam rentang (sesuai hari sekolah, dikurangi libur).">E <i class="fa fa-question-circle"></i></span>
                    </th>
                    <th title="Hari yang diinput">
                      <span class="hdr-tip" data-toggle="tooltip" title="Jumlah hari yang sudah dibuat lembar absensi (hanya hari efektif).">I <i class="fa fa-question-circle"></i></span>
                    </th>
                    <th><span class="hdr-tip" data-toggle="tooltip" title="% Input = I / E"> % Input <i class="fa fa-question-circle"></i></span></th>
                    <th><span class="hdr-tip" data-toggle="tooltip" title="% Hadir = H / total detail (hanya hari efektif)"> % Hadir <i class="fa fa-question-circle"></i></span></th>
                    <th>Update Terakhir</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                    $no=1;
                    foreach($rows as $r):
                      $pctIn = $r['pct_input'];
                      $pctHd = $r['pct_hadir'];
                      $clsIn = is_null($pctIn) ? 'pct-low' : ($pctIn>=75?'pct-high':($pctIn>=25?'pct-mid':'pct-low'));
                      $clsHd = is_null($pctHd) ? 'pct-low' : ($pctHd>=95?'pct-high':($pctHd>=85?'pct-mid':'pct-low'));
                      $inDisp = is_null($pctIn) ? '–' : ($pctIn.'%');
                      $hdDisp = is_null($pctHd) ? '–' : ($pctHd.'%');
                      $lastUpd = $r['last_update'] ? fmt_dmy_his($r['last_update']) : '-';
                      $lastUpdOrder = $r['last_update'] ?: '';
                  ?>
                  <tr>
                    <td><?= $no++ ?></td>
                    <td><?= escs($r['kelas_nama']) ?></td>
                    <td>
                      <span class="badge-e" data-toggle="tooltip" title="Hari efektif di rentang">
                        <i class="fa fa-briefcase"></i> <?= (int)$r['hari_efektif'] ?>
                      </span>
                    </td>
                    <td>
                      <span class="badge-i" data-toggle="tooltip" title="Jumlah tanggal yang sudah diinput absensinya (hanya hari efektif)">
                        <i class="fa fa-calendar-check-o"></i> <?= (int)$r['days_input'] ?>
                      </span>
                    </td>
                    <td>
                      <span class="chip-pct <?= $clsIn ?>" data-toggle="tooltip" title="% Input = I / E">
                        <i class="fa fa-upload"></i> <?= $inDisp ?>
                      </span>
                    </td>
                    <td>
                      <span class="chip-pct <?= $clsHd ?>" data-toggle="tooltip" title="% Hadir dari seluruh detail (hari efektif)">
                        <i class="fa fa-user"></i> <?= $hdDisp ?>
                      </span>
                    </td>
                    <td data-order="<?= escs($lastUpdOrder) ?>"><?= escs($lastUpd) ?></td>
                    <td style="white-space:nowrap;">
                      <button class="btn btn-ghost btn-xs btn-missing"
                              data-kelas-id="<?= (int)$r['kelas_id'] ?>"
                              data-kelas-nama="<?= escs($r['kelas_nama']) ?>"
                              data-toggle="tooltip" title="Lihat tanggal kerja yang belum ada lembar absensinya">
                        <i class="fa fa-calendar-times-o"></i> Lihat hari kosong
                      </button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="box">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-exclamation-triangle text-danger"></i> Input di Hari Non-Efektif</h3>
          </div>
          <div class="box-body">
            <?php if(!$nonEffInputs){ ?>
              <em>Tidak ada input pada hari non-efektif dalam rentang ini.</em>
            <?php } else { ?>
              <div class="table-responsive">
                <table id="tblNonEff" class="table table-bordered table-striped">
                  <thead>
                    <tr>
                      <th>Tanggal</th>
                      <th>Kelas</th>
                      <th>Lembar</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($nonEffInputs as $x): ?>
                      <tr>
                        <td data-order="<?= escs($x['tanggal']) ?>"><?= escs(fmt_dmy($x['tanggal'])) ?></td>
                        <td><?= escs($x['kelas_nama']) ?></td>
                        <td><?= (int)$x['lembar'] ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <small class="text-muted">Catatan: baris-baris ini <b>tidak dihitung</b> ke % Input/% Hadir.</small>
            <?php } ?>
          </div>
        </div>

      </div>

      <div class="col-md-3">
        <div class="widget-box widget-soft-green">
          <h4><i class="fa fa-trophy"></i> Kelas paling lengkap input (Top 5)</h4>
          <ol class="list-themed">
            <?php
              if(!$top5){ echo '<li><em>Belum ada data</em></li>'; }
              foreach($top5 as $t){
                $pct = is_null($t['pct_input']) ? '–' : ($t['pct_input'].'%');
                echo '<li><b>'.escs($t['kelas_nama']).'</b> — ('.(int)$t['days_input'].'/'.(int)$globalHariEfektif.'), '.$pct.'</li>';
              }
            ?>
          </ol>
        </div>

        <div class="widget-box widget-soft-red">
          <h4><i class="fa fa-flag"></i> Kelas tertinggal (Bottom 5)</h4>
          <ol class="list-themed">
            <?php
              if(!$bottom5){ echo '<li><em>Belum ada data</em></li>'; }
              foreach($bottom5 as $b){
                $pct = is_null($b['pct_input']) ? '–' : ($b['pct_input'].'%');
                echo '<li><b>'.escs($b['kelas_nama']).'</b> — ('.(int)$b['days_input'].'/'.(int)$globalHariEfektif.'), '.$pct.'</li>';
              }
            ?>
          </ol>
        </div>

        <div class="widget-box widget-soft-blue">
          <h4><i class="fa fa-calendar"></i> Tanggal paling banyak terisi</h4>
          <ol class="list-themed">
            <?php
              if(!$topDates){ echo '<li><em>Belum ada data</em></li>'; }
              foreach($topDates as $d){
                echo '<li>'.escs(fmt_dmy($d['tanggal'])).' — '.(int)$d['kelas_terisi'].' kelas</li>';
              }
            ?>
          </ol>
        </div>

        <div class="widget-box widget-soft-amber">
          <h4><i class="fa fa-calendar-times-o"></i> Tanggal paling sedikit terisi</h4>
          <ol class="list-themed">
            <?php
              if(!$lowDates){ echo '<li><em>Belum ada data</em></li>'; }
              foreach($lowDates as $d){
                echo '<li>'.escs(fmt_dmy($d['tanggal'])).' — '.(int)$d['kelas_terisi'].' kelas</li>';
              }
            ?>
          </ol>
        </div>

        <div class="widget-box widget-soft-red widget-libur">
          <h4><i class="fa fa-ban"></i> Tanggal Libur</h4>
          <ol class="list-themed">
            <?php
              $qLib = mysqli_query($koneksi,"
                SELECT tgl, tipe, keterangan
                FROM view_non_efektif
                WHERE tgl BETWEEN '".mysqli_real_escape_string($koneksi,$dari)."' AND '".mysqli_real_escape_string($koneksi,$sampai)."'
                  AND (ta_id IS NULL OR ta_id=$TA)
                ORDER BY tgl ASC
              ");
              $hasLib = false;
              while($L=mysqli_fetch_assoc($qLib)){
                $w=(int)date('N', strtotime($L['tgl']));
                if ($w>=1 && $w<=5) { // tetap hanya Sen–Jum
                  $hasLib = true;
                  echo '<li>'.escs(fmt_dmy($L['tgl'])).' — '.escs($L['keterangan']).' <span class="badge-'.escs($L['tipe']).'">'.escs($L['tipe']).'</span></li>';
                }
              }
              if(!$hasLib) echo '<li><em>Tidak ada libur pada hari kerja.</em></li>';
            ?>
          </ol>
          <a href="kalender_akademik.php?ta=<?=$TA?>&semester=<?=$semester?>&hari_sekolah=<?=$hari_sekolah?>" class="btn btn-default btn-xs" style="border-radius:10px;">
            <i class="fa fa-cog"></i> Kelola Libur
          </a>
        </div>
      </div>
    </div>



<?php
/* ==========================================================
   PANEL AUDIT LOG – Rekap Kelas (Monitoring Absensi)
   ========================================================== */

if (!isset($awal,$akhir)) { $awal = date('Y-m-01'); $akhir = date('Y-m-d'); }
if (!isset($kelas_id))   { $kelas_id = 0; }

$limitAudit = 300;
$awalDT  = $awal  . ' 00:00:00';
$akhirDT = $akhir . ' 23:59:59';

$mysqlVersion = mysqli_get_server_info($koneksi);
$supportsJSON = version_compare($mysqlVersion, '5.7.0', '>=');

// WHERE dasar: entitas absensi + rentang waktu
$where = "l.entity IN ('absensi_harian','absensi_harian_detail')
          AND l.created_at BETWEEN '".mysqli_real_escape_string($koneksi,$awalDT)."'
                               AND '".mysqli_real_escape_string($koneksi,$akhirDT)."'";

// --- Catatan penting ---
// Banyak log detail tidak menyimpan meta.kelas_id. Maka kita fallback:
//  a) Jika entity = absensi_harian  => ambil kelas dari hE (JOIN by entity_id)
//  b) Jika ada meta.harian_id       => ambil kelas dari hM (JOIN by JSON harian_id)
//  c) Jika ada meta.kelas_id        => langsung pakai itu
//
// Filter kelas (jika dipilih) juga memperhitungkan ketiga jalur di atas.

$whereKelas = '';
if ($kelas_id > 0) {
  if ($supportsJSON) {
    $kelas_id_int = (int)$kelas_id;
    $whereKelas = "
      AND (
            CAST(JSON_UNQUOTE(JSON_EXTRACT(l.meta,'$.kelas_id')) AS UNSIGNED) = {$kelas_id_int}
         OR (l.entity='absensi_harian' AND hE.kelas_id = {$kelas_id_int})
         OR (hM.kelas_id = {$kelas_id_int})
      )";
  } else {
    // Fallback sederhana (tanpa JSON): hanya bisa memfilter lewat meta tekstual
    $kelas_id_int = (int)$kelas_id;
    $whereKelas = " AND (l.meta LIKE '%\"kelas_id\":{$kelas_id_int}%' OR l.meta LIKE '%\"kelas_id\":\"{$kelas_id_int}\"%')";
  }
}

// SQL dengan JOIN fallback kelas (hE/hM) + siswa
$sqlAudit = "
  SELECT
    l.id, l.entity, l.action, l.entity_id, l.user_id, l.ip, l.created_at,
    ".($supportsJSON ? "
      JSON_UNQUOTE(JSON_EXTRACT(l.meta,'$.kelas_id'))   AS meta_kelas_id,
      JSON_UNQUOTE(JSON_EXTRACT(l.meta,'$.siswa_id'))   AS meta_siswa_id,
      JSON_UNQUOTE(JSON_EXTRACT(l.meta,'$.old_status')) AS old_status,
      JSON_UNQUOTE(JSON_EXTRACT(l.meta,'$.new_status')) AS new_status
    " : "
      NULL AS meta_kelas_id, NULL AS meta_siswa_id, NULL AS old_status, NULL AS new_status
    ").",
    u.user_nama,
    -- Ambil nama kelas dengan prioritas: meta.kelas_id -> hE.kelas_id -> hM.kelas_id
    k.kelas_nama,
    s.siswa_nama
  FROM audit_log l
  LEFT JOIN user  u ON u.user_id = l.user_id

  ".($supportsJSON ? "
    -- hE: entity absensi_harian -> kelas via entity_id
    LEFT JOIN absensi_harian hE
      ON (l.entity = 'absensi_harian' AND hE.harian_id = l.entity_id)

    -- hM: fallback via meta.harian_id (untuk baris detail yang tidak punya meta.kelas_id)
    LEFT JOIN absensi_harian hM
      ON CAST(JSON_UNQUOTE(JSON_EXTRACT(l.meta,'$.harian_id')) AS UNSIGNED) = hM.harian_id

    -- Join siswa dari meta.siswa_id (kalau ada)
    LEFT JOIN siswa s
      ON s.siswa_id = CAST(JSON_UNQUOTE(JSON_EXTRACT(l.meta,'$.siswa_id')) AS UNSIGNED)

    -- Join kelas dari COALESCE(meta.kelas_id, hE.kelas_id, hM.kelas_id)
    LEFT JOIN kelas k
      ON k.kelas_id = COALESCE(
           NULLIF(CAST(JSON_UNQUOTE(JSON_EXTRACT(l.meta,'$.kelas_id')) AS UNSIGNED), 0),
           NULLIF(hE.kelas_id, 0),
           NULLIF(hM.kelas_id, 0)
         )
  " : "
    LEFT JOIN kelas k ON 1=0
    LEFT JOIN siswa s ON 1=0
  ")."
  WHERE {$where} {$whereKelas}
  ORDER BY l.created_at DESC, l.id DESC
  LIMIT {$limitAudit}
";

$qAudit = @mysqli_query($koneksi, $sqlAudit);
?>

<style>
  .box-audit{ border-radius:12px; border:1px solid #e5e7eb; }
  .box-audit .box-header{ background:#fff; border-bottom:1px solid #eef2f7; border-radius:12px 12px 0 0; }
  .pill-act{ display:inline-block; font-weight:700; font-size:11px; padding:2px 8px; border-radius:9999px; }
  .pill-ubah{ background:#e0f2fe; color:#075985; }
  .pill-hapus{ background:#fee2e2; color:#7f1d1d; }
  .pill-final{ background:#dcfce7; color:#065f46; }
  .pill-unlock{ background:#fef9c3; color:#7a5d00; }
  .pill-default{ background:#e5e7eb; color:#374151; }
</style>

<div class="row" id="audit-log-panel" style="margin-top:18px;">
  <div class="col-xs-12">
    <div class="box box-audit">
      <div class="box-header with-border">
        <h3 class="box-title">
          Audit Log Absensi
          <small style="font-weight:600;color:#64748b;">
            (<?= htmlspecialchars($awal) ?> s/d <?= htmlspecialchars($akhir) ?><?= $kelas_id>0 ? ', Kelas ID: '.(int)$kelas_id : '' ?>)
          </small>
        </h3>
        <div class="pull-right">
          <button type="button" id="auditRefresh" class="btn btn-default btn-sm">
            <i class="fa fa-rotate-right"></i> Refresh
          </button>
        </div>
      </div>

      <div class="box-body">
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-hover" id="auditLogTable" width="100%">
            <thead>
              <tr>
                <th width="1%">#</th>
                <th>Waktu</th>
                <th>Pengguna</th>
                <th>Aksi</th>
                <th>Entitas</th>
                <th>Kelas</th>
                <th>Siswa</th>
                <th>Detail</th>
                <th>IP</th>
              </tr>
            </thead>
            <tbody>
            <?php
              $no=1;
              if($qAudit){
                while($r = mysqli_fetch_assoc($qAudit)){
                  $aksi = strtolower(trim($r['action'] ?? ''));
                  $pillClass = ($aksi==='ubah' ? 'pill-ubah' :
                               ($aksi==='hapus' ? 'pill-hapus' :
                               ($aksi==='final' ? 'pill-final' :
                               ($aksi==='unlock'? 'pill-unlock':'pill-default'))));
                  $detail = '';
                  if (!empty($r['old_status']) || !empty($r['new_status'])) {
                    $os = strtoupper($r['old_status'] ?: '-');
                    $ns = strtoupper($r['new_status'] ?: '-');
                    $detail = "Status: <b>{$os}</b> → <b>{$ns}</b>";
                  } else {
                    if ($aksi==='final')  $detail = 'Finalisasi lembar';
                    if ($aksi==='unlock') $detail = 'Buka kunci (draft)';
                    if ($aksi==='hapus')  $detail = 'Hapus data';
                    if ($aksi==='buat')   $detail = 'Buat lembar';
                    if ($aksi==='edit')   $detail = 'Edit lembar';
                  }
                  $waktu   = htmlspecialchars($r['created_at'] ?? '');
                  $peng    = htmlspecialchars($r['user_nama'] ?? ('UID '.(int)$r['user_id']));
                  $entitas = htmlspecialchars($r['entity'] ?? '');

                  // tampilkan nama kelas; jika tidak ada, tunjukkan #id kalau ada di meta
                  $kelasNm = '-';
                  if (!empty($r['kelas_nama'])) {
                    $kelasNm = htmlspecialchars($r['kelas_nama']);
                  } elseif (!empty($r['meta_kelas_id'])) {
                    $kelasNm = '#'.(int)$r['meta_kelas_id'];
                  }

                  $siswaNm = '-';
                  if (!empty($r['siswa_nama'])) {
                    $siswaNm = htmlspecialchars($r['siswa_nama']);
                  } elseif (!empty($r['meta_siswa_id'])) {
                    $siswaNm = '#'.(int)$r['meta_siswa_id'];
                  }

                  $ip      = htmlspecialchars($r['ip'] ?? '-');

                  echo "<tr>";
                  echo "<td>".($no++)."</td>";
                  echo "<td data-order=\"".htmlspecialchars($waktu)."\">{$waktu}</td>";
                  echo "<td>{$peng}</td>";
                  echo "<td><span class=\"pill-act {$pillClass}\">".strtoupper($aksi)."</span></td>";
                  echo "<td>{$entitas}</td>";
                  echo "<td>{$kelasNm}</td>";
                  echo "<td>{$siswaNm}</td>";
                  echo "<td>{$detail}</td>";
                  echo "<td>{$ip}</td>";
                  echo "</tr>";
                }
              }
            ?>
            </tbody>
          </table>
        </div>
        <p class="text-muted" style="margin-top:8px;">
          Menampilkan maksimal <b><?= (int)$limitAudit ?></b> log terbaru sesuai filter tanggal/kelas.
        </p>
      </div>
    </div>
  </div>
</div>

<script>
$(function(){
  // DataTable
  if ($.fn.DataTable) {
    $('#auditLogTable').DataTable({
      order: [[1,'desc']],
      pageLength: 10,
      lengthChange: true,
      autoWidth:false,
      responsive:true,
      columnDefs: [{targets:0, orderable:false}]
    });
  }
  // Refresh aman (tidak terpengaruh <base>)
  $('#auditRefresh').on('click', function(e){
    e.preventDefault();
    // jika kamu ingin mempertahankan querystring, reload saja:
    window.location.reload(true);
  });
});
</script>


  </section>
</div>

<div class="modal fade" id="modalMissing" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#fee2e2,#e0f2fe);">
        <button class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title"><i class="fa fa-calendar-times-o text-danger"></i> Hari kosong</h4>
      </div>
      <div class="modal-body">
        <p class="text-muted" id="missingKelas"></p>
        <div id="missingList"><em>Memuat…</em></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-default btn-sm" data-dismiss="modal" style="border-radius:10px;">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
$(function(){
  $('[data-toggle="tooltip"]').tooltip({container:'body'});

  // DataTable utama
  if ($.fn.dataTable && $.fn.dataTable.ext) { $.fn.dataTable.ext.errMode = 'console'; }
  var t = $('#tblRekap').DataTable({
    responsive:true, autoWidth:false, pageLength:25, lengthChange:true,
    order:[[1,'asc']], columnDefs:[{targets:[0,7], orderable:false}],
    language:{ search:"", searchPlaceholder:"Cari kelas…", paginate:{previous:"‹", next:"›"}, info:"Menampilkan _START_–_END_ dari _TOTAL_ baris" }
  });
  t.on('order.dt search.dt', function(){ let i=1; t.column(0,{search:'applied',order:'applied'}).nodes().each(function(c){c.innerHTML=i++;}); }).draw();

  // DataTable untuk panel "Input di Hari Non-Efektif" (default 5)
  if ($('#tblNonEff').length){
    $('#tblNonEff').DataTable({
      pageLength:5,
      lengthMenu:[[5,10,25,50,-1],[5,10,25,50,"Semua"]],
      order:[[0,'desc']], // by tanggal (pakai data-order=Y-m-d)
      autoWidth:false,
      language:{
        search:"", searchPlaceholder:"Cari…",
        paginate:{previous:"‹", next:"›"},
        lengthMenu:"Tampil _MENU_",
        info:"_START_–_END_ dari _TOTAL_"
      }
    });
  }

  // Sinkron UI mode
  (function(){
    function syncMode(){
      var m = document.getElementById('mode').value;
      document.querySelectorAll('.mode-wrap').forEach(function(e){ e.style.display='none'; });
      if(m==='semester'){ document.querySelectorAll('.mode-semester').forEach(function(e){ e.style.display=''; }); }
      if(m==='kustom'){ document.querySelectorAll('.mode-kustom').forEach(function(e){ e.style.display=''; }); }
    }
    document.getElementById('mode').addEventListener('change', syncMode);
    syncMode();
  })();

  // Tombol Terapkan shimmer
  (function(){
    var form = document.querySelector('.js-filter-form');
    var btn  = document.getElementById('btnTerapkan');
    var hint = document.getElementById('hintTerapkan');
    if(!form || !btn) return;
    function markDirty(){ btn.classList.add('pending'); if(hint) hint.style.display=''; }
    function clearDirty(){ btn.classList.remove('pending'); if(hint) hint.style.display='none'; }
    form.querySelectorAll('select,input').forEach(function(el){ el.addEventListener('change', markDirty); el.addEventListener('input',  markDirty); });
    form.addEventListener('submit', function(){ clearDirty(); });
    clearDirty();
  })();

  // Modal "hari kosong"
  function toDMY(iso){ if(!iso) return iso; var p=iso.split('-'); if(p.length!==3) return iso; return p[2]+'/'+p[1]+'/'+p[0]; }
  $('.btn-missing').on('click', function(){
    var kid = $(this).data('kelas-id');
    var knm = $(this).data('kelas-nama');
    $('#missingKelas').html('Kelas: <b>' + knm + '</b> | Rentang: <?=escs(fmt_dmy($dari))?> s.d. <?=escs(fmt_dmy($sampai))?>');
    $('#missingList').html('<em>Memuat…</em>');
    $('#modalMissing').modal('show');

    $.getJSON('rekap_kelas.php', {
      ajax: 'missing',
      kelas: kid,
      ta: '<?= (int)$TA ?>',
      mode: '<?= escs($mode) ?>',
      semester: '<?= (int)$semester ?>',
      mulai_efektif: '<?= escs($mulaiEf) ?>',
      awal: '<?= escs($awal) ?>',
      akhir: '<?= escs($akhir) ?>',
      hari_sekolah: '<?= (int)$hari_sekolah ?>',
      only_final: '<?= (int)$only_final ?>'
    }).done(function(res){
      if(res && res.ok){
        if(!res.missing || res.missing.length===0){
          $('#missingList').html('<span class="text-success"><i class="fa fa-check-circle"></i> Tidak ada hari kosong.</span>');
        }else{
          var html = '<ul style="padding-left:18px">';
          res.missing.forEach(function(d){ html += '<li>'+ toDMY(d) +'</li>'; });
          html += '</ul>';
          $('#missingList').html(html);
        }
      }else{
        $('#missingList').html('<span class="text-danger">Gagal memuat.</span>');
      }
    }).fail(function(){
      $('#missingList').html('<span class="text-danger">Gagal memuat.</span>');
    });
  });
});
</script>

<?php include 'footer.php'; ?>