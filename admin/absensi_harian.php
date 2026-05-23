<?php
// ===== ACTION HANDLER (sebelum output) =====
include '../koneksi.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Bootstrap RBAC
$__auth = __DIR__ . '/../includes/auth.php';
if (is_file($__auth)) {
  require_once $__auth;
  if (function_exists('ensure_logged_in')) ensure_logged_in();
  // Hanya role berikut yang boleh akses Absensi Harian:
  if (function_exists('user_has_any_role')) {
    if (!user_has_any_role(['administrator','superadmin','tas','sekretaris','piket','guru','admin'])) {
      header("location:../admin.php?alert=belum_login"); exit;
    }
  } else {
    // Fallback sangat minimal (jaga kompatibilitas lama)
    if (!isset($_SESSION['id'])) { header("location:../admin.php?alert=belum_login"); exit; }
  }
} else {
  // Fallback kalau auth.php tidak ada
  if (!isset($_SESSION['id'])) { header("location:../admin.php?alert=belum_login"); exit; }
}

$user_id = (int)($_SESSION['id'] ?? 0);

function _post($k,$d=''){ return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }
function _int($v){ return (int)$v; }

/* ==== Helper Tanggal
   - normalize_tanggal_input: terima 'dd/mm/yyyy' atau 'yyyy-mm-dd' → kembalikan 'Y-m-d'
   - format_tanggal_dmy: terima 'Y-m-d' → tampil 'dd/mm/yyyy'
==== */
function normalize_tanggal_input($s){
  $s = trim($s);
  if($s==='') return '';
  // dd/mm/yyyy
  if(preg_match('~^(\d{1,2})/(\d{1,2})/(\d{4})$~',$s,$m)){
    $d = str_pad($m[1],2,'0',STR_PAD_LEFT);
    $mo= str_pad($m[2],2,'0',STR_PAD_LEFT);
    $y = $m[3];
    if(checkdate((int)$mo,(int)$d,(int)$y)) return "$y-$mo-$d";
  }
  // yyyy-mm-dd
  if(preg_match('~^(\d{4})-(\d{2})-(\d{2})$~',$s,$m)){
    if(checkdate((int)$m[2],(int)$m[3],(int)$m[1])) return $s;
  }
  // d-m-Y (fallback)
  if(preg_match('~^(\d{1,2})-(\d{1,2})-(\d{4})$~',$s,$m)){
    $d = str_pad($m[1],2,'0',STR_PAD_LEFT);
    $mo= str_pad($m[2],2,'0',STR_PAD_LEFT);
    $y = $m[3];
    if(checkdate((int)$mo,(int)$d,(int)$y)) return "$y-$mo-$d";
  }
  return '';
}
function format_tanggal_dmy($ymd){
  if(!$ymd) return $ymd;
  if(preg_match('~^(\d{4})-(\d{2})-(\d{2})$~',$ymd,$m)){
    return $m[3].'/'.$m[2].'/'.$m[1];
  }
  return $ymd;
}

