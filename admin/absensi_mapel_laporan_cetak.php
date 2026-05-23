<?php
include '../koneksi.php';
if (session_status()===PHP_SESSION_NONE) session_start();

// ====== Auth ringan: wajib login (admin/guru) ======
if (empty($_SESSION['id'])) { header("location:../admin.php?alert=belum_login"); exit; }
$user_id = (int)$_SESSION['id'];

// ====== Role helpers ======
function user_has_role($r){ $roles=$_SESSION['roles']??[]; return in_array($r,$roles,true); }
function user_has_any_role($a){ foreach($a as $r){ if(user_has_role($r)) return true; } return false; }
$is_admin = user_has_any_role(['administrator','superadmin']);

// ====== Helper escaping ======
function dbesc($s){ global $koneksi; return mysqli_real_escape_string($koneksi,$s); }
function esc($s){ return htmlspecialchars($s,ENT_QUOTES,'UTF-8'); }
function get_scalar($sql){
  global $koneksi;
  $q = mysqli_query($koneksi,$sql);
  if($q && $r = mysqli_fetch_row($q)) return $r[0];
  return null;
}
function pct_str($num,$den){
  if($den<=0) return '0%';
  $p = ($num*100.0)/$den;
  // Format Indonesia: koma desimal
  return number_format($p,1,',','.') . '%';
}

// ====== Tahun Ajaran aktif ======
$ta=0;$ta_nama='-';
$taq=mysqli_query($koneksi,"SELECT ta_id, ta_nama FROM ta WHERE ta_status=1 LIMIT 1");
if($taq && $r=mysqli_fetch_assoc($taq)){ $ta=(int)$r['ta_id']; $ta_nama = $r['ta_nama']; }

// ====== Ambil filter ======
$kind     = $_GET['kind'] ?? 'harian'; // 'rekap' atau 'harian'
$d1       = $_GET['d1'] ?? date('Y-m-01');
$d2       = $_GET['d2'] ?? date('Y-m-d');
$mapel_id = isset($_GET['mapel_id'])?(int)$_GET['mapel_id']:0;
$kelas_id = isset($_GET['kelas_id'])?(int)$_GET['kelas_id']:0;
$guru_id  = isset($_GET['guru_id'])?(int)$_GET['guru_id']:0;

// RBAC: guru non-admin dibatasi melihat datanya sendiri
if(!$is_admin){ $guru_id = $user_id; }

// ====== Label filter (untuk header) ======
$kelas_label = $kelas_id>0 ? (get_scalar("SELECT kelas_nama FROM kelas WHERE kelas_id=".((int)$kelas_id)." LIMIT 1") ?: 'Tidak ditemukan') : 'Semua Kelas';
$mapel_label = $mapel_id>0 ? (get_scalar("SELECT mapel_nama FROM mapel WHERE mapel_id=".((int)$mapel_id)." LIMIT 1") ?: 'Tidak ditemukan') : 'Semua Mapel';
$guru_label  = $guru_id>0  ? (get_scalar("SELECT user_nama  FROM user  WHERE user_id=".((int)$guru_id)."  LIMIT 1") ?: 'Tidak ditemukan') : 'Semua Guru';

// ====== WHERE umum ======
$where = ["s.ta_id=$ta","s.status='final'"];
if($d1) $where[]="s.tanggal>='".dbesc($d1)."'";
if($d2) $where[]="s.tanggal<='".dbesc($d2)."'";
if($mapel_id>0) $where[]="s.mapel_id=$mapel_id";
if($kelas_id>0) $where[]="s.kelas_id=$kelas_id";
if($guru_id>0)  $where[]="s.guru_user_id=$guru_id";

