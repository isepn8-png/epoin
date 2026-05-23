<?php
// ===== ABSENSI PER MAPEL / SESI (RBAC + LAPORAN + POLISH UI + FILTER STATUS + FINAL ALL ADMIN) =====
include '../koneksi.php';
if (session_status() === PHP_SESSION_NONE) session_start();

/* ==== RBAC bootstrap (aman + fallback) ==== */
$user_id = 0;
$__auth = __DIR__ . '/../includes/auth.php';
if (is_file($__auth)) {
  require_once $__auth;
  if (function_exists('ensure_logged_in')) ensure_logged_in();
  if (function_exists('current_user_id')) { $user_id = (int)current_user_id(); }
}
if (empty($user_id)) {
  if(!isset($_SESSION['id'])){ header("location:../admin.php?alert=belum_login"); exit; }
  $user_id = (int)$_SESSION['id'];
}

/* ---------- Helpers (guard supaya tak bentrok) ---------- */
if (!function_exists('_post')){ function _post($k,$d=''){ return isset($_POST[$k]) ? trim($_POST[$k]) : $d; } }
if (!function_exists('_get')){  function _get($k,$d=''){ return isset($_GET[$k])  ? trim($_GET[$k])  : $d; } }
if (!function_exists('_int')){  function _int($v){ return (int)$v; } }
if (!function_exists('esc')){   function esc($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('dbesc')){  function dbesc($s){ global $koneksi; return mysqli_real_escape_string($koneksi, $s); } }

/* ---- RBAC helpers (pakai guard supaya tidak bentrok dgn file lain) ---- */
if (!function_exists('load_user_roles')){
  function load_user_roles($uid){
    global $koneksi;
    $roles = [];
    $q = mysqli_query($koneksi,"SELECT r.role_key FROM user_roles ur JOIN roles r ON r.role_id=ur.role_id WHERE ur.user_id=".(int)$uid);
    if($q){ while($row=mysqli_fetch_assoc($q)){ $roles[]=$row['role_key']; } }
    if(isset($_SESSION['level']) && $_SESSION['level']==='administrator' && !in_array('administrator',$roles,true)){ $roles[]='administrator'; }
    return $roles;
  }
}
if (!function_exists('load_user_permissions')){ function load_user_permissions($uid){ return []; } }
if (!function_exists('user_has_role')){
  function user_has_role($r){ $roles = isset($_SESSION['roles']) ? $_SESSION['roles'] : []; return in_array($r,$roles,true); }
}
if (!function_exists('user_has_any_role')){ function user_has_any_role($a){ foreach($a as $r){ if(user_has_role($r)) return true; } return false; } }

if (empty($_SESSION['roles'])) { $_SESSION['roles'] = load_user_roles($user_id); }
if (empty($_SESSION['perms'])) { $_SESSION['perms'] = load_user_permissions($user_id); }

function _is_admin(){ return user_has_any_role(['administrator','superadmin']); }
function _is_guru(){  return user_has_role('guru'); }

// Halaman ini khusus admin & guru
if (!(_is_admin() || _is_guru())) { http_response_code(403); exit('Akses ditolak.'); }

/* ---------- Ambil TA aktif ---------- */
$taq = mysqli_query($koneksi,"SELECT ta_id FROM ta WHERE ta_status=1 LIMIT 1");
$ta_aktif = 0; if($taq && $r=mysqli_fetch_assoc($taq)) $ta_aktif=(int)$r['ta_id'];

/* ---- RBAC ringan (kompatibilitas lama) ---- */
$__roles = array_fill_keys(isset($_SESSION['roles'])?$_SESSION['roles']:[], true);
$is_admin = (isset($__roles['administrator'])||isset($__roles['superadmin']));

/* Guru punya penugasan di TA aktif? */
$has_pengampu = false;
if($ta_aktif){
  $qhp = mysqli_query($koneksi,"SELECT 1 FROM pengampu_mapel WHERE ta_id=$ta_aktif AND guru_user_id=$user_id LIMIT 1");
  $has_pengampu = ($qhp && mysqli_fetch_row($qhp));
}

/* ---------- Routing aksi ---------- */
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

/* ====== BUAT SESI (unik: ta_id+tgl+kelas+mapel+jam_ke) ====== */
if($action==='buat'){
  if(!$ta_aktif){ header("location: absensi_mapel.php?alert=invalid"); exit; }

  $tanggal = _post('tanggal');
  $jam_ke  = max(1, _int(_post('jam_ke')));
  // value dari select "km" = "kelas_id|mapel_id|guru_user_id"
  $kmv = _post('km');
  $parts = explode('|', $kmv);
  $kelas_id = isset($parts[0]) ? (int)$parts[0] : 0;
  $mapel_id = isset($parts[1]) ? (int)$parts[1] : 0;
  $guru_id  = isset($parts[2]) ? (int)$parts[2] : 0;

  if(!$tanggal || !$kelas_id || !$mapel_id){
    header("location: absensi_mapel.php?alert=invalid"); exit;
  }

  // Validasi pengampu untuk non-admin
  if(!$is_admin){
    $cekpm = mysqli_query($koneksi,"SELECT 1 FROM pengampu_mapel 
      WHERE ta_id=$ta_aktif AND kelas_id=$kelas_id AND mapel_id=$mapel_id AND guru_user_id=$user_id LIMIT 1");
    if(!$cekpm || !mysqli_fetch_row($cekpm)){
      header("location: absensi_mapel.php?alert=forbidden"); exit;
    }
    $guru_id = $user_id;
  }else{
    if($guru_id<=0) $guru_id = $user_id;
  }

  // Cek sudah ada?
  $cek = mysqli_query($koneksi, "SELECT sesi_id FROM absensi_sesi 
           WHERE ta_id=$ta_aktif AND tanggal='".dbesc($tanggal)."'
             AND kelas_id=$kelas_id AND mapel_id=$mapel_id AND jam_ke=$jam_ke LIMIT 1");
  if($cek && $row=mysqli_fetch_assoc($cek)){
    $sid = (int)$row['sesi_id'];
  }else{
    $ok = mysqli_query($koneksi,"INSERT INTO absensi_sesi(ta_id,tanggal,kelas_id,mapel_id,jam_ke,guru_user_id,status,created_at)
                                 VALUES($ta_aktif,'$tanggal',$kelas_id,$mapel_id,$jam_ke,$guru_id,'draft',NOW())");
    if(!$ok){ header("location: absensi_mapel.php?alert=buat_fail"); exit; }
    $sid = (int)mysqli_insert_id($koneksi);

    // Prefill detail dari kelas_siswa
    $sis = mysqli_query($koneksi,"SELECT ks.ks_siswa AS siswa_id FROM kelas_siswa ks WHERE ks.ks_kelas=$kelas_id");
    if($sis){
      while($s = mysqli_fetch_assoc($sis)){
        $sid_siswa = (int)$s['siswa_id'];
        if($sid_siswa>0){
          mysqli_query($koneksi,"INSERT IGNORE INTO absensi_sesi_detail(sesi_id,siswa_id,status,updated_by) 
                                 VALUES($sid,$sid_siswa,'H',$user_id)");
        }
      }
    }
  }
  header("location: absensi_mapel.php?sesi_id=".$sid."&alert=buat_ok"); exit;
}

/* ====== SIMPAN MANUAL ====== */
if($action==='simpan'){
  $sid = _int(_post('sesi_id'));
  if($sid<=0){ header("location: absensi_mapel.php?alert=invalid"); exit; }

  $hdr = mysqli_query($koneksi,"SELECT guru_user_id,status FROM absensi_sesi WHERE sesi_id=$sid");
  $h = $hdr?mysqli_fetch_assoc($hdr):null;
  if(!$h){ header("location: absensi_mapel.php?alert=invalid"); exit; }
  if($h['status']==='final'){ header("location: absensi_mapel.php?sesi_id=$sid&alert=final_locked"); exit; }
  if(!$is_admin && (int)$h['guru_user_id']!==$user_id){ header("location: absensi_mapel.php?alert=forbidden"); exit; }

  $status = isset($_POST['st']) ? $_POST['st'] : [];
  $ket    = isset($_POST['ket']) ? $_POST['ket'] : [];
  foreach($status as $siswa_id=>$st){
    $siswa_id = (int)$siswa_id;
    $st = strtoupper(substr(trim($st),0,1));
    if(!in_array($st,['H','S','I','A'])) $st='H';
    $keterangan = isset($ket[$siswa_id]) ? dbesc(substr($ket[$siswa_id],0,255)) : 'NULL';
    $keterangan = ($keterangan==="NULL") ? "NULL" : "'$keterangan'";
    mysqli_query($koneksi,"INSERT INTO absensi_sesi_detail(sesi_id,siswa_id,status,keterangan,updated_by,updated_at)
                           VALUES($sid,$siswa_id,'$st',$keterangan,$user_id,NOW())
                           ON DUPLICATE KEY UPDATE 
                             status=VALUES(status),
                             keterangan=VALUES(keterangan),
                             updated_by=$user_id,
                             updated_at=NOW()");
  }
  header("location: absensi_mapel.php?sesi_id=".$sid."&alert=simpan_ok"); exit;
}

/* ====== AUTOSAVE (AJAX) ====== */
if($action==='autosave'){
  header('Content-Type: application/json; charset=utf-8');
  $sid = _int(_post('sesi_id'));
  if($sid<=0){ echo json_encode(['ok'=>false,'msg'=>'invalid']); exit; }

  $hdr = mysqli_query($koneksi,"SELECT guru_user_id,status FROM absensi_sesi WHERE sesi_id=$sid");
  $h = $hdr?mysqli_fetch_assoc($hdr):null;
  if(!$h || $h['status']==='final' || (!$is_admin && (int)$h['guru_user_id']!==$user_id)){
    echo json_encode(['ok'=>false,'msg'=>'forbidden']); exit;
  }

  $status = isset($_POST['st']) ? $_POST['st'] : [];
  $ket    = isset($_POST['ket']) ? $_POST['ket'] : [];
  $n=0;
  foreach($status as $siswa_id=>$st){
    $siswa_id = (int)$siswa_id;
    $st = strtoupper(substr(trim($st),0,1));
    if(!in_array($st,['H','S','I','A'])) $st='H';
    $keterangan = isset($ket[$siswa_id]) ? dbesc(substr($ket[$siswa_id],0,255)) : 'NULL';
    $keterangan = ($keterangan==="NULL") ? "NULL" : "'$keterangan'";
    $ok = mysqli_query($koneksi,"INSERT INTO absensi_sesi_detail(sesi_id,siswa_id,status,keterangan,updated_by,updated_at)
                                 VALUES($sid,$siswa_id,'$st',$keterangan,$user_id,NOW())
                                 ON DUPLICATE KEY UPDATE 
                                   status=VALUES(status),
                                   keterangan=VALUES(keterangan),
                                   updated_by=$user_id,
                                   updated_at=NOW()");
    if($ok) $n++;
  }
  echo json_encode(['ok'=>true,'saved'=>$n,'time'=>date('H:i:s')]); exit;
}

/* ====== FINAL (single) ====== */
if($action==='final'){
  $sid = isset($_GET['sesi_id'])?(int)$_GET['sesi_id']:_int(_post('sesi_id'));
  if($sid>0){
    $hdr = mysqli_query($koneksi,"SELECT guru_user_id FROM absensi_sesi WHERE sesi_id=$sid");
    $h = $hdr?mysqli_fetch_assoc($hdr):null;
    if($h && ($is_admin || (int)$h['guru_user_id']===$user_id)){
      mysqli_query($koneksi,"UPDATE absensi_sesi SET status='final',updated_at=NOW() WHERE sesi_id=$sid");
      header("location: absensi_mapel.php?sesi_id=".$sid."&alert=final_ok"); exit;
    }
    header("location: absensi_mapel.php?alert=forbidden"); exit;
  }
  header("location: absensi_mapel.php?alert=invalid"); exit;
}

/* ====== UNFINAL (Edit lagi): buka kunci sesi final ====== */
if($action==='unfinal'){
  $sid = isset($_GET['sesi_id'])?(int)$_GET['sesi_id']:_int(_post('sesi_id'));
  if($sid>0){
    $hdr = mysqli_query($koneksi,"SELECT guru_user_id FROM absensi_sesi WHERE sesi_id=$sid");
    $h = $hdr?mysqli_fetch_assoc($hdr):null;
    if($h && ($is_admin || (int)$h['guru_user_id']===$user_id)){
      mysqli_query($koneksi,"UPDATE absensi_sesi SET status='draft',updated_at=NOW() WHERE sesi_id=$sid");
      header("location: absensi_mapel.php?sesi_id=".$sid); exit;
    }
    header("location: absensi_mapel.php?alert=forbidden"); exit;
  }
  header("location: absensi_mapel.php?alert=invalid"); exit;
}

/* ====== FINAL ALL (ADMIN): finalkan semua sesi DRAFT di TA aktif ====== */
if($action==='final_all'){
  if(!$is_admin){ header("location: absensi_mapel.php?alert=forbidden"); exit; }
  if($ta_aktif<=0){ header("location: absensi_mapel.php?alert=invalid"); exit; }
  mysqli_query($koneksi,"UPDATE absensi_sesi SET status='final', updated_at=NOW() WHERE ta_id=$ta_aktif AND status='draft'");
  $n = mysqli_affected_rows($koneksi);
  header("location: absensi_mapel.php?alert=final_all_ok&n=".$n."&sf=final"); exit;
}

/* ====== HAPUS ====== */
if($action==='hapus'){
  $sid = isset($_GET['sesi_id'])?(int)$_GET['sesi_id']:_int(_post('sesi_id'));
  if($sid>0){
    $hdr = mysqli_query($koneksi,"SELECT guru_user_id FROM absensi_sesi WHERE sesi_id=$sid");
    $h = $hdr?mysqli_fetch_assoc($hdr):null;
    if($h && ($is_admin || (int)$h['guru_user_id']===$user_id)){
      mysqli_query($koneksi,"DELETE FROM absensi_sesi_detail WHERE sesi_id=$sid");
      mysqli_query($koneksi,"DELETE FROM absensi_sesi WHERE sesi_id=$sid");
      header("location: absensi_mapel.php?alert=hapus_ok"); exit;
    }
    header("location: absensi_mapel.php?alert=forbidden"); exit;
  }
  header("location: absensi_mapel.php?alert=invalid"); exit;
}

/* ====== EXPORT CSV (laporan) ====== */
if($action==='export_csv'){
  $kind   = _get('kind','rekap'); // default diubah ke rekap
  $mapel  = _int(_get('mapel_id'));
  $kelas  = _int(_get('kelas_id'));
  $guru   = _int(_get('guru_id'));
  $d1     = _get('d1');
  $d2     = _get('d2');
  if(!$is_admin){ $guru = $user_id; }

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=laporan_absensi_mapel_'.$kind.'_'.date('Ymd_His').'.csv');
  $out = fopen('php://output','w');

  if ($kind==='rekap'){
    $where = ["s.ta_id=$ta_aktif","s.status='final'"];
    if($d1) $where[] = "s.tanggal>='".dbesc($d1)."'";
    if($d2) $where[] = "s.tanggal<='".dbesc($d2)."'";
    if($mapel>0) $where[] = "s.mapel_id=$mapel";
    if($kelas>0) $where[] = "s.kelas_id=$kelas";
    if($guru>0) $where[] = "s.guru_user_id=$guru";
    $sql = "
      SELECT si.siswa_nama,
             SUM(d.status='H') AS H, SUM(d.status='S') AS S,
             SUM(d.status='I') AS I, SUM(d.status='A') AS A,
             COUNT(*) AS total
      FROM absensi_sesi s
      JOIN absensi_sesi_detail d ON d.sesi_id=s.sesi_id
      JOIN siswa si ON si.siswa_id=d.siswa_id
      WHERE ".implode(' AND ', $where)."
      GROUP BY d.siswa_id
      ORDER BY si.siswa_nama ASC";
    fputcsv($out, ['Nama Siswa','H','S','I','A','Total']);
    if($q=mysqli_query($koneksi,$sql)){
      while($r=mysqli_fetch_assoc($q)){
        fputcsv($out, [$r['siswa_nama'],$r['H'],$r['S'],$r['I'],$r['A'],$r['total']]);
      }
    }
  } else {
    $where = ["s.ta_id=$ta_aktif","s.status='final'"];
    if($d1) $where[] = "s.tanggal>='".dbesc($d1)."'";
    if($d2) $where[] = "s.tanggal<='".dbesc($d2)."'";
    if($mapel>0) $where[] = "s.mapel_id=$mapel";
    if($kelas>0) $where[] = "s.kelas_id=$kelas";
    if($guru>0) $where[] = "s.guru_user_id=$guru";
    $sql = "
      SELECT s.tanggal,k.kelas_nama,m.mapel_nama,s.jam_ke,u.user_nama,
             SUM(d.status='H') AS H, SUM(d.status='S') AS S,
             SUM(d.status='I') AS I, SUM(d.status='A') AS A,
             COUNT(*) AS total
      FROM absensi_sesi s
      JOIN absensi_sesi_detail d ON d.sesi_id=s.sesi_id
      JOIN kelas k ON k.kelas_id=s.kelas_id
      JOIN mapel m ON m.mapel_id=s.mapel_id
      JOIN user  u ON u.user_id=s.guru_user_id
      WHERE ".implode(' AND ', $where)."
      GROUP BY s.sesi_id
      ORDER BY s.tanggal DESC,k.kelas_nama ASC,s.jam_ke ASC";
    fputcsv($out, ['Tanggal','Kelas','Mapel','Jam ke','Guru','H','S','I','A','Total']);
    if($q=mysqli_query($koneksi,$sql)){
      while($r=mysqli_fetch_assoc($q)){
        fputcsv($out, [$r['tanggal'],$r['kelas_nama'],$r['mapel_nama'],(int)$r['jam_ke'],$r['user_nama'],$r['H'],$r['S'],$r['I'],$r['A'],$r['total']]);
      }
    }
  }
  fclose($out); exit;
}

/* ====== tampilan ====== */
include 'header.php';
?>
<style>
/* ==== Nav Tabs polished (GLOBAL ASAL) ==== */
.nav-tabs { border-bottom: 0; }
.nav-tabs > li > a{ border:0; margin-right:6px; border-radius:10px; padding:10px 14px; background:#f3f4f6; color:#334155; transition:all .15s ease; font-weight:600; }
.nav-tabs > li > a:hover{ background:#e8f1ff; color:#0b5ed7; }
.nav-tabs > li.active > a, .nav-tabs > li.active > a:focus, .nav-tabs > li.active > a:hover{ background:#0b5ed7 !important; color:#fff !important; font-weight:700; box-shadow:0 8px 18px rgba(11,94,215,.25); }

/* ==== Tombol modern + shimmer (tetap, utk buat sesi) ==== */
.btn-shimmer{ background: linear-gradient(90deg,#22c55e,#16a34a,#22c55e); background-size: 200% 100%; animation: shimmer 2.2s ease-in-out infinite; color:#fff !important; border:0; border-radius:12px; font-weight:700; letter-spacing:.2px; padding:10px 14px; box-shadow:0 8px 20px rgba(22,163,74,.25); }
.btn-shimmer .badge-live{ background:#fff; color:#16a34a; margin-left:8px; border-radius:9999px; padding:2px 8px; font-weight:800; }
.btn-shimmer:hover{ transform: translateY(-1px); box-shadow:0 10px 22px rgba(22,163,74,.32); }
@keyframes shimmer{ 0%{background-position:0 0} 50%{background-position:100% 0} 100%{background-position:0 0} }

/* ==== Label status (RESPONSIVE + BLINK untuk DRAFT) ==== */
.label-status{display:inline-flex;align-items:center;border-radius:999px;padding:4px 10px;font-weight:700;line-height:1;font-size:12px}
.label-status.final{background:#10b981;color:#fff}
.label-status.draft{background:#f59e0b;color:#111;animation:softBlink 2.2s ease-in-out infinite}
@keyframes softBlink{0%{opacity:1}50%{opacity:.72}100%{opacity:1}}
@media (prefers-reduced-motion:reduce){.label-status.draft{animation:none}}
@media (max-width:768px){.label-status{font-size:11px;padding:3px 8px}}

/* ==== Badge ringkasan ==== */
.sum-pill{display:inline-block;border-radius:9999px;padding:6px 10px;margin:0 6px 6px 0;color:#fff;font-weight:600;font-size:12px}
.sum-h{background:#2ecc71}.sum-s{background:#f1c40f;color:#111}.sum-i{background:#3498db}.sum-a{background:#e74c3c}

/* Summary bar (Total & Tidak Hadir lebih responsif) */
.summary-bar{display:flex;flex-wrap:wrap;align-items:center;gap:6px}
.summary-bar .sum-pills{display:inline-flex;flex-wrap:wrap;gap:0}
.summary-bar .sum-total,.summary-bar .sum-na{font-size:12px;color:#6b7280}
.summary-bar .sum-total{margin-left:6px}
.summary-bar .sum-na{margin-left:8px}
@media(max-width:768px){.summary-bar .sum-total{font-size:12px}.summary-bar .sum-na{flex-basis:100%;margin-left:0;margin-top:2px}}

/* ==== Table hovers ==== */
.table-hover.row-hover tbody tr:hover td, .table-hover.row-hover tbody tr:hover th{background:#fbfbff!important}

/* ==== Buttons umum ==== */
.btn-view{background:#f3f4f6;color:#475467;border:1px solid #e5e7eb;border-radius:8px;padding:6px 10px;font-weight:600;line-height:1;margin-right:6px}
.btn-view:hover{background:#e9eaee}
.btn-edit-draft{background:#f39c12;color:#fff;border:1px solid #d7890a;border-radius:8px;padding:6px 10px;font-weight:600;line-height:1;margin-right:6px}
.btn-edit-draft:hover{background:#e08e0b;color:#fff}
.btn-fin{margin-right:6px}
@media (max-width:1200px){.btn-view .lbl,.btn-edit-draft .lbl{display:none}.btn-view,.btn-edit-draft{padding:6px 8px}}

/* ==== Sticky kolom saat isi sesi ==== */
.table-responsive{position:relative}
#tbl-isi th,#tbl-isi td{vertical-align:middle}
#tbl-isi .col-no{position:sticky;left:0;z-index:3;background:#fff;width:42px}
#tbl-isi .col-name{position:sticky;left:42px;z-index:2;background:#fff;min-width:130px;max-width:180px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;line-height:1.25}
.chip-col{min-width:280px}
.chip-wrap{display:flex;gap:6px;align-items:center;overflow-x:auto;-webkit-overflow-scrolling:touch;padding-bottom:2px}
.btn-status{border:1px solid #cfcfcf;background:#fff;color:#555;padding:6px 12px;border-radius:9999px;font-weight:700;line-height:1.1;transition:all .15s}
.btn-status.active{color:#fff;border-color:transparent}
.btn-h.active{background:#2ecc71}.btn-s.active{background:#f1c40f;color:#111}.btn-i.active{background:#3498db}.btn-a.active{background:#e74c3c}
.note-btn{border-radius:9999px;padding:4px 8px}.note-btn.has-note{background:#eef7ff;color:#0a58ca;border-color:#bcdcff}

@media (max-width:768px){ #tbl-isi .col-name{min-width:120px;max-width:120px} .chip-col{min-width:0} .btn-status{padding:6px 10px} }
@media (max-width:420px){ #tbl-isi .col-name{min-width:110px;max-width:110px} .btn-status{padding:6px 8px} }

/* Sorot baris hasil pencarian */
tr.find-focus td, tr.find-focus th{background:#FFF4E6 !important}
tr.find-focus td:first-child{box-shadow:inset 3px 0 0 #f59e0b}
@keyframes pulseRow{0%{box-shadow:0 0 0 0 rgba(245,158,11,.35)}100%{box-shadow:0 0 0 6px rgba(245,158,11,0)}}
tr.find-focus{animation:pulseRow 1.2s ease-out 1}

/* ==== Datatable (LIST) ==== */
#table-datatable{table-layout:auto}
#table-datatable th,#table-datatable td{vertical-align:middle}
#table-datatable th.fit,#table-datatable td.fit{white-space:nowrap;width:1%}
#table-datatable th.opsi-col,#table-datatable td.opsi-col{white-space:nowrap;width:1%}

/* ==== Toolbar laporan ==== */
.toolbar-laporan{ display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; }
.toolbar-laporan .form-group{ display:flex; flex-direction:column; min-width:180px; margin-bottom:0; }
.toolbar-laporan .form-group > label{ font-size:12px; font-weight:600; color:#334155; margin-bottom:4px; }
.toolbar-laporan .form-control, .toolbar-laporan .select2-container .select2-selection--single{height:36px}
.toolbar-laporan .select2-selection__rendered{line-height:34px}
.toolbar-laporan .select2-selection__arrow{height:34px}
.toolbar-actions{ margin-left:auto; display:flex; gap:10px; align-items:center; }

/* ==== (TOMBOL TERAPKAN DIPERKECIL & SEJAJAR DENGAN JENIS) ==== */
.btn-apply{
  background:#0b5ed7;
  color:#fff;
  border:0;
  border-radius:10px;              /* serasi dengan seg-item */
  font-weight:800;
  padding:8px 12px;                /* sama rasa dgn seg-item */
  height:36px;                     /* sejajar tinggi kontrol */
  display:inline-flex;
  align-items:center;
  gap:6px;
  font-size:13px;                  /* samakan typografi */
  box-shadow:0 6px 14px rgba(11,94,215,.18);
  line-height:1;
  letter-spacing:.2px;
}
.btn-apply:hover{ transform:translateY(-1px); box-shadow:0 8px 16px rgba(11,94,215,.24); }
/* Animasi aktif saat filter berubah (tetap) */
.btn-apply.is-dirty{
  background:linear-gradient(90deg,#0b5ed7,#2563eb,#0b5ed7);
  background-size:200% 100%;
  animation:shimmer 1.2s linear infinite, applyPulse 1.6s ease-in-out infinite;
}

.btn-print{ background:#475569; color:#fff; border:0; border-radius:10px; font-weight:700; padding:8px 12px; }
.btn-print:hover{ background:#334155; }
.btn-export{ background:#16a34a; color:#fff; border:0; border-radius:10px; font-weight:700; padding:8px 12px; box-shadow:0 6px 14px rgba(22,163,74,.25); }
.btn-export:hover{ background:#15803d; }

/* ==== Perbaiki Select2 di dalam modal (z-index) ==== */
.select2-container--open .select2-dropdown{z-index:2060 !important;}

/* ==== Filter Status (LIST) + perapihan tombol Final All sejajar dropdown ==== */
.list-toolbar{ display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-top:8px; }
.list-toolbar .left-group{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.list-toolbar .form-inline{ display:flex; align-items:center; gap:6px; margin:0; }
.list-toolbar .form-inline label{ margin:0; font-weight:600; color:#334155; }
.list-toolbar .form-inline .form-control{ height:34px; padding:3px 8px; }
.btn-finalall{ height:34px; display:inline-flex; align-items:center; border-radius:10px; }

/* ==== Mobile tweaks ==== */
@media (max-width:768px){
  .nav-tabs > li > a{ padding:8px 10px; font-size:13px; }
  .content-header h1{ font-size:20px; }
  .toolbar-laporan{ gap:10px }
  .toolbar-laporan .form-group{ min-width:100%; }
  .toolbar-actions{ width:100%; margin-left:0; }
  .toolbar-actions .btn{ flex:1; }
  .list-toolbar{ gap:8px; }
  .list-toolbar .btn-group{ margin-left:auto; }
  /* HAPUS full-width pada tombol apply agar tetap sejajar saat memungkinkan */
}

/* === Perjelas animasi tombol Terapkan saat filter berubah === */
@keyframes applyPulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(37,99,235,.45); transform: translateY(0); }
  50%      { box-shadow: 0 0 0 10px rgba(37,99,235,0); transform: translateY(-1px); }
}

/* ============================= */
/* (3) SCOPED TAB BLUE THEME — kotak & menyatu */
/* ============================= */
#tabs-absensi-mapel .nav-tabs{ border:0; }
#tabs-absensi-mapel .nav-tabs > li{ float:left; margin-bottom:0; }
#tabs-absensi-mapel .nav-tabs > li > a{
  background:#E8F1FF !important;   /* soft blue non-aktif */
  color:#0B5ED7 !important;
  border-radius:0 !important;       /* persegi */
  border:1px solid rgba(11,94,215,.20);
  margin-right:0;                   /* tanpa jarak */
  font-weight:700;
  letter-spacing:.1px;
  box-shadow:0 1px 0 rgba(0,0,0,.03) inset;
  padding:10px 14px;
}
/* gabungkan border antar tab supaya tidak double */
#tabs-absensi-mapel .nav-tabs > li:not(:first-child) > a{ margin-left:-1px; }

#tabs-absensi-mapel .nav-tabs > li > a:hover{
  background:#DCEAFE !important;
  color:#0B5ED7 !important;
  transform:translateY(-1px);
}
#tabs-absensi-mapel .nav-tabs > li.active > a,
#tabs-absensi-mapel .nav-tabs > li.active > a:focus,
#tabs-absensi-mapel .nav-tabs > li.active > a:hover{
  background:linear-gradient(135deg,#3B82F6,#0B5ED7) !important;
  color:#fff !important;
  box-shadow:0 10px 22px rgba(11,94,215,.28);
  border-color:transparent !important;
}

/* ============================================= */
/* (1) SEGMENTED "Jenis" — tanpa wadah, tombol berdiri sendiri */
/* ============================================= */
.kind-seg{
  display:inline-flex;
  gap:8px;                 /* jarak antar tombol */
  padding:0;               /* tanpa padding/wadah */
  background:transparent;  /* hilangkan box/wadah */
  border:0;
}
.kind-seg input[type="radio"]{
  position:absolute;
  opacity:0;
  pointer-events:none;
}
.kind-seg .seg-item{
  display:inline-flex; align-items:center; gap:6px;
  padding:8px 12px;              /* ukuran tombol */
  border-radius:10px;            /* elegan */
  background:#ffffff;            /* tombol berdiri sendiri */
  color:#475467;
  font-size:13px;
  font-weight:700;
  border:1px solid #e5e7eb;      /* garis halus */
  cursor:pointer;
  user-select:none;
  transition:all .15s ease;
}
.kind-seg .seg-item:hover{
  background:#F6F8FE;            /* hover lembut */
  box-shadow:0 6px 14px rgba(0,0,0,.05);
  transform:translateY(-1px);
}
.kind-seg input[type="radio"]:checked + .seg-item{
  background:linear-gradient(135deg,#FFB860,#FF922B); /* warna aktif dipertahankan */
  color:#111;
  border-color:transparent;
  box-shadow:0 8px 18px rgba(255,146,43,.28);
}
/* Form-group khusus Jenis agar tidak memaksa lebar 180px */
.toolbar-laporan .form-group.kind-jenis{ min-width:auto; }

@media(max-width:768px){
  .kind-seg{ width:100%; flex-wrap:wrap; }
  .kind-seg .seg-item{ flex:1; justify-content:center; text-align:center; }
}
</style>

<div class="content-wrapper">
  <section class="content-header">
    <h1>Absensi Per Mapel <small>Sesi per jam & laporan rekap</small></h1>
    <ol class="breadcrumb">
      <li><a href="index.php"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Absensi Mapel</li>
    </ol>
  </section>

  <section class="content">
<?php
  // flash
  $alert = isset($_GET['alert']) ? $_GET['alert'] : '';
  $map = [
    'buat_ok'=>['success','Sesi dibuat.'],
    'buat_fail'=>['danger','Gagal membuat sesi.'],
    'simpan_ok'=>['success','Kehadiran tersimpan.'],
    'final_ok'=>['success','Sesi difinalisasi.'],
    'final_locked'=>['warning','Sesi sudah final.'],
    'hapus_ok'=>['success','Sesi dihapus.'],
    'invalid'=>['warning','Data tidak lengkap.'],
    'forbidden'=>['danger','Tidak memiliki akses.'],
    'final_all_ok'=>['success','Semua sesi draf berhasil difinalkan.']
  ];
  if(isset($map[$alert])){
    $extra = '';
    if($alert==='final_all_ok' && isset($_GET['n'])){ $extra = ' <b>(' . (int)$_GET['n'] . ' sesi)</b>'; }
    echo '<div class="alert alert-'.$map[$alert][0].' alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">×</button>'.$map[$alert][1].$extra.'
          </div>';
  }

// tab aktif: list | form sesi | laporan
$sesi_id = isset($_GET['sesi_id'])?(int)$_GET['sesi_id']:0;
$tab = $sesi_id>0 ? 'form' : (_get('tab','list') === 'laporan' ? 'laporan' : 'list');
?>
    <!-- (1) Tambah ID supaya styling tab scoped hanya di area ini -->
    <div class="nav-tabs-custom" id="tabs-absensi-mapel" style="margin-bottom:10px;">
      <ul class="nav nav-tabs">
        <li class="<?= $tab==='list'?'active':'' ?>"><a href="absensi_mapel.php"><i class="fa fa-list-ul"></i> Daftar Sesi</a></li>
        <li class="<?= $tab==='laporan'?'active':'' ?>"><a href="absensi_mapel.php?tab=laporan"><i class="fa fa-bar-chart"></i> Laporan Absensi Mapel</a></li>
        <?php if($sesi_id>0): ?>
        <li class="active"><a><i class="fa fa-edit"></i> Isi/Lihat Sesi</a></li>
        <?php endif; ?>
      </ul>
    </div>

<?php
  // ================== TAB FORM (ISI/LIHAT SESI) ==================
  if($sesi_id>0):
    $hdr = mysqli_query($koneksi,"SELECT s.*, k.kelas_nama, m.mapel_nama, u.user_nama
                                  FROM absensi_sesi s
                                  JOIN kelas k ON k.kelas_id=s.kelas_id
                                  JOIN mapel m ON m.mapel_id=s.mapel_id
                                  JOIN user  u ON u.user_id=s.guru_user_id
                                  WHERE s.sesi_id=$sesi_id");
    $h = $hdr?mysqli_fetch_assoc($hdr):null;
    if(!$h){
      echo '<div class="alert alert-warning">Data tidak ditemukan.</div>';
    } else {
      $editable = ($h['status']!=='final') && ($is_admin || (int)$h['guru_user_id']===$user_id);
      // ringkasan
      $sum = ['H'=>0,'S'=>0,'I'=>0,'A'=>0,'TOTAL'=>0];
      $qsum = mysqli_query($koneksi,"SELECT status, COUNT(*) c FROM absensi_sesi_detail WHERE sesi_id=$sesi_id GROUP BY status");
      while($rr=mysqli_fetch_assoc($qsum)){
        $st = strtoupper($rr['status']);
        if(isset($sum[$st])) $sum[$st]+=(int)$rr['c'];
        $sum['TOTAL'] += (int)$rr['c'];
      }
      $qsis = mysqli_query($koneksi,"SELECT d.siswa_id, s.siswa_nama
                                     FROM absensi_sesi_detail d
                                     JOIN siswa s ON s.siswa_id=d.siswa_id
                                     WHERE d.sesi_id=$sesi_id
                                     ORDER BY s.siswa_nama ASC");
?>
    <div class="box">
      <div class="box-header">
        <h3 class="box-title" style="margin-bottom:8px;">
          <i class="fa fa-calendar-check-o"></i>
          Tgl: <b><?=esc($h['tanggal'])?></b> • 
          Kelas: <b><?=esc($h['kelas_nama'])?></b> • 
          Mapel: <b><?=esc($h['mapel_nama'])?></b> • 
          Jam ke: <b><?= (int)$h['jam_ke'] ?></b> • 
          Guru: <b><?=esc($h['user_nama'])?></b> • 
          Status: <?= $h['status']=='final' ? '<span class="label-status final">FINAL</span>' : '<span class="label-status draft">DRAFT</span>' ?>
        </h3>

        <div class="pull-right">
          <a href="absensi_mapel.php" class="btn btn-default btn-sm" style="margin-right:8px;">
            <i class="fa fa-arrow-left"></i> Kembali
          </a>
          <select id="findSiswa" class="find-sel" style="width:220px;">
            <option value="">Cari siswa…</option>
            <?php if($qsis){ while($s = mysqli_fetch_assoc($qsis)){ ?>
              <option value="<?=$s['siswa_id']?>"><?=esc($s['siswa_nama'])?></option>
            <?php } } ?>
          </select>
        </div>
        <div class="clearfix"></div>
      </div>

      <div class="box-body">
        <div class="summary-bar" style="margin-bottom:8px;">
          <div class="sum-pills">
            <span class="sum-pill sum-h">H: <b id="sumH"><?=$sum['H']?></b></span>
            <span class="sum-pill sum-s">S: <b id="sumS"><?=$sum['S']?></b></span>
            <span class="sum-pill sum-i">I: <b id="sumI"><?=$sum['I']?></b></span>
            <span class="sum-pill sum-a">A: <b id="sumA"><?=$sum['A']?></b></span>
          </div>
          <span class="sum-total">Total: <b id="sumT"><?=$sum['TOTAL']?></b> siswa</span>
          <span class="sum-na">Tidak hadir: <b id="sumNA"><?=($sum['S']+$sum['I']+$sum['A'])?></b></span>
        </div>

        <form method="post" action="absensi_mapel.php" autocomplete="off" id="formSesi">
          <input type="hidden" name="action" value="simpan">
          <input type="hidden" name="sesi_id" value="<?=$sesi_id?>">

          <?php if($editable){ ?>
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
                                              FROM absensi_sesi_detail d
                                              JOIN siswa s ON s.siswa_id=d.siswa_id
                                              WHERE d.sesi_id=$sesi_id
                                              ORDER BY s.siswa_nama ASC");
                  $no=1;
                  if($q){
                    while($r=mysqli_fetch_assoc($q)){
                      $sid=(int)$r['siswa_id'];
                      $st = in_array($r['st'],['H','S','I','A']) ? $r['st'] : 'H';
                      $hasNote = trim((string)$r['keterangan'])!=='' ? 'has-note' : '';
                      $noteTitle = $hasNote ? esc($r['keterangan']) : 'Tambah catatan';
                ?>
                <tr>
                  <td class="col-no"><?=$no++?></td>
                  <td class="col-name"><?=esc($r['siswa_nama'])?></td>
                  <td class="chip-col">
                    <div class="chip-wrap" data-sid="<?=$sid?>" <?= $editable? '':'data-final="1"';?>>
                      <button type="button" class="btn btn-status btn-h<?=$st=='H'?' active':''?>" data-val="H">H</button>
                      <button type="button" class="btn btn-status btn-s<?=$st=='S'?' active':''?>" data-val="S">S</button>
                      <button type="button" class="btn btn-status btn-i<?=$st=='I'?' active':''?>" data-val="I">I</button>
                      <button type="button" class="btn btn-status btn-a<?=$st=='A'?' active':''?>" data-val="A">A</button>

                      <button type="button" class="btn btn-default btn-xs note-btn <?=$hasNote?>" 
                              title="<?=$noteTitle?>" data-toggle="tooltip"
                              data-sid="<?=$sid?>" data-name="<?=esc($r['siswa_nama'])?>" 
                              data-note="<?=esc((string)$r['keterangan'])?>"><i class="fa fa-sticky-note"></i></button>

                      <input type="hidden" name="st[<?=$sid?>]"  value="<?=$st?>" class="st-input">
                      <input type="hidden" name="ket[<?=$sid?>]" value="<?=esc((string)$r['keterangan'])?>" class="ket-input">
                    </div>
                  </td>
                </tr>
                <?php } } ?>
              </tbody>
            </table>
          </div>

          <?php if($editable){ ?>
          <div class="row" style="margin-top:8px;">
            <div class="col-xs-4">
              <a href="absensi_mapel.php" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> <span class="lbl">Kembali</span></a>
            </div>
            <div class="col-xs-4 text-center">
              <a href="absensi_mapel.php?action=final&sesi_id=<?=$sesi_id?>" class="btn btn-primary btn-sm">
                <i class="fa fa-lock"></i> <span class="lbl">Finalisasi</span>
              </a>
            </div>
            <div class="col-xs-4 text-right">
              <button type="submit" class="btn btn-success btn-sm"><i class="fa fa-save"></i> <span class="lbl">Simpan</span></button>
            </div>
          </div>
          <div class="text-center" style="margin-top:8px">
            <small class="text-danger">
              <i class="fa fa-info-circle"></i>
              <b>Simpan</b> = Draf (bisa diedit), <b>Finalisasi</b> = <u>mengunci</u> sesi (siap direkap & dilaporkan).
            </small>
          </div>
          <?php } else { ?>
          <div class="row" style="margin-top:8px;">
            <div class="col-xs-6">
              <?php if($is_admin || (int)$h['guru_user_id']===$user_id): ?>
                <a href="absensi_mapel.php?action=unfinal&sesi_id=<?=$sesi_id?>" class="btn btn-edit-draft btn-sm" onclick="return confirm('Buka kunci sesi ini untuk diedit kembali?');">
                  <i class="fa fa-unlock"></i> <span class="lbl">Edit</span>
                </a>
              <?php endif; ?>
            </div>
            <div class="col-xs-6 text-right">
              <a href="absensi_mapel.php" class="btn btn-default btn-sm"><i class="fa fa-list"></i> <span class="lbl">Daftar</span></a>
            </div>
          </div>
          <?php } ?>
        </form>
      </div>
    </div>
<?php
    }
  // ================== TAB LAPORAN ==================
  elseif($tab==='laporan'):
    // === Mode Rentang (Semester / Kustom) ===
    $mode = _get('mode','semester'); // 'semester' | 'custom'
    $mapel_id = _int(_get('mapel_id'));
    $kelas_id = _int(_get('kelas_id'));
    $guru_id  = _int(_get('guru_id'));
    $kind = _get('kind','rekap'); // default DIUBAH ke rekap

    // derive dates
    if($mode==='semester'){
      $d1 = date('Y-m-d');
      $qm = mysqli_query($koneksi,"SELECT MIN(tanggal) tmin FROM absensi_sesi WHERE ta_id=$ta_aktif");
      if($qm && $rm=mysqli_fetch_assoc($qm) && !empty($rm['tmin'])){ $d1 = $rm['tmin']; }
      else{
        $m = (int)date('n'); $y = (int)date('Y');
        $d1 = ($m>=7? ($y.'-07-01') : ($y.'-01-01'));
      }
      $d2 = date('Y-m-d');
    }else{
      $d1 = _get('d1', date('Y-m-01'));
      $d2 = _get('d2', date('Y-m-d'));
    }

    // RBAC: guru non-admin dipaksa guru_id = dirinya
    $pengampuWhere = "1=1";
    if(!$is_admin){
      $guru_id = $user_id;
      $pengampuWhere = "pm.ta_id=$ta_aktif AND pm.guru_user_id=$user_id";
    }

    // sumber dropdown mapel/kelas/guru
    $listMapel = [];
    if($is_admin){
      $qm = mysqli_query($koneksi,"SELECT mapel_id, mapel_nama FROM mapel ORDER BY mapel_nama");
    }else{
      $qm = mysqli_query($koneksi,"
        SELECT DISTINCT m.mapel_id, m.mapel_nama
        FROM pengampu_mapel pm
        JOIN mapel m ON m.mapel_id=pm.mapel_id
        WHERE $pengampuWhere
        ORDER BY m.mapel_nama
      ");
    }
    if($qm){ while($m=mysqli_fetch_assoc($qm)){ $listMapel[]=$m; } }

    $listKelas = [];
    if($is_admin){
      $qk = mysqli_query($koneksi,"SELECT kelas_id, kelas_nama FROM kelas ORDER BY kelas_nama");
    }else{
      $qk = mysqli_query($koneksi,"
        SELECT DISTINCT k.kelas_id, k.kelas_nama
        FROM pengampu_mapel pm
        JOIN kelas k ON k.kelas_id=pm.kelas_id
        WHERE $pengampuWhere
        ORDER BY k.kelas_nama
      ");
    }
    if($qk){ while($k=mysqli_fetch_assoc($qk)){ $listKelas[]=$k; } }

    $listGuru = [];
    if($is_admin){
      $qg = mysqli_query($koneksi,"
        SELECT DISTINCT u.user_id, u.user_nama 
        FROM pengampu_mapel pm JOIN user u ON u.user_id=pm.guru_user_id
        WHERE pm.ta_id=$ta_aktif
        ORDER BY u.user_nama
      ");
      if($qg){ while($g=mysqli_fetch_assoc($qg)){ $listGuru[]=$g; } }
    }

    // build WHERE laporan
    $where = ["s.ta_id=$ta_aktif","s.status='final'"];
    if($d1) $where[] = "s.tanggal>='".dbesc($d1)."'";
    if($d2) $where[] = "s.tanggal<='".dbesc($d2)."'";
    if($mapel_id>0) $where[] = "s.mapel_id=$mapel_id";
    if($kelas_id>0) $where[] = "s.kelas_id=$kelas_id";
    if($guru_id>0)  $where[] = "s.guru_user_id=$guru_id";
?>
    <div class="box">
      <div class="box-header">
        <h3 class="box-title"><i class="fa fa-bar-chart"></i> Laporan Absensi Mapel</h3>
      </div>
      <div class="box-body">
        <form class="toolbar-laporan" method="get" action="absensi_mapel.php" autocomplete="off" style="margin-bottom:10px">
          <input type="hidden" name="tab" value="laporan">

          <div class="form-group">
            <label>Mode Rentang</label>
            <select class="form-control" name="mode" id="mode_rentang">
              <option value="semester" <?= $mode==='semester'?'selected':'' ?>>Semester (Mulai Efektif → Hari Ini)</option>
              <option value="custom"   <?= $mode==='custom'  ?'selected':'' ?>>Kustom (Rentang Tanggal)</option>
            </select>
          </div>

          <div class="form-group" id="wrap_daterange">
            <label>Rentang</label>
            <div style="display:flex;gap:8px;align-items:center;">
              <input type="date" class="form-control" name="d1" id="d1" value="<?=esc($d1)?>">
              <span>—</span>
              <input type="date" class="form-control" name="d2" id="d2" value="<?=esc($d2)?>">
            </div>
          </div>

          <div class="form-group">
            <label>Mapel</label>
            <select class="form-control sel2" name="mapel_id" style="min-width:180px">
              <option value="0">Semua Mapel</option>
              <?php foreach($listMapel as $m): ?>
              <option value="<?=$m['mapel_id']?>" <?= $mapel_id==$m['mapel_id']?'selected':'' ?>><?=esc($m['mapel_nama'])?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Kelas</label>
            <select class="form-control sel2" name="kelas_id" style="min-width:160px">
              <option value="0">Semua Kelas</option>
              <?php foreach($listKelas as $k): ?>
              <option value="<?=$k['kelas_id']?>" <?= $kelas_id==$k['kelas_id']?'selected':'' ?>><?=esc($k['kelas_nama'])?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <?php if($is_admin): ?>
          <div class="form-group">
            <label>Guru</label>
            <select class="form-control sel2" name="guru_id" style="min-width:180px">
              <option value="0">Semua Guru</option>
              <?php foreach($listGuru as $g): ?>
              <option value="<?=$g['user_id']?>" <?= $guru_id==$g['user_id']?'selected':'' ?>><?=esc($g['user_nama'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>

          <!-- (1) Jenis: tombol berdiri sendiri (tanpa box wadah) -->
          <div class="form-group kind-jenis">
            <label>Jenis</label>
            <div class="kind-seg" role="group" aria-label="Jenis Laporan">
              <input type="radio" name="kind" id="kind_rekap" value="rekap" <?= $kind==='rekap'?'checked':''; ?>>
              <label for="kind_rekap" class="seg-item"><i class="fa fa-users"></i> Rekap per Siswa</label>

              <input type="radio" name="kind" id="kind_harian" value="harian" <?= $kind==='harian'?'checked':''; ?>>
              <label for="kind_harian" class="seg-item"><i class="fa fa-table"></i> Tabel per Tanggal</label>
            </div>
          </div>

          <div class="form-group">
            <label>&nbsp;</label>
            <button class="btn btn-apply"><i class="fa fa-filter"></i> Terapkan</button>
          </div>

          <div class="toolbar-actions">
            <a target="_blank"
               href="absensi_mapel_laporan_cetak.php?kind=<?=esc($kind)?>&d1=<?=esc($d1)?>&d2=<?=esc($d2)?>&mapel_id=<?=$mapel_id?>&kelas_id=<?=$kelas_id?>&guru_id=<?=$guru_id?>"
               class="btn btn-print" title="Cetak tampilan ini">
               <i class="fa fa-print"></i> Cetak
            </a>
            <a href="absensi_mapel.php?action=export_csv&kind=<?=esc($kind)?>&d1=<?=esc($d1)?>&d2=<?=esc($d2)?>&mapel_id=<?=$mapel_id?>&kelas_id=<?=$kelas_id?>&guru_id=<?=$guru_id?>"
               class="btn btn-export" title="Export ke CSV">
               <i class="fa fa-file-excel-o"></i> Export CSV
            </a>
          </div>
        </form>

<?php
    if($kind==='rekap'){
      $sql = "
        SELECT si.siswa_nama,
               SUM(d.status='H') AS H, SUM(d.status='S') AS S,
               SUM(d.status='I') AS I, SUM(d.status='A') AS A,
               COUNT(*) AS total
        FROM absensi_sesi s
        JOIN absensi_sesi_detail d ON d.sesi_id=s.sesi_id
        JOIN siswa si ON si.siswa_id=d.siswa_id
        WHERE ".implode(' AND ', $where)."
        GROUP BY d.siswa_id
        ORDER BY si.siswa_nama ASC";
      $q = mysqli_query($koneksi,$sql);
?>
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-hover" id="lap-rekap">
            <thead>
              <tr>
                <th style="width:1%">No</th>
                <th>Nama Siswa</th>
                <th class="text-center">H</th><th class="text-center">S</th><th class="text-center">I</th><th class="text-center">A</th>
                <th class="text-center">% Hadir</th>
              </tr>
            </thead>
            <tbody>
<?php
      $no=1;
      if($q){
        while($r=mysqli_fetch_assoc($q)){
          $ph = ($r['total']>0) ? round(($r['H']*100)/$r['total'],1) : 0;
          echo '<tr>
            <td class="text-right">'.($no++).'</td>
            <td>'.esc($r['siswa_nama']).'</td>
            <td class="text-center">'.$r['H'].'</td>
            <td class="text-center">'.$r['S'].'</td>
            <td class="text-center">'.$r['I'].'</td>
            <td class="text-center">'.$r['A'].'</td>
            <td class="text-center">'.$ph.'%</td>
          </tr>';
        }
      }
?>
            </tbody>
          </table>
        </div>
<?php
    } else {
      $sql = "
        SELECT s.sesi_id,s.tanggal,k.kelas_nama,m.mapel_nama,s.jam_ke,u.user_nama,
               SUM(d.status='H') AS H, SUM(d.status='S') AS S,
               SUM(d.status='I') AS I, SUM(d.status='A') AS A,
               COUNT(*) AS total
        FROM absensi_sesi s
        JOIN absensi_sesi_detail d ON d.sesi_id=s.sesi_id
        JOIN kelas k ON k.kelas_id=s.kelas_id
        JOIN mapel m ON m.mapel_id=s.mapel_id
        JOIN user  u ON u.user_id=s.guru_user_id
        WHERE ".implode(' AND ', $where)."
        GROUP BY s.sesi_id
        ORDER BY s.tanggal DESC,k.kelas_nama ASC,s.jam_ke ASC";
      $q = mysqli_query($koneksi,$sql);
?>
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-hover" id="lap-harian">
            <thead>
              <tr>
                <th style="width:1%">No</th>
                <th>Tanggal</th>
                <th>Kelas</th>
                <th>Mapel</th>
                <th>Jam</th>
                <th>Guru</th>
                <th class="text-center">H</th><th class="text-center">S</th><th class="text-center">I</th><th class="text-center">A</th>
                <th class="text-center">% Hadir</th>
                <th>Opsi</th>
              </tr>
            </thead>
            <tbody>
<?php
      $no=1;
      if($q){
        while($r=mysqli_fetch_assoc($q)){
          $ph = ($r['total']>0) ? round(($r['H']*100)/$r['total'],1) : 0;
          echo '<tr>
            <td class="text-right">'.($no++).'</td>
            <td>'.esc($r['tanggal']).'</td>
            <td>'.esc($r['kelas_nama']).'</td>
            <td>'.esc($r['mapel_nama']).'</td>
            <td class="text-center">'.(int)$r['jam_ke'].'</td>
            <td>'.esc($r['user_nama']).'</td>
            <td class="text-center">'.$r['H'].'</td>
            <td class="text-center">'.$r['S'].'</td>
            <td class="text-center">'.$r['I'].'</td>
            <td class="text-center">'.$r['A'].'</td>
            <td class="text-center">'.$ph.'%</td>
            <td class="opsi-col">
              <a href="absensi_mapel.php?sesi_id='.$r['sesi_id'].'" class="btn btn-default btn-xs" title="Lihat"><i class="fa fa-eye"></i></a>
            </td>
          </tr>';
        }
      }
?>
            </tbody>
          </table>
        </div>
<?php
    } // end kind
?>
      </div>
    </div>

<?php
  // ================== TAB LIST SESI ==================
  else:
    if(!$is_admin && !$has_pengampu){
      echo '<div class="alert alert-warning"><i class="fa fa-info-circle"></i> Belum ada penugasan mengajar untuk Tahun Ajaran aktif. Silakan hubungi admin.</div>';
    }

    // ==== STATUS FILTER (ALL/DRAFT/FINAL) ==== //
    $sf = strtolower(_get('sf','all'));
    if(!in_array($sf,['all','draft','final'],true)) $sf='all';
    $whereStatus = "1=1";
    if($sf==='draft'){ $whereStatus = "s.status='draft'"; }
    elseif($sf==='final'){ $whereStatus = "s.status='final'"; }

    // Draft count untuk tombol Final All (admin)
    $draft_count = 0;
    if($is_admin && $ta_aktif>0){
      $cq = mysqli_query($koneksi,"SELECT COUNT(*) c FROM absensi_sesi s WHERE s.ta_id=$ta_aktif AND s.status='draft'");
      if($cq && $cr=mysqli_fetch_assoc($cq)) $draft_count = (int)$cr['c'];
    }
?>
    <div class="box">
      <div class="box-header">
        <h3 class="box-title">Daftar Sesi Absensi Mapel (Per JP)</h3>
        <div class="help-block" style="margin-top:4px;">
          <small class="text-danger">
            <i class="fa fa-info-circle"></i>
            <b>Simpan</b> = <i>Draf</i>, <b>Finalisasi</b> = <u>kunci</u> sesi (siap rekap). <span class="text-muted">JP = Jam Pelajaran.</span>
          </small>
        </div>

        <!-- Toolbar: Status Filter & (Admin) Finalkan Semua Draf -->
        <div class="list-toolbar">
          <div class="left-group">
            <form class="form-inline" method="get" action="absensi_mapel.php">
              <label for="sf">Tampilkan:</label>
              <select name="sf" id="sf" class="form-control input-sm" onchange="this.form.submit()">
                <option value="all"   <?= $sf==='all'?'selected':'' ?>>Semua</option>
                <option value="draft" <?= $sf==='draft'?'selected':'' ?>>Draf</option>
                <option value="final" <?= $sf==='final'?'selected':'' ?>>Final</option>
              </select>
            </form>

            <?php if($is_admin && $sf==='draft'): ?>
            <form method="post" action="absensi_mapel.php">
              <input type="hidden" name="action" value="final_all">
              <button type="submit" class="btn btn-warning btn-sm btn-finalall" onclick="return confirm('Finalkan SEMUA sesi berstatus DRAF pada TA aktif?\nTindakan ini tidak dapat dibatalkan.')">
                <i class="fa fa-lock"></i>&nbsp;&nbsp;Finalkan Semua Draf
                <?php if($draft_count>0): ?><span class="badge" style="background:#fff;color:#b45309;margin-left:6px"><?=$draft_count?></span><?php endif; ?>
              </button>
            </form>
            <?php endif; ?>
          </div>

          <div class="btn-group pull-right" style="margin-left:auto">
<?php
  $btn_disabled = (!$is_admin && !$has_pengampu);
  if($btn_disabled){
    echo '<span data-toggle="tooltip" title="Belum ada penugasan mengajar. Hubungi admin.">
            <button type="button" class="btn btn-shimmer btn-sm" disabled>
              <i class="fa fa-calendar-plus-o"></i> + Input Absensi Mapel <span class="badge-live">OFF</span>
            </button>
          </span>';
  }else{
    echo '<button type="button" class="btn btn-shimmer btn-sm" data-toggle="modal" data-target="#modal_buat">
            <i class="fa fa-calendar-plus-o"></i> + Input Absensi Mapel
          </button>';
  }
?>
          </div>
        </div>
      </div>
      <div class="box-body">
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-hover row-hover" id="table-datatable">
            <thead>
              <tr>
                <th class="fit">No</th>
                <th class="fit">Tanggal</th>
                <th>Kelas</th>
                <th>Mapel</th>
                <th class="fit">Jam</th>
                <th>Guru</th>
                <th class="fit">Status</th>
                <th class="opsi-col fit">Opsi</th>
              </tr>
            </thead>
            <tbody>
<?php
  $whereGuru = $is_admin ? "1=1" : "s.guru_user_id=$user_id";
  $sql="SELECT s.sesi_id,s.tanggal,s.status,s.jam_ke, k.kelas_nama, m.mapel_nama, u.user_nama
        FROM absensi_sesi s
        JOIN kelas k ON k.kelas_id=s.kelas_id
        JOIN mapel m ON m.mapel_id=s.mapel_id
        JOIN user  u ON u.user_id=s.guru_user_id
        WHERE s.ta_id=$ta_aktif AND $whereGuru AND $whereStatus
        ORDER BY s.tanggal DESC, k.kelas_nama ASC, s.jam_ke ASC";
  $res=mysqli_query($koneksi,$sql);
  $no=1;
  if($res){
    while($r=mysqli_fetch_assoc($res)){
?>
              <tr>
                <td class="fit"><?=$no++?></td>
                <td class="fit"><?=esc($r['tanggal'])?></td>
                <td><?=esc($r['kelas_nama'])?></td>
                <td><?=esc($r['mapel_nama'])?></td>
                <td class="fit text-center"><?= (int)$r['jam_ke'] ?></td>
                <td><?=esc($r['user_nama'])?></td>
                <td class="fit">
                  <?= $r['status']=='final'
                      ? '<span class="label-status final">FINAL</span>'
                      : '<span class="label-status draft">DRAFT</span>' ?>
                </td>
                <td class="opsi-col">
<?php if($r['status']!=='final'){ ?>
                  <a href="absensi_mapel.php?sesi_id=<?=$r['sesi_id']?>" class="btn btn-edit-draft btn-sm" title="Isi (Draft)">
                    <i class="fa fa-edit"></i><span class="lbl"> Isi</span>
                  </a>
                  <a href="absensi_mapel.php?action=final&sesi_id=<?=$r['sesi_id']?>" class="btn btn-info btn-sm btn-fin" title="Finalisasi">
                    <i class="fa fa-lock"></i>
                  </a>
<?php } else { ?>
                  <a href="absensi_mapel.php?sesi_id=<?=$r['sesi_id']?>" class="btn btn-default btn-view btn-sm" title="Lihat (final)">
                    <i class="fa fa-eye"></i><span class="lbl"> Lihat</span>
                  </a>
<?php } ?>
                  <a href="absensi_mapel.php?action=hapus&sesi_id=<?=$r['sesi_id']?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus sesi ini?')"
                     title="Hapus">
                    <i class="fa fa-trash"></i>
                  </a>
                </td>
              </tr>
<?php } } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal Buat Sesi -->
    <div class="modal fade" id="modal_buat" tabindex="-1">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <form method="post" action="absensi_mapel.php" autocomplete="off">
            <input type="hidden" name="action" value="buat">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal">&times;</button>
              <h4 class="modal-title"><i class="fa fa-calendar-plus-o"></i> Buat Sesi Absensi Mapel</h4>
            </div>
            <div class="modal-body">
              <div class="form-group">
                <label>Tanggal</label>
                <input type="date" name="tanggal" class="form-control" required value="<?=date('Y-m-d')?>">
              </div>
              <div class="form-group">
                <label>Kelas • Mapel <small class="text-muted">(otomatis dari pengampu)</small></label>
                <select name="km" id="km_sel" class="form-control sel2" required <?=(!$is_admin && !$has_pengampu)?'disabled':'';?>>
<?php
  if(!$is_admin && !$has_pengampu){
    echo '<option value="">— Tidak ada penugasan —</option>';
  } else {
    $where = $is_admin ? "1=1" : "pm.guru_user_id=$user_id";
    $kq = mysqli_query($koneksi,"
      SELECT pm.kelas_id, k.kelas_nama, pm.mapel_id, m.mapel_nama, pm.guru_user_id, u.user_nama
      FROM pengampu_mapel pm
      JOIN kelas k ON k.kelas_id=pm.kelas_id
      JOIN mapel m ON m.mapel_id=pm.mapel_id
      JOIN user  u ON u.user_id=pm.guru_user_id
      WHERE pm.ta_id=$ta_aktif AND $where
      ORDER BY k.kelas_nama, m.mapel_nama
    ");
    echo '<option value="">- Pilih Kelas • Mapel -</option>';
    if($kq){
      while($km=mysqli_fetch_assoc($kq)){
        $val = (int)$km['kelas_id'].'|'.(int)$km['mapel_id'].'|'.(int)$km['guru_user_id'];
        $lbl = esc(trim($km['kelas_nama'])).' • '.esc(trim($km['mapel_nama']));
        if($is_admin){ $lbl .= ' — '.esc($km['user_nama']); }
        echo '<option value="'.$val.'">'.$lbl.'</option>';
      }
    }
  }
?>
                </select>
              </div>
              <div class="form-group">
                <label>Jam ke</label>
                <input type="number" name="jam_ke" class="form-control" min="1" max="20" value="1" required <?=(!$is_admin && !$has_pengampu)?'disabled':'';?>>
              </div>
              <p class="text-muted"><i class="fa fa-info-circle"></i> Siswa akan otomatis dimuat dari <em>kelas_siswa</em>.</p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-primary btn-sm" <?=(!$is_admin && !$has_pengampu)?'disabled':'';?>>Buat</button>
            </div>
          </form>
        </div>
      </div>
    </div>
<?php
  endif; // tab
?>
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

<!-- Tombol Back to Top -->
<button id="toTop" title="Kembali ke atas" aria-label="Kembali ke atas"
        style="position:fixed;right:16px;bottom:18px;z-index:999;display:none;width:42px;height:42px;border-radius:50%;border:0;background:#1670c8;color:#fff;box-shadow:0 6px 14px rgba(22,112,200,.35)">
  <i class="fa fa-arrow-up"></i>
</button>

<script>
$(function(){
  // DataTables daftar sesi
  if ($.fn.dataTable && $.fn.dataTable.ext) { $.fn.dataTable.ext.errMode = 'console'; }
  if ($('#table-datatable').length && $.fn.DataTable) {
    var t = $('#table-datatable').DataTable({
      responsive:true,
      autoWidth:false,
      order:[[1,'desc'],[2,'asc'],[4,'asc']],
      columnDefs:[{targets:[0,7],orderable:false}],
      orderMulti:true,
      pageLength:20,
      lengthMenu:[[10,20,25,50,100,-1],[10,20,25,50,100,'Semua']],
      language:{
        paginate:{previous:"‹",next:"›"},
        lengthMenu:"Tampilkan _MENU_ baris",
        search:"Cari:",
        info:"Menampilkan _START_–_END_ dari _TOTAL_ data",
        infoEmpty:"Tidak ada data",
        zeroRecords:"Tidak ditemukan data"
      }
    });
    function renumberList(){
      var info = t.page.info();
      t.column(0,{search:'applied',order:'applied',page:'current'}).nodes().each(function(c,i){
        c.innerHTML = info.start + i + 1;
      });
    }
    t.on('order.dt search.dt draw.dt', renumberList).draw();
  }

  // ====== DataTables LAPORAN (default 25 + full numbers + sorting header) ======
  if ($('#lap-harian').length && $.fn.DataTable) {
    var lh = $('#lap-harian').DataTable({
      paging:true, pagingType:'full_numbers', searching:true, ordering:true, orderMulti:true,
      order:[[1,'desc']],
      autoWidth:false, responsive:true,
      pageLength:25,
      lengthMenu:[[10,15,25,50,100,-1],[10,15,25,50,100,'Semua']],
      columnDefs:[{targets:[0,11],orderable:false}],
      language:{
        paginate:{first:'«', previous:'‹', next:'›', last:'»'},
        lengthMenu:'Tampilkan _MENU_ baris',
        search:'Cari:',
        info:'Menampilkan _START_–_END_ dari _TOTAL_ data',
        infoEmpty:'Tidak ada data',
        zeroRecords:'Tidak ditemukan data'
      }
    });
    function renumberHarian(){
      var info = lh.page.info();
      lh.column(0,{search:'applied',order:'applied',page:'current'}).nodes().each(function(cell,i){ cell.innerHTML = info.start + i + 1; });
    }
    lh.on('order.dt search.dt draw.dt', renumberHarian).draw();
  }

  if ($('#lap-rekap').length && $.fn.DataTable) {
    var lr = $('#lap-rekap').DataTable({
      paging:true, pagingType:'full_numbers', searching:true, ordering:true, orderMulti:true,
      order:[[1,'asc']],
      autoWidth:false, responsive:true,
      pageLength:25,
      lengthMenu:[[10,15,25,50,100,-1],[10,15,25,50,100,'Semua']],
      columnDefs:[{targets:[0],orderable:false}],
      language:{
        paginate:{first:'«', previous:'‹', next:'›', last:'»'},
        lengthMenu:'Tampilkan _MENU_ baris',
        search:'Cari:',
        info:'Menampilkan _START_–_END_ dari _TOTAL_ data',
        infoEmpty:'Tidak ada data',
        zeroRecords:'Tidak ditemukan data'
      }
    });
    function renumberRekap(){
      var info = lr.page.info();
      lr.column(0,{search:'applied',order:'applied',page:'current'}).nodes().each(function(cell,i){ cell.innerHTML = info.start + i + 1; });
    }
    lr.on('order.dt search.dt draw.dt', renumberRekap).draw();
  }

  // Select2 global
  $('.sel2').select2({width:'100%'});
  // Select2 di dalam modal
  $('#modal_buat').on('shown.bs.modal', function(){
    $('#modal_buat .sel2').select2({width:'100%', dropdownParent: $('#modal_buat')});
  });

  // QUICK FIND di form sesi
  $('#findSiswa').select2({width:'220px', placeholder:'Cari siswa…', allowClear:true});

  // Tooltip
  $('[data-toggle="tooltip"]').tooltip({container:'body'});

  // ====== FORM SESI: chip + catatan + autosave ======
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
  $(document).on('click','.chip-wrap .btn-status',function(){
    var $btn = $(this), $wrap = $btn.closest('.chip-wrap');
    if($wrap.data('final')=='1') return;
    $wrap.find('.btn-status').removeClass('active');
    $btn.addClass('active');
    $wrap.find('input.st-input').val($btn.data('val'));
    var val = ($btn.data('val')||'').toString().toUpperCase();
    var $row = $wrap.closest('tr');
    if($row.hasClass('find-focus') && (val==='S' || val==='I' || val==='A')){ $row.removeClass('find-focus'); }
    markDirty(); recalcSummary();
  });
  $('#btn-set-hadir').on('click', function(){
    $('.chip-wrap').each(function(){
      var $w=$(this); if($w.data('final')=='1') return;
      $w.find('.btn-status').removeClass('active'); 
      $w.find('.btn-h').addClass('active');
      $w.find('input.st-input').val('H');
    });
    markDirty(); recalcSummary();
  });

  var noteSid = null;
  $(document).on('click','.note-btn', function(){
    var $b = $(this);
    noteSid = $b.data('sid');
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
    $btn.attr('title', v!==''? v : 'Tambah catatan').tooltip('fixTitle');
    $('#ketModal').modal('hide');
    noteSid = null; markDirty();
  });

  // QUICK FIND scroll + highlight baris
  function scrollToSid(sid){
    var $row = $('.chip-wrap[data-sid="'+sid+'"]').closest('tr');
    if($row.length){
      $('html, body').animate({scrollTop: ($row.offset().top - 120)}, 260);
      $('tr.find-focus').removeClass('find-focus');
      $row.addClass('find-focus');
    }
  }
  $('#findSiswa').on('change', function(){ var sid=$(this).val(); if(sid){ scrollToSid(sid); } else { $('tr.find-focus').removeClass('find-focus'); }});

  // AUTOSAVE
  var autosaveMs = 12000;
  var autosaveOn = $('#formSesi').length>0 && $('a[href*="action=final"]').length>0;
  function doAutosave(){
    if(!autosaveOn || !dirty) return;
    var $f = $('#formSesi');
    var data = $f.serializeArray();
    data.push({name:'action', value:'autosave'});
    $.ajax({ url:'absensi_mapel.php', type:'POST', data:data, dataType:'json' })
      .done(function(res){ if(res && res.ok){ dirty=false; $('#autosaveStatus').text('Draft tersimpan ' + (res.time || new Date().toLocaleTimeString())); } });
  }
  setInterval(doAutosave, autosaveMs);
  if ($('#formSesi').length) recalcSummary();

  // Mode Rentang (Semester/Kustom)
  function toggleRange(){
    const mode = $('#mode_rentang').val();
    if(mode==='semester'){
      $('#wrap_daterange input').prop('readonly', true).addClass('disabled');
    }else{
      $('#wrap_daterange input').prop('readonly', false).removeClass('disabled');
    }
  }
  $('#mode_rentang').on('change', toggleRange); toggleRange();

  // ==== Highlight tombol Terapkan saat filter berubah ====
  var $applyBtn = $('.toolbar-laporan .btn-apply');
  $('.toolbar-laporan').on('change input', 'select,input', function(){ $applyBtn.addClass('is-dirty'); });
  $('.toolbar-laporan').on('submit', function(){ $applyBtn.removeClass('is-dirty'); });

  // Back to Top
  var $toTop = $('#toTop');
  $(window).on('scroll', function(){ if ($(this).scrollTop() > 300) $toTop.fadeIn(150); else $toTop.fadeOut(150); });
  $toTop.on('click', function(){ $('html, body').animate({scrollTop:0}, 300); });
});
</script>

<?php include 'footer.php'; ?>
