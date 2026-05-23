<?php include 'header.php'; ?>
<div class="content-wrapper">
  <section class="content-header">
    <h1>Absensi Saya <small>Rekap Bulanan / Semester</small></h1>
    <ol class="breadcrumb">
      <li><a href="index.php"><i class="fa fa-dashboard"></i> Dashboard</a></li>
      <li class="active">Absensi</li>
    </ol>
  </section>

  <section class="content">
<?php
// ==============================
// Setup dasar
// ==============================
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$id_siswa = $_SESSION['id'] ?? 0;
if (!$id_siswa) { die('<div class="alert alert-danger">Sesi siswa tidak ditemukan.</div>'); }

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function arr_get($a,$k,$d=null){ return isset($a[$k])?$a[$k]:$d; }

// ==============================
// Param UI
// ==============================
$view  = isset($_GET['view']) ? $_GET['view'] : 'bulanan'; // 'bulanan' (harian) | 'mapel' (detail mapel)
$mode  = isset($_GET['mode']) ? strtolower(trim($_GET['mode'])) : 'semester'; // default: SEMESTER

// Tetap dukung ?bulan=YYYY-MM ATAU ?bulan=1..12 (untuk mode bulanan)
$bulanReq = isset($_GET['bulan']) ? trim($_GET['bulan']) : '';

// Semester pilihannya tetap (1/2). Default otomatis dari bulan sekarang.
if (isset($_GET['sem'])) {
  $semReq = (int)$_GET['sem'];
} else {
  $semReq = ((int)date('n') >= 7) ? 1 : 2;
}
$sem = ($semReq === 2) ? 2 : 1;

// ==============================
// Tentukan Tahun Ajaran (TA) aktif otomatis
// TA dimulai Juli (7) s/d Juni (6) tahun berikutnya
// TAStartYear = tahun awal TA, contoh: TA 2025/2026 => TAStartYear=2025
// ==============================
$nowMonth = (int)date('n');
$nowYear  = (int)date('Y');
$TAStartYear = ($nowMonth >= 7) ? $nowYear : ($nowYear - 1);
$TAEndYear   = $TAStartYear + 1;
$taLabel     = "TA $TAStartYear/$TAEndYear";

// ==============================
// Normalisasi mode & bulan (untuk mode=bulan)
// ==============================
if (!in_array($mode, ['bulan','semester'], true)) { $mode = 'semester'; }

if ($bulanReq !== '' && preg_match('/^\d{4}\-\d{2}$/', $bulanReq)) {
  list($tmpY, $mm) = explode('-', $bulanReq, 2);
  $bulan = (int)$mm;
} else {
  $bulan = ($bulanReq !== '' && ctype_digit($bulanReq)) ? (int)$bulanReq : (int)date('n');
}
$bulan = max(1, min(12, $bulan));

// ==============================
// Hitung rentang tanggal berdasarkan MODE + TA
// ==============================
$monthStart = $bulan; // hanya meaningful untuk mode bulanan
$monthEnd   = $bulan;
$periodeLabel = ''; // info di UI
$dateStart = '';
$dateEnd   = '';

if ($mode === 'semester') {
  if ($sem === 1) {
    // Semester 1: Jul–Des di tahun TAStartYear
    $monthStart = 7; $monthEnd = 12;
    $dateStart  = sprintf('%04d-07-01', $TAStartYear);
    $dateEnd    = sprintf('%04d-12-31', $TAStartYear);
    $periodeLabel = "Semester 1 (Jul–Des $TAStartYear) — $taLabel";
  } else {
    // Semester 2: Jan–Jun di tahun TAEndYear
    $monthStart = 1; $monthEnd = 6;
    $dateStart  = sprintf('%04d-01-01', $TAEndYear);
    $dateEnd    = sprintf('%04d-06-30', $TAEndYear);
    $periodeLabel = "Semester 2 (Jan–Jun $TAEndYear) — $taLabel";
  }
} else {
  // mode 'bulan' — bulan yang dipilih selalu mengacu pada TA aktif
  // Bulan 7..12 di TAStartYear, Bulan 1..6 di TAEndYear
  $yearForMonth = ($bulan >= 7) ? $TAStartYear : $TAEndYear;
  $dateStart    = sprintf('%04d-%02d-01', $yearForMonth, $bulan);
  $dateEnd      = date('Y-m-t', strtotime($dateStart));
  $periodeLabel = date('F', mktime(0,0,0,$bulan,1))." $yearForMonth — $taLabel";
}

