<?php
// ===== GUARD: hanya STAF (admin + guru/wali/BK), tolak siswa =====
require_once __DIR__ . '/../includes/epoin_security.php';
epoin_staff_only_guard();
include 'header.php';
?>



<?php
// --- Helper aman ---
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function status_badge_class($s){
  $s = strtolower(trim((string)$s));
  if($s === 'aktif') return 'label-success';
  if($s === 'nonaktif' || $s === 'tidak aktif') return 'label-default';
  return 'label-warning';
}

// --- Query statistik ringkas ---
$stat = ['total'=>0,'aktif'=>0,'nonaktif'=>0];
$qStat = mysqli_query($koneksi, "
  SELECT 
    COUNT(*) total,
    SUM(CASE WHEN LOWER(siswa_status)='aktif' THEN 1 ELSE 0 END) aktif,
    SUM(CASE WHEN LOWER(siswa_status)<>'aktif' THEN 1 ELSE 0 END) nonaktif
  FROM siswa
");
if($qStat){ $stat = mysqli_fetch_assoc($qStat) ?: $stat; }

// --- List jurusan untuk chip & filter ---
$jurusanList = [];
$qJur = mysqli_query($koneksi, "
  SELECT j.jurusan_id, j.jurusan_nama, COUNT(*) jml
  FROM siswa s 
  JOIN jurusan j ON s.siswa_jurusan = j.jurusan_id
  GROUP BY j.jurusan_id, j.jurusan_nama
  ORDER BY j.jurusan_nama ASC
");
if($qJur){
  while($r = mysqli_fetch_assoc($qJur)){ $jurusanList[] = $r; }
}

/* ====== [BARU] Ambil TA aktif ====== */
$ACTIVE_TA_ID = 0;
$_qTa = mysqli_query($koneksi, "SELECT ta_id FROM ta WHERE ta_status=1 ORDER BY ta_id DESC LIMIT 1");
if($_qTa && ($_rTa=mysqli_fetch_assoc($_qTa))){ $ACTIVE_TA_ID = (int)$_rTa['ta_id']; }

/* ====== [BARU] List kelas pada TA aktif (untuk dropdown filter) ====== */
$kelasList = [];
if ($ACTIVE_TA_ID > 0){
  $qKelas = mysqli_query($koneksi, "
    SELECT k.kelas_id, k.kelas_nama, COUNT(ks.ks_id) AS jml
    FROM kelas k
    LEFT JOIN kelas_siswa ks ON ks.ks_kelas = k.kelas_id
    WHERE k.kelas_ta = ".(int)$ACTIVE_TA_ID."
    GROUP BY k.kelas_id, k.kelas_nama
    ORDER BY k.kelas_nama ASC
  ");
  if($qKelas){ while($r = mysqli_fetch_assoc($qKelas)){ $kelasList[] = $r; } }
}

/* --- Dataset tabel utama: SALDO POIN (Prestasi - Pelanggaran) + [BARU] Kelas (TA aktif) --- */
$data = mysqli_query($koneksi, "
  SELECT 
    s.siswa_id, s.siswa_nama, s.siswa_nis, s.siswa_status,
    j.jurusan_nama,
    k.kelas_id, k.kelas_nama,
    (
      COALESCE((SELECT SUM(p.prestasi_point) 
        FROM input_prestasi ip 
        JOIN prestasi p ON ip.prestasi=p.prestasi_id
        WHERE ip.siswa=s.siswa_id),0)
      -
      COALESCE((SELECT SUM(pg.pelanggaran_point)
        FROM input_pelanggaran ig 
        JOIN pelanggaran pg ON ig.pelanggaran=pg.pelanggaran_id
        WHERE ig.siswa=s.siswa_id),0)
    ) AS saldo_poin
  FROM siswa s
  JOIN jurusan j ON s.siswa_jurusan = j.jurusan_id
  LEFT JOIN kelas_siswa ks ON ks.ks_siswa = s.siswa_id
  LEFT JOIN kelas k ON k.kelas_id = ks.ks_kelas AND k.kelas_ta = ".(int)$ACTIVE_TA_ID."
  ORDER BY s.siswa_nama ASC
");

/* ====== [R4] Status SP per siswa — 1 QUERY AGREGAT (anti N+1) ======
   Ambil tingkat SP TERTINGGI yang sudah diterbitkan pada tahun berjalan
   dari sp_log, petakan per siswa_id. Tidak ada query per-baris. */
$spRankMap = [];
if (function_exists('table_exists') && table_exists($koneksi, 'sp_log')) {
  $__yr = (int)date('Y');
  $qSp = mysqli_query($koneksi, "
    SELECT siswa_id,
           MAX(CASE sp_level
                 WHEN 'SP4' THEN 4 WHEN 'SP3' THEN 3
                 WHEN 'SP2' THEN 2 WHEN 'SP1' THEN 1 ELSE 0 END) AS sp_rank
    FROM sp_log
    WHERE YEAR(tanggal) = $__yr
    GROUP BY siswa_id
  ");
  if ($qSp) {
    while ($r = mysqli_fetch_assoc($qSp)) {
      $rank = (int)$r['sp_rank'];
      if ($rank > 0) $spRankMap[(int)$r['siswa_id']] = $rank;
    }
  }
}
?>

<style>
  /* ====== Polish UI minimal (asli) ====== */
  .content-header h1 { display:flex; align-items:center; gap:10px; opacity: 0; animation: fadeIn 1s forwards; }
  .page-accent{
    display:inline-block; padding:.25rem .6rem; font-weight:600; border-radius:999px;
    background:linear-gradient(135deg,#3b82f6,#06b6d4); color:#fff; font-size:12px;
    box-shadow:0 4px 14px rgba(59,130,246,.25);
    animation: slide-fade .6s ease both;
  }

  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: translateY(-10px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  @keyframes slide-fade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}

  /* =========[ BARU ]========== */
  .content-header h1.page-title{
    margin:0;
    display:flex; align-items:center; gap:12px;
    color:#000000;
    font-family:"Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
    font-weight:800;
    font-size:clamp(18px, 2.2vw, 26px);
    line-height:1.2;
    letter-spacing:.2px;
    opacity:0; transform:translateY(6px);
    animation:textFade .6s ease-out .05s forwards;
  }
  .title-icon{
    display:inline-flex; align-items:center; justify-content:center;
    width:40px; height:40px; border-radius:12px;
    background:linear-gradient(135deg,#e0ecff,#f0f7ff);
    color:#1e3a8a;
    box-shadow:0 6px 18px rgba(59,130,246,.18);
  }
  .title-badge{
    display:inline-flex; align-items:center; gap:6px;
    background:linear-gradient(90deg,#2563eb,#1d4ed8);
    color:#ffffff; border-radius:9999px;
    padding:3px 10px; line-height:1;
    font-family:"Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
    font-weight:700;
    font-size:10px;
    box-shadow:0 4px 12px rgba(29,78,216,.25);
    transform:translateY(-1px);
  }
  .title-badge i{ font-size:12px; }

  .content-header > h1.page-title > small.title-badge{
    font-size:10px !important;
    padding:3px 10px !important;
    line-height:1 !important;
  }

  @keyframes textFade{
    from{ opacity:0; transform:translateY(6px); }
    to  { opacity:1; transform:translateY(0); }
  }

  @media (max-width:480px){
    .content-header h1.page-title{ gap:8px; font-size:18px; }
    .content-header > h1.page-title > small.title-badge{
      font-size:9px !important; padding:3px 8px !important; transform:none;
    }
  }
  /* =========[ /BARU ]========== */

  /* ====== KARTU STATISTIK: COLORFUL ====== */
  .stat-card{
    position:relative;
    border-radius:16px;
    color:#ffffff;
    background: linear-gradient(135deg, #64748b, #334155);
    box-shadow: 0 14px 28px rgba(2,6,23,.12), inset 0 0 0 1px rgba(255,255,255,.06);
    padding:16px 16px 14px;
    transition:transform .18s ease, box-shadow .18s ease, filter .18s ease;
    overflow:hidden;
    isolation:isolate;
  }
  .stat-card:hover{
    transform: translateY(-2px);
    box-shadow: 0 18px 36px rgba(2,6,23,.16), inset 0 0 0 1px rgba(255,255,255,.08);
    filter: saturate(1.06);
  }
  .stat-card::after{ content:none !important; display:none !important; }
  @keyframes shimmer-card{ 0%{ left:-40%; } 60%{ left:120%; } 100%{ left:120%; } }

  .card-total{
    background: radial-gradient(1200px 280px at -10% -10%, rgba(255,255,255,.18), transparent),
                linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%);
  }
  .card-aktif{
    background: radial-gradient(1200px 280px at -10% -10%, rgba(255,255,255,.16), transparent),
                linear-gradient(135deg, #16a34a 0%, #0ea5e9 100%);
  }
  .card-nonaktif{
    background: radial-gradient(1200px 280px at -10% -10%, rgba(255,255,255,.14), transparent),
                linear-gradient(135deg, #f97316 0%, #7c3aed 100%);
  }
  .stat-ico{
    position:relative;
    width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;
    color:#0b2239; background:rgba(255,255,255,.9);
    box-shadow:0 8px 16px rgba(0,0,0,.12);
    margin-right:12px; font-size:18px;
    z-index:1;
  }
  .stat-label{ font-size:12px; color: rgba(255,255,255,.9); font-weight:600; z-index:1; }
  .stat-value{ font-size:26px; font-weight:900; letter-spacing:.2px; text-shadow:0 1px 2px rgba(0,0,0,.25); z-index:1; }
  .countup{ animation: pop .5s ease-out both; }
  @keyframes pop{ from{transform:scale(.9);opacity:0} to{transform:scale(1);opacity:1} }

  .toolbar{
    display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; margin-bottom:12px;
  }
  .toolbar .left, .toolbar .right{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; }

  .btn-elegant{ 
    padding:8px 14px !important; 
    font-weight:700; 
    border-radius:10px; 
    box-shadow:0 6px 14px rgba(0,0,0,.08);
    transition:transform .12s ease, box-shadow .12s ease, filter .12s ease;
  }
  .btn-elegant:hover{ transform:translateY(-1px); box-shadow:0 10px 20px rgba(0,0,0,.12); filter:saturate(1.04); }
  .btn-elegant:active{ transform:none; box-shadow:0 6px 14px rgba(0,0,0,.10); }
  .btn-elegant i, .btn-elegant svg{ margin-right:6px; }

  .chip{
    display:inline-flex; align-items:center; gap:8px; padding:.35rem .6rem; border-radius:999px;
    background:#eef2ff; color:#3730a3; font-weight:600; border:1px solid #e0e7ff; cursor:pointer;
    transition: background .15s ease, box-shadow .15s ease, transform .15s ease;
  }
  .chip:hover{ background:#e0e7ff; box-shadow:0 4px 12px rgba(55,48,163,.15); transform: translateY(-1px); }

  #students-table thead th{ position:sticky; top:0; background:#f8fafc; z-index:1; }
  #students-table tbody tr:hover{
    background: #e0f2fe;
  }
  #students-table tbody tr:hover .badge-row{
    display: inline-block;
  }

  .avatar{
    width:30px;height:30px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;
    background:linear-gradient(135deg,#06b6d4,#3b82f6); color:#fff; font-weight:700; margin-right:6px; font-size:12px;
  }
  .name-cell { display:flex; align-items:center; gap:6px; }
  .name-text { max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:inline-block; }

  .nis{ font-weight:700; color:#111; }
  .label.rounded { border-radius:999px; padding:.25em .6em; font-weight:600; }

  .saldo-badge{ border-radius:999px; padding:.25em .6em; font-weight:800; display:inline-block; min-width:64px; text-align:center; }
  .saldo-pos { background:#dcfce7; color:#065f46; }
  .saldo-neg { background:#fee2e2; color:#991b1b; }
  .saldo-zero{ background:#e2e8f0; color:#334155; }

  /* ====== [R4] Badge Status SP (disiplin) ====== */
  .sp-badge{ display:inline-block; border-radius:999px; padding:.25em .7em; font-weight:800; font-size:12px; border:1px solid transparent; white-space:nowrap; }
  .sp-aman{ background:#dcfce7; color:#065f46; border-color:#bbf7d0; }
  .sp-ew{ background:#dbeafe; color:#1d4ed8; border-color:#bfdbfe; }
  .sp-sp1{ background:#fef9c3; color:#854d0e; border-color:#fde68a; }
  .sp-sp2{ background:#ffedd5; color:#c2410c; border-color:#fed7aa; }
  .sp-sp3{ background:#fee2e2; color:#991b1b; border-color:#fecaca; }
  .sp-sp4{ background:#7f1d1d; color:#fff;    border-color:#7f1d1d; }

  .icon-svg { width:14px; height:14px; vertical-align:-2px; }

  .hidden-soft{ display:none !important; }

  /* ====== REVISI DIMINTA ====== */
  /* 1) Kolom TINGKAT / JUR dibuat rapat sesuai teks */
  th.col-jur, td.col-jur{
    white-space:nowrap;
    width:1%;
  }

  /* [BARU] Kolom KELAS rapat juga */
  th.col-kelas, td.col-kelas{
    white-space:nowrap;
    width:1%;
  }

  /* 2) Kolom OPSI melebar seperlunya & tombol menyatu (combo pill) satu baris */
  th.col-opsi, td.col-opsi{ white-space:nowrap; }
  td.col-opsi{
    overflow:visible;
  }
  .btn-combo.actions{
    display:inline-flex; align-items:stretch; flex-wrap:nowrap;
    gap:0; border-radius:9999px; overflow:hidden;
    box-shadow:0 6px 14px rgba(0,0,0,.08);
  }
  .btn-combo.actions .btn{
    float:none; margin:0 !important; border:0; border-radius:0 !important;
    line-height:1; padding:6px 10px; display:inline-flex; align-items:center; justify-content:center;
  }
  .btn-combo.actions .btn:first-child{ border-radius:9999px 0 0 9999px !important; }
  .btn-combo.actions .btn:last-child{  border-radius:0 9999px 9999px 0 !important; }

  /* ====== [BARU] Palet badge kelas (berbeda tiap kelas) ====== */
  .badge-kelas{ border-radius:999px; padding:.25em .6em; font-weight:700; display:inline-block; border:1px solid transparent; }
  .badge-kls-0{ background:#e2e8f0; color:#334155; border-color:#cbd5e1; } /* default/unknown */
  .badge-kls-1{ background:#ecfeff; color:#0e7490; border-color:#a5f3fc; }  /* cyan */
  .badge-kls-2{ background:#ecfdf5; color:#065f46; border-color:#a7f3d0; }  /* green */
  .badge-kls-3{ background:#fdf4ff; color:#6b21a8; border-color:#f5d0fe; }  /* purple */
  .badge-kls-4{ background:#fff7ed; color:#9a3412; border-color:#fed7aa; }  /* orange */
  .badge-kls-5{ background:#fef2f2; color:#7f1d1d; border-color:#fecaca; }  /* red */
  .badge-kls-6{ background:#eef2ff; color:#3730a3; border-color:#e0e7ff; }  /* indigo */
  .badge-kls-7{ background:#f0f9ff; color:#075985; border-color:#bae6fd; }  /* sky */
  .badge-kls-8{ background:#fefce8; color:#854d0e; border-color:#fde68a; }  /* yellow */
  .badge-kls-9{ background:#f1f5f9; color:#0f172a; border-color:#e2e8f0; }  /* slate */
  .badge-kls-10{ background:#ecfccb; color:#365314; border-color:#d9f99d; } /* lime */
  .badge-kls-11{ background:#fae8ff; color:#701a75; border-color:#f5d0fe; } /* fuchsia */
  .badge-kls-12{ background:#e2f2ff; color:#0c4a6e; border-color:#bfdbfe; } /* blue */

  @media (max-width:576px){
    .toolbar{ gap:6px; }
    .stat-card{ padding:12px; }
    .table-responsive{ overflow-x:auto; }
    #students-table td, #students-table th{ white-space:nowrap; }
    .name-text { max-width:140px; }
  }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <h1 class="page-title">
      <span class="title-icon"><i class="fa fa-users"></i></span>
      Siswa
      <small class="title-badge"><i class="fa fa-check-circle"></i> Data Siswa</small>
    </h1>

<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<?php if (!empty($_SESSION['flash_ok'])): ?>
  <div class="alert alert-success alert-dismissible">
    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
    <?= $_SESSION['flash_ok']; ?>
  </div>
  <?php unset($_SESSION['flash_ok']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_err'])): ?>
  <div class="alert alert-danger alert-dismissible">
    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
    <?= $_SESSION['flash_err']; ?>
  </div>
  <?php unset($_SESSION['flash_err']); ?>
<?php endif; ?>


    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Siswa</li>
    </ol>
  </section>

  <section class="content">

    <!-- ======== Kartu Statistik Cepat ======== -->
    <div class="row" style="margin-bottom:10px;">
      <div class="col-sm-4">
        <div class="stat-card card-total">
          <div style="display:flex; align-items:center; position:relative; z-index:1;">
            <div class="stat-ico"><i class="fa fa-users"></i></div>
            <div>
              <div class="stat-label">Total Siswa</div>
              <div class="stat-value countup" data-target="<?php echo (int)$stat['total']; ?>" data-duration="1200">0</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-4">
        <div class="stat-card card-aktif">
          <div style="display:flex; align-items:center; position:relative; z-index:1;">
            <div class="stat-ico"><i class="fa fa-user-plus"></i></div>
            <div>
              <div class="stat-label">Aktif</div>
              <div class="stat-value countup" data-target="<?php echo (int)$stat['aktif']; ?>" data-duration="1200">0</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-4">
        <div class="stat-card card-nonaktif">
          <div style="display:flex; align-items:center; position:relative; z-index:1;">
            <div class="stat-ico"><i class="fa fa-user-times"></i></div>
            <div>
              <div class="stat-label">Nonaktif / Lainnya</div>
              <div class="stat-value countup" data-target="<?php echo (int)$stat['nonaktif']; ?>" data-duration="1200">0</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ======== Box Tabel ======== -->
    <div class="row">
      <section class="col-lg-12">
        <div class="box box-primary">

          <div class="box-header">
            <h3 class="box-title">Siswa</h3>
            <div class="btn-group pull-right">
              <a href="hp_ortu_import.php" class="btn btn-success btn-sm btn-elegant" data-toggle="tooltip" title="Import HP Orang Tua via Excel">
                <i class="fa fa-whatsapp"></i> &nbsp;Import HP Ortu
              </a>
              <a href="siswa_import.php" class="btn btn-danger btn-sm btn-elegant" data-toggle="tooltip" title="Import data siswa dari Excel">
                <i class="fa fa-file-excel-o"></i> &nbsp;Import Siswa
              </a>
              <a href="siswa_tambah.php" class="btn btn-primary btn-sm btn-elegant" data-toggle="tooltip" title="Tambah siswa baru">
                <i class="fa fa-plus"></i> &nbsp;Tambah
              </a>
            </div>
          </div>

          <div class="box-body">

            <?php if(isset($_SESSION['pesan'])): ?>
              <?php if($_SESSION['pesan']==="sukses"): 
                $terimport = $_SESSION['pesan_terimport'] ?? 0;
                $duplikat = $_SESSION['pesan_duplikat'] ?? 0;
                $tidak_lengkap = $_SESSION['pesan_tidak_lengkap'] ?? 0;
                $tidak_ditemukan = $_SESSION['pesan_tidak_ditemukan'] ?? 0;
              ?>
                <div class="alert alert-success">
                  <i class="fa fa-check-circle"></i>
                  <b>Proses import selesai!</b><br>
                  Berhasil diimport : <b><?php echo (int)$terimport; ?></b><br>
                  Data Duplikat : <b><?php echo (int)$duplikat; ?></b><br>
                  Data Tidak Lengkap / Tidak Sesuai : <b><?php echo (int)$tidak_lengkap; ?></b><br>
                  Status Tidak Ditemukan : <b><?php echo (int)$tidak_ditemukan; ?></b>
                </div>
              <?php else: ?>
                <div class="alert alert-danger">
                  <i class="fa fa-exclamation-triangle"></i>
                  <b>Proses import gagal!</b> Pastikan file atau format yang anda upload sudah sesuai.
                </div>
              <?php endif; ?>
              <?php
                unset($_SESSION['pesan'], $_SESSION['pesan_terimport'], $_SESSION['pesan_duplikat'], $_SESSION['pesan_tidak_lengkap'], $_SESSION['pesan_tidak_ditemukan']);
              ?>
            <?php endif; ?>

            <!-- ======== Toolbar: Filters & Aksi ======== -->
            <div class="toolbar">
              <div class="left">
                <div class="filters-wrap" id="filters-wrap" style="display:flex; gap:8px; align-items:center;">
                  <select id="filter-jurusan" class="form-control input-sm" style="min-width:150px;">
                    <option value="">Semua Tingkat/Jur</option>
                    <?php foreach($jurusanList as $j): ?>
                      <option value="<?php echo e($j['jurusan_nama']); ?>"><?php echo e($j['jurusan_nama']); ?> (<?php echo (int)$j['jml']; ?>)</option>
                    <?php endforeach; ?>
                  </select>

                  <!-- [BARU] Filter Nama Kelas (TA aktif) -->
                  <select id="filter-kelas" class="form-control input-sm" style="min-width:150px;">
                    <option value="">Pilih Kelas (TA aktif)</option>
                    <?php foreach($kelasList as $k): ?>
                      <option value="<?php echo e($k['kelas_nama']); ?>"><?php echo e($k['kelas_nama']); ?> (<?php echo (int)$k['jml']; ?>)</option>
                    <?php endforeach; ?>
                  </select>

                  <select id="filter-status" class="form-control input-sm" style="min-width:160px;">
                    <option value="">Semua Status</option>
                    <option value="aktif">Aktif</option>
                    <option value="nonaktif">Nonaktif</option>
                  </select>

                  <!-- [R4] Filter Status SP (disiplin) -->
                  <select id="filter-sp" class="form-control input-sm" style="min-width:175px;">
                    <option value="">Semua Status SP</option>
                    <option value="Aman">Aman</option>
                    <option value="EW">&#9888; Early Warning</option>
                    <option value="SP1">SP1</option>
                    <option value="SP2">SP2</option>
                    <option value="SP3">SP3</option>
                    <option value="SP4">SP4</option>
                    <option value="KRITIS">Kritis (SP3&ndash;SP4)</option>
                  </select>
                </div>
                <button id="btn-reset" class="btn btn-default btn-sm" data-toggle="tooltip" title="Reset filter">
                  <i class="fa fa-refresh"></i> Reset
                </button>
                <button id="btn-toggle-filters" class="btn btn-default btn-sm" data-toggle="tooltip" title="Tampilkan atau sembunyikan filter">
                  <i class="fa fa-sliders"></i> Tampilkan Filter
                </button>
              </div>
              <div class="right">
                <!-- Tombol Export CSV dan Cetak Dihilangkan -->
              </div>
            </div>

            <!-- Chips jurusan (quick pick) -->
            <div id="chips-wrap" style="margin-bottom:10px; display:flex; flex-wrap:wrap; gap:8px;">
              <?php foreach($jurusanList as $j): ?>
                <span class="chip" data-chip-jurusan="<?php echo e($j['jurusan_nama']); ?>">
                  <i class="fa fa-book"></i> <?php echo e($j['jurusan_nama']); ?> <span class="label label-primary rounded"><?php echo (int)$j['jml']; ?></span>
                </span>
              <?php endforeach; ?>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-striped table-hover" id="students-table" style="width:100%;">
                <thead>
                  <tr>
                    <th width="1%">#</th>
                    <th>NAMA</th>
                    <th>NIS</th>
                    <th class="col-jur">TINGKAT / JUR</th>
                    <!-- [BARU] Kolom KELAS -->
                    <th class="col-kelas">KELAS</th>
                    <th>SALDO POIN</th>
                    <th>STATUS SP</th>
                    <th>STATUS</th>
                    <th class="col-opsi">OPSI</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $no=1; while($d = mysqli_fetch_assoc($data)): ?>
                    <?php
                      $saldo = (int)$d['saldo_poin'];
                      $saldoClass = $saldo>0 ? 'saldo-pos' : ($saldo<0 ? 'saldo-neg' : 'saldo-zero');
                      $saldoText  = ($saldo>0?'+':'').number_format($saldo,0,',','.');
                      $kelasNama  = trim($d['kelas_nama'] ?? '');
                      $kelasId    = (int)($d['kelas_id'] ?? 0);
                      $badgeIdx   = $kelasId > 0 ? (($kelasId % 12) ?: 12) : 0; // 1..12 or 0
                      // [R4] Status SP (disiplin): SP terbit > saldo
                      $sid    = (int)$d['siswa_id'];
                      $spRank = $spRankMap[$sid] ?? 0;
                      if ($spRank > 0)     { $spKey='SP'.$spRank; $spLabel='SP'.$spRank; }
                      elseif ($saldo >= 0) { $spKey='AMAN';       $spLabel='Aman'; }
                      else                 { $spKey='EW';         $spLabel='EW'; }
                    ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td class="name-cell">
                        <span class="avatar" aria-hidden="true">
                          <?php
                            $nm = trim($d['siswa_nama'] ?? '');
                            $parts = preg_split('/\s+/',$nm,-1,PREG_SPLIT_NO_EMPTY);
                            $ini = '';
                            foreach(array_slice($parts,0,2) as $p){ $ini .= mb_strtoupper(mb_substr($p,0,1)); }
                            echo e($ini ?: 'S');
                          ?>
                        </span>
                        <span class="name-text"><b><?php echo e($d['siswa_nama']); ?></b></span>
                      </td>
                      <td>
                        <span class="nis"><?php echo e($d['siswa_nis']); ?></span>
                        <button class="btn btn-xs btn-default copy-nis" data-nis="<?php echo e($d['siswa_nis']); ?>" title="Salin NIS"><i class="fa fa-copy"></i></button>
                      </td>
                      <td class="col-jur"><span class="label label-primary rounded"><?php echo e($d['jurusan_nama']); ?></span></td>

                      <!-- [BARU] Sel KELAS -->
                      <td class="col-kelas">
                        <span class="badge-kelas <?php echo 'badge-kls-'.$badgeIdx; ?>">
                          <?php echo e($kelasNama !== '' ? $kelasNama : '-'); ?>
                        </span>
                      </td>

                      <td class="text-center"><span class="saldo-badge <?php echo $saldoClass; ?>"><?php echo $saldoText; ?></span></td>

                      <!-- [R4] Sel STATUS SP -->
                      <td class="text-center">
                        <span class="sp-badge sp-<?php echo strtolower($spKey); ?>" title="Status pembinaan/disiplin">
                          <?php if($spKey==='EW'): ?><i class="fa fa-exclamation-triangle"></i> <?php elseif($spKey!=='AMAN'): ?><i class="fa fa-file-text-o"></i> <?php endif; ?><?php echo e($spLabel); ?>
                        </span>
                      </td>

                      <td><span class="label rounded <?php echo status_badge_class($d['siswa_status']); ?>"><?php echo e($d['siswa_status']); ?></span></td>
                      <td class="col-opsi">
                        <!-- REVISI: tombol menyatu jadi pill & selalu 1 baris -->
                        <div class="btn-combo actions" role="group" aria-label="Opsi">
                          <a class="btn btn-success btn-sm" data-toggle="tooltip" title="Profil Disiplin (Ringkasan + SP)" href="siswa_riwayat.php?id=<?php echo (int)$d['siswa_id']; ?>">
                            <i class="fa fa-list"></i> <span class="hidden-xs">Profil</span>
                          </a>
                          <a class="btn btn-warning btn-sm" data-toggle="tooltip" title="Edit" href="siswa_edit.php?id=<?php echo (int)$d['siswa_id']; ?>">
                            <svg class="icon-svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false">
                              <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-9.5 9.5a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l9.5-9.5zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zM10.5 3.207 2 11.707V14h2.293l8.5-8.5-2.293-2.293z"/>
                            </svg>
                          </a>
                          <a class="btn btn-danger btn-sm" data-toggle="tooltip" title="Hapus (konfirmasi)" href="siswa_hapus_konfir.php?id=<?php echo (int)$d['siswa_id']; ?>">
                            <i class="fa fa-trash"></i>
                          </a>
                        </div>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>

          </div>
        </div>
      </section>
    </div>
  </section>
</div>

<?php include 'footer.php'; ?>

<script>
  // Matikan alert popup DataTables (log tetap di console)
  if ($.fn && $.fn.dataTable && $.fn.dataTable.ext) {
    $.fn.dataTable.ext.errMode = 'none';
  }

  // ====== COUNT-UP (angka naik) ======
  (function(){
    function easeOutCubic(t){ return 1 - Math.pow(1 - t, 3); }
    function countUp(el){
      if (el.dataset.animated) return;
      el.dataset.animated = '1';
      var to = parseInt(el.getAttribute('data-target'),10) || 0;
      var dur = parseInt(el.getAttribute('data-duration'),10) || 1000;
      var start = 0, startTime = performance.now();
      function frame(now){
        var p = Math.min((now - startTime)/dur, 1);
        var val = Math.round(start + (to - start) * easeOutCubic(p));
        el.textContent = val.toLocaleString('id-ID');
        if (p < 1) requestAnimationFrame(frame);
      }
      requestAnimationFrame(frame);
    }
    var counters = document.querySelectorAll('.countup');
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function(entries){
        entries.forEach(function(en){
          if (en.isIntersecting){ countUp(en.target); io.unobserve(en.target); }
        });
      }, {threshold:0.6});
      counters.forEach(function(el){ io.observe(el); });
    } else {
      counters.forEach(countUp);
    }
  })();

  // ====== Halaman siap ======
  $(function () {
    $('[data-toggle="tooltip"]').tooltip();

    var $tbl = $('#students-table');
    if ($.fn.DataTable && $.fn.DataTable.isDataTable($tbl)) {
      try { $tbl.DataTable().clear().destroy(); } catch(e){}
    }

    /* Indeks kolom:
       0:#, 1:Nama, 2:NIS, 3:Jurusan, 4:Kelas, 5:Saldo, 6:Status SP, 7:Status, 8:Opsi */
    var dt = $tbl.DataTable({
      destroy:true,
      pageLength:25,
      order:[[1,'asc']],
      autoWidth:false,
      columnDefs:[
        {targets:0, width:"32px"},
        {targets:[5,6,7], className:'text-center'}, // Saldo, Status SP, Status
        {targets:8, orderable:false}                // Opsi
      ],
      language:{
        search:"Cari:",
        lengthMenu:"Tampil _MENU_ data",
        info:"Menampilkan _START_–_END_ dari _TOTAL_ data",
        paginate:{previous:"←", next:"→"}
      }
    });

    // Filter
    $('#filter-jurusan').on('change', function(){ dt.column(3).search(this.value).draw(); });
    $('#filter-kelas').on('change', function(){ dt.column(4).search(this.value).draw(); });
    $('#filter-sp').on('change', function(){            // [R4] filter Status SP (kolom 6)
      var v = this.value;
      if (v === 'KRITIS')   { dt.column(6).search('SP3|SP4', true, false).draw(); }
      else if (v === '')    { dt.column(6).search('').draw(); }
      else                  { dt.column(6).search('\\b'+v+'\\b', true, false).draw(); }
    });
    $('#filter-status').on('change', function(){ dt.column(7).search(this.value, true, false).draw(); });
    $('#btn-reset').on('click', function(){
      $('#filter-jurusan').val('');
      $('#filter-kelas').val('');
      $('#filter-sp').val('');
      $('#filter-status').val('');
      dt.search('').columns().search('').draw();
    });

    // Toggle filter & chips
    var $filtersWrap = $('#filters-wrap');
    var $chips = $('#chips-wrap');
    var $btnToggle = $('#btn-toggle-filters');
    var filtersVisible = true;
    function updateToggleLabel(){
      $btnToggle.html('<i class="fa fa-sliders"></i> ' + (filtersVisible ? 'Sembunyikan Filter' : 'Tampilkan Filter'));
    }
    updateToggleLabel();
    $btnToggle.on('click', function(){
      filtersVisible = !filtersVisible;
      $filtersWrap.toggleClass('hidden-soft', !filtersVisible);
      $chips.toggleClass('hidden-soft', !filtersVisible);
      updateToggleLabel();
    });

    // Chips quick filter jurusan
    $('[data-chip-jurusan]').on('click', function(){
      var val = $(this).data('chip-jurusan');
      $('#filter-jurusan').val(val).trigger('change');
    });

    // Copy NIS
    $(document).on('click', '.copy-nis', function(){
      var nis = $(this).data('nis')+'';
      var btn = $(this);
      if(navigator.clipboard){
        navigator.clipboard.writeText(nis).then(()=>{
          btn.tooltip({title:'Tersalin!', placement:'top'}).tooltip('show');
          setTimeout(()=>{btn.tooltip('destroy');}, 800);
        });
      }else{
        var ta=document.createElement('textarea'); ta.value=nis; document.body.appendChild(ta);
        ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
        alert('NIS tersalin');
      }
    });
  });
</script>
