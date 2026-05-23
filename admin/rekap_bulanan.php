<?php
// admin/rekap_bulanan.php — Rekap Absensi Bulanan (weekday only) + Print/PDF/Excel
// - Sel harian full color (H=Hijau, S=Kuning, I=Biru, A=Merah; kosong=abu)
// - Hanya Senin–Jumat (Sabtu/Minggu disembunyikan)
// - HBE per siswa = jumlah hari yang ada catatan H/S/I/A pada bulan tsb
// - % HADIR = H / HBE * 100 (full H = 100%)
// - Ambang % hadir: ≥90% hijau, 70–89% oranye, <70% merah
// - Footer rekap total kelas + tanda tangan wali kelas
// - Unduh PDF (dompdf) & Excel (HTML-Excel)

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../koneksi.php';

// ===== RBAC singkat (selaras data_absensi.php) =====
$__auth = __DIR__ . '/../includes/auth.php';
if (is_file($__auth)) { require_once $__auth; if (function_exists('ensure_logged_in')) ensure_logged_in(); }

$user_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : (function_exists('current_user_id') ? (int) current_user_id() : 0);
$IS_ADMIN = function_exists('_is_admin') ? _is_admin() : (isset($_SESSION['level']) && $_SESSION['level']==='administrator');
$IS_GURU  = function_exists('_is_guru')  ? _is_guru()  : false;
$IS_TAS   = function_exists('_is_tas')   ? _is_tas()   : false;
$CAN_VIEW_ALL = function_exists('user_can') ? user_can('attendance.view_all') : false;

if (!($IS_ADMIN || $IS_TAS || $IS_GURU)) { http_response_code(403); die('Akses ditolak.'); }

function esc($s){ global $koneksi; return mysqli_real_escape_string($koneksi, $s); }
function get_ta_aktif(){
  global $koneksi;
  $q = mysqli_query($koneksi, "SELECT ta_id FROM ta WHERE ta_status=1 LIMIT 1");
  if ($q && $r = mysqli_fetch_assoc($q)) return (int)$r['ta_id'];
  return 0;
}

// ===== Param dasar =====
$TA       = get_ta_aktif(); if ($TA<=0) { http_response_code(500); die('Tahun ajaran aktif tidak ditemukan.'); }
$kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
$bulan    = isset($_GET['bulan']) ? max(1, min(12, (int)$_GET['bulan'])) : (int)date('n');
$tahun    = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

// Validasi minimal
if ($kelas_id <= 0) {
  http_response_code(400);
  echo '<!doctype html><meta charset="utf-8"><div style="padding:20px;font:14px/1.4 sans-serif">';
  echo '<b>Rekap Bulanan</b><br>Silakan pilih & <b>Terapkan</b> kelas pada halaman "Data Absensi", lalu klik tombol <i>Rekap Bulanan</i>.';
  echo '</div>'; exit;
}

// Batasi akses guru (kecuali boleh lihat semua)
if (!$CAN_VIEW_ALL && $IS_GURU) {
  $cek = mysqli_query($koneksi, "SELECT 1 FROM pengampu_mapel WHERE ta_id=$TA AND kelas_id=$kelas_id AND guru_user_id=$user_id LIMIT 1");
  if (!$cek || mysqli_num_rows($cek)==0) { http_response_code(403); die('Akses ditolak untuk kelas ini.'); }
}

// Nama kelas
$kelas_nama = '-';
$qq = mysqli_query($koneksi, "SELECT kelas_nama FROM kelas WHERE kelas_id=$kelas_id");
if ($qq && $rr = mysqli_fetch_assoc($qq)) { $kelas_nama = $rr['kelas_nama']; }

// Range bulan
$start   = sprintf('%04d-%02d-01', $tahun, $bulan);
$lastDay = (int)date('t', strtotime($start));
$end     = sprintf('%04d-%02d-%02d', $tahun, $bulan, $lastDay);

// Hanya Senin–Jumat
$hari_kerja = [];   // ['d'=>1..31, 'ymd'=>'Y-m-d']
for ($d=1; $d <= $lastDay; $d++) {
  $ymd = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
  $dow = (int)date('N', strtotime($ymd)); // 1=Mon ... 7=Sun
  if ($dow >= 1 && $dow <= 5) $hari_kerja[] = ['d'=>$d, 'ymd'=>$ymd];
}

