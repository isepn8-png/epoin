<?php
/**
 * admin/manajemen_pengguna.php — Satu file, 3 Tab:
 *  - Tab 1: Akun & Role (Daftar Pengguna + Kelola Role via AJAX modal)
 *  - Tab 2: Guru Piket (dari user ber-role guru) — tambah/hapus sebagai piket
 *  - Tab 3: Sekretaris (Absensi Kelas) dari data SISWA (jadikan/cabut/reset password)
 *
 * Catatan:
 *  - Butuh jQuery, Bootstrap (modal & tooltip). DataTables dipakai utk header sortable.
 *  - CSRF dipakai untuk semua POST (AJAX & non-AJAX).
 */

// ====== Auth & koneksi (JANGAN output apa pun sebelum blok AJAX) ======
require_once __DIR__ . '/../includes/auth.php';
ensure_logged_in();

include '../koneksi.php';
if (!user_has_any_role(['administrator','superadmin','tas'])) { http_response_code(403); exit('Akses ditolak'); }

// Self URL (untuk link tab yang aman dari <base> / rewrite)
$SELF = htmlspecialchars(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), ENT_QUOTES, 'UTF-8');

// Tab aktif
$tab = $_GET['tab'] ?? 'akun'; // akun | sekretaris | piket

// ====== CSRF helper ======
if (!isset($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$CSRF = $_SESSION['csrf'];
function require_csrf_all() {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
      http_response_code(419); exit('CSRF token invalid');
    }
  }
}

// ====== Util ======
function _h($x){return htmlspecialchars((string)$x, ENT_QUOTES,'UTF-8');}
function q1($db,$sql){$r=mysqli_query($db,$sql);return $r?mysqli_fetch_assoc($r):null;}

/* ==================== [BARU] FILTER USER TERSEMBUNYI ==================== */
/* Akun/identitas di bawah akan DISSEMBUNYIKAN dari tabel daftar pengguna */
$HIDE_USERNAMES = ['superadmin_ep'];         // sembunyikan berdasarkan username
$HIDE_NAMES     = ['Administrator EP'];      // sembunyikan berdasarkan nama tampil

// Helper kecil untuk bikin klausa NOT IN yang aman
function sql_not_in($koneksi, $col, array $vals){
  $vals = array_values(array_unique(array_filter($vals, 'strlen')));
  if (!$vals) return '1=1';
  $esc = array_map(function($v) use ($koneksi){ return mysqli_real_escape_string($koneksi, $v); }, $vals);
  return $col . " NOT IN ('" . implode("','", $esc) . "')";
}
/* ======================================================================== */

// ====== Lookup konstanta DB ======
$roleSek = q1($koneksi, "SELECT role_id FROM roles WHERE role_key='sekretaris' LIMIT 1");
$ROLE_SEKRETARIS = $roleSek? (int)$roleSek['role_id'] : 8;

$roleGuru = q1($koneksi, "SELECT role_id FROM roles WHERE role_key='guru' LIMIT 1");
$ROLE_GURU = $roleGuru? (int)$roleGuru['role_id'] : 3;

// ambil sekolah_id pertama (sistem ini single-school)
$sk = q1($koneksi, "SELECT sekolah_id FROM sekolah LIMIT 1");
$SEKOLAH_ID = $sk ? (int)$sk['sekolah_id'] : 1;

// password default reset siswa
$DEFAULT_RESET_PASSWORD = '123456';

/* =========================================================
 * ============  BLOK AJAX: KELUAR DENGAN JSON  ============
 * ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  require_csrf_all();
  header('Content-Type: application/json; charset=utf-8');
  $act = $_POST['action'];

  // --- Ambil daftar semua role + role milik user
  if ($act === 'get_user_roles') {
    $uid = (int)($_POST['uid'] ?? 0);
    if ($uid <= 0) { echo json_encode(['ok'=>false,'msg'=>'User tidak valid']); exit; }

    $roles = [];
    $q = mysqli_query($koneksi,"SELECT role_id, role_key, role_name FROM roles ORDER BY role_id");
    while ($r = mysqli_fetch_assoc($q)) $roles[] = $r;

    $mine = [];
    $mq = mysqli_query($koneksi,"SELECT role_id FROM user_roles WHERE user_id=".(int)$uid);
    while ($m = mysqli_fetch_assoc($mq)) $mine[] = (int)$m['role_id'];

    echo json_encode(['ok'=>true,'roles'=>$roles,'mine'=>$mine]); exit;
  }

  // --- Simpan role user (cegah cabut superadmin terakhir)
  if ($act === 'save_user_roles') {
    $uid = (int)($_POST['uid'] ?? 0);
    if ($uid <= 0) { echo json_encode(['ok'=>false,'msg'=>'User tidak valid']); exit; }

    $isSuperadmin = function($uid) use($koneksi){
      $r = mysqli_query($koneksi,"SELECT 1
        FROM user_roles ur
        JOIN roles r ON r.role_id=ur.role_id
        WHERE ur.user_id=".(int)$uid." AND r.role_key='superadmin' LIMIT 1");
      return $r && mysqli_num_rows($r) > 0;
    };
    $countSuperadmin = function() use($koneksi){
      $r = mysqli_query($koneksi,"SELECT COUNT(DISTINCT ur.user_id) AS n
        FROM user_roles ur
        JOIN roles r ON r.role_id=ur.role_id
        WHERE r.role_key='superadmin'");
      $row = mysqli_fetch_assoc($r); return (int)$row['n'];
    };

    $selected = array_map('intval', $_POST['roles'] ?? []);

    if ($isSuperadmin($uid) && $countSuperadmin() === 1) {
      $hasSuper = false;
      if ($selected) {
        $in = implode(',', $selected);
        $chk = mysqli_query($koneksi,"SELECT 1 FROM roles WHERE role_id IN ($in) AND role_key='superadmin' LIMIT 1");
        $hasSuper = $chk && mysqli_num_rows($chk) > 0;
      }
      if (!$hasSuper) { echo json_encode(['ok'=>false,'msg'=>'Tidak boleh mencabut Super Admin terakhir']); exit; }
    }

    mysqli_query($koneksi,"DELETE FROM user_roles WHERE user_id=".(int)$uid);
    foreach ($selected as $rid) {
      mysqli_query($koneksi,"INSERT INTO user_roles (user_id, role_id) VALUES (".(int)$uid.", ".(int)$rid.")");
    }
    echo json_encode(['ok'=>true]); exit;
  }

  // --- Hapus user (hard delete) — [DIPERKUAT] lindungi SEMUA yang ber-role 'superadmin'
  if ($act === 'delete_user') {
    $uid = (int)($_POST['uid'] ?? 0);
    $me  = (int)($_SESSION['id'] ?? 0);
    if ($uid === 1) { echo json_encode(['ok'=>false,'msg'=>'User root tidak boleh dihapus']); exit; }

    // Cegah menghapus akun yang MEMILIKI role superadmin (tanpa peduli username/nama)
    $rs = mysqli_query($koneksi,"SELECT 1
                                 FROM user_roles ur
                                 JOIN roles r ON r.role_id=ur.role_id
                                 WHERE ur.user_id=".(int)$uid." AND r.role_key='superadmin' LIMIT 1");
    if ($rs && mysqli_num_rows($rs) > 0) {
      echo json_encode(['ok'=>false,'msg'=>'Akun dengan role superadmin tidak dapat dihapus']); exit;
    }

    if ($uid === $me){ echo json_encode(['ok'=>false,'msg'=>'Tidak boleh menghapus akun sendiri']); exit; }

    mysqli_query($koneksi,"DELETE FROM user WHERE user_id=".(int)$uid);
    echo json_encode(['ok'=>true]); exit;
  }

  echo json_encode(['ok'=>false,'msg'=>'Aksi tidak dikenal']); exit;
}
/* ======================= END AJAX ======================= */

