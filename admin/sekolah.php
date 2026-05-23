<?php
// admin/sekolah.php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../koneksi.php';

// ---- Fallback role check kalau helper belum ada
if (!function_exists('_is_admin')) { function _is_admin(){ return true; } }

// ---- Helper kecil
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function table_exists_local($koneksi,$name){
  $q=@mysqli_query($koneksi,"SHOW TABLES LIKE '".mysqli_real_escape_string($koneksi,$name)."'");
  return $q && mysqli_num_rows($q)>0;
}
function get_role_id($koneksi,$key){
  $key = mysqli_real_escape_string($koneksi,$key);
  $q = mysqli_query($koneksi,"SELECT role_id FROM roles WHERE role_key='$key' LIMIT 1");
  $r = $q? mysqli_fetch_assoc($q):null;
  return $r? (int)$r['role_id']: 0;
}

/* =========================================================
   UTILITAS BASIS DATA (Backup/Restore/Clear)
   ========================================================= */

function db_all_tables(mysqli $koneksi){
  $list=[]; $q=mysqli_query($koneksi,"SHOW TABLES");
  while($q && $r=mysqli_fetch_row($q)){ $list[]=$r[0]; }
  return $list;
}

function db_hard_exclude(){
  // tabel inti yang tidak boleh dihapus saat clear per-kategori
  return ['user','users','roles','user_roles','sekolah','sekolah_license','sekolah_license_log','migrations','settings','ci_sessions','sessions'];
}

/**
 * Pilih tabel berdasarkan kategori yang DISESUAIKAN dengan skema DB pengguna.
 * Kategori: sekolah, pelanggaran, prestasi, absensi, master, semua
 */
