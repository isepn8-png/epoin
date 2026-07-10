<?php
// admin/export_csv.php
// Export CSV per kategori -> ZIP, dengan masking kolom sensitif.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/epoin_security.php';

// [SECURITY] Export data DB = sensitif. Wajib login admin (bukan lagi fallback `return true`).
epoin_staff_guard(true);
if (!function_exists('_is_admin')) { function _is_admin(){ return epoin_is_admin_session(); } }
if (!_is_admin()) {
  http_response_code(403);
  exit('Forbidden');
}

// ---- helper
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---- util tabel (copy ringan dari sekolah.php agar file ini mandiri)
function db_all_tables(mysqli $koneksi){
  $list=[]; $q=mysqli_query($koneksi,"SHOW TABLES");
  while($q && $r=mysqli_fetch_row($q)){ $list[]=$r[0]; }
  return $list;
}
function db_hard_exclude(){
  return ['user','users','roles','user_roles','sekolah','sekolah_license','sekolah_license_log','migrations','settings','ci_sessions','sessions'];
}
function db_category_tables(mysqli $koneksi, string $category){
  $all = db_all_tables($koneksi);
  $lowerAll = array_map('strtolower',$all);
  $intersectExisting = function(array $cands) use ($all,$lowerAll){
    $out=[];
    foreach($cands as $t){
      $i = array_search(strtolower($t), $lowerAll, true);
      if ($i!==false) $out[] = $all[$i];
    }
    return array_values(array_unique($out));
  };
  switch($category){
    case 'sekolah':     return $intersectExisting(['sekolah','sekolah_license','sekolah_license_log','sekolah_staff']);
    case 'pelanggaran': return $intersectExisting(['pelanggaran','sp_log']);
    case 'prestasi':    return $intersectExisting(['prestasi']);
    case 'absensi':     return $intersectExisting(['absensi_harian','absensi_sesi','absensi_sesi_detail','permohonan_absensi']);
    case 'master':      return $intersectExisting(['siswa','jurusan','kelas','mapel','ta']);
    case 'semua':       return array_values(array_diff($all,$intersectExisting(db_hard_exclude())));
    default:            return [];
  }
}

// ---- masking kolom sensitif
function is_sensitive_column($col){
  // match umum utk kolom sensitif
  return (bool)preg_match('/pass(word)?|token|secret|key|salt|hash|otp|code|session|auth|api|license/i', $col);
}
function mask_value($col, $val){
  if ($val===null || $val==='') return $val;
  // khusus: hash/password -> benar2 ditutup
  if (preg_match('/pass(word)?|hash|salt/i',$col)) return '[MASKED]';
  if (preg_match('/token|secret|key|otp|code|session|auth|api|license/i',$col)) {
    $s = (string)$val;
    $last4 = substr($s, -4);
    return str_repeat('*', max(0, strlen($s)-4)).$last4;
  }
  return $val; // default: tidak dimask
}

// ---- ambil kategori
$cat = preg_replace('~[^a-z_]~','', $_GET['cat'] ?? 'semua');
$tables = db_category_tables($koneksi, $cat ?: 'semua');

if (!$tables){
  http_response_code(404);
  exit('No tables for selected category.');
}

// pastikan ZipArchive ada
if (!class_exists('ZipArchive')) {
  http_response_code(500);
  exit('ZipArchive extension is required on server.');
}

// buat zip di tmp
$tmpZip = tempnam(sys_get_temp_dir(), 'csvzip_');
$zip = new ZipArchive();
if ($zip->open($tmpZip, ZipArchive::OVERWRITE)!==true){
  http_response_code(500);
  exit('Cannot create ZIP.');
}

// tambahkan README ringkas
$readme  = "CSV Export (kategori: $cat)\n";
$readme .= "Dibuat: ".date('Y-m-d H:i:s')."\n";
$readme .= "Catatan: Beberapa kolom sensitif telah dimasking otomatis.\n";
$zip->addFromString('README.txt', $readme);

// untuk setiap tabel -> CSV
mysqli_set_charset($koneksi, 'utf8mb4');
foreach($tables as $table){
  $tSafe = str_replace('`','``',$table);
  $q = mysqli_query($koneksi, "SELECT * FROM `$tSafe`");
  if (!$q) continue;

  // tulis CSV di memori
  $fp = fopen('php://temp', 'w+');
  // tulis BOM UTF8 agar Excel nyaman
  fwrite($fp, "\xEF\xBB\xBF");

  // header kolom
  $fields = [];
  while($finfo = mysqli_fetch_field($q)){ $fields[] = $finfo->name; }
  fputcsv($fp, $fields);

  // data
  while($row = mysqli_fetch_assoc($q)){
    $line = [];
    foreach($fields as $c){
      $v = $row[$c];
      if (is_sensitive_column($c)) $v = mask_value($c, $v);
      $line[] = $v;
    }
    fputcsv($fp, $line);
  }

  rewind($fp);
  $csvContent = stream_get_contents($fp);
  fclose($fp);

  $zip->addFromString($table.'.csv', $csvContent);
}

$zip->close();

// stream ke browser
$fname = 'backup_'.$cat.'_'.date('Ymd_His').'.csv.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Content-Length: '.filesize($tmpZip));
header('X-Content-Type-Options: nosniff');
readfile($tmpZip);
@unlink($tmpZip);
exit;
