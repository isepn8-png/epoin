<?php
// ====== WAJIB: jalankan sebelum output HTML ======
if (function_exists('session_status')) {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    @ini_set('session.use_only_cookies', 1);
    if (!headers_sent()) { @session_start(); }
  }
} else {
  if (session_id() === '' || !isset($_SESSION)) {
    if (!headers_sent()) { @session_start(); }
  }
}

// Pastikan koneksi tersedia (sekali saja)
if (!isset($koneksi)) { @include_once __DIR__ . '/../koneksi.php'; }

// Auth siswa
if (!isset($_SESSION['level']) || $_SESSION['level'] !== 'siswa') {
  header("Location: ../index.php?alert=belum_login");
  exit;
}

// ====== Util: aman dari redeclare ======
if (!function_exists('table_exists')) {
  function table_exists($koneksi, $name){
    $q = @mysqli_query($koneksi,"SHOW TABLES LIKE '".mysqli_real_escape_string($koneksi,$name)."'");
    return $q && mysqli_num_rows($q)>0;
  }
}
if (!function_exists('count_table')) {
  function count_table($koneksi, $table){
    $res = @mysqli_query($koneksi, "SELECT COUNT(*) AS jml FROM `".$table."`");
    if($res){ $row = mysqli_fetch_assoc($res); return (int)$row['jml']; }
    return 0;
  }
}

// Data untuk sidebar
$id_user = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
$profil = array();
if ($id_user) {
  $r = @mysqli_query($koneksi, "SELECT * FROM siswa WHERE siswa_id='".intval($id_user)."'");
  $profil = $r ? (@mysqli_fetch_assoc($r) ?: array()) : array();
}

$jumlah_jenis_prestasi    = table_exists($koneksi,'prestasi')    ? count_table($koneksi,'prestasi')    : 0;
$jumlah_jenis_pelanggaran = table_exists($koneksi,'pelanggaran') ? count_table($koneksi,'pelanggaran') : 0;

// Current file untuk highlight menu
$CURRENT = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if($CURRENT === '' || $CURRENT === '/') $CURRENT = 'index.php';