// ------------------------------
// Helper untuk querystring di tab (tanpa tahun lagi)
// ------------------------------
$filterQS = "mode=".rawurlencode($mode);
if ($mode === 'bulan')   { $filterQS .= "&bulan=".rawurlencode($bulan); }
if ($mode === 'semester'){ $filterQS .= "&sem=".rawurlencode($sem); }

$badge = ['H'=>'success','I'=>'info','S'=>'warning','A'=>'danger'];

// ==========================================================
// ===============  QUERY DATA INTI (pakai DATE RANGE) ======
// ==========================================================

// Donut: Rekap harian final
$kehadiran = ['H'=>0,'I'=>0,'S'=>0,'A'=>0];
$sqlKeh = "SELECT UPPER(d.status) AS s, COUNT(*) AS jml
           FROM absensi_harian_detail d
           JOIN absensi_harian h ON h.harian_id=d.harian_id
           WHERE d.siswa_id=? AND h.status='final'
             AND h.tanggal BETWEEN ? AND ?
           GROUP BY UPPER(d.status)";
$st = mysqli_prepare($koneksi,$sqlKeh);
mysqli_stmt_bind_param($st,'iss',$id_siswa,$dateStart,$dateEnd);
mysqli_stmt_execute($st);
$res = mysqli_stmt_get_result($st);
while($r = mysqli_fetch_assoc($res)){
  $k = strtoupper($r['s']);
  if(isset($kehadiran[$k])) $kehadiran[$k] = (int)$r['jml'];
}
mysqli_stmt_close($st);

// Data Harian (tabel)
$rowsHarian = [];
$sqlHarian = "SELECT h.tanggal, UPPER(d.status) AS status
              FROM absensi_harian_detail d
              JOIN absensi_harian h ON h.harian_id=d.harian_id
              WHERE d.siswa_id=? AND h.status='final'
                AND h.tanggal BETWEEN ? AND ?
              ORDER BY h.tanggal DESC";
$stx = mysqli_prepare($koneksi,$sqlHarian);
mysqli_stmt_bind_param($stx,'iss',$id_siswa,$dateStart,$dateEnd);
mysqli_stmt_execute($stx);
$rs = mysqli_stmt_get_result($stx);
while($r=mysqli_fetch_assoc($rs)) $rowsHarian[]=$r;
mysqli_stmt_close($stx);

// Data Per Mapel (detail)
$rowsMapel = [];
$sqlMapel = "SELECT s.tanggal, m.mapel_nama AS mapel, s.jam_ke, UPPER(d.status) AS status
             FROM absensi_sesi_detail d
             JOIN absensi_sesi s ON s.sesi_id=d.sesi_id
             JOIN mapel m ON m.mapel_id=s.mapel_id
             WHERE d.siswa_id=? AND s.status='final'
               AND s.tanggal BETWEEN ? AND ?
             ORDER BY s.tanggal DESC, s.jam_ke ASC, m.mapel_nama ASC";
$stm = mysqli_prepare($koneksi,$sqlMapel);
mysqli_stmt_bind_param($stm,'iss',$id_siswa,$dateStart,$dateEnd);
mysqli_stmt_execute($stm);
$rsm = mysqli_stmt_get_result($stm);
while($r=mysqli_fetch_assoc($rsm)) $rowsMapel[]=$r;
mysqli_stmt_close($stm);

