<?php include 'header.php'; ?>
<?php

?>
<div class="content-wrapper">
  <section class="content-header">
    <h1>Dashboard <small>Panel Siswa</small></h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Dashboard</li>
    </ol>
  </section>

  <section class="content">

<?php
$id_siswa = $_SESSION['id'];
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

/* ==== Helper bulan Indonesia (dipakai di tempat lain jika perlu) ==== */
function bulanID($dateStr){
  $nama = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  $t = strtotime($dateStr);
  $m = (int)date('n',$t);
  $y = date('Y',$t);
  return ($nama[$m] ?? date('M',$t)).' '.$y;
}

/* ===== Param bulan (YYYY-MM) untuk donut absensi (tetap disimpan, mungkin dipakai bagian lain) ===== */
$bulan_param = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
if(!preg_match('/^\d{4}\-\d{2}$/',$bulan_param)){ $bulan_param = date('Y-m'); }
$first = $bulan_param.'-01';
$last  = date('Y-m-t', strtotime($first));
$first_esc = mysqli_real_escape_string($koneksi,$first);
$last_esc  = mysqli_real_escape_string($koneksi,$last);

/* ===== TA aktif ===== */
$ta_aktif = mysqli_query($koneksi,"SELECT ta_id, ta_nama FROM ta WHERE ta_status='1' ORDER BY ta_id DESC LIMIT 1");
$ta_aktif = mysqli_fetch_assoc($ta_aktif);