// ====== BRAND TERPADU (SAMA DENGAN ADMIN) ======
require_once __DIR__ . '/../includes/theme_brand.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

  <?php $PAGE_TITLE = isset($PAGE_TITLE)&&$PAGE_TITLE!=='' ? $PAGE_TITLE : 'Siswa'; ?>
  <title><?php echo build_full_title($PAGE_TITLE); ?></title>

  <!-- Vendor CSS -->
  <link rel="stylesheet" href="../assets/bower_components/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/bower_components/font-awesome/css/font-awesome.min.css"> <!-- FA4 (kompat) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"> <!-- FA5 modern -->
  <link rel="stylesheet" href="../assets/bower_components/Ionicons/css/ionicons.min.css">
  <link rel="stylesheet" href="../assets/dist/css/AdminLTE.min.css">
  <link rel="stylesheet" href="../assets/bower_components/datatables.net-bs/css/dataTables.bootstrap.min.css">
  <link rel="stylesheet" href="../assets/dist/css/skins/_all-skins.min.css">
  <link rel="stylesheet" href="../assets/bower_components/morris.js/morris.css">
  <link rel="stylesheet" href="../assets/bower_components/jvectormap/jquery-jvectormap.css">
  <link rel="stylesheet" href="../assets/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css">
  <link rel="stylesheet" href="../assets/bower_components/bootstrap-daterangepicker/daterangepicker.css">
  <link rel="stylesheet" href="../assets/plugins/bootstrap-wysihtml5/bootstrap3-wysihtml5.min.css">

  <!-- Font: Inter + Source Sans Pro + (admin brand fonts) -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&family=Nunito:wght@600;700&display=swap" rel="stylesheet">

  <style>
    /* ====== Typography & base ====== */
    html, body { font-family: Inter, "Source Sans Pro", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
    .sidebar .header { letter-spacing:.3px; font-weight:700; color:#a5b4fc; padding-top:12px; }

    /* ====== Samakan warna navbar/logo dgn Admin ====== */
    .main-header .navbar, .main-header .logo{ background:#3f4e61 !important; border-bottom:1px solid #304052 !important; }
    .main-header .logo{ text-align:left !important; padding-left:10px; }
    .main-header .navbar .sidebar-toggle{ color:#fff !important; }
    .main-header .navbar .sidebar-toggle:hover{ background:rgba(255,255,255,.06) !important; }

    /* ====== Brand 2 baris (persis admin) ====== */
    .main-header .logo{ display:flex !important; align-items:center !important; justify-content:flex-start !important; height:56px !important; padding-left:12px !important; line-height:1 !important; overflow:hidden; }
    .main-header .logo .logo-lg{ display:flex; }
    .main-header .logo .logo-lg.brand2{ display:flex; align-items:center; gap:10px; white-space:normal; }
    .main-header .logo .brand-text{ display:block; line-height:1.05; }
    .main-header .logo .logo-mini{ display:none; }
    .main-header .logo .brand-mini{ width:28px; height:28px; object-fit:contain; display:block; border-radius:6px; }
    .main-header .logo .brand-logo{ width:26px; height:26px; object-fit:contain; border-radius:6px; background:transparent; padding:0; box-shadow:none; }
    .main-header .logo .brand-title{
      display:block; font-weight:700; font-size:14px; letter-spacing:.1px; color:#ffffff;
      font-family:'Poppins','Nunito',system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;
    }
    .main-header .logo .brand-subtitle{
      display:block; font-size:11.5px; font-weight:700; color:#cbe6ff; margin-top:1px;
      font-family:'Nunito','Poppins',system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;
    }
    @media (max-width:480px){
      .main-header .logo{ height:52px !important; padding-left:10px !important; }
      .main-header .logo .brand-logo{ width:24px; height:24px; }
      .main-header .logo .brand-title{ font-size:13px; }
      .main-header .logo .brand-subtitle{ font-size:11px; }
    }
    .sidebar-mini.sidebar-collapse .main-header .logo{ justify-content:center !important; align-items:center !important; padding-left:1 !important; }
    .sidebar-mini.sidebar-collapse .main-header .logo .logo-mini{ display:block !important; }
    .sidebar-mini.sidebar-collapse .main-header .logo .logo-lg{ display:none !important; }

    /* ====== Sidebar & menu polish (tetap) ====== */
    .main-sidebar, .left-side{ background:#111827; }
    .sidebar { overflow-x:hidden; backface-visibility:hidden; }
    .sidebar-menu > li { position:relative; z-index:0; }
    .sidebar-menu > li > a{
      display:flex; align-items:center; gap:10px; font-weight:600; letter-spacing:.2px;
      padding-top:11px; padding-bottom:11px; position:relative; z-index:0;
    }
    .sidebar-menu > li > a .menu-ic{ width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; background:rgba(255,255,255,.08); }
    .menu-text{ line-height:1.2; }
    .sidebar-menu .treeview-menu > li > a{ display:flex; align-items:center; gap:10px; padding-top:9px; padding-bottom:9px; padding-left:38px; font-weight:500; }
    .sidebar-menu .treeview-menu > li > a .menu-ic{ background:transparent; width:18px; }
    .sidebar-menu .label { border-radius:12px; padding:3px 7px; }
    .skin-green .sidebar-menu > li:hover > a{ background:rgba(255,255,255,.06); }
    .skin-green .sidebar-menu > li.active > a{
      background:linear-gradient(90deg,#10b981,#0ea5e9); color:#fff;
      box-shadow: inset 0 -1px 0 rgba(255,255,255,.15);
    }
    .skin-green .sidebar-menu > li.active > a .menu-ic{ background:rgba(255,255,255,.18); }

    .sidebar-menu .treeview > a .fa-angle-left{ margin-top:3px; transition: transform .18s ease; }
    .sidebar-menu .treeview.menu-open > a .fa-angle-left{ transform: rotate(-90deg); }

    .user-panel>.image>img{ border:2px solid #fff; box-shadow:0 4px 10px rgba(0,0,0,.08); }
    .user-panel>.info>p{ font-weight:700; margin-bottom:4px; }
    .user-panel .text-success{ color:#34d399 !important; }

    /* ====== MODE COLLAPSE (ikon tetap tampil) ====== */
    .sidebar-mini.sidebar-collapse .main-sidebar .user-panel{ display:none !important; }
    .sidebar-mini.sidebar-collapse .sidebar .header{ display:none !important; }
    .sidebar-mini.sidebar-collapse .sidebar-menu > li > a{ justify-content:center; padding-left:14px; padding-right:14px; }
    .sidebar-mini.sidebar-collapse .sidebar-menu > li > a .menu-text{ display:none !important; }
    .sidebar-mini.sidebar-collapse .sidebar-menu > li > a > .menu-ic{
      display:inline-flex !important; width:22px !important; height:22px !important; background:rgba(255,255,255,.12);
      align-items:center; justify-content:center;
    }
    .sidebar-mini.sidebar-collapse .sidebar-menu > li > a > .menu-ic > i{ font-size:16px; line-height:1; }
    .sidebar-mini.sidebar-collapse .sidebar-menu .fa-angle-left{ display:none !important; }
    .sidebar-mini.sidebar-collapse .main-header .logo{ width:50px; }

    /* ==== Submenu latar & separator (rapih) ==== */
    .sidebar-menu .treeview-menu{
      overflow:hidden; background: rgba(255,255,255,.03);
      padding: 6px 0 8px 0; margin: 0;
      border-top: 1px solid rgba(255,255,255,.06);
      border-bottom: 1px solid rgba(0,0,0,.25);
    }
    .sidebar-menu .treeview.menu-open > a{ box-shadow: inset 0 -1px 0 rgba(0,0,0,.2); }
    .sidebar-mini.sidebar-collapse .sidebar-menu .treeview-menu{ padding-left:0; }
    @media (max-width: 768px){
      .sidebar-mini.sidebar-collapse .main-sidebar,
      .sidebar-mini.sidebar-collapse .left-side{ transition: none; }
    }
  </style>

  <style>
  /* Tombol logout elegan — MERAH */
  .navbar-custom-menu .nav-logout .nav-logout-btn{
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 12px; border-radius:999px; font-weight:800;
    color:#fff !important; line-height:1; border:0;
    background:linear-gradient(135deg,#ef4444,#dc2626);
    box-shadow:0 8px 18px rgba(239,68,68,.28);
    transition:transform .12s ease, box-shadow .12s ease, background .18s ease;
    margin:8px 10px 8px 0;
  }
  .navbar-custom-menu .nav-logout .nav-logout-btn:hover{
    transform:translateY(-1px);
    box-shadow:0 12px 26px rgba(185,28,28,.35);
    background:linear-gradient(135deg,#b91c1c,#991b1b);
  }
  .nav-logout .ic-wrap{ position:relative; width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; }
  .nav-logout .ic-wrap i{ font-size:16px; }
  .nav-logout .ic-wave{
    position:absolute; inset:0; border-radius:50%;
    background:radial-gradient(closest-side, rgba(255,255,255,.7), transparent);
    transform: scale(0); opacity:0; pointer-events:none;
    animation: none;
  }
  .nav-logout-btn:hover .ic-wave{ animation: wave .9s ease-out; }
  @keyframes wave{
    0%{ transform:scale(.35); opacity:.55; }
    100%{ transform:scale(1.4); opacity:0; }
  }
  @media (max-width: 480px){
    .navbar-custom-menu .nav-logout .nav-logout-btn{ padding:8px 10px; }
    .navbar-custom-menu .nav-logout .nav-logout-btn .hidden-xs{ display:none; }
  }
  </style>

  <!-- ==== FIX garis putih kiri sidebar ==== -->
  <style>
    html, body, .wrapper { background:#111827; }
    html, body { overflow-x:hidden; }
    .navbar-custom-menu { padding-right:8px; }
    .navbar-custom-menu .nav-logout .nav-logout-btn { margin-right:8px; }
    .main-sidebar::before{
      content:""; position:fixed; left:0; top:0; bottom:0; width:1px; background:#111827; z-index:1035;
    }
    .sidebar-mini.sidebar-collapse .main-sidebar::before{ background:#111827; }
  </style>

  <!-- ==== Kecilkan font submenu "Kategori Poin" ==== -->
  <style>
    .sidebar-menu .treeview > a[title="Kategori Poin"] + .treeview-menu > li > a{
      font-size:12.5px; padding-top:7px; padding-bottom:7px;
    }
    .sidebar-menu .treeview > a[title="Kategori Poin"] + .treeview-menu > li > a .menu-ic{ width:16px; }
    .sidebar-menu .treeview > a[title="Kategori Poin"] + .treeview-menu > li > a .label{ transform:scale(.92); transform-origin:right center; }
  </style>

  <!-- ===================== [REVISI KHUSUS] Center modal "Jenjang Pembinaan Peserta Didik" ===================== -->
  <style>
    /* Utama: flex center + fallback inline-block, serta anti "nempel kiri" */
    .modal.modal-centered{
      position: fixed; /* pastikan mengikuti viewport */
      top:0; right:0; bottom:0; left:0;
      display:flex !important;
      align-items:center;
      justify-content:center;
      padding:10px;
      text-align:center;             /* fallback untuk inline-block trick */
    }
    .modal.modal-centered:before{    /* fallback vertical centering (old browsers) */
      content:"";
      display:inline-block;
      height:100%;
      vertical-align:middle;
    }
    .modal.modal-centered .modal-dialog{
      margin:0 auto !important;      /* cegah margin diset nol di tema lain */
      float:none !important;         /* pastikan tidak float kiri */
      display:inline-block;          /* untuk fallback :before */
      vertical-align:middle;         /* untuk fallback :before */
      text-align:left;               /* konten normal */
      position:relative;             /* reset posisi */
      left:auto; right:auto; top:auto; bottom:auto;
      max-width:980px;               /* batas desktop */
      width:auto;
      transform:none !important;     /* hilangkan translate bawaan */
    }
    .modal.modal-centered .modal-content{
      max-height:calc(100vh - 20px);
      display:flex; flex-direction:column;
    }
    .modal.modal-centered .modal-body{ overflow:auto; }

    /* Jika konten lebih tinggi dari viewport → rapikan align-top */
    .modal.modal-centered.modal-overflow{
      align-items:flex-start !important;
    }
    .modal.modal-centered.modal-overflow:before{ display:none; }
    .modal.modal-centered.modal-overflow .modal-dialog{
      margin:10px auto !important;
    }

    /* Mobile */
    @media (max-width: 576px){
      .modal.modal-centered .modal-dialog{ max-width:100%; margin:0 auto !important; }
      .modal.modal-centered .modal-content{ border-radius:10px; }
    }
  </style>
  <!-- ================== /END REVISI KHUSUS ================== -->


  <style>
  /* ===== FIX: cegah body “menyusut” saat SweetAlert2 muncul ===== */
  body.swal2-shown { padding-right: 0 !important; }

  /* Elemen fixed yang biasanya ikut dikompensasi – netralisir juga */
  .swal2-shown .navbar-fixed-top,
  .swal2-shown .navbar-fixed-bottom,
  .swal2-shown .main-header,
  .swal2-shown .main-footer {
    padding-right: 0 !important;
  }

  /* AdminLTE terkadang memberi margin-right saat kompensasi – nolkan */
  .swal2-shown .content-wrapper,
  .swal2-shown .right-side {
    margin-right: 0 !important;
  }

  /* (opsional) Hindari bounce/scroll chaining saat dialog terbuka */
  .swal2-container { overscroll-behavior: contain; }
</style>


</head>

<body class="hold-transition skin-green sidebar-mini">
<div class="wrapper">

  <!-- ===== Topbar ===== -->
  <header class="main-header" role="banner">
    <?php echo render_theme_brand($THEME_BRAND); ?>

    <nav class="navbar navbar-static-top" role="navigation" aria-label="Bar navigasi">
      <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button" aria-label="Buka menu">
        <span class="sr-only">Toggle navigation</span>
      </a>
      <div class="navbar-custom-menu">
        <ul class="nav navbar-nav">
          <li class="nav-logout">
            <a href="logout.php"
               class="nav-logout-btn"
               id="btnLogout"
               data-logout
               title="Keluar dari aplikasi"
               aria-label="Keluar">
              <span class="ic-wrap" aria-hidden="true">
                <i class="fas fa-power-off"></i>
                <span class="ic-wave"></span>
              </span>
              <span class="hidden-xs">Keluar</span>
            </a>
          </li>
        </ul>
      </div>
    </nav>
  </header>

  <!-- ===== Sidebar ===== -->
  <aside class="main-sidebar">
    <section class="sidebar">

      <!-- User -->
      <?php
        $avatar = (!empty($profil['siswa_foto']))
                  ? "../gambar/siswa/".htmlspecialchars($profil['siswa_foto'], ENT_QUOTES, 'UTF-8')
                  : "../gambar/sistem/user.png";
      ?>
      <div class="user-panel">
        <div class="pull-left image">
          <img src="<?= $avatar ?>" class="img-circle" style="height:45px;width:45px;object-fit:cover" alt="User">
        </div>
        <div class="pull-left info">
          <p><?= htmlspecialchars(isset($profil['siswa_nama'])?$profil['siswa_nama']:'Siswa', ENT_QUOTES, 'UTF-8') ?></p>
          <a href="#"><i class="fa fa-circle text-success"></i> Online</a>
        </div>
      </div>

      <!-- Menus -->
      <ul class="sidebar-menu" data-widget="tree">
        <li class="header">Menu Utama</li>

        <li class="<?php echo ($CURRENT=='index.php')?'active':''; ?>">
          <a href="index.php" title="Dashboard">
            <span class="menu-ic"><i class="fas fa-th-large"></i></span>
            <span class="menu-text">Dashboard</span>
          </a>
        </li>

        <li class="<?php echo ($CURRENT=='absensi.php')?'active':''; ?>">
          <a href="absensi.php" title="Absensi Saya">
            <span class="menu-ic"><i class="fas fa-calendar-check"></i></span>
            <span class="menu-text">Absensi Saya</span>
          </a>
        </li>

        <li class="<?php echo ($CURRENT=='poin.php')?'active':''; ?>">
          <a href="poin.php" title="Poin Saya">
            <span class="menu-ic"><i class="fas fa-chart-line"></i></span>
            <span class="menu-text">Poin Saya</span>
          </a>
        </li>

        <li class="header">Kategori</li>

        <li class="treeview <?php echo (in_array($CURRENT,['prestasi.php','pelanggaran.php']))?'menu-open active':''; ?>">
          <a href="#" title="Kategori Poin" aria-expanded="<?php echo (in_array($CURRENT,['prestasi.php','pelanggaran.php']))?'true':'false'; ?>">
            <span class="menu-ic"><i class="fas fa-layer-group"></i></span>
            <span class="menu-text">Kategori Poin</span>
            <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
          </a>
          <ul class="treeview-menu" <?php echo (in_array($CURRENT,['prestasi.php','pelanggaran.php']))?'style="display:block"':''; ?>>
            <li class="<?php echo ($CURRENT=='prestasi.php')?'active':''; ?>">
              <a href="prestasi.php" title="Jenis Prestasi">
                <span class="menu-ic"><i class="fas fa-medal"></i></span>
                <span class="menu-text">Jenis Prestasi</span>
                <span class="pull-right-container">
                  <small class="label pull-right bg-green"><?php echo $jumlah_jenis_prestasi; ?></small>
                </span>
              </a>
            </li>
            <li class="<?php echo ($CURRENT=='pelanggaran.php')?'active':''; ?>">
              <a href="pelanggaran.php" title="Jenis Pelanggaran">
                <span class="menu-ic"><i class="fas fa-gavel"></i></span>
                <span class="menu-text">Jenis Pelanggaran</span>
                <span class="pull-right-container">
                  <small class="label pull-right bg-red"><?php echo $jumlah_jenis_pelanggaran; ?></small>
                </span>
              </a>
            </li>
          </ul>
        </li>

        <li class="header">Riwayat</li>
        <li class="<?php echo ($CURRENT=='prestasi_saya.php')?'active':''; ?>">
          <a href="prestasi_saya.php" title="Prestasi Saya">
            <span class="menu-ic"><i class="fas a fa-trophy"></i></span>
            <span class="menu-text">Prestasi Saya</span>
          </a>
        </li>
        <li class="<?php echo ($CURRENT=='pelanggaran_saya.php')?'active':''; ?>">
          <a href="pelanggaran_saya.php" title="Pelanggaran Saya">
            <span class="menu-ic"><i class="fas fa-exclamation-triangle"></i></span>
            <span class="menu-text">Pelanggaran Saya</span>
          </a>
        </li>

        <li class="header">Penilaian</li>
        <li class="<?php echo (in_array($CURRENT,['ujian.php','ujian_kelas.php']))?'active':''; ?>">
          <a href="ujian.php" title="Penilaian Sumatif (Ujian Online)">
            <span class="menu-ic"><i class="fas fa-clipboard-check"></i></span>
            <span class="menu-text">Ujian Online</span>
          </a>
        </li>
        <li class="<?php echo in_array($CURRENT, ['tugas_saya.php', 'tugas_detail.php'], true) ? 'active' : ''; ?>">
          <a href="tugas_saya.php" title="Tugas Saya">
            <span class="menu-ic"><i class="fas fa-tasks"></i></span>
            <span class="menu-text">Tugas Saya</span>
          </a>
        </li>
        <li class="">
          <a href="https://cbt.smpn1gunungtanjung.sch.id/" title="CBT NESAGUN">
            <span class="menu-ic"><i class="fas fa-clipboard-check"></i></span>
            <span class="menu-text">CBT NESAGUN</span>
          </a>
        </li>

        <li class="header">Akun</li>
        <li class="<?php echo ($CURRENT=='gantipassword.php')?'active':''; ?>">
          <a href="gantipassword.php" title="Ganti Password">
            <span class="menu-ic"><i class="fas fa-user-shield"></i></span>
            <span class="menu-text">Ganti Password</span>
          </a>
        </li>

      </ul>
    </section>


    
  </aside>

  <!-- jQuery + Bootstrap + AdminLTE (untuk tree & push-menu) -->
  <script src="../assets/bower_components/jquery/dist/jquery.min.js"></script>
  <script src="../assets/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
  <script src="../assets/dist/js/adminlte.min.js"></script>

  <script>
    (function () {
      function ready(fn){ if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }
      ready(function () {
        var $menu = $('.sidebar-menu');

        $(document).off('click', '.sidebar-menu li a');
        $(document).off('click', '.sidebar-menu a');
        $menu.off('click'); $menu.off('click', 'li a'); $menu.off('click', 'a');

        // === PERSIST COLLAPSE ===
        var LS_KEY = 'eps_sidebar_collapsed';
        function applyPersistedCollapse(){
          try { var v = localStorage.getItem(LS_KEY); if (v === '1') { $('body').addClass('sidebar-collapse'); } } catch(e){}
        }
        function persistCollapseFromBody(){
          try { localStorage.setItem(LS_KEY, $('body').hasClass('sidebar-collapse') ? '1' : '0'); } catch(e){}
        }
        applyPersistedCollapse();
        $('.sidebar-toggle').off('click.persistState').on('click.persistState', function(){ setTimeout(persistCollapseFromBody, 10); });

        function closeTree($li){
          var $sub = $li.children('.treeview-menu'); if(!$sub.length) return;
          $sub.stop(true, true).slideUp(160, function(){ $li.removeClass('menu-open active').data('animating', false); $(this).css('height',''); });
          $li.children('a').attr('aria-expanded', 'false');
        }
        function openTree($li){
          var $sub = $li.children('.treeview-menu'); if(!$sub.length) return;
          $li.siblings('.menu-open').each(function(){ closeTree($(this)); });
          $sub.stop(true, true).slideDown(160, function(){ $li.addClass('menu-open active').data('animating', false); $(this).css('height',''); });
          $li.children('a').attr('aria-expanded', 'true');
        }
        function toggleTree($li){
          var $sub = $li.children('.treeview-menu'); if(!$sub.length) return;
          if (window.innerWidth > 768 && $('body').hasClass('sidebar-mini') && $('body').hasClass('sidebar-collapse') && !$('body').hasClass('sidebar-expanded-on-hover')) { return; }
          if ($li.data('lock')) return; $li.data('lock', true); setTimeout(function(){ $li.removeData('lock'); }, 250);
          if ($li.data('animating')) return; $li.data('animating', true);
          if ($li.hasClass('menu-open')) { closeTree($li); } else { openTree($li); }
        }

        $(document).on('click.navTree', '.sidebar-menu .treeview > a', function(e){ e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation(); toggleTree($(this).parent()); return false; });
        $(document).on('keydown.navTree', '.sidebar-menu .treeview > a', function(e){ if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleTree($(this).parent()); } });

        var path = window.location.pathname.split('/').pop() || 'index.php';
        $menu.find('a').each(function(){
          var href = ($(this).attr('href')||'').split('/').pop();
          if(href === path){
            $(this).closest('li').addClass('active');
            var tree = $(this).closest('.treeview');
            if (tree.length){ tree.addClass('menu-open active'); tree.children('.treeview-menu').show(); }
            $(this).attr('aria-current','page');
          }
        });

        var pushMenuAvailable = (typeof $.AdminLTE !== 'undefined' && $.AdminLTE.pushMenu);
        if (!pushMenuAvailable) {
          $('.sidebar-toggle').off('click.pushFallback').on('click.pushFallback', function(e){
            e.preventDefault();
            if (window.innerWidth > 768) $('body').toggleClass('sidebar-collapse'); else $('body').toggleClass('sidebar-open');
            persistCollapseFromBody();
          });
          $(document).off('click.closeSidebar').on('click.closeSidebar', function(ev){
            if (window.innerWidth <= 768) {
              var inside = $(ev.target).closest('.main-sidebar, .sidebar-toggle').length;
              if (!inside) $('body').removeClass('sidebar-open');
            }
          });
        }

        // === COLLAPSE HOVER EXPAND ===
        (function collapseHoverExpand(){
          var $body = $('body');
          var $sidebar = $('.main-sidebar');
          var enterTimer = 0, leaveTimer = 0;
          var DELAY_IN = 120, DELAY_OUT = 180;

          function canHoverExpand(){
            return window.innerWidth > 768 && $body.hasClass('sidebar-mini') && $body.hasClass('sidebar-collapse');
          }
          function expand(){ if (!canHoverExpand()) return; if (!$body.hasClass('sidebar-expanded-on-hover')) { $body.addClass('sidebar-expanded-on-hover'); } }
          function collapse(){ if ($body.hasClass('sidebar-expanded-on-hover')) { $body.removeClass('sidebar-expanded-on-hover'); $('.sidebar-menu .treeview-menu').css('display',''); } }

          $sidebar.off('.hoverExpand')
            .on('pointerenter.hoverExpand mouseenter.hoverExpand', function(){ clearTimeout(leaveTimer); enterTimer = setTimeout(expand, DELAY_IN); })
            .on('pointerleave.hoverExpand mouseleave.hoverExpand', function(){ clearTimeout(enterTimer); leaveTimer = setTimeout(collapse, DELAY_OUT); });

          $(document).off('mousedown.collapseWhenClickOutside').on('mousedown.collapseWhenClickOutside', function(ev){
            if (!$body.hasClass('sidebar-expanded-on-hover')) return;
            var inside = $(ev.target).closest('.main-sidebar').length || $(ev.target).closest('.sidebar-toggle').length;
            if (!inside) collapse();
          });

          var hoverOpenDelay = 140, hoverCloseDelay = 180;
          $(document)
            .off('mouseenter.autoTree mouseleave.autoTree')
            .on('mouseenter.autoTree', '.sidebar-expanded-on-hover .sidebar-menu .treeview', function(){
              if (!canHoverExpand()) return;
              var $li = $(this);
              clearTimeout($li.data('hoverCloseTimer'));
              var t = setTimeout(function(){ if (!$li.hasClass('menu-open')) { openTree($li); } }, hoverOpenDelay);
              $li.data('hoverOpenTimer', t);
            })
            .on('mouseleave.autoTree', '.sidebar-expanded-on-hover .sidebar-menu .treeview', function(){
              if (!canHoverExpand()) return;
              var $li = $(this);
              clearTimeout($li.data('hoverOpenTimer'));
              var t = setTimeout(function(){ if ($li.hasClass('menu-open')) { closeTree($li); } }, hoverCloseDelay);
              $li.data('hoverCloseTimer', t);
            });

          // AUTO-HIDE setelah klik menu saat overlay
          $(document).off('click.hideAfterSelect').on('click.hideAfterSelect', '.sidebar-menu a', function(){
            if (window.innerWidth > 768 && $body.hasClass('sidebar-mini') && $body.hasClass('sidebar-collapse') && $body.hasClass('sidebar-expanded-on-hover')) {
              try { localStorage.setItem(LS_KEY, '1'); } catch(e){}
              setTimeout(function(){ $body.removeClass('sidebar-expanded-on-hover'); }, 30);
            }
          });

          $(window).off('resize.cleanHover').on('resize.cleanHover', function(){ if (window.innerWidth <= 768) { $body.removeClass('sidebar-expanded-on-hover'); } });
        })();
      });
    })();
  </script>

  <!-- ===================== [REVISI KHUSUS] JS Centering Modal Jenjang ===================== -->
  <script>
    (function(){
      // Identifikasi modal "Jenjang Pembinaan Peserta Didik"
      function isJenjangModal($m){
        var title = ($m.find('.modal-title').first().text()||'').trim().toLowerCase();
        if (title.indexOf('jenjang pembinaan peserta didik') !== -1) return true;
        if ($m.is('#modalJenjangPembinaan, #jenjangModal, [data-jenjang-modal]')) return true;
        return false;
      }

      function recalcCenter($m){
        if(!$m || !$m.length) return;
        var $dlg = $m.find('.modal-dialog');
        var winH = $(window).height();
        var dlgH = $dlg.outerHeight(true);
        $m.toggleClass('modal-overflow', dlgH > (winH - 20));
      }

      // Saat modal akan tampil
      $(document).on('show.bs.modal', function(e){
        var $m = $(e.target);
        if (!isJenjangModal($m)) return;

        // Pastikan modal menempel ke <body> (hindari parent yg punya transform)
        if (!$m.parent().is('body')) { $m.appendTo('body'); }

        // Aktifkan mode centering
        $m.addClass('modal-centered');
      });

      // Setelah tampil → ukur apakah overflow
      $(document).on('shown.bs.modal', function(e){
        var $m = $(e.target);
        if (!$m.hasClass('modal-centered')) return;
        setTimeout(function(){ recalcCenter($m); }, 0);
      });

      // Recalculate ketika resize/orientasi berubah
      $(window).on('resize.modalCenter orientationchange.modalCenter', function(){
        $('.modal.modal-centered:visible').each(function(){ recalcCenter($(this)); });
      });

      // Mutasi konten dinamis (accordion/expand) di dalam modal
      var observer = new MutationObserver(function(){
        $('.modal.modal-centered:visible').each(function(){ recalcCenter($(this)); });
      });
      $(document).on('shown.bs.modal', function(e){
        var $m = $(e.target);
        if(!$m.hasClass('modal-centered')) return;
        try{
          observer.disconnect();
          observer.observe($m.find('.modal-content')[0], {childList:true, subtree:true});
        }catch(err){}
      });
      $(document).on('hide.bs.modal', function(){ try{ observer.disconnect(); }catch(err){} });
    })();
  </script>
  <!-- ================== /END REVISI KHUSUS ================== -->

  <script>
(function(){
  var btn = document.querySelector('[data-logout]');
  if(!btn) return;

  btn.addEventListener('click', function(e){
    e.preventDefault();
    confirmLogout(this.getAttribute('href') || 'logout.php');
  });

function confirmLogout(url){
  function proceed(){
    if (window.Swal){
      Swal.fire({
        title:'Mengakhiri sesi…',
        didOpen:()=>Swal.showLoading(),
        allowOutsideClick:false, allowEscapeKey:false,
        showConfirmButton:false, timer:900,
        scrollbarPadding:false                // <— TAMBAHKAN INI
      });
    }
    setTimeout(function(){ window.location.href = url; }, 700);
  }

  if (window.Swal){
    Swal.fire({
      title:'Keluar dari E-Poin?',
      text:'Sesi Anda akan ditutup.',
      icon:'question',
      showCancelButton:true,
      confirmButtonText:'Keluar',
      cancelButtonText:'Batal',
      reverseButtons:true,
      customClass:{ popup:'swal2-brand' },
      backdrop:'rgba(15,23,42,.45)',
      scrollbarPadding:false                // <— TAMBAHKAN INI
    }).then(function(res){ if(res.isConfirmed) proceed(); });
    return;
  }

  var s = document.createElement('script');
  s.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
  s.onload = function(){ confirmLogout(url); };
  document.head.appendChild(s);
}
})();
</script>

<!-- CATATAN: Jangan tutup wrapper/body/html di header.php. Konten/halaman yang menambahkan <div class="content-wrapper">, dan footer.php yang menutup. -->
