<?php



// ================== AJAX: DETAIL ABSENSI PER SISWA (JSON) ==================
// Ditaruh di paling atas agar tidak mengganggu output HTML lain.
if (isset($_GET['ajax']) && $_GET['ajax']==='detail_siswa') {
  include '../koneksi.php';
  // helper mini (hindari bentrok dengan fungsi di bawah)
  $i=function($v){ return (int)$v; };
  $valid_ymd=function($s){ return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$s); };
  $ymd=function($d){ return date('Y-m-d', strtotime($d)); };

  header('Content-Type: application/json');
  try{
    $today = date('Y-m-d');
    $TA = isset($_GET['ta']) ? $i($_GET['ta']) : 0;
    $mode = isset($_GET['mode']) ? trim($_GET['mode']) : 'semester';
    
    // --- PENYEMPURNAAN (AJAX): Deteksi otomatis semester default ---
    $bulanBerjalanAjax = (int)date('n');
    $defaultSemesterAjax = ($bulanBerjalanAjax >= 1 && $bulanBerjalanAjax <= 6) ? 2 : 1;
    $semester = isset($_GET['semester']) ? $i($_GET['semester']) : $defaultSemesterAjax;
    // ---------------------------------------------------------------

    $mulai_efektif = isset($_GET['mulai_efektif']) ? $_GET['mulai_efektif'] : '2025-07-21';
    $awal_in = isset($_GET['awal']) ? $_GET['awal'] : '';
    $akhir_in= isset($_GET['akhir'])? $_GET['akhir']: '';
    $kelas_id = isset($_GET['kelas']) ? $i($_GET['kelas']) : 0;
    $sid = isset($_GET['sid']) ? $i($_GET['sid']) : 0;

    if($sid<=0) throw new Exception('Parameter siswa tidak valid.');

    // Ambil TA => tentukan tahun
    $ta_row = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT ta_nama FROM ta WHERE ta_id=$TA"));
    $ta_nama = trim($ta_row['ta_nama'] ?? '');
    preg_match('/(\d{4})\D+(\d{4})/',$ta_nama,$m);
    $y1 = isset($m[1]) ? (int)$m[1] : (int)date('Y');
    $y2 = isset($m[2]) ? (int)$m[2] : $y1+1;

    if($semester==2){ $semStart="$y2-01-01"; $semEnd="$y2-06-30"; }
    else{ $semStart="$y1-07-01"; $semEnd="$y1-12-31"; }

    $upperBound = ($today < $semEnd) ? $today : $semEnd;
    $mulaiEf = $mulai_efektif; if(!$valid_ymd($mulaiEf)) $mulaiEf='2025-07-21';
    if($mulaiEf < $semStart) $mulaiEf = $semStart; if($mulaiEf > $upperBound) $mulaiEf = $upperBound;

    if($mode==='kustom' && $valid_ymd($awal_in) && $valid_ymd($akhir_in)){
      $awal=$ymd($awal_in); $akhir=$ymd($akhir_in);
    } else { $awal=$mulaiEf; $akhir=$upperBound; }
    if($awal>$akhir) $akhir=$awal;

    // Nama siswa
    $srow = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT siswa_nama FROM siswa WHERE siswa_id=$sid"));
    $siswa_nama = $srow['siswa_nama'] ?? 'Siswa';

    // ====== DETEKSI AMAN KOLUMN KETERANGAN (keterangan/ket) ======
    // Beberapa database memakai nama kolom berbeda. Kita cek yang tersedia agar query tidak error.
    $ketExpr = "''"; // default kosong
    $chk1 = @mysqli_query($koneksi, "SHOW COLUMNS FROM `absensi_harian_detail` LIKE 'keterangan'");
    if($chk1 && mysqli_num_rows($chk1)>0){
      $ketExpr = 'ahd.keterangan';
    } else {
      $chk2 = @mysqli_query($koneksi, "SHOW COLUMNS FROM `absensi_harian_detail` LIKE 'ket'");
      if($chk2 && mysqli_num_rows($chk2)>0){
        $ketExpr = 'ahd.ket';
      }
    }

    // Detail harian siswa
    $filter_kelas = $kelas_id ? " AND ah.kelas_id=$kelas_id " : '';
    $sql = "SELECT ah.tanggal, COALESCE(k.kelas_nama,'-') AS kelas_nama, ahd.status, " . $ketExpr . " AS ket
            FROM absensi_harian_detail ahd
            JOIN absensi_harian ah ON ah.harian_id=ahd.harian_id
            LEFT JOIN kelas k ON k.kelas_id=ah.kelas_id
            WHERE ah.ta_id=$TA AND ah.status='final'
              AND ah.tanggal BETWEEN '$awal' AND '$akhir'
              AND ahd.siswa_id=$sid
              $filter_kelas
            ORDER BY ah.tanggal ASC";
    $rs = mysqli_query($koneksi,$sql);
    if(!$rs){
      throw new Exception('Query gagal: '.mysqli_error($koneksi));
    }

    $rows=[]; $H=0;$S=0;$I=0;$A=0; $kelasSet=[];
    while($r=mysqli_fetch_assoc($rs)){
      $st = strtoupper(trim($r['status']));
      if($st==='H') $H++; elseif($st==='S') $S++; elseif($st==='I') $I++; else $A++;
      $rows[] = [
        'tanggal' => $r['tanggal'],
        'kelas'   => $r['kelas_nama'],
        'status'  => $st,
        'ket'     => $r['ket']
      ];
      $kelasSet[$r['kelas_nama']] = true;
    }
    $ttl = $H+$S+$I+$A; $pct = $ttl? round(100*$H/$ttl,1) : 0;

    echo json_encode([
      'ok'=>true,
      'siswa'=>['id'=>$sid,'nama'=>$siswa_nama],
      'range'=>['awal'=>$awal,'akhir'=>$akhir],
      'rekap'=>['H'=>$H,'S'=>$S,'I'=>$I,'A'=>$A,'ttl'=>$ttl,'pct'=>$pct],
      'kelas'=>array_keys($kelasSet),
      'rows'=>$rows
    ]);
  }catch(Throwable $e){ echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
  exit;
}
// ================== END AJAX ==================
?>
<?php
// laporan_absensi.php (REVISI: SERAGAM DATATABLES + RESPONSIF + TAB KOTAK RAPAT
// + MONITOR PER MAPEL MINGGUAN + TAMBAH "PER GURU (DETAIL)" + ***DETAIL PER SISWA***)
// + REVISI TERBARU: Auto-deteksi semester default berdasarkan bulan berjalan (Jan-Jun = Sem 2, Jul-Des = Sem 1)
include 'header.php';
include '../koneksi.php';

// ===== Helpers =====
function esc($s){ return htmlspecialchars($s,ENT_QUOTES,'UTF-8'); }
function i($v){ return (int)$v; }
function ymd($d){ return date('Y-m-d', strtotime($d)); }
function _get($k,$d=null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function valid_ymd($s){ return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$s); }
function table_exists($koneksi,$name){
  $res = @mysqli_query($koneksi,"SHOW TABLES LIKE '".mysqli_real_escape_string($koneksi,$name)."'");
  return $res && mysqli_num_rows($res)>0;
}

$today = date('Y-m-d');

// Ambil TA aktif & pilihan
$ta_aktif = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT ta_id, ta_nama FROM ta WHERE ta_status=1 ORDER BY ta_id DESC LIMIT 1"));
$TA = isset($_GET['ta']) ? i($_GET['ta']) : i($ta_aktif['ta_id'] ?? 0);