/* ===== kelas siswa terakhir ===== */
$q_kelas = mysqli_query($koneksi,"SELECT ks.ks_kelas, k.kelas_nama, k.kelas_ta
                                  FROM kelas_siswa ks
                                  LEFT JOIN kelas k ON k.kelas_id=ks.ks_kelas
                                  WHERE ks.ks_siswa='".intval($id_siswa)."'
                                  ORDER BY ks.ks_id DESC LIMIT 1");
$kls = mysqli_fetch_assoc($q_kelas);

/* ===== Total poin milik siswa login ===== */
$q_plus = mysqli_query($koneksi,"SELECT COALESCE(SUM(p.prestasi_point),0) AS plus_poin
                                 FROM input_prestasi ip
                                 JOIN prestasi p ON p.prestasi_id=ip.prestasi
                                 WHERE ip.siswa='".intval($id_siswa)."'");
$plus = (int)mysqli_fetch_assoc($q_plus)['plus_poin'];
$q_min = mysqli_query($koneksi,"SELECT COALESCE(SUM(pg.pelanggaran_point),0) AS minus_poin
                                FROM input_pelanggaran ig
                                JOIN pelanggaran pg ON pg.pelanggaran_id=ig.pelanggaran
                                WHERE ig.siswa='".intval($id_siswa)."'");
$minus = (int)mysqli_fetch_assoc($q_min)['minus_poin'];
$total_poin = $plus - $minus; // == saldo

/* ===== Ringkasan jumlah entri milik siswa login ===== */
$cnt_prestasi = 0; $cnt_pelanggaran = 0;
$r = mysqli_query($koneksi,"SELECT COUNT(*) jml FROM input_prestasi WHERE siswa='".intval($id_siswa)."'");
if($r) $cnt_prestasi = (int)mysqli_fetch_assoc($r)['jml'];
$r = mysqli_query($koneksi,"SELECT COUNT(*) jml FROM input_pelanggaran WHERE siswa='".intval($id_siswa)."'");
if($r) $cnt_pelanggaran = (int)mysqli_fetch_assoc($r)['jml'];

/* ===== Kehadiran H/I/S/A (absensi_harian final) — BULANAN ===== */
$id   = intval($id_siswa);
$sqlKeh = "SELECT d.status, COUNT(*) jml
           FROM absensi_harian_detail d
           JOIN absensi_harian h ON h.harian_id=d.harian_id
           WHERE d.siswa_id=$id AND h.status='final'
             AND h.tanggal BETWEEN '$first_esc' AND '$last_esc'
           GROUP BY d.status";
$qKeh = mysqli_query($koneksi,$sqlKeh);
$kehadiran = ['H'=>0,'I'=>0,'S'=>0,'A'=>0];
while($r = mysqli_fetch_assoc($qKeh)){
  $key = strtoupper($r['status']);
  if(isset($kehadiran[$key])) $kehadiran[$key] = (int)$r['jml'];
}

/* ====== Kehadiran SEMESTER ====== */
$nowM = (int)date('n'); $nowY = (int)date('Y');
$TAStartYear = ($nowM >= 7) ? $nowY : ($nowY - 1);
$TAEndYear   = $TAStartYear + 1;
$sem_param   = isset($_GET['sem']) ? (int)$_GET['sem'] : (($nowM >= 7) ? 1 : 2);
$sem_param   = ($sem_param === 2) ? 2 : 1;

if ($sem_param === 1) {
  $sem_start = sprintf('%04d-07-01', $TAStartYear);
  $sem_end   = sprintf('%04d-12-31', $TAStartYear);
  $sem_label = "Semester 1 (Jul–Des $TAStartYear) — TA $TAStartYear/$TAEndYear";
} else {
  $sem_start = sprintf('%04d-01-01', $TAEndYear);
  $sem_end   = sprintf('%04d-06-30', $TAEndYear);
  $sem_label = "Semester 2 (Jan–Jun $TAEndYear) — TA $TAStartYear/$TAEndYear";
}
$sem_start_esc = mysqli_real_escape_string($koneksi, $sem_start);
$sem_end_esc   = mysqli_real_escape_string($koneksi, $sem_end);

$kehadiran_sem = ['H'=>0,'I'=>0,'S'=>0,'A'=>0];
$sqlKehSem = "SELECT UPPER(d.status) s, COUNT(*) jml
              FROM absensi_harian_detail d
              JOIN absensi_harian h ON h.harian_id=d.harian_id
              WHERE d.siswa_id=$id AND h.status='final'
                AND h.tanggal BETWEEN '$sem_start_esc' AND '$sem_end_esc'
              GROUP BY UPPER(d.status)";
$qKehSem = mysqli_query($koneksi,$sqlKehSem);
while($r = mysqli_fetch_assoc($qKehSem)){
  $kk = strtoupper($r['s']);
  if(isset($kehadiran_sem[$kk])) $kehadiran_sem[$kk] = (int)$r['jml'];
}

/* ===== Aktivitas poin Anda (5 terbaru) ===== */
$sqlAkt = "(SELECT 'Prestasi' tipe, ip.waktu tgl, pr.prestasi_nama nama, pr.prestasi_point poin
            FROM input_prestasi ip
            JOIN prestasi pr ON pr.prestasi_id=ip.prestasi
            WHERE ip.siswa=".intval($id_siswa).")
           UNION ALL
           (SELECT 'Pelanggaran' tipe, ig.waktu tgl, pl.pelanggaran_nama nama, -pl.pelanggaran_point poin
            FROM input_pelanggaran ig
            JOIN pelanggaran pl ON pl.pelanggaran_id=ig.pelanggaran
            WHERE ig.siswa=".intval($id_siswa).")
           ORDER BY tgl DESC
           LIMIT 5";
$aktivitas = [];
$q = mysqli_query($koneksi,$sqlAkt);
while($row=mysqli_fetch_assoc($q)) $aktivitas[]=$row;

/* ===== Split + vs - ===== */
$sum_pm = $plus + $minus;
if($sum_pm > 0){
  $portion_plus  = round(($plus  / $sum_pm) * 100);
  $portion_minus = 100 - $portion_plus;
} else {
  $portion_plus = 0; $portion_minus = 0;
}

/* ======== AKTIVITAS PENERIMA POIN TERBARU (SEMUA WAKTU) ======== */
$sqlAktAll = "
  (SELECT 'Prestasi' AS tipe, ip.waktu AS tgl, s.siswa_nama AS siswa, pr.prestasi_nama AS nama, pr.prestasi_point AS poin
     FROM input_prestasi ip
     JOIN prestasi pr ON pr.prestasi_id = ip.prestasi
     JOIN siswa s ON s.siswa_id = ip.siswa
     WHERE LOWER(TRIM(pr.prestasi_nama)) NOT LIKE '%memberikan informasi valid terkait pelanggaran%')
  UNION ALL
  (SELECT 'Pelanggaran' AS tipe, ig.waktu AS tgl, s.siswa_nama AS siswa, pl.pelanggaran_nama AS nama, -pl.pelanggaran_point AS poin
     FROM input_pelanggaran ig
     JOIN pelanggaran pl ON pl.pelanggaran_id = ig.pelanggaran
     JOIN siswa s ON s.siswa_id = ig.siswa)
  ORDER BY tgl DESC
  LIMIT 300
";
$aktivitas_all = [];
$qa = mysqli_query($koneksi,$sqlAktAll);
while($row=mysqli_fetch_assoc($qa)){
  $isPrestasi = (strcasecmp($row['tipe'] ?? '', 'Prestasi') === 0);
  $nama = trim($row['nama'] ?? '');
  $needle = 'memberikan informasi valid terkait pelanggaran';
  if($isPrestasi && stripos($nama, $needle) !== false){ continue; }
  $aktivitas_all[]=$row;
}

/* ===== Foto profil siswa ===== */
$foto_url = '../gambar/sistem/user.png';
if (isset($profil) && is_array($profil)) {
  $candKeys = ['siswa_foto','foto','photo','gambar','image','avatar'];
  $found = '';
  foreach ($candKeys as $k) {
    if (!empty($profil[$k])) { $found = $profil[$k]; break; }
  }
  if ($found) {
    $bn = basename($found);
    $tries = [
      "../gambar/siswa/$bn",
      "../gambar/$bn",
      "../uploads/siswa/$bn",
      "../uploads/$bn",
      $found
    ];
    foreach ($tries as $p) {
      if (filter_var($p, FILTER_VALIDATE_URL) || is_file($p)) { $foto_url = $p; break; }
    }
  }
}

/* ====== Mapping Jenjang / Stage dari saldo negatif (config ambang fleksibel) ====== */
require_once __DIR__ . '/../includes/epoin_sp_helpers.php';

$saldo    = (int)$total_poin;
$negSaldo = max(0, -$saldo);

$SP_BY_SALDO_SEQUENTIAL = true;

$STAGES = epoin_sp_stages($koneksi); // dibangun dari skala & jumlah level yang dikonfigurasi
$scaleMax = (int) epoin_sp_config($koneksi)['skala_max']; // skala maks (denominator risiko), mis. 100/200
$SAFE_STAGE = ['roman'=>'-', 'min'=>0, 'max'=>0, 'program'=>'Apresiasi / Monitoring', 'action'=>'Tidak ada tindakan', 'color'=>'#10b981', 'sp'=>null];

$currentStage = $SAFE_STAGE;
$levelActive  = 0; // 0 = aman; selain itu = urutan tahap aktif
if ($negSaldo > 0){
  foreach($STAGES as $i => $st){
    if($negSaldo >= $st['min'] && $negSaldo <= $st['max']){ $currentStage = $st; $levelActive = $i + 1; break; }
  }
}

/* — Perubahan Terakhir — */
$last = null;
$qlast = mysqli_query($koneksi, "
  (SELECT ip.waktu AS waktu, 'prestasi' AS jenis, p.prestasi_nama AS nama, p.prestasi_point AS poin
     FROM input_prestasi ip
     JOIN prestasi p ON ip.prestasi=p.prestasi_id
    WHERE ip.siswa='".intval($id_siswa)."')
  UNION ALL
  (SELECT ig.waktu AS waktu, 'pelanggaran' AS jenis, pg.pelanggaran_nama AS nama, pg.pelanggaran_point AS poin
     FROM input_pelanggaran ig
     JOIN pelanggaran pg ON ig.pelanggaran=pg.pelanggaran_id
    WHERE ig.siswa='".intval($id_siswa)."')
  ORDER BY waktu DESC
  LIMIT 1
");
if($qlast){ $last = mysqli_fetch_assoc($qlast); }

/* — Status SP (dari ambang fleksibel) — */
$spStatus = epoin_sp_status_for($negSaldo, $koneksi) ?? 'Belum SP';

/* — Visual progres saldo — */
$scaleMaxSaldo = max(100, abs($saldo));
$percentSaldo  = $scaleMaxSaldo > 0 ? min(100, round(abs($saldo) / $scaleMaxSaldo * 100)) : 0;
$isPos         = $saldo >= 0;

/* — Risiko sanksi — */
$riskPercent = max(0, min(100, -$saldo));
$stageColor  = $currentStage['color'] ?? '#10b981';

/* — Target aman — */
$toSafe = $saldo < 0 ? abs($saldo) : 0;
?>

<!-- ====== Styles (tambahan + integrasi responsif) ====== -->
<style>
  /* === Variabel tema dari skrip responsif === */
  :root {
    --blue-deep: #0b3c7c;   /* biru tua */
    --green-soft: #28a745;  /* hijau lembut */
    --border-soft: #e9ecef; /* garis halus */
    --text-dark: #1f2937;
  }

  /* === Utility spacing & helpers (BS3 friendly) === */
  .mb-0 { margin-bottom:0!important; }
  .mb-2 { margin-bottom:8px!important; }
  .mb-3 { margin-bottom:12px!important; }
  .mb-4 { margin-bottom:16px!important; }
  .me-2 { margin-right:6px!important; }
  .px-3 { padding-left:12px!important; padding-right:12px!important; }
  .py-2 { padding-top:8px!important; padding-bottom:8px!important; }
  .py-3 { padding-top:12px!important; padding-bottom:12px!important; }
  .fw-bold { font-weight:600; }
  .label-pill{
    border-radius:999px;
    display:inline-block;
    padding:3px 10px;
  }

  /* === Card generik (aman dipakai sewaktu-waktu) === */
  .card, .card-jenjang {
    background:#fff;
    border:1px solid var(--border-soft);
    border-radius:10px;
    padding:14px 16px;
    box-shadow:0 6px 18px rgba(0,0,0,.06);
  }
  .card h3,.card h4,.card h5,
  .card-jenjang h3,.card-jenjang h4,.card-jenjang h5 { margin-top:0; color:var(--text-dark); }
  .card i.fa, .card-jenjang i.fa { color: var(--green-soft); }

  /* === Modal header jenjang (jika suatu saat pakai BS modal) === */
  .modal-header.jenjang{
    background:linear-gradient(90deg,#0b3c7c 0%, #1e3a8a 100%);
    color:#fff;
    border-top-left-radius:6px;
    border-top-right-radius:6px;
  }
  .modal-header.jenjang .close{ color:#fff; opacity:.85; }
  .modal-header.jenjang .close:hover{ opacity:1; }

  /* === Responsif AdminLTE small-box / info-box (mobile-friendly) === */
  @media (max-width:480px){
    .small-box { text-align:center; }
    .small-box .inner h3 { font-size:20px !important; }
    .small-box .inner p { font-size:13px !important; }
    .small-box .icon { font-size:42px !important; top:8px; right:8px; }
    .small-box-footer { font-size:12px !important; padding:8px !important; }
  }
  @media (max-width:480px){
    .info-box { min-height:64px; }
    .info-box-icon { width:50px; height:50px; line-height:50px; font-size:22px; }
    .info-box-content { margin-left:60px; }
    .info-box-text { font-size:12px; }
    .info-box-number { font-size:18px; }
    .progress-description, .info-box-more { font-size:12px; }
  }

  /* === Style asli skrip kamu (dipertahankan) === */
  .btn-elevate { transition: transform .15s ease, box-shadow .15s ease; }
  .btn-elevate:hover, .btn-elevate:focus { transform: translateY(-1px) scale(1.03); box-shadow: 0 6px 16px rgba(0,0,0,.08); }

  .legend-row { margin-top:8px; display:flex; align-items:center; justify-content:center; gap:18px; flex-wrap:nowrap; }
  .legend-item { display:inline-flex; align-items:center; gap:8px; font-size:13px; }
  .legend-box { width:28px; height:8px; border-radius:4px; display:inline-block; }

  .rekap-inline { display:flex; align-items:center; gap:14px; margin:0; padding:0; list-style:none; }
  .badge-day { display:inline-block; min-width:22px; height:22px; border-radius:4px; color:#fff; font-weight:700; line-height:22px; text-align:center; font-size:12px; margin-right:6px; }
  .bg-h{background:#16a34a;} .bg-i{background:#3b82f6;} .bg-s{background:#f59e0b;} .bg-a{background:#ef4444;}

  /* ========= [REV-1] GREETING ========= */
  .greet-wrap{ padding:4px 0 8px; }
  .greet-title{
    position:relative;
    display:inline-block; /* penting: ukur sesuai lebar teks */
    font-weight:900; font-size:28px; line-height:1.25; letter-spacing:.2px;
    background: linear-gradient(90deg,#2563eb,#10b981,#f59e0b,#ef4444);
    -webkit-background-clip:text; background-clip:text; color:transparent;
    background-size: 220% 220%;
    opacity:0; transform:translateY(6px);
    transition:opacity .9s ease, transform .9s ease;
  }
  .greet-title.appear{ opacity:1; transform:translateY(0); animation: greeting-pan 5s ease-in-out infinite; }
  .greet-title.appear::after{
    content:""; position:absolute; left:-8%; top:0; height:100%; width:16%;
    pointer-events:none;
    background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,.6) 45%, transparent 90%);
    transform:skewX(-20deg);
    opacity:.0;
    animation: greeting-shine 2.2s cubic-bezier(.22,1,.36,1) .3s forwards;
  }
  .greet-underline{
    height:6px; width:0; border-radius:999px; margin-top:6px;
    background: linear-gradient(90deg,#2563eb,#10b981,#f59e0b,#ef4444);
    box-shadow:0 2px 8px rgba(0,0,0,.06);
    transition: width 1.1s cubic-bezier(.22,1,.36,1) .1s;
  }
  @media(min-width:992px){ .greet-title{ font-size:30px; } }
  @keyframes greeting-pan{ 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
  @keyframes greeting-shine{ 0%{opacity:0; transform:translateX(0) skewX(-20deg)} 100%{opacity:.45; transform:translateX(120%) skewX(-20deg)} }
  /* ========= [/REV-1] ========= */

  /* ===== Kartu Profil, Hero Meter, dst. ===== */
  .profile-card{ display:flex; align-items:center; gap:12px; margin-top:12px; padding:10px 12px; background:rgba(255,255,255,.68);
    border-radius:16px; box-shadow:0 10px 24px rgba(0,0,0,.06); backdrop-filter: blur(6px); cursor:pointer;}
  .profile-avatar{ width:68px; height:68px; border-radius:50%; overflow:hidden; position:relative; box-shadow:0 6px 16px rgba(0,0,0,.15);
    border:3px solid #fff; flex:0 0 68px;}
  .profile-avatar img{ width:100%; height:100%; object-fit:cover; display:block; }
  .profile-avatar:before{content:""; position:absolute; inset:-3px; border-radius:50%; background:conic-gradient(#60a5fa,#10b981,#f59e0b,#ef4444,#60a5fa);
    z-index:-1; filter:blur(8px); opacity:.35;}
  .profile-meta{ line-height:1.2; } .profile-name{ font-weight:800; font-size:16px; letter-spacing:.2px; }
  .profile-sub{ color:#6b7280; font-size:12px; margin-top:2px; } .profile-state{ color:#059669; font-size:12px; margin-top:2px; }

  .hero-meter{ margin-top:12px; padding:14px; border-radius:16px; background:linear-gradient(135deg,#ffffff,#f6f8ff);
    border:1px solid #e5e7eb; box-shadow:0 8px 20px rgba(2,6,23,.06);}
  .hm-top{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; }
  .hm-score{ font-weight:900; font-size:22px; }
  .hm-track{ position:relative; height:18px; border-radius:999px; overflow:hidden; background: linear-gradient(90deg, #fecaca 0%, #fecaca 50%, #d1fae5 50%, #d1fae5 100%); border:1px solid #e5e7eb;}
  .hm-zero{position:absolute; left:50%; top:0; bottom:0; width:2px; background:#11182722;}
  .hm-fill{ position:absolute; top:0; bottom:0; width:0; background:linear-gradient(90deg,#ef4444,#ef4444); transition: width .9s cubic-bezier(.22,1,.36,1); }
  .hm-fill.positive{ background:linear-gradient(90deg,#10b981,#22c55e); left:50%; }
  .hm-fill.negative{ background:linear-gradient(90deg,#f87171,#ef4444); right:50%; }
  .hm-thumb{ position:absolute; top:50%; transform:translate(-50%,-50%); width:22px; height:22px; border-radius:50%; border:2px solid #fff;
    background:#22c55e; left:50%; box-shadow:0 6px 18px rgba(0,0,0,.18); transition:left .9s cubic-bezier(.22,1,.36,1); --pulse: rgba(34,197,94,.45);}
  .hm-thumb.negative{ background:#ef4444; --pulse: rgba(239,68,68,.45); }
  .hm-thumb.pulse{ animation: hm-pulse-shadow 1.8s ease-out infinite; }
  @keyframes hm-pulse-shadow{ 0%{box-shadow:0 6px 18px rgba(0,0,0,.18), 0 0 0 0 var(--pulse)} 70%{box-shadow:0 6px 18px rgba(0,0,0,.18), 0 0 0 12px rgba(0,0,0,0)} 100%{box-shadow:0 6px 18px rgba(0,0,0,.18), 0 0 0 0 rgba(0,0,0,0)} }
  .hm-legend{ display:flex; justify-content:space-between; font-size:12px; color:#475569; margin-top:6px; }

  .saldo-wrap{background:#0b1220;border:1px solid #1f2a44;border-radius:12px;overflow:hidden;position:relative;box-shadow: inset 0 0 0 1px rgba(255,255,255,.03)}
  .saldo-zero{position:absolute;left:50%;top:0;bottom:0;width:2px;background:rgba(255,255,255,.25)}
  .saldo-bar{height:12px;transition:width .6s ease, transform .6s ease;}
  .saldo-green{background:linear-gradient(90deg,#86efac,#22c55e)}
  .saldo-red{background:linear-gradient(90deg,#fecaca,#ef4444)}
  .saldo-legend{display:flex;justify-content:space-between;font-size:12px;color:#0f172a;margin-top:8px}

  .viol-wrap{background:linear-gradient(180deg,#f8fafc,#f1f5f9);border:1px solid #e5e7eb;border-radius:12px;padding:12px;box-shadow:0 12px 24px rgba(2,6,23,.06)}
  .viol-bar{height:10px;border-radius:999px;background:#e5e7eb;overflow:hidden}
  .viol-fill{height:100%; width:0; transition:width .8s cubic-bezier(.22,1,.36,1);}

  .stage-card{ position:relative;border-radius:12px;background:#ffffff; border:1px solid #e5e7eb;padding:12px;margin-top:10px;overflow:hidden}
  .stage-card::before{ content:""; position:absolute; inset:0; pointer-events:none; background: radial-gradient(500px 120px at -10% -10%, rgba(99,102,241,.12), transparent 70%); }
  .stage-grid{display:grid;grid-template-columns:140px 1fr;gap:8px 14px}
  .stage-badge{display:inline-block;border-radius:999px;font-weight:900;padding:.15rem .6rem;color:#fff;box-shadow:0 6px 12px rgba(0,0,0,.12)}
  .sp-status{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;font-weight:800;}
  .sp-s1{background:#fff7ed;border:1px solid #fdba74;color:#b45309}
  .sp-s2{background:#fff1f2;border:1px solid #fda4af;color:#be123c}
  .sp-s3{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}
  .sp-s4{background:#fee2e2;border:1px solid #ef4444;color:#7f1d1d}
  .sp-safe{background:#ecfeff;border:1px solid #67e8f9;color:#155e75}

  .kpi-row{ display:flex; gap:12px; justify-content:space-between; }
  .kpi{ flex:1; color:#fff; border-radius:24px; padding:18px 18px 20px; position:relative; overflow:hidden;
        box-shadow:0 4px 14px rgba(0,0,0,.06); transition:transform .15s ease, box-shadow .15s ease; min-height:140px; min-width:0; }
  .kpi:hover{ transform:translateY(-2px); box-shadow:0 10px 22px rgba(0,0,0,.10); }
  .kpi-content{ display:flex; flex-direction:column; gap:8px; }
  .kpi .kpi-icon{ position:absolute; right:16px; top:14px; font-size:26px; opacity:.22; }
  .kpi .kpi-title{ font-size:14px; letter-spacing:.4px; opacity:.95; text-transform:uppercase; }
  .kpi .kpi-value{ font-size:48px; font-weight:800; line-height:1; letter-spacing:.5px; }
  .kpi .kpi-sub{ margin-top:2px; font-size:18px; font-weight:600; opacity:.95; line-height:1.25; }
  .kpi-total{ background:linear-gradient(135deg,#60a5fa,#2563eb); } .kpi-plus{ background:linear-gradient(135deg,#34d399,#059669);} .kpi-minus{background:linear-gradient(135deg,#fb7185,#dc2626);}
  .split-track{ margin-top:14px; width:100%; height:10px; border-radius:999px; background:#eef2ff; overflow:hidden; }
  .split-plus{ height:100%; background:#16a34a; float:left; transition:width .8s ease; }
  .split-minus{ height:100%; background:#ef4444; float:left; transition:width .8s ease; }

  .box-aktivitas-bulan .box-header{ display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; text-align:left; }
  .box-aktivitas-bulan .box-header .box-title{ margin:0; text-align:left; flex:1 1 auto; }
  .box-aktivitas-bulan .box-header .box-tools{ position:static !important; float:none !important; margin-left:auto; }
  .box-aktivitas-bulan .bulan-chipbar{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; overflow:auto; -webkit-overflow-scrolling:touch; }
  .box-aktivitas-bulan .bulan-chipbar::-webkit-scrollbar{ display:none; }

  .chip-filter{ appearance:none; border:1px solid #dbe4ff; background:#eef2ff; color:#1d4ed8; font-weight:700; font-size:13px;
    padding:8px 14px; border-radius:999px; cursor:pointer; box-shadow: inset 0 -2px 0 rgba(255,255,255,.6), 0 2px 6px rgba(30,64,175,.08);
    transition: background .25s ease, color .25s ease, box-shadow .25s ease, transform .06s ease, border-color .25s ease; }
  .chip-filter:hover{ background:#e0e7ff; box-shadow:0 8px 18px rgba(59,130,246,.25); }
  .chip-filter:active{ transform:translateY(1px); }
  .chip-filter:focus{ outline:0; box-shadow:0 0 0 3px rgba(59,130,246,.25); }
  .chip-filter.active[data-filter="all"]{ background:linear-gradient(180deg,#3b82f6,#2563eb); color:#fff; border-color:#1d4ed8; box-shadow:0 10px 20px rgba(37,99,235,.35); }
  .chip-filter.active[data-filter="Prestasi"]{ background:linear-gradient(180deg,#34d399,#059669); color:#fff; border-color:#059669; box-shadow:0 10px 20px rgba(5,150,105,.35); }
  .chip-filter.active[data-filter="Pelanggaran"]{ background:linear-gradient(180deg,#fb7185,#ef4444); color:#fff; border-color:#ef4444; box-shadow:0 10px 20px rgba(239,68,68,.35); }

  .list-aktivitas{ margin:0; padding:0; list-style:none; }
  .list-aktivitas li{ display:flex; gap:12px; align-items:flex-start; padding:12px 6px; border-bottom:1px dashed #e5e7eb; }
  .act-main{ flex:1; }
  .act-title{ margin:0; font-weight:700; }
  .act-meta{ color:#6b7280; font-size:12px; }
  .score-badge{ display:inline-flex; align-items:center; justify-content:center; min-width:34px; height:24px; padding:0 8px; border-radius:999px; color:#fff; font-weight:800; font-size:12px; }
  .sb-pos{ background:#10b981; } .sb-neg{ background:#ef4444; }

  .akt-toolbar{ display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; margin:8px 0 6px; }
  .perpage{ display:flex; align-items:center; gap:6px; }
  .perpage select{ padding:6px 8px; height:30px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; }
  .pager-controls{ display:flex; gap:6px; flex-wrap:wrap; align-items:center; justify-content:center; padding-top:8px; }
  .btn-pager{ border:1px solid #dbe4ff; background:#eef2ff; color:#1f2937; border-radius:999px; padding:6px 12px; font-size:12px; cursor:pointer; }
  .btn-pager.active{ background:#2563eb; color:#fff; border-color:#2563eb; }
  .btn-pager[disabled]{ opacity:.5; cursor:not-allowed; }

  .mini-card{ margin-top:10px; border:1px solid #e5e7eb; border-radius:14px; padding:12px; background:#fff; box-shadow:0 6px 16px rgba(2,6,23,.05); }
  .mini-prog{ height:8px; background:#eef2ff; border-radius:999px; overflow:hidden; }
  .mini-fill{ height:100%; width:0; background:#22c55e; transition:width 1s ease; }

  #backTop{ position:fixed; right:16px; bottom:18px; z-index:9999; display:none; width:44px; height:44px; border-radius:999px; border:none; cursor:pointer;
    background:linear-gradient(135deg,#60a5fa,#2563eb); color:#fff; box-shadow:0 10px 20px rgba(37,99,235,.25); }
  #backTop i{ font-size:18px; }
  #backTop.show{ display:flex; align-items:center; justify-content:center; }

  /* ====== Toggle Semester ====== */
  .seg-wrap{ display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
  .seg{ border:1px solid #e5e7eb; background:#fff; color:#1f2937; padding:6px 12px; border-radius:999px; font-size:12px; font-weight:700; cursor:pointer; }
  .seg.active{ background:linear-gradient(135deg,#10b981,#059669); color:#fff; border-color:#059669; box-shadow:0 8px 20px rgba(5,150,105,.25); }
  .subtle{ color:#6b7280; font-size:12px; }

  .kehadiran-head{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;}
  .kehadiran-head .box-title{margin:0;text-align:left;flex:1 1 auto;}
  .kehadiran-head .seg-wrap{margin-left:auto;}
  @media (max-width: 576px){
    .kehadiran-head{flex-direction:column;align-items:stretch;}
    .kehadiran-head .seg-wrap{width:100%;display:flex;justify-content:flex-end;}
  }

  /* ==========================================================
     ✅ POIN SAYA — Responsiveness overrides
     ========================================================== */
  @media (max-width: 1200px){ .kpi-row{ display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:12px; } }
  @media (max-width: 768px){ .kpi-row{ display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:10px; } }
  @media (max-width: 560px){ .kpi-row{ display:grid; grid-template-columns: 1fr; gap:10px; } }

  .kpi .kpi-title{ font-size: clamp(11px, 2.9vw, 14px); letter-spacing:.3px; }
  .kpi .kpi-value{ font-size: clamp(28px, 9.5vw, 48px); line-height:1.05; word-break:break-word; }
  .kpi .kpi-sub{ font-size: clamp(12px, 3.4vw, 18px); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .kpi .kpi-icon{ font-size: clamp(18px, 6.2vw, 26px); right:12px; top:10px; opacity:.20; }
  @media (max-width: 560px){ .kpi{ min-height:110px; padding:14px 14px 16px; border-radius:18px; } .split-track{ height:8px; margin-top:12px; } }

  /* ========= [REV-2] FITUR LANJUTAN – kartu interaktif ========= */
  .feat-grid{ display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:12px; }
  @media(max-width: 992px){ .feat-grid{ grid-template-columns: repeat(2, minmax(0,1fr)); } }
  @media(max-width: 560px){ .feat-grid{ grid-template-columns: 1fr; } }

  .feat-card{
    display:block; width:100%; text-align:left; cursor:pointer; border:1px solid #e5e7eb; border-radius:16px; padding:14px;
    background:linear-gradient(180deg,#ffffff,#f8fafc); box-shadow:0 8px 18px rgba(2,6,23,.06);
    transition:transform .12s ease, box-shadow .18s ease, border-color .18s ease;
    position:relative; overflow:hidden;
  }
  .feat-card:hover{ transform:translateY(-2px); box-shadow:0 14px 28px rgba(2,6,23,.12); border-color:#c7d2fe; }
  .feat-card.expanded{ box-shadow:0 18px 36px rgba(2,6,23,.16); border-color:#93c5fd; }

  .feat-head{ display:flex; align-items:center; gap:12px; }
  .feat-ic{ width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:#eef2ff; color:#2563eb; border:1px solid #dbeafe; }
  .feat-title{ margin:0; font-weight:800; color:#0f172a; font-size:16px; }
  .feat-sub{ margin-top:2px; color:#64748b; font-size:12.5px; }

  .feat-tags{ margin-top:8px; display:flex; flex-wrap:wrap; gap:6px; }
  .chip{ display:inline-flex; align-items:center; gap:6px; font-size:11px; font-weight:800; padding:4px 8px; border-radius:999px; border:1px solid; }
  .chip-live{ background:#ecfeff; border-color:#67e8f9; color:#155e75; }
  .chip-beta{ background:#fff7ed; border-color:#fdba74; color:#b45309; }
  .chip-soon{ background:#fee2e2; border-color:#fca5a5; color:#7f1d1d; }

  .feat-more{ margin-top:10px; color:#334155; font-size:13px; line-height:1.55; overflow:hidden; max-height:0; transition:max-height .28s ease; }
  .feat-card.expanded .feat-more{ /* height via JS */ }

  .feat-caret{ position:absolute; right:12px; top:12px; width:30px; height:30px; display:flex; align-items:center; justify-content:center; border-radius:999px; border:1px solid #e5e7eb; background:#fff; color:#334155; }
  .feat-card.expanded .feat-caret{ background:#eef2ff; color:#1d4ed8; border-color:#c7d2fe; }
  /* ========= [/REV-2] ========= */
</style>

<div class="row">
  <section class="col-lg-12">
    <div class="box box-solid" style="border-radius:12px;">
      <div class="box-body" style="padding:20px;background:linear-gradient(90deg,#f0f4ff,#f7f8fb);border-radius:12px;">
        <div class="row">
          <div class="col-md-7">
            <!-- Sapaan -->
            <div class="greet-wrap">
              <div id="greetTitle" class="greet-title" aria-label="Sapaan" aria-live="polite">
                Selamat datang, <?php echo h($profil['siswa_nama']); ?>
              </div>
              <div id="greetLine" class="greet-underline" aria-hidden="true"></div>
            </div>

            <div>
              <?php if($ta_aktif){ ?><span class="label label-primary" style="margin-right:6px;"><i class="fa fa-calendar"></i> TA <?php echo h($ta_aktif['ta_nama']); ?></span><?php } ?>
              <?php if($kls){ ?><span class="label label-default"><i class="fa fa-users"></i> <?php echo h($kls['kelas_nama']); ?></span><?php } ?>
            </div>

            <!-- ===== Kartu Foto Profil Siswa ===== -->
            <div class="profile-card" data-toggle="modal" data-target="#modalFotoSiswa" title="Klik untuk memperbesar foto">
              <div class="profile-avatar">
                <img id="fotoSiswaThumb" src="<?php echo h($foto_url); ?>" alt="Foto Profil Siswa">
              </div>
              <div class="profile-meta">
                <div class="profile-name"><?php echo h($profil['siswa_nama']); ?></div>
                <?php if($kls){ ?><div class="profile-sub"><?php echo h($kls['kelas_nama']); ?></div><?php } ?>
                <div class="profile-state"><i class="fa fa-circle"></i> Aktif</div>
              </div>
            </div>
            <!-- ===== /Kartu Foto Profil ===== -->

            <!-- ===== HERO METER ===== -->
            <div class="hero-meter" id="heroMeter" aria-label="Progres Saldo Poin">
              <div class="hm-top">
                <div><strong>Progres Saldo Poin</strong> <small class="text-muted">(Prestasi − Pelanggaran)</small></div>
                <div class="hm-score"><span id="hmScore">0</span> <small>poin</small></div>
              </div>
              <div class="hm-track">
                <div class="hm-zero"></div>
                <div id="hmFillPos" class="hm-fill positive" style="width:0"></div>
                <div id="hmFillNeg" class="hm-fill negative" style="width:0"></div>
                <span id="hmThumb" class="hm-thumb<?php echo $saldo<0? ' negative':''; ?>"></span>
              </div>
              <div class="hm-legend">
                <span>−100</span>
                <span><b>0</b></span>
                <span>+100</span>
              </div>

              <!-- Mini cards: Target & Tips -->
              <div class="mini-card" style="margin-top:10px;">
                <?php if($saldo < 0){ ?>
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                    <strong>Target kembali aman</strong>
                    <span class="label label-danger">Butuh +<?php echo $toSafe; ?> poin</span>
                  </div>
                  <div class="mini-prog"><div id="safeFill" class="mini-fill" style="width:0"></div></div>
                  <small class="text-muted">Kumpulkan prestasi untuk menutup defisit saldo.</small>
                <?php } else { ?>
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                    <strong>Pertahankan saldo baik</strong>
                    <span class="label label-success">Buffer +<?php echo $saldo; ?> poin</span>
                  </div>
                  <div class="mini-prog"><div id="safeFill" class="mini-fill" style="width:0"></div></div>
                  <small class="text-muted">Teruskan kebiasaan baik agar tetap di zona aman.</small>
                <?php } ?>
              </div>

              <div class="mini-card">
                <strong>Tips Disiplin Hari Ini</strong>
                <?php
                  $tips = [
                    "Datang 10 menit lebih awal agar tidak terburu-buru.",
                    "Rapikan atribut & rambut sesuai tata tertib sebelum berangkat.",
                    "Catat tugas dan target harianmu di buku kecil.",
                    "Minta tanda tangan pembina setelah mengikuti kegiatan positif.",
                    "Hindari hal yang berpotensi pelanggaran kecil berulang."
                  ];
                  $tip = $tips[array_rand($tips)];
                ?>
                <p style="margin:6px 0 0;"><?php echo h($tip); ?></p>
              </div>
            </div>
            <!-- ===== /HERO METER ===== -->

          </div>

          <!-- Panel kanan ringkasan progres -->
          <div class="col-md-5">
            <div class="box" style="border-radius:12px; overflow:hidden;">
              <div class="box-header with-border">
                <h3 class="box-title">Ringkasan Progres</h3>
                <div class="box-tools">
                  <strong style="font-size:16px;"><?php echo ($saldo>=0?'+':'').(int)$saldo; ?></strong> <small>poin</small>
                </div>
              </div>
              <div class="box-body">
                <div class="saldo-wrap" aria-label="Bar Saldo Poin">
                  <div class="saldo-zero"></div>
                  <div id="saldoBar" class="saldo-bar <?php echo $isPos?'saldo-green':'saldo-red'; ?>"
                       style="width:0; transform:translateX(<?php echo $isPos? '50%' : '-50%'; ?>);">
                  </div>
                </div>
                <div class="saldo-legend">
                  <div><b>Saldo:</b> <span style="color:<?php echo $isPos?'#059669':'#b91c1c'; ?>;"><?php echo ($saldo>=0?'+':'').(int)$saldo; ?></span> dari ±<?php echo (int)$scaleMaxSaldo; ?></div>
                  <div id="saldoPct">0%</div>
                </div>

                <div class="viol-wrap" style="margin-top:12px;">
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                    <b>Progres Poin (berdasarkan saldo)</b>
                    <span id="riskText"><?php echo $negSaldo; ?> / <?php echo (int)$scaleMax; ?> (risiko sanksi)</span>
                  </div>
                  <div class="viol-bar" title="Semakin penuh = saldo semakin negatif">
                    <div id="riskFill" class="viol-fill" style="width:0; background:<?php echo h($stageColor); ?>;"></div>
                  </div>

                  <div class="stage-card">
                    <div class="stage-grid">
                      <div><b>Tingkat</b></div>
                      <div>
                        <span class="stage-badge" style="background:<?php echo h($stageColor); ?>;">
                          <?php echo h($currentStage['roman']); ?>
                        </span>
                        <span style="margin-left:8px;color:#475569;">(Saldo: <?php echo ($saldo>=0?'+':'').$saldo; ?>)</span>
                      </div>

                      <div><b>Program</b></div>
                      <div><?php echo h($currentStage['program']); ?></div>

                      <div><b>Tindakan</b></div>
                      <div><?php echo h($currentStage['action']); ?></div>

                      <div><b>Perubahan Terakhir</b></div>
                      <div>
                        <?php if($last): ?>
                          <?php
                            $isPrestasi = (strtolower($last['jenis'])==='prestasi');
                            $sign = $isPrestasi ? '+' : '-';
                            $badgeStyle = $isPrestasi ? 'background:#dcfce7;color:#065f46' : 'background:#fee2e2;color:#991b1b';
                          ?>
                          <span class="table-badge" style="<?php echo $badgeStyle; ?>">
                            <?php echo $sign.(int)$last['poin']; ?>
                          </span>
                          <span style="margin-left:6px;">
                            <?php echo ucfirst(h($last['jenis'])); ?> — <?php echo h($last['nama']); ?>,
                            <i><?php echo date('d-m-Y H:i:s', strtotime($last['waktu'])); ?></i>
                          </span>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </div>

                      <div><b>Status SP</b></div>
                      <div>
                        <?php
                          $spCls = 'sp-safe'; if($spStatus==='SP1') $spCls='sp-s1'; elseif($spStatus==='SP2') $spCls='sp-s2'; elseif($spStatus==='SP3') $spCls='sp-s3'; elseif($spStatus==='SP4') $spCls='sp-s4';
                        ?>
                        <span class="sp-status <?php echo $spCls; ?>">
                          <i class="fa fa-flag"></i> <?php echo h($spStatus); ?>
                        </span>
                      </div>
                    </div>
                  </div>
                </div><!-- /viol-wrap -->

                <!-- ===== CTA: Jenjang Pembinaan Peserta Didik ===== -->
                <div class="text-center" style="margin-top:14px;">
                  <button id="btnJenjang" type="button" class="btn btn-primary btn-lg btn-elevate jenjang-cta btn-jenjang">
                    <i class="fa fa-sitemap"></i> Jenjang Pembinaan Peserta Didik
                  </button>
                </div>
                <!-- ===== /CTA ===== -->

              </div>
            </div>
          </div>
          <!-- /Panel Progres -->
        </div>
      </div>
    </div>
  </section>
</div>

<div class="row">
  <!-- Kehadiran SEMESTER -->
  <section class="col-lg-6">
    <div class="box box-primary" style="border-radius:12px;">
      <div class="box-header with-border kehadiran-head">
        <h3 class="box-title">Kehadiran Semester</h3>
        <div class="seg-wrap">
          <form id="frmSem" method="get" class="form-inline" style="margin:0;">
            <input type="hidden" name="sem" id="semVal" value="<?php echo (int)$sem_param; ?>">
            <?php foreach($_GET as $k=>$v){ if($k==='sem') continue; if(is_array($v)) continue; echo '<input type="hidden" name="'.h($k).'" value="'.h($v).'">'; } ?>
            <button type="button" class="seg <?php echo $sem_param===1?'active':''; ?>" onclick="setSem(1)">Semester 1</button>
            <button type="button" class="seg <?php echo $sem_param===2?'active':''; ?>" onclick="setSem(2)">Semester 2</button>
          </form>
        </div>
      </div>

      <div class="box-body">
        <div class="subtle" style="text-align:left;margin-bottom:8px;"><?php echo h($sem_label); ?></div>

        <div style="max-width:360px;margin:auto;">
          <canvas id="chartKehadiranSem" height="200" aria-label="Diagram Donat Kehadiran Semester"></canvas>
        </div>

        <div class="legend-row">
          <span class="legend-item"><span class="legend-box" style="background:#16a34a"></span>Hadir</span>
          <span class="legend-item"><span class="legend-box" style="background:#3b82f6"></span>Izin</span>
          <span class="legend-item"><span class="legend-box" style="background:#f59e0b"></span>Sakit</span>
          <span class="legend-item"><span class="legend-box" style="background:#ef4444"></span>Alpha</span>
        </div>

        <ul class="rekap-inline" style="justify-content:center;margin-top:10px;">
          <li><span class="badge-day bg-h">H</span><?php echo $kehadiran_sem['H']; ?> hari</li>
          <li><span class="badge-day bg-i">I</span><?php echo $kehadiran_sem['I']; ?> hari</li>
          <li><span class="badge-day bg-s">S</span><?php echo $kehadiran_sem['S']; ?> hari</li>
          <li><span class="badge-day bg-a">A</span><?php echo $kehadiran_sem['A']; ?> hari</li>
        </ul>

        <div class="text-center" style="margin-top:12px;">
          <a href="absensi.php?mode=semester&sem=<?php echo (int)$sem_param; ?>&view=bulanan" class="btn btn-primary btn-xs btn-elevate" style="border-radius:999px;">
            <i class="fa fa-calendar"></i> Lihat detail absensi
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- Poin Saya -->
  <section class="col-lg-6">
    <div class="box box-primary" style="border-radius:12px;">
      <div class="box-header with-border">
        <h3 class="box-title">
          Poin Saya
          <span class="meter-info" data-toggle="tooltip" data-placement="bottom"
            title="Ringkasan Poin: Total = (Jumlah poin prestasi) − (Jumlah poin pelanggaran). Di bawah ini ada pembagian proporsi +prestasi vs −pelanggaran.">
            <i class="fa fa-info-circle"></i>
          </span>
        </h3>
        <div class="box-tools pull-right"><a href="poin.php" class="btn btn-sm btn-default">Detail</a></div>
      </div>
      <div class="box-body">

        <div class="kpi-row">
          <div class="kpi kpi-total">
            <i class="fa fa-chart-line kpi-icon"></i>
            <div class="kpi-content">
              <div class="kpi-title">TOTAL</div>
              <div class="kpi-value" id="count-total2"><?php echo (int)$total_poin; ?></div>
              <div class="kpi-sub">Skor keseluruhan</div>
            </div>
          </div>

        <div class="kpi kpi-plus text-white">
            <i class="fa fa-trophy kpi-icon"></i>
            <div class="kpi-content">
              <div class="kpi-title">PRESTASI</div>
              <div class="kpi-value"><span id="count-plus"><?php echo (int)$plus; ?></span></div>
              <div class="kpi-sub"><?php echo (int)$cnt_prestasi; ?> prestasi</div>
            </div>
          </div>

          <div class="kpi kpi-minus text-white">
            <i class="fa fa-exclamation-triangle kpi-icon"></i>
            <div class="kpi-content">
              <div class="kpi-title">PELANGGARAN</div>
              <div class="kpi-value"><span id="count-minus"><?php echo (int)$minus; ?></span></div>
              <div class="kpi-sub"><?php echo (int)$cnt_pelanggaran; ?> pelanggaran</div>
            </div>
          </div>
        </div>

        <div class="split-track" title="+Prestasi vs −Pelanggaran">
          <span class="split-plus"  style="width: <?php echo $portion_plus; ?>%"></span>
          <span class="split-minus" style="width: <?php echo $portion_minus; ?>%"></span>
        </div>

        <hr>
        <div class="text-center">
          <a href="pelanggaran.php" class="btn btn-danger btn-sm btn-elevate" style="border-radius:999px;">
            <i class="fa fa-list"></i> Lihat semua jenis pelanggaran
          </a>
          <a href="prestasi.php" class="btn btn-success btn-sm btn-elevate" style="border-radius:999px;">
            <i class="fa fa-list"></i> Lihat semua jenis prestasi
          </a>
        </div>
      </div>
    </div>
  </section>
</div>

<div class="row">
  <!-- Aktivitas Poin Anda (Terbaru) -->
  <section class="col-lg-12">
    <div class="box box-primary" style="border-radius:12px;">
      <div class="box-header with-border">
        <h3 class="box-title">
          Aktivitas Poin Anda (Terbaru)
          <span class="meter-info" data-toggle="tooltip" data-placement="bottom"
                title="Aktivitas poin khusus milik Anda: penambahan (+prestasi) dan pengurangan (−pelanggaran) yang paling baru dicatat.">
            <i class="fa fa-info-circle"></i>
          </span>
        </h3>
      </div>
      <div class="box-body">
        <?php if(empty($aktivitas)){ ?>
          <div class="text-center text-muted" style="padding:20px;"><i class="fa fa-stream fa-2x"></i><br>Belum ada aktivitas.</div>
        <?php } else { ?>
          <ul class="products-list product-list-in-box">
            <?php foreach($aktivitas as $a){
              $isPos = ((int)$a['poin']>0);
              $lbl = $isPos ? 'label-success' : 'label-danger';
              $val = ($isPos?'+':'').(int)$a['poin'];
            ?>
              <li class="item">
                <div class="product-info" style="margin-left:0;">
                  <a class="product-title"><strong><?php echo h($a['tipe']); ?></strong> — <?php echo h($a['nama']); ?>
                    <span class="label <?php echo $lbl; ?> pull-right"><?php echo $val; ?></span>
                  </a>
                  <span class="product-description"><?php echo date('d M Y', strtotime($a['tgl'])); ?></span>
                </div>
              </li>
            <?php } ?>
          </ul>
        <?php } ?>
      </div>
    </div>
  </section>
</div>

<!-- ====== AKTIVITAS PENERIMA POIN TERBARU (SEMUA WAKTU) ====== -->
<div class="row">
  <section class="col-lg-12">
    <div class="box box-info box-aktivitas-bulan" style="border-radius:12px;">
      <div class="box-header with-border">
        <h3 class="box-title">
          Aktivitas Penerima Poin Terbaru
          <span class="meter-info" data-toggle="tooltip" data-placement="bottom"
                title="Gabungan SEMUA catatan +Prestasi dan −Pelanggaran untuk seluruh siswa, diurutkan dari yang paling baru. Gunakan chip di kanan untuk memfilter.">
            <i class="fa fa-info-circle"></i>
          </span>
        </h3>
        <div class="box-tools">
          <div class="bulan-chipbar" role="tablist" aria-label="Filter aktivitas">
            <button class="chip-filter active" data-filter="all" type="button" aria-pressed="true">Semua</button>
            <button class="chip-filter"        data-filter="Prestasi" type="button" aria-pressed="false">Prestasi</button>
            <button class="chip-filter"        data-filter="Pelanggaran" type="button" aria-pressed="false">Pelanggaran</button>
          </div>
        </div>
      </div>
      <div class="box-body">
        <?php if(empty($aktivitas_all)){ ?>
          <div class="text-center text-muted" style="padding:18px;"><i class="fa fa-info-circle"></i> Belum ada data.</div>
        <?php } else { ?>
          <div class="akt-toolbar">
            <div class="page-info" id="aktInfo">Menampilkan 0–0 dari 0 entri</div>
            <div class="perpage">
              <label for="aktPageSize" style="margin:0;">Tampilkan</label>
              <select id="aktPageSize">
                <option value="5" selected>5</option>
                <option value="10">10</option>
                <option value="15">15</option>
                <option value="20">20</option>
              </select>
              <span style="font-size:12px;color:#6b7280">per halaman</span>
            </div>
          </div>

          <ul id="listAktBulan" class="list-aktivitas">
            <?php foreach($aktivitas_all as $row){
              $isPrestasi = ($row['tipe']==='Prestasi');
              $cls = $isPrestasi ? 'sb-pos' : 'sb-neg';
              $badge = ($isPrestasi?'+':'').(int)$row['poin'];
            ?>
              <li data-tipe="<?php echo h($row['tipe']); ?>">
                <span class="score-badge <?php echo $cls; ?>"><?php echo $badge; ?></span>
                <div class="act-main">
                  <p class="act-title">
                    <strong><?php echo h($row['siswa']); ?></strong>
                    <span class="label <?php echo $isPrestasi ? 'label-success' : 'label-danger'; ?>" style="margin-left:6px;">
                      <?php echo h($row['tipe']); ?>
                    </span>
                  </p>
                  <div class="act-meta">
                    <?php echo h($row['nama']); ?> —
                    <i class="fa fa-clock-o"></i> <?php echo date('d M Y H:i', strtotime($row['tgl'])); ?>
                  </div>
                </div>
              </li>
            <?php } ?>
          </ul>

          <div id="aktPager" class="pager-controls" aria-label="Pagination"></div>
        <?php } ?>
      </div>
    </div>
  </section>
</div>

<!-- ====== [REV-2] REKOMENDASI FITUR — kartu interaktif ====== -->
<div class="row">
  <section class="col-lg-12">
    <div class="box box-default" style="border-radius:12px;">
      <div class="box-header with-border">
        <h3 class="box-title">Fitur Lanjutan</h3>
        <div class="box-tools">
          <span class="label label-success">Roadmap</span>
        </div>
      </div>
      <div class="box-body">
        <div class="feat-grid" id="featGrid">
          <!-- WABA -->
          <button type="button" class="feat-card" data-key="waba">
            <div class="feat-caret"><i class="fa fa-chevron-down"></i></div>
            <div class="feat-head">
              <div class="feat-ic"><i class="fa fa-whatsapp"></i></div>
              <div>
                <h4 class="feat-title">Notifikasi WhatsApp</h4>
                <div class="feat-sub">Kirim otomatis ke orang tua (WABA)</div>
              </div>
            </div>
            <div class="feat-tags"><span class="chip chip-live"><i class="fa fa-plug"></i> Integrasi</span><span class="chip chip-beta"><i class="fa fa-cog"></i> Opsional</span></div>
            <div class="feat-more">Pemberitahuan realtime untuk prestasi/pelanggaran dan rekap harian. Mendukung template pesan & jadwal pengiriman.</div>
          </button>

          <!-- Gamification -->
          <button type="button" class="feat-card" data-key="gami">
            <div class="feat-caret"><i class="fa fa-chevron-down"></i></div>
            <div class="feat-head">
              <div class="feat-ic"><i class="fa fa-star"></i></div>
              <div>
                <h4 class="feat-title">Motivasi & Gamification</h4>
                <div class="feat-sub">Badge, level, dan misi harian</div>
              </div>
            </div>
            <div class="feat-tags"><span class="chip chip-beta"><i class="fa fa-flask"></i> Beta</span></div>
            <div class="feat-more">Naikkan engagement siswa lewat misi harian dan target mingguan yang bisa dikonfigurasi, dilengkapi badge tematik dan level. Kumpulkan poin positif dari perilaku baik & prestasi, lalu tukar di koperasi untuk voucher kantin, alat tulis, atau merchandise resmi. Disiplin jadi seru sekaligus berhadiah!.</div>
          </button>

          <!-- Leaderboard -->
          <button type="button" class="feat-card" data-key="board">
            <div class="feat-caret"><i class="fa fa-chevron-down"></i></div>
            <div class="feat-head">
              <div class="feat-ic"><i class="fa fa-trophy"></i></div>
              <div>
                <h4 class="feat-title">Leaderboard Kelas</h4>
                <div class="feat-sub">Peringkat kelas & individu</div>
              </div>
            </div>
            <div class="feat-tags"><span class="chip chip-beta"><i class="fa fa-signal"></i> Live</span></div>
            <div class="feat-more">Papan peringkat otomatis per kelas & sekolah, lengkap dengan filter waktu dan highlight siswa teladan.</div>
          </button>

          <!-- Self-Service -->
          <button type="button" class="feat-card" data-key="self">
            <div class="feat-caret"><i class="fa fa-chevron-down"></i></div>
            <div class="feat-head">
              <div class="feat-ic"><i class="fa fa-id-badge"></i></div>
              <div>
                <h4 class="feat-title">Self-Service Siswa</h4>
                <div class="feat-sub">Ajukan klarifikasi & unggah bukti</div>
              </div>
            </div>
            <div class="feat-tags"><span class="chip chip-soon"><i class="fa fa-clock-o"></i> Soon</span></div>
            <div class="feat-more">Form mandiri untuk klarifikasi pelanggaran, ajukan prestasi, serta tracking status persetujuan guru/BK.</div>
          </button>

          <!-- Workflow Guru & BK -->
          <button type="button" class="feat-card" data-key="flow">
            <div class="feat-caret"><i class="fa fa-chevron-down"></i></div>
            <div class="feat-head">
              <div class="feat-ic"><i class="fa fa-sitemap"></i></div>
              <div>
                <h4 class="feat-title">Workflow Guru & BK</h4>
                <div class="feat-sub">Proses persetujuan & tugas terarah</div>
              </div>
            </div>
            <div class="feat-tags"><span class="chip chip-soon"><i class="fa fa-clock-o"></i> Soon</span></div>
            <div class="feat-more">Alur verifikasi multi-tahap, assignment ke pembina, serta histori tindakan yang transparan.</div>
          </button>

          <!-- Kalender Agenda -->
          <button type="button" class="feat-card" data-key="cal">
            <div class="feat-caret"><i class="fa fa-chevron-down"></i></div>
            <div class="feat-head">
              <div class="feat-ic"><i class="fa fa-calendar"></i></div>
              <div>
                <h4 class="feat-title">Kalender Agenda</h4>
                <div class="feat-sub">Ujian, OSIS, & pengumuman</div>
              </div>
            </div>
            <div class="feat-tags"><span class="chip chip-beta"><i class="fa fa-bell"></i> Remind</span></div>
            <div class="feat-more">Sinkron dengan event sekolah, notifikasi pengingat, dan tampilan kalender mingguan yang ringan.</div>
          </button>
        </div>
      </div>
    </div>
  </section>
</div>
<!-- ====== [/REV-2] ====== -->

<!-- Tombol Back to Top -->
<button id="backTop" aria-label="Kembali ke atas" title="Kembali ke atas"><i class="fa fa-arrow-up"></i></button>

<!-- Modal Perbesar Foto Siswa -->
<div class="modal fade" id="modalFotoSiswa" tabindex="-1" role="dialog" aria-labelledby="modalFotoLabel">
  <div class="modal-dialog" role="document" style="width:360px; max-width:95%;">
    <div class="modal-content" style="border-radius:14px; overflow:hidden;">
      <div class="modal-body" style="padding:0;">
        <img id="fotoSiswaFull" src="<?php echo h($foto_url); ?>" alt="Foto Profil Siswa" style="width:100%; display:block;">
      </div>
      <div class="modal-footer" style="display:flex; align-items:center; justify-content:space-between;">
        <strong><?php echo h($profil['siswa_nama']); ?><?php echo $kls ? ' — '.h($kls['kelas_nama']) : ''; ?></strong>
        <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/jenjang_pembinaan_modal.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
function animateNumber(el, end, duration){
  if(!el) return;
  var start = 0, startTime = null;
  end = Number(end)||0;
  function step(ts){
    if(!startTime) startTime = ts;
    var p = Math.min((ts - startTime)/duration, 1);
    el.textContent = Math.floor(p * end);
    if(p < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}

function countToSigned(el, value, duration){
  if(!el) return;
  var start = 0, startTime = null;
  var absEnd = Math.abs(Number(value)||0);
  var isNeg = value < 0;
  function step(ts){
    if(!startTime) startTime = ts;
    var p = Math.min((ts - startTime)/duration, 1);
    var val = Math.round(p * absEnd);
    el.textContent = (isNeg ? '-' : (value>0?'+':'')) + val;
    if(p < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}

function setSem(n){
  var f = document.getElementById('frmSem');
  var sv = document.getElementById('semVal');
  if(f && sv){
    sv.value = n;
    f.submit();
  }
}

document.addEventListener('DOMContentLoaded', function(){
  function setFallback(img){
    if(!img) return;
    img.addEventListener('error', function(){
      if(this.src.indexOf('user.png') === -1){
        this.src = '../gambar/sistem/user.png';
      }
    });
  }
  setFallback(document.getElementById('fotoSiswaThumb'));
  setFallback(document.getElementById('fotoSiswaFull'));

  // Donut Kehadiran SEMESTER
  var elSem = document.getElementById('chartKehadiranSem');
  if(elSem){
    new Chart(elSem, {
      type:'doughnut',
      data:{
        labels:['Hadir','Izin','Sakit','Alpha'],
        datasets:[{
          data:[<?php echo (int)$kehadiran_sem['H'];?>,<?php echo (int)$kehadiran_sem['I'];?>,<?php echo (int)$kehadiran_sem['S'];?>,<?php echo (int)$kehadiran_sem['A'];?>],
          backgroundColor: ['#16a34a','#3b82f6','#f59e0b','#ef4444'],
          hoverBackgroundColor: ['#16a34a','#3b82f6','#f59e0b','#ef4444'],
          hoverOffset: 6
        }]
      },
      options:{
        plugins:{
          legend:{ display:false },
          tooltip:{
            callbacks:{
              label: function(ctx){
                var total = ctx.dataset.data.reduce(function(a,b){ return a+b; },0) || 1;
                var val = ctx.parsed || 0;
                var pct = Math.round(val/total*100);
                return ctx.label + ': ' + val + ' (' + pct + '%)';
              }
            }
          }
        },
        cutout:'60%',
        animation:{ animateRotate:true, animateScale:true, duration:1100, easing:'easeOutQuart' },
        maintainAspectRatio:false
      }
    });
  }

  // Animasi angka KPI
  animateNumber(document.getElementById('count-total2'), <?php echo (int)$total_poin; ?>, 800);
  animateNumber(document.getElementById('count-plus'),   <?php echo (int)$plus; ?>,        900);
  animateNumber(document.getElementById('count-minus'),  <?php echo (int)$minus; ?>,       900);

  // ========= [REV-1] Greeting underline mengikuti lebar teks =========
  (function(){
    var gt = document.getElementById('greetTitle');
    var gl = document.getElementById('greetLine');
    if (gt) gt.classList.add('appear');

    function syncGreetLine(){
      if(!gt || !gl) return;
      // hitung lebar aktual teks (elemen sudah inline-block)
      var w = Math.ceil(gt.getBoundingClientRect().width);
      // batas minimum/maximum biar aman
      var maxW = Math.ceil((gt.parentElement || gt).getBoundingClientRect().width);
      if(w > maxW) w = maxW;
      gl.style.width = '0px'; // reset agar anim ulang
      gl.offsetHeight;        // reflow
      gl.classList.add('appear');
      gl.style.width = w + 'px';
    }

    // Jalankan setelah font siap (jika didukung), + fallback
    var doSync = function(){ setTimeout(syncGreetLine, 30); setTimeout(syncGreetLine, 400); };
    if (document.fonts && document.fonts.ready) { document.fonts.ready.then(doSync); }
    else { doSync(); }

    // Resize handler (debounced)
    var rT; window.addEventListener('resize', function(){ clearTimeout(rT); rT = setTimeout(syncGreetLine, 140); });
  })();
  // ========= [/REV-1] =========

  // Tooltip Bootstrap
  if (typeof $ !== 'undefined' && $.fn.tooltip) {
    $('[data-toggle="tooltip"]').tooltip();
  }

  // Hero Meter
  (function(){
    var saldo = <?php echo (int)$saldo; ?>;
    var hmScore = document.getElementById('hmScore');
    countToSigned(hmScore, saldo, 1000);

    var thumb = document.getElementById('hmThumb');
    var fillP = document.getElementById('hmFillPos');
    var fillN = document.getElementById('hmFillNeg');
    var safeF = document.getElementById('safeFill');

    if(saldo >= 0){
      fillP.style.width = '0';
      setTimeout(function(){ fillP.style.width = Math.min(100, saldo) + '%'; }, 60);
      if(thumb){
        thumb.classList.remove('negative');
        thumb.style.left = '50%';
        setTimeout(function(){
          thumb.style.left = (50 + Math.min(50, saldo/2)) + '%';
          thumb.classList.add('pulse');
        }, 80);
      }
    }else{
      fillN.style.width = '0';
      setTimeout(function(){ fillN.style.width = Math.min(100, Math.abs(saldo)) + '%'; }, 60);
      if(thumb){
        thumb.classList.add('negative');
        thumb.style.left = '50%';
        setTimeout(function(){
          thumb.style.left = (50 - Math.min(50, Math.abs(saldo)/2)) + '%';
          thumb.classList.add('pulse');
        }, 80);
      }
    }
    setTimeout(function(){ if(thumb) thumb.classList.remove('pulse'); }, 6000);

    if(safeF){
      var toSafe = <?php echo (int)$toSafe; ?>;
      var w = 0;
      if(<?php echo $saldo<0 ? 'true':'false'; ?>){
        w = Math.min(100, Math.round((100 - Math.min(100,toSafe))));
      }else{
        w = Math.min(100, Math.round(Math.min(100, saldo)));
      }
      setTimeout(function(){ safeF.style.width = w + '%'; }, 150);
    }
  })();

  // Panel kanan: animasi bar & risiko
  (function(){
    var saldoPct = document.getElementById('saldoPct');
    var saldoBar = document.getElementById('saldoBar');
    var riskFill = document.getElementById('riskFill');
    var riskText = document.getElementById('riskText');
    var pct  = <?php echo (int)$percentSaldo; ?>;
    var rPct = <?php echo (int)$riskPercent; ?>;
    setTimeout(function(){
      if(saldoBar) saldoBar.style.width = pct + '%';
      if(saldoPct) saldoPct.textContent = pct + '%';
      if(riskFill) riskFill.style.width = rPct + '%';
      if(riskText) riskText.textContent = '<?php echo $negSaldo; ?> / <?php echo (int)$scaleMax; ?> (risiko sanksi)';
    }, 150);
  })();

  // Filter + Pagination — Aktivitas Penerima Poin Terbaru
  var list = document.getElementById('listAktBulan');
  var pager = document.getElementById('aktPager');
  var info  = document.getElementById('aktInfo');
  var sel   = document.getElementById('aktPageSize');

  if(list && pager && info && sel){
    var items = Array.prototype.slice.call(list.children);
    var currentFilter = 'all';
    var perPage = parseInt(sel.value,10) || 5;
    var currentPage = 1;

    function getFiltered(){
      if(currentFilter === 'all') return items;
      return items.filter(function(li){ return li.getAttribute('data-tipe') === currentFilter; });
    }

    function render(){
      var filtered = getFiltered();
      var total = filtered.length;
      var totalPages = Math.max(1, Math.ceil(total / perPage));
      if(currentPage > totalPages) currentPage = totalPages;

      items.forEach(function(li){ li.style.display = 'none'; });

      var start = (currentPage - 1) * perPage;
      var end   = Math.min(start + perPage, total);
      filtered.slice(start, end).forEach(function(li){ li.style.display = 'flex'; });

      if(total === 0) info.textContent = 'Tidak ada entri untuk filter ini.';
      else info.textContent = 'Menampilkan ' + (start+1) + '–' + end + ' dari ' + total + ' entri';

      while(pager.firstChild) pager.removeChild(pager.firstChild);

      function makeBtn(label, disabled, handler, active){
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn-pager' + (active ? ' active' : '');
        b.textContent = label;
        if(disabled) b.setAttribute('disabled','disabled');
        b.addEventListener('click', handler);
        pager.appendChild(b);
      }

      makeBtn('Prev', currentPage===1, function(){ currentPage--; render(); });

      var maxBtns = 7;
      var startPage = Math.max(1, currentPage - Math.floor(maxBtns/2));
      var endPage   = Math.min(totalPages, startPage + maxBtns - 1);
      if(endPage - startPage + 1 < maxBtns){ startPage = Math.max(1, endPage - maxBtns + 1); }
      for(var p=startPage; p<=endPage; p++){
        (function(pp){
          makeBtn(String(pp), false, function(){ currentPage = pp; render(); }, pp===currentPage);
        })(p);
      }

      makeBtn('Next', currentPage===totalPages, function(){ currentPage++; render(); });
    }

    var chips = document.querySelectorAll('.chip-filter');
    chips.forEach(function(btn){
      btn.classList.remove('active');
      if(btn.getAttribute('data-filter')==='all') btn.classList.add('active');
      btn.addEventListener('click', function(){
        chips.forEach(function(b){
          b.classList.remove('active');
          b.setAttribute('aria-pressed','false');
        });
        this.classList.add('active');
        this.setAttribute('aria-pressed','true');
        currentFilter = this.getAttribute('data-filter');
        currentPage = 1;
        render();
      });
    });

    sel.addEventListener('change', function(){
      perPage = parseInt(this.value,10) || 5;
      currentPage = 1;
      render();
    });

    render();
  }

  // Back to top toggle
  var backTop = document.getElementById('backTop');
  function toggleBackTop(){
    if(window.scrollY > 400){ backTop.classList.add('show'); }
    else{ backTop.classList.remove('show'); }
  }
  window.addEventListener('scroll', toggleBackTop);
  backTop.addEventListener('click', function(){ window.scrollTo({top:0, behavior:'smooth'}); });
  toggleBackTop();

  // ===== [REV-2] Interaksi kartu Fitur Lanjutan =====
  (function(){
    var grid = document.getElementById('featGrid');
    if(!grid) return;
    var cards = grid.querySelectorAll('.feat-card');

    function setMore(card, expand){
      var more = card.querySelector('.feat-more');
      if(!more) return;
      if(expand){
        card.classList.add('expanded');
        more.style.maxHeight = more.scrollHeight + 'px';
      }else{
        card.classList.remove('expanded');
        more.style.maxHeight = '0px';
      }
    }

    cards.forEach(function(c){
      c.addEventListener('click', function(){
        var already = this.classList.contains('expanded');
        cards.forEach(function(o){ setMore(o, false); });
        setMore(this, !already);
      });
    });
  })();
  // ===== [/REV-2] =====

});
</script>

  </section>
</div>
<?php include 'footer.php'; ?>