/* =========================================================
 * ========  BLOK POST Non-AJAX: Sekretaris / Piket  =======
 * ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
  require_csrf_all();
  $act = $_POST['act'] ?? '';

  // ===== Sekretaris =====
  if (in_array($act, ['sek_make','sek_revoke','sek_resetpass'], true)) {
    $sid = (int)($_POST['siswa_id'] ?? 0);
    if ($sid <= 0) { header('Location: '.$SELF.'?tab=sekretaris'); exit; }

    if ($act==='sek_make') {
      $s = q1($koneksi, "SELECT s.*, ks.ks_kelas FROM siswa s
                          LEFT JOIN kelas_siswa ks ON ks.ks_siswa=s.siswa_id
                          WHERE s.siswa_id=".(int)$sid." LIMIT 1");
      if ($s){
        mysqli_begin_transaction($koneksi);
        try {
          // buat/ambil user yang terhubung siswa
          $u = q1($koneksi, "SELECT * FROM user WHERE linked_siswa_id=".(int)$sid." LIMIT 1");
          if (!$u){
            $stmt = mysqli_prepare($koneksi, "INSERT INTO user(user_nama,user_username,user_password,user_foto,linked_siswa_id,user_level,status_login) VALUES(?,?,?,?,?,'sekretaris','offline')");
            $foto = '';
            mysqli_stmt_bind_param($stmt,'ssssi',$s['siswa_nama'],$s['siswa_nis'],$s['siswa_password'],$foto,$sid);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            $userId = (int)mysqli_insert_id($koneksi);
          } else { $userId = (int)$u['user_id']; }

          // role sekretaris (pencatatan staff bisa ditangani modul lain)
          mysqli_query($koneksi, "INSERT IGNORE INTO user_roles(user_id,role_id) VALUES(".(int)$userId.",".(int)$ROLE_SEKRETARIS.")");

          mysqli_commit($koneksi);
          $_SESSION['flash_success'] = 'Sekretaris ditetapkan.';
        } catch (Throwable $e){ mysqli_rollback($koneksi); $_SESSION['flash_error']='Gagal menetapkan sekretaris: '.$e->getMessage(); }
      }
      header('Location: '.$SELF.'?tab=sekretaris'); exit;
    }

    if ($act==='sek_revoke') {
      $u = q1($koneksi, "SELECT user_id FROM user WHERE linked_siswa_id=".(int)$sid." LIMIT 1");
      if ($u){
        $uid = (int)$u['user_id'];
        mysqli_begin_transaction($koneksi);
        try{
          mysqli_query($koneksi, "DELETE FROM user_roles WHERE user_id=".(int)$uid." AND role_id=".(int)$ROLE_SEKRETARIS);
          mysqli_query($koneksi, "DELETE FROM sekolah_staff WHERE posisi_key='sekretaris' AND user_id=".(int)$uid);
          mysqli_commit($koneksi);
          $_SESSION['flash_success']='Role Sekretaris dicabut.';
        }catch(Throwable $e){ mysqli_rollback($koneksi); $_SESSION['flash_error']='Gagal mencabut: '.$e->getMessage(); }
      }
      header('Location: '.$SELF.'?tab=sekretaris'); exit;
    }

    if ($act==='sek_resetpass') {
      $hash = md5($DEFAULT_RESET_PASSWORD); // sesuai pola dump
      // reset password di tabel siswa
      $stmt = mysqli_prepare($koneksi, "UPDATE siswa SET siswa_password=? WHERE siswa_id=?");
      mysqli_stmt_bind_param($stmt,'si',$hash,$sid); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
      // sinkron ke akun user yang terhubung (jika ada)
      mysqli_query($koneksi, "UPDATE user SET user_password='".$hash."' WHERE linked_siswa_id=".(int)$sid);
      $_SESSION['flash_success'] = 'Password siswa & akun terhubung berhasil di-reset.';
      header('Location: '.$SELF.'?tab=sekretaris'); exit;
    }
  }

  // ===== Guru Piket =====
  if (in_array($act, ['piket_make','piket_revoke'], true)) {
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid<=0) { header('Location: '.$SELF.'?tab=piket'); exit; }

    if ($act==='piket_make'){
      $stmt = mysqli_prepare($koneksi, "INSERT IGNORE INTO sekolah_staff(sekolah_id,posisi_key,user_id) VALUES(?, 'piket', ?)");
      mysqli_stmt_bind_param($stmt,'ii',$SEKOLAH_ID,$uid); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
      $_SESSION['flash_success']='Ditambahkan sebagai Guru Piket.';
    } else if ($act==='piket_revoke'){
      mysqli_query($koneksi, "DELETE FROM sekolah_staff WHERE posisi_key='piket' AND user_id=".(int)$uid);
      $_SESSION['flash_success']='Dihapus dari Guru Piket.';
    }
    header('Location: '.$SELF.'?tab=piket'); exit;
  }
}
 /* =================== END Non-AJAX POST =================== */

