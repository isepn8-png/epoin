<?php
// ====== START BUFFER supaya Export Excel/headers aman ======
if (session_status() === PHP_SESSION_NONE) { session_start(); }
ob_start();

include 'header.php';
if(!isset($koneksi)){ die("Koneksi \$koneksi tidak ditemukan."); }

/* =========================
   Helper
========================= */
function esc($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function getv($key, $default=''){ return isset($_GET[$key]) ? $_GET[$key] : $default; }
function asInt($v){ return (int)$v; }
$basePath = esc(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

/* =========================
   Ambil parameter filter (BULAN DIHAPUS)
========================= */
$ta_id       = asInt(getv('ta_id', 0));          // 0 = Semua TA
$kelas_id    = asInt(getv('kelas_id', 0));       // 0 = Semua Kelas
$urutkan     = trim(getv('urutkan',''));         // prestasi_terbanyak | pelanggaran_terbanyak | net_point_tertinggi | net_point_terendah
$view        = trim(getv('view','siswa'));       // siswa | kelas
$export      = trim(getv('export',''));          // excel | ''
$saldo_scope = trim(getv('saldo_scope','all'));  // all | pos | zero | neg (MENU SARAN BARU)

$allowedViews = ['siswa', 'kelas'];
if (!in_array($view, $allowedViews, true)) {
  $view = 'siswa';
}
$allowedScopes = ['all', 'pos', 'zero', 'neg'];
if (!in_array($saldo_scope, $allowedScopes, true)) {
  $saldo_scope = 'all';
}
if ($export !== '' && $export !== 'excel') {
  $export = '';
}
if ($ta_id < 0) {
  $ta_id = 0;
}
if ($kelas_id < 0) {
  $kelas_id = 0;
}

/* Default urutan -> SALDO TERTINGGI */
if ($urutkan === '' || !in_array($urutkan, ['prestasi_terbanyak','pelanggaran_terbanyak','net_point_tertinggi','net_point_terendah'], true)) {
  $urutkan = 'net_point_tertinggi';
}

/* =========================
   Options dropdown
========================= */
$ops_ta = mysqli_query($koneksi, "SELECT ta_id, ta_nama FROM ta ORDER BY ta_id DESC");

// Kelas: jika TA dipilih => filter per TA, jika "Semua TA" => tampilkan semua kelas lintas TA
if($ta_id>0){
  $ops_kelas = mysqli_query($koneksi, "SELECT kelas_id, kelas_nama FROM kelas WHERE kelas_ta=".$ta_id." ORDER BY kelas_nama ASC");
}else{
  $ops_kelas = mysqli_query($koneksi, "SELECT kelas_id, kelas_nama FROM kelas ORDER BY kelas_nama ASC");
}

/* =========================
   Bangun filter SQL untuk PER SISWA
========================= */
$filtersPrestasi   = [];
$filtersPelanggaran= [];

if($ta_id>0){
  $filtersPrestasi[]    = "k.kelas_ta = ".$ta_id;
  $filtersPelanggaran[] = "k2.kelas_ta = ".$ta_id;
}
if($kelas_id>0){
  $filtersPrestasi[]    = "ip.kelas = ".$kelas_id;
  $filtersPelanggaran[] = "ig.kelas = ".$kelas_id;
}

/* (Bulan dihapus) */

/* WHERE klausa gabungan */
$wherePrestasi    = $filtersPrestasi    ? ("WHERE ".implode(" AND ", $filtersPrestasi))    : "";
$wherePelanggaran = $filtersPelanggaran ? ("WHERE ".implode(" AND ", $filtersPelanggaran)) : "";

/* =========================
   Subquery agregasi untuk PER SISWA
========================= */
$subPrestasi = "
  SELECT ip.siswa, SUM(pr.prestasi_point) AS total_prestasi
  FROM input_prestasi ip
  JOIN prestasi pr ON pr.prestasi_id = ip.prestasi
  JOIN kelas k ON k.kelas_id = ip.kelas
  $wherePrestasi
  GROUP BY ip.siswa
";
$subPelanggaran = "
  SELECT ig.siswa, SUM(pg.pelanggaran_point) AS total_pelanggaran
  FROM input_pelanggaran ig
  JOIN pelanggaran pg ON pg.pelanggaran_id = ig.pelanggaran
  JOIN kelas k2 ON k2.kelas_id = ig.kelas
  $wherePelanggaran
  GROUP BY ig.siswa
";

$subIDs = "
  SELECT ip.siswa AS siswa_id
  FROM input_prestasi ip
  JOIN kelas k ON k.kelas_id = ip.kelas
  $wherePrestasi
  UNION
  SELECT ig.siswa AS siswa_id
  FROM input_pelanggaran ig
  JOIN kelas k2 ON k2.kelas_id = ig.kelas
  $wherePelanggaran
";

/* =========================
   Urutkan (DEFAULT saldo tertinggi)
========================= */
switch($urutkan){
  case 'prestasi_terbanyak':
    $orderSql = " ORDER BY COALESCE(p.total_prestasi,0) DESC, COALESCE(g.total_pelanggaran,0) ASC, s.siswa_nama ASC ";
    break;
  case 'pelanggaran_terbanyak':
    $orderSql = " ORDER BY COALESCE(g.total_pelanggaran,0) DESC, COALESCE(p.total_prestasi,0) ASC, s.siswa_nama ASC ";
    break;
  case 'net_point_tertinggi':
    $orderSql = " ORDER BY (COALESCE(p.total_prestasi,0) - COALESCE(g.total_pelanggaran,0)) DESC, s.siswa_nama ASC ";
    break;
  case 'net_point_terendah':
    $orderSql = " ORDER BY (COALESCE(p.total_prestasi,0) - COALESCE(g.total_pelanggaran,0)) ASC, s.siswa_nama ASC ";
    break;
}

/* ===== Scope Saldo (menu saran baru, implementasi dengan HAVING) ===== */
$havingSiswa = "";
if     ($saldo_scope === 'pos')  { $havingSiswa = " HAVING saldo > 0 "; }
elseif ($saldo_scope === 'zero') { $havingSiswa = " HAVING saldo = 0 "; }
elseif ($saldo_scope === 'neg')  { $havingSiswa = " HAVING saldo < 0 "; }

/* =========================
   SQL Per Siswa
========================= */
$sqlPerSiswa = "
  SELECT 
    s.siswa_id, s.siswa_nama, s.siswa_nis,
    COALESCE(p.total_prestasi,0) AS total_prestasi,
    COALESCE(g.total_pelanggaran,0) AS total_pelanggaran,
    (COALESCE(p.total_prestasi,0) - COALESCE(g.total_pelanggaran,0)) AS saldo
  FROM siswa s
  JOIN ( $subIDs ) ids ON ids.siswa_id = s.siswa_id
  LEFT JOIN ( $subPrestasi ) p ON p.siswa = s.siswa_id
  LEFT JOIN ( $subPelanggaran ) g ON g.siswa = s.siswa_id
  $havingSiswa
  $orderSql
";

/* =========================
   SQL Per Kelas
========================= */
$ignoreKelasOnRekap = ($view === 'kelas' && $kelas_id > 0);
$kelas_id_for_kelas = $ignoreKelasOnRekap ? 0 : $kelas_id;

$filtersKelas = [];
if($ta_id>0){ $filtersKelas[] = "k.kelas_ta = ".$ta_id; }
if($kelas_id_for_kelas>0){ $filtersKelas[] = "k.kelas_id = ".$kelas_id_for_kelas; }
$whereKelas = $filtersKelas ? "WHERE ".implode(" AND ", $filtersKelas) : "";

$subKelasPrestasi = "
  SELECT ip.kelas AS kelas_id, SUM(pr.prestasi_point) AS total_prestasi
  FROM input_prestasi ip
  JOIN prestasi pr ON pr.prestasi_id = ip.prestasi
  JOIN kelas k ON k.kelas_id = ip.kelas
  $wherePrestasi
  GROUP BY ip.kelas
";
$subKelasPelanggaran = "
  SELECT ig.kelas AS kelas_id, SUM(pg.pelanggaran_point) AS total_pelanggaran
  FROM input_pelanggaran ig
  JOIN pelanggaran pg ON pg.pelanggaran_id = ig.pelanggaran
  JOIN kelas k2 ON k2.kelas_id = ig.kelas
  $wherePelanggaran
  GROUP BY ig.kelas
";

/* Urutkan untuk rekap kelas */
switch($urutkan){
  case 'prestasi_terbanyak':    $orderKelas = " ORDER BY COALESCE(p.total_prestasi,0) DESC, k.kelas_nama ASC "; break;
  case 'pelanggaran_terbanyak': $orderKelas = " ORDER BY COALESCE(g.total_pelanggaran,0) DESC, k.kelas_nama ASC "; break;
  case 'net_point_tertinggi':   $orderKelas = " ORDER BY (COALESCE(p.total_prestasi,0) - COALESCE(g.total_pelanggaran,0)) DESC, k.kelas_nama ASC "; break;
  case 'net_point_terendah':    $orderKelas = " ORDER BY (COALESCE(p.total_prestasi,0) - COALESCE(g.total_pelanggaran,0)) ASC, k.kelas_nama ASC "; break;
  default:                      $orderKelas = " ORDER BY (COALESCE(p.total_prestasi,0) - COALESCE(g.total_pelanggaran,0)) DESC, k.kelas_nama ASC "; break;
}

/* Scope saldo untuk rekap kelas */
$havingKelas = "";
if     ($saldo_scope === 'pos')  { $havingKelas = " HAVING saldo > 0 "; }
elseif ($saldo_scope === 'zero') { $havingKelas = " HAVING saldo = 0 "; }
elseif ($saldo_scope === 'neg')  { $havingKelas = " HAVING saldo < 0 "; }

$sqlPerKelas = "
  SELECT 
    k.kelas_id, k.kelas_nama,
    COALESCE(p.total_prestasi,0) AS total_prestasi,
    COALESCE(g.total_pelanggaran,0) AS total_pelanggaran,
    (COALESCE(p.total_prestasi,0) - COALESCE(g.total_pelanggaran,0)) AS saldo
  FROM kelas k
  $whereKelas
  LEFT JOIN ( $subKelasPrestasi ) p ON p.kelas_id = k.kelas_id
  LEFT JOIN ( $subKelasPelanggaran ) g ON g.kelas_id = k.kelas_id
  $havingKelas
  $orderKelas
";

/* =========================
   Export Excel (fix)
========================= */
if($export === 'excel'){
  if (ob_get_length()) { ob_end_clean(); }
  header("Content-Type: application/vnd.ms-excel; charset=utf-8");
  header("Content-Disposition: attachment; filename=laporan_point_".($view==='kelas'?'per_kelas':'per_siswa')."_".date('Ymd_His').".xls");
  echo "<table border='1' cellpadding='6' cellspacing='0'>";
  if($view==='kelas'){
    echo "<tr><th>No</th><th>Kelas</th><th>Total Prestasi</th><th>Total Pelanggaran</th><th>Saldo Poin</th></tr>";
    $res = mysqli_query($koneksi, $sqlPerKelas);
    $no=1;
    if($res){ while($r = mysqli_fetch_assoc($res)){
      $prestasi = (int)$r['total_prestasi'];
      $pelanggaran = (int)$r['total_pelanggaran'];
      $net = $prestasi - $pelanggaran;
      echo "<tr>
              <td>".$no++."</td>
              <td>".esc($r['kelas_nama'])."</td>
              <td>".$prestasi."</td>
              <td>".$pelanggaran."</td>
              <td>".$net."</td>
            </tr>";
    }}
  } else {
    echo "<tr><th>No</th><th>Nama Siswa</th><th>NIS</th><th>Total Prestasi</th><th>Total Pelanggaran</th><th>Saldo Poin</th></tr>";
    $res = mysqli_query($koneksi, $sqlPerSiswa);
    $no=1;
    if($res){ while($r = mysqli_fetch_assoc($res)){
      $prestasi = (int)$r['total_prestasi'];
      $pelanggaran = (int)$r['total_pelanggaran'];
      $net = $prestasi - $pelanggaran;
      echo "<tr>
              <td>".$no++."</td>
              <td>".esc($r['siswa_nama'])."</td>
              <td>".esc($r['siswa_nis'])."</td>
              <td>".$prestasi."</td>
              <td>".$pelanggaran."</td>
              <td>".$net."</td>
            </tr>";
    }}
  }
  echo "</table>";
  exit;
}

/* =========================
   Ambil label TA/Kelas (untuk ringkasan)
========================= */
$ta_nama = ($ta_id>0) ? '' : 'Semua Tahun Ajaran';
if($ta_id>0){
  $stmtTa = mysqli_prepare($koneksi, 'SELECT ta_nama FROM ta WHERE ta_id = ? LIMIT 1');
  if ($stmtTa) {
    mysqli_stmt_bind_param($stmtTa, 'i', $ta_id);
    mysqli_stmt_execute($stmtTa);
    $t = mysqli_stmt_get_result($stmtTa);
    if($t && $tt = mysqli_fetch_assoc($t)){ $ta_nama = $tt['ta_nama']; }
    mysqli_stmt_close($stmtTa);
  }
}
$kelas_nama = ($kelas_id>0) ? '' : 'Semua';
if($kelas_id>0){
  $stmtKl = mysqli_prepare($koneksi, 'SELECT kelas_nama FROM kelas WHERE kelas_id = ? LIMIT 1');
  if ($stmtKl) {
    mysqli_stmt_bind_param($stmtKl, 'i', $kelas_id);
    mysqli_stmt_execute($stmtKl);
    $k = mysqli_stmt_get_result($stmtKl);
    if($k && $kk = mysqli_fetch_assoc($k)){ $kelas_nama = $kk['kelas_nama']; }
    mysqli_stmt_close($stmtKl);
  }
}
?>

<style>
/* ====== THEME & INTERAKSI ====== */
:root{
  --green:#2e7d32;
  --green-bg:#e8f5e9;
  --red:#c62828;
  --red-bg:#ffebee;
  --blue:#1565c0;
  --blue-bg:#e3f2fd;
  --soft:#f6f9ff;

  /* warna untuk kontrol Chips */
  --chip-bg:#eef3ff;
  --chip-border:#dfe7ff;
  --chip-hover:#e6edff;
  --chip-active-grad: linear-gradient(90deg,#42a5f5,#7e57c2);

  /* tombol CTA */
  --cta-grad: linear-gradient(135deg,#0d47a1 0%, #1976d2 40%, #00bcd4 100%);
}
.box.box-primary .box-header{
  background: linear-gradient(120deg, var(--blue) 0%, #3f51b5 100%);
  color:#fff;border-radius:6px 6px 0 0;
}
.box.box-primary{
  border:0;box-shadow:0 8px 20px rgba(0,0,0,.06);
  border-radius:8px;overflow:hidden;animation:fadeIn .4s ease both;
}
.badge-pill { border-radius: 999px; padding:6px 12px; display:inline-block; font-weight:600; }
.badge-blue  { background:var(--blue-bg);  color:var(--blue);  }

/* ===== Segmented "chips" untuk Urutkan & Scope ===== */
.select-native{ position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden; }
.segment{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
.segment .chip{
  padding:8px 12px; border-radius:999px;
  background:var(--chip-bg); border:1px solid var(--chip-border);
  cursor:pointer; font-weight:600; display:inline-flex; align-items:center; gap:8px;
  transition: all .2s ease; user-select:none;
}
.segment .chip i{ opacity:.8; }
.segment .chip:hover{ background:var(--chip-hover); transform:translateY(-1px); }
.segment .chip.active{
  background:var(--chip-active-grad); color:#fff; border-color:transparent;
  box-shadow:0 6px 16px rgba(66,165,245,.28);
}
.segment .chip.active i{ opacity:1; }

.segment-help{ margin-top:6px; font-size:12px; color:#6b6f76; }

/* ====== Table & mobile ====== */
.table-responsive{ -webkit-overflow-scrolling: touch; overflow-x:auto; position:relative; } /* penting utk sticky */
#table-datatable{ width:100%; border-collapse: separate; }
.nowrap th, .nowrap td{ white-space:nowrap; }

/* HILANGKAN scale hover yg bikin header/isi melenceng saat zoom */
.table-bordered>tbody>tr{ transition: background-color .12s ease, box-shadow .12s ease; }
.table-bordered>tbody>tr:hover{ background:linear-gradient(90deg,#fafafa, #f3f7ff); box-shadow:0 2px 10px rgba(0,0,0,.05); }

/* Penegasan warna kolom */
th.th-prestasi{ background:var(--green-bg) !important; color:var(--green); }
th.th-pelanggaran{ background:var(--red-bg) !important; color:var(--red); }
td.col-prestasi{ color:var(--green); font-weight:600; background:rgba(46,125,50,.06); }
td.col-pelanggaran{ color:var(--red); font-weight:600; background:rgba(198,40,40,.06); }
td.col-net.pos{ color:var(--green); font-weight:700; }
td.col-net.neg{ color:var(--red); font-weight:700; }
td.col-net.zero{ color:#777; font-weight:700; }

/* ======= STICKY LEFT untuk kolom 1-2 (header & body) ======= */
.sticky-first{ --noW: 58px; } /* default desktop */
.sticky-first thead th, .sticky-first tbody td{ background-clip:padding-box; }

.sticky-first thead th:nth-child(1),
.sticky-first tbody td:nth-child(1){
  position:sticky; left:0;
  min-width:var(--noW); width:var(--noW);
  background:#fff;
}
.sticky-first thead th:nth-child(2),
.sticky-first tbody td:nth-child(2){
  position:sticky; left:var(--noW);
  background:#fff;
}

/* z-index layering: header di atas body, kolom 1 di atas kolom 2 */
.sticky-first thead th:nth-child(1){ z-index:10; }
.sticky-first thead th:nth-child(2){ z-index:9; }
.sticky-first tbody td:nth-child(1){ z-index:8; }
.sticky-first tbody td:nth-child(2){ z-index:7; }

@keyframes fadeIn { from{opacity:0; transform:translateY(6px);} to{opacity:1; transform:none;} }
.row-animate{ opacity:0; animation:fadeIn .45s ease forwards; }

.summary-card { border-radius:12px; padding:16px; background:linear-gradient(135deg,#ffffff,var(--soft)); box-shadow:0 4px 12px rgba(0,0,0,.05); }

/* Hint mobile geser tabel */
.mobile-hint{display:none;margin:6px 0 12px;background:#f1f5ff;border:1px dashed #cdd9ff;color:#3b5bdb;padding:8px 12px;border-radius:8px;font-size:12px}

/* Mobile-first polish */
@media (max-width: 767px){
  .btn-block-xs{width:100% !important; display:block;}
  .mobile-hint{display:block;}
  .summary-card{padding:12px;}
  .box .box-body{padding:12px;}
  #table-datatable{ font-size:12px; }
  #table-datatable th, #table-datatable td{ padding:6px 8px; }
  .sticky-first{ --noW: 40px; } /* lebar kolom NO di HP */
  .sticky-first td:nth-child(2){ white-space:normal !important; word-break:break-word; line-height:1.25; font-size:13px; }
  .sticky-first th:nth-child(2){ font-size:12px; }
}

/* ===== Tombol: ubah jadi persegi & tanpa shadow ===== */
.btn-row{ display:flex; flex-wrap:wrap; gap:8px; align-items:stretch; }
.btn-row .btn{ flex:1 1 auto; min-width: 140px; }

/* gaya tombol persegi */
.btn-rect{
  border-radius:6px; border:0; box-shadow:none !important;
  padding:10px 14px; font-weight:800; letter-spacing:.2px; color:#fff !important;
}
.btn-cta{ background:var(--cta-grad); }
.btn-cta:hover{ filter:saturate(1.06) brightness(1.03); }
.btn-reset{ background: linear-gradient(135deg,#ff5252,#ff8a65); }
.btn-excel{ background: linear-gradient(135deg,#21a366,#107c41); }
.btn-pdf{ background: linear-gradient(135deg,#e53935,#ff5252); }

/* ====== SHIMMER untuk tombol TAMPILKAN saat filter berubah ====== */
@keyframes shimmer{
  0%   { transform:translateX(-150%); }
  100% { transform:translateX(150%); }
}
.btn-rect.attention{
  position:relative; overflow:hidden;
}
.btn-rect.attention::after{
  content:"";
  position:absolute; top:0; left:0; height:100%; width:60%;
  background:linear-gradient(110deg, rgba(255,255,255,0) 0%, rgba(255,255,255,.55) 45%, rgba(255,255,255,0) 60%);
  animation:shimmer 1.4s ease-in-out infinite;
}

/* Print */
@media print{
  .no-print,.main-header,.main-sidebar,.content-header,.main-footer,.box-header,.breadcrumb{ display:none !important; }
  .content-wrapper { margin:0 !important; }
  table { font-size: 12px; }
}
</style>

<div class="content-wrapper" style="animation:fadeIn .35s ease both;">
  <section class="content-header">
    <h1 style="animation:fadeIn .4s .05s ease both;">LAPORAN <small>Poin Siswa (Prestasi &amp; Pelanggaran)</small></h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Laporan Poin</li>
    </ol>
  </section>

  <section class="content">

    <?php if($ta_id==0): ?>
      <div class="alert alert-info" style="animation:fadeIn .35s .05s ease both;">
        <i class="fa fa-info-circle"></i>
        Saat ini menampilkan <b>Semua Tahun Ajaran</b>. Anda dapat menyaring per <b>Kelas</b> (lintas TA) atau pilih <b>Tahun Ajaran tertentu</b> agar data lebih spesifik.
      </div>
    <?php endif; ?>

    <?php if($ignoreKelasOnRekap): ?>
      <div class="alert alert-warning" style="animation:fadeIn .35s .05s ease both;">
        <i class="fa fa-exclamation-triangle"></i>
        Tampilan <b>Rekap Per Kelas</b> tidak menggunakan filter <b>Kelas</b> tertentu.
        Sistem <b>mengabaikan</b> pilihan kelas dan menampilkan seluruh kelas
        <?php echo $ta_id>0 ? 'pada Tahun Ajaran <b>'.esc($ta_nama).'</b>' : 'dari semua Tahun Ajaran'; ?>.
        Jika ingin merinci kelas tertentu, ubah tampilan ke <b>Per Siswa</b>.
      </div>
    <?php endif; ?>

    <!-- FILTER -->
    <div class="box box-primary">
      <div class="box-header"><h3 class="box-title"><i class="fa fa-filter"></i> Filter Laporan Poin</h3></div>
      <div class="box-body" style="animation:fadeIn .45s .06s ease both;">
        <form id="formFilter" method="get" action="">
          <div class="row">

            <div class="col-md-3">
              <!-- Tahun Ajaran -->
              <div class="form-group">
                <label>
                  Tahun Ajaran
                  <i class="fa fa-info-circle text-info" data-toggle="tooltip" title="Pilih tahun ajaran untuk membatasi data. Pilih 'Semua Tahun Ajaran' untuk lintas tahun."></i>
                </label>
                <select name="ta_id" class="form-control" onchange="this.form.submit()">
                  <option value="0" <?php echo $ta_id===0?'selected':''; ?>>Semua Tahun Ajaran</option>
                  <?php if($ops_ta){ while($r = mysqli_fetch_assoc($ops_ta)){ ?>
                    <option value="<?php echo (int)$r['ta_id']; ?>" <?php echo ($ta_id==(int)$r['ta_id'])?'selected':''; ?>>
                      <?php echo esc($r['ta_nama']); ?>
                    </option>
                  <?php } } ?>
                </select>
              </div>

              <!-- Kelas -->
              <div class="form-group">
                <label>
                  Kelas
                  <i class="fa fa-info-circle text-info" data-toggle="tooltip" title="Filter kelas akan mencari berdasarkan kelas yang dipilih. Jika 'Semua Tahun Ajaran' dipilih, daftar berisi semua kelas lintas TA."></i>
                </label>
                <select name="kelas_id" class="form-control">
                  <option value="0">Semua</option>
                  <?php if($ops_kelas){ while($k = mysqli_fetch_assoc($ops_kelas)){ ?>
                    <option value="<?php echo (int)$k['kelas_id']; ?>" <?php echo ($kelas_id==(int)$k['kelas_id'])?'selected':''; ?>>
                      <?php echo esc($k['kelas_nama']); ?>
                    </option>
                  <?php } } ?>
                </select>
              </div>

              <!-- Tampilan -->
              <div class="form-group">
                <label>
                  Tampilan
                  <i class="fa fa-info-circle text-info" data-toggle="tooltip" title="Pilih 'Per Siswa' untuk daftar siswa, atau 'Rekap Per Kelas' untuk ringkasan per kelas."></i>
                </label>
                <select name="view" class="form-control">
                  <option value="siswa" <?php echo $view==='siswa'?'selected':''; ?>>Per Siswa</option>
                  <option value="kelas" <?php echo $view==='kelas'?'selected':''; ?>>Rekap Per Kelas</option>
                </select>
              </div>
            </div>

            <div class="col-md-5">
              <!-- Urutkan (INTERAKTIF & WARNA) -->
              <div class="form-group">
                <label>
                  Urutkan
                  <i class="fa fa-info-circle text-info" data-toggle="tooltip" title="Klik salah satu opsi untuk menerapkan urutan. Default: Saldo Poin Tertinggi."></i>
                </label>

                <!-- select asli (fallback) -->
                <select id="urutkanSelect" name="urutkan" class="form-control select-native">
                  <option value="prestasi_terbanyak"   <?php echo $urutkan=='prestasi_terbanyak'?'selected':''; ?>>Prestasi Terbanyak</option>
                  <option value="pelanggaran_terbanyak" <?php echo $urutkan=='pelanggaran_terbanyak'?'selected':''; ?>>Pelanggaran Terbanyak</option>
                  <option value="net_point_tertinggi"   <?php echo $urutkan=='net_point_tertinggi'?'selected':''; ?>>Saldo Poin Tertinggi</option>
                  <option value="net_point_terendah"    <?php echo $urutkan=='net_point_terendah'?'selected':''; ?>>Saldo Poin Terendah</option>
                </select>

                <!-- segmented chips -->
                <div id="urutkanChips" class="segment no-print" aria-label="Pilih urutan">
                  <span class="chip" data-value="prestasi_terbanyak"   title="Prestasi Terbanyak"><i class="fas fa-medal"></i> Prestasi</span>
                  <span class="chip" data-value="pelanggaran_terbanyak" title="Pelanggaran Terbanyak"><i class="fas fa-gavel"></i> Pelanggaran</span>
                  <span class="chip" data-value="net_point_tertinggi"   title="Saldo Poin Tertinggi"><i class="fas fa-arrow-up"></i> Saldo Poin ↑</span>
                  <span class="chip" data-value="net_point_terendah"    title="Saldo Poin Terendah"><i class="fas fa-arrow-down"></i> Saldo Poin ↓</span>
                </div>
                <div class="segment-help">Klik opsi di atas untuk langsung menerapkan urutan (default: Saldo Poin ↑).</div>
              </div>

              <!-- Scope Saldo (MENU SARAN BARU, IMPLEMENTED) -->
              <div class="form-group">
                <label>
                  Tampilkan
                  <i class="fa fa-info-circle text-info" data-toggle="tooltip" title="Saring berdasarkan saldo poin agar analisis lebih fokus."></i>
                </label>

                <select id="scopeSelect" name="saldo_scope" class="form-control select-native">
                  <option value="all"  <?php echo $saldo_scope==='all'?'selected':''; ?>>Semua</option>
                  <option value="pos"  <?php echo $saldo_scope==='pos'?'selected':''; ?>>Saldo Positif</option>
                  <option value="zero" <?php echo $saldo_scope==='zero'?'selected':''; ?>>Saldo Nol</option>
                  <option value="neg"  <?php echo $saldo_scope==='neg'?'selected':''; ?>>Saldo Negatif</option>
                </select>

                <div id="scopeChips" class="segment no-print" aria-label="Pilih scope saldo">
                  <span class="chip" data-value="all"  title="Semua"><i class="fas fa-layer-group"></i> Semua</span>
                  <span class="chip" data-value="pos"  title="Saldo Positif"><i class="fas fa-thumbs-up"></i> Positif</span>
                  <span class="chip" data-value="zero" title="Saldo Nol"><i class="fas fa-equals"></i> Nol</span>
                  <span class="chip" data-value="neg"  title="Saldo Negatif"><i class="fas fa-thumbs-down"></i> Negatif</span>
                </div>
                <div class="segment-help">Gunakan scope ini untuk fokus ke siswa/kelas yang <b>unggul</b> (saldo positif), <b>berisiko</b> (saldo negatif), atau <b>netral</b>.</div>
              </div>
            </div>

            <div class="col-md-4">
              <div class="form-group no-print" style="margin-top:24px">
                <!-- ====== BARIS TOMBOL PERSEGI TANPA SHADOW (rapi & responsif) ====== -->
                <div class="btn-row">
                  <button id="btnTampilkan" class="btn btn-rect btn-cta" type="submit">
                    <i class="fa fa-bolt"></i> TAMPILKAN
                  </button>
                  <a href="<?php echo $basePath; ?>" class="btn btn-rect btn-reset" data-toggle="tooltip" title="Reset filter">
                    <i class="fa fa-refresh"></i> RESET
                  </a>
                  <a href="?<?php echo http_build_query(array_merge($_GET,['export'=>'excel'])); ?>" class="btn btn-rect btn-excel" data-toggle="tooltip" title="Unduh sebagai Excel (.xls)">
                    <i class="fa fa-file-excel-o"></i> Export Excel
                  </a>
                  <?php
                    $pdfParams = [
                      'ta_id'=>$ta_id,
                      'kelas_id'=>$kelas_id,
                      'urutkan'=>$urutkan,
                      'view'=>$view,
                      'saldo_scope'=>$saldo_scope,
                    ];
                  ?>
                  <a href="laporan_pdf.php?<?php echo http_build_query($pdfParams); ?>" target="_blank" class="btn btn-rect btn-pdf" data-toggle="tooltip" title="Cetak / Simpan sebagai PDF">
                    <i class="fa fa-file-pdf-o"></i> Cetak PDF
                  </a>
                </div>
              </div>

              <div class="summary-card" style="animation:fadeIn .45s .1s ease both;">
                <div class="badge-pill badge-blue">Tahun Ajaran: <?php echo esc($ta_nama); ?></div><br>
                <div class="badge-pill badge-blue" style="margin-top:6px;">Kelas: <?php echo esc($kelas_nama); ?></div>
                <!-- (BULAN DIHAPUS dari ringkasan) -->
              </div>
            </div>

          </div>
        </form>
      </div>
    </div>

    <!-- TABEL LAPORAN -->
    <div class="box box-primary">
      <div class="box-header">
        <h3 class="box-title"><i class="fa fa-table"></i> Laporan <?php echo $view==='kelas'?'Rekap Per Kelas':'Per Siswa'; ?></h3>
      </div>
      <div class="box-body" style="animation:fadeIn .45s .08s ease both;">

        <div class="mobile-hint only-mobile"><i class="fa fa-hand-pointer-o"></i> Geser tabel ke samping untuk melihat kolom lainnya.</div>

        <?php if($view==='kelas'): ?>
          <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover nowrap sticky-first" id="table-datatable">
              <thead>
                <tr>
                  <th width="1%">NO</th>
                  <th>KELAS</th>
                  <th class="text-center th-prestasi">TOTAL PRESTASI</th>
                  <th class="text-center th-pelanggaran">TOTAL PELANGGARAN</th>
                  <th class="text-center">SALDO POIN</th>
                </tr>
              </thead>
              <tbody>
                <?php
                  $no=1;
                  $res = mysqli_query($koneksi, $sqlPerKelas);
                  if($res && mysqli_num_rows($res)>0){
                    while($d = mysqli_fetch_assoc($res)){
                      $prestasi = (int)$d['total_prestasi'];
                      $pelanggaran = (int)$d['total_pelanggaran'];
                      $net = $prestasi - $pelanggaran;
                      $netClass = $net>0?'pos':($net<0?'neg':'zero');
                      echo "<tr class='row-animate'>
                              <td>".$no++."</td>
                              <td>".esc($d['kelas_nama'])."</td>
                              <td class='text-center col-prestasi'>".$prestasi."</td>
                              <td class='text-center col-pelanggaran'>".$pelanggaran."</td>
                              <td class='text-center col-net ".$netClass."'><b>".$net."</b></td>
                            </tr>";
                    }
                  } else {
                    echo "<tr class='dt-empty'>" .
                           "<td class='text-center'>Tidak ada data untuk filter yang dipilih.</td>" .
                           "<td></td><td></td><td></td><td></td>" .
                         "</tr>";
                  }
                ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover nowrap sticky-first" id="table-datatable">
              <thead>
                <tr>
                  <th width="1%">NO</th>
                  <th>NAMA SISWA</th>
                  <th class="text-center">NIS</th>
                  <th class="text-center th-prestasi">TOTAL PRESTASI</th>
                  <th class="text-center th-pelanggaran">TOTAL PELANGGARAN</th>
                  <th class="text-center">SALDO POIN</th>
                  <th class="text-center">OPSI</th>
                </tr>
              </thead>
              <tbody>
                <?php
                  $no=1;
                  $res = mysqli_query($koneksi, $sqlPerSiswa);
                  if($res && mysqli_num_rows($res)>0){
                    while($d = mysqli_fetch_assoc($res)){
                      $prestasi = (int)$d['total_prestasi'];
                      $pelanggaran = (int)$d['total_pelanggaran'];
                      $net = $prestasi - $pelanggaran;
                      $netClass = $net>0?'pos':($net<0?'neg':'zero');
                      echo "<tr class='row-animate'>
                              <td>".$no++."</td>
                              <td>".esc($d['siswa_nama'])."</td>
                              <td class='text-center'>".esc($d['siswa_nis'])."</td>
                              <td class='text-center col-prestasi'>".$prestasi."</td>
                              <td class='text-center col-pelanggaran'>".$pelanggaran."</td>
                              <td class='text-center col-net ".$netClass."'><b>".$net."</b></td>
                              <td class='text-center'>
                                <a class='btn btn-success btn-sm' target='_blank' href='siswa_riwayat.php?id=".(int)$d['siswa_id']."'><i class='fa fa-info-circle'></i> Detail</a>
                              </td>
                            </tr>";
                    }
                  } else {
                    echo "<tr class='dt-empty'>" .
                           "<td class='text-center'>Tidak ada data untuk filter yang dipilih.</td>" .
                           "<td></td><td></td><td></td><td></td><td></td><td></td>" .
                         "</tr>";
                  }
                ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

      </div>
    </div>

  </section>
</div>

<script>
// Aktifkan tooltip (Bootstrap)
$(function(){
  if (typeof $ !== 'undefined' && typeof $.fn.tooltip === 'function') {
    $('[data-toggle="tooltip"]').tooltip();
  }
});

// Animasi baris (staggered)
function animateRows(){
  $('#table-datatable tbody tr.row-animate').each(function(i){
    $(this).css('animation-delay', (i*50)+'ms');
  });
}

(function initDT(){
  if (typeof $ === 'undefined' || !$.fn.DataTable) { animateRows(); return; }

  var $tbl = $('#table-datatable');

  // Rapikan markup (sesuai skrip lama)
  (function prepareTableMarkup($t){
    var cols = $t.find('thead th').length;
    $t.find('tbody tr').each(function(){
      var $cells = $(this).children('td');
      if ($cells.length === 1 && ( $cells.attr('colspan') || $cells.prop('colSpan') > 1 )) {
        $(this).remove();
        return;
      }
      if ($cells.length < cols){
        for (var i=$cells.length; i<cols; i++){ $(this).append('<td></td>'); }
      }
    });
  })($tbl);

  // Pastikan tidak ada instance lama
  if ($.fn.DataTable.isDataTable($tbl)) {
    try { $tbl.DataTable().destroy(); } catch(e){}
  }

  // Inisialisasi SEKALI, memakai default global dari header
  var dt = $tbl.DataTable();

  // Sinkronisasi lebar kolom & animasi
  function adjustColumns(){ dt.columns.adjust(); }
  animateRows();
  dt.on('draw.dt init.dt', function(){ animateRows(); adjustColumns(); });

  var resizeTimer=null;
  $(window).on('resize', function(){
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(adjustColumns, 100);
  });
  setTimeout(adjustColumns, 0);
})();

/* ====== Interaksi segmented chips: Urutkan ====== */
(function(){
  var $sel  = $('#urutkanSelect');
  var $chips = $('#urutkanChips');

  function setActive(val){
    $chips.find('.chip').removeClass('active');
    $chips.find('.chip[data-value="'+val+'"]').addClass('active');
  }
  setActive($sel.val()||'net_point_tertinggi');

  $chips.on('click keydown', '.chip', function(e){
    if(e.type==='click' || (e.type==='keydown' && (e.key==='Enter' || e.key===' '))){
      var v = String($(this).data('value')||'net_point_tertinggi');
      $sel.val(v).trigger('change');              // trigger agar efek shimmer nyala
      $('#btnTampilkan').addClass('attention');   // pastikan shimmer terlihat
      $(this).closest('form')[0].submit();        // tetap auto-submit seperti skrip lama
    }
  });
  $chips.find('.chip').attr('tabindex','0');
  $sel.on('change', function(){ setActive(this.value); });
})();

/* ====== Interaksi segmented chips: Scope Saldo ====== */
(function(){
  var $sel  = $('#scopeSelect');
  var $chips = $('#scopeChips');

  function setActive(val){
    $chips.find('.chip').removeClass('active');
    $chips.find('.chip[data-value="'+val+'"]').addClass('active');
  }
  setActive($sel.val()||'all');

  $chips.on('click keydown', '.chip', function(e){
    if(e.type==='click' || (e.type==='keydown' && (e.key==='Enter' || e.key===' '))){
      var v = String($(this).data('value')||'all');
      $sel.val(v).trigger('change');
      $('#btnTampilkan').addClass('attention');
      $(this).closest('form')[0].submit();
    }
  });
  $chips.find('.chip').attr('tabindex','0');
  $sel.on('change', function(){ setActive(this.value); });
})();

/* ====== Tombol TAMPILKAN: SHIMMER saat filter berubah; berhenti saat submit ====== */
(function(){
  var $form = $('#formFilter');
  var $cta  = $('#btnTampilkan');

  // Nyalakan shimmer ketika ada perubahan (semua filter termasuk TA)
  $form.find('select, input').on('change input', function(){
    $cta.addClass('attention');
  });

  // Matikan shimmer saat data ditampilkan (submit)
  $form.on('submit', function(){
    $cta.removeClass('attention');
  });
})();
</script>


<?php
include 'footer.php';
// jangan end_clean di sini (biar halaman tampil). Export Excel sudah exit lebih dulu.
?>
