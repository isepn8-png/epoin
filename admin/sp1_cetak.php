<?php
// sp1_cetak.php — Surat Peringatan (SP) siap cetak, berbasis SALDO (netto)

// ===== GUARD: SP hanya untuk STAF (admin + guru/wali/BK), tolak siswa =====
require_once __DIR__ . '/../includes/epoin_security.php';
epoin_staff_only_guard();
include 'header.php';
include '../koneksi.php';

// ===== DEBUG OPSIONAL: tampilkan error saat ?debug=1 =====
if (isset($_GET['debug'])) {
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
}

/*
Schema references used in auto Wali Kelas/Waka resolution:
- kelas_wali columns: ta_id, kelas_id, wali_user_id, wali_nip, ...
- user columns: nama (fleksibel) & NIP (khusus: user_username dipakai sebagai NIP) 
- kelas column: kelas_ta
- sekolah: menyimpan nama & id user untuk wakasek_kesiswaan (mis. wakasek_kesiswaan_nama, wakasek_kesiswaan_id, dst)
- unique (ta_id, kelas_id) on kelas_wali
*/

// ===== Helper aman (guard bila sudah ada di header.php) =====
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('indo_tanggal')) {
  function indo_tanggal($date=''){
    if(!$date) $date = date('Y-m-d');
    $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $ts = strtotime($date);
    return date('d', $ts).' '.$bulan[(int)date('n',$ts)].' '.date('Y',$ts);
  }
}

// ===== Konfigurasi nomor surat =====
$SCHOOL_CODE_UP = 'SMPN1GTJ';
if (!function_exists('build_nomor_surat')) {
  function build_nomor_surat($seq3, $sp_level, $school_code, $year){
    return $seq3.'/'.$sp_level.'/'.$school_code.'/'.$year; // NNN/SPx/SCHOOL/YYYY
  }
}