// ====== DATA per TAB (untuk render HTML) ======
// (1) AKUN & ROLE

/* =================== [REVISI] FILTER DI QUERY LIST USER =================== */
$where = [];
$where[] = sql_not_in($koneksi, 'u.user_username', $HIDE_USERNAMES);
$where[] = sql_not_in($koneksi, 'u.user_nama',     $HIDE_NAMES);
$whereSql = 'WHERE '.implode(' AND ', $where);

$users = mysqli_query($koneksi,
  "SELECT u.user_id, u.user_nama, u.user_username, u.user_foto,
          GROUP_CONCAT(r.role_key ORDER BY r.role_key SEPARATOR ', ') AS roles
   FROM user u
   LEFT JOIN user_roles ur ON ur.user_id = u.user_id
   LEFT JOIN roles r ON r.role_id = ur.role_id
   $whereSql
   GROUP BY u.user_id
   ORDER BY u.user_nama"
);
/* ========================================================================== */

// (2) SEKRETARIS (dari data siswa) — pagination & counter ditangani JS berbasis baris tabel.
$kelasId = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
$kelas = mysqli_query($koneksi, "SELECT kelas_id, kelas_nama FROM kelas ORDER BY kelas_nama");
$where = $kelasId ? "WHERE ks.ks_kelas=".(int)$kelasId : '';
$sqlSek = "SELECT s.siswa_id, s.siswa_nama, s.siswa_nis, k.kelas_nama,
                  u.user_id, IF(ur.user_id IS NULL,0,1) AS is_sekretaris
           FROM siswa s
           LEFT JOIN kelas_siswa ks ON ks.ks_siswa=s.siswa_id
           LEFT JOIN kelas k ON k.kelas_id=ks.ks_kelas
           LEFT JOIN user u ON u.linked_siswa_id=s.siswa_id
           LEFT JOIN user_roles ur ON ur.user_id=u.user_id AND ur.role_id=".(int)$ROLE_SEKRETARIS."
           $where
           ORDER BY k.kelas_nama, s.siswa_nama";
$listSek = mysqli_query($koneksi,$sqlSek);

// (3) GURU PIKET (dari user ber-role guru)
$sqlPiket = "SELECT u.user_id, u.user_nama, u.user_username,
                    CASE WHEN ss.user_id IS NULL THEN 0 ELSE 1 END AS is_piket
             FROM user u
             JOIN user_roles ur ON ur.user_id=u.user_id AND ur.role_id=".(int)$ROLE_GURU."
             LEFT JOIN sekolah_staff ss ON ss.user_id=u.user_id AND ss.posisi_key='piket'
             ORDER BY u.user_nama";
$rsPiket = mysqli_query($koneksi,$sqlPiket);

// ====== RENDER HTML ======
include 'header.php';
?>