// Ringkasan per Bulan (untuk mode semester)
$rekapBulan = []; // [m] => ['H'=>x,'I'=>y,'S'=>z,'A'=>w]
if ($mode !== 'bulan') {
  for ($m=1;$m<=12;$m++) $rekapBulan[$m] = ['H'=>0,'I'=>0,'S'=>0,'A'=>0];

  $sqlRingkas = "SELECT MONTH(h.tanggal) AS m, UPPER(d.status) AS s, COUNT(*) AS jml
                 FROM absensi_harian_detail d
                 JOIN absensi_harian h ON h.harian_id=d.harian_id
                 WHERE d.siswa_id=? AND h.status='final'
                   AND h.tanggal BETWEEN ? AND ?
                 GROUP BY MONTH(h.tanggal), UPPER(d.status)
                 ORDER BY MONTH(h.tanggal) ASC";
  $stb = mysqli_prepare($koneksi,$sqlRingkas);
  mysqli_stmt_bind_param($stb,'iss',$id_siswa,$dateStart,$dateEnd);
  mysqli_stmt_execute($stb);
  $rsb = mysqli_stmt_get_result($stb);
  while ($r = mysqli_fetch_assoc($rsb)) {
    $m = (int)$r['m'];
    $s = $r['s'];
    if (isset($rekapBulan[$m][$s])) {
      $rekapBulan[$m][$s] = (int)$r['jml'];
    }
  }
  mysqli_stmt_close($stb);
}

// Rekap PER MAPEL (periode)
$rekapMapel = [];
$sqlRekapMapel = "SELECT m.mapel_nama AS mapel,
         SUM(CASE WHEN UPPER(d.status)='H' THEN 1 ELSE 0 END) AS H,
         SUM(CASE WHEN UPPER(d.status)='I' THEN 1 ELSE 0 END) AS I,
         SUM(CASE WHEN UPPER(d.status)='S' THEN 1 ELSE 0 END) AS S,
         SUM(CASE WHEN UPPER(d.status)='A' THEN 1 ELSE 0 END) AS A,
         COUNT(*) AS total
  FROM absensi_sesi_detail d
  JOIN absensi_sesi s ON s.sesi_id=d.sesi_id
  JOIN mapel m ON m.mapel_id=s.mapel_id
  WHERE d.siswa_id=? AND s.status='final'
    AND s.tanggal BETWEEN ? AND ?
  GROUP BY m.mapel_nama
  ORDER BY m.mapel_nama ASC";
$stmr = mysqli_prepare($koneksi,$sqlRekapMapel);
mysqli_stmt_bind_param($stmr,'iss',$id_siswa,$dateStart,$dateEnd);
mysqli_stmt_execute($stmr);
$rsmr = mysqli_stmt_get_result($stmr);
while($r=mysqli_fetch_assoc($rsmr)){
  $tot = (int)$r['total'];
  $h   = (int)$r['H'];
  $rate = $tot>0 ? round($h/$tot*100,2) : 0;
  $rekapMapel[] = [
    'mapel'=>$r['mapel'],
    'H'=>$h, 'I'=>(int)$r['I'], 'S'=>(int)$r['S'], 'A'=>(int)$r['A'],
    'total'=>$tot, 'rate'=>$rate
  ];
}
mysqli_stmt_close($stmr);

// Rekap PER MAPEL PER BULAN (pivot H per bulan + total) — hanya semester
$rekapMapelBulan = [];
if ($mode !== 'bulan') {
  $sqlPivot = "SELECT m.mapel_nama AS mapel, MONTH(s.tanggal) AS m,
          SUM(CASE WHEN UPPER(d.status)='H' THEN 1 ELSE 0 END) AS H,
          COUNT(*) AS total
    FROM absensi_sesi_detail d
    JOIN absensi_sesi s ON s.sesi_id=d.sesi_id
    JOIN mapel m ON m.mapel_id=s.mapel_id
    WHERE d.siswa_id=? AND s.status='final'
      AND s.tanggal BETWEEN ? AND ?
    GROUP BY m.mapel_nama, MONTH(s.tanggal)
    ORDER BY m.mapel_nama ASC, MONTH(s.tanggal) ASC";
  $stp = mysqli_prepare($koneksi,$sqlPivot);
  mysqli_stmt_bind_param($stp,'iss',$id_siswa,$dateStart,$dateEnd);
  mysqli_stmt_execute($stp);
  $rsp = mysqli_stmt_get_result($stp);
  while($r=mysqli_fetch_assoc($rsp)){
    $mp = $r['mapel']; $m=(int)$r['m'];
    if(!isset($rekapMapelBulan[$mp])) $rekapMapelBulan[$mp]=['perbulan'=>[], 'totalH'=>0,'total'=>0];
    $rekapMapelBulan[$mp]['perbulan'][$m]=['H'=>(int)$r['H'],'total'=>(int)$r['total']];
    $rekapMapelBulan[$mp]['totalH'] += (int)$r['H'];
    $rekapMapelBulan[$mp]['total']  += (int)$r['total'];
  }
  mysqli_stmt_close($stp);
}