// Daftar siswa
$students = [];
$qs = mysqli_query($koneksi, "
  SELECT s.siswa_id, s.siswa_nis, s.siswa_nama
  FROM kelas_siswa ks
  JOIN siswa s ON s.siswa_id = ks.ks_siswa
  WHERE ks.ks_kelas = $kelas_id
  ORDER BY s.siswa_nama ASC
");
while($row = mysqli_fetch_assoc($qs)) $students[] = $row;

// Matrix status harian bulan ini
$matrix = []; // $matrix[siswa_id][Y-m-d] = 'H'/'S'/'I'/'A'
$qm = mysqli_query($koneksi, "
  SELECT d.siswa_id, h.tanggal, d.status
  FROM absensi_harian h
  JOIN absensi_harian_detail d ON d.harian_id = h.harian_id
  WHERE h.ta_id = $TA AND h.kelas_id = $kelas_id
    AND h.tanggal BETWEEN '".esc($start)."' AND '".esc($end)."'
");
while($m = mysqli_fetch_assoc($qm)){
  $sid = (int)$m['siswa_id']; $tgl = $m['tanggal'];
  $matrix[$sid][$tgl] = $m['status'];
}

// Nama bulan (locale ID)
$MONTH_ID = strtoupper(date('F', strtotime($start)));
setlocale(LC_TIME, 'id_ID.UTF-8','id_ID','id');
$nama_bulan = function_exists('strftime')
  ? (strtoupper(strftime('%B', strtotime($start))) ?: $MONTH_ID)
  : $MONTH_ID;

// helper tanggal Indonesia untuk tanda tangan
function tgl_indo($ts=null){
  if ($ts===null) $ts=time();
  $MONTH_EN = strtoupper(date('F', $ts));
  $indo = function_exists('strftime') ? strftime('%d %B %Y', $ts) : date('d ', $ts).$MONTH_EN.' '.date('Y',$ts);
  return $indo;
}

// ====== Render HTML body ======
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Rekap Absensi Bulanan — <?php echo htmlspecialchars($kelas_nama).' ('.$nama_bulan.' '.$tahun.')'; ?></title>
  <link rel="stylesheet" href="../assets/bower_components/bootstrap/dist/css/bootstrap.min.css">
  <style>
    :root{
      --c-hijau:#34a853; --c-kuning:#fbbc04; --c-biru:#1a73e8; --c-merah:#ea4335;
      --c-gray:#eeeeee; --c-gray-text:#666;
      --c-orange:#fb8c00; --c-green-dark:#2e7d32; --c-red-dark:#c62828;
      --c-head:#f5f5f5;
    }
    html,body{ background:#fff; }
    .page-wrapper{ padding:10px; font-size:12px; }
    .header{ margin-bottom:10px; }
    .header h3{ font-weight:700; margin:0 0 6px; }
    .meta{ color:#444; }
    .legend-box{ display:inline-block; width:12px; height:12px; margin-right:6px; border-radius:2px; }
    .lg-H{ background:var(--c-hijau); } .lg-S{ background:var(--c-kuning); }
    .lg-I{ background:var(--c-biru); }  .lg-A{ background:var(--c-merah); }

    table.rekap{ border-collapse:collapse; width:100%; font-size:11px; table-layout:fixed; }
    table.rekap th, table.rekap td{ border:1px solid #ccc; padding:3px 4px; text-align:center; vertical-align:middle; }
    table.rekap th.name, table.rekap td.name{
      text-align:left; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
      position:sticky; left:0; background:#fff; z-index:1;
    }
    table.rekap th.sticky{ position:sticky; top:0; background:var(--c-head); z-index:2; }

    /* Sel harian penuh warna */
    .cell-H{ background:var(--c-hijau); color:#fff; font-weight:700; }
    .cell-S{ background:var(--c-kuning); color:#333; font-weight:700; }
    .cell-I{ background:var(--c-biru);  color:#fff; font-weight:700; }
    .cell-A{ background:var(--c-merah); color:#fff; font-weight:700; }
    .cell-null{ background:var(--c-gray); color:var(--c-gray-text); }

    /* Rekap totals berwarna jika > 0 */
    .sum{ font-weight:700; }
    .sum-0{ background:#fafafa; color:#9e9e9e; }
    .sum-H{ background:var(--c-hijau); color:#fff; }
    .sum-S{ background:var(--c-kuning); color:#333; }
    .sum-I{ background:var(--c-biru);  color:#fff; }
    .sum-A{ background:var(--c-merah); color:#fff; }

    /* % hadir dengan ambang warna (≥90 hijau, 70–89 oranye, <70 merah) */
    .pct{ font-weight:700; }
    .pct-good{ background:var(--c-green-dark); color:#fff; }
    .pct-mid{  background:var(--c-orange);     color:#fff; }
    .pct-bad{  background:var(--c-red-dark);   color:#fff; }

    tfoot td{ background:#f0f3f7; font-weight:700; }

    /* Print tweaks: A4 & F4 landscape, semua kolom muat */
    @page { size: A4 landscape; margin:10mm; }
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    @media print{
      .no-print{ display:none !important; }
      .page-wrapper{ padding:0; }
      table.rekap th, table.rekap td{ padding:2px 3px; font-size:9.5px; }
      table.rekap th.name, table.rekap td.name{ position:static; background:#fff; }
      table.rekap th.sticky{ position:static; }
    }
  </style>
</head>
<body>
<div class="page-wrapper">
  <div class="header">
    <h3>Rekap Absensi Bulanan</h3>
    <div class="meta" title="HBE dihitung per siswa sebagai jumlah hari yang terisi H/S/I/A pada bulan ini.">
      <b>Kelas:</b> <?php echo htmlspecialchars($kelas_nama); ?> &nbsp; | &nbsp;
      <b>Bulan:</b> <?php echo htmlspecialchars($nama_bulan.' '.$tahun); ?> &nbsp; | &nbsp;
      <b>HBE:</b> per siswa (jumlah hari terisi H/S/I/A)
    </div>
    <div class="legend" style="margin-top:6px">
      <span class="legend-box lg-H"></span>Hadir &nbsp;&nbsp;
      <span class="legend-box lg-S"></span>Sakit &nbsp;&nbsp;
      <span class="legend-box lg-I"></span>Izin &nbsp;&nbsp;
      <span class="legend-box lg-A"></span>Alfa
    </div>
    <div class="no-print" style="margin-top:10px">
      <a class="btn btn-default btn-sm" href="javascript:window.print()" title="Cetak ke kertas (A4/F4, landscape)"><i class="glyphicon glyphicon-print"></i> Cetak / Print</a>
      <a class="btn btn-primary btn-sm" href="?kelas_id=<?php echo $kelas_id; ?>&bulan=<?php echo $bulan; ?>&tahun=<?php echo $tahun; ?>&pdf=1" title="Unduh PDF (memerlukan dompdf)"><i class="glyphicon glyphicon-download"></i> Unduh PDF</a>
      <a class="btn btn-success btn-sm" href="?kelas_id=<?php echo $kelas_id; ?>&bulan=<?php echo $bulan; ?>&tahun=<?php echo $tahun; ?>&excel=1" title="Unduh Excel"><i class="glyphicon glyphicon-list-alt"></i> Unduh Excel</a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="rekap table table-bordered table-striped">
      <thead>
        <tr>
          <th class="sticky" style="width:34px">NO</th>
          <th class="sticky name" style="width:90px">NIS</th>
          <th class="sticky name" style="width:220px">NAMA</th>
<?php foreach($hari_kerja as $hk): ?>
          <th class="sticky" style="width:24px" title="Tanggal <?php echo (int)$hk['d']; ?>"><?php echo (int)$hk['d']; ?></th>
<?php endforeach; ?>
          <th class="sticky" style="width:36px">H</th>
          <th class="sticky" style="width:36px">S</th>
          <th class="sticky" style="width:36px">I</th>
          <th class="sticky" style="width:36px">A</th>
          <th class="sticky" style="width:44px">HBE</th>
          <th class="sticky" style="width:70px">% HADIR</th>
        </tr>
      </thead>
      <tbody>
<?php
$no=1;
$totH=$totS=$totI=$totA=$totHBE=0;
$sumPct=0; $cntPct=0;

foreach($students as $s):
  $sid = (int)$s['siswa_id'];
  $cnt = ['H'=>0,'S'=>0,'I'=>0,'A'=>0];
  $hbe_i = 0; // HBE per siswa
?>
        <tr>
          <td><?php echo $no++; ?></td>
          <td class="name"><?php echo htmlspecialchars($s['siswa_nis']); ?></td>
          <td class="name"><?php echo htmlspecialchars($s['siswa_nama']); ?></td>
<?php foreach($hari_kerja as $hk):
  $tgl = $hk['ymd'];
  $st  = isset($matrix[$sid][$tgl]) ? $matrix[$sid][$tgl] : null;
  if ($st) { $hbe_i++; if (isset($cnt[$st])) $cnt[$st]++; }
  $cls = $st ? 'cell-'.$st : 'cell-null';
  $txt = $st ? $st : '–';
?>
          <td class="<?php echo $cls; ?>"><?php echo $txt; ?></td>
<?php endforeach; ?>
<?php
  // kelas warna untuk rekap totals
  $clsH = $cnt['H']>0 ? 'sum sum-H' : 'sum sum-0';
  $clsS = $cnt['S']>0 ? 'sum sum-S' : 'sum sum-0';
  $clsI = $cnt['I']>0 ? 'sum sum-I' : 'sum sum-0';
  $clsA = $cnt['A']>0 ? 'sum sum-A' : 'sum sum-0';

  // % hadir berbasis HBE per siswa
  $persen = $hbe_i > 0 ? round(($cnt['H'] / $hbe_i) * 100, 2) : 0.00;
  $pcls = ($persen >= 90) ? 'pct pct-good' : (($persen >= 70) ? 'pct pct-mid' : 'pct pct-bad');

  // akumulasi total kelas
  $totH += $cnt['H']; $totS += $cnt['S']; $totI += $cnt['I']; $totA += $cnt['A']; $totHBE += $hbe_i;
  if ($hbe_i > 0) { $sumPct += $persen; $cntPct++; }
?>
          <td class="<?php echo $clsH; ?>"><?php echo (int)$cnt['H']; ?></td>
          <td class="<?php echo $clsS; ?>"><?php echo (int)$cnt['S']; ?></td>
          <td class="<?php echo $clsI; ?>"><?php echo (int)$cnt['I']; ?></td>
          <td class="<?php echo $clsA; ?>"><?php echo (int)$cnt['A']; ?></td>
          <td><b><?php echo (int)$hbe_i; ?></b></td>
          <td class="<?php echo $pcls; ?>"><?php echo number_format($persen, 2); ?>%</td>
        </tr>
<?php endforeach; ?>
      </tbody>
<?php
  $avgPct = $cntPct>0 ? round($sumPct/$cntPct, 2) : 0.00;
  $avgCls = ($avgPct >= 90) ? 'pct pct-good' : (($avgPct >= 70) ? 'pct pct-mid' : 'pct pct-bad');
?>
      <tfoot>
        <tr>
          <td class="text-center" colspan="<?php echo 3+count($hari_kerja); ?>"><b>REKAP TOTAL KELAS</b></td>
          <td class="sum sum-H"><?php echo (int)$totH; ?></td>
          <td class="sum sum-S"><?php echo (int)$totS; ?></td>
          <td class="sum sum-I"><?php echo (int)$totI; ?></td>
          <td class="sum sum-A"><?php echo (int)$totA; ?></td>
          <td><b><?php echo (int)$totHBE; ?></b></td>
          <td class="<?php echo $avgCls; ?>"><?php echo number_format($avgPct, 2); ?>%</td>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- Tanda tangan -->
  <div style="margin-top:30px">
    <div style="text-align:right; margin-bottom:60px;">
      Tasikmalaya, <?php echo htmlspecialchars(tgl_indo()); ?><br>
      Mengetahui,<br>
      Wali Kelas
      <br><br><br><br>
      <span style="display:inline-block; min-width:240px; border-top:1px solid #333; margin-top:10px;">&nbsp;</span>
    </div>
  </div>
</div>
</body>
</html>
<?php
$html = ob_get_clean();

// ===== Export Excel (HTML-Excel) =====
if (isset($_GET['excel']) && (int)$_GET['excel'] === 1) {
  $fname = 'rekap-absensi-'.preg_replace('/[^a-z0-9_\-]+/i','_',$kelas_nama).'-'.$bulan.'-'.$tahun.'.xls';
  header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
  header("Content-Disposition: attachment; filename=\"$fname\"");
  echo $html; exit;
}

// ===== PDF (opsional Dompdf) =====
if (isset($_GET['pdf']) && (int)$_GET['pdf'] === 1) {
  // coba autoload Composer bila ada
  $autoloads = [ __DIR__ . '/../vendor/autoload.php', __DIR__ . '/../../vendor/autoload.php' ];
  foreach ($autoloads as $auto) { if (is_file($auto)) { require_once $auto; break; } }

  if (class_exists('\Dompdf\Dompdf')) {
    $dompdf = new \Dompdf\Dompdf();
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->loadHtml($html);
    $dompdf->render();
    $fname = 'rekap-absensi-'.preg_replace('/[^a-z0-9_\-]+/i','_',$kelas_nama).'-'.$bulan.'-'.$tahun.'.pdf';
    $dompdf->stream($fname, ['Attachment'=>true]);
    exit;
  } else {
    echo '<div style="padding:10px;background:#fff7e6;border:1px solid #ffd591;margin:10px;" class="no-print">';
    echo '<b>Catatan:</b> Ekspor PDF membutuhkan <code>dompdf/dompdf</code>. Install via Composer: ';
    echo '<code>composer require dompdf/dompdf</code></div>';
    echo $html; exit;
  }
}

echo $html;