// Daftar TA
$tas = mysqli_query($koneksi,"SELECT ta_id, ta_nama FROM ta ORDER BY ta_id DESC");

// ===== PARAMETER FILTER =====
$mode           = _get('mode','semester');               // semester|kustom

// --- PENYEMPURNAAN: Deteksi otomatis semester default ---
// Jika bulan 1 s/d 6 (Jan-Jun) maka default Semester 2. Jika 7 s/d 12 (Jul-Des) maka default Semester 1.
$bulanBerjalan = (int)date('n');
$defaultSemester = ($bulanBerjalan >= 1 && $bulanBerjalan <= 6) ? 2 : 1;
$semester       = isset($_GET['semester']) ? i($_GET['semester']) : $defaultSemester;
// --------------------------------------------------------

$kustom_awal    = _get('awal','');
$kustom_akhir   = _get('akhir','');
$hari_sekolah   = (_get('hari_sekolah','5') === '6') ? 6 : 5;
$kelas_id       = isset($_GET['kelas']) ? i($_GET['kelas']) : 0;
$mapel_id       = isset($_GET['mapel']) ? i($_GET['mapel']) : 0;
$mulai_efektif  = _get('mulai_efektif','2025-07-21');     // default sesuai permintaan

// Ambil nama TA (format "YYYY/YYYY+1")
$ta_row = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT ta_nama FROM ta WHERE ta_id=$TA"));
$ta_nama = trim($ta_row['ta_nama'] ?? '');
preg_match('/(\d{4})\D+(\d{4})/',$ta_nama,$m);
$y1 = isset($m[1]) ? (int)$m[1] : (int)date('Y');
$y2 = isset($m[2]) ? (int)$m[2] : $y1+1;

// Batas semester
if($semester==2){ $semStart="$y2-01-01"; $semEnd="$y2-06-30"; }
else{ $semStart="$y1-07-01"; $semEnd="$y1-12-31"; }

// Mulai Efektif (dipatok dlm [semStart .. min(today, semEnd)])
$upperBound = ($today < $semEnd) ? $today : $semEnd;
$mulaiEf = $mulai_efektif;
if(!valid_ymd($mulaiEf)) $mulaiEf = '2025-07-21';
if($mulaiEf < $semStart) $mulaiEf = $semStart;
if($mulaiEf > $upperBound) $mulaiEf = $upperBound;

// Rentang tanggal
if($mode==='kustom' && valid_ymd($kustom_awal) && valid_ymd($kustom_akhir)){
  $awal = ymd($kustom_awal); $akhir= ymd($kustom_akhir);
}else{
  $awal = $mulaiEf; $akhir = $upperBound;
}
if($awal > $akhir) $akhir = $awal;

// Libur nasional
$libur = [];
$res_libur = @mysqli_query($koneksi,"SELECT tgl FROM libur_nasional WHERE tgl BETWEEN '$awal' AND '$akhir'");
if($res_libur){ while($r=mysqli_fetch_row($res_libur)){ $libur[$r[0]]=true; } }

// Hari efektif (prioritas tabel hari_efektif)
$efektif = [];
$has_hari_efektif = false;
if (table_exists($koneksi,'hari_efektif')) {
  $qhe = @mysqli_query($koneksi,"SELECT tanggal FROM hari_efektif WHERE ta_id=$TA AND is_efektif=1 AND tanggal BETWEEN '$awal' AND '$akhir' ORDER BY tanggal");
  if($qhe && mysqli_num_rows($qhe)>0){
    $has_hari_efektif = true;
    while($r=mysqli_fetch_row($qhe)){ $efektif[]=$r[0]; }
  }
}
if(!$has_hari_efektif){
  $d = strtotime($awal); $e=strtotime($akhir);
  while($d<=$e){
    $w = (int)date('N',$d); // 1=Mon..7=Sun
    $is_schoolday = $hari_sekolah==6 ? ($w<=6) : ($w<=5);
    $tgl = date('Y-m-d',$d);
    if($is_schoolday && empty($libur[$tgl])) $efektif[]=$tgl;
    $d = strtotime('+1 day',$d);
  }
}
$hari_efektif = count($efektif);

// ======= HITUNG MINGGU EFEKTIF (basis per minggu) =======
$weeks = [];
foreach($efektif as $tgl){ $weeks[date('oW', strtotime($tgl))] = true; }
$minggu_efektif = count($weeks); // untuk IM (pertemuan/minggu)

// Daftar kelas (untuk filter)
$sql_kelas = "SELECT DISTINCT k.kelas_id, k.kelas_nama
FROM kelas k
JOIN absensi_harian ah ON ah.kelas_id=k.kelas_id
WHERE ah.ta_id=$TA AND ah.tanggal BETWEEN '$awal' AND '$akhir'
ORDER BY k.kelas_nama";
$kelas_rs = mysqli_query($koneksi,$sql_kelas);

// Daftar mapel (untuk filter)
$sql_mapel = "SELECT DISTINCT m.mapel_id, m.mapel_nama
FROM mapel m
JOIN absensi_sesi s ON s.mapel_id=m.mapel_id
WHERE s.ta_id=$TA AND s.tanggal BETWEEN '$awal' AND '$akhir'
ORDER BY m.mapel_nama";
$mapel_rs = mysqli_query($koneksi,$sql_mapel);