<style>
  /* ===== POLISH NAV TABS ===== */
  .nav-tabs { border-bottom: none; display: flex; flex-wrap: wrap; gap: .5rem; }
  .nav-tabs>li { float: none; }
  .nav-tabs>li>a {
    border: 1px solid #D7E6FF;
    border-radius: 12px; padding: 10px 14px; font-weight: 600; letter-spacing:.2px;
    background: #F6F9FF; color: #1f2d3d; box-shadow: inset 0 0 0 1px rgba(59,163,255,.06);
    transition: all .2s ease; display:flex; align-items:center; gap:.5rem;
  }
  .nav-tabs>li>a i { opacity:.85; }
  .nav-tabs>li:not(.active)>a:hover { transform: translateY(-1px); background:#EEF5FF; color:#0B57D0; border-color:#98C2FF; box-shadow: 0 6px 16px rgba(17,121,239,.12); }
  .nav-tabs>li.active>a, .nav-tabs>li.active>a:focus, .nav-tabs>li.active>a:hover {
    background: linear-gradient(135deg,#0B57D0,#3BA3FF); color:#fff; box-shadow: 0 10px 24px rgba(11,87,208,.25);
    border-color: transparent;
  }
  @media (max-width:767px){ .nav-tabs>li { width:100%; } .nav-tabs>li>a { border-radius:12px; } }

  /* ===== CONTENT POLISH ===== */
  .box { border-radius: 12px; overflow: hidden; border: none; box-shadow: 0 8px 24px rgba(0,0,0,.06); }
  .box .box-header { background: #0B57D0; color:#fff; border-radius:12px 12px 0 0; padding:12px 16px; display:flex; align-items:center; min-height:52px; justify-content:space-between; }
  .box .box-header .box-title { font-weight:700; margin:0; line-height:1.25; display:flex; align-items:center; gap:8px; }
  .box .box-header .box-actions { margin-left:auto; }

  .tab-toolbar { margin-top: 16px; }
  .badge-flat { background: rgba(11,87,208,.08); border:1px solid rgba(11,87,208,.2); color:#0B57D0; font-weight:600; }
  .table>thead>tr>th { background:#f8fafc; border-bottom: 2px solid #eef2f7; font-weight:700; }
  .table tbody tr:hover { background:#EEF5FF !important; }
  .btn-icon-only{ width:34px; height:30px; display:inline-flex; align-items:center; justify-content:center; padding:0; }

  /* ====== FILTER BAR (Sekretaris) ====== */
  .filters-row { display:flex; flex-wrap:wrap; gap:.75rem; align-items:flex-end; }
  .filters-row .form-inline { display:flex; flex-wrap:wrap; gap:.5rem 1rem; align-items:flex-end; }
  .filters-row label { margin-right:.35rem; font-weight:600; color:#334155; }
  .filters-row .form-control { min-width:160px; }
  @media (max-width:767px){ .filters-row .form-control{ width:100%; } .filters-row label{ display:block; margin-bottom:4px; } }

  /* ====== STATS BAR ====== */
  .statsbar { margin: 12px 0 8px; display:flex; gap:.75rem; flex-wrap:wrap; align-items:stretch; }
  .stat-card {
    display:flex; align-items:center; gap:.7rem;
    padding:12px 14px; border-radius:14px; color:#0b1b32;
    background:linear-gradient(180deg,#ffffff,#f8fbff); border:1px solid #dbeafe;
    box-shadow: 0 6px 18px rgba(11,87,208,.08), inset 0 0 0 1px rgba(59,163,255,.06);
    transition:.2s ease; min-width:220px;
  }
  .stat-card:hover{ transform: translateY(-1px); box-shadow: 0 10px 26px rgba(11,87,208,.12); }
  .stat-icon{ width:36px; height:36px; display:flex; align-items:center; justify-content:center; border-radius:10px; font-size:16px; }
  .stat-val { font-weight:800; font-size:20px; line-height:1; }
  .stat-label { font-size:12px; opacity:.8; margin-top:2px; }
  .badge-pill{ display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; margin-left:8px; }
  .grad-green{ background:linear-gradient(135deg,#dcfce7,#bbf7d0); border-color:#86efac; }
  .grad-amber{ background:linear-gradient(135deg,#fff7ed,#fde68a); border-color:#fcd34d; }
  .grad-blue{ background:linear-gradient(135deg,#eff6ff,#dbeafe); border-color:#93c5fd; }
  .ico-green{ background:#22c55e1a; color:#16a34a; }
  .ico-amber{ background:#f59e0b1a; color:#d97706; }
  .ico-blue{ background:#0ea5e91a; color:#0284c7; }

  /* Pager */
  .pagerbar { margin-top:10px; display:flex; align-items:center; justify-content:space-between; gap:.75rem; flex-wrap:wrap; }
  .pagerbar .pageinfo { font-weight:600; color:#0B57D0; }
  .pagination { margin: 0; }
  .pagination>li.ellipsis>span { cursor:default; background:#fff; border:1px solid #ddd; }

  /* ===== POLISH JUDUL HALAMAN (ikon + badge) ===== */
  .page-title{
    display:flex; align-items:center; gap:12px; margin:0 0 6px;
    color:#0b1b32; font-weight:800; letter-spacing:.2px;
  }
  .title-icon{
    width:42px;height:42px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;
    background:#E6EFFF; box-shadow:inset 0 0 0 1px #D7E6FF;
  }
  .title-icon i{ color:#0B57D0; }
  .title-badge{
    display:inline-flex; align-items:center; gap:6px;
    background:#F6F9FF; color:#1f2d3d; border:1px solid #D7E6FF;
    border-radius:999px; padding:4px 10px; font-weight:700; font-size:12px;
  }

/* ===== Tombol Tambah (gradasi biru kontras) — dipertahankan */
.btn-grad{
  background: linear-gradient(90deg,#0a2a6b,#22c1fd);
  color:#fff; border:0; border-radius:12px; padding:8px 12px;
  box-shadow: 0 10px 22px rgba(34,193,253,.38);
}
.btn-grad:hover{
  filter:brightness(1.06); color:#fff; text-decoration:none;
}

/* ======== [BARU] Tombol Tambah Pengguna warna DeepSkyBlue (#00BFFF) ======== */
.btn-sky{
  background:#00BFFF; border:0; color:#fff !important; font-weight:800;
  border-radius:12px; padding:8px 14px;
  box-shadow:0 12px 26px rgba(0,191,255,.35);
  transition:all .18s ease; letter-spacing:.2px;
}
.btn-sky:hover, .btn-sky:focus{
  color:#fff !important; filter:brightness(1.06);
  box-shadow:0 14px 30px rgba(0,191,255,.45);
}
.btn-sky:active{ transform:translateY(1px); box-shadow:0 10px 20px rgba(0,191,255,.30); }

/* ======== Modal Kelola Role — header & checkbox chic ======== */
#modal-roles .modal-header{
  background: linear-gradient(90deg,#00BFFF,#0EA5E9);
  color:#fff; border-bottom:none;
}
#modal-roles .modal-title{
  font-weight:800; display:flex; align-items:center; gap:8px;
}
#modal-roles .modal-body{ background:#F7FBFF; }

/* checkbox cardy */
.role-check{
  position:relative; display:flex; align-items:center; gap:10px;
  padding:10px 12px; margin:8px 0;
  background:#ffffff; border:1px solid #E4F1FF; border-radius:12px;
  cursor:pointer; transition:.18s ease;
  box-shadow:0 3px 10px rgba(0,0,0,.03);
}
.role-check:hover{ border-color:#BFEAFF; box-shadow:0 8px 20px rgba(0,191,255,.12); }
.role-check input[type=checkbox]{ position:absolute; opacity:0; inset:0; cursor:pointer; }
.role-check .rc-box{
  width:18px; height:18px; border-radius:6px; border:2px solid #9DDFFF;
  display:inline-flex; align-items:center; justify-content:center; flex:0 0 18px; background:#fff;
}
.role-check .rc-box:after{
  content:''; width:10px; height:10px; border-radius:3px; background:#00BFFF; display:none;
}
.role-check .rc-text{ font-weight:700; color:#0b1b32; }
.role-check .rc-key{
  margin-left:auto; font-weight:700; font-size:11px; color:#055160;
  background:#E6F9FF; border:1px solid #BFEAFF; border-radius:999px; padding:3px 8px;
}
.role-check input:checked ~ .rc-box{ border-color:#00BFFF; background:#E9F9FF; }
.role-check input:checked ~ .rc-box:after{ display:block; }
.role-check input:checked ~ .rc-text{ color:#063B4A; }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <h1 class="page-title">
      <span class="title-icon"><i class="fa fa-users"></i></span>
      <span>Manajemen Pengguna</span>
      <span class="title-badge"><i class="fa fa-layer-group"></i> Akun &amp; Role • Guru Piket • Sekretaris (Absensi Kelas)</span>
    </h1>
    <ol class="breadcrumb">
      <li><a href="index.php"><i class="fa fa-dashboard"></i> Dashboard</a></li>
      <li class="active">Manajemen Pengguna</li>
    </ol>
  </section>

  <section class="content">
    <ul class="nav nav-tabs" role="tablist">
      <li role="presentation" class="<?= $tab==='akun'?'active':'' ?>"><a href="<?= $SELF ?>?tab=akun"><i class="fa fa-id-card"></i> Akun & Role</a></li>
      <li role="presentation" class="<?= $tab==='piket'?'active':'' ?>"><a href="<?= $SELF ?>?tab=piket"><i class="fa fa-bell"></i> Guru Piket</a></li>
      <li role="presentation" class="<?= $tab==='sekretaris'?'active':'' ?>"><a href="<?= $SELF ?>?tab=sekretaris"><i class="fa fa-id-badge"></i> Sekretaris (Absensi Kelas)</a></li>
    </ul>


<div class="tab-content tab-toolbar">
  <div role="tabpanel" class="tab-pane active">

  <?php if ($tab==='akun'): ?>
    <!-- ============ TAB: AKUN & ROLE ============ -->
    <div class="box box-primary">
      <div class="box-header">
        <h3 class="box-title"><i class="fa fa-users"></i> Daftar Pengguna</h3>
        <div class="box-actions">
          <a href="user_tambah.php" class="btn btn-sky btn-sm" title="Tambah Pengguna" data-toggle="tooltip">
            <i class="fa fa-user-plus"></i> <span class="hidden-xs" style="font-weight:800">&nbsp;Tambah Pengguna</span>
          </a>
        </div>
      </div>
      <div class="box-body">
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-hover" id="table-datatable">
            <thead>
              <tr>
                <th width="1%">No</th>
                <th>Nama</th>
                <th>Username</th>
                <th>Roles</th>
                <th width="10%">Foto</th>
                <th width="12%">Aksi</th>
              </tr>
            </thead>
            <tbody>
            <?php
              // mapping warna badge per role (poin 2)
              $ROLE_BADGE = [
                'sekretaris'   => 'label label-warning', // oranye
                'administrator'=> 'label label-primary', // biru primary
                'tas'          => 'label label-success', // hijau
                'superadmin'   => 'label label-danger',  // merah (bonus aman)
              ];
            ?>
            <?php $no=1; while($u=mysqli_fetch_assoc($users)): ?>
              <?php
                // cek role superadmin utk sembunyikan tombol hapus (poin 1)
                $rolesArr   = array_filter(array_map('trim', explode(',', $u['roles'] ?? '')));
                $isSuperAdm = in_array('superadmin', $rolesArr, true);
              ?>
              <tr data-user-id="<?= (int)$u['user_id'] ?>">
                <td><?= $no++ ?></td>
                <td><?= _h($u['user_nama']??'') ?></td>
                <td><?= _h($u['user_username']??'') ?></td>
                <td class="col-roles">
                  <?php
                    if (!$rolesArr) {
                      echo '<span class="label label-default">-</span>';
                    } else {
                      foreach($rolesArr as $rk){
                        $cls = $ROLE_BADGE[$rk] ?? 'label label-info';
                        echo '<span class="'.$cls.'" style="margin-right:4px">'. _h($rk) .'</span>';
                      }
                    }
                  ?>
                </td>
                <td class="text-center">
                  <?php if(empty($u['user_foto'])): ?>
                    <img src="../gambar/sistem/user.png" style="width:30px;height:auto">
                  <?php else: ?>
                    <img src="../gambar/user/<?= _h($u['user_foto']) ?>" style="width:30px;height:auto">
                  <?php endif; ?>
                </td>
                <td>
                  <div class="btn-group">
                    <a class="btn btn-warning btn-sm btn-icon-only" href="user_edit.php?id=<?= (int)$u['user_id'] ?>" title="Edit" data-toggle="tooltip">
                      <i class="fa fa-cog"></i>
                    </a>
                    <button class="btn btn-info btn-sm btn-roles btn-icon-only" data-uid="<?= (int)$u['user_id'] ?>" title="Kelola Role" data-toggle="tooltip">
                      <i class="fa fa-users"></i>
                    </button>
                    <?php
                      // [REVISI POIN 1] — tidak tampilkan tombol hapus untuk SEMUA yang punya role superadmin
                      $canDelete = ((int)$u['user_id'] !== 1) && !$isSuperAdm;
                      if ($canDelete):
                    ?>
                      <button class="btn btn-danger btn-sm btn-delete btn-icon-only" data-uid="<?= (int)$u['user_id'] ?>" title="Hapus" data-toggle="tooltip">
                        <i class="fa fa-trash"></i>
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL KELOLA ROLE -->
    <div class="modal fade" id="modal-roles">
      <div class="modal-dialog">
        <div class="modal-content">
          <form id="form-roles" method="post">
            <input type="hidden" name="csrf" value="<?=$CSRF?>">
            <input type="hidden" name="action" value="save_user_roles">
            <input type="hidden" name="uid" id="roles-uid" value="0">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true" style="color:#fff">&times;</span></button>
              <h4 class="modal-title"><i class="fa fa-user-shield"></i> Kelola Role</h4>
            </div>
            <div class="modal-body">
              <div id="roles-list">Memuat...</div>
            </div>
            <div class="modal-footer">
              <button type="submit" class="btn btn-primary">Simpan</button>
              <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- FORM DELETE (POST + CSRF) -->
    <form id="form-delete" method="post" style="display:none">
      <input type="hidden" name="csrf" value="<?=$CSRF?>">
      <input type="hidden" name="action" value="delete_user">
      <input type="hidden" name="uid" id="del-uid" value="0">
    </form>

  <?php elseif ($tab==='sekretaris'): ?>
    <!-- ============ TAB: SEKRETARIS (ABSENSI KELAS) ============ -->
    <div class="box">
      <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-id-badge"></i> Sekretaris (Absensi Kelas)</h3>
      </div>
      <div class="box-body">
        <div class="filters-row">
          <form method="get" class="form-inline" action="<?= $SELF ?>">
            <input type="hidden" name="tab" value="sekretaris">
            <div class="form-group">
              <label>Pilih Kelas</label>
              <select name="kelas_id" class="form-control" onchange="this.form.submit()">
                <option value="0">Semua Kelas</option>
                <?php mysqli_data_seek($kelas,0); while($k = mysqli_fetch_assoc($kelas)): ?>
                  <option value="<?= (int)$k['kelas_id'] ?>" <?= $kelasId===$k['kelas_id']?'selected':'' ?>><?= _h($k['kelas_nama']) ?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Status</label>
              <select id="sekStat" class="form-control">
                <option value="all">Semua Status</option>
                <option value="1">Sekretaris</option>
                <option value="0">Non-Sekretaris</option>
              </select>
            </div>
          </form>
          <div class="input-group" style="max-width:340px; margin-left:auto">
            <span class="input-group-addon"><i class="fa fa-search"></i></span>
            <input id="qSek" type="text" class="form-control" placeholder="Cari nama/NIS...">
          </div>
        </div>

        <!-- ====== STATS ====== -->
        <div class="statsbar" id="sekInfo">
          <!-- Diisi via JS -->
        </div>

        <div class="table-responsive" style="margin-top:8px">
          <table class="table table-bordered table-striped table-hover" id="tblSek">
            <thead>
              <tr>
                <th style="width:48px">No</th>
                <th>Nama</th>
                <th>NIS</th>
                <th>Kelas</th>
                <th>Status</th>
                <th style="width:360px">Aksi</th>
              </tr>
            </thead>
            <tbody>
            <?php $no=1; while($row=mysqli_fetch_assoc($listSek)): ?>
              <tr data-siswa="<?= (int)$row['siswa_id'] ?>" data-issek="<?= (int)$row['is_sekretaris'] ?>">
                <td><?= $no++ ?></td>
                <td><?= _h($row['siswa_nama']) ?></td>
                <td><code><?= _h($row['siswa_nis']) ?></code></td>
                <td><?= _h($row['kelas_nama'] ?? '-') ?></td>
                <td>
                  <?php if ((int)$row['is_sekretaris']===1): ?>
                    <span class="chip chip-success"><i class="fa fa-check-circle"></i> Sekretaris</span>
                  <?php else: ?>
                    <span class="chip chip-danger"><i class="fa fa-minus-circle"></i> Non-Sekretaris</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="btn-group">
                    <?php if ((int)$row['is_sekretaris']===1): ?>
                      <form method="post" onsubmit="return confirm('Cabut status sekretaris untuk <?= _h($row['siswa_nama']) ?>?')" style="display:inline">
                        <input type="hidden" name="csrf" value="<?=$CSRF?>">
                        <input type="hidden" name="act" value="sek_revoke">
                        <input type="hidden" name="siswa_id" value="<?= (int)$row['siswa_id'] ?>">
                        <button class="btn btn-warning" title="Cabut Sekretaris" data-toggle="tooltip"><i class="fa fa-unlink"></i></button>
                      </form>
                    <?php else: ?>
                      <form method="post" onsubmit="return confirm('Jadikan <?= _h($row['siswa_nama']) ?> sebagai sekretaris?')" style="display:inline">
                        <input type="hidden" name="csrf" value="<?=$CSRF?>">
                        <input type="hidden" name="act" value="sek_make">
                        <input type="hidden" name="siswa_id" value="<?= (int)$row['siswa_id'] ?>">
                        <button class="btn btn-primary" title="Jadikan Sekretaris" data-toggle="tooltip"><i class="fa fa-user-plus"></i></button>
                      </form>
                    <?php endif; ?>

                    <form method="post" onsubmit="return confirm('Reset password akun siswa + sekretaris (jika terhubung) ke default?')" style="display:inline">
                      <input type="hidden" name="csrf" value="<?=$CSRF?>">
                      <input type="hidden" name="act" value="sek_resetpass">
                      <input type="hidden" name="siswa_id" value="<?= (int)$row['siswa_id'] ?>">
                      <button class="btn btn-danger" title="Reset = Password Siswa" data-toggle="tooltip"><i class="fa fa-refresh"></i></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>

        <div class="pagerbar">
          <div class="pageinfo" id="sekPageInfo">&nbsp;</div>
          <ul class="pagination pagination-sm" id="sekPager"></ul>
        </div>

        <p class="help-block"><i class="fa fa-info-circle"></i> Username akun sekretaris = NIS. Password default reset = <code><?= _h($DEFAULT_RESET_PASSWORD) ?></code>.</p>
      </div>
    </div>

  <?php else: ?>
    <!-- ============ TAB: GURU PIKET ============ -->
    <div class="box">
      <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-bell"></i> Guru Piket</h3>
      </div>
      <div class="box-body">
        <div class="row" style="margin-bottom:10px">
          <div class="col-sm-6">
            <span class="help-block" style="margin:0"><i class="fa fa-info-circle"></i> Tetapkan guru bertugas piket. Penjadwalan di modul terpisah.</span>
          </div>
          <div class="col-sm-6">
            <div class="input-group" style="max-width:340px; float:right">
              <span class="input-group-addon"><i class="fa fa-search"></i></span>
              <input id="qPiket" type="text" class="form-control" placeholder="Cari nama guru...">
            </div>
          </div>
        </div>

        <div class="statsbar" id="piketInfo"></div>

        <div class="table-responsive">
          <table class="table table-bordered table-striped table-hover" id="tblPiket">
            <thead>
              <tr>
                <th style="width:60px">No</th>
                <th>Nama Guru</th>
                <th>Username</th>
                <th>Status</th>
                <th style="width:260px">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php $no=1; while($g = mysqli_fetch_assoc($rsPiket)): ?>
              <tr data-user="<?= (int)$g['user_id'] ?>" data-ispiket="<?= (int)$g['is_piket'] ?>">
                <td><?= $no++ ?></td>
                <td><?= _h($g['user_nama']) ?></td>
                <td><code><?= _h($g['user_username']) ?></code></td>
                <td>
                  <?php if ((int)$g['is_piket']===1): ?>
                    <span class="chip chip-info"><i class="fa fa-check-circle"></i> Aktif Piket</span>
                  <?php else: ?>
                    <span class="chip chip-danger"><i class="fa fa-minus-circle"></i> Non-Piket</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ((int)$g['is_piket']===1): ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('Hapus dari Guru Piket?')">
                      <input type="hidden" name="csrf" value="<?=$CSRF?>">
                      <input type="hidden" name="act" value="piket_revoke">
                      <input type="hidden" name="user_id" value="<?= (int)$g['user_id'] ?>">
                      <button class="btn btn-warning" title="Hapus dari Piket" data-toggle="tooltip"><i class="fa fa-unlink"></i></button>
                    </form>
                  <?php else: ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('Tambahkan sebagai Guru Piket?')">
                      <input type="hidden" name="csrf" value="<?=$CSRF?>">
                      <input type="hidden" name="act" value="piket_make">
                      <input type="hidden" name="user_id" value="<?= (int)$g['user_id'] ?>">
                      <button class="btn btn-primary" title="Tambahkan sebagai Piket" data-toggle="tooltip"><i class="fa fa-user-plus"></i></button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>

        <div class="pagerbar">
          <div class="pageinfo" id="piketPageInfo">&nbsp;</div>
          <ul class="pagination pagination-sm" id="piketPager"></ul>
        </div>

      </div>
    </div>
  <?php endif; ?>

  </div>
</div>


  </section>
</div>

<!-- ====== JS: Kelola Role (AJAX) + Helpers + Pagination Sekretaris & Piket ====== -->

<script>
(function(){
  function enableTooltips(){ if (window.jQuery && $.fn.tooltip) $('body').tooltip({ selector: '[data-toggle="tooltip"]' }); }
  document.addEventListener('DOMContentLoaded', enableTooltips);
})();

// Inisialisasi DataTables (tabel: Akun & Role, Sekretaris, Piket)
document.addEventListener('DOMContentLoaded', function() {
  if (window.jQuery && $.fn.DataTable) {
    if ($('#table-datatable').length) {
      $('#table-datatable').DataTable();
    }
    if ($('#tblSek').length) {
      var dtSek = $('#tblSek').DataTable({ paging:false, searching:false, info:false, lengthChange:false, autoWidth:false, order:[[1,'asc']] });
      $('#tblSek').on('order.dt', function(){ if(window.sekPager){ window.sekPager.refreshRows(); window.sekPager.applyFilter(); } });
    }
    if ($('#tblPiket').length) {
      var dtPik = $('#tblPiket').DataTable({ paging:false, searching:false, info:false, lengthChange:false, autoWidth:false, order:[[1,'asc']] });
      $('#tblPiket').on('order.dt', function(){ if(window.piketPager){ window.piketPager.refreshRows(); window.piketPager.applyFilter(); } });
    }
  }
});

// Modal Kelola Role (AJAX)
$(document).on('click', '.btn-roles', function() {
  const uid = $(this).data('uid');
  $('#roles-uid').val(uid);
  $('#roles-list').html('Memuat...');
  $.post('<?= $SELF ?>', { action:'get_user_roles', uid:uid, csrf:'<?=$CSRF?>' }, function(res) {
    if (!res || !res.ok) { $('#roles-list').html('Gagal memuat'); return; }
    let html = '';
    res.roles.forEach(function(r) {
      const checked = res.mine.includes(parseInt(r.role_id,10)) ? 'checked' : '';
      html += ''
        + '<label class="role-check">'
        +   '<input type="checkbox" name="roles[]" value="'+r.role_id+'" '+checked+'>'
        +   '<span class="rc-box"></span>'
        +   '<span class="rc-text">'+r.role_name+'</span>'
        +   '<span class="rc-key">'+r.role_key+'</span>'
        + '</label>';
    });
    $('#roles-list').html(html);
    if ($.fn.modal) { $('#modal-roles').modal('show'); }
    else { alert('Bootstrap modal JS belum ter-load'); }
  }, 'json').fail(function(xhr){
    console.error('AJAX error:', xhr.responseText);
    $('#roles-list').html('Gagal memuat (lihat Console)');
  });
});

$('#form-roles').on('submit', function(e) {
  e.preventDefault();
  const fd = $(this).serialize();
  $.post('<?= $SELF ?>', fd, function(res) {
    if (res && res.ok) {
      const uid = $('#roles-uid').val();
      $.post('<?= $SELF ?>', { action:'get_user_roles', uid:uid, csrf:'<?=$CSRF?>' }, function(r2) {
        if (r2 && r2.ok) {
          const row = $('tr[data-user-id="'+uid+'"]').find('.col-roles');

          // mapping warna badge per role — HARUS sama dengan PHP (poin 2)
          function roleBadgeClass(key){
            switch(key){
              case 'sekretaris':    return 'label-warning';
              case 'administrator': return 'label-primary';
              case 'tas':           return 'label-success';
              case 'superadmin':    return 'label-danger';
              default:              return 'label-info';
            }
          }

          let badge = '';
          const mine = new Set(r2.mine.map(function(x){ return parseInt(x,10); }));
          r2.roles.forEach(function(r){
            if (mine.has(parseInt(r.role_id,10))) {
              badge += '<span class="label '+roleBadgeClass(r.role_key)+'" style="margin-right:4px">'+r.role_key+'</span>';
            }
          });
          row.html(badge || '<span class="label label-default">-</span>');
        }
        if ($.fn.modal) $('#modal-roles').modal('hide');
      }, 'json');
    } else alert(res.msg || 'Gagal menyimpan role');
  }, 'json').fail(function(xhr){ console.error('AJAX error:', xhr.responseText); alert('Gagal menyimpan (lihat Console)'); });
});

// Hapus user (POST + CSRF)
$(document).on('click', '.btn-delete', function(){
  const uid = $(this).data('uid');
  if(!confirm('Hapus user ini? Data terhubung akan ikut terhapus.')) return;
  $('#del-uid').val(uid);
  $.post('<?= $SELF ?>', $('#form-delete').serialize(), function(res){
    if(res && res.ok){ $('tr[data-user-id="'+uid+'"]').fadeOut(200, function(){ $(this).remove(); }); }
    else alert(res.msg || 'Gagal menghapus user');
  }, 'json').fail(function(xhr){ console.error('AJAX error:', xhr.responseText); alert('Gagal menghapus (lihat Console)'); });
});

/* ================= Pagination + Counter Utility ================= */
function Pager(cfg){
  this.table = document.querySelector(cfg.table);
  this.pager = document.querySelector(cfg.pager);
  this.info  = document.querySelector(cfg.info);
  this.pageInfoEl = cfg.pageInfo ? document.querySelector(cfg.pageInfo) : null;
  this.search= cfg.search ? document.querySelector(cfg.search) : null;
  this.statusSel = cfg.statusSel ? document.querySelector(cfg.statusSel) : null;
  this.pageSize = cfg.pageSize || 10;
  this.mode = cfg.mode || 'sekretaris';
  this.matchIdx = [];
  this.curr = 1;
  this.rows = [];
  this.refreshRows();
}
Pager.prototype.refreshRows = function(){
  if (!this.table) return;
  this.rows = Array.from(this.table.tBodies[0].rows);
  this.rows.forEach(function(tr){ tr._q = tr.innerText.toLowerCase(); });
};
Pager.prototype.calcCounts = function(){
  const idsActive = new Set();
  let matched = 0;
  for (let i of this.matchIdx){ matched++; const tr=this.rows[i];
    if (this.mode==='sekretaris' && tr.dataset.issek==='1') idsActive.add(tr.dataset.siswa);
    if (this.mode==='piket'       && tr.dataset.ispiket==='1') idsActive.add(tr.dataset.user);
  }
  const active = idsActive.size;
  const nonActive = Math.max(0, matched - active);
  const pct = matched ? Math.round((active / matched) * 100) : 0;

  if (this.info) {
    const titleA = (this.mode==='sekretaris') ? 'Sekretaris Aktif' : 'Guru Piket Aktif';
    const titleB = (this.mode==='sekretaris') ? 'Tidak Aktif' : 'Non-Piket';
    const titleC = (this.mode==='sekretaris') ? 'Total Siswa Tersaring' : 'Total Guru Tersaring';
    this.info.innerHTML =
      '<div class="stat-card grad-green">' +
        '<div class="stat-icon ico-green"><i class="fa fa-check"></i></div>' +
        '<div><div class="stat-val">'+active+'<span class="badge-pill" style="background:#16a34a; color:#fff">'+pct+'%</span></div>' +
        '<div class="stat-label">'+titleA+'</div></div>' +
      '</div>' +
      '<div class="stat-card grad-amber">' +
        '<div class="stat-icon ico-amber"><i class="fa fa-minus-circle"></i></div>' +
        '<div><div class="stat-val">'+nonActive+'</div><div class="stat-label">'+titleB+'</div></div>' +
      '</div>' +
      '<div class="stat-card grad-blue">' +
        '<div class="stat-icon ico-blue"><i class="fa fa-users"></i></div>' +
        '<div><div class="stat-val">'+matched+'</div><div class="stat-label">'+titleC+'</div></div>' +
      '</div>';
  }
};
Pager.prototype.applyFilter = function(){
  this.refreshRows();
  const q = (this.search && this.search.value || '').toLowerCase();
  const st = (this.statusSel && this.statusSel.value) ? this.statusSel.value : 'all';
  this.matchIdx = [];
  for (let i=0;i<this.rows.length;i++){
    const tr = this.rows[i];
    const okSearch = (q==='') || tr._q.indexOf(q) > -1;
    let okStatus = true;
    if (this.mode==='sekretaris' && st!=='all') okStatus = tr.dataset.issek === st;
    const ok = okSearch && okStatus;
    tr.style.display = ok ? '' : 'none';
    if (ok) this.matchIdx.push(i);
  }
  this.calcCounts();
  this.goto(1);
};
Pager.prototype.renderPager = function(){
  if (!this.pager) return;
  const total = this.matchIdx.length;
  const pages = Math.max(1, Math.ceil(total / this.pageSize));
  const self = this;

  function li(p, label, disabled, active, isGap){
    const cls = (disabled?'disabled ':'') + (active?'active ':'') + (isGap?'ellipsis ':'');
    if (isGap) return `<li class="${cls}"><span>${label}</span></li>`;
    return `<li class="${cls}"><a href="#" data-page="${p}">${label}</a></li>`;
  }

  let html = '';
  html += li(Math.max(1,this.curr-1),'&laquo;', this.curr===1, false);
  html += li(1, 1, false, this.curr===1);
  if (this.curr > 3) { html += li(null, '…', true, false, true); }
  const startMid = Math.max(2, this.curr - 1);
  const endMid   = Math.min(pages - 1, this.curr + 1);
  for(let p=startMid; p<=endMid; p++){
    if (p!==1 && p!==pages) html += li(p, p, false, p===this.curr);
  }
  if (this.curr < pages - 2) { html += li(null, '…', true, false, true); }
  if (pages > 1) html += li(pages, pages, false, this.curr===pages);
  html += li(Math.min(pages,this.curr+1),'&raquo;', this.curr===pages, false);

  this.pager.innerHTML = html;
  Array.from(this.pager.querySelectorAll('a[data-page]')).forEach(function(a){
    a.addEventListener('click', function(ev){ ev.preventDefault(); self.goto(parseInt(this.dataset.page,10)); });
  });
};
Pager.prototype.goto = function(p){
  const total = this.matchIdx.length;
  const pages = Math.max(1, Math.ceil(total / this.pageSize));
  this.curr = Math.min(Math.max(1,p), pages);
  for (let i=0;i<this.rows.length;i++) this.rows[i].style.display = 'none';
  const start = (this.curr-1)*this.pageSize;
  const end   = Math.min(total, start + this.pageSize);
  for (let k=start;k<end;k++) this.rows[this.matchIdx[k]].style.display = '';
  if (this.pageInfoEl){
    const s = total===0 ? 0 : (start+1);
    const e = end;
    this.pageInfoEl.textContent = `Menampilkan ${s}–${e} dari ${total} data`;
  }
  this.renderPager();
};

// INIT Sekretaris & Piket pagination (default 10 per page)
document.addEventListener('DOMContentLoaded', function(){
  // Sekretaris
  if (document.querySelector('#tblSek')){
    window.sekPager = new Pager({ table:'#tblSek', pager:'#sekPager', info:'#sekInfo', pageInfo:'#sekPageInfo', search:'#qSek', statusSel:'#sekStat', pageSize:10, mode:'sekretaris' });
    const inp = document.querySelector('#qSek');
    if (inp) inp.addEventListener('input', function(){ window.sekPager.applyFilter(); });
    const stsel = document.querySelector('#sekStat');
    if (stsel) stsel.addEventListener('change', function(){ window.sekPager.applyFilter(); });
    window.sekPager.applyFilter();
  }
  // Piket
  if (document.querySelector('#tblPiket')){
    window.piketPager = new Pager({ table:'#tblPiket', pager:'#piketPager', info:'#piketInfo', pageInfo:'#piketPageInfo', search:'#qPiket', pageSize:10, mode:'piket' });
    const inp2 = document.querySelector('#qPiket');
    if (inp2) inp2.addEventListener('input', function(){ window.piketPager.applyFilter(); });
    window.piketPager.applyFilter();
  }
});
</script>

<?php include 'footer.php'; ?>