$judulJenis = ($kind==='rekap')?'Rekap per Siswa':'Rekap per Sesi (Harian)';
$subPeriode = "Periode ".esc($d1)." s.d. ".esc($d2);
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Laporan Absensi Mapel — <?=$judulJenis?></title>
<style>
  :root{
    --ink:#111;
    --muted:#556;
    --line:#d7dbe7;
    --accent:#1e66ff;
    --accent-soft:#eaf0ff;
  }
  *{ box-sizing:border-box }
  body{
    font-family: Calibri, Arial, Helvetica, sans-serif;
    font-size:12px; color:var(--ink); margin:18px; line-height:1.5;
  }
  header.report-header{
    border-bottom:2px solid var(--line);
    margin-bottom:12px; padding-bottom:10px;
    display:flex; align-items:center; gap:12px;
  }
  .brand-logo{
    width:56px; height:56px; object-fit:contain;
    border-radius:8px;
  }
  h1{font-size:18px;margin:0}
  .sub{color:var(--muted);margin:3px 0 0 0}
  .meta{
    margin-top:10px;
    display:grid; grid-template-columns: 120px 12px 1fr;
    row-gap:4px; column-gap:6px;
    font-size:12px;
  }
  .tagline{
    margin-top:6px; font-size:11px; color:#666
  }
  table{border-collapse:collapse;width:100%; margin-top:10px}
  th,td{border:1px solid var(--line);padding:7px 8px;vertical-align:middle}
  th{background:var(--accent-soft); font-weight:600}
  tr:nth-child(even) td{ background:#fbfcff }
  .right{text-align:right}.center{text-align:center}.nowrap{white-space:nowrap}
  .small{font-size:11px;color:#666}
  .muted{color:#667}
  .badge{
    display:inline-block; border:1px solid var(--line); border-radius:999px;
    padding:2px 8px; font-size:11px; color:#334;
    background:#fafbff; margin-right:6px;
  }
  .legend{ margin-top:6px; }
  .footer{
    margin-top:24px; display:flex; justify-content:space-between; gap:16px;
    font-size:11px; color:#555;
  }
  .signature{
    margin-top:32px; display:flex; justify-content:flex-end;
  }
  .signature .box{
    width:260px; text-align:center;
  }
  /* Spasi tanda tangan: ~4–5 baris (±80px) sebelum garis */
  .signature .box .line{ margin-top:80px; border-top:1px solid #333; height:0 }
  @media print{
    @page{margin:12mm}
    a[href]:after{content:""}
    body{ margin:0 }
    .badge{ border-color:#ccc }
  }
</style>
</head>
<body>

<header class="report-header">
  <img src="../gambar/sistem/logosekolah.png" alt="Logo Sekolah" class="brand-logo">
  <div>
    <h1>Laporan Absensi Mapel — <?=$judulJenis?></h1>
    <div class="sub"><?=$subPeriode?> | TA: <?=esc($ta_nama)?></div>
    <div class="tagline">Dokumen ini dirancang untuk cetak & arsip — data hanya menampilkan sesi berstatus <b>final</b>.</div>
  </div>
</header>

<section class="meta">
  <div>Kelas</div><div>:</div><div><?=esc($kelas_label)?></div>
  <div>Mapel</div><div>:</div><div><?=esc($mapel_label)?></div>
  <div>Guru</div><div>:</div><div><?=esc($guru_label)?></div>
  <div>Mode</div><div>:</div><div><?=($kind==='rekap')?'Rekap per Siswa':'Rekap per Sesi (Harian)'?></div>
  <div>Dibuat</div><div>:</div><div><?= date('Y-m-d H:i:s') ?></div>
</section>

<div class="legend">
  <span class="badge">H = Hadir</span>
  <span class="badge">S = Sakit</span>
  <span class="badge">I = Izin</span>
  <span class="badge">A = Alfa</span>
</div>

<?php if($kind==='rekap'):
  // ================== REKAP PER SISWA ==================
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
  $no=1;$tH=$tS=$tI=$tA=$tTOT=0;
?>
  <table>
    <thead>
      <tr>
        <th style="width:36px">No</th>
        <th>Nama Siswa</th>
        <th style="width:60px">H</th>
        <th style="width:60px">S</th>
        <th style="width:60px">I</th>
        <th style="width:60px">A</th>
        <th style="width:80px">% Hadir</th>
      </tr>
    </thead>
    <tbody>
<?php while($r=mysqli_fetch_assoc($q)){
      $tH += (int)$r['H']; $tS += (int)$r['S']; $tI += (int)$r['I']; $tA += (int)$r['A']; $tTOT += (int)$r['total'];
      $pct = pct_str((int)$r['H'], (int)$r['total']);
?>
      <tr>
        <td class="right"><?=$no++?></td>
        <td><?=esc($r['siswa_nama'])?></td>
        <td class="right"><?=$r['H']?></td>
        <td class="right"><?=$r['S']?></td>
        <td class="right"><?=$r['I']?></td>
        <td class="right"><?=$r['A']?></td>
        <td class="right"><?=$pct?></td>
      </tr>
<?php } ?>
      <tr>
        <th colspan="2" class="right">TOTAL</th>
        <th class="right"><?=$tH?></th>
        <th class="right"><?=$tS?></th>
        <th class="right"><?=$tI?></th>
        <th class="right"><?=$tA?></th>
        <th class="right"><?= pct_str($tH, max(1,$tTOT)) ?></th>
      </tr>
    </tbody>
  </table>
<?php else:
  // ================== REKAP PER SESI (HARIAN) ==================
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
  $q = mysqli_query($koneksi,$sql);
  $no=1;$tH=$tS=$tI=$tA=$tTOT=0;
?>
  <table>
    <thead>
      <tr>
        <th style="width:36px">No</th>
        <th class="nowrap" style="width:90px">Tanggal</th>
        <th>Kelas</th>
        <th>Mapel</th>
        <th style="width:50px">Jam</th>
        <!-- Kolom Guru dihilangkan sesuai permintaan -->
        <th style="width:60px">H</th>
        <th style="width:60px">S</th>
        <th style="width:60px">I</th>
        <th style="width:60px">A</th>
        <th style="width:80px">% Hadir</th>
      </tr>
    </thead>
    <tbody>
<?php while($r=mysqli_fetch_assoc($q)){
      $tH += (int)$r['H']; $tS += (int)$r['S']; $tI += (int)$r['I']; $tA += (int)$r['A']; $tTOT += (int)$r['total'];
      $pct = pct_str((int)$r['H'], (int)$r['total']);
?>
      <tr>
        <td class="right"><?=$no++?></td>
        <td class="nowrap"><?=esc($r['tanggal'])?></td>
        <td><?=esc($r['kelas_nama'])?></td>
        <td><?=esc($r['mapel_nama'])?></td>
        <td class="center"><?= (int)$r['jam_ke'] ?></td>
        <!-- Kolom Guru dihilangkan -->
        <td class="right"><?=$r['H']?></td>
        <td class="right"><?=$r['S']?></td>
        <td class="right"><?=$r['I']?></td>
        <td class="right"><?=$r['A']?></td>
        <td class="right"><?=$pct?></td>
      </tr>
<?php } ?>
      <tr>
        <th colspan="5" class="right">TOTAL</th>
        <th class="right"><?=$tH?></th>
        <th class="right"><?=$tS?></th>
        <th class="right"><?=$tI?></th>
        <th class="right"><?=$tA?></th>
        <th class="right"><?= pct_str($tH, max(1,$tTOT)) ?></th>
      </tr>
    </tbody>
  </table>
<?php endif; ?>

<div class="footer">
  <div>
    <div class="small muted">Catatan:</div>
    <div class="small">
      Persentase hadir dihitung sebagai <b>H / (H+S+I+A)</b> pada baris terkait.<br>
      Data yang ditampilkan hanya sesi berstatus <b>final</b> pada Tahun Ajaran aktif.
    </div>
  </div>
  <div class="small">Dicetak pada: <?=date('Y-m-d H:i:s')?> WIB</div>
</div>

<div class="signature">
  <div class="box">
    <div>Kepala Sekolah / Wali Kelas</div>
    <div class="line"></div>
  </div>
</div>

<script>window.print();</script>
</body>
</html>