// ===== RINGKAS =====
$filter_kelas = $kelas_id ? " AND ah.kelas_id=$kelas_id " : "";
$q_sum = mysqli_query($koneksi,"
  SELECT 
    SUM(CASE WHEN ahd.status='H' THEN 1 ELSE 0 END) AS H,
    SUM(CASE WHEN ahd.status='S' THEN 1 ELSE 0 END) AS S,
    SUM(CASE WHEN ahd.status='I' THEN 1 ELSE 0 END) AS I,
    SUM(CASE WHEN ahd.status='A' THEN 1 ELSE 0 END) AS A,
    COUNT(*) AS ttl
  FROM absensi_harian_detail ahd
  JOIN absensi_harian ah ON ahd.harian_id=ah.harian_id
  WHERE ah.ta_id=$TA AND ah.status='final' 
    AND ah.tanggal BETWEEN '$awal' AND '$akhir' 
    $filter_kelas
");
$sum = mysqli_fetch_assoc($q_sum) ?: ['H'=>0,'S'=>0,'I'=>0,'A'=>0,'ttl'=>0];

// ===== PER KELAS =====
$sql_per_kelas = "
SELECT ah.kelas_id, k.kelas_nama,
  SUM(ahd.status='H') AS H,
  SUM(ahd.status='S') AS S,
  SUM(ahd.status='I') AS I,
  SUM(ahd.status='A') AS A,
  COUNT(*) AS ttl
FROM absensi_harian_detail ahd
JOIN absensi_harian ah ON ah.harian_id=ahd.harian_id
JOIN kelas k ON k.kelas_id=ah.kelas_id
WHERE ah.ta_id=$TA AND ah.status='final'
  AND ah.tanggal BETWEEN '$awal' AND '$akhir'
GROUP BY ah.kelas_id, k.kelas_nama
ORDER BY k.kelas_nama";
$per_kelas = mysqli_query($koneksi,$sql_per_kelas);

// ===== PER SISWA (opsional filter kelas) =====
$filter_kelas2 = $kelas_id ? " AND ah.kelas_id=$kelas_id " : "";
$sql_per_siswa = "
SELECT ahd.siswa_id, s.siswa_nama, MAX(ah.kelas_id) AS kelas_id, k.kelas_nama,
  SUM(ahd.status='H') AS H,
  SUM(ahd.status='S') AS S,
  SUM(ahd.status='I') AS I,
  SUM(ahd.status='A') AS A,
  COUNT(*) AS ttl
FROM absensi_harian_detail ahd
JOIN absensi_harian ah ON ah.harian_id=ahd.harian_id
JOIN siswa s ON s.siswa_id=ahd.siswa_id
LEFT JOIN kelas k ON k.kelas_id=ah.kelas_id
WHERE ah.ta_id=$TA AND ah.status='final'
  AND ah.tanggal BETWEEN '$awal' AND '$akhir'
  $filter_kelas2
GROUP BY ahd.siswa_id, s.siswa_nama, k.kelas_nama
ORDER BY k.kelas_nama, s.siswa_nama";
$per_siswa = mysqli_query($koneksi,$sql_per_siswa);

// ===== MONITOR PER MAPEL (MINGGUAN) =====
$filter_mapel = $mapel_id ? " AND s.mapel_id=$mapel_id " : "";
$filter_kelas3 = $kelas_id ? " AND s.kelas_id=$kelas_id " : "";

// (A) RINGKAS per MAPEL (lintas semua kelas)
$sql_mapel_ringkas = "
SELECT 
  m.mapel_id, 
  m.mapel_nama,
  COUNT(DISTINCT s.kelas_id) AS kelas_diampu,
  COUNT(DISTINCT CONCAT(DATE(s.tanggal),'#',s.kelas_id)) AS pertemuan_final,
  MAX(s.tanggal) AS last_input
FROM mapel m
LEFT JOIN absensi_sesi s
  ON s.mapel_id = m.mapel_id
 AND s.ta_id    = $TA
 AND s.status   = 'final'
 AND s.tanggal BETWEEN '$awal' AND '$akhir'
 $filter_mapel $filter_kelas3
GROUP BY m.mapel_id, m.mapel_nama
ORDER BY m.mapel_nama";
$rs_mapel_ringkas = mysqli_query($koneksi,$sql_mapel_ringkas);

$mapel_ringkas = [];
while($r=mysqli_fetch_assoc($rs_mapel_ringkas)){
  $pertemuan = (int)$r['pertemuan_final'];
  $im = $minggu_efektif>0 ? round($pertemuan/$minggu_efektif,2) : 0.00;
  $last = $r['last_input'] ?: null;
  $days = $last ? (int)((strtotime(date('Y-m-d')) - strtotime($last))/86400) : null;
  $mapel_ringkas[] = [
    'mapel_id'       => (int)$r['mapel_id'],
    'mapel_nama'     => $r['mapel_nama'],
    'kelas_diampu'   => (int)$r['kelas_diampu'],
    'pertemuan'      => $pertemuan,
    'minggu_efektif' => $minggu_efektif,
    'im'             => $im,
    'last_input'     => $last,
    'days_since'     => $days
  ];
}

// (B) DETAIL per MAPEL × KELAS
$sql_mapel_detail = "
SELECT 
  m.mapel_id, m.mapel_nama,
  k.kelas_id, k.kelas_nama,
  COUNT(DISTINCT DATE(s.tanggal)) AS pertemuan_kelas,
  MAX(s.tanggal) AS last_input
FROM absensi_sesi s
JOIN mapel m ON m.mapel_id=s.mapel_id
JOIN kelas k ON k.kelas_id=s.kelas_id
WHERE s.ta_id=$TA
  AND s.status='final'
  AND s.tanggal BETWEEN '$awal' AND '$akhir'
  $filter_mapel $filter_kelas3
GROUP BY m.mapel_id, m.mapel_nama, k.kelas_id, k.kelas_nama
ORDER BY m.mapel_nama, k.kelas_nama";
$rs_mapel_detail = mysqli_query($koneksi,$sql_mapel_detail);

$mapel_detail = [];
while($r=mysqli_fetch_assoc($rs_mapel_detail)){
  $pertemuan = (int)$r['pertemuan_kelas'];
  $im = $minggu_efektif>0 ? round($pertemuan/$minggu_efektif,2) : 0.00;
  $last = $r['last_input'] ?: null;
  $days = $last ? (int)((strtotime(date('Y-m-d')) - strtotime($last))/86400) : null;
  $mapel_detail[] = [
    'mapel_id'       => (int)$r['mapel_id'],
    'mapel_nama'     => $r['mapel_nama'],
    'kelas_id'       => (int)$r['kelas_id'],
    'kelas_nama'     => $r['kelas_nama'],
    'pertemuan'      => $pertemuan,
    'minggu_efektif' => $minggu_efektif,
    'im'             => $im,
    'last_input'     => $last,
    'days_since'     => $days
  ];
}

// (C) *** PER GURU (DETAIL) *** — pakai absensi_sesi.guru_user_id -> user.user_id
$sql_guru_ringkas = "
SELECT 
  u.user_id,
  u.user_nama,
  COUNT(DISTINCT CASE WHEN s.status='final' THEN s.kelas_id END) AS kelas_diampu,
  COUNT(DISTINCT CASE WHEN s.status='final' THEN s.mapel_id END) AS mapel_diampu,
  COUNT(DISTINCT CASE WHEN s.status='final' THEN CONCAT(DATE(s.tanggal),'#',s.kelas_id) END) AS pertemuan_final,
  MAX(s.tanggal) AS last_input
FROM (
  SELECT DISTINCT guru_user_id FROM absensi_sesi WHERE guru_user_id IS NOT NULL
) g
JOIN user u ON u.user_id=g.guru_user_id
LEFT JOIN absensi_sesi s 
  ON s.guru_user_id = u.user_id
 AND s.ta_id = $TA
 AND s.tanggal BETWEEN '$awal' AND '$akhir'
 $filter_mapel $filter_kelas3
GROUP BY u.user_id, u.user_nama
ORDER BY u.user_nama";
$rs_guru = mysqli_query($koneksi,$sql_guru_ringkas);

$guru_rows = [];
while($r=mysqli_fetch_assoc($rs_guru)){
  $pertemuan = (int)$r['pertemuan_final'];
  $im = $minggu_efektif>0 ? round($pertemuan/$minggu_efektif,2) : 0.00;
  $last = $r['last_input'] ?: null;
  $days = $last ? (int)((strtotime(date('Y-m-d')) - strtotime($last))/86400) : null;
  $guru_rows[] = [
    'user_id'        => (int)$r['user_id'],
    'user_nama'      => $r['user_nama'],
    'mapel_diampu'   => (int)$r['mapel_diampu'],
    'kelas_diampu'   => (int)$r['kelas_diampu'],
    'pertemuan'      => $pertemuan,
    'minggu_efektif' => $minggu_efektif,
    'im'             => $im,
    'last_input'     => $last,
    'days_since'     => $days
  ];
}
?>
<style>
  .filter-card .form-group{ margin-bottom:10px;}
  .table-sticky thead th{ position:sticky; top:0; background:#fff; z-index:2;}
  .table-responsive{ overflow-x:auto; }

  /* Toolbar */
  .toolbar{ display:flex; gap:10px; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; }

  /* Tombol Print elegan — dirampingkan */
  .btn-print-fancy{
    display:inline-flex; align-items:center; gap:6px;
    color:#fff; border:0; font-weight:800; border-radius:10px;
    background:linear-gradient(135deg,#10b981,#059669);
    box-shadow:0 6px 14px rgba(16,185,129,.25);
    padding:6px 10px; font-size:13px; letter-spacing:.2px;
    transition:transform .12s ease, box-shadow .18s ease, opacity .12s ease;
  }
  .btn-print-fancy:hover{ transform:translateY(-1px); box-shadow:0 8px 18px rgba(16,185,129,.35); text-decoration:none; }
  .btn-print-fancy:active{ transform:translateY(0); box-shadow:0 4px 10px rgba(16,185,129,.3); }
  .btn-print-fancy:focus{ outline:0; box-shadow:0 0 0 3px rgba(16,185,129,.25); }
  .btn-print-fancy .fa{ font-size:13px; }

  /* Tombol Terapkan */
  .btn-apply{
    position: relative; overflow: hidden; border: none; color: #fff; font-weight: 700;
    background: linear-gradient(135deg,#6a5af9,#0ea5e9);
    box-shadow: 0 6px 18px rgba(14,165,233,.25);
    transition: transform .15s ease, box-shadow .2s ease, opacity .2s ease;
    border-radius:8px; padding:8px 12px;
  }
  .btn-apply:hover{ transform: translateY(-1px); box-shadow: 0 8px 20px rgba(14,165,233,.35); }
  .btn-apply.pending{ animation: btnPulse 1.4s ease-in-out infinite; }
  .btn-apply.pending:after{
    content:''; position:absolute; top:0; left:-150%; width:150%; height:100%;
    background: linear-gradient(120deg, transparent, rgba(255,255,255,.35), transparent);
    animation: shimmer 1.2s linear infinite;
  }
  @keyframes shimmer { 0%{left:-150%;} 100%{left:150%;} }
  @keyframes btnPulse { 0%,100%{ box-shadow: 0 0 0 0 rgba(14,165,233,.35);} 50%{ box-shadow: 0 0 0 6px rgba(14,165,233,.12);} }

  /* (lama) Nav tabs: sebelumnya pakai child selector langsung */
  .nav-tabs-custom{ border:0; }
  .nav-tabs-custom > .nav-tabs{ border-bottom:0; display:flex; gap:0; }
  .nav-tabs-custom > .nav-tabs > li{ margin:0; }
  .nav-tabs-custom > .nav-tabs > li > a{
    border:0; margin:0; border-radius:0; padding:10px 14px;
    background:#f3f4f6; color:#334155; transition:all .15s ease; font-weight:700;
  }
  .nav-tabs-custom > .nav-tabs > li > a:hover{
    background:#ffe9d5; color:#b45309; box-shadow:0 6px 14px rgba(244,114,35,.2);
  }
  .nav-tabs-custom > .nav-tabs > li.active > a,
  .nav-tabs-custom > .nav-tabs > li.active > a:focus,
  .nav-tabs-custom > .nav-tabs > li.active > a:hover{
    background:linear-gradient(135deg,#0ea5e9,#3b82f6);
    color:#fff; font-weight:800; box-shadow:0 10px 24px rgba(59,130,246,.35);
    border-radius:0;
  }

  /* Sub-toggle dalam tab Per Mapel */
  .segmented{ display:inline-flex; border-radius:8px; overflow:hidden; border:1px solid #e5e7eb; background:#fff; }
  .segmented button{ padding:6px 10px; border:0; background:#fff; }
  .segmented button.active{ background:#e2e8f0; font-weight:700; }
  .segmented button + button{ border-left:1px solid #e5e7eb; }

  /* DataTables responsive */
  table.dataTable thead th, table.dataTable thead td { white-space:nowrap; }
  .dataTables_wrapper .dataTables_filter input{ border-radius:8px; }
  .dataTables_wrapper .dataTables_length select{ border-radius:8px; }
  .dataTables_wrapper .dataTables_info{ padding-top:0.6rem; }
  .dataTables_wrapper .dataTables_paginate{ padding-top:0.2rem; }

  /* Badge/pill */
  .pill{ display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; font-weight:700; }
  .pill.green{ background:#dcfce7; color:#166534; }
  .pill.yellow{ background:#fef9c3; color:#854d0e; }
  .pill.red{ background:#fee2e2; color:#991b1b; }

  /* ====== Modal Detail Siswa ====== */
  .modal-chip{ display:inline-block; margin:2px 4px; }
  .badge-H{ background:#22c55e; }
  .badge-S{ background:#06b6d4; }
  .badge-I{ background:#eab308; }
  .badge-A{ background:#ef4444; }
  .status-badge{ display:inline-block; color:#fff; border-radius:6px; padding:2px 8px; font-weight:700; font-size:12px; }
  .filter-inline{ display:flex; gap:8px; align-items:center; flex-wrap:wrap; }

  /* ========== TAMBAHAN: judul + badge biru soft + header tab sejajar tombol print ========== */
  .page-title{
    display:flex; align-items:center; gap:10px; margin:0; font-weight:800; letter-spacing:.2px;
  }
  .page-title .fa{ color:#0ea5e9; }
  .badge-soft-blue{
    background:#eaf3ff; color:#1d4ed8; border-radius:999px; padding:4px 10px; font-size:12px; font-weight:700;
  }
  .tabs-header-flex{ display:flex; align-items:center; justify-content:space-between; gap:8px; padding:0 0 6px 0; }
  .tabs-actions{ margin-left:auto; }
  @media (min-width:768px){
    .toolbar .btn-print-fancy{ display:none !important; }
  }

  /* ======= OVERRIDE FIX: selector tanpa '>' agar cocok dg .tabs-header-flex wrapper ======= */
  .nav-tabs-custom .nav-tabs{ border-bottom:0; display:flex; gap:0; }
  .nav-tabs-custom .nav-tabs > li{ margin:0; border-top:3px solid transparent !important; }
  .nav-tabs-custom .nav-tabs > li > a{
    border:0 !important; margin:0; border-radius:0 !important; padding:10px 14px;
    background:#ffffff !important; color:#374151 !important; font-weight:700;
    transition:all .15s ease;
    display:block;
  }
  /* Hover biru soft untuk SEMUA tab (tidak aktif) */
  .nav-tabs-custom .nav-tabs > li:not(.active) > a:hover,
  .nav-tabs-custom .nav-tabs > li:not(.active) > a:focus{
    background:#eaf3ff !important; color:#1d4ed8 !important;
    box-shadow:0 6px 14px rgba(29,78,216,.15);
  }
  /* Tab aktif biru */
  .nav-tabs-custom .nav-tabs > li.active{ border-top-color:#3b82f6 !important; }
  .nav-tabs-custom .nav-tabs > li.active > a,
  .nav-tabs-custom .nav-tabs > li.active > a:focus,
  .nav-tabs-custom .nav-tabs > li.active > a:hover{
    background:linear-gradient(135deg,#0ea5e9,#3b82f6) !important;
    color:#fff !important; font-weight:800;
    box-shadow:0 10px 24px rgba(59,130,246,.35);
  }
  /* Backstop untuk Bootstrap default */
  .nav-tabs > li > a:hover{ background:#eaf3ff !important; color:#1d4ed8 !important; }
  .nav-tabs > li.active > a,
  .nav-tabs > li.active > a:hover,
  .nav-tabs > li.active > a:focus{
    background:linear-gradient(135deg,#0ea5e9,#3b82f6) !important;
    color:#fff !important; border:0 !important;
  }
</style>

<div class="content-wrapper">
<section class="content-header">
  <h1 class="page-title">
    <i class="fa fa-calendar-check-o"></i>
    <span>Laporan Absensi Lengkap</span>
    <span class="badge-soft-blue">(Harian, Semester, Per Mapel)</span>
  </h1>
  <ol class="breadcrumb">
    <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Laporan Absensi</li>
  </ol>
</section>

<section class="content">

<div class="box box-primary filter-card">
  <div class="box-header with-border"><h3 class="box-title">Filter</h3></div>
  <div class="box-body">
    <form class="form-inline js-filter-form" method="get">
      <div class="row">
        <div class="col-sm-2">
          <label>Tahun Ajaran</label>
          <select name="ta" class="form-control" style="width:100%">
            <?php mysqli_data_seek($tas,0); while($t=mysqli_fetch_assoc($tas)): ?>
              <option value="<?=i($t['ta_id'])?>" <?=$TA==i($t['ta_id'])?'selected':'';?>>
                <?=esc($t['ta_nama'])?>
              </option>
            <?php endwhile;?>
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
          <input type="date" name="mulai_efektif" value="<?=esc($mulaiEf)?>" class="form-control" style="width:100%">
        </div>

        <div class="col-sm-2 mode-wrap mode-kustom" style="display:none">
          <label>Tgl Mulai</label>
          <input type="date" name="awal" value="<?=esc($awal)?>" class="form-control" style="width:100%">
        </div>
        <div class="col-sm-2 mode-wrap mode-kustom" style="display:none">
          <label>Tgl Akhir</label>
          <input type="date" name="akhir" value="<?=esc($akhir)?>" class="form-control" style="width:100%">
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
            <?php mysqli_data_seek($kelas_rs,0); while($k=mysqli_fetch_assoc($kelas_rs)): ?>
              <option value="<?=i($k['kelas_id'])?>" <?=$kelas_id==i($k['kelas_id'])?'selected':'';?>>
                <?=esc($k['kelas_nama'])?>
              </option>
            <?php endwhile;?>
          </select>
        </div>

        <div class="col-sm-3">
          <label>Filter Mapel (opsional)</label>
          <select name="mapel" class="form-control" style="width:100%">
            <option value="0">Semua Mapel</option>
            <?php while($m=mysqli_fetch_assoc($mapel_rs)): ?>
              <option value="<?=i($m['mapel_id'])?>" <?=$mapel_id==i($m['mapel_id'])?'selected':'';?>>
                <?=esc($m['mapel_nama'])?>
              </option>
            <?php endwhile;?>
          </select>
        </div>

        <div class="col-sm-3">
          <label>&nbsp;</label>
          <div style="display:flex; gap:8px; align-items:center;">
            <button id="btnTerapkan" class="btn btn-apply" type="submit"><i class="fa fa-search"></i> Terapkan</button>
            <a class="btn btn-default" style="border-radius:8px;" href="laporan_absensi.php"><i class="fa fa-undo"></i> Reset</a>
          </div>
          <small class="text-muted" id="hintTerapkan" style="display:none">Ada perubahan filter. Klik <b>Terapkan</b> untuk memuat data.</small>
        </div>
      </div>
    </form>
  </div>
  <div class="box-footer">
    <small>
      Sumber hari efektif: <?=$has_hari_efektif?'tabel <b>hari_efektif</b>':'perhitungan weekdays ('.$hari_sekolah.' hari) – libur nasional';?>.
      TA: <?=esc($ta_nama)?>, Rentang: <b><?=esc($awal)?> s/d <?=esc($akhir)?></b> <?=$mode==='semester' ? '(mulai efektif: <b>'.esc($mulaiEf).'</b>)' : '';?>.
      • Minggu Efektif: <b><?=$minggu_efektif?></b>
    </small>
  </div>
</div>

<div class="nav-tabs-custom">
  <div class="tabs-header-flex">
    <ul class="nav nav-tabs">
      <li class="active"><a href="#tab-siswa" data-toggle="tab">Per Siswa</a></li>
      <li><a href="#tab-kelas" data-toggle="tab">Per Kelas</a></li>
      <li><a href="#tab-mapel" data-toggle="tab">Per Mapel</a></li>
    </ul>
    <div class="tabs-actions">
      <button type="button" class="btn btn-print-fancy js-print-fixed">
        <i class="fa fa-print"></i> Print
      </button>
    </div>
  </div>

  <div class="tab-content">

    <div class="tab-pane active" id="tab-siswa">
      <div class="toolbar">
        <button class="btn btn-print-fancy" onclick="printTable('tblSiswa','Laporan Absensi - Per Siswa')">
          <i class="fa fa-print"></i> Print
        </button>
      </div>
      <div class="table-responsive">
        <table id="tblSiswa" class="table table-bordered table-striped table-sticky">
          <thead>
            <tr>
              <th>No</th>
              <th>Kelas</th>
              <th>Nama Siswa</th>
              <th>H</th>
              <th>S</th>
              <th>I</th>
              <th>A</th>
              <th>Jml Sesi</th>
              <th>% Hadir</th>
              <th>Detail</th>
            </tr>
          </thead>
          <tbody>
            <?php mysqli_data_seek($per_siswa,0); while($r=mysqli_fetch_assoc($per_siswa)):
              $pct = $r['ttl']? round(100*$r['H']/$r['ttl'],1):0; ?>
              <tr>
                <td></td>
                <td><?=esc($r['kelas_nama'])?></td>
                <td><?=esc($r['siswa_nama'])?></td>
                <td><span class="badge bg-green"><?=$r['H']?></span></td>
                <td><span class="badge bg-aqua"><?=$r['S']?></span></td>
                <td><span class="badge bg-yellow"><?=$r['I']?></span></td>
                <td><span class="badge bg-red"><?=$r['A']?></span></td>
                <td><?=$r['ttl']?></td>
                <td><b><?=$pct?>%</b></td>
                <td>
                  <button class="btn btn-xs btn-info js-detail-siswa" 
                          data-sid="<?=i($r['siswa_id'])?>"
                          data-nama="<?=esc($r['siswa_nama'])?>">
                    <i class="fa fa-search"></i> Detail
                  </button>
                </td>
              </tr>
            <?php endwhile;?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="tab-pane" id="tab-kelas">
      <div class="toolbar">
        <button class="btn btn-print-fancy" onclick="printTable('tblKelas','Laporan Absensi - Per Kelas')">
          <i class="fa fa-print"></i> Print
        </button>
      </div>
      <div class="small muted" style="margin:6px 0 10px 0"><i class="fa fa-info-circle"></i> Tab <b>Per Kelas</b> menampilkan agregat kehadiran seluruh siswa per kelas pada periode terpilih. Kolom <b>H,S,I,A</b> menunjukkan jumlah kejadian (bukan siswa), <b>% Hadir</b> memudahkan perbandingan antar kelas, dan <b>Jml Sesi</b> memberi konteks banyaknya pertemuan. Gunakan ini untuk memantau kelas mana yang paling konsisten hadir atau perlu perhatian.</div>
<div class="table-responsive">
        <table id="tblKelas" class="table table-bordered table-striped table-sticky">
          <thead>
            <tr>
              <th>No</th>
              <th>Kelas</th>
              <th>H</th>
              <th>S</th>
              <th>I</th>
              <th>A</th>
              <th>Jml Sesi</th>
              <th>% Hadir</th>
            </tr>
          </thead>
          <tbody>
            <?php mysqli_data_seek($per_kelas,0); while($r=mysqli_fetch_assoc($per_kelas)):
              $pct = $r['ttl']? round(100*$r['H']/$r['ttl'],1):0; ?>
              <tr>
                <td></td>
                <td><?=esc($r['kelas_nama'])?></td>
                <td><span class="badge bg-green"><?=$r['H']?></span></td>
                <td><span class="badge bg-aqua"><?=$r['S']?></span></td>
                <td><span class="badge bg-yellow"><?=$r['I']?></span></td>
                <td><span class="badge bg-red"><?=$r['A']?></span></td>
                <td><?=$r['ttl']?></td>
                <td><b><?=$pct?>%</b></td>
              </tr>
            <?php endwhile;?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="tab-pane" id="tab-mapel">
      <div class="toolbar">
        <button class="btn btn-print-fancy" onclick="printTable(currentMapelTableId(),'Monitor Aktivitas Absensi - ' + currentMapelModeLabel())">
          <i class="fa fa-print"></i> Print
        </button>

        <div class="segmented" role="group" aria-label="Mode tampilan mapel">
          <button type="button" class="js-mode-mapel active" data-target="#wrapRingkas" title="Ringkas (Mapel)">Ringkas (Mapel)</button>
          <button type="button" class="js-mode-mapel" data-target="#wrapGuru" title="Per Guru (Detail)">Per Guru (Detail)</button>
          <button type="button" class="js-mode-mapel" data-target="#wrapDetail" title="Per Kelas (Mapel×Kelas)">Per Kelas</button>
        </div>
      </div>

      <div id="wrapRingkas">
        <div class="table-responsive">
          <table id="tblMapelRingkas" class="table table-bordered table-striped table-sticky">
            <thead>
              <tr>
                <th>No</th>
                <th>Mapel</th>
                <th>Kelas Diampu</th>
                <th>Pertemuan Final</th>
                <th>Minggu Efektif</th>
                <th>IM (Pertemuan/Minggu)</th>
                <th>Terakhir Input</th>
                <th>Hari Sejak Terakhir</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($mapel_ringkas as $r): 
                $badge = ($r['im']>=1.5)?'green':(($r['im']>=0.8)?'yellow':'red');
                $badgeDays = is_null($r['days_since'])? '' : (($r['days_since']<=7)?'green':(($r['days_since']<=14)?'yellow':'red'));
              ?>
                <tr>
                  <td></td>
                  <td><?=esc($r['mapel_nama'])?></td>
                  <td><?=$r['kelas_diampu']?></td>
                  <td><?=$r['pertemuan']?></td>
                  <td><?=$r['minggu_efektif']?></td>
                  <td><span class="pill <?=$badge?>"><?=$r['im']?></span></td>
                  <td><?= $r['last_input'] ? esc($r['last_input']) : '<i>—</i>' ?></td>
                  <td><?= is_null($r['days_since']) ? '<i>—</i>' : '<span class="pill '.$badgeDays.'">'.$r['days_since'].' hr</span>' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div id="wrapGuru" style="display:none">
        <div class="table-responsive">
          <table id="tblMapelGuru" class="table table-bordered table-striped table-sticky">
            <thead>
              <tr>
                <th>No</th>
                <th>Guru</th>
                <th>Mapel Diampu</th>
                <th>Kelas Diampu</th>
                <th>Pertemuan Final</th>
                <th>Minggu Efektif</th>
                <th>IM (Pertemuan/Minggu)</th>
                <th>Terakhir Input</th>
                <th>Hari Sejak Terakhir</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($guru_rows as $r): 
                $badge = ($r['im']>=1.5)?'green':(($r['im']>=0.8)?'yellow':'red');
                $badgeDays = is_null($r['days_since'])? '' : (($r['days_since']<=7)?'green':(($r['days_since']<=14)?'yellow':'red'));
              ?>
                <tr>
                  <td></td>
                  <td><?=esc($r['user_nama'])?></td>
                  <td><?=$r['mapel_diampu']?></td>
                  <td><?=$r['kelas_diampu']?></td>
                  <td><?=$r['pertemuan']?></td>
                  <td><?=$r['minggu_efektif']?></td>
                  <td><span class="pill <?=$badge?>"><?=$r['im']?></span></td>
                  <td><?= $r['last_input'] ? esc($r['last_input']) : '<i>—</i>' ?></td>
                  <td><?= is_null($r['days_since']) ? '<i>—</i>' : '<span class="pill '.$badgeDays.'">'.$r['days_since'].' hr</span>' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="help-block" style="margin-top:8px">
          *Mode ini menampilkan aktivitas absensi per guru lintas mapel/kelas (dalam rentang). Urutkan kolom IM atau “Hari Sejak Terakhir” untuk melihat yang paling aktif / jarang mengisi.
        </p>
      </div>

      <div id="wrapDetail" style="display:none">
        <div class="table-responsive">
          <table id="tblMapelDetail" class="table table-bordered table-striped table-sticky">
            <thead>
              <tr>
                <th>No</th>
                <th>Mapel</th>
                <th>Kelas</th>
                <th>Pertemuan Final</th>
                <th>Minggu Efektif</th>
                <th>IM (Pertemuan/Minggu)</th>
                <th>Terakhir Input</th>
                <th>Hari Sejak Terakhir</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($mapel_detail as $r): 
                $badge = ($r['im']>=1.5)?'green':(($r['im']>=0.8)?'yellow':'red');
                $badgeDays = is_null($r['days_since'])? '' : (($r['days_since']<=7)?'green':(($r['days_since']<=14)?'yellow':'red'));
              ?>
                <tr>
                  <td></td>
                  <td><?=esc($r['mapel_nama'])?></td>
                  <td><?=esc($r['kelas_nama'])?></td>
                  <td><?=$r['pertemuan']?></td>
                  <td><?=$r['minggu_efektif']?></td>
                  <td><span class="pill <?=$badge?>"><?=$r['im']?></span></td>
                  <td><?= $r['last_input'] ? esc($r['last_input']) : '<i>—</i>' ?></td>
                  <td><?= is_null($r['days_since']) ? '<i>—</i>' : '<span class="pill '.$badgeDays.'">'.$r['days_since'].' hr</span>' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="help-block" style="margin-top:8px">
          *IM = rata-rata pertemuan/minggu selama rentang. Gunakan urutan kolom IM atau Hari Sejak Terakhir untuk melihat yang paling aktif / jarang mengisi per kelas.
        </p>
      </div>

    </div></div>
</div>

<div id="modalDetailSiswa" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="mdlDetailLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="mdlDetailLabel"><i class="fa fa-user"></i> Detail Absensi: <span id="mdlNamaSiswa">Siswa</span>
          <button type="button" id="btnReloadDetail" class="btn btn-default btn-xs" title="Muat ulang data detail" style="margin-left:8px"><i class="fa fa-refresh"></i></button>
        </h4>
      </div>
      <div class="modal-body">
        <div class="filter-inline" style="margin-bottom:8px">
          <div>
            <span class="status-badge badge-H">Hadir: <b id="rekH">0</b></span>
            <span class="status-badge badge-S">Sakit: <b id="rekS">0</b></span>
            <span class="status-badge badge-I">Izin: <b id="rekI">0</b></span>
            <span class="status-badge badge-A">Alpha: <b id="rekA">0</b></span>
            <span class="pill" style="background:#e5e7eb;color:#111827">Jml Sesi: <b id="rekT">0</b> • %Hadir: <b id="rekPct">0%</b></span>
          </div>
          <div style="margin-left:auto"></div>
          <div>
            <div class="segmented" id="segStatus">
              <button type="button" data-st="" class="active">Semua</button>
              <button type="button" data-st="H">H</button>
              <button type="button" data-st="S">S</button>
              <button type="button" data-st="I">I</button>
              <button type="button" data-st="A">A</button>
            </div>
          </div>
          <div>
            <select id="filterKelasDetail" class="form-control" style="min-width:180px">
              <option value="">Semua Kelas</option>
            </select>
          </div>
        </div>

        <div class="table-responsive">
          <table id="tblDetailSiswa" class="table table-bordered table-striped table-sticky" style="width:100%">
            <thead>
              <tr>
                <th style="width:60px">#</th>
                <th>Tanggal</th>
                <th>Kelas</th>
                <th>Status</th>
                <th>Keterangan</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
        <p class="help-block" style="margin-top:8px">
          Tips: gunakan filter <b>Status</b> (H/S/I/A) dan <b>Kelas</b> untuk menelusuri catatan harian. Klik header <b>Tanggal</b> untuk urutkan naik/turun.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

</section>
</div>

<script>
// ===== Sinkron UI mode rentang =====
(function(){
  function syncMode(){
    var m = document.getElementById('mode').value;
    document.querySelectorAll('.mode-wrap').forEach(function(e){ e.style.display='none'; });
    if(m==='semester'){ document.querySelectorAll('.mode-semester').forEach(function(e){ e.style.display=''; }); }
    if(m==='kustom'){   document.querySelectorAll('.mode-kustom').forEach(function(e){ e.style.display=''; }); }
  }
  document.getElementById('mode').addEventListener('change', syncMode);
  syncMode();
})();

// ===== Tombol Terapkan shimmer =====
(function(){
  var form = document.querySelector('.js-filter-form');
  var btn = document.getElementById('btnTerapkan');
  var hint = document.getElementById('hintTerapkan');
  if(!form || !btn) return;
  function markDirty(){ btn.classList.add('pending'); if(hint) hint.style.display=''; }
  function clearDirty(){ btn.classList.remove('pending'); if(hint) hint.style.display='none'; }
  form.querySelectorAll('select,input').forEach(function(el){ el.addEventListener('change', markDirty); el.addEventListener('input', markDirty); });
  form.addEventListener('submit', function(){ clearDirty(); });
  clearDirty();
})();

// ===== Print util =====
function printTable(tableId, titleText){
  var table = document.getElementById(tableId); if(!table) return;
  var w = window.open('', '_blank');
  w.document.write('<html><head><title>'+ (titleText||'Print') +'</title>');
  w.document.write('<style>body{font-family:Arial,sans-serif}table{width:100%;border-collapse:collapse;}th,td{border:1px solid #444;padding:6px;font-size:12px}th{background:#eee}h3{margin:0 0 10px}</style>');
  w.document.write('</head><body>');
  w.document.write('<h3>'+ (titleText||'') +'</h3>');
  w.document.write(table.outerHTML);
  w.document.write('</body></html>');
  w.document.close(); w.focus(); w.print(); w.close();
}
function currentMapelTableId(){
  if (document.getElementById('wrapRingkas').style.display!=='none') return 'tblMapelRingkas';
  if (document.getElementById('wrapGuru').style.display!=='none') return 'tblMapelGuru';
  return 'tblMapelDetail';
}
function currentMapelModeLabel(){
  if (document.getElementById('wrapRingkas').style.display!=='none') return 'Ringkas (Mapel)';
  if (document.getElementById('wrapGuru').style.display!=='none') return 'Per Guru (Detail)';
  return 'Per Kelas';
}

// ===== Print di kanan atas (sejajar tabs) mengikuti tab aktif =====
document.addEventListener('click', function(e){
  var b = e.target.closest('.js-print-fixed');
  if(!b) return;
  var activeTab = document.querySelector('.nav-tabs li.active a');
  var href = activeTab ? activeTab.getAttribute('href') : '#tab-siswa';
  if(href==='#tab-siswa'){
    printTable('tblSiswa','Laporan Absensi - Per Siswa');
  }else if(href==='#tab-kelas'){
    printTable('tblKelas','Laporan Absensi - Per Kelas');
  }else{
    printTable(currentMapelTableId(),'Monitor Aktivitas Absensi - ' + currentMapelModeLabel());
  }
});

// ===== Toggle Ringkas / Per Guru / Per Kelas =====
document.querySelectorAll('.js-mode-mapel').forEach(function(btn){
  btn.addEventListener('click', function(){
    document.querySelectorAll('.js-mode-mapel').forEach(function(b){ b.classList.remove('active'); });
    this.classList.add('active');
    var target = this.getAttribute('data-target');
    document.getElementById('wrapRingkas').style.display = (target==='#wrapRingkas') ? '' : 'none';
    document.getElementById('wrapGuru').style.display    = (target==='#wrapGuru')    ? '' : 'none';
    document.getElementById('wrapDetail').style.display  = (target==='#wrapDetail')  ? '' : 'none';
    // perbaiki layout DataTables setelah show/hide
    setTimeout(function(){
      if($.fn.dataTable){
        $('#tblMapelRingkas').DataTable().columns.adjust().responsive.recalc();
        $('#tblMapelGuru').DataTable().columns.adjust().responsive.recalc();
        $('#tblMapelDetail').DataTable().columns.adjust().responsive.recalc();
      }
    }, 120);
  });
});

// ===== DataTables seragam untuk semua tabel =====
$(function(){
  if ($.fn.dataTable && $.fn.dataTable.ext) $.fn.dataTable.ext.errMode = 'console';

  function makeDT(selector, defaultOrderIdx, defaultOrderDir){
    var t = $(selector).DataTable({
      responsive:true,
      autoWidth:false,
      pageLength:50,                               // default 50 baris
      lengthMenu:[[10,20,50,100,200],[10,20,50,100,200]],
      order:[[defaultOrderIdx||1, defaultOrderDir||'asc']],
      language:{
        searchPlaceholder:'ketik untuk mencari…',
        lengthMenu: 'Show _MENU_ entries'
      },
      dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>rt<"row"<"col-sm-5"i><"col-sm-7"p>>'
    });
    // nomor urut dinamis di kolom 0
    t.on('order.dt search.dt draw.dt', function(){
      let i = 1;
      t.column(0, {search:'applied', order:'applied', page:'current'}).nodes().each(function(cell){
        cell.innerHTML = i++;
      });
    }).draw();
    return t;
  }

  makeDT('#tblSiswa', 1,'asc');
  makeDT('#tblKelas', 1,'asc');

  // Mapel: Ringkas — urut IM desc lalu Pertemuan desc
  var dtRingkas = $('#tblMapelRingkas').DataTable({
    responsive:true, autoWidth:false, pageLength:50,
    lengthMenu:[[10,20,50,100,200],[10,20,50,100,200]],
    order:[[5,'desc'],[3,'desc']], // IM desc, Pertemuan desc
    language:{ searchPlaceholder:'cari mapel…', lengthMenu:'Show _MENU_ entries' },
    dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>rt<"row"<"col-sm-5"i><"col-sm-7"p>>'
  });
  dtRingkas.on('order.dt search.dt draw.dt', function(){
    let i = 1;
    dtRingkas.column(0, {search:'applied', order:'applied', page:'current'}).nodes().each(function(cell){ cell.innerHTML = i++; });
  }).draw();

  // Mapel: Per Guru — urut IM desc lalu Pertemuan desc
  var dtGuru = $('#tblMapelGuru').DataTable({
    responsive:true, autoWidth:false, pageLength:50,
    lengthMenu:[[10,20,50,100,200],[10,20,50,100,200]],
    order:[[6,'desc'],[4,'desc']],
    language:{ searchPlaceholder:'cari guru…', lengthMenu:'Show _MENU_ entries' },
    dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>rt<"row"<"col-sm-5"i><"col-sm-7"p>>'
  });
  dtGuru.on('order.dt search.dt draw.dt', function(){
    let i = 1;
    dtGuru.column(0, {search:'applied', order:'applied', page:'current'}).nodes().each(function(cell){ cell.innerHTML = i++; });
  }).draw();

  // Mapel: Per Kelas — urut IM desc lalu Pertemuan desc
  var dtDetail = $('#tblMapelDetail').DataTable({
    responsive:true, autoWidth:false, pageLength:50,
    lengthMenu:[[10,20,50,100,200],[10,20,50,100,200]],
    order:[[5,'desc'],[3,'desc']],
    language:{ searchPlaceholder:'cari mapel/kelas…', lengthMenu:'Show _MENU_ entries' },
    dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>rt<"row"<"col-sm-5"i><"col-sm-7"p>>'
  });
  dtDetail.on('order.dt search.dt draw.dt', function(){
    let i = 1;
    dtDetail.column(0, {search:'applied', order:'applied', page:'current'}).nodes().each(function(cell){ cell.innerHTML = i++; });
  }).draw();

  // ====== DETAIL PER SISWA (AJAX + MODAL) ======
  window.dtDetailSiswa = null; // DataTable instance di modal (global utk cleanup)
  var currentDetailSid = null;
  var currentDetailNama = '';
  var detailReqSeq = 0; // sequence untuk mengabaikan respons yang kedaluwarsa

  function buildQueryParams(){
    var params = new URLSearchParams(window.location.search);
    var form = document.querySelector('.js-filter-form');
    if(form){
      ['ta','mode','semester','mulai_efektif','awal','akhir','hari_sekolah','kelas','mapel'].forEach(function(n){
        var el=form.querySelector('[name="'+n+'"]');
        if(el && el.value!==undefined){ params.set(n, el.value); }
      });
    }
    return params;
  }

  function setLoadingState(nama){
    $('#mdlNamaSiswa').text(nama||'Siswa');
    $('#rekH,#rekS,#rekI,#rekA,#rekT').text('…'); $('#rekPct').text('…');
    var tbody = document.querySelector('#tblDetailSiswa tbody');
    tbody.innerHTML='<tr><td colspan="5"><i class="fa fa-spinner fa-spin"></i> Memuat…</td></tr>';
  }

  function fillDetailModal(data){
    $('#mdlNamaSiswa').text(data.siswa.nama);
    $('#rekH').text(data.rekap.H); $('#rekS').text(data.rekap.S); $('#rekI').text(data.rekap.I); $('#rekA').text(data.rekap.A);
    $('#rekT').text(data.rekap.ttl); $('#rekPct').text(data.rekap.pct+'%');

    var sel = document.getElementById('filterKelasDetail');
    var keep = sel.value;
    sel.innerHTML = '<option value="">Semua Kelas</option>' + (data.kelas||[]).map(function(k){ return '<option>'+k+'</option>'; }).join('');
    if (Array.from(sel.options).some(function(o){return o.value===keep;})) sel.value=keep;

    var tbody = document.querySelector('#tblDetailSiswa tbody');
    tbody.innerHTML='';
    (data.rows||[]).forEach(function(r){
      var badgeClass = r.status==='H'?'badge-H':(r.status==='S'?'badge-S':(r.status==='I'?'badge-I':'badge-A'));
      var tr = document.createElement('tr');
      tr.innerHTML = '<td></td>'+
                     '<td>'+ r.tanggal +'</td>'+
                     '<td>'+ (r.kelas||'-') +'</td>'+
                     '<td><span class="status-badge '+badgeClass+'">'+ r.status +'</span></td>'+
                     '<td>'+ (r.ket? $("<div/>").text(r.ket).html() : '-') +'</td>';
      tbody.appendChild(tr);
    });

    if(window.dtDetailSiswa){ window.dtDetailSiswa.destroy(); }
    window.dtDetailSiswa = $('#tblDetailSiswa').DataTable({
      responsive:true, autoWidth:false, pageLength:25,
      order:[[1,'asc']],
      dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>rt<"row"<"col-sm-5"i><"col-sm-7"p>>'
    });
    window.dtDetailSiswa.on('order.dt search.dt draw.dt', function(){
      let i = 1;
      window.dtDetailSiswa.column(0, {search:'applied', order:'applied', page:'current'}).nodes().each(function(cell){ cell.innerHTML = i++; });
    }).draw();

    document.querySelectorAll('#segStatus button').forEach(function(b){ b.classList.remove('active'); });
    document.querySelector('#segStatus button[data-st=""]').classList.add('active');
    if(window.dtDetailSiswa){ window.dtDetailSiswa.column(3).search('').draw(); }
    $('#filterKelasDetail').val(''); if(window.dtDetailSiswa){ window.dtDetailSiswa.column(2).search('').draw(); }
  }

  function loadDetailSiswa(sid, nama){
    currentDetailSid = sid; currentDetailNama = nama || 'Siswa';
    setLoadingState(currentDetailNama);
    var icon = document.querySelector('#btnReloadDetail i');
    if(icon){ icon.classList.add('fa-spin'); }
    var p = buildQueryParams(); p.set('ajax','detail_siswa'); p.set('sid', sid); p.set('_ts', Date.now());
    const seq = ++detailReqSeq;
    fetch('laporan_absensi.php?'+p.toString(), { cache: 'no-store' })
      .then(function(r){ return r.json(); })
      .then(function(j){
        if(seq!==detailReqSeq) return;
        if(!j.ok){ throw new Error(j.error||'Gagal memuat data'); }
        fillDetailModal(j);
      })
      .catch(function(e){
        var tbody = document.querySelector('#tblDetailSiswa tbody');
        tbody.innerHTML = '<tr><td colspan="5" class="text-danger">Gagal memuat: '+ e.message +'</td></tr>';
      })
      .finally(function(){ if(icon){ icon.classList.remove('fa-spin'); } });
  }

  document.getElementById('tblSiswa').addEventListener('click', function(e){
    var btn = e.target.closest('.js-detail-siswa');
    if(!btn) return;
    var sid = parseInt(btn.getAttribute('data-sid'),10);
    var nama = btn.getAttribute('data-nama');
    $('#modalDetailSiswa').modal('show');
    loadDetailSiswa(sid, nama);
  });

  document.getElementById('btnReloadDetail').addEventListener('click', function(){
    if(currentDetailSid){ loadDetailSiswa(currentDetailSid, currentDetailNama); }
  });

  document.querySelectorAll('#segStatus button').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.querySelectorAll('#segStatus button').forEach(function(b){ b.classList.remove('active'); });
      this.classList.add('active');
      var st = this.getAttribute('data-st') || '';
      if(window.dtDetailSiswa){ window.dtDetailSiswa.column(3).search(st).draw(); }
    });
  });

  document.getElementById('filterKelasDetail').addEventListener('change', function(){
    var v = this.value || '';
    if(window.dtDetailSiswa){ window.dtDetailSiswa.column(2).search(v).draw(); }
  });
});

  $('#modalDetailSiswa').on('hidden.bs.modal', function(){
    if (window.dtDetailSiswa) { try { window.dtDetailSiswa.destroy(); } catch(e){} window.dtDetailSiswa=null; }
    document.querySelector('#tblDetailSiswa tbody').innerHTML = '';
    $('#rekH,#rekS,#rekI,#rekA,#rekT').text('0'); $('#rekPct').text('0%');
  });
</script>

<?php include 'footer.php'; ?>