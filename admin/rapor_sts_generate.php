<?php
/**
 * Generate file HTML rapor STS per-siswa (kelas yang dipilih).
 * Output JSON: { ok, generated, failed, errors[] }
 *
 * v2025-10-20.r18 (BULLET-PROOF EDITION)
 * - FIX: Menghapus NISN dari tampilan Identitas Siswa
 */

// Tangkap semua error PHP agar tidak membocorkan teks HTML yang merusak JSON
error_reporting(0);
ini_set('display_errors', 0);

// Pastikan selalu membalas dengan JSON jika terjadi Fatal Error mendadak
register_shutdown_function(function(){
    $err = error_get_last();
    if($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])){
        while(ob_get_level()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false, 'error'=>'Fatal Error Server: '.$err['message']]);
        exit;
    }
});

if (session_status()===PHP_SESSION_NONE) session_start();
if (function_exists('mysqli_report')) @mysqli_report(MYSQLI_REPORT_OFF);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/../koneksi.php';
$DB = isset($koneksi) && $koneksi instanceof mysqli ? $koneksi : (isset($mysqli)? $mysqli : null);
if(!$DB){ echo json_encode(['ok'=>false,'error'=>'Koneksi DB tidak tersedia']); exit; }
@mysqli_set_charset($DB,'utf8mb4');

