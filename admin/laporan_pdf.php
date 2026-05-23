<?php
// ====== DEBUG aman (matikan di produksi jika mau) ======
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
ob_start();

/* ====== Temukan koneksi ($koneksi) tanpa merender layout ====== */
$koneksi = null;
$try = [
  __DIR__.'/koneksi.php',
  __DIR__.'/../koneksi.php',
  __DIR__.'/../config/koneksi.php',
  __DIR__.'/config/koneksi.php',
  __DIR__.'/includes/koneksi.php',
  __DIR__.'/../includes/koneksi.php'
];
foreach($try as $p){
  if(file_exists($p)){
    require_once $p;
    break;
  }
}
if(!isset($koneksi)){
  // fallback: include header.php lalu buang outputnya (demi $koneksi)
  $hp = __DIR__.'/header.php';
  if(file_exists($hp)){
    ob_start();
    include $hp;
    ob_end_clean();
  }
}
if(!isset($koneksi)){
  die("Koneksi database tidak ditemukan. Pastikan file koneksi.php atau header.php tersedia.");
}

/* ====== Helper & parameter ====== */
function esc($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function getv($k,$d=''){ return isset($_GET[$k])?$_GET[$k]:$d; }
function asInt($v){ return (int)$v; }
function build_url(array $extra=[]){
  $params = $_GET;
  foreach($extra as $k=>$v){
    if($v===null){ unset($params[$k]); } else { $params[$k]=$v; }
  }
  return basename(__FILE__).'?'.http_build_query($params);
}

$ta_id   = asInt(getv('ta_id', 0));     // 0 = semua TA
$kelas_id= asInt(getv('kelas_id', 0));  // 0 = semua
$bulan   = asInt(getv('bulan', 0));     // 0 = semua
$urutkan = trim(getv('urutkan',''));
$view    = trim(getv('view','siswa'));  // siswa | kelas
$download= asInt(getv('download',0));   // 1 = unduh PDF

$bulanNama = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

/* ====== Build SQL (sama seperti halaman laporan) ====== */
$filtersPrestasi=[]; $filtersPelanggaran=[];
if($ta_id>0){ $filtersPrestasi[]="k.kelas_ta=".$ta_id; $filtersPelanggaran[]="k2.kelas_ta=".$ta_id; }
if($kelas_id>0){ $filtersPrestasi[]="ip.kelas=".$kelas_id; $filtersPelanggaran[]="ig.kelas=".$kelas_id; }
if($bulan>=1 && $bulan<=12){ $filtersPrestasi[]="MONTH(ip.waktu)=".$bulan; $filtersPelanggaran[]="MONTH(ig.waktu)=".$bulan; }

$wherePrestasi    = $filtersPrestasi?("WHERE ".implode(" AND ",$filtersPrestasi)):"";
$wherePelanggaran = $filtersPelanggaran?("WHERE ".implode(" AND ",$filtersPelanggaran)):"";

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

$orderSql = " ORDER BY s.siswa_nama ASC ";
switch($urutkan){
  case 'prestasi_terbanyak':   $orderSql=" ORDER BY COALESCE(p.total_prestasi,0) DESC, COALESCE(g.total_pelanggaran,0) ASC, s.siswa_nama ASC "; break;
  case 'pelanggaran_terbanyak':$orderSql=" ORDER BY COALESCE(g.total_pelanggaran,0) DESC, COALESCE(p.total_prestasi,0) ASC, s.siswa_nama ASC "; break;
  case 'net_point_tertinggi':  $orderSql=" ORDER BY (COALESCE(p.total_prestasi,0)-COALESCE(g.total_pelanggaran,0)) DESC, s.siswa_nama ASC "; break;
  case 'net_point_terendah':   $orderSql=" ORDER BY (COALESCE(p.total_prestasi,0)-COALESCE(g.total_pelanggaran,0)) ASC, s.siswa_nama ASC "; break;
}

$sqlPerSiswa = "
  SELECT s.siswa_id, s.siswa_nama, s.siswa_nis,
         COALESCE(p.total_prestasi,0) AS total_prestasi,
         COALESCE(g.total_pelanggaran,0) AS total_pelanggaran
  FROM siswa s
  JOIN ( $subIDs ) ids ON ids.siswa_id = s.siswa_id
  LEFT JOIN ( $subPrestasi ) p ON p.siswa = s.siswa_id
  LEFT JOIN ( $subPelanggaran ) g ON g.siswa = s.siswa_id
  $orderSql
";

$filtersKelas = [];
if($ta_id>0){ $filtersKelas[]="k.kelas_ta=".$ta_id; }
if($kelas_id>0){ $filtersKelas[]="k.kelas_id=".$kelas_id; }
$whereKelas = $filtersKelas ? "WHERE ".implode(" AND ",$filtersKelas) : "";

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

$orderKelas = " ORDER BY k.kelas_nama ASC ";
switch($urutkan){
  case 'prestasi_terbanyak':   $orderKelas=" ORDER BY COALESCE(p.total_prestasi,0) DESC, k.kelas_nama ASC "; break;
  case 'pelanggaran_terbanyak':$orderKelas=" ORDER BY COALESCE(g.total_pelanggaran,0) DESC, k.kelas_nama ASC "; break;
  case 'net_point_tertinggi':  $orderKelas=" ORDER BY (COALESCE(p.total_prestasi,0)-COALESCE(g.total_pelanggaran,0)) DESC, k.kelas_nama ASC "; break;
  case 'net_point_terendah':   $orderKelas=" ORDER BY (COALESCE(p.total_prestasi,0)-COALESCE(g.total_pelanggaran,0)) ASC, k.kelas_nama ASC "; break;
}

$sqlPerKelas = "
  SELECT k.kelas_id, k.kelas_nama,
         COALESCE(p.total_prestasi,0) AS total_prestasi,
         COALESCE(g.total_pelanggaran,0) AS total_pelanggaran
  FROM kelas k
  $whereKelas
  LEFT JOIN ( $subKelasPrestasi ) p ON p.kelas_id = k.kelas_id
  LEFT JOIN ( $subKelasPelanggaran ) g ON g.kelas_id = k.kelas_id
  $orderKelas
";

/* ====== Label filter ====== */
$ta_nama = ($ta_id>0) ? '' : 'Semua Tahun Ajaran';
if($ta_id>0){
  $t = mysqli_query($koneksi, "SELECT ta_nama FROM ta WHERE ta_id=".$ta_id);
  if($t && $tt = mysqli_fetch_assoc($t)) $ta_nama = $tt['ta_nama'];
}
$kelas_nama = ($kelas_id>0) ? '' : 'Semua';
if($kelas_id>0){
  $k = mysqli_query($koneksi, "SELECT kelas_nama FROM kelas WHERE kelas_id=".$kelas_id);
  if($k && $kk = mysqli_fetch_assoc($k)) $kelas_nama = $kk['kelas_nama'];
}

/* ====== HTML laporan ====== */
$judul = ($view==='kelas') ? 'LAPORAN REKAP POINT PER KELAS' : 'LAPORAN POINT PRESTASI & PELANGGARAN SISWA';

$css = '
<style>
  /* --- Tipografi & layout umum (aman untuk mPDF) --- */
  * { font-family: "DejaVu Sans", Arial, Helvetica, sans-serif; }
  html, body { margin:0; padding:0; }
  body { margin:16px; font-size:12.5px; color:#222; }
  h2 {
    text-align:center; margin:0 0 10px 0; font-weight:700; letter-spacing:.3px;
  }
  .subtitle { text-align:center; margin:2px 0 16px; font-size:11.5px; color:#555; }

  /* --- Panel informasi filter --- */
  table.info { width:100%; border-collapse:separate; border-spacing:0; margin-bottom:14px; }
  table.info td { padding:6px 10px; vertical-align:top; font-size:12px; }
  .info-wrap {
    border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;
    background:#fafafa;
  }
  .info-head { background:#0d47a1; color:#fff; font-weight:700; padding:8px 10px; }
  .info-body { padding:6px 10px; }

  /* --- Tabel data --- */
  table.data { width:100%; border-collapse:separate; border-spacing:0; font-size:12px; }
  table.data col.num { width:6%; }
  table.data th, table.data td { padding:8px 10px; border:1px solid #d1d5db; }
  table.data thead th {
    background:#0d47a1; color:#fff; font-weight:700; text-transform:uppercase; letter-spacing:.4px;
  }
  table.data thead th:first-child { border-top-left-radius:6px; }
  table.data thead th:last-child  { border-top-right-radius:6px; }
  table.data tbody tr:nth-child(even) { background:#f7f9fc; }
  table.data tbody td.center, .center { text-align:center; }
  .right { text-align:right; }
  .bold { font-weight:700; }

  .footer { margin-top:14px; font-size:11px; color:#666; text-align:right; }

  /* Toolbar (tidak ikut tercetak) */
  .toolbar { position:sticky; top:0; padding:8px 0 12px; background:#fff; z-index:10; }
  .btn { display:inline-block; padding:8px 12px; margin-right:8px; border-radius:6px; text-decoration:none; border:1px solid #ccc; background:#f8f9fa; color:#222; font-size:14px; }
  .btn-primary { background:#1e88e5; color:#fff; border-color:#1e88e5; }
  .btn-success { background:#43a047; color:#fff; border-color:#43a047; }
  .btn:hover { opacity:0.92; }

  @media print { .toolbar { display:none !important; } }
</style>';

$info = '
<div class="info-wrap">
  <div class="info-head">Ringkasan Filter</div>
  <div class="info-body">
    <table class="info">
      <tr><td class="small" style="width:22%"><b>Tahun Ajaran</b></td><td class="small" style="width:2%">:</td><td class="small">'.esc($ta_nama).'</td></tr>
      <tr><td class="small"><b>Kelas</b></td><td class="small">:</td><td class="small">'.esc($kelas_nama).'</td></tr>
      <tr><td class="small"><b>Bulan</b></td><td class="small">:</td><td class="small">'.(($bulan>=1&&$bulan<=12)?esc($bulanNama[$bulan]):'Semua').'</td></tr>
      <tr><td class="small"><b>Urutan</b></td><td class="small">:</td><td class="small">'.esc($urutkan?:'Nama (A-Z)').'</td></tr>
    </table>
  </div>
</div>';

$tbody = '';
if($view==='kelas'){
  $tbody .= '<table class="data">
    <colgroup><col class="num"><col><col><col><col></colgroup>
    <thead><tr>
      <th>NO</th>
      <th>KELAS</th>
      <th>TOTAL PRESTASI</th>
      <th>TOTAL PELANGGARAN</th>
      <th>SALDO POIN</th>
    </tr></thead><tbody>';
  $no=1; $res = mysqli_query($koneksi, $sqlPerKelas);
  if($res && mysqli_num_rows($res)>0){
    while($d=mysqli_fetch_assoc($res)){
      $net = (int)$d['total_prestasi'] - (int)$d['total_pelanggaran'];
      $tbody .= '<tr>
        <td class="center">'.$no++.'</td>
        <td>'.esc($d['kelas_nama']).'</td>
        <td class="center">'.(int)$d['total_prestasi'].'</td>
        <td class="center">'.(int)$d['total_pelanggaran'].'</td>
        <td class="center bold">'.$net.'</td>
      </tr>';
    }
  } else {
    $tbody .= '<tr><td colspan="5" class="center">Tidak ada data</td></tr>';
  }
  $tbody .= '</tbody></table>';
}else{
  $tbody .= '<table class="data">
    <colgroup><col class="num"><col><col><col><col><col></colgroup>
    <thead><tr>
      <th>NO</th>
      <th>NAMA SISWA</th>
      <th>NIS</th>
      <th>TOTAL PRESTASI</th>
      <th>TOTAL PELANGGARAN</th>
      <th>SALDO POIN</th>
    </tr></thead><tbody>';
  $no=1; $res = mysqli_query($koneksi, $sqlPerSiswa);
  if($res && mysqli_num_rows($res)>0){
    while($d=mysqli_fetch_assoc($res)){
      $net = (int)$d['total_prestasi'] - (int)$d['total_pelanggaran'];
      $tbody .= '<tr>
        <td class="center">'.$no++.'</td>
        <td>'.esc($d['siswa_nama']).'</td>
        <td class="center">'.esc($d['siswa_nis']).'</td>
        <td class="center">'.(int)$d['total_prestasi'].'</td>
        <td class="center">'.(int)$d['total_pelanggaran'].'</td>
        <td class="center bold">'.$net.'</td>
      </tr>';
    }
  } else {
    $tbody .= '<tr><td colspan="6" class="center">Tidak ada data</td></tr>';
  }
  $tbody .= '</tbody></table>';
}

$legend = '<div class="subtitle"><em>Saldo Poin = Total Prestasi – Total Pelanggaran</em></div>';

/* === Footer: ganti label sistem sesuai permintaan === */
$footer = '<div class="footer">Dicetak: '.date('d/m/Y H:i').' &nbsp;|&nbsp; E-Point SMPN 1 Gunungtanjung</div>';

/* ====== Jika diminta download PDF ====== */
$autoloaders = [
  __DIR__.'/vendor/autoload.php',
  __DIR__.'/../vendor/autoload.php',
  __DIR__.'/mpdf/autoload.php'
];
$mpdf_ready = false;
foreach($autoloaders as $a){ if(file_exists($a)){ require_once $a; $mpdf_ready = class_exists('\\Mpdf\\Mpdf'); break; } }

if($download === 1){
  if($mpdf_ready){
    if (ob_get_length()) { ob_end_clean(); }
    $mpdf = new \Mpdf\Mpdf(['format'=>'A4-L','tempDir'=>__DIR__.'/tmp']);
    $mpdf->SetTitle($judul);
    $mpdf->WriteHTML($css.'<h2>'.$judul.'</h2>'.$legend.$info.$tbody.$footer);
    $mpdf->Output('laporan_point_'.date('Ymd_His').'.pdf','D'); // Download
    exit;
  } else {
    // mPDF tidak tersedia: tampilkan HTML dengan pemberitahuan
    if (ob_get_length()) { ob_end_clean(); }
    header('Content-Type: text/html; charset=utf-8');
    echo $css;
    echo '<div class="toolbar" style="margin-bottom:8px;color:#b71c1c;">mPDF belum terpasang di server. Gunakan tombol <b>Cetak</b> lalu pilih <i>Simpan sebagai PDF</i>.</div>';
    echo '<h2>'.esc($judul).'</h2>'.$legend.$info.$tbody.$footer;
    echo '<script>window.print();</script>';
    exit;
  }
}

/* ====== Default: tampilkan HALAMAN HTML + toolbar (Cetak & Download) ====== */
if (ob_get_length()) { ob_end_clean(); }
header('Content-Type: text/html; charset=utf-8');

echo $css;

// Toolbar dengan 2 tombol
echo '<div class="toolbar">
        <a href="#" class="btn btn-primary" onclick="window.print();return false;">🖨️ Cetak</a>
        <a class="btn btn-success" href="'.esc(build_url(['download'=>1])).'">⬇️ Download PDF</a>
      </div>';

echo '<h2>'.esc($judul).'</h2>';
echo $legend.$info.$tbody.$footer;