/* ========= AUDIT LOG (baru) ========= */
if (!function_exists('audit_log_init')) {
  function audit_log_init($koneksi){
    @mysqli_query($koneksi,"CREATE TABLE IF NOT EXISTS `audit_log`(
      `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `sekolah_id` INT UNSIGNED DEFAULT 0,
      `user_id` INT UNSIGNED DEFAULT 0,
      `action` VARCHAR(32) NOT NULL,
      `entity` VARCHAR(64) NOT NULL,
      `entity_id` BIGINT UNSIGNED DEFAULT 0,
      `meta` TEXT NULL,
      `ip` VARCHAR(45) DEFAULT NULL,
      `user_agent` VARCHAR(255) DEFAULT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY `idx_entity` (`entity`,`entity_id`),
      KEY `idx_action` (`action`),
      KEY `idx_user` (`user_id`),
      KEY `idx_sekolah` (`sekolah_id`),
      KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }
}
if (!function_exists('audit_log')) {
  function audit_log($koneksi,$user_id,$action,$entity,$entity_id,$meta_arr=[]){
    $sekolah_id = isset($GLOBALS['SEKOLAH_ID']) ? (int)$GLOBALS['SEKOLAH_ID'] : 0;
    $uid = (int)$user_id;
    $eid = (int)$entity_id;
    $act = mysqli_real_escape_string($koneksi,(string)$action);
    $ent = mysqli_real_escape_string($koneksi,(string)$entity);
    $ip  = mysqli_real_escape_string($koneksi, $_SERVER['REMOTE_ADDR'] ?? '');
    $ua  = mysqli_real_escape_string($koneksi, substr($_SERVER['HTTP_USER_AGENT'] ?? '',0,255));
    $meta_json = mysqli_real_escape_string($koneksi, json_encode($meta_arr, JSON_UNESCAPED_UNICODE));
    @mysqli_query($koneksi,"INSERT INTO audit_log(sekolah_id,user_id,action,entity,entity_id,meta,ip,user_agent,created_at)
                            VALUES($sekolah_id,$uid,'$act','$ent',$eid,'$meta_json','$ip','$ua',NOW())");
  }
}
audit_log_init($koneksi);
/* ======= /AUDIT LOG ======= */

// Ambil TA aktif
$taq = mysqli_query($koneksi,"SELECT ta_id FROM ta WHERE ta_status=1 LIMIT 1");
$ta_aktif = 0; if($taq && $r=mysqli_fetch_assoc($taq)) $ta_aktif=(int)$r['ta_id'];

// Routing aksi
$action = isset($_POST['action'])?$_POST['action']:(isset($_GET['action'])?$_GET['action']:'');

// Buat lembar harian (unik: ta_id+tgl+kelas)
if($action==='buat'){
  $tanggal_input = _post('tanggal');
  $tanggal = normalize_tanggal_input($tanggal_input); // <-- TERIMA dd/mm/yyyy, simpan Y-m-d
  $kelas_id= _int(_post('kelas_id'));
  if(!$ta_aktif || !$tanggal || !$kelas_id){
    header("location: absensi_harian.php?alert=invalid"); exit;
  }

  // Cek ada?
  $cek = mysqli_query($koneksi, "SELECT harian_id FROM absensi_harian
                                 WHERE ta_id=$ta_aktif AND tanggal='".mysqli_real_escape_string($koneksi,$tanggal)."'
                                   AND kelas_id=$kelas_id LIMIT 1");
  if($cek && $row=mysqli_fetch_assoc($cek)){
    $hid = (int)$row['harian_id'];
  }else{
    $ok = mysqli_query($koneksi, "INSERT INTO absensi_harian(ta_id,tanggal,kelas_id,petugas_id,status,created_at)
                                  VALUES($ta_aktif,'$tanggal',$kelas_id,$user_id,'draft',NOW())");
    if(!$ok){ header("location: absensi_harian.php?alert=buat_fail"); exit; }
    $hid = (int)mysqli_insert_id($koneksi);

    // Prefill detail: ambil siswa kelas ini
    $sis = mysqli_query($koneksi, "SELECT ks.ks_siswa AS siswa_id FROM kelas_siswa ks WHERE ks.ks_kelas=$kelas_id");
    while($s = mysqli_fetch_assoc($sis)){
      $sid = (int)$s['siswa_id'];
      if($sid>0){
        mysqli_query($koneksi, "INSERT IGNORE INTO absensi_harian_detail(harian_id,siswa_id,status,updated_by)
                                VALUES($hid,$sid,'H',$user_id)");
      }
    }
  }
  audit_log($koneksi,$user_id,'create','absensi_harian',$hid,['tanggal'=>$tanggal,'kelas_id'=>$kelas_id]);
  header("location: absensi_harian.php?harian_id=".$hid."&alert=buat_ok"); exit;
}

// Simpan kehadiran (manual submit)
if($action==='simpan'){
  $hid = _int(_post('harian_id'));
  if($hid<=0){ header("location: absensi_harian.php?alert=invalid"); exit; }

  $status = isset($_POST['st'])?$_POST['st']:[];
  $ket    = isset($_POST['ket'])?$_POST['ket']:[];
  $n=0;
  foreach($status as $sid=>$st){
    $sid = (int)$sid;
    $st  = strtoupper(substr(trim($st),0,1));
    if(!in_array($st,['H','S','I','A'])) $st='H';
    $keterangan = isset($ket[$sid]) ? mysqli_real_escape_string($koneksi, substr($ket[$sid],0,255)) : 'NULL';
    $keterangan = $keterangan==="NULL" ? "NULL" : "'$keterangan'";
    $ok = mysqli_query($koneksi, "INSERT INTO absensi_harian_detail(harian_id,siswa_id,status,keterangan,updated_by,updated_at)
                            VALUES($hid,$sid,'$st',$keterangan,$user_id,NOW())
                            ON DUPLICATE KEY UPDATE
                              status=VALUES(status),
                              keterangan=VALUES(keterangan),
                              updated_by=$user_id,
                              updated_at=NOW()");
    if($ok) $n++;
  }
  // LOG edit (simpan manual)
  audit_log($koneksi,$user_id,'edit','absensi_harian',$hid,['saved'=>$n,'mode'=>'manual']);
  header("location: absensi_harian.php?harian_id=".$hid."&alert=simpan_ok"); exit;
}

// AUTOSAVE (AJAX)
if($action==='autosave'){
  header('Content-Type: application/json; charset=utf-8');
  $hid = _int(_post('harian_id'));
  if($hid<=0){ echo json_encode(['ok'=>false,'msg'=>'invalid']); exit; }
  $status = isset($_POST['st'])?$_POST['st']:[];
  $ket    = isset($_POST['ket'])?$_POST['ket']:[];
  $n=0;
  foreach($status as $sid=>$st){
    $sid = (int)$sid;
    $st  = strtoupper(substr(trim($st),0,1));
    if(!in_array($st,['H','S','I','A'])) $st='H';
    $keterangan = isset($ket[$sid]) ? mysqli_real_escape_string($koneksi, substr($ket[$sid],0,255)) : 'NULL';
    $keterangan = $keterangan==="NULL" ? "NULL" : "'$keterangan'";
    $ok = mysqli_query($koneksi, "INSERT INTO absensi_harian_detail(harian_id,siswa_id,status,keterangan,updated_by,updated_at)
                                  VALUES($hid,$sid,'$st',$keterangan,$user_id,NOW())
                                  ON DUPLICATE KEY UPDATE
                                    status=VALUES(status),
                                    keterangan=VALUES(keterangan),
                                    updated_by=$user_id,
                                    updated_at=NOW()");
    if($ok) $n++;
  }
  echo json_encode(['ok'=>true,'saved'=>$n,'time'=>date('H:i:s')]); exit;
}

if (function_exists('usage_log_db_snapshot')) usage_log_db_snapshot($koneksi, $SEKOLAH_ID);


// Finalisasi (lock) — TANPA PIN
if($action==='final'){
  $hid = isset($_GET['harian_id'])?(int)$_GET['harian_id']:_int(_post('harian_id'));
  if($hid>0){
    mysqli_query($koneksi, "UPDATE absensi_harian SET status='final',updated_at=NOW() WHERE harian_id=$hid");
    audit_log($koneksi,$user_id,'final','absensi_harian',$hid);
    header("location: absensi_harian.php?harian_id=".$hid."&alert=final_ok"); exit;
  }
  header("location: absensi_harian.php?alert=invalid"); exit;
}

// ==== Unlock/Edit (sekarang TANPA PIN) → ubah status ke draft ====
if($action==='unlock'){
  $hid = isset($_GET['harian_id'])?(int)$_GET['harian_id']:_int(_post('harian_id'));
  if($hid>0){
    mysqli_query($koneksi, "UPDATE absensi_harian SET status='draft',updated_at=NOW() WHERE harian_id=$hid");
    audit_log($koneksi,$user_id,'unlock','absensi_harian',$hid);
    header("location: absensi_harian.php?harian_id=".$hid."&alert=unlock_ok"); exit;
  }
  header("location: absensi_harian.php?alert=invalid"); exit;
}

// Hapus (sekarang TANPA PIN; tetap dilog)
if($action==='hapus'){
  $hid = isset($_GET['harian_id'])?(int)$_GET['harian_id']:_int(_post('harian_id'));
  if($hid>0){
    // Log sebelum dihapus
    audit_log($koneksi,$user_id,'delete','absensi_harian',$hid);
    mysqli_query($koneksi,"DELETE FROM absensi_harian_detail WHERE harian_id=$hid");
    mysqli_query($koneksi,"DELETE FROM absensi_harian WHERE harian_id=$hid");
    header("location: absensi_harian.php?alert=hapus_ok"); exit;
  }
  header("location: absensi_harian.php?alert=invalid"); exit;
}

include 'header.php';
?>

<style>
  /* ====== Tabel & kolom sticky ====== */
  .table-responsive{ position:relative; }
  #tbl-isi{ table-layout:auto; }
  #tbl-isi th, #tbl-isi td{ vertical-align:middle; }

  /* sticky kolom No + Nama */
  #tbl-isi .col-no{
    position:sticky; left:0; z-index:3; background:#fff;
    width:42px; min-width:42px; max-width:42px;
  }
  #tbl-isi .col-name{
    position:sticky; left:42px; z-index:2; background:#fff;
    white-space:normal; word-break:break-word; line-height:1.25;
    min-width:160px;
  }

  /* ====== Status Chip ====== */
  .chip-col{ min-width:300px; }
  .chip-wrap{
    display:flex; gap:6px; align-items:center;
    /* default wrap (desktop) */
    flex-wrap:wrap;
    overflow-x:visible; -webkit-overflow-scrolling:touch; padding-bottom:2px;
    justify-content:flex-start;
  }
  .btn-status{
    border:1px solid #cfcfcf; background:#fff; color:#555;
    padding:6px 12px; border-radius:9999px; font-weight:700; line-height:1.1;
    transition:all .15s ease; white-space:nowrap; font-size:13px;
  }
  .btn-status:hover{ box-shadow:0 1px 3px rgba(0,0,0,.12); }
  .btn-status.active{ color:#fff; border-color:transparent; }
  .btn-h.active{ background:#2ecc71; }
  .btn-s.active{ background:#f1c40f; color:#111;}
  .btn-i.active{ background:#3498db; }
  .btn-a.active{ background:#e74c3c; }

  .note-btn{ border-radius:9999px; padding:4px 8px; }
  .note-btn.has-note{ background:#eef7ff; color:#0a58ca; border-color:#bcdcff; }

  /* legend kecil di header box — tidak dipakai lagi */
  .legend-chip{ display:inline-block; padding:3px 8px; border-radius:9999px; font-size:12px; color:#fff; margin-right:6px; }
  .lg-h{ background:#2ecc71; } .lg-s{ background:#f1c40f; color:#111; } .lg-i{ background:#3498db; } .lg-a{ background:#e74c3c; }

  /* badge ringkasan (di halaman isi) */
  .sum-pill{ display:inline-block; border-radius:9999px; padding:6px 10px; margin:0 6px 6px 0; color:#fff; font-weight:600; font-size:12px; }
  .sum-h{ background:#2ecc71; } .sum-s{ background:#f1c40f; color:#111; }
  .sum-i{ background:#3498db; } .sum-a{ background:#e74c3c; }
  .sum-extra{ font-size:12px; margin-left:6px; font-weight:600; }

  /* hover baris (umum) → soft biru/tosca */
  .table-hover.row-hover tbody tr:hover td,
  .table-hover.row-hover tbody tr:hover th{ background:#f0fbff !important; }

  /* Sorot hasil cari ala Absensi Mapel (orange + badge kiri) */
  tr.find-focus td, tr.find-focus th{ background:#FFF4E6 !important; }
  tr.find-focus td:first-child{ box-shadow: inset 3px 0 0 #f59e0b; }
  @keyframes pulseRow{ 0%{ box-shadow:0 0 0 0 rgba(245,158,11,.35);} 100%{ box-shadow:0 0 0 6px rgba(245,158,11,0);} }
  tr.find-focus{ animation:pulseRow 1.2s ease-out 1; }

  /* Focus visual pada chip-wrap ketika hasil pencarian dipilih (POIN #2) */
  .chip-wrap.chip-focus{ box-shadow:0 0 0 2px #fde68a inset; background:#FFF8E1; border-radius:10px; }

  /* progress list / catatan kecil */
  .prog-note{ font-size:12px; color:#555; margin-top:3px; }

  /* ====== TOMBOL AKSI DI DAFTAR ====== */
  .btn-isi{
    background:#1b84e7; border-color:#177ddc; color:#fff;
    border-radius:8px; padding:6px 12px; font-weight:600; line-height:1; transition:all .15s ease;
  }
  .btn-isi i{ margin-right:6px; }
  .btn-isi:hover{ background:#1670c8; border-color:#155fa9; box-shadow:0 4px 10px rgba(23,125,220,.25); }
  .btn-isi:focus{ outline:0; box-shadow:0 0 0 3px rgba(27,132,231,.25); }
  .btn-isi:active{ transform:translateY(1px); }

  .btn-isi-ghost{
    background:#eff7ff; color:#1670c8; border:1px solid #cfe6ff;
    border-radius:8px; padding:6px 12px; font-weight:600; line-height:1; transition:all .15s ease;
  }
  .btn-isi-ghost i{ margin-right:6px; }
  .btn-isi-ghost:hover{ background:#dff0ff; box-shadow:0 2px 6px rgba(23,125,220,.2); }

  .btn-view{ border-radius:8px; padding:6px 12px; font-weight:600; line-height:1; }
  .btn-view i{ margin-right:6px; }

  @media (max-width:768px){
    .btn-isi .lbl, .btn-isi-ghost .lbl, .btn-view .lbl{ display:none; }
    .btn-isi, .btn-isi-ghost, .btn-view{ padding:6px 8px; }
  }

  /* Filter kelas bar */
  .filter-bar{ margin-bottom:10px; }

  /* ====== Tombol "Buat Absensi" gradient hijau + shimmer ====== */
  .btn-absensi{
    position:relative;
    border:0;
    color:#fff !important;
    border-radius:12px;
    padding:8px 14px;
    background:linear-gradient(135deg,#22c55e,#16a34a,#059669);
    background-size:200% 200%;
    animation:gradShift 3s ease infinite;
    box-shadow:0 8px 18px rgba(5,150,105,.35);
    overflow:hidden;
  }
  .btn-absensi:hover{ filter:brightness(1.05); box-shadow:0 10px 22px rgba(5,150,105,.45); }
  .btn-absensi:active{ transform:translateY(1px); }
  .btn-absensi:focus{ outline:0; box-shadow:0 0 0 3px rgba(34,197,94,.25); }

  .btn-absensi::after{
    content:"";
    position:absolute; top:0; left:-150%;
    width:50%; height:100%;
    background:linear-gradient(120deg, rgba(255,255,255,0) 0%, rgba(255,255,255,.35) 50%, rgba(255,255,255,0) 100%);
    transform:skewX(-20deg);
    animation:shimmer 2.8s infinite;
    pointer-events:none;
  }
  @keyframes shimmer{ 0%{left:-150%;} 100%{left:150%;} }
  @keyframes gradShift{ 0%{background-position:0% 50%;} 50%{background-position:100% 50%;} 100%{background-position:0% 50%;} }

  /* ====== Kalender: Sabtu & Minggu merah ====== */
  .datepicker-days thead .dow:nth-child(1),
  .datepicker-days thead .dow:nth-child(7){ color:#e74c3c; }
  .datepicker table tr td.weekend,
  .datepicker table tr td.weekend:hover{ color:#e74c3c !important; }

  /* ====== Badge status kecil ala Absensi Mapel ====== */
  .badge-status{ display:inline-block; font-size:11px; padding:3px 8px; border-radius:9999px; font-weight:700; line-height:1; vertical-align:middle; }
  .badge-final{ background:#22c55e; color:#fff; }

  /* >>> Revisi: DRAFT lebih tajam (oranye), teks hitam, blink di teks saja <<< */
  .badge-draft{ background:#f59e0b; color:#111; }
  .badge-draft .blink{ display:inline-block; animation:blinkText 1.2s linear infinite; }
  @keyframes blinkText{ 50%{ opacity:.25; } }

  /* (opsi) perbaiki z-index dropdown select2 bila perlu */
  .select2-container--open .select2-dropdown{ z-index: 2050 !important; }

  /* ==================== RESPONSIVE TWEAKS (POIN 1) ==================== */
  @media (max-width:768px){
    /* Tabel fixed agar kolom proporsional & chip muat 1 baris */
    #tbl-isi{ table-layout:fixed; }
    #tbl-isi .col-no{ width:34px; min-width:34px; max-width:34px; }
    /* Perkecil area Nama Siswa supaya chip muat 1 baris. Tetap wrap. */
    #tbl-isi thead th.col-name{ width:42%; }
    #tbl-isi thead th.chip-col{ width:58%; }
    #tbl-isi .col-name{ left:34px; min-width:0; max-width:42vw; width:42vw; font-size:13px; line-height:1.2; }
    .chip-col{ min-width:0; width:auto; }
    #tbl-isi th, #tbl-isi td{ padding:8px 6px; }

    /* Chip lebih kecil & satu baris */
    .chip-wrap{ flex-wrap:nowrap; gap:4px; overflow-x:auto; }
    .btn-status{ padding:4px 9px; font-size:12px; }
    .note-btn{ padding:3px 6px; }
  }
  @media (max-width:480px){
    .btn-status{ padding:3px 8px; font-size:11px; }
    #tbl-isi .col-name{ font-size:12.5px; }
    #tbl-isi th, #tbl-isi td{ padding:7px 5px; }
    .chip-wrap{ gap:3px; }
  }
</style>

<div class="content-wrapper">

  <section class="content-header">
    <h1>Absensi Harian <small>per tanggal & kelas (TA aktif)</small></h1>
    <ol class="breadcrumb">
      <li><a href="index.php"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Absensi Harian</li>
    </ol>
  </section>

  <section class="content">

<?php
  $alert = isset($_GET['alert'])?$_GET['alert']:'';
  $map = [
    'buat_ok'=>['success','Lembar absensi dibuat.'],
    'buat_fail'=>['danger','Gagal membuat lembar.'],
    'simpan_ok'=>['success','Kehadiran tersimpan.'],
    'final_ok'=>['success','Lembar difinalisasi.'],
    'hapus_ok'=>['success','Lembar dihapus.'],
    'unlock_ok'=>['success','Lembar dibuka kunci (menjadi draft) untuk diedit.'],
    'invalid'=>['warning','Data tidak lengkap.'],
  ];
  if(isset($map[$alert])){
    echo '<div class="alert alert-'.$map[$alert][0].' alert-dismissible">\n                <button type="button" class="close" data-dismiss="alert">×</button>'.$map[$alert][1].'\n              </div>';
  }
?>

<?php
  // Jika ada harian_id → tampilkan form isi
  $harian_id = isset($_GET['harian_id'])?(int)$_GET['harian_id']:0;
  if($harian_id>0):
    $hdr = mysqli_query($koneksi,"SELECT h.*,k.kelas_nama,u.user_nama
                                  FROM absensi_harian h
                                  JOIN kelas k ON k.kelas_id=h.kelas_id
                                  JOIN user  u ON u.user_id=h.petugas_id
                                  WHERE h.harian_id=$harian_id");
    $h = $hdr?mysqli_fetch_assoc($hdr):null;
    if(!$h){ echo '<div class="alert alert-warning">Data tidak ditemukan.</div>'; }
    else {

      // Hitung ringkasan awal (server side)
      $sum = ['H'=>0,'S'=>0,'I'=>0,'A'=>0,'TOTAL'=>0];
      $qsum = mysqli_query($koneksi,"SELECT status, COUNT(*) c FROM absensi_harian_detail WHERE harian_id=$harian_id GROUP BY status");
      while($rr = mysqli_fetch_assoc($qsum)){
        $st = strtoupper($rr['status']);
        if(isset($sum[$st])) $sum[$st] += (int)$rr['c'];
        $sum['TOTAL'] += (int)$rr['c'];
      }
      $tidak_hadir = $sum['S'] + $sum['I'] + $sum['A'];

      // === Daftar siswa utk pencarian
      $qsis = mysqli_query($koneksi,"SELECT d.siswa_id, s.siswa_nama
                                     FROM absensi_harian_detail d
                                     JOIN siswa s ON s.siswa_id=d.siswa_id
                                     WHERE d.harian_id=$harian_id
                                     ORDER BY s.siswa_nama ASC");
?>
<div class="box">
  <div class="box-header">
    <h3 class="box-title">
      Tanggal: <b><?=htmlspecialchars(format_tanggal_dmy($h['tanggal']))?></b> •
      Kelas: <b><?=htmlspecialchars(trim($h['kelas_nama']))?></b> •
      Status:
      <?php if($h['status']==='final'){ ?>
        <span class="badge-status badge-final">FINAL</span>
      <?php } else { ?>
        <span class="badge-status badge-draft"><span class="blink">DRAFT</span></span>
      <?php } ?>
    </h3>

    <div class="pull-right">
      <a href="absensi_harian.php" class="btn btn-default btn-sm" style="margin-right:8px;">
        <i class="fa fa-arrow-left"></i> Kembali
      </a>

      <!-- Pencarian siswa -->
      <select id="findSiswa" class="find-sel" style="width:220px;">
        <option value="">Cari siswa…</option>
        <?php if($qsis){ while($s = mysqli_fetch_assoc($qsis)){ ?>
          <option value="<?=$s['siswa_id']?>"><?=htmlspecialchars($s['siswa_nama'])?></option>
        <?php } } ?>
      </select>
    </div>
    <div class="clearfix"></div>
  </div>

  <div class="box-body">
    <!-- Ringkasan cepat -->
    <div class="clearfix" style="margin-bottom:8px;">
      <div class="pull-left">
        <span class="sum-pill sum-h">H: <b id="sumH"><?=$sum['H']?></b></span>
        <span class="sum-pill sum-s">S: <b id="sumS"><?=$sum['S']?></b></span>
        <span class="sum-pill sum-i">I: <b id="sumI"><?=$sum['I']?></b></span>
        <span class="sum-pill sum-a">A: <b id="sumA"><?=$sum['A']?></b></span>
        <span class="sum-extra">Total: <b id="sumT"><?=$sum['TOTAL']?></b></span>
        <span class="sum-extra">Tidak hadir: <b id="sumNA"><?=$tidak_hadir?></b></span>
      </div>
    </div>

    <form method="post" action="absensi_harian.php" autocomplete="off" id="formAbsensi">
      <input type="hidden" name="action" value="simpan">
      <input type="hidden" name="harian_id" value="<?=$harian_id?>">

      <?php if($h['status']!=='final'){ ?>
      <div class="clearfix" style="margin-bottom:8px;">
        <div class="pull-left" id="autosaveStatus" style="padding-top:6px;color:#888;font-size:12px;">
          Draft belum tersimpan
        </div>
        <div class="pull-right">
          <button type="button" id="btn-set-hadir" class="btn btn-default btn-xs">
            <i class="fa fa-check-circle"></i> Set Semua Hadir
          </button>
        </div>
      </div>
      <?php } ?>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover row-hover" id="tbl-isi">
          <thead>
            <tr>
              <th class="col-no">No</th>
              <th class="col-name">Nama Siswa</th>
              <th class="chip-col">Status & Catatan</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $q = mysqli_query($koneksi,"SELECT d.siswa_id, s.siswa_nama, COALESCE(d.status,'H') AS st, d.keterangan
                                          FROM absensi_harian_detail d
                                          JOIN siswa s ON s.siswa_id=d.siswa_id
                                          WHERE d.harian_id=$harian_id
                                          ORDER BY s.siswa_nama ASC");
              $no=1;
              while($r=mysqli_fetch_assoc($q)){
                $sid=(int)$r['siswa_id'];
                $st = in_array($r['st'],['H','S','I','A']) ? $r['st'] : 'H';
                $hasNote = trim($r['keterangan'])!=='' ? 'has-note' : '';
                $noteTitle = $hasNote ? htmlspecialchars($r['keterangan']) : 'Tambah catatan';
            ?>
            <tr id="row-sid-<?=$sid?>">
              <td class="col-no"><?=$no++?></td>
              <td class="col-name"><?=htmlspecialchars($r['siswa_nama'])?></td>
              <td class="chip-col">
                <div class="chip-wrap" data-sid="<?=$sid?>" <?= $h['status']=='final'?'data-final="1"':'';?>>
                  <button type="button" class="btn btn-status btn-h<?=$st=='H'?' active':''?>" data-val="H" tabindex="0">H</button>
                  <button type="button" class="btn btn-status btn-s<?=$st=='S'?' active':''?>" data-val="S" tabindex="0">S</button>
                  <button type="button" class="btn btn-status btn-i<?=$st=='I'?' active':''?>" data-val="I" tabindex="0">I</button>
                  <button type="button" class="btn btn-status btn-a<?=$st=='A'?' active':''?>" data-val="A" tabindex="0">A</button>

                  <!-- Tombol catatan mungil -->
                  <button type="button" class="btn btn-default btn-xs note-btn <?=$hasNote?>"
                          title="<?=$noteTitle?>" data-toggle="tooltip"
                          data-sid="<?=$sid?>" data-name="<?=htmlspecialchars($r['siswa_nama'])?>"
                          data-note="<?=htmlspecialchars($r['keterangan'])?>">
                    <i class="fa fa-sticky-note"></i>
                  </button>

                  <!-- hidden inputs -->
                  <input type="hidden" name="st[<?=$sid?>]"  value="<?=$st?>" class="st-input">
                  <input type="hidden" name="ket[<?=$sid?>]" value="<?=htmlspecialchars($r['keterangan'])?>" class="ket-input">
                </div>
              </td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>

      <?php if($h['status']!=='final'){ ?>
      <div class="row" style="margin-top:8px;">
        <div class="col-xs-4">
          <a href="absensi_harian.php" class="btn btn-default btn-sm">
            <i class="fa fa-arrow-left"></i> <span class="lbl">Kembali</span>
          </a>
        </div>
        <div class="col-xs-4 text-center">
          <a href="absensi_harian.php?action=final&harian_id=<?=$harian_id?>" class="btn btn-primary btn-sm">
            <i class="fa fa-lock"></i> <span class="lbl">Finalisasi</span>
          </a>
        </div>
        <div class="col-xs-4 text-right">
          <button type="submit" class="btn btn-success btn-sm"><i class="fa fa-save"></i> <span class="lbl">Simpan</span></button>
        </div>
      </div>
      <?php } else { ?>
      <div class="text-right">
        <!-- Edit (unlock) TANPA PIN -->
        <a href="absensi_harian.php?action=unlock&harian_id=<?=$harian_id?>"
           class="btn btn-warning btn-sm" title="Edit (buka kunci)">
          <i class="fa fa-pencil"></i> <span class="lbl">Edit</span>
        </a>
        <!-- Hapus TANPA PIN -->
        <a href="absensi_harian.php?action=hapus&harian_id=<?=$harian_id?>"
           class="btn btn-danger btn-sm" title="Hapus" onclick="return confirm('Data yang terhubung akan ikut dihapus. Yakin?');">
          <i class="fa fa-trash"></i>
        </a>
        <a href="absensi_harian.php" class="btn btn-default btn-sm"><i class="fa fa-list"></i> <span class="lbl">Daftar</span></a>
      </div>
      <?php } ?>
    </form>
  </div>
</div>
<?php } // end else h ?>
<?php else: // ==== LIST & MODAL BUAT ==== ?>

<div class="box">
  <div class="box-header">
    <h3 class="box-title">Daftar Absensi Harian</h3>
    <div class="btn-group pull-right">
      <button type="button" class="btn btn-success btn-sm btn-absensi" data-toggle="modal" data-target="#modal_buat">
        <i class="fa fa-plus"></i> <span class="lbl">Buat Absensi</span>
      </button>
    </div>
  </div>

  <div class="box-body">

    <!-- ===== Filter Kelas (client-side untuk DataTables) ===== -->
    <div class="row filter-bar">
      <div class="col-sm-6">
        <label for="filterKelas" class="control-label" style="margin-right:6px;">Filter Kelas:</label>
        <select id="filterKelas" class="form-control" style="max-width:320px; display:inline-block;">
          <option value="">Semua Kelas</option>
          <?php
            $klsQ = mysqli_query($koneksi,"SELECT kelas_id, kelas_nama FROM kelas ORDER BY kelas_nama");
            while($kl = mysqli_fetch_assoc($klsQ)){
              echo '<option value="'.htmlspecialchars(trim($kl['kelas_nama'])).'">'.htmlspecialchars(trim($kl['kelas_nama'])).'</option>';
            }
          ?>
        </select>
      </div>
    </div>
    <!-- ===== /Filter Kelas ===== -->

    <div class="table-responsive">
      <table class="table table-bordered table-striped table-hover row-hover" id="table-datatable">
        <thead>
          <tr>
            <th width="1%">No</th>
            <th>Tanggal</th>
            <th>Kelas</th>
            <th>Petugas</th>
            <th>Status</th>
            <th>Hadir (%)</th>
            <th>Final oleh</th>
            <th>Waktu final</th>
            <th width="20%">Opsi</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $sql="SELECT
                    h.harian_id,h.tanggal,h.status,h.updated_at,
                    k.kelas_nama,
                    u.user_nama,
                    SUM(CASE WHEN d.status='H' THEN 1 ELSE 0 END) AS h_cnt,
                    COUNT(d.siswa_id) AS total
                  FROM absensi_harian h
                  JOIN kelas k ON k.kelas_id=h.kelas_id
                  JOIN user  u ON u.user_id=h.petugas_id
                  LEFT JOIN absensi_harian_detail d ON d.harian_id=h.harian_id
                  WHERE h.ta_id=$ta_aktif
                  GROUP BY h.harian_id,h.tanggal,h.status,h.updated_at,k.kelas_nama,u.user_nama
                  ORDER BY h.tanggal DESC, k.kelas_nama ASC";
            $res=mysqli_query($koneksi,$sql);
            $no=1;
            while($r=mysqli_fetch_assoc($res)){
              $total = (int)$r['total'];
              $hC = (int)$r['h_cnt'];
              $pHad = $total>0 ? round($hC*100/$total) : 0;
              $isFinal = ($r['status']==='final');
              $finalBy = $isFinal ? $r['user_nama'] : '-';
              $finalAt = $isFinal ? ($r['updated_at'] ? htmlspecialchars($r['updated_at']) : '-') : '-';
          ?>
          <tr>
            <td><?=$no++?></td>
            <!-- Tampilkan dd/mm/yyyy, order tetap pakai Y-m-d -->
            <td data-order="<?=htmlspecialchars($r['tanggal'])?>"><?=htmlspecialchars(format_tanggal_dmy($r['tanggal']))?></td>
            <td><?=htmlspecialchars(trim($r['kelas_nama']))?></td>
            <td><?=htmlspecialchars($r['user_nama'])?></td>
            <td>
              <?php if($isFinal){ ?>
                <span class="badge-status badge-final">FINAL</span>
              <?php } else { ?>
                <span class="badge-status badge-draft"><span class="blink">DRAFT</span></span>
              <?php } ?>
            </td>
            <td><?= $pHad ?>%</td>
            <td><?= htmlspecialchars($finalBy) ?></td>
            <td><?= $finalAt ?></td>
            <td>
              <?php if(!$isFinal){ ?>
                <a href="absensi_harian.php?harian_id=<?=$r['harian_id']?>"
                   class="btn btn-isi-ghost btn-sm"
                   aria-label="Isi absensi" data-toggle="tooltip" title="Isi absensi">
                  <i class="fa fa-pencil"></i><span class="lbl"> Isi</span>
                </a>
                <a href="absensi_harian.php?action=final&harian_id=<?=$r['harian_id']?>"
                   class="btn btn-info btn-sm" aria-label="Finalisasi" data-toggle="tooltip" title="Finalisasi">
                  <i class="fa fa-lock"></i>
                </a>
              <?php } else { ?>
                <a href="absensi_harian.php?harian_id=<?=$r['harian_id']?>"
                   class="btn btn-default btn-view btn-sm"
                   aria-label="Lihat (final)" data-toggle="tooltip" title="Lihat (final)">
                  <i class="fa fa-eye"></i><span class="lbl"> Lihat</span>
                </a>
                <!-- Edit (unlock) TANPA PIN dari daftar -->
                <a href="absensi_harian.php?action=unlock&harian_id=<?=$r['harian_id']?>"
                   class="btn btn-warning btn-sm" aria-label="Edit" data-toggle="tooltip" title="Edit (buka kunci)">
                  <i class="fa fa-pencil"></i><span class="lbl"> Edit</span>
                </a>
              <?php } ?>
              <!-- Hapus TANPA PIN -->
              <a href="absensi_harian.php?action=hapus&harian_id=<?=$r['harian_id']?>"
                 class="btn btn-danger btn-sm" aria-label="Hapus" data-toggle="tooltip" title="Hapus"
                 onclick="return confirm('Data yang terhubung akan ikut dihapus. Yakin?');">
                <i class="fa fa-trash"></i>
              </a>
            </td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Buat -->
<div class="modal fade" id="modal_buat" tabindex="-1">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="post" action="absensi_harian.php" autocomplete="off">
        <input type="hidden" name="action" value="buat">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title">Buat Lembar Absensi Harian</h4>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Tanggal</label>
            <!-- REVISI: tampil dd/mm/yyyy + datepicker -->
            <input type="text" name="tanggal" id="tanggal_buat" class="form-control" required value="<?=date('d/m/Y')?>"
                   placeholder="dd/mm/yyyy" autocomplete="off">
          </div>
          <div class="form-group">
            <label>Kelas</label>
            <select name="kelas_id" id="kelas_id" class="form-control sel2" required>
              <option value="">- Pilih Kelas -</option>
              <?php
                $kq = mysqli_query($koneksi,"SELECT kelas_id,kelas_nama FROM kelas WHERE kelas_ta=$ta_aktif ORDER BY kelas_nama");
                while($k=mysqli_fetch_assoc($kq)){
                  echo '<option value="'.(int)$k['kelas_id'].'">'.htmlspecialchars(trim($k['kelas_nama'])).'</option>';
                }
              ?>
            </select>
          </div>
          <p class="text-muted">Siswa akan otomatis dimuat dari <em>kelas_siswa</em> untuk kelas terpilih.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary btn-sm">Buat</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php endif; // end list vs form ?>

  </section>
</div>

<!-- Modal Catatan -->

<div class="modal fade" id="ketModal" tabindex="-1" role="dialog" aria-labelledby="ketLabel">
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title" id="ketLabel">Catatan</h4>
      </div>
      <div class="modal-body">
        <p id="ketNama" class="text-muted" style="margin-top:-5px;"></p>
        <input type="text" id="ketInput" class="form-control" maxlength="255" placeholder="Tambahkan catatan (opsional)">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary btn-sm" id="ketSimpan">Simpan</button>
      </div>
    </div>
  </div>
</div>

<script>
$(function(){
  // DataTables (halaman daftar)
  if ($.fn.dataTable && $.fn.dataTable.ext) { $.fn.dataTable.ext.errMode = 'console'; }
  if ($('#table-datatable').length && $.fn.DataTable) {
    var t = $('#table-datatable').DataTable({
      responsive:true,
      autoWidth:false,
      pageLength: 10,
      lengthChange: true,
      order:[[1,'desc'],[2,'asc']],
      // Kolom: 0 No, 1 Tgl, 2 Kelas, 3 Petugas, 4 Status, 5 Hadir%, 6 Final oleh, 7 Waktu final, 8 Opsi
      columnDefs:[
        {targets:[0,8],orderable:false}
      ],
      language:{paginate:{previous:"‹",next:"›"}}
    });
    // re-number kolom No
    t.on('order.dt search.dt', function(){
      let i=1; t.column(0,{search:'applied',order:'applied'}).nodes().each(function(c){c.innerHTML=i++;});
    }).draw();

    // Filter Kelas (kolom index 2)
    $('#filterKelas').on('change', function(){
      var val = $(this).val() || '';
      t.column(2).search(val, true, false).draw();
    });
  }

  $('.sel2').select2({width:'100%',dropdownParent:$('#modal_buat')});
  $('[data-toggle="tooltip"]').tooltip({container:'body'});

  /* ====== Datepicker untuk input tanggal (dd/mm/yyyy) ======
     Catatan: weekend (0=Min,6=Sab) di-highlight & angka merah via CSS di atas.
  */
  if ($.fn.datepicker) {
    $('#tanggal_buat').datepicker({
      format:'dd/mm/yyyy',
      autoclose:true,
      todayHighlight:true,
      daysOfWeekHighlighted:[0,6],
      orientation:'bottom auto',
      clearBtn:true,
      todayBtn:'linked'
    });
  } else {
    // Fallback ringan: paksa pola dd/mm/yyyy
    $('#tanggal_buat').attr('pattern','\\d{2}/\\d{2}/\\d{4}');
  }

  // ====== Form isi (chip + catatan + autosave + summary) ======
  var dirty = false;
  function markDirty(){ dirty = true; }

  function recalcSummary(){
    var h=0,s=0,i=0,a=0,t=0;
    $('.st-input').each(function(){
      var v = ($(this).val()||'').toUpperCase();
      if(v==='H') h++; else if(v==='S') s++; else if(v==='I') i++; else if(v==='A') a++;
      t++;
    });
    $('#sumH').text(h); $('#sumS').text(s); $('#sumI').text(i); $('#sumA').text(a); $('#sumT').text(t);
    $('#sumNA').text(s+i+a);
  }

  // Simpan fokus baris yang sedang dicari (POIN #2: highlight bertahan sampai status dipilih)
  var currentFocusSid = null;
  function clearFocusRow(sid){
    if(!sid) return;
    var $row = $('#row-sid-'+sid);
    $row.removeClass('find-focus');
    $row.find('.chip-wrap').removeClass('chip-focus');
  }

  // Klik chip status
  $(document).on('click','.chip-wrap .btn-status',function(){
    var $btn = $(this), $wrap = $btn.closest('.chip-wrap');
    if($wrap.data('final')=='1') return;
    $wrap.find('.btn-status').removeClass('active');
    $btn.addClass('active');
    $wrap.find('input.st-input').val($btn.data('val'));
    // Jika baris ini sedang fokus hasil cari → bersihkan highlight setelah memilih status
    var sid = $wrap.data('sid');
    if(currentFocusSid && sid==currentFocusSid){ clearFocusRow(currentFocusSid); currentFocusSid = null; }
    markDirty(); recalcSummary();
  });

  // Set semua hadir
  $('#btn-set-hadir').on('click', function(){
    $('.chip-wrap').each(function(){
      var $w=$(this); if($w.data('final')=='1') return;
      $w.find('.btn-status').removeClass('active');
      $w.find('.btn-h').addClass('active');
      $w.find('input.st-input').val('H');
    });
    if(currentFocusSid){ clearFocusRow(currentFocusSid); currentFocusSid=null; }
    markDirty(); recalcSummary();
  });

  // ====== Modal catatan ======
  var noteSid = null;
  $(document).on('click','.note-btn', function(){
    var $b = $(this);
    noteSid = $b.data('sid');
    $('#ketLabel').text('Catatan');
    $('#ketNama').text($b.data('name'));
    $('#ketInput').val($b.data('note') || '');
    $('#ketModal').modal('show');
    setTimeout(function(){ $('#ketInput').focus(); }, 250);
  });

  $('#ketSimpan').on('click', function(){
    if(noteSid==null) return;
    var v = $('#ketInput').val().trim();
    var $wrap = $('.chip-wrap[data-sid="'+noteSid+'"]');
    $wrap.find('input.ket-input').val(v);
    var $btn  = $wrap.find('.note-btn');
    $btn.toggleClass('has-note', v!=='');
    $btn.attr('title', v!=='' ? v : 'Tambah catatan').tooltip('fixTitle');
    $('#ketModal').modal('hide');
    noteSid = null;
    markDirty();
  });

  // ====== AUTOSAVE draft ======
  var autosaveMs = 12000; // ~12 detik
  var autosaveOn = $('#formAbsensi').length>0 && $('a[href*="action=final"]').length>0; // halaman isi & belum final
  function doAutosave(){
    if(!autosaveOn || !dirty) return;
    var $f = $('#formAbsensi');
    var data = $f.serializeArray();
    data.push({name:'action', value:'autosave'});
    $.ajax({
      url:'absensi_harian.php',
      type:'POST',
      data:data,
      dataType:'json'
    }).done(function(res){
      if(res && res.ok){
        dirty = false;
        $('#autosaveStatus').text('Draft tersimpan ' + (res.time || new Date().toLocaleTimeString()));
      }
    });
  }
  setInterval(doAutosave, autosaveMs);

  // Init ringkasan dari DOM
  if ($('#formAbsensi').length) recalcSummary();

  // ====== QUICK FIND (POIN #2): highlight TETAP sampai status dipilih ======
  function scrollToSid(sid){
    if(!sid) return;
    var $row = $('#row-sid-'+sid);
    if(!$row.length){ $row = $('.chip-wrap[data-sid="'+sid+'"]').closest('tr'); }
    if(!$row.length) return;

    var $wrapTbl = $('#tbl-isi').closest('.table-responsive');
    var top = $row.position().top + $wrapTbl.scrollTop() - 40; // offset 40px untuk ruang judul
    $wrapTbl.animate({scrollTop: top}, 300);

    // Bersihkan fokus sebelumnya (jika ada)
    if(currentFocusSid && currentFocusSid!=sid){ clearFocusRow(currentFocusSid); }

    // aktifkan highlight & fokus chip
    $row.addClass('find-focus');
    var $chip = $row.find('.chip-wrap');
    $chip.addClass('chip-focus');
    var $btnActive = $chip.find('.btn-status.active');
    if(!$btnActive.length) $btnActive = $chip.find('.btn-status').first();
    setTimeout(function(){
      try{ $chip.animate({scrollLeft: Math.max(0, ($btnActive.position() ? $btnActive.position().left : 0) + $chip.scrollLeft() - 20)}, 200); }catch(e){}
      $btnActive.focus();
    }, 120);

    // simpan sebagai fokus aktif — tidak ada timer; akan dihapus setelah pilih status
    currentFocusSid = sid;
  }

  if ($.fn.select2 && $('#findSiswa').length){
    $('#findSiswa').select2({
      placeholder:'Cari siswa…',
      allowClear:true,
      width:'resolve'
    }).on('change', function(){
      var sid = $(this).val();
      scrollToSid(sid);
    });
  }
});
</script>

<?php include 'footer.php'; ?>