// ===== DB wrappers: tahan fatal bila tabel/kolom tak ada =====
if (!function_exists('db_try_query')) {
  function db_try_query($koneksi, $sql){
    try { return mysqli_query($koneksi, $sql); }
    catch (Throwable $e) { return false; }
  }
}
if (!function_exists('db_table_exists')) {
  function db_table_exists($koneksi, $table){
    $esc = mysqli_real_escape_string($koneksi, $table);
    $res = db_try_query($koneksi, "SELECT 1
      FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name = '$esc' LIMIT 1");
    if ($res && mysqli_fetch_row($res)) return true;
    return false;
  }
}
if (!function_exists('col_exists')) {
  function col_exists($koneksi, $table, $col){
    $t = mysqli_real_escape_string($koneksi, $table);
    $c = mysqli_real_escape_string($koneksi, $col);
    $res = db_try_query($koneksi, "SELECT 1
      FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name='$t' AND column_name='$c' LIMIT 1");
    return ($res && mysqli_fetch_row($res)) ? true : false;
  }
}
if (!function_exists('find_one_safe')) {
  function find_one_safe($koneksi, $sql){
    $res = db_try_query($koneksi, $sql);
    if ($res && ($row = mysqli_fetch_assoc($res))) return $row;
    return null;
  }
}

// ===== Ambil nama & NIP dari tabel user (nama: fleksibel; NIP: pakai user_username) =====
if (!function_exists('get_user_name_nip_by_id')) {
  function get_user_name_nip_by_id($koneksi, $userId){
    $userId = (int)$userId;
    if ($userId<=0 || !db_table_exists($koneksi,'user')) return ['nama'=>'-','nip'=>'-'];

    // deteksi kolom nama yang tersedia
    $nameCols = ['user_nama','nama_lengkap','nama','full_name','display_name','name','username'];
    $nameCol  = null;
    foreach($nameCols as $c){ if (col_exists($koneksi,'user',$c)) { $nameCol=$c; break; } }
    if(!$nameCol) $nameCol = 'username';

    // NIP WAJIB dari user_username (sesuai requirement)
    $nipCol = col_exists($koneksi,'user','user_username') ? 'user_username' : $nameCol;

    $row = find_one_safe($koneksi, "SELECT `$nameCol` AS nama, `$nipCol` AS nip FROM user WHERE user_id=$userId LIMIT 1");
    if(!$row) return ['nama'=>'-','nip'=>'-'];

    $nama = trim((string)$row['nama']); if($nama==='') $nama='-';
    $nip  = trim((string)$row['nip']);  if($nip==='')  $nip='-';
    return ['nama'=>$nama,'nip'=>$nip];
  }
}

// ===== Pencarian guru by name (fallback bila tak ada relasi user) =====
if (!function_exists('find_guru_by_name')) {
  function find_guru_by_name($koneksi, $name){
    $escName = mysqli_real_escape_string($koneksi, $name);
    $cands = [
      ['guru',     'guru_nama', 'guru_nip'],
      ['tb_guru',  'nama_guru', 'nip'],
      ['pegawai',  'nama',      'nip'],
      ['user',     'user_nama', 'user_username'], // username as NIP (fallback)
    ];
    foreach($cands as [$tbl,$colName,$colNip]){
      if (!db_table_exists($koneksi, $tbl)) continue;
      $sql = "SELECT `$colName` AS nama, `$colNip` AS nip
              FROM `$tbl`
              WHERE `$colName` LIKE '$escName%'
              ORDER BY `$colName` ASC
              LIMIT 1";
      if ($row = find_one_safe($koneksi, $sql)) {
        $nama = trim((string)$row['nama']); if($nama==='') $nama='-';
        $nip  = trim((string)$row['nip']);  if($nip==='')  $nip='-';
        return ['nama'=>$nama,'nip'=>$nip];
      }
    }
    return ['nama'=>$name,'nip'=>'-'];
  }
}

// ===== Cari wali kelas (berbasis kelas_wali + user + kelas_ta) =====
if (!function_exists('find_wali_kelas_by_kelas_id')) {
  function find_wali_kelas_by_kelas_id($koneksi, $kelasId){
    $kelasId = (int)$kelasId;
    if ($kelasId <= 0) return ['nama'=>'-','nip'=>'-'];

    // Cari TA dari tabel kelas (kelas_ta)
    $taId = null;
    if (db_table_exists($koneksi,'kelas')){
      $qta = find_one_safe($koneksi, "SELECT kelas_ta FROM kelas WHERE kelas_id=$kelasId LIMIT 1");
      if ($qta) $taId = (int)$qta['kelas_ta'];
    }

    // Ambil entri wali terbaru utk kelas & TA tersebut (atau paling baru jika TA tak diketahui)
    if (!db_table_exists($koneksi,'kelas_wali')) return ['nama'=>'-','nip'=>'-'];

    $where = "kelas_id=$kelasId";
    if ($taId) $where .= " AND ta_id=$taId";

    $wali = find_one_safe($koneksi, "
      SELECT id, ta_id, kelas_id, wali_user_id, COALESCE(wali_nip,'') AS wali_nip
      FROM kelas_wali
      WHERE $where
      ORDER BY COALESCE(updated_at, created_at) DESC, id DESC
      LIMIT 1
    ");
    if (!$wali) return ['nama'=>'-','nip'=>'-'];

    // Jika ada user (guru) terkait — ambil nama & NIP dari tabel user
    if (!empty($wali['wali_user_id']) && db_table_exists($koneksi,'user')){
      $u = get_user_name_nip_by_id($koneksi, (int)$wali['wali_user_id']);
      // override NIP jika di kelas_wali sudah diisi wali_nip
      if (!empty($wali['wali_nip'])) $u['nip'] = trim((string)$wali['wali_nip']);
      if ($u['nama']==='') $u['nama']='-';
      if ($u['nip']==='')  $u['nip']='-';
      return $u;
    }

    // fallback: kalau hanya punya wali_nip
    if (!empty($wali['wali_nip'])) return ['nama'=>'-','nip'=>trim((string)$wali['wali_nip'])];

    return ['nama'=>'-','nip'=>'-'];
  }
}

// ===== Cari Waka Kesiswaan otomatis (Nama dari sekolah, NIP dari user.user_username) =====
if (!function_exists('find_waka_kesiswaan')) {
  function find_waka_kesiswaan($koneksi, $sekolahId){
    $sekolahId = (int)$sekolahId;
    $nama='-'; $nip='-';

    if (db_table_exists($koneksi,'sekolah')){
      // deteksi kolom id sekolah
      $idCol = col_exists($koneksi,'sekolah','sekolah_id') ? 'sekolah_id' : (col_exists($koneksi,'sekolah','id') ? 'id' : null);

      // kandidat kolom nama & user_id wakasek kesiswaan di tabel sekolah
      $nameCands = ['wakasek_kesiswaan_nama','waka_kesiswaan_nama','wakasek_nama','waka_nama','waka_kesiswaan'];
      $uidCands  = ['wakasek_kesiswaan_id','waka_kesiswaan_id','wakasek_user_id','waka_user_id','wakasek_kesiswaan_user_id'];

      $nameCol = null; foreach($nameCands as $c){ if (col_exists($koneksi,'sekolah',$c)) { $nameCol=$c; break; } }
      $uidCol  = null; foreach($uidCands  as $c){ if (col_exists($koneksi,'sekolah',$c)) { $uidCol=$c;  break; } }

      if ($idCol){
        $sql = "SELECT "
             . ($nameCol? "`$nameCol` AS nama" : "'' AS nama")
             . ", "
             . ($uidCol?  "`$uidCol` AS uid"  : "NULL AS uid")
             . " FROM sekolah WHERE `$idCol`='$sekolahId' LIMIT 1";
        $row = find_one_safe($koneksi, $sql);
        if ($row){
          $nama = trim((string)$row['nama']); if ($nama==='') $nama='-';
          $uid  = (int)($row['uid'] ?? 0);

          // Ambil NIP dari user.user_username (kalau ada user_id)
          if ($uid>0 && db_table_exists($koneksi,'user') && col_exists($koneksi,'user','user_username')){
            $nipRow = find_one_safe($koneksi, "SELECT user_username AS nip FROM user WHERE user_id=$uid LIMIT 1");
            if ($nipRow){ $nip = trim((string)$nipRow['nip']); if($nip==='') $nip='-'; }
          }

          // Jika nama masih '-' tapi ada user id, fallback nama dari tabel user
          if ($nama==='-' && $uid>0 && db_table_exists($koneksi,'user')){
            $u = get_user_name_nip_by_id($koneksi, $uid);
            if ($u['nama']!=='-') $nama = $u['nama'];
            if ($nip==='-' && $u['nip']!=='-') $nip = $u['nip'];
          }

          return ['nama'=>$nama, 'nip'=>$nip];
        }
      }
    }

    // Fallback terakhir (misal kolom di sekolah tidak ada): pakai sekolah_staff → user
    if (db_table_exists($koneksi,'sekolah_staff') && db_table_exists($koneksi,'user')){
      $row = find_one_safe($koneksi, "
        SELECT ss.user_id
        FROM sekolah_staff ss
        WHERE ss.sekolah_id='$sekolahId' AND ss.posisi_key='wakasek_kesiswaan'
        ORDER BY ss.id DESC LIMIT 1
      ");
      if ($row && (int)$row['user_id']>0){
        $u = get_user_name_nip_by_id($koneksi, (int)$row['user_id']);
        return ['nama'=>$u['nama'] ?: '-', 'nip'=>$u['nip'] ?: '-'];
      }
    }

    return ['nama'=>$nama,'nip'=>$nip];
  }
}

// ===== Deteksi level SP =====
$sp = isset($_GET['sp']) ? strtoupper(trim($_GET['sp'])) : '';
if ($sp==='') {
  $scr = strtolower(basename($_SERVER['SCRIPT_NAME'] ?? ''));
  if (strpos($scr, 'sp1') !== false) $sp = 'SP1';
  elseif (strpos($scr, 'sp2') !== false) $sp = 'SP2';
  elseif (strpos($scr, 'sp3') !== false) $sp = 'SP3';
  elseif (strpos($scr, 'sp4') !== false) $sp = 'SP4';
}
require_once __DIR__ . '/../includes/epoin_sp_helpers.php';

$spValidated = epoin_sp_validate_level($sp !== '' ? $sp : 'SP1');
$sp = $spValidated ?? 'SP1';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  echo "<div class='content-wrapper'><section class='content'><div class='alert alert-danger'>ID siswa tidak valid.</div></section></div>";
  include 'footer.php'; exit;
}

require_once __DIR__ . '/../includes/epoin_security.php';

// ===== Identitas siswa =====
$stu = epoin_fetch_siswa_row($koneksi, $id);
if (!$stu) {
  echo "<div class='content-wrapper'><section class='content'><div class='alert alert-danger'>Data siswa tidak ditemukan.</div></section></div>";
  include 'footer.php'; exit;
}

// ===== Kelas/tingkat terakhir dari riwayat =====
$kelasTerakhir = '-';
$kelasTerakhirId = 0;
$qr = null;
$stmtKr = mysqli_prepare($koneksi, "
  (SELECT k.kelas_id AS kid, k.kelas_nama AS kn, ig.waktu AS w
     FROM input_pelanggaran ig JOIN kelas k ON ig.kelas=k.kelas_id
    WHERE ig.siswa = ?)
  UNION ALL
  (SELECT k.kelas_id AS kid, k.kelas_nama AS kn, ip.waktu AS w
     FROM input_prestasi ip JOIN kelas k ON ip.kelas=k.kelas_id
    WHERE ip.siswa = ?)
  ORDER BY w DESC LIMIT 1
");
if ($stmtKr) {
  mysqli_stmt_bind_param($stmtKr, 'ii', $id, $id);
  mysqli_stmt_execute($stmtKr);
  $qr = mysqli_stmt_get_result($stmtKr);
}
if($qr && $r = mysqli_fetch_assoc($qr)){
  $kelasTerakhir = $r['kn'];
  $kelasTerakhirId = (int)$r['kid'];
}
if($kelasTerakhirId<=0 && $kelasTerakhir!=='-'){
  $escK = mysqli_real_escape_string($koneksi, $kelasTerakhir);
  $qk = db_try_query($koneksi, "SELECT kelas_id FROM kelas WHERE kelas_nama='$escK' LIMIT 1");
  if($qk && ($rk = mysqli_fetch_assoc($qk))) $kelasTerakhirId = (int)$rk['kelas_id'];
}

// ===== Totals & SALDO =====
$totPrestasi = epoin_sum_prestasi_siswa($koneksi, $id);
$totPelang   = epoin_sum_pelanggaran_siswa($koneksi, $id);
$saldo       = $totPrestasi - $totPelang;
$negSaldo    = max(0, -$saldo);

// ===== Tahapan pembinaan =====
$STAGES = [
  ['roman'=>'I',   'min'=>1,   'max'=>20,     'program'=>'Pembinaan Umum',                       'action'=>'Teguran',                 'sp'=>'SP1'],
  ['roman'=>'II',  'min'=>21,  'max'=>40,     'program'=>'Pembinaan Umum / Panggilan Orang Tua', 'action'=>'Peringatan 1 (SP 1)',     'sp'=>'SP1'],
  ['roman'=>'III', 'min'=>41,  'max'=>60,     'program'=>'Panggilan Orang Tua',                  'action'=>'Peringatan 2 (SP 2)',     'sp'=>'SP2'],
  ['roman'=>'IV',  'min'=>61,  'max'=>80,     'program'=>'Pembinaan Khusus',                     'action'=>'Peringatan 3 (SP 3)',     'sp'=>'SP3'],
  ['roman'=>'V',   'min'=>81,  'max'=>90,     'program'=>'Konferensi Kasus',                     'action'=>'Peringatan Terakhir (SP 4)','sp'=>'SP4'],
  ['roman'=>'V',   'min'=>91,  'max'=>99,     'program'=>'Konferensi Kasus',                     'action'=>'Tidak naik kelas (SP 4)', 'sp'=>'SP4'],
  ['roman'=>'VI',  'min'=>100, 'max'=>999999, 'program'=>'Dikembalikan pada Orang Tua',          'action'=>'Pemulangan (SP 4)',       'sp'=>'SP4'],
];
$SAFE_STAGE = ['roman'=>'-', 'min'=>0, 'max'=>0, 'program'=>'Apresiasi / Monitoring', 'action'=>'Tidak ada tindakan', 'sp'=>null];

$currentStage = $SAFE_STAGE;
if ($negSaldo > 0){
  foreach($STAGES as $st){
    if($negSaldo >= $st['min'] && $negSaldo <= $st['max']){ $currentStage = $st; break; }
  }
}

// ===== Riwayat gabungan =====
$riwayat = [];
$stmtRw = mysqli_prepare($koneksi, "
  (SELECT ip.waktu AS waktu, 'Prestasi' AS jenis, p.prestasi_nama AS nama,  p.prestasi_point AS poin
     FROM input_prestasi ip JOIN prestasi p ON ip.prestasi=p.prestasi_id
    WHERE ip.siswa = ?)
  UNION ALL
  (SELECT ig.waktu AS waktu, 'Pelanggaran' AS jenis, pg.pelanggaran_nama AS nama, -pg.pelanggaran_point AS poin
     FROM input_pelanggaran ig JOIN pelanggaran pg ON ig.pelanggaran=pg.pelanggaran_id
    WHERE ig.siswa = ?)
  ORDER BY waktu ASC
");
if ($stmtRw) {
  mysqli_stmt_bind_param($stmtRw, 'ii', $id, $id);
  mysqli_stmt_execute($stmtRw);
  $qrw = mysqli_stmt_get_result($stmtRw);
  if ($qrw) {
    while ($r = mysqli_fetch_assoc($qrw)) {
      $riwayat[] = $r;
    }
  }
  mysqli_stmt_close($stmtRw);
}

// ===== Log/nomor SP (prepared; alasan GET hanya untuk tampilan HTML) =====
epoin_sp_ensure_schema($koneksi);

$year = (int) date('Y');
$log = epoin_sp_fetch_latest_log($koneksi, $id, $sp, $year);

$allow = epoin_sp_can_issue_level($sp, $negSaldo, [], false);
if (!$log && $allow) {
  $log = epoin_sp_auto_create_for_print($koneksi, $id, $sp, $negSaldo, $SCHOOL_CODE_UP ?? 'SMPN1GTJ');
}

// Nomor & tanggal
$nomorSurat   = isset($log['nomor']) ? (string) $log['nomor'] : '- (belum terbit)';
$tanggalSurat = indo_tanggal(isset($log['tanggal']) ? substr((string) $log['tanggal'], 0, 10) : date('Y-m-d'));
$alasanLog    = trim((string) ($log['alasan'] ?? ''));
if ($alasanLog === '' && isset($_GET['alasan'])) {
  $alasanLog = epoin_sp_sanitize_alasan((string) $_GET['alasan']);
}

// ===== Tahun Ajaran & Semester =====
$bulanNow = (int)date('n');
if ($bulanNow >= 7) { $TA_awal = (int)date('Y'); $TA_akhir= $TA_awal + 1; $semesterLabel = 'Semester Ganjil'; }
else               { $TA_akhir= (int)date('Y'); $TA_awal = $TA_akhir - 1; $semesterLabel = 'Semester Genap'; }
$taTeks = $TA_awal.'/'.$TA_akhir;

// ===== Judul dinamis =====
$roman = ['SP1'=>'I','SP2'=>'II','SP3'=>'III','SP4'=>'IV'];
$angka = ['SP1'=>'1','SP2'=>'2','SP3'=>'3','SP4'=>'4'];
$judul = "SURAT PERINGATAN ".($roman[$sp] ?? 'I')." (SP-".($angka[$sp] ?? '1').")";

// ===== Redaksi isi =====
$dasarAturan = "Berdasarkan Tata Tertib Peserta Didik yang berlaku di sekolah pada Tahun Pelajaran ".$taTeks." ".$semesterLabel.", serta hasil evaluasi kepatuhan terhadap peraturan sekolah, bersama ini kami menyampaikan pemberitahuan resmi terkait pelanggaran yang dilakukan oleh peserta didik yang bersangkutan.";
$pernyataan  = "Sehubungan dengan uraian pada bagian <b>Alasan Penerbitan</b> serta hasil penilaian saldo poin (prestasi dikurangi pelanggaran) dalam sistem, sekolah menetapkan untuk menerbitkan <b>".$judul."</b> kepada peserta didik tersebut.";
$ajakan      = "Melalui surat ini, kami memohon kerja sama Bapak/Ibu Orang Tua/Wali untuk melakukan pembinaan di rumah, mendorong perbaikan sikap, serta memastikan putra/putrinya menaati seluruh tata tertib sekolah dan mengikuti program pembinaan yang ditetapkan.";
$nextSPLabels = ['SP1'=>'SP-2','SP2'=>'SP-3','SP3'=>'SP-4','SP4'=>null];
$nextSP = $nextSPLabels[$sp] ?? null;
$konsekuensi = $nextSP
  ? "Apabila setelah diterbitkannya surat ini terjadi pelanggaran kembali atau tidak terdapat perbaikan yang berarti, maka sekolah akan melanjutkan ke tahap peringatan berikutnya (".$nextSP.") sesuai ketentuan yang berlaku."
  : "Apabila setelah diterbitkannya surat ini tidak terdapat perbaikan yang berarti, sekolah berwenang mengambil keputusan lebih lanjut termasuk pengembalian peserta didik kepada orang tua sesuai ketentuan yang berlaku.";
$penutup     = "Demikian surat peringatan ini kami sampaikan untuk menjadi perhatian. Atas perhatian dan kerja samanya, kami ucapkan terima kasih.";

// ===== Data penandatangan =====

// 0) Tentukan SEKOLAH_ID
if (session_status() === PHP_SESSION_NONE) @session_start();
$SEKOLAH_ID = (int)($_SESSION['sekolah_id'] ?? 1);

// 1) Wali Kelas otomatis dari kelas_wali/user
$wali = find_wali_kelas_by_kelas_id($koneksi, $kelasTerakhirId);
$waliNama = $wali['nama'] ?? '-';
$waliNip  = $wali['nip']  ?? '-';

// 2) Wakasek Kesiswaan otomatis (Nama dari sekolah, NIP dari user.user_username)
$waka = find_waka_kesiswaan($koneksi, $SEKOLAH_ID);
$wkNama = $waka['nama'] ?? '-';
$wkNip  = $waka['nip']  ?? '-';

// 3) Guru BK (penandatangan) dari log penerbitan (signer_user_id); fallback ke snapshot nama
$bkNama = '-';
$bkNip  = '-';
if ($log && isset($log['signer_posisi_key']) && $log['signer_posisi_key']==='guru_bp'){
  if (!empty($log['signer_user_id'])){
    $bp = get_user_name_nip_by_id($koneksi, (int)$log['signer_user_id']);
    $bkNama = $bp['nama'] ?: ($log['signer_nama'] ?? '-');
    $bkNip  = $bp['nip']  ?: '-';
  } else {
    $bkNama = $log['signer_nama'] ?? '-';
    $bkNip  = '-';
  }
}

// ===== UI / Print =====
$kopPath = "../gambar/sistem/kop_sekolah.png";
?>
<style>
  :root{ --ink:#111827; --muted:#4b5563; --line:#e5e7eb; }
  .main-footer{ display:none !important; }

  .print-container{ background:#fff; margin:20px auto; max-width:900px; padding:16px 18px 20px;
    box-shadow:0 10px 24px rgba(0,0,0,.08); color:#111827; font-size:13.5px; line-height:1.6; }
  .kop-wrap{ margin-bottom:8px; }
  .kop-wrap img{ width:100%; height:auto; display:block; object-fit:contain; }

  .title-block{ text-align:center; margin-top:6px; margin-bottom:8px; }
  .title-block h2{ margin:0; font-weight:800; letter-spacing:.3px; text-transform:uppercase; }
  .title-block .subtitle{ color:#374151; margin-top:2px; }

  .meta table{ width:100%; border-collapse:collapse; margin-top:8px; }
  .meta th, .meta td{ border:1px solid var(--line); padding:8px 10px; vertical-align:top; }
  .meta th{ width:230px; background:#f9fafb; font-weight:600; }

  .content{ margin-top:6px; }
  .content p{ text-align:justify; margin:0 0 10px; }

  .badge{display:inline-block;border-radius:999px;padding:.1rem .5rem;font-weight:700}
  .bg-green{background:#dcfce7;color:#065f46}
  .bg-red{background:#fee2e2;color:#991b1b}

  .note-warn{ background:#fff7ed; border:1px solid #fdba74; padding:8px 10px; border-radius:8px; margin:8px 0; color:#7c2d12; }

  .rincian-title{ font-weight:800; margin:8px 0 6px; }
  .rincian table{ width:100%; border-collapse:collapse; font-size:12.5px; table-layout:fixed; border:1px solid #111; }
  .rincian th, .rincian td{ border:1px solid #111; padding:6px 8px; white-space:normal; word-break:break-word; }
  .rincian th{ background:#f3f4f6; text-align:center; }
  .rincian td:nth-child(1), .rincian td:nth-child(2), .rincian td:nth-child(5){ text-align:center; }
  .rincian td:nth-child(4){ text-align:justify; }
  .rincian tfoot td{ font-weight:800; background:#f3f4f6; }

  .ttd{ margin-top:10px; }
  .ttd-grid2{ display:grid; grid-template-columns:repeat(2,1fr); gap:24px 36px; align-items:end; }
  .sig{text-align:center;}
  .sig .cap-muted{ color:#0f172a; font-weight:600; letter-spacing:.2px; margin-bottom:2px; }
  .sig .cap-role{ margin-bottom:6px; font-weight:700; color:#0f172a; }
  .sig .space{ height:64px; }
  .sig .space3{ height:64px; }
  .sig .line{ width:68%; margin:0 auto 4px; border-bottom:2px solid #111; height:0; }
  .sig .name{ font-weight:700; margin-top:2px; }
  .sig .nip{ color:#374151; font-size:12.5px; }

  .btn-print{ position:sticky; top:8px; background:#0ea5e9; color:#fff; border:none; padding:8px 12px; border-radius:6px; margin-bottom:10px; }
  @media print{
    @page{ size: A4; margin: 16mm 16mm; }
    body{ background:#fff; }
    .btn-print{ display:none; }
    .print-container{ box-shadow:none; margin:0; padding:0; }
  }
</style>

<div class="content-wrapper">
  <section class="content">
    <button class="btn-print" onclick="window.print()"><i class="fa fa-print"></i> Cetak</button>

    <div class="print-container">

      <!-- KOP SEKOLAH -->
      <div class="kop-wrap">
        <?php if(is_file($kopPath)): ?>
          <img src="<?php echo e($kopPath); ?>" alt="Kop Sekolah">
        <?php else: ?>
          <div style="text-align:center;font-weight:800;color:#374151;border-bottom:3px double #111;padding:8px 0;">KOP SEKOLAH</div>
        <?php endif; ?>
      </div>

      <!-- JUDUL & NOMOR -->
      <div class="title-block">
        <h2><?php echo e($judul); ?></h2>
        <div class="subtitle">
          Nomor: <?php echo e(strtoupper($nomorSurat)); ?> &nbsp; | &nbsp; Tanggal: <?php echo e($tanggalSurat); ?>
        </div>
      </div>

      <!-- DATA SISWA & RINGKASAN POIN -->
      <div class="meta">
        <table class="table table-bordered">
          <tr><th>Nama Siswa</th><td><?php echo e($stu['siswa_nama']); ?></td></tr>
          <tr><th>NIS</th><td><?php echo e($stu['siswa_nis']); ?></td></tr>
          <tr><th>Tingkat/Kelas</th><td><?php echo e($kelasTerakhir); ?></td></tr>

          <tr><th>Total Prestasi</th><td><span class="badge bg-green">+<?php echo (int)$totPrestasi; ?></span> poin</td></tr>
          <tr><th>Total Pelanggaran</th><td><span class="badge bg-red">-<?php echo (int)$totPelang; ?></span> poin</td></tr>
          <tr><th>Saldo (Prestasi − Pelanggaran)</th>
              <td>
                <?php if($saldo>=0): ?>
                  <span class="badge bg-green">+<?php echo $saldo; ?></span> (Aman/Apresiasi)
                <?php else: ?>
                  <span class="badge bg-red"><?php echo $saldo; ?></span> (Risiko sanksi: <?php echo $negSaldo; ?>/100)
                <?php endif; ?>
              </td>
          </tr>

          <tr><th>Tingkat Pembinaan</th>
              <td>
                <?php echo e($currentStage['roman']); ?> — <?php echo e($currentStage['program']); ?>.
                Tindakan: <b><?php echo e($currentStage['action']); ?></b>.
              </td>
          </tr>

          <tr><th>Alasan Penerbitan</th>
              <td><?php echo $alasanLog!=='' ? e($alasanLog) : '<span style="color:#6b7280">—</span>'; ?></td>
          </tr>
        </table>
      </div>

      <?php if (!$log && $negSaldo < ( $sp==='SP1'?21 : ($sp==='SP2'?41 : ($sp==='SP3'?61 : 81)) ) ): ?>
        <div class="note-warn">
          Catatan: Ambang saldo untuk <?php echo e($sp); ?> belum terpenuhi pada saat pembuatan dokumen ini dan belum ada catatan penerbitan di log tahun <?php echo e(date('Y')); ?>.
          Dokumen ini bersifat <b>draft</b> (tanpa nomor surat).
        </div>
      <?php endif; ?>

      <!-- ISI SURAT -->
      <div class="content">
        <p>Kepada Yth.</p>
        <p>Orang Tua/Wali dari siswa tersebut di atas.</p>

        <p><?php echo $dasarAturan; ?></p>

        <div class="rincian-title">Rincian Riwayat Poin (Prestasi &amp; Pelanggaran)</div>
        <div class="rincian">
          <table>
            <thead>
              <tr>
                <th width="7%">No</th>
                <th width="22%">Waktu</th>
                <th width="17%">Jenis</th>
                <th>Uraian</th>
                <th width="12%">Poin (+/−)</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($riwayat)): ?>
                <tr><td colspan="5" style="text-align:center;color:#6b7280;">Belum ada riwayat prestasi/pelanggaran.</td></tr>
              <?php else: $i=1; foreach($riwayat as $row): ?>
                <tr>
                  <td><?php echo $i++; ?></td>
                  <td><?php echo e(date('d-m-Y H:i', strtotime($row['waktu']))); ?></td>
                  <td style="text-align:center;"><?php echo e($row['jenis']); ?></td>
                  <td><?php echo e($row['nama']); ?></td>
                  <td style="text-align:center;"><?php echo ($row['poin']>=0?'+':'').(int)$row['poin']; ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="4" style="text-align:center;">Total Prestasi</td>
                <td style="text-align:center;"><?php echo '+'.$totPrestasi; ?></td>
              </tr>
              <tr>
                <td colspan="4" style="text-align:center;">Total Pelanggaran</td>
                <td style="text-align:center;"><?php echo '-'.$totPelang; ?></td>
              </tr>
              <tr>
                <td colspan="4" style="text-align:center;">Saldo (Prestasi − Pelanggaran)</td>
                <td style="text-align:center;"><?php echo ($saldo>=0?'+':'').$saldo; ?></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <p><?php echo $pernyataan; ?></p>
        <p><?php echo $ajakan; ?></p>
        <p><?php echo $konsekuensi; ?></p>
        <p><?php echo $penutup; ?></p>
      </div>

      <!-- TANDA TANGAN (SP1: tanpa Kepala Sekolah) -->
      <div class="ttd">
        <div class="ttd-grid2">
          <!-- Kolom 1: Orang Tua/Wali -->
          <div class="sig">
            <div class="cap-role">Orang Tua/Wali,</div>
            <div class="space3"></div>
            </br>
            <div class="line"></div>
            <div class="name">&nbsp;</div>
          </div>

          <!-- Kolom 2: Wakasek Kesiswaan -->
          <div class="sig">
            <div class="cap-muted">ttd,</div>
            <div class="cap-role">Wakil Kepala Sekolah Bidang Kesiswaan</div>
            <div class="space3"></div>
            <div class="name"><?php echo e($wkNama); ?></div>
            <div class="nip">NIP. <?php echo e($wkNip); ?></div>
          </div>

          <!-- Kolom 3: Wali Kelas -->
          <div class="sig">
            <div class="cap-muted">Mengetahui,</div>
            <div class="cap-role">Wali Kelas</div>
            <div class="space3"></div>
            <div class="name"><?php echo e($waliNama); ?></div>
            <div class="nip">NIP. <?php echo e($waliNip); ?></div>
          </div>

          <!-- Kolom 4: Guru BK (penandatangan dari log) -->
          <div class="sig">
            <div class="cap-muted">Mengetahui,</div>
            <div class="cap-role">Guru BK</div>
            <div class="space3"></div>
            <div class="name"><?php echo e($bkNama); ?></div>
            <div class="nip">NIP. <?php echo e($bkNip); ?></div>
          </div>
        </div>
      </div>

    </div>
  </section>
</div>

<?php include 'footer.php'; ?>