/* -------------------- Helpers -------------------- */
function db_one(mysqli $db,$q){ $r=@mysqli_query($db,$q); if(!$r)return null; $x=@mysqli_fetch_assoc($r); @mysqli_free_result($r); return $x?:null; }
function db_all(mysqli $db,$q){ $r=@mysqli_query($db,$q); if(!$r)return []; $o=[]; while($x=@mysqli_fetch_assoc($r))$o[]=$x; @mysqli_free_result($r); return $o; }
function esc($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function table_exists(mysqli $db,$name){ $safe=mysqli_real_escape_string($db,$name); $q=@mysqli_query($db,"SHOW FULL TABLES LIKE '{$safe}'"); $ok=$q && mysqli_num_rows($q)>0; if($q) @mysqli_free_result($q); return $ok; }
function cols(mysqli $db,$name){ $a=[]; $q=@mysqli_query($db,"SHOW COLUMNS FROM `{$name}`"); if($q){ while($r=@mysqli_fetch_assoc($q)) $a[]=$r['Field']; @mysqli_free_result($q);} return $a; }
function pick(array $cols,array $cands){ foreach($cands as $c){ if(in_array($c,$cols,true)) return $c; } return null; }
function indo_tanggal($ts=null){
  if($ts===null) $ts=time();
  $b=[1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  $d=(int)date('j',$ts); $m=(int)date('n',$ts); $y=(int)date('Y',$ts);
  return $d.' '.$b[$m].' '.$y;
}

/* -------------------- Input -------------------- */
$kelas_id = (int)($_POST['kelas_id'] ?? 0);
if($kelas_id<=0){ echo json_encode(['ok'=>false,'error'=>'Kelas tidak valid']); exit; }

/* -------------------- TA & Semester -------------------- */
$TA = db_one($DB,"SELECT * FROM ta WHERE ta_status=1 LIMIT 1");
if(!$TA) $TA = db_one($DB,"SELECT * FROM ta ORDER BY ta_id DESC LIMIT 1");
$ta_label = '';
foreach (['ta_nama','tahun_ajaran','nama','label'] as $c) if(isset($TA[$c]) && $TA[$c]!==''){ $ta_label=$TA[$c]; break; }
if($ta_label===''){ $y1 = isset($TA['tahun1'])?$TA['tahun1']:date('Y'); $ta_label = $y1.'/'.((int)$y1+1); }
$ta_for_db = 0; if (preg_match('/\d{4}/', $ta_label, $m)) { $ta_for_db = (int)$m[0]; }

$default_semester_auto = (int) (date('n') >= 7 ? 1 : 2);
$semester = isset($_POST['semester']) ? (int)$_POST['semester'] : $default_semester_auto;
if (!in_array($semester, [1, 2], true)) { $semester = $default_semester_auto; }
$semester_label = ($semester === 1) ? '1 (Ganjil)' : '2 (Genap)';

/* -------------------- Data kelas & siswa -------------------- */
$KELAS = db_one($DB, "SELECT * FROM kelas WHERE kelas_id={$kelas_id}");
if(!$KELAS){ echo json_encode(['ok'=>false,'error'=>'Kelas tidak ditemukan']); exit; }

$SISWA_COLS = cols($DB,'siswa');
$nisnField  = pick($SISWA_COLS,['siswa_nisn','nisn']);
$nisnSQL    = $nisnField ? ", s.`$nisnField` AS siswa_nisn" : "";

$SISWA = db_all($DB, "SELECT s.siswa_id, s.siswa_nama, s.siswa_nis{$nisnSQL}
                      FROM kelas_siswa ks JOIN siswa s ON s.siswa_id=ks.ks_siswa
                      WHERE ks.ks_kelas={$kelas_id} ORDER BY s.siswa_nama");

if(empty($SISWA)){ echo json_encode(['ok'=>false,'error'=>'Tidak ada data siswa di kelas ini']); exit; }

/* -------------------- Mapel yang punya SET PTS aktif -------------------- */
$MAPEL = db_all($DB, "
  SELECT DISTINCT m.mapel_id, m.mapel_nama
  FROM nilai_pts_set s
  JOIN mapel m ON m.mapel_id=s.mapel_id
  WHERE s.kelas_id={$kelas_id}
    AND s.ta_id=".(int)($TA['ta_id'] ?? 0)."
    AND s.semester={$semester}
");

/* -------------------- Nilai & deskripsi -------------------- */
function get_nilai_desc(mysqli $db,$pts_set_id,$siswa_id){
  $row = db_one($db,"SELECT * FROM nilai_pts WHERE pts_set_id={$pts_set_id} AND siswa_id={$siswa_id} LIMIT 1");
  if(!$row) return [null,''];
  $nilai = $row['nilai'];
  $desc  = '';
  $rpd = db_one($db,"SELECT deskripsi_final FROM rapor_pts_deskripsi WHERE pts_id=".(int)$row['pts_id']." LIMIT 1");
  if($rpd && trim((string)$rpd['deskripsi_final'])!==''){ $desc = $rpd['deskripsi_final']; }
  else{
    $vf  = db_one($db,"SELECT deskripsi_optimal, deskripsi_perlu FROM v_rapor_pts_deskripsi_final WHERE pts_id=".(int)$row['pts_id']." LIMIT 1");
    $opt = $vf['deskripsi_optimal'] ?? ($row['deskripsi_optimal'] ?? '');
    $per = $vf['deskripsi_perlu']   ?? ($row['deskripsi_perlu']   ?? '');
    $desc = trim($opt."\n".$per);
  }
  return [$nilai, $desc];
}
function latest_set_id(mysqli $db,$kelas_id,$mapel_id,$ta_id,$semester){
  $q = "
    SELECT s.pts_set_id
    FROM nilai_pts_set s
    JOIN nilai_pts np ON np.pts_set_id=s.pts_set_id
    WHERE s.kelas_id={$kelas_id} AND s.mapel_id={$mapel_id}
      AND s.ta_id={$ta_id} AND s.semester={$semester}
    GROUP BY s.pts_set_id
    ORDER BY MAX(np.updated_at) DESC, s.updated_at DESC, s.pts_set_id DESC
    LIMIT 1";
  $r = db_one($db,$q);
  if($r && (int)$r['pts_set_id']>0) return (int)$r['pts_set_id'];
  $r = db_one($db,"SELECT s.pts_set_id FROM nilai_pts_set s
                   WHERE s.kelas_id={$kelas_id} AND s.mapel_id={$mapel_id}
                     AND s.ta_id={$ta_id} AND s.semester={$semester}
                   ORDER BY s.updated_at DESC, s.pts_set_id DESC LIMIT 1");
  return $r ? (int)$r['pts_set_id'] : 0;
}

/* -------------------- Urutan mapel sesuai format -------------------- */
function norm_mapel($s){ $x=strtolower(trim((string)$s)); $x=str_replace(['  ',' (umum)'],' ', $x); return $x; }
function order_index($name){
  $n = norm_mapel($name);
  $keys = [
    1 => ['pendidikan agama','pai','budi pekerti'],
    2 => ['pendidikan pancasila','pancasila'],
    3 => ['bahasa indonesia'],
    4 => ['matematika'],
    5 => ['ilmu pengetahuan alam','ipa'],
    6 => ['ilmu pengetahuan sosial','ips'],
    7 => ['bahasa inggris'],
    8 => ['seni budaya','seni, budaya','prakarya'],
    9 => ['pendidikan jasmani','pjok','olahraga','kesehatan'],
    10=> ['informatika'],
    11=> ['bahasa sunda','sunda'],
  ];
  foreach($keys as $idx=>$arr){ foreach($arr as $kw){ if(strpos($n,$kw)!==false) return $idx; } }
  return 999;
}
usort($MAPEL, function($a,$b){ $ia=order_index($a['mapel_nama']); $ib=order_index($b['mapel_nama']); return $ia<=>$ib ?: strcasecmp($a['mapel_nama'],$b['mapel_nama']); });

/* -------------------- Sekolah -------------------- */
function sekolah_info(mysqli $db){
  $r = db_one($db,"SELECT * FROM sekolah LIMIT 1");
  $out = ['nama'=>'SMPN 1 Gunungtanjung','alamat'=>''];
  if($r){
    foreach(['sekolah_nama','nama'] as $k) if(isset($r[$k]) && $r[$k]!==''){ $out['nama']=$r[$k]; break; }
    foreach(['sekolah_alamat','alamat'] as $k) if(isset($r[$k]) && $r[$k]!==''){ $out['alamat']=$r[$k]; break; }
  }
  return $out;
}

/* -------------------- Wali Kelas otomatis -------------------- */
function user_name_nip_by_id(mysqli $db,$uid){
  if($uid<=0 || !table_exists($db,'user')) return ['nama'=>'-','nip'=>'-'];
  $c = cols($db,'user');
  $nameCol = pick($c,['user_nama','nama_lengkap','nama','full_name','display_name','name','username']) ?: 'username';
  $nipCol  = pick($c,['user_username','username','nip']) ?: $nameCol;
  $r = db_one($db,"SELECT `$nameCol` AS n, `$nipCol` AS p FROM `user` WHERE user_id=".(int)$uid." LIMIT 1");
  return ['nama'=>trim((string)($r['n']??'-')) ?: '-','nip'=>trim((string)($r['p']??'-')) ?: '-'];
}
function wali_kelas_auto(mysqli $db,$kelasRow,$TA){
  if(table_exists($db,'kelas_wali')){
    $c=cols($db,'kelas_wali');
    $idK = pick($c,['kelas_id','id_kelas']);
    $idT = pick($c,['ta_id','id_ta','tahun_ajaran_id']);
    $uid = pick($c,['wali_user_id','user_id','uid']);
    $nip = pick($c,['wali_nip','nip']);
    if($idK){
      $w = "WHERE `$idK`=".(int)$kelasRow['kelas_id'];
      if($idT && isset($TA['ta_id'])) $w .= " AND `$idT`=".(int)$TA['ta_id'];
      $row = db_one($db,"SELECT ".($uid?"`$uid` AS u":"NULL AS u").", ".($nip?"`$nip` AS n": "NULL AS n")."
                         FROM kelas_wali $w ORDER BY COALESCE(updated_at, created_at) DESC, 1 DESC LIMIT 1");
      if($row){
        $u = (int)($row['u'] ?? 0); $nipSet = trim((string)($row['n'] ?? ''));
        if($u>0){ $UN = user_name_nip_by_id($db,$u); if($nipSet!=='') $UN['nip']=$nipSet; return [$UN['nama'],$UN['nip']]; }
        if($nipSet!=='') return [null,$nipSet];
      }
    }
  }
  foreach(['wali_id','id_wali','wali_kelas_id','guru_wali_id'] as $k){
    if(isset($kelasRow[$k]) && (int)$kelasRow[$k]>0){
      $UN = user_name_nip_by_id($db,(int)$kelasRow[$k]);
      if($UN['nama']!=='-' || $UN['nip']!=='-') return [$UN['nama'],$UN['nip']];
      break;
    }
  }
  if(table_exists($db,'guru')){
    $c=cols($db,'guru'); $idc=pick($c,['guru_id','id_guru','id']); $nm=pick($c,['guru_nama','nama','name']); $np=pick($c,['guru_nip','nip']);
    if($idc && $nm && isset($kelasRow['wali_id']) && (int)$kelasRow['wali_id']>0){
      $r=db_one($db,"SELECT `$nm` AS n, ".($np?"`$np` AS p":"NULL AS p")." FROM `guru` WHERE `$idc`=".(int)$kelasRow['wali_id']." LIMIT 1");
      if($r) return [$r['n']??null, $r['p']??null];
    }
  }
  return [null,null];
}

/* -------------------- Rekap Absensi -------------------- */
function normalize_status($v){
  $s = strtolower(trim((string)$v));
  if($s==='') return '';
  if(is_numeric($s)){
    if($s==='2') return 'sakit';
    if($s==='3') return 'izin';
    if($s==='4') return 'alpa';
    return 'hadir';
  }
  if($s==='s') return 'sakit';
  if($s==='i') return 'izin';
  if($s==='a') return 'alpa';
  if($s==='h') return 'hadir';
  return $s;
}

function attendance_counts(mysqli $db,$siswa_id,$kelas_id,$ta_id,$semester){
  $out=['sakit'=>0,'izin'=>0,'alpa'=>0];

  $detailTables = ['absensi_harian_detail','absensi_harian_details','absensi_detail'];
  $detailTbl = null;
  foreach($detailTables as $t){ if(table_exists($db,$t)){ $detailTbl=$t; break; } }
  if(!$detailTbl || !table_exists($db,'absensi_harian')) return $out;

  $ch = cols($db,'absensi_harian');
  $hid  = pick($ch,['harian_id','ah_id','absensi_id','id']);
  $hcls = pick($ch,['kelas_id','id_kelas','kelas']);
  $hta  = pick($ch,['ta_id','id_ta','tahun_ajaran_id','ta']);
  $hsm  = pick($ch,['semester','smt','id_semester']);
  
  $htgl = pick($ch,['tanggal','tgl','date','waktu','created_at']); 

  if(!$hid || !$hcls) return $out;

  $cd = cols($db,$detailTbl);
  $dlink = pick($cd,['harian_id','ah_id','absensi_id','id_absensi','absensi_harian_id']);
  $dsis  = pick($cd,['siswa_id','id_siswa','siswa']);
  $dsts  = pick($cd,['status','kehadiran','kode','sts','absen','keterangan']);
  if(!$dlink || !$dsis || !$dsts) return $out;

  $w = "WHERE h.`$hcls`={$kelas_id} AND d.`$dsis`={$siswa_id}";
  if($hta) $w .= " AND h.`$hta`={$ta_id}";
  if($hsm) $w .= " AND h.`$hsm`={$semester}";

  if($htgl) {
      if((int)$semester === 1) {
          $w .= " AND MONTH(h.`$htgl`) >= 7 AND MONTH(h.`$htgl`) <= 12";
      } else {
          $w .= " AND MONTH(h.`$htgl`) >= 1 AND MONTH(h.`$htgl`) <= 6";
      }
  }

  $rows = db_all($db,"
    SELECT LOWER(TRIM(CAST(d.`$dsts` AS CHAR))) AS s, COUNT(*) AS c
    FROM `$detailTbl` d
    JOIN `absensi_harian` h ON h.`$hid` = d.`$dlink`
    $w
    GROUP BY LOWER(TRIM(CAST(d.`$dsts` AS CHAR)))
  ");
  
  $got = false;
  foreach($rows as $r){
    $s = normalize_status($r['s']); $c=(int)$r['c'];
    if($s==='sakit'){ $out['sakit'] += $c; $got=true; }
    elseif($s==='izin'){ $out['izin'] += $c; $got=true; }
    elseif(in_array($s,['alpa','alpha','alfa','tk','tanpa keterangan','tanpa_keterangan'],true)){ $out['alpa'] += $c; $got=true; }
  }
  if($got) return $out;

  $rows2 = db_all($db,"
    SELECT d.`$dsts` AS s
    FROM `$detailTbl` d
    JOIN `absensi_harian` h ON h.`$hid` = d.`$dlink`
    $w
  ");
  foreach($rows2 as $r){
    $s = normalize_status($r['s']);
    if($s==='sakit') $out['sakit']++;
    elseif($s==='izin') $out['izin']++;
    elseif(in_array($s,['alpa','alpha','alfa','tk','tanpa keterangan','tanpa_keterangan'],true)) $out['alpa']++;
  }

  return $out;
}

/* ==================== Catatan Wali ==================== */
function _panggilan_ananda($nama_lengkap){
  $nama = trim(preg_replace('~\s+~',' ', (string)$nama_lengkap));
  if($nama==='') return 'Ananda';
  $first = explode(' ', $nama)[0];
  return 'Ananda '.$first;
}
function _catatan_default_by_avg($avg, $nama_lengkap){
  $sapaan = _panggilan_ananda($nama_lengkap);
  if ($avg >= 90) {
    return "$sapaan hebat, capaian sangat baik. Pertahankan kebiasaan belajar, tetap rendah hati, dan tularkan positif pada teman.";
  }
  if ($avg >= 79) {
    return "Capaian $sapaan sudah baik. Tingkatkan dengan jadwal belajar teratur dan bertanya saat belum paham.";
  }
  return "Belajar itu proses, $sapaan. Ayo kuatkan dasar lewat remedial dan target mingguan. Guru siap membimbingmu.";
}

function catatan_wali(mysqli $db, $siswa_id, $kelas_id, $ta_id, $semester, $total, $cnt, $siswa_nama = null){
  foreach(['rapor_catatan_wali','catatan_wali','wali_catatan'] as $t){
    if(!table_exists($db,$t)) continue;
    $c=cols($db,$t);
    $sid=pick($c,['siswa_id','id_siswa']);
    $kid=pick($c,['kelas_id','id_kelas']);
    $ta =pick($c,['ta_id','id_ta','tahun_ajaran_id']);
    $sm =pick($c,['semester','smt','id_semester']);
    $tx =pick($c,['catatan','keterangan','note','catatan_wali']);
    if($sid && $tx){
      $w="WHERE `$sid`=".(int)$siswa_id; if($kid) $w.=" AND `$kid`=".(int)$kelas_id;
      if($ta) $w.=" AND `$ta`=".(int)$ta_id; if($sm) $w.=" AND `$sm`=".(int)$semester;
      $r=db_one($db,"SELECT `$tx` AS c FROM `$t` $w ORDER BY 1 DESC LIMIT 1");
      if($r && trim((string)$r['c'])!=='') return $r['c'];
    }
  }
  $avg = $cnt? $total/$cnt : 0;
  if ($siswa_nama === null) {
    $r = db_one($db, "SELECT siswa_nama FROM siswa WHERE siswa_id=".(int)$siswa_id." LIMIT 1");
    $siswa_nama = $r['siswa_nama'] ?? '';
  }
  return _catatan_default_by_avg($avg, $siswa_nama);
}

/* ==================== Catatan Kehadiran ==================== */
function catatan_kehadiran_auto(array $absen, $nama_lengkap = null){
  $sapaan = _panggilan_ananda($nama_lengkap ?? '');
  $prefix = "Kehadiran $sapaan: ";

  $S = (int)($absen['sakit'] ?? 0);
  $I = (int)($absen['izin']  ?? 0);
  $A = (int)($absen['alpa']  ?? 0);
  $T = $S + $I + $A;

  if ($T === 0) return $prefix."Hebat, hadir penuh sepanjang periode ini. Pertahankan konsistensinya.";
  if ($T <= 2 && $A === 0) return $prefix."Sudah baik. Jaga stamina dan pertahankan kedisiplinan hadir.";
  if ($A >= 3)  return $prefix."Perlu perhatian serius (tanpa keterangan $A hari). Perbaiki kedisiplinan hadir.";
  if ($A === 2) return $prefix."Perlu perhatian (tanpa keterangan 2 hari). Perbaiki ketertiban hadir.";
  if ($A === 1) return $prefix."Mohon lebih tertib (alpa 1 hari). Komunikasikan izin bila berhalangan.";
  if ($A === 0 && $T > 2) return $prefix."Perlu ditingkatkan (sakit/izin $T hari). Jaga kesehatan dan koordinasi dengan wali kelas.";

  return $prefix."Perlu perhatian pada kehadiran. Jaga kedisiplinan dan komunikasikan perizinan dengan baik.";
}

/* -------------------- Render HTML -------------------- */
function render_html(mysqli $db,$siswa,$kelas,$MAPEL,$TA,$semester){
  $ta_label = '';
  foreach (['ta_nama','tahun_ajaran','nama','label'] as $c) if(isset($TA[$c]) && $TA[$c]!==''){ $ta_label=$TA[$c]; break; }
  if($ta_label===''){ $y1 = isset($TA['tahun1'])?$TA['tahun1']:date('Y'); $ta_label = $y1.'/'.((int)$y1+1); }
  
  $semester_label = ($semester === 1) ? '1 (Ganjil)' : '2 (Genap)';

  $SEK = sekolah_info($db);
  
  list($wali_nama,$wali_nip) = wali_kelas_auto($db,$kelas,$TA);
  
  $kep_nama = 'Asep Warlina, S.Pd., M.M.Pd';
  $kep_nip  = '196807261991031003';

  $rows=[]; $total=0; $cnt=0;
  foreach($MAPEL as $mp){
    $set_id = latest_set_id($db,(int)$kelas['kelas_id'],(int)$mp['mapel_id'], (int)($TA['ta_id'] ?? 0), (int)$semester);
    $nilai=null; $desc=''; if($set_id>0){ list($nilai,$desc) = get_nilai_desc($db,$set_id,(int)$siswa['siswa_id']); }
    if($nilai!==null){ $total+=(float)$nilai; $cnt++; }
    $rows[]=['mapel'=>$mp['mapel_nama'],'nilai'=>$nilai,'desc'=>$desc];
  }

  $absen   = attendance_counts($db,(int)$siswa['siswa_id'], (int)$kelas['kelas_id'], (int)($TA['ta_id']??0), (int)$semester);
  $catatan = catatan_wali($db,(int)$siswa['siswa_id'], (int)$kelas['kelas_id'], (int)($TA['ta_id']??0), (int)$semester,$total,$cnt, $siswa['siswa_nama'] ?? null);
  $catatan_absen = catatan_kehadiran_auto($absen, $siswa['siswa_nama'] ?? null);
  $catatan = trim($catatan . " " . $catatan_absen);

  $tglCetak = indo_tanggal();
  $nis  = trim((string)($siswa['siswa_nis'] ?? ''));

  ob_start(); ?>
<!doctype html>
<html>
<head><meta charset="utf-8">
<title>Rapor STS - <?= esc($siswa['siswa_nama']) ?></title>
<style>
  :root{ --zoom: 1; --bg:#f3f4f6; --ink:#111; --line:#111; --muted:#4b5563; --base-size: 11.5px; }
  body{ margin:0; background:var(--bg); color:var(--ink); font-family: Arial, Helvetica, sans-serif; }
  .toolbar{ position:sticky; top:0; z-index:10; display:flex; gap:8px; align-items:center;
            padding:10px 12px; background:#fff; border-bottom:1px solid #e5e7eb; box-shadow:0 2px 8px rgba(0,0,0,.05); }
  .toolbar .title{ font-weight:700; margin-right:auto; color:#111827; font-size:14px; }
  .btn{ appearance:none; border:1px solid #e5e7eb; background:#0ea5e9; color:#fff; padding:6px 10px; border-radius:8px; cursor:pointer; }
  .btn.secondary{ background:#fff; color:#111; }
  .btn:active{ transform:translateY(1px); }
  .zoom-info{ min-width:72px; text-align:center; font-weight:700; }

  .preview-wrap{ display:flex; justify-content:center; padding:16px; }
  .page-shadow{ transform:scale(var(--zoom)); transform-origin: top center; }
  .page{ width:210mm; min-height:297mm; background:#fff; color:#111; box-shadow:0 18px 40px rgba(0,0,0,.12);
         padding:12mm; box-sizing:border-box; font-size: var(--base-size); }

  @page { size: A4; margin: 12mm 12mm 12mm 12mm; }
  @media print{
    .toolbar, .preview-wrap{ display:block; }
    .toolbar{ display:none !important; }
    body{ background:#fff; }
    .page-shadow{ transform:none !important; }
    .page{ box-shadow:none !important; width:auto; min-height:auto; padding:0; }
  }

  .meta{width:100%; border-collapse:collapse; margin-bottom:6px}
  .meta td{padding:2px 4px}
  .divider{height:1px;background:#000;margin:3px 0 6px}
  h1{font-size:16px; text-align:center; margin:12px 0 0; font-weight:bold; text-transform:uppercase}
  h2{font-size:11px; text-align:center; margin:0 0 15px; font-weight:bold; text-transform:uppercase}

  table.grid{width:100%; border-collapse:collapse; table-layout:fixed; page-break-inside:auto;}
  table.grid th, table.grid td{border:1px solid #111; padding:6px 6px; vertical-align:middle; word-wrap:break-word}
  table.grid th{background:#f2f2f2; text-align:center; font-weight:bold}

  col.col-no    { width: 10mm; }
  col.col-mapel { width: 38mm; }
  col.col-nilai { width: 22mm; }
  col.col-komp  { width: auto; }

  .td-center{text-align:center}
  .td-justify{text-align:justify}
  .small{font-size: var(--base-size); line-height:1.35}
  .sum-row td{background:#f7f7f7; font-weight:bold}

  .rc-grid{display:grid; grid-template-columns: auto 1fr; gap:10px; margin-top:8px; align-items:start}
  .rc-title{font-weight:bold; margin:4px 0 4px}
  .absen{border-collapse:collapse; width:auto; table-layout:auto}
  .absen td{border:1px solid #333; padding:5px 8px; white-space:nowrap; text-align:left}
  .absen td:first-child{padding-right:12px}
  .absen .cnum{width:auto; white-space:nowrap; text-align:left; padding-left:0 !important}
  .absen .sep{display:inline-block; padding:0 6px 0 0}
  .absen .sep::before{content:"\00a0";}
  .absen .unit{display:inline-block; padding-left:8px}

  .note-box{
    border:1px solid #333;
    padding:8px;
    min-height:52px;
    text-align: justify;
    text-justify: inter-word;
    font-size: calc(var(--base-size) * 0.95);
    line-height: 1.4;
  }

  .ttd-wrap{
    margin-top:14px;
    --sig-left-shift: -5mm;   
    --sig-middle-shift: -9mm;  
  }
  .ttd-row3{ display:flex; justify-content:space-between; gap:12mm; }
  .sig{ width:33.333%; text-align:center; font-size: var(--base-size); }
  .sig.shift-left{ transform: translateX(var(--sig-left-shift)); }
  .sig.shift-middle{ transform: translateX(var(--sig-middle-shift)); }
  .space{ height:65px; }
  .name{
    font-weight:700;
    display:inline-block;
    padding-bottom:2px;
    border-bottom:1px solid #000;
    white-space:nowrap;
    max-width:100%;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  .nip{ font-size: var(--base-size); }
  .dots2cm{ display:inline-block; width:25mm; white-space:nowrap; overflow:hidden; }

  .preline{ min-height: 1.2em; }
  .hdr{ min-height: 2.2em; display:flex; align-items:flex-start; justify-content:center; }
  .hdr b{ font-weight:700; }

  @media print{
    .ttd-wrap{ margin-top:12px; }
    .sig.shift-left{ transform: translateX(var(--sig-left-shift)); }
    .sig.shift-middle{ transform: translateX(var(--sig-middle-shift)); }
    .name{ border-bottom:1px solid #000; }
  }
</style>
</head>
<body>

<div class="toolbar">
  <div class="title">Preview Rapor STS — <?= esc($siswa['siswa_nama']) ?></div>
  <button class="btn" onclick="window.print()" title="Ctrl+P">🖨️ Print</button>
  <button class="btn secondary" onclick="zoomOut()">−</button>
  <div id="zoomInfo" class="zoom-info">100%</div>
  <button class="btn secondary" onclick="zoomIn()">+</button>
  <button class="btn secondary" onclick="zoomReset()">Reset</button>
</div>

<div class="preview-wrap">
  <div class="page-shadow" id="pageShadow">
    <div class="page">

      <table class="meta">
        <tr>
          <td>Nama</td>
          <td>: <strong><?= esc($siswa['siswa_nama']) ?></strong></td>
          <td style="width:60px"></td>
          <td>Kelas</td><td>: <?= esc($kelas['kelas_nama'] ?? '-') ?></td>
        </tr>
        <tr>
          <td>NIS</td><td>: <?= esc($nis) ?></td>
          <td></td>
          <td>Fase</td><td>: D</td>
        </tr>
        <tr>
          <td>Nama Sekolah</td><td>: <?= esc($SEK['nama']) ?></td>
          <td></td>
          <td>Semester</td><td>: <?= esc($semester_label) ?></td>
        </tr>
        <tr>
          <td>Alamat</td><td>: <?= esc($SEK['alamat']) ?></td>
          <td></td>
          <td>Tahun Pelajaran</td><td>: <?= esc($ta_label) ?></td>
        </tr>
      </table>

      <div class="divider"></div>
      <h1>LAPORAN HASIL BELAJAR</h1>
      <h2>SUMATIF TENGAH SEMESTER (STS) - SEMESTER <?= $semester ?></h2>

      <table class="grid">
        <colgroup>
          <col class="col-no">
          <col class="col-mapel">
          <col class="col-nilai">
          <col class="col-komp">
        </colgroup>
        <thead>
          <tr>
            <th>No</th>
            <th>Mata Pelajaran</th>
            <th>Nilai Akhir</th>
            <th>Capaian Kompetensi</th>
          </tr>
        </thead>
        <tbody>
          <?php $no=1; foreach($rows as $r):
            $nilai_str = $r['nilai']!==null ? number_format((float)$r['nilai'],0,',','.') : '-';
            $desc = trim((string)$r['desc'])!=='' ? $r['desc'] : '—';
          ?>
          <tr>
            <td class="td-center"><?= $no++ ?></td>
            <td><?= esc($r['mapel']) ?></td>
            <td class="td-center"><?= $nilai_str ?></td>
            <td class="td-justify small"><?= nl2br(esc($desc)) ?></td>
          </tr>
          <?php endforeach; ?>
          <tr class="sum-row">
            <td></td>
            <td class="td-center">Jumlah Nilai</td>
            <td class="td-center"><?= $cnt? (int)$total : '-' ?></td>
            <td></td>
          </tr>
        </tbody>
      </table>

      <div class="rc-grid">
        <div>
          <div class="rc-title">Rekap Kehadiran</div>
          <table class="absen">
            <tr>
              <td>Sakit</td>
              <td class="cnum"><span class="sep">:</span><span class="num"><?= (int)$absen['sakit'] ?></span><span class="unit">hari</span></td>
            </tr>
            <tr>
              <td>Izin</td>
              <td class="cnum"><span class="sep">:</span><span class="num"><?= (int)$absen['izin'] ?></span><span class="unit">hari</span></td>
            </tr>
            <tr>
              <td>Tanpa Keterangan</td>
              <td class="cnum"><span class="sep">:</span><span class="num"><?= (int)$absen['alpa'] ?></span><span class="unit">hari</span></td>
            </tr>
          </table>
        </div>
        <div>
          <div class="rc-title">Catatan Wali Kelas</div>
          <div class="note-box"><?= nl2br(esc((string)$catatan)) ?></div>
        </div>
      </div>

      <div class="ttd-wrap">
        <div class="ttd-row3">

          <div class="sig shift-left">
            <div class="preline">&nbsp;</div>
            <div class="hdr"><b>Mengetahui<br>Orang Tua/Wali,</b></div>
            <div class="space"></div>
            <div class="dots2cm">..............................................................</div>
            <div class="nip">&nbsp;</div>
          </div>

          <div class="sig shift-middle">
            <div class="preline">&nbsp;</div>
            <div class="hdr"><b>Wali Kelas</b></div>
            <div class="space"></div>
            <div class="name"><?= esc($wali_nama ?? '____________________') ?></div>
            <div class="nip">NIP. <?= esc($wali_nip ?? '') ?></div>
          </div>

          <div class="sig">
            <div class="preline">Tasikmalaya, <?= esc($tglCetak) ?></div>
            <div class="hdr"><b>Kepala Sekolah</b></div>
            <div class="space"></div>
            <div class="name"><?= esc($kep_nama) ?></div>
            <div class="nip">NIP. <?= esc($kep_nip) ?></div>
          </div>

        </div>
      </div>

    </div></div></div><script>
  (function(){
    const root = document.documentElement;
    const info = document.getElementById('zoomInfo');
    function setZoom(z){
      z = Math.max(0.6, Math.min(2.0, z)); 
      root.style.setProperty('--zoom', z);
      if(info) info.textContent = Math.round(z*100) + '%';
      localStorage.setItem('rapor_zoom', String(z));
    }
    window.zoomIn = () => setZoom( parseFloat(getComputedStyle(root).getPropertyValue('--zoom')) + .1 );
    window.zoomOut = () => setZoom( parseFloat(getComputedStyle(root).getPropertyValue('--zoom')) - .1 );
    window.zoomReset = () => setZoom(1);

    const last = parseFloat(localStorage.getItem('rapor_zoom') || '1');
    if(!isNaN(last)) setZoom(last);

    window.addEventListener('keydown', (e)=>{
      if(e.ctrlKey && (e.key==='=' || e.key==='+')){ e.preventDefault(); zoomIn(); }
      if(e.ctrlKey && e.key==='-'){ e.preventDefault(); zoomOut(); }
      if(e.ctrlKey && (e.key==='0' || e.key==='Insert')){ e.preventDefault(); zoomReset(); }
      if(e.ctrlKey && (e.key==='p' || e.key==='P')){ e.preventDefault(); window.print(); }
    }, {passive:false});
  })();
</script>
</body>
</html>
<?php
  return ob_get_clean();
}

/* -------------------- Folder output -------------------- */
$baseDir = __DIR__ . '/uploads/rapor_sts';
if(!is_dir($baseDir)) @mkdir($baseDir,0775,true);
$ta_parts = array_map('trim', explode('/', $ta_label));
$ta_dir = $baseDir;
foreach($ta_parts as $p){
  if($p==='') continue;
  $ta_dir .= '/' . preg_replace('~[^0-9A-Za-z_-]+~','_', $p);
  if(!is_dir($ta_dir)) @mkdir($ta_dir,0775,true);
}

$smt_slug = 'smt_' . $semester;
$ta_dir .= '/' . $smt_slug;
if(!is_dir($ta_dir)) @mkdir($ta_dir,0775,true);

$kelas_slug = 'kelas_'.preg_replace('~[^0-9A-Za-z_-]+~','_', $KELAS['kelas_nama'] ?? $kelas_id);
$kelas_dir  = $ta_dir . '/'.$kelas_slug;
if(!is_dir($kelas_dir)) @mkdir($kelas_dir,0775,true);

/* -------------------- Generate per siswa -------------------- */
$generated=0; $failed=0; $errors=[];
foreach($SISWA as $s){
  $html = render_html($DB,$s,$KELAS,$MAPEL,$TA,$semester);
  $file = $kelas_dir.'/rapor_'.(int)$s['siswa_id'].'.html';
  $ok = @file_put_contents($file,$html);
  if($ok===false){ $failed++; $errors[]='Gagal menulis file untuk '.$s['siswa_nama']; continue; }

  $rel_ta_path = implode('/', array_map(function($p){return preg_replace('~[^0-9A-Za-z_-]+~','_',trim($p));}, $ta_parts));
  $rel  = 'uploads/rapor_sts/'.$rel_ta_path.'/'.$smt_slug.'/'.$kelas_slug.'/rapor_'.(int)$s['siswa_id'].'.html';
  $hash = hash('sha256',$html);

  $stmt = @mysqli_prepare($DB,"INSERT INTO rapor_sts_files (siswa_id,kelas_id,tahun_ajaran,semester,file_path,file_hash)
    VALUES(?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE tahun_ajaran=VALUES(tahun_ajaran), semester=VALUES(semester),
      file_path=VALUES(file_path), file_hash=VALUES(file_hash), generated_at=CURRENT_TIMESTAMP");
      
  if($stmt){
    $sid=(int)$s['siswa_id']; $kid=(int)$kelas_id; $tal=(int)$ta_for_db; $sem=(int)$semester; $path=$rel; $h=$hash;
    @mysqli_stmt_bind_param($stmt,'iiiiss', $sid, $kid, $tal, $sem, $path, $h);
    if(!@mysqli_stmt_execute($stmt)){
      $failed++; $errors[]='DB error untuk '.$s['siswa_nama'].': '.@mysqli_error($DB);
      continue;
    }
  }else{
    $failed++; $errors[]='DB prep error: '.@mysqli_error($DB);
    continue;
  }

  $generated++;
}

/* -------------------- JSON -------------------- */
while(ob_get_level()) ob_end_clean();
echo json_encode(['ok'=>true,'generated'=>$generated,'failed'=>$failed,'errors'=>$errors]);
exit;