function db_category_tables(mysqli $koneksi, string $category){
  $all = db_all_tables($koneksi);
  $lowerAll = array_map('strtolower',$all);
  $excludeHard = array_map('strtolower', db_hard_exclude());

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

/**
 * Membuat dump SQL tabel2 (create+insert)
 * (Tetap dipertahankan meski UI backup telah pindah ke CSV)
 */
function db_dump_tables_sql(mysqli $koneksi, array $tables): string{
  if (!$tables) return "-- (no tables)\n";
  mysqli_query($koneksi,"SET NAMES 'utf8mb4'");
  $out  = "-- Backup generated: ".date('Y-m-d H:i:s')."\n";
  $out .= "SET SQL_MODE=\"NO_AUTO_VALUE_ON_ZERO\";\n";
  $out .= "SET time_zone = \"+00:00\";\n";
  $out .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

  foreach($tables as $table){
    $tSafe = str_replace('`','``',$table);

    $qc = mysqli_query($koneksi,"SHOW CREATE TABLE `$tSafe`");
    if($qc && $cr = mysqli_fetch_assoc($qc)){
      $create = $cr['Create Table'] ?? array_values($cr)[1] ?? '';
      $out .= "-- ----------------------------\n";
      $out .= "-- Structure for table `$table`\n";
      $out .= "-- ----------------------------\n";
      $out .= "DROP TABLE IF EXISTS `$tSafe`;\n".$create.";\n\n";
    }

    $qd = mysqli_query($koneksi,"SELECT * FROM `$tSafe`");
    if($qd && mysqli_num_rows($qd)){
      $fields = [];
      while($finfo = mysqli_fetch_field($qd)){ $fields[]=$finfo->name; }
      $cols = '`'.implode('`,`', array_map(fn($c)=>str_replace('`','``',$c), $fields)).'`';
      $out .= "-- Data for `$table`\n";
      while($row = mysqli_fetch_assoc($qd)){
        $vals = [];
        foreach($fields as $c){
          $v = $row[$c];
          if (is_null($v)) { $vals[] = "NULL"; }
          else { $vals[] = "'".mysqli_real_escape_string($koneksi,(string)$v)."'"; }
        }
        $out .= "INSERT INTO `$tSafe` ($cols) VALUES (".implode(',',$vals).");\n";
      }
      $out .= "\n";
    }
  }

  $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
  return $out;
}

/* =========================================================
   Ambil/siapkan baris sekolah
   ========================================================= */

$sekolah = null;
$q = mysqli_query($koneksi,"SELECT * FROM sekolah LIMIT 1");
if ($q) $sekolah = mysqli_fetch_assoc($q);
if (!$sekolah){
  mysqli_query($koneksi, "INSERT INTO sekolah (nama_sekolah) VALUES ('Profil Sekolah')");
  $id = mysqli_insert_id($koneksi);
  $q = mysqli_query($koneksi,"SELECT * FROM sekolah WHERE sekolah_id=$id");
  $sekolah = $q? mysqli_fetch_assoc($q): null;
}
$sekolah_id = (int)($sekolah['sekolah_id'] ?? 0);

// ---- Status lisensi saat ini
function license_is_active_now(array $sk){
  if (($sk['license_status'] ?? '') !== 'active') return false;
  $exp = $sk['license_expires_at'] ?? null;
  if (!$exp) return true;
  return strtotime($exp) >= strtotime('today');
}
$__LICENSE_ACTIVE__ = license_is_active_now($sekolah);

// ---- Load daftar guru (user role 'guru')
$roleGuru = get_role_id($koneksi,'guru');
$daftar_guru = [];
if ($roleGuru) {
  $qg = mysqli_query($koneksi,"
    SELECT u.user_id, u.user_nama
    FROM user u
    JOIN user_roles ur ON ur.user_id=u.user_id AND ur.role_id=$roleGuru
    ORDER BY u.user_nama ASC
  ");
  while($qg && $row=mysqli_fetch_assoc($qg)){ $daftar_guru[] = $row; }
}

// ---- Ambil Guru BK/BP (multi)
$bp_selected = [];
if ($sekolah_id && table_exists_local($koneksi,'sekolah_staff')) {
  $qb = mysqli_query($koneksi,"SELECT user_id FROM sekolah_staff WHERE sekolah_id=$sekolah_id AND posisi_key='guru_bp'");
  while($qb && $r=mysqli_fetch_assoc($qb)){ $bp_selected[] = (int)$r['user_id']; }
}

/* =========================================================
   HANDLERS (POST/GET)
   ========================================================= */

// ---- Simpan Profil Umum (terkunci jika lisensi belum aktif)
if ($_SERVER['REQUEST_METHOD']==='POST'
    && ($_POST['aksi'] ?? '')==='save_profile' && _is_admin()){
  if (!$__LICENSE_ACTIVE__) {
    header("Location: sekolah.php?alert=license_required"); exit;
  }

  $nama     = trim($_POST['nama_sekolah'] ?? '');
  $npsn     = trim($_POST['npsn'] ?? '');
  $jenjang  = trim($_POST['jenjang'] ?? 'SMP');
  $alamat   = trim($_POST['alamat'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $telepon  = trim($_POST['telepon'] ?? '');
  $website  = trim($_POST['website'] ?? '');

  $kepala   = (int)($_POST['kepala_user_id'] ?? 0);
  $wakka    = (int)($_POST['wakasek_kesiswaan_id'] ?? 0);
  $wakur    = (int)($_POST['wakasek_kurikulum_id'] ?? 0);
  $bp_ids   = array_filter(array_map('intval', $_POST['bp_user_ids'] ?? []));

  // Upload logo (optional)
  $logo_sql = '';
  if (!empty($_FILES['logo']['name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    if (in_array($ext,['png','jpg','jpeg','webp'])) {
      $fname = 'logo_sekolah_'.$sekolah_id.'_'.time().'.'.$ext;
      $dest  = __DIR__ . '/../gambar/sistem/'.$fname;
      if (@move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
        if (!empty($sekolah['logo_path']) && file_exists(__DIR__.'/../gambar/sistem/'.$sekolah['logo_path'])) {
          @unlink(__DIR__.'/../gambar/sistem/'.$sekolah['logo_path']);
        }
        $logo_sql = ", logo_path='".mysqli_real_escape_string($koneksi,$fname)."'";
        $sekolah['logo_path'] = $fname;
      }
    }
  }

  $sql = "
    UPDATE sekolah SET
      nama_sekolah='".mysqli_real_escape_string($koneksi,$nama)."',
      npsn='".mysqli_real_escape_string($koneksi,$npsn)."',
      jenjang='".mysqli_real_escape_string($koneksi,$jenjang)."',
      alamat='".mysqli_real_escape_string($koneksi,$alamat)."',
      email='".mysqli_real_escape_string($koneksi,$email)."',
      telepon='".mysqli_real_escape_string($koneksi,$telepon)."',
      website='".mysqli_real_escape_string($koneksi,$website)."',
      kepala_user_id=$kepala,
      wakasek_kesiswaan_id=$wakka,
      wakasek_kurikulum_id=$wakur
      $logo_sql
    WHERE sekolah_id=$sekolah_id
  ";
  mysqli_query($koneksi,$sql);

  // Simpan guru BP (multi)
  if (table_exists_local($koneksi,'sekolah_staff')) {
    mysqli_query($koneksi,"DELETE FROM sekolah_staff WHERE sekolah_id=$sekolah_id AND posisi_key='guru_bp'");
    foreach($bp_ids as $uid){
      mysqli_query($koneksi,"INSERT IGNORE INTO sekolah_staff (sekolah_id,posisi_key,user_id) VALUES ($sekolah_id,'guru_bp',$uid)");
    }
  }

  header("Location: sekolah.php?alert=updated");
  exit;
}

// ---- Simpan Lisensi
if ($_SERVER['REQUEST_METHOD']==='POST'
    && ($_POST['aksi'] ?? '')==='save_license' && _is_admin()){

  $license_key = trim($_POST['license_key'] ?? '');
  $act_code    = trim($_POST['activation_code'] ?? '');
  $domain_name = trim($_POST['domain_name'] ?? '');
  $provider    = trim($_POST['hosting_provider'] ?? '');

  $status = null;      // null = jangan ubah status bila tidak aktivasi
  $expires = null;     // null = jangan ubah expiry bila tidak aktivasi
  $note   = null;

  if ($act_code !== '' && table_exists_local($koneksi,'license_codes')) {
    $codeEsc = mysqli_real_escape_string($koneksi,$act_code);
    $qc = mysqli_query($koneksi,"SELECT * FROM license_codes WHERE code='$codeEsc' AND status<>'revoked' LIMIT 1");
    if ($qc && $c=mysqli_fetch_assoc($qc)) {
      $today = date('Y-m-d');
      $validFromOk = empty($c['valid_from'])  || $today >= $c['valid_from'];
      $validUntilOk= empty($c['valid_until']) || $today <= $c['valid_until'];
      $usesOk      = ((int)$c['used_count'] < (int)$c['max_uses']);
      $domainOk    = empty($c['bound_domain']) || strtolower($c['bound_domain']) === strtolower($domain_name);
      $schoolOk    = empty($c['bound_school_id']) || (int)$c['bound_school_id'] === (int)$sekolah_id;

      if ($validFromOk && $validUntilOk && $usesOk && $domainOk && $schoolOk) {
        $status  = 'active';
        $expires = $c['license_until'];
        $note    = 'OK via code '.$act_code;

        mysqli_query($koneksi,"UPDATE license_codes
          SET used_count = used_count + 1,
              status = IF(used_count + 1 >= max_uses, 'used', status),
              bound_school_id = IF(bound_school_id IS NULL,$sekolah_id,bound_school_id)
          WHERE code_id=".(int)$c['code_id']);

        mysqli_query($koneksi,"INSERT INTO sekolah_license_log (sekolah_id,action,code_used,note)
          VALUES ($sekolah_id,'".($c['code_type']==='RENEW'?'renew':'activate')."','$codeEsc','Aktif s/d $expires')");
      } else {
        $note = 'Kode kedaluwarsa/limit/terikat ke domain/sek. lain.';
        mysqli_query($koneksi,"INSERT INTO sekolah_license_log (sekolah_id,action,code_used,note)
          VALUES ($sekolah_id,'check','$codeEsc','Gagal: $note')");
      }
    } else {
      $note = 'Kode tidak ditemukan.';
      mysqli_query($koneksi,"INSERT INTO sekolah_license_log (sekolah_id,action,code_used,note)
        VALUES ($sekolah_id,'check','".mysqli_real_escape_string($koneksi,$act_code)."','Gagal: $note')");
    }
  }
  elseif ($act_code !== '') {
    if (preg_match('~ACT-([0-9]{8})~',$act_code,$m)) {
      $expires = substr($m[1],0,4).'-'.substr($m[1],4,2).'-'.substr($m[1],6,2);
      $status  = 'active';
      $note    = 'Aktivasi (demo) '.$act_code;
      mysqli_query($koneksi,"INSERT INTO sekolah_license_log (sekolah_id,action,code_used,note)
        VALUES ($sekolah_id,'activate','".mysqli_real_escape_string($koneksi,$act_code)."','Aktif s/d $expires')");
    } elseif (preg_match('~RENEW-([0-9]{8})~',$act_code,$m)) {
      $expires = substr($m[1],0,4).'-'.substr($m[1],4,2).'-'.substr($m[1],6,2);
      $status  = 'active';
      $note    = 'Perpanjang (demo) '.$act_code;
      mysqli_query($koneksi,"INSERT INTO sekolah_license_log (sekolah_id,action,code_used,note)
        VALUES ($sekolah_id,'renew','".mysqli_real_escape_string($koneksi,$act_code)."','Perpanjang s/d $expires')");
    } else {
      $note = 'Kode tidak dikenali (demo).';
      mysqli_query($koneksi,"INSERT INTO sekolah_license_log (sekolah_id,action,code_used,note)
        VALUES ($sekolah_id,'check','".mysqli_real_escape_string($koneksi,$act_code)."','Kode tak valid')");
    }
  }

  // Upsert ke sekolah_license
  $ql = mysqli_query($koneksi,"SELECT id FROM sekolah_license WHERE sekolah_id=$sekolah_id LIMIT 1");
  if ($ql && $row=mysqli_fetch_assoc($ql)){
    $id=(int)$row['id'];
    $sets = [];
    $sets[] = "license_key='".mysqli_real_escape_string($koneksi,$license_key)."'";
    $sets[] = "provider_domain='".mysqli_real_escape_string($koneksi,$domain_name)."'";
    $sets[] = "provider_contact='".mysqli_real_escape_string($koneksi,$provider)."'";
    if (!is_null($status)) {
      $sets[] = "status='".mysqli_real_escape_string($koneksi,$status)."'";
      $sets[] = "activated_at = IF('$status'='active', IFNULL(activated_at,NOW()), activated_at)";
    }
    if (!is_null($expires)) { $sets[] = "expires_at='".mysqli_real_escape_string($koneksi,$expires)."'"; }
    $sets[] = "last_check_at=NOW()";
    $sets[] = "last_check_note=".($note? "'".mysqli_real_escape_string($koneksi,$note)."'":"NULL");
    mysqli_query($koneksi,"UPDATE sekolah_license SET ".implode(',', $sets)." WHERE id=$id");
  }else{
    $st = is_null($status) ? 'inactive' : $status;
    mysqli_query($koneksi,"
      INSERT INTO sekolah_license
        (sekolah_id, license_key, status, activated_at, expires_at, last_check_at, last_check_note, provider_domain, provider_contact)
      VALUES
        ($sekolah_id,
         '".mysqli_real_escape_string($koneksi,$license_key)."',
         '".mysqli_real_escape_string($koneksi,$st)."',
         ".($st==='active' ? "NOW()" : "NULL").",
         ".(!is_null($expires)? "'".mysqli_real_escape_string($koneksi,$expires)."'" : "NULL").",
         NOW(),
         ".($note? "'".mysqli_real_escape_string($koneksi,$note)."'" : "NULL").",
         '".mysqli_real_escape_string($koneksi,$domain_name)."',
         '".mysqli_real_escape_string($koneksi,$provider)."')
    ");
  }

  // Mirror ringkas ke tabel sekolah
  $sets2 = [];
  $sets2[] = "license_key='".mysqli_real_escape_string($koneksi,$license_key)."'";
  $sets2[] = "domain_name='".mysqli_real_escape_string($koneksi,$domain_name)."'";
  $sets2[] = "hosting_provider='".mysqli_real_escape_string($koneksi,$provider)."'";
  if (!is_null($status)) $sets2[] = "license_status='".mysqli_real_escape_string($koneksi,$status)."'";
  if (!is_null($expires)) $sets2[] = "license_expires_at='".mysqli_real_escape_string($koneksi,$expires)."'";
  mysqli_query($koneksi,"UPDATE sekolah SET ".implode(',', $sets2)." WHERE sekolah_id=$sekolah_id");

  header("Location: sekolah.php?alert=license_saved");
  exit;
}

/* ---------- HANDLER: BACKUP ----------
   (Tidak dipakai lagi di UI; dibiarkan atau bisa di-nonaktifkan)
*/
// (dinonaktifkan agar tidak digunakan via URL lama)
// if (isset($_GET['db']) && $_GET['db']==='backup' && _is_admin()){
//   $cat = preg_replace('~[^a-z_]~','', $_GET['cat'] ?? 'semua');
//   $tables = db_category_tables($koneksi, $cat ?: 'semua');
//   $sql = db_dump_tables_sql($koneksi, $tables);
//   $fname = 'backup_'.$cat.'_'.date('Ymd_His').'.sql';
//   header('Content-Type: application/sql; charset=UTF-8');
//   header('Content-Disposition: attachment; filename="'.$fname.'"');
//   echo $sql; exit;
// }

/* ---------- HANDLER: RESTORE ----------
   (DIHAPUS: restore umum ditiadakan untuk keamanan)
*/

/* ---------- HANDLER: CLEAR/KOSONGKAN (plus PIN guard) ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST'
    && ($_POST['aksi'] ?? '')==='db_clear' && _is_admin()){
  $cat = preg_replace('~[^a-z_]~','', $_POST['cat'] ?? '');
  $confirm = trim($_POST['confirm'] ?? '');
  $pin6 = trim($_POST['pin6'] ?? ''); // PIN dari modal
  // PIN default (bisa kamu pindah ke config/env)
  $PIN_DEFAULT = '123456';

  if (!$cat){ header("Location: sekolah.php?alert=clear_badcat"); exit; }
  if ($confirm!=='KOSONGKAN'){ header("Location: sekolah.php?alert=type_KOSONGKAN"); exit; }
  if ($pin6!==$PIN_DEFAULT){ header("Location: sekolah.php?alert=bad_pin"); exit; }

  $tables = db_category_tables($koneksi,$cat);
  if (!$tables){ header("Location: sekolah.php?alert=clear_notables"); exit; }

  mysqli_query($koneksi,"SET FOREIGN_KEY_CHECKS=0");
  foreach($tables as $t){
    $ts = str_replace('`','``',$t);
    @mysqli_query($koneksi,"TRUNCATE TABLE `$ts`");
  }
  mysqli_query($koneksi,"SET FOREIGN_KEY_CHECKS=1");
  header("Location: sekolah.php?alert=clear_ok_$cat"); exit;
}

/* ---- Refresh data terbaru setelah submit ---- */
$q = mysqli_query($koneksi,"SELECT * FROM sekolah LIMIT 1");
$sekolah = $q? mysqli_fetch_assoc($q): $sekolah;
$__LICENSE_ACTIVE__ = license_is_active_now($sekolah);

// ---- Ambil lisensi & log
$license = null;
$q = mysqli_query($koneksi,"SELECT * FROM sekolah_license WHERE sekolah_id=$sekolah_id LIMIT 1");
if ($q) $license = mysqli_fetch_assoc($q);
$logs = [];
$qlog = mysqli_query($koneksi,"SELECT * FROM sekolah_license_log WHERE sekolah_id=$sekolah_id ORDER BY log_id DESC LIMIT 20");
while($qlog && $r=mysqli_fetch_assoc($qlog)){ $logs[]=$r; }

// Nilai License Key tersimpan (untuk mengunci input)
$savedLicenseKey = trim($license['license_key'] ?? $sekolah['license_key'] ?? '');

// ---- Baru output HTML
include __DIR__ . '/header.php';
?>

<style>
  .school-hero{ display:flex; align-items:center; gap:16px; padding:18px; border-radius:14px; background:linear-gradient(90deg,#f0f9ff,#ecfeff); border:1px solid #dbeafe; box-shadow:0 6px 18px rgba(2,132,199,.08); }
  .school-hero .logo{ width:84px; height:84px; border-radius:12px; background:#fff; border:1px solid #e5e7eb; display:flex; align-items:center; justify-content:center; }
  .school-hero .logo img{ max-width:74px; max-height:74px; }
  .school-badge{ position:absolute; right:-1px; top:-1px; background:#fbbf24; color:#7c2d12; padding:6px 12px; border-bottom-left-radius:12px; font-weight:800; letter-spacing:.4px; }
  .card{ border:1px solid #e5e7eb; border-radius:12px; background:#fff; box-shadow:0 6px 22px rgba(0,0,0,.04); }
  .card h4{ margin:0 0 8px; font-weight:700; color:#0f172a; }
  .card .list-group-item{ border-color:#eef2f7; }
  .chip{ display:inline-flex; align-items:center; gap:6px; font-weight:700; padding:4px 10px; border-radius:999px; background:#f1f5f9; border:1px solid #e2e8f0; }
  .chip.ok{ background:#ecfeff; border-color:#bae6fd; color:#0369a1; }
  .chip.warn{ background:#fff7ed; border-color:#fed7aa; color:#9a3412; }
  .chip.danger{ background:#fef2f2; border-color:#fecaca; color:#7f1d1d; }
  .section-title{ font-weight:800; color:#0f172a; }
  .form-help{ color:#64748b; font-size:12px; margin-top:4px; }

  /* ===========================
     BEAUTIFY FORM (scoped)
     =========================== */
  #formSekolah { --soft-bg:#f8fafc; --soft-border:#dbeafe; --soft-border2:#bfdbfe; --soft-ring:rgba(59,130,246,.14); --txt:#0f172a; }
  #formSekolah h4{ font-weight:800; color:#0f172a; margin-bottom:8px; }
  #formSekolah label{ font-weight:700; color:#334155; margin-bottom:6px; }
  #formSekolah hr{ border-color:#e2e8f0; margin:16px 0; }

  #formSekolah .form-control{
    border-radius:10px; border:1px solid var(--soft-border);
    background: var(--soft-bg); color: var(--txt); height:42px;
    box-shadow:none; transition: all .18s ease;
  }
  #formSekolah textarea.form-control{ min-height:72px; height:auto; resize:vertical; padding-top:10px; padding-bottom:10px; }
  #formSekolah .form-control:focus{ background:#fff; border-color: var(--soft-border2); box-shadow: 0 0 0 4px var(--soft-ring); }

  #formSekolah select.select2{ width:100% !important; }
  #formSekolah .select2-container{ width:100% !important; font-size:13.5px; }
  #formSekolah .select2-container--default .select2-selection--single{
    height:42px; border-radius:10px; border:1px solid var(--soft-border); background: var(--soft-bg); transition: all .18s ease;
  }
  #formSekolah .select2-container--default .select2-selection--single:hover{ border-color: var(--soft-border2); }
  #formSekolah .select2-container--default.select2-container--focus .select2-selection--single{
    background:#fff; border-color: var(--soft-border2); box-shadow: 0 0 0 4px var(--soft-ring);
  }
  #formSekolah .select2-container .select2-selection--single .select2-selection__rendered{ line-height:42px; padding-left:12px; color:#111827; }
  #formSekolah .select2-container--default .select2-selection--single .select2-selection__placeholder{ color:#6b7280; }
  #formSekolah .select2-container--default .select2-selection--single .select2-selection__arrow{ height:42px; right:10px; }

  #formSekolah .select2-container--default .select2-selection--multiple{
    min-height:42px; border-radius:10px; border:1px solid var(--soft-border);
    background: var(--soft-bg); padding:4px 6px; display:flex; align-items:flex-start; flex-wrap:wrap; transition: all .18s ease;
  }
  #formSekolah .select2-container--default .select2-selection--multiple:hover{ border-color: var(--soft-border2); }
  #formSekolah .select2-container--default.select2-container--focus .select2-selection--multiple{
    background:#fff; border-color: var(--soft-border2); box-shadow: 0 0 0 4px var(--soft-ring);
  }
  #formSekolah .select2-container--default .select2-selection--multiple .select2-selection__choice{
    border:1px solid #bae6fd; background:#e0f2fe; color:#075985; border-radius:999px; padding:3px 8px; margin:3px 6px 3px 0; font-weight:700;
  }
  #formSekolah .select2-container--default .select2-selection--multiple .select2-selection__choice__remove{ color:#0369a1; margin-right:6px; font-weight:900; }
  #formSekolah .select2-container .select2-dropdown{ border:1px solid var(--soft-border2); border-radius:10px; overflow:hidden; box-shadow:0 12px 28px rgba(15,23,42,.12); }
  #formSekolah .select2-container .select2-search--dropdown .select2-search__field{ border-radius:8px; border:1px solid var(--soft-border); background:#f9fafb; }
  #formSekolah .select2-results__option--highlighted[aria-selected]{ background:#dbeafe; color:#0f172a; }
  #formSekolah .select2-results__option[aria-selected=true]{ background:#eff6ff; color:#0f172a; }
  #formSekolah .btn-primary{
    border-radius:10px; padding:8px 14px; font-weight:700;
    background: linear-gradient(90deg,#38bdf8,#60a5fa);
    border:1px solid #93c5fd;
    box-shadow: 0 6px 16px rgba(59,130,246,.25);
  }
  #formSekolah .btn-primary:hover{ filter: saturate(1.05); box-shadow: 0 10px 24px rgba(59,130,246,.28); transform: translateY(-1px); }
  #formSekolah .btn-primary:active{ transform: translateY(0); box-shadow:none; }
  #formSekolah .row + .row{ margin-top:10px; }

  /* ======= BASIS DATA UI ======= */
  .db-card h4{ font-weight:800; color:#1e293b; }
  .db-table{ width:100%; border-collapse:separate; border-spacing:0; overflow:hidden; border-radius:12px; border:1px solid #e5e7eb; }
  .db-table thead th{ background:#4338ca; color:#eef2ff; padding:10px 12px; font-weight:800; }
  .db-table tbody td{ background:#fafafa; border-top:1px solid #eef2f7; padding:10px 12px; vertical-align:middle; }
  .db-table tbody tr:nth-child(odd) td{ background:#fdfdfd; }
  .btn-soft{ border-radius:999px; padding:6px 12px; font-weight:700; border:1px solid #c7d2fe; background:#eef2ff; color:#3730a3; }
  .btn-soft:hover{ filter:saturate(1.05); }
  .btn-danger-soft{ border-radius:999px; padding:6px 12px; font-weight:700; border:1px solid #fecaca; background:#fef2f2; color:#991b1b; }
  .db-note{ color:#64748b; font-size:12px; }
  .db-badge{ display:inline-block; padding:2px 8px; border-radius:999px; background:#e2e8f0; color:#334155; font-weight:700; font-size:12px; }
  .db-cat{ font-weight:700; color:#0f172a; }

  /* ======= Lisensi INACTIVE/ACTIVE (tetap) ======= */
  .license-card.inactive{
    background: linear-gradient(135deg,#fff7ed 0%, #fffbeb 100%);
    border:1px solid #fed7aa;
    box-shadow: 0 10px 28px rgba(251,146,60,.15);
    position: relative;
  }
  .license-card .lic-callout{
    display:flex; align-items:center; gap:14px;
    padding:12px 14px; border-radius:12px;
    background:#fff; border:1px dashed #fecaa6;
    box-shadow: 0 6px 18px rgba(251,146,60,.12);
    margin-bottom:12px;
  }
  .license-card .lic-icon{
    width:46px; height:46px; border-radius:12px;
    display:flex; align-items:center; justify-content:center;
    background:linear-gradient(135deg,#ffedd5,#fde68a);
    border:1px solid #fcd34d;
    animation: pop 1.2s ease-in-out infinite alternate;
  }
  .license-card .lic-icon i{ font-size:22px; color:#7c2d12; }
  @keyframes pop{ 0%{ transform:scale(1);} 100%{ transform:scale(1.06);} }
  .license-card .lic-title{ font-weight:900; color:#7c2d12; letter-spacing:.2px; }
  .license-card .lic-sub{ color:#92400e; font-size:13px; }
  .license-card .btn-activate{
    margin-left:auto; white-space:nowrap;
    border-radius:999px; padding:8px 14px; font-weight:800;
    border:1px solid #fdba74; background:#ffedd5; color:#7c2d12;
  }
  .license-card .btn-activate:hover{ filter:saturate(1.05); }

  .license-card.active{
    position:relative;
    background: linear-gradient(135deg,#ecfeff 0%, #eff6ff 100%);
    border:1px solid #bae6fd;
    box-shadow: 0 16px 36px rgba(2,132,199,.18);
  }
  .license-card.active .licensed-badge{
    position:absolute; top:-10px; right:-10px;
    background: linear-gradient(90deg,#1d4ed8,#2563eb);
    color:#fff; padding:8px 12px; border-radius:12px;
    font-weight:900; letter-spacing:.3px;
    box-shadow:0 10px 22px rgba(37,99,235,.35);
    display:flex; align-items:center; gap:8px;
  }
  .lic-ok{
    display:flex; align-items:center; gap:12px;
    padding:12px; border-radius:12px;
    background:#ffffff; border:1px solid #dbeafe;
    box-shadow:0 6px 20px rgba(59,130,246,.10);
    margin-bottom:12px;
  }
  .lic-ok .ok-ico{
    width:46px; height:46px; border-radius:12px;
    display:flex; align-items:center; justify-content:center;
    background:linear-gradient(135deg,#dbeafe,#bfdbfe);
    border:1px solid #93c5fd; color:#1d4ed8; font-size:22px;
  }
  .lic-ok .ok-title{ font-weight:900; color:#1e3a8a; }
  .lic-ok .ok-sub{ color:#1e40af; font-size:13px; }

  .db-split .section-head{ display:flex; align-items:center; gap:8px; margin:8px 0; font-weight:800; color:#334155; }
  .db-split .section-head .badge{ font-size:11px; padding:3px 8px; border-radius:999px; font-weight:800; background:#eef2ff; color:#3730a3; border:1px solid #c7d2fe; }

  /* ======= PIN GUARD Modal ======= */
  .pin-overlay{ position:fixed; inset:0; background:rgba(15,23,42,.35); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); display:none; align-items:center; justify-content:center; z-index:9999; }
  .pin-modal{ width:520px; max-width:90%; background:#fff; border-radius:16px; box-shadow:0 30px 60px rgba(2,6,23,.35); overflow:hidden; border:1px solid #e5e7eb; }
  .pin-header{ background:linear-gradient(90deg,#2563eb,#3b82f6); color:#fff; padding:10px 14px; font-weight:800; display:flex; align-items:center; gap:8px; }
  .pin-body{ padding:20px; text-align:center; }
  .pin-inputs{ display:flex; gap:10px; justify-content:center; margin:12px 0 8px; }
  .pin-inputs input{ width:46px; height:46px; border-radius:10px; border:1px solid #cbd5e1; text-align:center; font-size:22px; font-weight:800; outline:none; transition:.15s; }
  .pin-inputs input:focus{ border-color:#93c5fd; box-shadow:0 0 0 4px rgba(59,130,246,.18); }
  .pin-actions{ display:flex; gap:10px; justify-content:center; margin-top:10px; }
  .pin-btn{ border-radius:10px; padding:8px 14px; font-weight:800; border:1px solid #93c5fd; background:linear-gradient(90deg,#38bdf8,#60a5fa); color:#fff; box-shadow:0 6px 16px rgba(59,130,246,.25); }
  .pin-btn.secondary{ background:#fff; color:#0f172a; border-color:#e5e7eb; box-shadow:none; }
  .pin-note{ color:#64748b; font-size:12px; margin-top:6px; }
  .pin-error{ color:#b91c1c; font-size:13px; font-weight:700; min-height:18px; margin-top:6px; }
  .pin-shake{ animation: pinshake .28s linear; }
  @keyframes pinshake{ 0%,100%{ transform:translateX(0);} 25%{transform:translateX(-6px);} 75%{transform:translateX(6px);} }


  /* ===== Track dasar ===== */
  #limitHostingPanel .meter{
    height:14px; background:#f1f5f9;
    border-radius:999px; overflow:hidden;
    border:1px solid #e5e7eb; position:relative;
  }

  /* ===== Bar isi: tumbuh maju (width) + shimmer ke kanan ===== */
  #limitHostingPanel .meter-bar{
    position:relative; height:100%; width:0;
    border-radius:999px;
    /* warna default (OK) – teal → blue */
    background:linear-gradient(90deg,#06b6d4 0%, #3b82f6 100%);
    transition: width .9s cubic-bezier(.22,1,.36,1);
  }

  /* Shimmer stripes ke KANAN */
#limitHostingPanel .meter-bar::after{
  content:"";
  position:absolute; inset:0;
  border-radius:inherit;
  /* satu pita highlight lembut, tanpa repeat */
  background: linear-gradient(100deg,
    rgba(255,255,255,0) 0%,
    rgba(255,255,255,.18) 10%,
    rgba(255,255,255,.36) 20%,
    rgba(255,255,255,.18) 30%,
    rgba(255,255,255,0) 40%);
  background-size: 250% 100%;
  background-repeat: no-repeat;
  mix-blend-mode: screen;      /* bikin highlight menyatu elegan */
  animation: shimmer-sheen 2.6s ease-in-out infinite;
  pointer-events:none;
}

/* optional: bila 0% atau 100% kamu sudah kasih .no-anim, ini akan nonaktif */
#limitHostingPanel .meter-bar.no-anim::after{ display:none; }

/* Keyframes BARU (hapus/abaikan meter-march lama) */
@keyframes shimmer-sheen{
  from{ background-position: 150% 0; }
  to  { background-position: -150% 0; }
}

/* Respect reduced motion */
@media (prefers-reduced-motion: reduce){
  #limitHostingPanel .meter-bar::after{ animation: none; }
}

  /* End-cap dot berdenyut di ujung bar */
  #limitHostingPanel .meter-bar::before{
    content:""; position:absolute; top:50%; right:-6px;
    width:12px; height:12px; border-radius:999px; transform:translateY(-50%);
    background:#fff; box-shadow:
      0 0 0 3px rgba(59,130,246,.35),
      0 0 10px rgba(59,130,246,.35);
    animation: meter-dot 1.6s ease-in-out infinite;
  }

  /* ===== Variasi warna berdasar level ===== */
  #limitHostingPanel .meter-bar.is-ok{
    background:linear-gradient(90deg,#06b6d4 0%, #3b82f6 100%);
  }
  #limitHostingPanel .meter-bar.is-warn{
    background:linear-gradient(90deg,#f59e0b 0%, #f97316 100%);
  }
  #limitHostingPanel .meter-bar.is-crit{
    background:linear-gradient(90deg,#ef4444 0%, #f43f5e 100%);
  }

  /* Saat 0% atau 100%: matikan shimmer & dot */
  #limitHostingPanel .meter-bar.no-anim::after,
  #limitHostingPanel .meter-bar.no-anim::before{ display:none; }

  /* Info teks */
  #limitHostingPanel .meter-info{
    display:flex; justify-content:space-between;
    font-size:12px; color:#0f172a; margin-top:6px;
  }

  /* Keyframes */
  @keyframes meter-march{
    from{ transform: translateX(-48px); }
    to  { transform: translateX(48px); }
  }
  @keyframes meter-dot{
    0%,100%{ transform:translateY(-50%) scale(.95); box-shadow:0 0 0 2px rgba(59,130,246,.25), 0 0 8px rgba(59,130,246,.25); }
    50%   { transform:translateY(-50%) scale(1.05); box-shadow:0 0 0 4px rgba(59,130,246,.45), 0 0 14px rgba(59,130,246,.45); }
  }

  /* High-contrast: saat warning/critical, dot ikut warna */
  #limitHostingPanel .meter-bar.is-warn::before{
    box-shadow:0 0 0 3px rgba(249,115,22,.35), 0 0 10px rgba(249,115,22,.35);
  }
  #limitHostingPanel .meter-bar.is-crit::before{
    box-shadow:0 0 0 3px rgba(244,63,94,.35), 0 0 10px rgba(244,63,94,.35);
  }

  
</style>

<div class="content-wrapper" id="contentWrap">
  <section class="content-header">
    <h1 class="section-title"><i class="fa-solid fa-school"></i> Profil Sekolah</h1>
    <ol class="breadcrumb"><li><a href="index.php"><i class="fa fa-dashboard"></i> Home</a></li><li class="active">Sekolah</li></ol>
  </section>

  <section class="content">
    <div class="row">
      <div class="col-12 col-lg-7">
        <div class="card" style="position:relative; padding:16px;">
          <span class="school-badge">SEKOLAH</span>
          <div class="school-hero">
            <div class="logo">
              <?php if(!empty($sekolah['logo_path'])): ?>
                <img src="../gambar/sistem/<?php echo esc($sekolah['logo_path']); ?>" alt="Logo Sekolah">
              <?php else: ?>
                <img src="../gambar/sistem/logonesagun.png" alt="Logo">
              <?php endif; ?>
            </div>
            <div>
              <div style="font-size:18px; font-weight:800; letter-spacing:.2px;"><?php echo esc($sekolah['nama_sekolah'] ?: 'Nama Sekolah'); ?></div>
              <div class="text-muted"><?php echo esc($sekolah['jenjang'] ?: 'SMP'); ?></div>
              <?php
                $status = $sekolah['license_status'] ?: 'inactive';
                $exp    = $sekolah['license_expires_at'] ? date('d M Y', strtotime($sekolah['license_expires_at'])) : '-';
                $chipClass = ($status==='active'?'ok':($status==='expired'?'danger':'warn'));
              ?>
              <div style="margin-top:6px;">
                <span class="chip <?php echo $chipClass; ?>"><i class="fa-solid fa-id-badge"></i> Lisensi: <?php echo strtoupper($status); ?> (s/d <?php echo $exp; ?>)</span>
              </div>
            </div>
          </div>

          <ul class="list-group" style="margin-top:14px;">
            <li class="list-group-item"><b><i class="fa-solid fa-barcode"></i> NPSN</b><br><?php echo esc($sekolah['npsn']); ?></li>
            <li class="list-group-item"><b><i class="fa-solid fa-phone"></i> No. Telepon</b><br><?php echo esc($sekolah['telepon']); ?></li>
            <li class="list-group-item"><b><i class="fa-solid fa-location-dot"></i> Alamat</b><br><?php echo esc($sekolah['alamat']); ?></li>
            <li class="list-group-item"><b><i class="fa-solid fa-envelope"></i> e-mail</b><br><?php echo esc($sekolah['email']); ?></li>
            <li class="list-group-item"><b><i class="fa-solid fa-globe"></i> Website</b><br><?php echo esc($sekolah['website']); ?></li>
          </ul>

          <?php if (_is_admin()): ?>
          <div style="padding:14px; border-top:1px solid #e5e7eb;">
            <button class="btn btn-success btn-sm" data-toggle="collapse" data-target="#formSekolah"><i class="fa fa-pen"></i> Ubah Data</button>
          </div>

          <div id="formSekolah" class="collapse" style="padding:14px;">
            <?php if (!$__LICENSE_ACTIVE__): ?>
              <div class="callout callout-warning" style="margin:10px 0">
                <b>Aplikasi belum diaktivasi.</b>
                Silakan masukkan <i>License Key</i> dan <i>Kode Aktivasi</i> pada panel <b>Aktivasi & Lisensi</b> di sebelah kanan.
                Setelah aktif, bar ini akan hilang.
              </div>
            <?php endif; ?>

            <fieldset <?php echo (!$__LICENSE_ACTIVE__ ? 'disabled' : ''); ?>>
              <form id="formProfilSekolah" action="" method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="aksi" value="save_profile">
                <div class="row">
                  <div class="col-sm-6">
                    <label data-toggle="tooltip" title="Nama resmi lembaga.">Nama Sekolah</label>
                    <input type="text" class="form-control" name="nama_sekolah" value="<?php echo esc($sekolah['nama_sekolah']); ?>" required>
                  </div>
                  <div class="col-sm-3">
                    <label data-toggle="tooltip" title="Nomor Pokok Sekolah Nasional.">NPSN</label>
                    <input type="text" class="form-control" name="npsn" value="<?php echo esc($sekolah['npsn']); ?>">
                  </div>
                  <div class="col-sm-3">
                    <label>Jenjang</label>
                    <input type="text" class="form-control" name="jenjang" value="<?php echo esc($sekolah['jenjang']); ?>">
                  </div>
                </div>

                <div class="row" style="margin-top:8px;">
                  <div class="col-sm-6">
                    <label>Alamat</label>
                    <textarea class="form-control" name="alamat" rows="2"><?php echo esc($sekolah['alamat']); ?></textarea>
                  </div>
                  <div class="col-sm-3">
                    <label>e-mail</label>
                    <input type="email" class="form-control" name="email" value="<?php echo esc($sekolah['email']); ?>">
                  </div>
                  <div class="col-sm-3">
                    <label>Telepon</label>
                    <input type="text" class="form-control" name="telepon" value="<?php echo esc($sekolah['telepon']); ?>">
                  </div>
                </div>

                <div class="row" style="margin-top:8px;">
                  <div class="col-sm-6">
                    <label>Website</label>
                    <input type="text" class="form-control" name="website" value="<?php echo esc($sekolah['website']); ?>" placeholder="https://...">
                  </div>
                </div>

                <hr>
                <h4>Pejabat Sekolah</h4>
                <div class="row">
                  <div class="col-sm-4">
                    <label data-toggle="tooltip" title="Dipakai untuk dokumen umum & cadangan penandatangan SP bila Waka Kesiswaan kosong.">
                      Kepala Sekolah <i class="fa fa-circle-info tip"></i>
                    </label>
                    <select class="form-control select2" name="kepala_user_id" data-placeholder="Pilih Kepala Sekolah">
                      <option value="0">—</option>
                      <?php foreach($daftar_guru as $g): ?>
                        <option value="<?php echo (int)$g['user_id']; ?>" <?php echo ((int)$sekolah['kepala_user_id']==(int)$g['user_id']?'selected':''); ?>>
                          <?php echo esc($g['user_nama']); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-sm-4">
                    <label data-toggle="tooltip" title="Secara otomatis dicetak sebagai penandatangan utama SP. Jika belum diisi, sistem menggunakan Kepala Sekolah.">
                      Waka Kesiswaan <i class="fa fa-circle-info tip"></i>
                    </label>
                    <select class="form-control select2" name="wakasek_kesiswaan_id" data-placeholder="Pilih Wakka">
                      <option value="0">—</option>
                      <?php foreach($daftar_guru as $g): ?>
                        <option value="<?php echo (int)$g['user_id']; ?>" <?php echo ((int)$sekolah['wakasek_kesiswaan_id']==(int)$g['user_id']?'selected':''); ?>>
                          <?php echo esc($g['user_nama']); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-sm-4">
                    <label data-toggle="tooltip" title="Digunakan pada dokumen akademik (mis. kurikulum/penilaian) bila diperlukan.">
                      <!-- (opsional) DIHILANGKAN -->
                      Waka Kurikulum <i class="fa fa-circle-info tip"></i>
                    </label>
                    <select class="form-control select2" name="wakasek_kurikulum_id" data-placeholder="Pilih Wakur">
                      <option value="0">—</option>
                      <?php foreach($daftar_guru as $g): ?>
                        <option value="<?php echo (int)$g['user_id']; ?>" <?php echo ((int)$sekolah['wakasek_kurikulum_id']==(int)$g['user_id']?'selected':''); ?>>
                          <?php echo esc($g['user_nama']); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="row" style="margin-top:8px;">
                  <div class="col-sm-12">
                    <label data-toggle="tooltip" title="Ditampilkan pada bagian Mengetahui (SP & laporan pembinaan). Pilih satu atau lebih guru BK.">
                      Guru BK/BP (bisa lebih dari satu) <i class="fa fa-circle-info tip"></i>
                    </label>
                    <select id="bp_select" class="form-control select2" name="bp_user_ids[]" multiple data-placeholder="Pilih Guru BK/BP">
                      <?php foreach($daftar_guru as $g): ?>
                        <option value="<?php echo (int)$g['user_id']; ?>" <?php echo in_array((int)$g['user_id'],$bp_selected,true)?'selected':''; ?>>
                          <?php echo esc($g['user_nama']); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <div class="form-help">Urutkan “Mengetahui” dengan drag pada chip di bawah.</div>
                    <div id="bkSortable" class="bk-sortable-chips"></div>
                  </div>
                </div>

                <div class="form-help" style="margin-top:10px">
                  <b>NIP (otomatis)</b> — Sistem mengambil NIP dari profil pengguna. Bila belum ada, lengkapi NIP pada profil user. Format 18 digit, tanpa spasi/tanda baca.
                </div>

                <hr>
                <label>Logo Sekolah (PNG/JPG/WEBP)</label>
                <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp">

                <div class="text-right" style="margin-top:12px;">
                  <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
                </div>
              </form>
            </fieldset>
          </div>
          <?php endif; ?>
        </div>
        <!-- Panel Pejabat & Penandatangan -->
        <div class="card" style="padding:16px; margin-bottom:16px;">
          <h4 style="display:flex; align-items:center; gap:8px;">
            <i class="fa-solid fa-user-tie"></i> Pejabat & Penandatangan
          </h4>
          <div class="db-note" style="margin-top:6px; line-height:1.5;">
            Data pada panel ini dipakai otomatis sebagai nama, jabatan, dan NIP penandatangan di berbagai dokumen sistem
            (mis. Surat Peringatan (SP), surat keterangan, rekap pelanggaran, dll). Pastikan diperbarui saat terjadi pergantian pejabat.
          </div>
          <div class="list-group" style="margin-top:8px;">
            <div class="list-group-item"><b>Kepala Sekolah</b><br><?php
              if($sekolah['kepala_user_id']){
                $q=mysqli_query($koneksi,"SELECT user_nama FROM user WHERE user_id=".(int)$sekolah['kepala_user_id']." LIMIT 1");
                $r=$q?mysqli_fetch_assoc($q):null; echo esc($r['user_nama']??'—');
              }else{ echo '—'; } ?></div>

            <div class="list-group-item"><b>Waka Kesiswaan</b><br><?php
              if($sekolah['wakasek_kesiswaan_id']){
                $q=mysqli_query($koneksi,"SELECT user_nama FROM user WHERE user_id=".(int)$sekolah['wakasek_kesiswaan_id']." LIMIT 1");
                $r=$q?mysqli_fetch_assoc($q):null; echo esc($r['user_nama']??'—');
              }else{ echo '—'; } ?></div>

            <div class="list-group-item"><b>Waka Kurikulum</b><br><?php
              if($sekolah['wakasek_kurikulum_id']){
                $q=mysqli_query($koneksi,"SELECT user_nama FROM user WHERE user_id=".(int)$sekolah['wakasek_kurikulum_id']." LIMIT 1");
                $r=$q?mysqli_fetch_assoc($q):null; echo esc($r['user_nama']??'—');
              }else{ echo '—'; } ?></div>

            <div class="list-group-item"><b>Guru BK/BP</b><br>
              <?php
                $names=[];
                if ($sekolah_id && table_exists_local($koneksi,'sekolah_staff')){
                  $qb=mysqli_query($koneksi,"
                    SELECT u.user_nama FROM sekolah_staff s
                    JOIN user u ON u.user_id=s.user_id
                    WHERE s.sekolah_id=$sekolah_id AND s.posisi_key='guru_bp'
                    ORDER BY u.user_nama
                  ");
                  while($qb && $r=mysqli_fetch_assoc($qb)){ $names[]=$r['user_nama']; }
                }
                echo $names? esc(implode(', ',$names)) : '—';
              ?>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-5">

        <!-- Panel Aktivasi & Lisensi -->
        <div id="licensePanel" class="card license-card <?php echo (!$__LICENSE_ACTIVE__ ? 'inactive' : 'active'); ?>" style="padding:16px;">
          <h4 style="display:flex;align-items:center;gap:8px;"><i class="fa-solid fa-shield-check"></i> Aktivasi & Lisensi</h4>

          <?php if ($__LICENSE_ACTIVE__): ?>
            <div class="licensed-badge"><i class="fa fa-check-circle"></i> AKTIF</div>
            <div class="lic-ok">
              <div class="ok-ico"><i class="fa fa-shield-heart"></i></div>
              <div>
                <div class="ok-title">Sudah Berlisensi & Teraktivasi</div>
                <div class="ok-sub">
                  Terima kasih. Lisensi aktif hingga
                  <b><?php echo $sekolah['license_expires_at'] ? date('d M Y', strtotime($sekolah['license_expires_at'])) : '-'; ?></b>.
                </div>
              </div>
            </div>
          <?php else: ?>
            <div class="lic-callout">
              <div class="lic-icon"><i class="fa-solid fa-unlock-keyhole"></i></div>
              <div class="lic-text">
                <div class="lic-title">Aktivasi Diperlukan</div>
                <div class="lic-sub">Masukkan <b>License Key</b> & <b>Kode Aktivasi</b> agar semua fitur dapat digunakan.</div>
              </div>
              <a href="#licensePanel" class="btn-activate" id="btnActivate"><i class="fa-solid fa-key"></i> Aktifkan</a>
            </div>
          <?php endif; ?>

          <form id="licenseForm" class="license-form" action="" method="post" autocomplete="off" style="margin-top:8px;">
            <input type="hidden" name="aksi" value="save_license">

            <div class="row">
              <div class="col-sm-12">
                <label>License Key</label>
                <?php if ($savedLicenseKey !== ''): ?>
                  <!-- Terkunci: tetap terkirim (readonly, bukan disabled) -->
                  <div class="input-group">
                    <input id="license_key" type="text" class="form-control" name="license_key"
                           value="<?php echo esc($savedLicenseKey); ?>" readonly>
                    <span class="input-group-addon" title="Terkunci"><i class="fa fa-lock"></i></span>
                  </div>
                  <div class="form-help">License Key tersimpan & terkunci. Gunakan <b>Kode Perpanjang</b> untuk memperbarui masa aktif.</div>
                <?php else: ?>
                  <input id="license_key" type="text" class="form-control" name="license_key"
                         value="" placeholder="Masukkan License Key EPS-XXXXX-...">
                <?php endif; ?>
              </div>
            </div>

            <div class="row" style="margin-top:8px;">
              <div class="col-sm-12">
                <label>Kode Aktivasi / Perpanjang</label>
                <input id="activation_code" type="text" class="form-control" name="activation_code" placeholder="Mis: ACT-20261231-XXXXX / RENEW-...">
                <div class="form-help">Tanggal kedaluwarsa akan diambil otomatis dari kode / bank kode.</div>
              </div>
            </div>

            <div class="row" style="margin-top:8px;">
              <div class="col-sm-7">
                <label>Domain</label>
                <input type="text" class="form-control" name="domain_name" value="<?php echo esc($sekolah['domain_name']); ?>" placeholder="contoh: sekolah.sch.id">
              </div>
              <div class="col-sm-5">
                <label>Hosting/Provider</label>
                <!-- default otomatis www.epoinsiswa.com bila kosong -->
                <input type="text" class="form-control" name="hosting_provider" value="<?php echo esc($sekolah['hosting_provider'] ?: 'www.epoinsiswa.com'); ?>" placeholder="Nama provider">
              </div>
            </div>

            <div class="text-right" style="margin-top:12px;">
              <button id="btnSaveLicense" class="btn btn-lic btn-sm"><i class="fa fa-save"></i> Simpan Lisensi</button>
            </div>
          </form>

          <div style="margin-top:12px;">
            <b>Status:</b>
            <?php
              $chipClass = ($sekolah['license_status']==='active'?'ok':($sekolah['license_status']==='expired'?'danger':'warn'));
              $exp = $sekolah['license_expires_at'] ? date('d M Y', strtotime($sekolah['license_expires_at'])) : '-';
            ?>
            <span class="chip <?php echo $chipClass; ?>"><i class="fa-solid fa-id-badge"></i> <?php echo strtoupper($sekolah['license_status'] ?: 'INACTIVE'); ?></span>
            <?php if($sekolah['license_expires_at']): ?>
              <span class="chip"><i class="fa fa-clock"></i> Exp: <?php echo $exp; ?></span>
            <?php endif; ?>
          </div>

          <?php if ($logs): ?>
          <hr>
          <div><b>Riwayat</b></div>
          <ul class="list-unstyled" style="margin-top:6px; max-height:180px; overflow:auto;">
            <?php foreach($logs as $lg): ?>
              <li style="padding:6px 8px; border-left:3px solid #bae6fd; margin-bottom:6px; background:#f8fafc; border-radius:6px;">
                <small><?php echo esc($lg['created_at']); ?></small><br>
                <b><?php echo strtoupper(esc($lg['action'])); ?></b>
                <?php if($lg['code_used']): ?> — <code><?php echo esc($lg['code_used']); ?></code><?php endif; ?>
                <?php if($lg['note']): ?> <span class="text-muted">· <?php echo esc($lg['note']); ?></span><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </div>
<!-- Panel Limit & Hosting (versi safety + fallback informasi schema) -->
<?php
  if (!function_exists('esc')){ function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
  if (!function_exists('table_exists_local')){
    function table_exists_local(mysqli $koneksi, string $table): bool {
      $table_esc = mysqli_real_escape_string($koneksi, $table);
      $qdb = mysqli_query($koneksi, "SELECT DATABASE() AS db");
      $dbrow = $qdb ? mysqli_fetch_assoc($qdb) : null;
      $db = $dbrow && isset($dbrow['db']) ? $dbrow['db'] : '';
      if ($db === '') return false;
      $db_esc = mysqli_real_escape_string($koneksi, $db);
      $q = mysqli_query($koneksi, "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA='$db_esc' AND TABLE_NAME='$table_esc' LIMIT 1");
      return $q && mysqli_fetch_row($q) ? true : false;
    }
  }
  if (!function_exists('format_bytes_local')){
    function format_bytes_local($b){
      $b = (float)$b; $u=['B','KB','MB','GB','TB']; $i=0;
      while($b>=1024 && $i<count($u)-1){ $b/=1024; $i++; }
      $dec = $i>=2? 2:0; return number_format($b,$dec).' '.$u[$i];
    }
  }
  if (!function_exists('pct_local')){
    function pct_local($used,$limit){ return ($limit>0? max(0,min(100, (int)round(($used/$limit)*100))):0); }
  }
  if (!isset($sekolah_id)){ $sekolah_id = (int)($sekolah['sekolah_id'] ?? ($_SESSION['sekolah_id'] ?? 0)); }

  $disk_limit_mb       = 500;
  $bandwidth_limit_gb  = null;
  $inode_limit         = null;
  $disk_used_bytes     = 0;
  $bw_used_bytes       = 0;
  $inode_used          = 0;

  if (table_exists_local($koneksi,'tenant_quota')){
    $qs = mysqli_query($koneksi, "SELECT * FROM tenant_quota WHERE sekolah_id=".$sekolah_id." LIMIT 1");
    if ($qs && $row = mysqli_fetch_assoc($qs)){
      $disk_limit_mb      = (int)($row['disk_limit_mb'] ?? $disk_limit_mb);
      $bandwidth_limit_gb = isset($row['bandwidth_limit_gb']) && $row['bandwidth_limit_gb']!=='' ? (int)$row['bandwidth_limit_gb'] : null;
      $inode_limit        = isset($row['inode_limit']) && $row['inode_limit']!=='' ? (int)$row['inode_limit'] : null;
    }
  }

  $ulog_exists = table_exists_local($koneksi,'usage_log');
  $uday_exists = table_exists_local($koneksi,'usage_daily');

  if ($ulog_exists){
    if ($uday_exists){
      $qday = mysqli_query($koneksi, "SELECT COALESCE(SUM(disk_delta_bytes),0) AS disk_sum, COALESCE(SUM(bandwidth_bytes),0) AS bw_sum, COALESCE(SUM(inode_delta),0) AS inode_sum FROM usage_daily WHERE sekolah_id=".$sekolah_id);
      if ($qday && $r = mysqli_fetch_assoc($qday)){ $disk_used_bytes += (int)$r['disk_sum']; $bw_used_bytes += (int)$r['bw_sum']; $inode_used += (int)$r['inode_sum']; }
      $qtoday = mysqli_query($koneksi, "SELECT
          GREATEST(0, SUM(CASE WHEN action IN ('upload','import','insert') THEN bytes WHEN action IN ('delete','remove') THEN -bytes ELSE 0 END)) AS disk_used,
          GREATEST(0, SUM(CASE WHEN action IN ('download','export') THEN bytes ELSE 0 END)) AS bw_used,
          SUM(CASE WHEN action='create_object' THEN 1 WHEN action='delete_object' THEN -1 ELSE 0 END) AS inode_used
        FROM usage_log WHERE sekolah_id=".$sekolah_id." AND DATE(occurred_at)=CURDATE()");
      if ($qtoday && $r = mysqli_fetch_assoc($qtoday)){ $disk_used_bytes += (int)($r['disk_used'] ?? 0); $bw_used_bytes += (int)($r['bw_used'] ?? 0); $inode_used += (int)($r['inode_used'] ?? 0); }
    } else {
      $qu = mysqli_query($koneksi, "SELECT
          GREATEST(0, SUM(CASE WHEN action IN ('upload','import','insert') THEN bytes WHEN action IN ('delete','remove') THEN -bytes ELSE 0 END)) AS disk_used,
          GREATEST(0, SUM(CASE WHEN action IN ('download','export') THEN bytes ELSE 0 END)) AS bw_used,
          SUM(CASE WHEN action='create_object' THEN 1 WHEN action='delete_object' THEN -1 ELSE 0 END) AS inode_used
        FROM usage_log WHERE sekolah_id=".$sekolah_id);
      if ($qu && $r = mysqli_fetch_assoc($qu)){ $disk_used_bytes = (int)($r['disk_used'] ?? 0); $bw_used_bytes = (int)($r['bw_used'] ?? 0); $inode_used = (int)($r['inode_used'] ?? 0); }
    }
  } else {
    $qdb = mysqli_query($koneksi, "SELECT DATABASE() AS db");
    $dbrow = $qdb ? mysqli_fetch_assoc($qdb) : null;
    $db = $dbrow && isset($dbrow['db']) ? mysqli_real_escape_string($koneksi, $dbrow['db']) : '';
    if ($db !== ''){
      $qsize = mysqli_query($koneksi, "
        SELECT COALESCE(SUM(DATA_LENGTH + INDEX_LENGTH),0) AS total_bytes,
               COUNT(*) AS table_count,
               COALESCE(SUM(TABLE_ROWS),0) AS total_rows
        FROM information_schema.TABLES WHERE TABLE_SCHEMA='$db'");
      if ($qsize && $rs = mysqli_fetch_assoc($qsize)){
        $disk_used_bytes = (int)$rs['total_bytes'];
        $inode_used = (int)$rs['table_count'];
      }
    }
  }

  $disk_limit_bytes = $disk_limit_mb * 1024 * 1024;
  $pct_disk = pct_local($disk_used_bytes, $disk_limit_bytes);
  $pct_bw   = $bandwidth_limit_gb ? pct_local($bw_used_bytes, $bandwidth_limit_gb * 1024 * 1024 * 1024) : 0;
  $pct_inode= $inode_limit ? pct_local(max(0,$inode_used), $inode_limit) : 0;
?>
<div id="limitHostingPanel" class="card" style="padding:0; overflow:hidden; border:1px solid #e5e7eb; margin-top:14px;">
  <div style="display:flex; align-items:center; gap:10px; padding:14px 16px; background:linear-gradient(90deg,#06b6d4,#3b82f6); color:#fff;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 5v6h5v2h-7V7h2z"/></svg>
    <div style="font-weight:800; letter-spacing:.3px;">Status Kuota Hosting</div>
    <div style="margin-left:auto; font-size:12px; opacity:.9;">Tenant: <b><?php echo esc($sekolah['nama_sekolah'] ?? '—'); ?></b></div>
  </div>

  <div style="padding:16px;">
<!-- ===== Panel Status Kuota Hosting (REVISI) ===== -->
<div id="limitHostingPanel">
  <div class="usage-box" style="margin-bottom:16px;">
    <div class="usage-head" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
      <div style="display:flex;align-items:center;gap:8px;font-weight:700;color:#0f172a;">
        <i class="fa-solid fa-hdd"></i> <span>Disk Space</span>
      </div>
      <div class="chip" title="Terpakai / Kuota" style="font-size:12px;border-radius:999px;background:#eef2ff;color:#3730a3;padding:4px 10px;">
        <?php echo format_bytes_local($disk_used_bytes); ?> / <?php echo number_format($disk_limit_mb); ?> MB
      </div>
    </div>

    <div class="meter">
      <!-- data-pct tetap dipakai untuk target pengisian -->
      <div class="meter-bar" data-pct="<?php echo $pct_disk; ?>"></div>
    </div>

    <div class="meter-info">
      <span><?php echo $pct_disk; ?>%</span>
      <span>tersisa: <b><?php echo format_bytes_local(max(0,$disk_limit_bytes - $disk_used_bytes)); ?></b></span>
    </div>
  </div>
</div>

      <?php if ($bandwidth_limit_gb): ?>
      <div class="usage-box" style="margin-bottom:16px;">
        <div class="usage-head" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
          <div style="display:flex;align-items:center;gap:8px;font-weight:700;color:#0f172a;">
            <i class="fa-solid fa-wave-square"></i> <span>Bandwidth</span>
          </div>
          <div class="chip" style="font-size:12px;border-radius:999px;background:#ecfeff;color:#155e75;padding:4px 10px;">
            <?php echo format_bytes_local($bw_used_bytes); ?> / <?php echo (int)$bandwidth_limit_gb; ?> GB
          </div>
        </div>
        <div class="meter">
          <div class="meter-bar meter-2" data-pct="<?php echo $pct_bw; ?>"></div>
        </div>
        <div class="meter-info">
          <span><?php echo $pct_bw; ?>%</span>
          <span>tersisa: <b><?php echo format_bytes_local(max(0, ($bandwidth_limit_gb*1024*1024*1024) - $bw_used_bytes)); ?></b></span>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($inode_limit): ?>
      <div class="usage-box">
        <div class="usage-head" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
          <div style="display:flex;align-items:center;gap:8px;font-weight:700;color:#0f172a;">
            <i class="fa-solid fa-cubes"></i> <span>Objek (inode)</span>
          </div>
          <div class="chip" style="font-size:12px;border-radius:999px;background:#f0fdf4;color:#14532d;padding:4px 10px;">
            <?php echo max(0,$inode_used); ?> / <?php echo (int)$inode_limit; ?>
          </div>
        </div>
        <div class="meter">
          <div class="meter-bar meter-3" data-pct="<?php echo $pct_inode; ?>"></div>
        </div>
        <div class="meter-info">
          <span><?php echo $pct_inode; ?>%</span>
          <span>tersisa: <b><?php echo max(0, $inode_limit - max(0,$inode_used)); ?></b></span>
        </div>
      </div>
      <?php endif; ?>

      <div class="tips" style="margin-top:10px;color:#64748b;font-size:12px;">
        <i class="fa fa-circle-info"></i>
        <span>Kuota terpakai oleh <b>file website</b> dan <b>database transaksi</b>. Jika mendekati batas, hapus file yang tidak diperlukan atau upgrade paket hosting.</span>
      </div>
  </div>

  <style>
    #limitHostingPanel .meter{ height:14px; background:#f1f5f9; border-radius:999px; overflow:hidden; border:1px solid #e5e7eb; position:relative; }
    #limitHostingPanel .meter-bar{ height:100%; width:0; background:linear-gradient(90deg,#06b6d4,#3b82f6); background-size: 200% 100%; animation: flow 2.5s linear infinite; box-shadow: inset 0 0 6px rgba(0,0,0,.12); }
    #limitHostingPanel .meter-bar.meter-2{ background:linear-gradient(90deg,#22c55e,#84cc16); }
    #limitHostingPanel .meter-bar.meter-3{ background:linear-gradient(90deg,#f97316,#f43f5e); }
    #limitHostingPanel .meter-info{ display:flex; justify-content:space-between; font-size:12px; color:#0f172a; margin-top:6px; }
    @keyframes flow{ 0%{background-position:0% 50%;} 100%{background-position:200% 50%;} }
  </style>

  <script>
    (function(){
      var bars = document.querySelectorAll('#limitHostingPanel .meter-bar');
      bars.forEach(function(b){ var pct = b.getAttribute('data-pct') || 0; setTimeout(function(){ b.style.width = pct + '%'; }, 50); });
    })();
  </script>
</div>

      </div>
    </div>

    <!-- ======= BASIS DATA ======= -->
    <?php if (_is_admin()): ?>
    <div class="row" style="margin-top:16px;">
      <div class="col-lg-12">
        <div class="card db-card" style="padding:16px;">
          <h4><i class="fa-solid fa-database"></i> Basis Data</h4>
          <div class="db-note" style="margin:6px 0 12px">
            Fitur ini untuk <b>pencadangan</b> (backup) dan <b>pengosongan</b> (clear) data.
            Demi keamanan, saat mengosongkan data Anda harus mengetik <span class="db-badge">KOSONGKAN</span> <b>dan</b> memasukkan PIN.
          </div>

          <!-- === Tambahan informasi backup CSV (eye-catching & elegan) === -->

          <div class="callout callout-info" style="margin:8px 0">
            <b><i class="fa fa-circle-info"></i> Pencadangan CSV</b><br>
            Cadangan tersedia sebagai <b>CSV (.csv.zip)</b> per modul untuk arsip dan portabilitas. Kolom sensitif dimasking otomatis.
            <br><b>Restore mandiri tidak disediakan</b> demi keamanan & konsistensi skema.
            Untuk pemulihan data, silakan <b>hubungi admin/vendor</b>.
          </div>
          <!-- === End tambahan informasi === -->

          <div class="row db-split">
            <div class="col-md-6">
              <div class="section-head"><i class="fa-solid fa-cloud-arrow-down"></i> Pencadangan (Backup) <span class="badge">.csv.zip</span></div>
              <table class="db-table">
                <thead>
                  <tr><th>Basis Data</th><th style="width:140px; text-align:center">Cadangkan</th></tr>
                </thead>
                <tbody>
                  <?php
                    $cats = [
                      'sekolah'     => 'Data Sekolah (Profil, Lisensi, Staff)',
                      'pelanggaran' => 'Data Pelanggaran & SP',
                      'prestasi'    => 'Data Prestasi',
                      'absensi'     => 'Data Absensi/Presensi',
                      'master'      => 'Master Data (Siswa, Jurusan, Kelas, Mapel, TA)',
                      'semua'       => '— Seluruh Tabel (kecuali inti)'
                    ];
                    foreach($cats as $key=>$label):
                  ?>
                  <tr>
                    <td><span class="db-cat"><?php echo esc($label); ?></span>
                      <div class="db-note">
                        <?php $preview = db_category_tables($koneksi,$key); ?>
                        <?php if ($preview): ?>
                          Tabel: <?php echo esc(implode(', ', array_slice($preview,0,5))); ?><?php echo count($preview)>5?'...':''; ?>
                        <?php else: ?>
                          (Tidak terdeteksi / akan kosong)
                        <?php endif; ?>
                      </div>
                    </td>
                    <td style="text-align:center">
                      <a class="btn btn-soft" href="export_csv.php?cat=<?php echo esc($key); ?>">
                        <i class="fa fa-download"></i> Unduh
                      </a>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="col-md-6">
              <div class="section-head"><i class="fa-solid fa-broom"></i> Pengosongan (Clear) <span class="badge">Konfirmasi: KOSONGKAN + PIN</span></div>
              <table class="db-table">
                <thead>
                  <tr><th>Basis Data</th><th style="width:150px; text-align:center">Kosongkan</th></tr>
                </thead>
                <tbody>
                  <?php foreach($cats as $key=>$label): if ($key==='semua' || $key==='sekolah') continue; ?>
                  <tr>
                    <td>
                      <span class="db-cat"><?php echo esc($label); ?></span>
                      <div class="db-note">
                        <?php $preview = db_category_tables($koneksi,$key); ?>
                        <?php if ($preview): ?>
                          Tabel yang akan dikosongkan: <?php echo esc(implode(', ', array_slice($preview,0,5))); ?><?php echo count($preview)>5?'...':''; ?>
                        <?php else: ?>
                          (Tidak ada tabel yang terdeteksi)
                        <?php endif; ?>
                      </div>
                    </td>
                    <td style="text-align:center">
                      <button class="btn btn-danger-soft btn-xs btn-open-clear" type="button" data-toggle="collapse" data-target="#clr_<?php echo esc($key); ?>">
                        <i class="fa fa-trash"></i> Kosongkan
                      </button>
                      <div id="clr_<?php echo esc($key); ?>" class="collapse" style="margin-top:8px;">
                        <form action="" method="post" autocomplete="off" class="form-clear">
                          <input type="hidden" name="aksi" value="db_clear">
                          <input type="hidden" name="cat" value="<?php echo esc($key); ?>">
                          <input type="text" class="form-control" name="confirm" placeholder="Ketik KOSONGKAN untuk konfirmasi">
                          <div class="db-note" style="margin-top:6px">Tindakan ini tidak bisa dibatalkan. Pastikan Anda sudah membackup.</div>
                          <button class="btn btn-danger-soft btn-submit-clear" style="margin-top:6px;" type="submit"><i class="fa fa-exclamation-triangle"></i> Ya, Kosongkan</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <!-- Panel Restore DIHAPUS sesuai permintaan -->
          </div><!-- /db-split -->
        </div>
      </div>
    </div>
    <?php endif; ?>
    <!-- ======= END BASIS DATA ======= -->

  </section>
</div>

<!-- PIN GUARD MODAL -->
<div class="pin-overlay" id="pinOverlay" aria-hidden="true">
  <div class="pin-modal" role="dialog" aria-modal="true" aria-labelledby="pinTitle">
    <div class="pin-header" id="pinTitle"><i class="fa fa-lock"></i> Keamanan · Masukkan PIN 6 Digit</div>
    <div class="pin-body">
      <div class="pin-inputs" id="pinInputs">
        <input type="password" inputmode="numeric" maxlength="1">
        <input type="password" inputmode="numeric" maxlength="1">
        <input type="password" inputmode="numeric" maxlength="1">
        <input type="password" inputmode="numeric" maxlength="1">
        <input type="password" inputmode="numeric" maxlength="1">
        <input type="password" inputmode="numeric" maxlength="1">
      </div>
      <div class="pin-actions">
        <button class="pin-btn" id="btnPinOk"><i class="fa fa-unlock"></i> Buka</button>
        <button class="pin-btn secondary" id="btnPinClear"><i class="fa fa-eraser"></i> Hapus</button>
      </div>
      <div class="pin-note">Halaman pengosongan dikunci. Masukkan PIN untuk melanjutkan.</div>
      <div class="pin-error" id="pinError"></div>
    </div>
  </div>
</div>

<!-- JS kecil untuk tooltip + fokus lisensi + drag order BK + shimmer button + PIN guard -->
<script>
  // Bootstrap tooltip (jika Bootstrap tersedia)
  if (typeof $ !== 'undefined' && $.fn.tooltip) {
    $(function(){ $('[data-toggle="tooltip"]').tooltip(); });
  }

  // Tombol Aktifkan -> fokus License Key + pulse ring
  (function(){
    var btn = document.getElementById('btnActivate');
    if(!btn) return;
    btn.addEventListener('click', function(e){
      var inp = document.getElementById('license_key');
      if (inp) {
        setTimeout(function(){
          inp.focus();
          inp.classList.add('pulse-ring');
          setTimeout(function(){ inp.classList.remove('pulse-ring'); }, 1800);
        }, 120);
      }
    });
  })();

  // Shimmer berjalan saat user mengetik lisensi/kode, berhenti saat submit
  (function(){
    var form = document.getElementById('licenseForm');
    if(!form) return;
    var key = document.getElementById('license_key');
    var code= document.getElementById('activation_code');
    var btn = document.getElementById('btnSaveLicense');

    function markTyping(){ form.classList.add('is-typing'); }
    if(key) key.addEventListener('input', markTyping);
    if(code) code.addEventListener('input', markTyping);
    btn && btn.addEventListener('click', function(){ form.classList.remove('is-typing'); });
  })();

  // BK drag order chips
  (function(){
    var select = document.getElementById('bp_select');
    var box    = document.getElementById('bkSortable');
    var form   = document.getElementById('formProfilSekolah');
    if(!select || !box || !form) return;

    function rebuildChips(){
      box.innerHTML = '';
      var opts = Array.from(select.options).filter(o=>o.selected);
      opts.forEach(function(o){
        var chip = document.createElement('div');
        chip.className = 'bk-chip';
        chip.setAttribute('draggable','true');
        chip.dataset.value = o.value;
        chip.innerHTML = '<span class="bk-name">'+o.text+'</span><span class="bk-remove">&times;</span>';
        box.appendChild(chip);
      });
    }

    let dragEl = null;
    box.addEventListener('dragstart', function(e){
      if(e.target.classList.contains('bk-chip')){
        dragEl = e.target;
        e.dataTransfer.effectAllowed = 'move';
        setTimeout(()=>dragEl.classList.add('dragging'),0);
      }
    });
    box.addEventListener('dragend', function(e){
      if(dragEl){ dragEl.classList.remove('dragging'); dragEl = null; }
    });
    box.addEventListener('dragover', function(e){
      e.preventDefault();
      var after = getAfterElement(box, e.clientX, e.clientY);
      if (after==null) box.appendChild(dragEl);
      else box.insertBefore(dragEl, after);
    });
    function getAfterElement(container, x, y){
      const els = [...container.querySelectorAll('.bk-chip:not(.dragging)')];
      return els.reduce((closest, child)=>{
        const rect = child.getBoundingClientRect();
        const offset = y - rect.top - rect.height/2;
        if(offset < 0 && offset > closest.offset){ return { offset: offset, element: child }; }
        else return closest;
      }, {offset: Number.NEGATIVE_INFINITY}).element;
    }

    box.addEventListener('click', function(e){
      if(e.target.classList.contains('bk-remove')){
        const chip = e.target.closest('.bk-chip');
        const val = chip.dataset.value;
        [...select.options].forEach(o=>{ if(o.value===val) o.selected=false; });
        chip.remove();
        if (typeof $ !== 'undefined' && $(select).hasClass('select2')) $(select).trigger('change');
      }
    });

    if (typeof $ !== 'undefined' && $(select).hasClass('select2')) {
      $(select).on('change', rebuildChips);
    } else {
      select.addEventListener('change', rebuildChips);
    }
    rebuildChips();

    form.addEventListener('submit', function(){
      const order = [...box.querySelectorAll('.bk-chip')].map(c=>c.dataset.value);
      if(order.length){
        const all = [...select.options];
        order.forEach(function(val){
          const opt = all.find(o=>o.value===val);
          if (opt){ opt.selected = true; select.appendChild(opt); }
        });
      }
    });
  })();

  /* ===== PIN GUARD logic ===== */
  (function(){
    const PIN_DEFAULT = '123456';
    const overlay = document.getElementById('pinOverlay');
    const inputsBox = document.getElementById('pinInputs');
    const inputs = inputsBox ? inputsBox.querySelectorAll('input') : [];
    const btnOk = document.getElementById('btnPinOk');
    const btnClear = document.getElementById('btnPinClear');
    const err = document.getElementById('pinError');
    let pendingForm = null; // form yang sedang ingin submit

    function openModal(forForm){
      pendingForm = forForm;
      if(!overlay) return;
      overlay.style.display = 'flex';
      err.textContent = '';
      inputs.forEach(i => { i.value=''; });
      if (inputs[0]) inputs[0].focus();
    }
    function closeModal(){
      if(!overlay) return;
      overlay.style.display = 'none';
      pendingForm = null;
    }

    // intercept semua form clear
    document.querySelectorAll('form.form-clear').forEach(function(f){
      f.addEventListener('submit', function(e){
        e.preventDefault();
        openModal(f);
      });
    });

    // input behavior
    inputs.forEach((inp,idx)=>{
      inp.addEventListener('input', function(){
        this.value = this.value.replace(/\D/g,'').slice(0,1);
        if (this.value && inputs[idx+1]) inputs[idx+1].focus();
      });
      inp.addEventListener('keydown', function(ev){
        if (ev.key==='Backspace' && !this.value && inputs[idx-1]) inputs[idx-1].focus();
      });
    });

    function getPin(){
      return Array.from(inputs).map(i=>i.value).join('');
    }

    btnOk && btnOk.addEventListener('click', function(){
      const pin = getPin();
      if (pin.length<6){
        err.textContent = 'PIN belum lengkap.';
        inputsBox.classList.remove('pin-shake'); void inputsBox.offsetWidth; inputsBox.classList.add('pin-shake');
        return;
      }
      if (pin !== PIN_DEFAULT){
        err.textContent = 'PIN salah.';
        inputsBox.classList.remove('pin-shake'); void inputsBox.offsetWidth; inputsBox.classList.add('pin-shake');
        return;
      }
      // inject pin ke form dan submit
      if (pendingForm){
        let h = pendingForm.querySelector('input[name="pin6"]');
        if(!h){
          h = document.createElement('input');
          h.type='hidden'; h.name='pin6';
          pendingForm.appendChild(h);
        }
        h.value = pin;
        closeModal();
        pendingForm.submit();
      }
    });

    btnClear && btnClear.addEventListener('click', function(){
      inputs.forEach(i=>i.value='');
      if (inputs[0]) inputs[0].focus();
      err.textContent='';
    });

    // tutup modal kalau klik area gelap
    overlay && overlay.addEventListener('click', function(e){
      if (e.target === overlay) closeModal();
    });
  })();
</script>


<script>
  (function(){
    var bar = document.querySelector('#limitHostingPanel .meter-bar');
    if(!bar) return;

    var pct = parseFloat(bar.getAttribute('data-pct')) || 0;
    // Clamp
    pct = Math.max(0, Math.min(100, pct));

    // Level warna otomatis
    var level = (pct >= 85) ? 'is-crit' : (pct >= 60) ? 'is-warn' : 'is-ok';
    bar.classList.add(level);

    // Matikan shimmer bila 0 atau 100
    if (pct === 0 || pct === 100) bar.classList.add('no-anim');

    // Trigger animasi pengisian (maju)
    requestAnimationFrame(function(){
      bar.style.width = pct + '%';
    });
  })();
</script>

<?php include __DIR__ . '/footer.php'; ?>

<?php
/* -----------------------------------------------------------------------
   SNIPPET: Tooltip & Multi-select per stack (tidak dieksekusi, hanya referensi)
   ----------------------------------------------------------------------- */
?>