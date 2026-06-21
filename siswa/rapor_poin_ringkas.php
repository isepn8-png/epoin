<?php
// siswa/rapor_poin_ringkas.php — Rapor Poin ringkas (halaman cetak / PDF)
include '../koneksi.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!isset($_SESSION['level']) || $_SESSION['level'] !== 'siswa') {
  header("location:../index.php?alert=belum_login"); exit;
}
$id_siswa = (int)($_SESSION['id'] ?? 0);
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Brand sekolah (jika tersedia)
$schoolName = 'Sekolah';
$brandFile = __DIR__ . '/../includes/theme_brand.php';
if (is_file($brandFile)) {
  require_once $brandFile;
  if (function_exists('brand_school_name')) $schoolName = brand_school_name();
}

// Identitas + kelas
$profil = mysqli_fetch_assoc(mysqli_query($koneksi,"
  SELECT s.siswa_nama, s.siswa_nis, j.jurusan_nama
  FROM siswa s LEFT JOIN jurusan j ON j.jurusan_id = s.siswa_jurusan
  WHERE s.siswa_id = {$id_siswa} LIMIT 1")) ?: [];

$kelas = mysqli_fetch_assoc(mysqli_query($koneksi,"
  SELECT k.kelas_nama FROM kelas_siswa ks JOIN kelas k ON k.kelas_id = ks.ks_kelas
  WHERE ks.ks_siswa = {$id_siswa} ORDER BY ks.ks_id DESC LIMIT 1"));

// Poin
$plus = (int)mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COALESCE(SUM(p.prestasi_point),0) v
  FROM input_prestasi ip JOIN prestasi p ON p.prestasi_id=ip.prestasi WHERE ip.siswa={$id_siswa}"))['v'];
$minus = (int)mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COALESCE(SUM(pg.pelanggaran_point),0) v
  FROM input_pelanggaran ig JOIN pelanggaran pg ON pg.pelanggaran_id=ig.pelanggaran WHERE ig.siswa={$id_siswa}"))['v'];
$total = $plus - $minus;
$jml_prestasi   = (int)mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) v FROM input_prestasi WHERE siswa={$id_siswa}"))['v'];
$jml_pelanggaran= (int)mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT COUNT(*) v FROM input_pelanggaran WHERE siswa={$id_siswa}"))['v'];

// Status SP berdasar saldo negatif (selaras dashboard)
$neg = max(0, -$total);
if     ($neg >= 81) { $sp='SP4'; $spText='Peringatan Terakhir'; $spColor='#b91c1c'; }
elseif ($neg >= 61) { $sp='SP3'; $spText='Pembinaan Khusus';    $spColor='#ef4444'; }
elseif ($neg >= 41) { $sp='SP2'; $spText='Panggilan Orang Tua'; $spColor='#f97316'; }
elseif ($neg >= 21) { $sp='SP1'; $spText='Peringatan 1';        $spColor='#f59e0b'; }
else                { $sp='AMAN';$spText='Tidak ada tindakan';  $spColor='#10b981'; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rapor Poin — <?= h($profil['siswa_nama'] ?? 'Siswa') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box}
  body{font-family:Inter,Arial,sans-serif; color:#1e293b; margin:0; background:#eef2f7;}
  .sheet{max-width:760px; margin:24px auto; background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,.10);}
  .hd{background:linear-gradient(135deg,#1d4ed8,#0ea5e9); color:#fff; padding:22px 26px;}
  .hd .sub{font-size:12px; letter-spacing:1px; text-transform:uppercase; opacity:.85}
  .hd h1{margin:4px 0 2px; font-size:22px; font-weight:800}
  .hd .school{font-size:13px; opacity:.95}
  .bd{padding:24px 26px}
  .idgrid{display:grid; grid-template-columns:1fr 1fr; gap:8px 18px; margin-bottom:18px}
  .idgrid .lbl{font-size:11px; text-transform:uppercase; letter-spacing:.4px; color:#94a3b8; font-weight:700}
  .idgrid .val{font-size:15px; font-weight:700; color:#0f172a}
  .cards{display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin:18px 0}
  .c{border-radius:12px; padding:14px 16px; color:#fff; position:relative}
  .c .t{font-size:11px; text-transform:uppercase; letter-spacing:.4px; opacity:.95}
  .c .v{font-size:30px; font-weight:800; line-height:1.1}
  .c .s{font-size:11px; opacity:.9}
  .c-total{background:linear-gradient(135deg,#60a5fa,#2563eb)}
  .c-plus{background:linear-gradient(135deg,#34d399,#059669)}
  .c-minus{background:linear-gradient(135deg,#fb7185,#dc2626)}
  .spbar{display:flex; align-items:center; gap:12px; border:1px solid #e5e7eb; border-radius:12px; padding:14px 16px; margin-top:6px}
  .spbadge{font-weight:800; color:#fff; padding:8px 16px; border-radius:10px; font-size:15px}
  .spinfo .a{font-weight:700}
  .spinfo .b{font-size:12px; color:#64748b}
  .ft{margin-top:22px; padding-top:14px; border-top:1px dashed #e5e7eb; font-size:12px; color:#94a3b8; display:flex; justify-content:space-between; flex-wrap:wrap; gap:6px}
  .toolbar{max-width:760px; margin:18px auto 0; text-align:center}
  .btn{display:inline-flex; align-items:center; gap:7px; border:0; cursor:pointer; font-weight:700; font-size:14px;
       padding:10px 20px; border-radius:999px; text-decoration:none; margin:0 4px}
  .btn-print{background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; box-shadow:0 8px 18px rgba(37,99,235,.28)}
  .btn-close{background:#e2e8f0; color:#334155}
  @media print{
    body{background:#fff}
    .sheet{box-shadow:none; margin:0; max-width:100%; border-radius:0}
    .toolbar{display:none !important}
    @page{margin:14mm}
  }
  @media(max-width:560px){ .idgrid,.cards{grid-template-columns:1fr} }
</style>
</head>
<body>

<div class="sheet">
  <div class="hd">
    <div class="sub">Rapor Poin Siswa</div>
    <h1>Ringkasan Poin Pembinaan</h1>
    <div class="school"><?= h($schoolName) ?></div>
  </div>
  <div class="bd">
    <div class="idgrid">
      <div><div class="lbl">Nama</div><div class="val"><?= h($profil['siswa_nama'] ?? '-') ?></div></div>
      <div><div class="lbl">NIS</div><div class="val"><?= h($profil['siswa_nis'] ?? '-') ?></div></div>
      <div><div class="lbl">Kelas</div><div class="val"><?= h($kelas['kelas_nama'] ?? '-') ?></div></div>
      <div><div class="lbl">Jurusan</div><div class="val"><?= h($profil['jurusan_nama'] ?? '-') ?></div></div>
    </div>

    <div class="cards">
      <div class="c c-total"><div class="t">Total Poin</div><div class="v"><?= $total ?></div><div class="s">Saldo akhir</div></div>
      <div class="c c-plus"><div class="t">Prestasi (+)</div><div class="v"><?= $plus ?></div><div class="s"><?= $jml_prestasi ?> entri</div></div>
      <div class="c c-minus"><div class="t">Pelanggaran (−)</div><div class="v"><?= $minus ?></div><div class="s"><?= $jml_pelanggaran ?> entri</div></div>
    </div>

    <div class="spbar">
      <span class="spbadge" style="background:<?= $spColor ?>"><?= h($sp) ?></span>
      <div class="spinfo">
        <div class="a">Status Pembinaan: <?= h($spText) ?></div>
        <div class="b">Dihitung dari saldo poin negatif (<?= $neg ?> poin). SP1≥21 · SP2≥41 · SP3≥61 · SP4≥81.</div>
      </div>
    </div>

    <div class="ft">
      <span>Dicetak: <?= date('d M Y H:i') ?> WIB</span>
      <span>Dokumen ini dihasilkan otomatis oleh sistem E-Poin.</span>
    </div>
  </div>
</div>

<div class="toolbar">
  <button class="btn btn-print" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
  <a class="btn btn-close" href="poin.php">← Kembali</a>
</div>

</body>
</html>