// Daftar mapel unik (untuk filter Mapel)
$mapelSet = [];
foreach ($rowsMapel as $rx) { $mapelSet[$rx['mapel']] = true; }
$mapelOptions = array_keys($mapelSet);
sort($mapelOptions, SORT_NATURAL | SORT_FLAG_CASE);
?>
<style>
/* ====== Styling UI yang lebih fresh ====== */
.box-action-sticky{
  position: sticky; top: -1px; z-index: 11; background:#fff; padding:10px 12px;
  border:1px solid #eee; border-radius:12px; box-shadow:0 1px 10px rgba(0,0,0,.04);
}
.btn-gradient{
  background: linear-gradient(135deg,#10b981,#059669); color:#fff; border:none; border-radius:999px;
}
.btn-gradient:hover{ filter:brightness(1.05); color:#fff; }
.badge-soft{ padding:6px 10px;border-radius:999px;background:#f4f6f9;border:1px solid #e5e7eb;font-weight:600; }
.kpi-card{
  background:linear-gradient(135deg,#f8fafc,#ffffff); border:1px solid #eef2f7; border-radius:14px; padding:12px;
  display:flex; align-items:center; gap:10px; box-shadow:0 6px 18px rgba(16,24,40,.04);
}
.kpi-dot{ width:10px; height:10px; border-radius:50%;}
.kpi-h{background:#2ECC71}.kpi-i{background:#3498DB}.kpi-s{background:#F39C12}.kpi-a{background:#E74C3C}
.table-toolbar{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin:8px 0;}
@media (max-width: 768px){
  .btn-toolbar .btn-group{ margin-bottom:6px; }
}
</style>

<div class="box box-solid" style="border-radius:12px;">
  <div class="box-body">

    <!-- FILTER + NAV BAR -->
    <div class="box-action-sticky">
      <form class="form-inline" method="get" id="filterForm" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
        <input type="hidden" name="view" value="<?php echo h($view); ?>">

        <div class="form-group">
          <label style="margin-right:6px;">Mode:</label>
          <select name="mode" class="form-control input-sm" onchange="toggleModeFields(); this.form.submit()">
            <option value="bulan"   <?php echo $mode==='bulan'?'selected':''; ?>>Bulanan</option>
            <option value="semester"<?php echo $mode==='semester'?'selected':''; ?>>Semester</option>
          </select>
        </div>

        <div id="field-bulan" class="form-group" style="<?php echo $mode==='bulan'?'':'display:none'; ?>">
          <label style="margin:0 6px 0 12px;">Bulan:</label>
          <select name="bulan" class="form-control input-sm" onchange="this.form.submit()">
            <?php for($b=1;$b<=12;$b++): ?>
              <option value="<?php echo $b; ?>" <?php echo $b==$bulan?'selected':''; ?>>
                <?php echo date('F', mktime(0,0,0,$b,1)); ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>

        <div id="field-semester" class="form-group" style="<?php echo $mode==='semester'?'':'display:none'; ?>">
          <label style="margin:0 6px 0 12px;">Semester:</label>
          <select name="sem" class="form-control input-sm" onchange="this.form.submit()">
            <option value="1" <?php echo $sem==1?'selected':''; ?>>Semester 1 (Jul–Des)</option>
            <option value="2" <?php echo $sem==2?'selected':''; ?>>Semester 2 (Jan–Jun)</option>
          </select>
        </div>

        <!-- NAV TABS QUICK -->
        <div class="btn-toolbar" role="toolbar" style="margin-left:auto">
          <div class="btn-group">
            <a class="btn btn-default btn-sm" href="?view=bulanan&<?php echo h($filterQS); ?>"><i class="fa fa-calendar"></i> Harian</a>
            <a class="btn btn-default btn-sm" href="?view=mapel&<?php echo h($filterQS); ?>"><i class="fa fa-book"></i> Per Mapel</a>
          </div>
        </div>
      </form>
    </div>

    <!-- KPI kecil / ringkas -->
    <div style="margin:12px 0;display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
      <div class="kpi-card"><span class="kpi-dot kpi-h"></span><div><div><strong>Hadir</strong></div><div class="text-muted"><?php echo (int)$kehadiran['H']; ?> kali</div></div></div>
      <div class="kpi-card"><span class="kpi-dot kpi-i"></span><div><div><strong>Izin</strong></div><div class="text-muted"><?php echo (int)$kehadiran['I']; ?> kali</div></div></div>
      <div class="kpi-card"><span class="kpi-dot kpi-s"></span><div><div><strong>Sakit</strong></div><div class="text-muted"><?php echo (int)$kehadiran['S']; ?> kali</div></div></div>
      <div class="kpi-card"><span class="kpi-dot kpi-a"></span><div><div><strong>Alpha</strong></div><div class="text-muted"><?php echo (int)$kehadiran['A']; ?> kali</div></div></div>
    </div>

    <div class="row">
      <div class="col-sm-4">
        <div style="height:190px;"><canvas id="donutHarian"></canvas></div>
        <div class="text-center text-muted" style="margin-top:6px">
          <small>Rekap Kehadiran – <?php echo h($periodeLabel); ?></small>
        </div>
      </div>
      <div class="col-sm-8">
        <?php if($mode!=='bulan'): ?>
          <div style="height:190px;"><canvas id="stackMonthly"></canvas></div>
          <div class="text-center text-muted" style="margin-top:6px">
            <small>Kehadiran per Bulan (H/I/S/A)</small>
          </div>
        <?php else: ?>
          <div class="text-center" style="margin-top:16px">
            <span class="badge-soft">Periode: <?php echo h($periodeLabel); ?></span>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Ringkasan per Bulan (hanya Semester) -->
    <?php if ($mode !== 'bulan'): ?>
      <div class="table-responsive" style="margin-top:10px">
        <table class="table table-bordered table-striped compact" id="tblRingkas">
          <thead>
            <tr>
              <th style="width:160px">Bulan</th>
              <th class="text-center">H</th>
              <th class="text-center">I</th>
              <th class="text-center">S</th>
              <th class="text-center">A</th>
              <th class="text-center">Total</th>
            </tr>
          </thead>
          <tbody>
            <?php
              for ($m=$monthStart; $m <= $monthEnd; $m++):
                $r = $rekapBulan[$m] ?? ['H'=>0,'I'=>0,'S'=>0,'A'=>0];
                $tot = $r['H']+$r['I']+$r['S']+$r['A'];
                $yDisp = ($m>=7) ? $TAStartYear : $TAEndYear; // tampilkan tahun sesuai bulan
            ?>
              <tr>
                <td><?php echo date('F', mktime(0,0,0,$m,1))." $yDisp"; ?></td>
                <td class="text-center"><span class="label label-<?php echo $badge['H']; ?>"><?php echo (int)$r['H']; ?></span></td>
                <td class="text-center"><span class="label label-<?php echo $badge['I']; ?>"><?php echo (int)$r['I']; ?></span></td>
                <td class="text-center"><span class="label label-<?php echo $badge['S']; ?>"><?php echo (int)$r['S']; ?></span></td>
                <td class="text-center"><span class="label label-<?php echo $badge['A']; ?>"><?php echo (int)$r['A']; ?></span></td>
                <td class="text-center"><strong><?php echo (int)$tot; ?></strong></td>
              </tr>
            <?php endfor; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs" style="margin-top:10px">
      <li class="<?php echo ($view=='bulanan'?'active':''); ?>">
        <a href="?view=bulanan&<?php echo h($filterQS); ?>"><i class="fa fa-calendar"></i> Harian</a>
      </li>
      <li class="<?php echo ($view=='mapel'?'active':''); ?>">
        <a href="?view=mapel&<?php echo h($filterQS); ?>"><i class="fa fa-book"></i> Per Mapel</a>
      </li>
      <li>
        <a href="#tab_rekap_mapel" data-toggle="tab"><i class="fa fa-pie-chart"></i> Rekap Per Mapel</a>
      </li>
      <?php if($mode!=='bulan'): ?>
      <li>
        <a href="#tab_rekap_mapel_bulan" data-toggle="tab"><i class="fa fa-th"></i> Rekap Mapel per Bulan</a>
      </li>
      <?php endif; ?>
    </ul>

    <div class="tab-content">
      <!-- Tabel Harian -->
      <div class="tab-pane <?php echo ($view=='bulanan'?'active':''); ?>" id="tab_harian">
        <div class="table-toolbar">
          <label>Status: </label>
          <select id="fltStatusHarian" class="form-control input-sm">
            <option value="">Semua</option>
            <option value="H">Hadir</option>
            <option value="I">Izin</option>
            <option value="S">Sakit</option>
            <option value="A">Alpha</option>
          </select>
        </div>
        <div class="table-responsive" style="margin-top:6px">
          <table class="table table-bordered table-striped display nowrap" id="tblHarian" style="width:100%">
            <thead>
              <tr><th style="width:160px">Tanggal</th><th>Status</th></tr>
            </thead>
            <tbody>
              <?php if(empty($rowsHarian)): ?>
                <tr><td colspan="2" class="text-center text-muted">Belum ada data.</td></tr>
              <?php else: foreach($rowsHarian as $r): $s = strtoupper($r['status']); ?>
                <tr>
                  <td><?php
                    // tampilkan tanggal dengan tahun sebenarnya
                    echo date('d M Y', strtotime($r['tanggal']));
                  ?></td>
                  <td><span class="label label-<?php echo $badge[$s] ?? 'default'; ?>" title="<?php echo $s; ?>"><?php echo $s; ?></span></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tabel Per Mapel (detail) -->
      <div class="tab-pane <?php echo ($view=='mapel'?'active':''); ?>" id="tab_mapel">
        <div class="table-toolbar">
          <label>Mapel: </label>
          <select id="fltMapel" class="form-control input-sm">
            <option value="">Semua</option>
            <?php foreach($mapelOptions as $mp): ?>
              <option value="<?php echo h($mp); ?>"><?php echo h($mp); ?></option>
            <?php endforeach; ?>
          </select>
          <label>Status: </label>
          <select id="fltStatusMapel" class="form-control input-sm">
            <option value="">Semua</option>
            <option value="H">Hadir</option>
            <option value="I">Izin</option>
            <option value="S">Sakit</option>
            <option value="A">Alpha</option>
          </select>
        </div>
        <div class="table-responsive" style="margin-top:6px">
          <table class="table table-bordered table-striped display nowrap" id="tblMapel" style="width:100%">
            <thead>
              <tr>
                <th style="width:160px">Tanggal</th>
                <th>Mapel</th>
                <th style="width:90px">Jam ke</th>
                <th style="width:100px">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($rowsMapel)): ?>
                <tr><td colspan="4" class="text-center text-muted">Belum ada data.</td></tr>
              <?php else: foreach($rowsMapel as $r): $s = strtoupper($r['status']); ?>
                <tr>
                  <td><?php echo date('d M Y', strtotime($r['tanggal'])); ?></td>
                  <td><?php echo h($r['mapel']); ?></td>
                  <td class="text-center"><?php echo (int)$r['jam_ke']; ?></td>
                  <td><span class="label label-<?php echo $badge[$s] ?? 'default'; ?>" title="<?php echo $s; ?>"><?php echo $s; ?></span></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Rekap Per Mapel (periode) -->
      <div class="tab-pane" id="tab_rekap_mapel">
        <div class="table-responsive" style="margin-top:10px">
          <table class="table table-bordered table-striped display nowrap" id="tblRekapMapel" style="width:100%">
            <thead>
              <tr>
                <th>Mapel</th><th>H</th><th>I</th><th>S</th><th>A</th><th>Total</th><th>Rate Hadir (%)</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($rekapMapel)): ?>
                <tr><td colspan="7" class="text-center text-muted">Belum ada data.</td></tr>
              <?php else: foreach($rekapMapel as $r): ?>
                <tr>
                  <td><?php echo h($r['mapel']); ?></td>
                  <td class="text-center"><span class="label label-success"><?php echo (int)$r['H']; ?></span></td>
                  <td class="text-center"><span class="label label-info"><?php echo (int)$r['I']; ?></span></td>
                  <td class="text-center"><span class="label label-warning"><?php echo (int)$r['S']; ?></span></td>
                  <td class="text-center"><span class="label label-danger"><?php echo (int)$r['A']; ?></span></td>
                  <td class="text-center"><strong><?php echo (int)$r['total']; ?></strong></td>
                  <td class="text-center"><?php echo number_format($r['rate'],2,',','.'); ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Rekap Mapel per Bulan (pivot) — hanya Semester -->
      <?php if($mode!=='bulan'): ?>
      <div class="tab-pane" id="tab_rekap_mapel_bulan">
        <div class="table-responsive" style="margin-top:10px">
          <table class="table table-bordered table-striped display nowrap" id="tblRekapMapelPivot" style="width:100%">
            <thead>
              <tr>
                <th>Mapel</th>
                <?php for($m=$monthStart;$m<=$monthEnd;$m++):
                  $yDisp = ($m>=7) ? $TAStartYear : $TAEndYear; ?>
                  <th><?php echo date('M', mktime(0,0,0,$m,1)).' '.$yDisp; ?><br><small>Hadir</small></th>
                <?php endfor; ?>
                <th>Total H</th>
                <th>Total Pertemuan</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($rekapMapelBulan)): ?>
                <tr><td colspan="<?php echo ($monthEnd-$monthStart+1)+3; ?>" class="text-center text-muted">Belum ada data.</td></tr>
              <?php else: foreach($rekapMapelBulan as $mp=>$rec): ?>
                <tr>
                  <td><?php echo h($mp); ?></td>
                  <?php for($m=$monthStart;$m<=$monthEnd;$m++):
                    $val = arr_get(arr_get($rec,'perbulan',[]),$m,['H'=>0])['H']; ?>
                    <td class="text-center"><?php echo (int)$val; ?></td>
                  <?php endfor; ?>
                  <td class="text-center"><strong><?php echo (int)$rec['totalH']; ?></strong></td>
                  <td class="text-center"><strong><?php echo (int)$rec['total']; ?></strong></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- DataTables (interaktif + responsif) -->
<link rel="stylesheet" href="https://cdn.datatables.net/v/bs/dt-2.0.7/r-3.0.3/datatables.min.css"/>
<script src="https://cdn.datatables.net/v/bs/dt-2.0.7/r-3.0.3/datatables.min.js"></script>

<script>
function toggleModeFields(){
  var mode = document.querySelector('select[name="mode"]').value;
  document.getElementById('field-bulan').style.display    = (mode==='bulan') ? '' : 'none';
  document.getElementById('field-semester').style.display = (mode==='semester') ? '' : 'none';
}

document.addEventListener('DOMContentLoaded', function(){
  toggleModeFields();

  // Donut
  var el = document.getElementById('donutHarian');
  if(el){
    new Chart(el, {
      type:'doughnut',
      data:{
        labels:['Hadir','Izin','Sakit','Alpha'],
        datasets:[{
          data:[<?php echo (int)$kehadiran['H'];?>,<?php echo (int)$kehadiran['I'];?>,<?php echo (int)$kehadiran['S'];?>,<?php echo (int)$kehadiran['A'];?>],
          backgroundColor: ['#2ECC71','#3498DB','#F39C12','#E74C3C'],
          hoverBackgroundColor: ['#2ECC71','#3498DB','#F39C12','#E74C3C'],
          borderWidth: 0
        }]
      },
      options:{ cutout:'65%', plugins:{legend:{position:'bottom'}}, maintainAspectRatio:false }
    });
  }

  // Stacked bar per bulan (hanya semester)
  <?php if($mode!=='bulan'): ?>
    var el2 = document.getElementById('stackMonthly');
    if(el2){
      var months = [
        <?php for($m=$monthStart;$m<=$monthEnd;$m++): ?>
          '<?php echo date('M', mktime(0,0,0,$m,1)); ?>',
        <?php endfor; ?>
      ];
      var dataH=[], dataI=[], dataS=[], dataA=[];
      <?php for($m=$monthStart;$m<=$monthEnd;$m++): $r=$rekapBulan[$m] ?? ['H'=>0,'I'=>0,'S'=>0,'A'=>0]; ?>
        dataH.push(<?php echo (int)$r['H']; ?>);
        dataI.push(<?php echo (int)$r['I']; ?>);
        dataS.push(<?php echo (int)$r['S']; ?>);
        dataA.push(<?php echo (int)$r['A']; ?>);
      <?php endfor; ?>

      new Chart(el2, {
        type:'bar',
        data:{
          labels: months,
          datasets:[
            {label:'H', data:dataH, backgroundColor:'#2ECC71'},
            {label:'I', data:dataI, backgroundColor:'#3498DB'},
            {label:'S', data:dataS, backgroundColor:'#F39C12'},
            {label:'A', data:dataA, backgroundColor:'#E74C3C'}
          ]
        },
        options:{
          maintainAspectRatio:false,
          responsive:true,
          plugins:{legend:{position:'bottom'}},
          scales:{ x:{stacked:true}, y:{stacked:true, beginAtZero:true} }
        }
      });
    }
  <?php endif; ?>

  // DataTables init
  var dtOpts = {responsive:true, pageLength:10, lengthChange:false, order:[], autoWidth:false};
  var dtH=null, dtM=null, dtRM=null, dtRKP=null, dtRing=null;

  if (document.getElementById('tblHarian')) dtH = $('#tblHarian').DataTable(dtOpts);
  if (document.getElementById('tblMapel'))  dtM = $('#tblMapel').DataTable(dtOpts);
  if (document.getElementById('tblRekapMapel')) dtRM = $('#tblRekapMapel').DataTable(dtOpts);
  if (document.getElementById('tblRekapMapelPivot')) dtRKP = $('#tblRekapMapelPivot').DataTable(dtOpts);
  if (document.getElementById('tblRingkas')) dtRing = $('#tblRingkas').DataTable(dtOpts);

  // Filter interaktif — Harian
  var fltH = document.getElementById('fltStatusHarian');
  if (fltH && dtH){
    fltH.addEventListener('change', function(){
      var val = this.value;
      if (val) dtH.column(1).search('^'+val+'$', true, false).draw();
      else dtH.column(1).search('').draw();
    });
  }

  // Filter interaktif — Mapel
  var fltMapel = document.getElementById('fltMapel');
  var fltStatusMapel = document.getElementById('fltStatusMapel');
  if (dtM){
    if (fltMapel){
      fltMapel.addEventListener('change', function(){
        var val = this.value;
        if (val) dtM.column(1).search(val, true, false).draw();
        else dtM.column(1).search('').draw();
      });
    }
    if (fltStatusMapel){
      fltStatusMapel.addEventListener('change', function(){
        var val = this.value;
        if (val) dtM.column(3).search('^'+val+'$', true, false).draw();
        else dtM.column(3).search('').draw();
      });
    }
  }
});
</script>

  </section>
</div>
<?php include 'footer.php'; ?>
