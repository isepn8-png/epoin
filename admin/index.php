<?php include 'header.php'; ?>
<?php include '../koneksi.php'; ?>

<?php
/* =========================
   Helper DB (anti-HTTP 500)
   ========================= */
mysqli_report(MYSQLI_REPORT_OFF); // cegah mysqli melempar exception
function db_row($koneksi, $sql){
  $res = mysqli_query($koneksi, $sql);
  if ($res instanceof mysqli_result){
    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    return $row ?: null;
  } else {
    error_log('[SQL ERR] '.mysqli_error($koneksi).' | '.$sql);
    return null;
  }
}
function db_count($koneksi, $sql){
  $row = db_row($koneksi, $sql);
  return (int)($row['total'] ?? 0);
}
function db_query($koneksi, $sql){
  $res = mysqli_query($koneksi, $sql);
  if ($res === false){
    error_log('[SQL ERR] '.mysqli_error($koneksi).' | '.$sql);
  }
  return $res; // bisa mysqli_result atau false
}

// ------- data nama yang login (aman dengan fallback) -------
$nama_login = $_SESSION['nama'] ?? $_SESSION['user_nama'] ?? $_SESSION['username'] ?? 'Admin';

/* ================================================
   KONFIGURASI: SEMBUNYIKAN AKUN DARI PANEL STATUS
   - Sembunyikan akun "Admin EP / superadmin_ep / user_id tertentu"
   - Dicocokkan case-insensitive untuk username/nama
   - Tambah user_id jika perlu, contoh: [1, 9]
   ================================================ */
$HIDE_ADMIN_USER_IDS = [ /* isi user_id jika ingin, contoh: 1 */ ];
$HIDE_ADMIN_USERNAMES = ['superadmin_ep', 'Admin EP']; // username atau nama tampilan (case-insensitive)

// ------- FILTER: Tahun Ajaran (TA) -------
$ta_aktif   = db_row($koneksi,"SELECT ta_id,ta_nama FROM ta WHERE ta_status='1' ORDER BY ta_id DESC LIMIT 1");
$TA_SELECTED = isset($_GET['ta']) ? (int)$_GET['ta'] : (int)($ta_aktif['ta_id'] ?? 0);

// Label TA terpilih
$ta_row = null;
if ($TA_SELECTED > 0) {
  $ta_row = db_row($koneksi,"SELECT ta_id,ta_nama,ta_status FROM ta WHERE ta_id=".(int)$TA_SELECTED." LIMIT 1");
}
if (!$ta_row) { // fallback: pilih TA terbaru jika filter tak valid
  $ta_row = db_row($koneksi,"SELECT ta_id,ta_nama,ta_status FROM ta ORDER BY ta_status DESC, ta_id DESC LIMIT 1");
  $TA_SELECTED = (int)($ta_row['ta_id'] ?? 0);
}
$TA_LABEL = $ta_row ? ($ta_row['ta_nama'].($ta_row['ta_status']=='1'?' (Aktif)':'')) : 'Semua TA';

// ------- KPI (sebagian mengikuti TA) -------
$SISWA = db_count($koneksi,"SELECT COUNT(*) total FROM siswa");
$ADMIN = db_count($koneksi, "
  SELECT COUNT(*) total FROM (
    SELECT DISTINCT u.user_id
    FROM user u
    JOIN user_roles ur ON ur.user_id = u.user_id
    JOIN roles r       ON r.role_id  = ur.role_id
    WHERE u.linked_siswa_id IS NULL
      AND r.role_key IN ('guru','tas','administrator','superadmin')
  ) x
");
$ROMBEL= db_count($koneksi,"SELECT COUNT(*) total FROM jurusan");

// Kelas dalam TA terpilih
$KELAS = db_count($koneksi,"SELECT COUNT(*) total FROM kelas WHERE kelas_ta=".(int)$TA_SELECTED);

// Jenis pelanggaran & prestasi (master, tidak tergantung TA)
$JPel = db_count($koneksi,"SELECT COUNT(*) total FROM pelanggaran");
$JPres= db_count($koneksi,"SELECT COUNT(*) total FROM prestasi");

// Transaksi per TA (filter via join kelas -> kelas_ta)
$TPel = db_count($koneksi,"
  SELECT COUNT(*) total
  FROM input_pelanggaran ip
  JOIN kelas k ON ip.kelas=k.kelas_id
  WHERE k.kelas_ta=".(int)$TA_SELECTED);
$TPres = db_count($koneksi,"
  SELECT COUNT(*) total
  FROM input_prestasi ip
  JOIN kelas k ON ip.kelas=k.kelas_id
  WHERE k.kelas_ta=".(int)$TA_SELECTED);

/* ======== DATA GRAFIK (tren bulanan) — pakai kolom INT YYYYMM (ip_ym/pr_ym) ======== */
$pel_month = [];
$q1 = db_query($koneksi,"
  SELECT ip.ip_ym AS ym, COUNT(*) cnt
  FROM input_pelanggaran ip
  JOIN kelas k ON ip.kelas=k.kelas_id
  WHERE k.kelas_ta=".(int)$TA_SELECTED."
  GROUP BY ip.ip_ym
  ORDER BY ip.ip_ym
");
if($q1){
  while($r=mysqli_fetch_assoc($q1)){ $pel_month[(int)$r['ym']] = (int)$r['cnt']; }
  mysqli_free_result($q1);
}

$pres_month = [];
$q2 = db_query($koneksi,"
  SELECT ip.pr_ym AS ym, COUNT(*) cnt
  FROM input_prestasi ip
  JOIN kelas k ON ip.kelas=k.kelas_id
  WHERE k.kelas_ta=".(int)$TA_SELECTED."
  GROUP BY ip.pr_ym
  ORDER BY ip.pr_ym
");
if($q2){
  while($r=mysqli_fetch_assoc($q2)){ $pres_month[(int)$r['ym']] = (int)$r['cnt']; }
  mysqli_free_result($q2);
}

/* Gabungkan semua bulan */
$m1 = array_keys($pel_month);
$m2 = array_keys($pres_month);
$months = array_values(array_unique(array_merge($m1,$m2)));
sort($months, SORT_NUMERIC);

/* Label & dataset Chart.js */
function label_bulan_num($ymInt){
  $y = (int)floor($ymInt/100);
  $m = (int)($ymInt % 100);
  $t = strtotime(sprintf('%04d-%02d-01',$y,$m));
  return date('M Y',$t);
}
$labels = array_map('label_bulan_num',$months);
$seriePel = array_map(function($m)use($pel_month){ return (int)($pel_month[$m] ?? 0); }, $months);
$seriePres= array_map(function($m)use($pres_month){ return (int)($pres_month[$m] ?? 0); }, $months);

// ------- TOP LIST (mengikuti TA terpilih) -------
$topPel = db_query($koneksi,"
  SELECT s.siswa_nama,s.siswa_nis,SUM(p.pelanggaran_point) total_pelanggaran
  FROM input_pelanggaran ip
  JOIN pelanggaran p ON ip.pelanggaran=p.pelanggaran_id
  JOIN siswa s ON ip.siswa=s.siswa_id
  JOIN kelas k ON ip.kelas=k.kelas_id
  WHERE k.kelas_ta=".(int)$TA_SELECTED."
  GROUP BY s.siswa_id
  ORDER BY total_pelanggaran DESC
  LIMIT 5
");

/* Skor tertinggi (prestasi - pelanggaran) */
$rankTop = [];
$qrank = db_query($koneksi,"
  SELECT s.siswa_id, s.siswa_nama, s.siswa_nis,
         IFNULL(pres.total_pres,0) - IFNULL(pel.total_pel,0) AS skor
  FROM kelas_siswa ks
  JOIN siswa s ON ks.ks_siswa = s.siswa_id
  JOIN kelas k ON ks.ks_kelas = k.kelas_id AND k.kelas_ta = ".(int)$TA_SELECTED."
  LEFT JOIN (
    SELECT ip.siswa, SUM(pr.prestasi_point) AS total_pres
    FROM input_prestasi ip
    JOIN prestasi pr ON pr.prestasi_id = ip.prestasi
    JOIN kelas k2 ON ip.kelas = k2.kelas_id
    WHERE k2.kelas_ta = ".(int)$TA_SELECTED."
    GROUP BY ip.siswa
  ) pres ON pres.siswa = s.siswa_id
  LEFT JOIN (
    SELECT ig.siswa, SUM(pl.pelanggaran_point) AS total_pel
    FROM input_pelanggaran ig
    JOIN pelanggaran pl ON pl.pelanggaran_id = ig.pelanggaran
    JOIN kelas k3 ON ig.kelas = k3.kelas_id
    WHERE k3.kelas_ta = ".(int)$TA_SELECTED."
    GROUP BY ig.siswa
  ) pel ON pel.siswa = s.siswa_id
  ORDER BY skor DESC
  LIMIT 5
");
if($qrank){
  while($row = mysqli_fetch_assoc($qrank)){
    $rankTop[] = [
      'siswa_nama' => $row['siswa_nama'],
      'siswa_nis'  => $row['siswa_nis'],
      'skor'       => (int)$row['skor']
    ];
  }
  mysqli_free_result($qrank);
}

/* ======== SP DISTRIBUTION & EARLY WARNING (per TA terpilih) ======== */
$spDist = ['aman'=>0,'pembinaan'=>0,'sp1'=>0,'sp2'=>0,'sp3'=>0,'sp4'=>0];
$earlyWarning = [];

$qSaldo = db_query($koneksi, "
  SELECT s.siswa_id, s.siswa_nama, s.siswa_nis,
         IFNULL(pres.total,0) - IFNULL(pel.total,0) AS saldo
  FROM kelas_siswa ks
  JOIN siswa s ON ks.ks_siswa = s.siswa_id
  JOIN kelas k  ON ks.ks_kelas = k.kelas_id AND k.kelas_ta = ".(int)$TA_SELECTED."
  LEFT JOIN (
    SELECT ip.siswa, SUM(pr.prestasi_point) AS total
    FROM input_prestasi ip
    JOIN prestasi pr ON pr.prestasi_id = ip.prestasi
    JOIN kelas k2 ON ip.kelas = k2.kelas_id AND k2.kelas_ta = ".(int)$TA_SELECTED."
    GROUP BY ip.siswa
  ) pres ON pres.siswa = s.siswa_id
  LEFT JOIN (
    SELECT ig.siswa, SUM(pl.pelanggaran_point) AS total
    FROM input_pelanggaran ig
    JOIN pelanggaran pl ON pl.pelanggaran_id = ig.pelanggaran
    JOIN kelas k3 ON ig.kelas = k3.kelas_id AND k3.kelas_ta = ".(int)$TA_SELECTED."
    GROUP BY ig.siswa
  ) pel ON pel.siswa = s.siswa_id
  GROUP BY s.siswa_id, s.siswa_nama, s.siswa_nis
  ORDER BY saldo ASC
");
if ($qSaldo) {
  while ($r = mysqli_fetch_assoc($qSaldo)) {
    $saldo = (int)$r['saldo'];
    $neg   = max(0, -$saldo);
    if ($neg === 0) {
      $spDist['aman']++;
    } elseif ($neg <= 20) {
      $spDist['pembinaan']++;
      if ($neg >= 16) {
        $earlyWarning[] = ['siswa_id'=>(int)$r['siswa_id'],'siswa_nama'=>$r['siswa_nama'],'siswa_nis'=>$r['siswa_nis'],'saldo'=>$saldo,'mendekati'=>'SP1','jarak'=>21-$neg];
      }
    } elseif ($neg <= 40) {
      $spDist['sp1']++;
      if ($neg >= 36) {
        $earlyWarning[] = ['siswa_id'=>(int)$r['siswa_id'],'siswa_nama'=>$r['siswa_nama'],'siswa_nis'=>$r['siswa_nis'],'saldo'=>$saldo,'mendekati'=>'SP2','jarak'=>41-$neg];
      }
    } elseif ($neg <= 60) {
      $spDist['sp2']++;
      if ($neg >= 56) {
        $earlyWarning[] = ['siswa_id'=>(int)$r['siswa_id'],'siswa_nama'=>$r['siswa_nama'],'siswa_nis'=>$r['siswa_nis'],'saldo'=>$saldo,'mendekati'=>'SP3','jarak'=>61-$neg];
      }
    } elseif ($neg <= 80) {
      $spDist['sp3']++;
      if ($neg >= 76) {
        $earlyWarning[] = ['siswa_id'=>(int)$r['siswa_id'],'siswa_nama'=>$r['siswa_nama'],'siswa_nis'=>$r['siswa_nis'],'saldo'=>$saldo,'mendekati'=>'SP4','jarak'=>81-$neg];
      }
    } else {
      $spDist['sp4']++;
    }
  }
  mysqli_free_result($qSaldo);
}
usort($earlyWarning, function($a,$b){ return $a['jarak'] - $b['jarak']; });
$EW_COUNT = count($earlyWarning);
$SP_AKTIF = $spDist['sp1'] + $spDist['sp2'] + $spDist['sp3'] + $spDist['sp4'];

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
$qa = db_query($koneksi,$sqlAktAll);
if($qa){
  while($row=mysqli_fetch_assoc($qa)){
    $isPrestasi = (strcasecmp($row['tipe'] ?? '', 'Prestasi') === 0);
    $nama = trim($row['nama'] ?? '');
    $needle = 'memberikan informasi valid terkait pelanggaran';
    if($isPrestasi && stripos($nama, $needle) !== false){ continue; }
    $aktivitas_all[]=$row;
  }
  mysqli_free_result($qa);
}
?>

<style>
  /* ===== THEME & RESETS ===== */
  :root{
    --c-primary:#2563eb; --c-primary-2:#60a5fa;
    --c-green:#059669; --c-amber:#d97706; --c-red:#dc2626;
    --c-slate:#0f172a; --c-muted:#64748b; --c-border:#eef2f7; --c-bg:#f8fafc;
    --top-nav: 58px; /* akan dioverride JS sesuai tinggi navbar */

    /* ===== NEW: Hero underline sizing (filled via JS) ===== */
    --welcome-underline-width: 100%;
  }
  *{-webkit-tap-highlight-color:transparent}
  .content{padding-bottom:78px}

  /* ===== UTIL ===== */
  .table-hover tbody tr:hover{background:#f5f7fb}
  .status-badge{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:6px}
  .status-online{background:#28a745}.status-offline{background:#dc3545}
  .status-text{color:#111827;font-weight:600}
  .text-muted-600{color:#64748b}

  /* ===== HERO ===== */
  .hero-card{
    background:linear-gradient(90deg,#eef4ff,#f8fafc);
    border-radius:16px;padding:22px 24px;margin-bottom:14px;
    box-shadow:0 6px 18px rgba(30,58,138,.06)
  }
  .welcome-wrap{display:flex;gap:16px;align-items:center;flex-wrap:wrap}
  .welcome-icon{width:48px;height:48px;border-radius:12px;display:grid;place-items:center;background:#fff;color:#2563eb;box-shadow:0 6px 14px rgba(37,99,235,.15)}
  .welcome-text{
    margin:0 0 12px 0; /* beri jarak ke subteks */
    font-weight:800;letter-spacing:.3px;
    background:linear-gradient(90deg,#111827,#2563eb 60%,#06b6d4);
    -webkit-background-clip:text;background-clip:text;color:transparent;
    font-size:clamp(18px,3.6vw,26px);position:relative;display:inline-block;
  }
  /* Garis cantik di bawah welcome: lebih tebal, glow, dan mengikuti ujung nama (via --welcome-underline-width) */
  .welcome-text::before{
    content:"";
    position:absolute;
    left:0; bottom:-8px;
    height:5px; /* lebih tebal */
    width:0; /* start 0 lalu animate ke target */
    max-width:var(--welcome-underline-width);
    background:linear-gradient(90deg,#2563eb 0%,#60a5fa 50%,#06b6d4 100%);
    border-radius:999px;
    box-shadow:0 2px 10px rgba(37,99,235,.35);
    opacity:.0;
    pointer-events:none;
  }
  @media(prefers-reduced-motion:no-preference){
    .welcome-text::before{
      animation: underline-grow-elite .9s ease-out .15s forwards;
    }
    @keyframes underline-grow-elite{
      0%   { width:0;   opacity:0;   transform:translateY(2px); }
      100% { width:var(--welcome-underline-width); opacity:1; transform:translateY(0); }
    }
  }
  @media(prefers-reduced-motion:reduce){
    .welcome-text::before{ width:var(--welcome-underline-width); opacity:1; }
  }

  @media(prefers-reduced-motion:no-preference){
    .welcome-text{animation:fadeIn .6s ease both}
    .welcome-text:after{
      content:"";position:absolute;inset:0;transform:translateX(-120%) skewX(-20deg);
      background:linear-gradient(120deg,transparent 0%,rgba(255,255,255,.75) 50%,transparent 100%);
      animation:shine 2.8s ease-in-out infinite
    }
    @keyframes shine{0%{transform:translateX(-120%) skewX(-20deg)}60%{transform:translateX(100%) skewX(-20deg)}100%{transform:translateX(120%) skewX(-20deg)}}
    @keyframes fadeIn{from{opacity:0;translate:0 6px}to{opacity:1;translate:0 0}}
  }

  /* ===== FILTER BAR ===== */
  .filter-bar{
    position:sticky;top:calc(var(--top-nav) + 6px);z-index:7;
    background:#fff;border:1px solid var(--c-border);border-radius:12px;padding:10px;
    box-shadow:0 4px 12px rgba(0,0,0,.045);margin-bottom:12px
  }
  .filter-bar-inner{display:grid;gap:10px;align-items:center;grid-template-columns:auto 1fr auto;min-width:0}
  .filter-pill{display:inline-flex;align-items:center;gap:8px;background:#eef2ff;border:1px solid #dbe4ff;border-radius:999px;padding:6px 10px;font-weight:700;color:#1d4ed8}
  .filter-form{display:grid;gap:8px;align-items:center;grid-template-columns:minmax(180px,1fr) auto auto}
  .filter-form .form-control{width:100%}
  .filter-actions .btn{min-width:96px}
  .filter-ta{justify-self:end}
  .filter-ta .badge-soft{white-space:nowrap}
  @media(max-width:768px){
    .filter-bar-inner{grid-template-columns:1fr}
    .filter-form{grid-template-columns:1fr}
    .filter-actions .btn{width:100%}
    .filter-ta{justify-self:start}
  }

  /* ===== QUICK ACTIONS ===== */
  .quick-actions{display:grid;grid-template-columns:repeat(12,1fr);gap:10px;margin-bottom:12px}
  .qa{grid-column:span 3;display:flex;align-items:center;gap:10px;padding:12px;border-radius:12px;background:#fff;border:1px solid var(--c-border);box-shadow:0 6px 14px rgba(0,0,0,.05);transition:transform .15s ease,box-shadow .15s ease}
  .qa:hover{transform:translateY(-2px);box-shadow:0 12px 20px rgba(0,0,0,.08)}
  .qa:active{transform:scale(.99)}
  .qa .ico{width:38px;height:38px;border-radius:10px;display:grid;place-items:center;color:#fff}
  .ico-red{background:linear-gradient(135deg,#fb7185,#dc2626)}
  .ico-green{background:linear-gradient(135deg,#34d399,#059669)}
  .ico-blue{background:linear-gradient(135deg,#60a5fa,#2563eb)}
  .ico-amber{background:linear-gradient(135deg,#f59e0b,#d97706)}
  @media(max-width:1199px){.qa{grid-column:span 6}}
  @media(max-width:576px){.qa{grid-column:span 12}}

  /* ===== KPI ===== */
  .kpi-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:14px}
  .kpi{min-height:116px;border-radius:18px;color:#fff;position:relative;overflow:hidden;padding:18px 16px 14px;box-shadow:0 8px 24px rgba(0,0,0,.10);transition:transform .18s ease,box-shadow .18s ease,opacity .18s ease}
  .kpi:hover{transform:translateY(-3px);box-shadow:0 18px 32px rgba(0,0,0,.14)}
  .kpi .icon-bg{position:absolute;right:12px;bottom:8px;font-size:48px;opacity:.13;pointer-events:none}
  .kpi .kpi-value{font-size:clamp(26px,5vw,42px);font-weight:900;line-height:1;letter-spacing:-.5px}
  .kpi .kpi-label{margin-top:6px;font-size:clamp(10px,2.4vw,13px);opacity:.9;font-weight:600;line-height:1.3}
  .kpi .kpi-sub{font-size:10px;opacity:.72;margin-top:3px;font-weight:400}
  .kpi-blue{background:linear-gradient(135deg,#60a5fa,#2563eb)}
  .kpi-amber{background:linear-gradient(135deg,#f59e0b,#d97706)}
  .kpi-red{background:linear-gradient(135deg,#fb7185,#dc2626)}
  .kpi-green{background:linear-gradient(135deg,#34d399,#059669)}
  .kpi-danger{background:linear-gradient(135deg,#f97316,#dc2626)}
  .kpi-safe{background:linear-gradient(135deg,#34d399,#059669)}
  @media(max-width:1199px){.kpi-grid{grid-template-columns:repeat(3,1fr)}}
  @media(max-width:640px){.kpi-grid{grid-template-columns:repeat(2,1fr)}}
  @media(max-width:380px){.kpi-grid{grid-template-columns:1fr}}

  /* ===== BOX & TABLE ===== */
  .box-modern{border-radius:14px;overflow:hidden;box-shadow:0 6px 18px rgba(0,0,0,.05)}
  .box-modern>.box-header{background:#fff;border-bottom:1px solid var(--c-border)}
  .box-modern>.box-header .box-title{font-weight:700}
  .ribbon-title{font-weight:700;margin:0;font-size:16px}
  .table-modern thead th{background:#f7f8fb;border-bottom:1px solid #e5e7eb}
  .table-modern tbody tr:nth-child(even){background:#fbfdff}
  .table-responsive{overflow-x:auto;-webkit-overflow-scrolling:touch;scroll-snap-type:x mandatory}
  .table-responsive table{scroll-snap-align:start}

  /* ===== BADGES & PROGRESS ===== */
  .badge-soft{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700}
  .badge-soft-green{background:#e8fff3;color:#059669}
  .badge-soft-red{background:#ffecef;color:#dc2626}
  .badge-soft-amber{background:#fff7e6;color:#d97706}
  .badge-soft-blue{background:#e8f0ff;color:#2563eb}
  .mini-progress{height:8px;border-radius:999px;background:#eef2ff;overflow:hidden}
  .mini-progress>span{display:block;height:8px;background:#2563eb;transition:width .8s ease}

  /* ===== AKTIVITAS ===== */
  .box-aktivitas-bulan .box-header{display:flex;align-items:center;gap:12px;flex-wrap:nowrap;}
  .box-aktivitas-bulan .box-title{margin:0;flex:1 1 auto;min-width:0;text-align:left;}
  .box-aktivitas-bulan .box-tools{margin-left:auto;flex:0 0 auto;position:static !important;float:none !important;}
  .box-aktivitas-bulan .bulan-chipbar{display:flex;gap:8px;flex-wrap:nowrap;align-items:center;overflow:auto;-webkit-overflow-scrolling:touch;padding-bottom:2px;}
  .box-aktivitas-bulan .bulan-chipbar::-webkit-scrollbar{display:none}
  @media(max-width:576px){
    .box-aktivitas-bulan .box-header{flex-wrap:wrap}
    .box-aktivitas-bulan .box-tools{width:100%}
    .box-aktivitas-bulan .bulan-chipbar{justify-content:flex-start}
  }
  .chip-filter{appearance:none;border:1px solid #dbe4ff;background:#eef2ff;color:#1d4ed8;font-weight:700;font-size:13px;padding:8px 14px;border-radius:999px;cursor:pointer;transition:.25s}
  .chip-filter:hover{background:#e0e7ff;box-shadow:0 8px 18px rgba(59,130,246,.25)}
  .chip-filter.active[data-filter="all"]{background:linear-gradient(180deg,#3b82f6,#2563eb);color:#fff;border-color:#1d4ed8}
  .chip-filter.active[data-filter="Prestasi"]{background:linear-gradient(180deg,#34d399,#059669);color:#fff;border-color:#059669}
  .chip-filter.active[data-filter="Pelanggaran"]{background:linear-gradient(180deg,#fb7185,#ef4444);color:#fff;border-color:#ef4444}
  .list-aktivitas{margin:0;padding:0;list-style:none}
  .list-aktivitas li{display:flex;gap:12px;align-items:flex-start;padding:12px 6px;border-bottom:1px dashed #e5e7eb}
  .act-main{flex:1}
  .act-title{margin:0;font-weight:700}
  .act-meta{color:#6b7280;font-size:12px}
  .score-badge{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:24px;padding:0 8px;border-radius:999px;color:#fff;font-weight:800;font-size:12px}
  .sb-pos{background:#10b981}.sb-neg{background:#ef4444}
  .akt-toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin:8px 0 6px}
  .perpage{display:flex;align-items:center;gap:6px}
  .perpage select{padding:6px 8px;height:30px;border-radius:8px;border:1px solid #e5e7eb;background:#fff}
  .pager-controls{display:flex;gap:6px;flex-wrap:wrap;align-items:center;justify-content:center;padding-top:8px}
  .btn-pager{border:1px solid #dbe4ff;background:#eef2ff;color:#1f2937;border-radius:999px;padding:6px 12px;font-size:12px;cursor:pointer}
  .btn-pager.active{background:#2563eb;color:#fff;border-color:#2563eb}
  .btn-pager[disabled]{opacity:.5;cursor:not-allowed}

  /* ===== CHART (Chart.js) ===== */
  #trendChart{max-height:320px}
  .chart-area{
    position:relative;
    width:100%;
    height:clamp(240px, 42vh, 380px);
  }
  .chart-area canvas{
    width:100% !important;
    height:100% !important;
    display:block;
  }
  @media(max-width:576px){
    #trendChart{max-height:260px}
    .chart-area{height:clamp(220px, 40vh, 320px);}
  }

  /* ===== Highcharts ===== */
  .hc-area{
    width:100%;
    min-height:clamp(260px, 42vh, 400px);
  }
  /* Tampilkan hanya Highcharts (Chart.js tetap ada sebagai fallback) */
  .chart-area{ display:none !important; }

  /* ===== FAB (benar-benar pojok kanan-bawah) ===== */
  .fab{
    position:fixed;
    right:16px;
    bottom:16px;
    z-index:100;
    width:52px;
    display:none;
  }
  @media(max-width:768px){ .fab{display:block} }

  .fab-main{
    width:52px;height:52px;border:0;border-radius:999px;
    background:linear-gradient(180deg,#3b82f6,#2563eb);
    color:#fff;box-shadow:0 12px 26px rgba(37,99,235,.35);
    display:block;
    position:relative; overflow:hidden; transition: transform .2s ease;
  }

  /* Submenu FAB */
  .fab-group{
    position:absolute; right:0; bottom:60px;
    display:flex; flex-direction:column; align-items:flex-end; gap:8px;
    transition:opacity .2s ease, transform .2s ease;
    opacity:0; transform:translateY(8px); pointer-events:none; width:max-content;
  }
  .fab.open .fab-group{opacity:1; transform:translateY(0); pointer-events:auto}

  .fab a{
    display:flex; align-items:center; justify-content:flex-end;
    gap:8px; padding:8px 12px; border-radius:999px; color:#fff; text-decoration:none;
    box-shadow:0 10px 20px rgba(0,0,0,.18); white-space:nowrap; text-align:right;
  }
  .fab .pel{background:linear-gradient(135deg,#fb7185,#dc2626)}
  .fab .pres{background:linear-gradient(135deg,#34d399,#059669)}
  .fab .absen{background:linear-gradient(135deg,#60a5fa,#2563eb)}

  .fab[data-dir="left"] .fab-group{
    bottom:0; right:60px; flex-direction:row-reverse; transform:translateX(8px);
  }
  .fab.open[data-dir="left"] .fab-group{transform:translateX(0)}

  /* VARIAN A — SHIMMER / SHINE */
  @media (prefers-reduced-motion: no-preference) {
    .fab-main::after{
      content: ""; position: absolute; top: -40%; bottom: -40%; left: -120%;
      width: 55%; transform: rotate(25deg);
      background: linear-gradient(120deg, transparent 0%, rgba(255,255,255,.65) 50%, transparent 100%);
      filter: blur(1px); pointer-events: none; z-index: 1;
      animation: fab-shine 2.8s ease-in-out infinite;
    }
  }
  @keyframes fab-shine{ 0%{left:-120%} 60%{left:140%} 100%{left:140%} }

  /* VARIAN B — PULSE GLOW */
  @media (prefers-reduced-motion: no-preference) {
    .fab-main.attention{ animation: fab-pulse 2.4s ease-out infinite; }
  }
  @keyframes fab-pulse{
    0%{ box-shadow: 0 0 0 0 rgba(37,99,235,.55), 0 10px 24px rgba(37,99,235,.35) }
    70%{ box-shadow: 0 0 0 14px rgba(37,99,235,0), 0 10px 24px rgba(37,99,235,.35) }
    100%{ box-shadow: 0 0 0 0 rgba(37,99,235,0), 0 10px 24px rgba(37,99,235,.35) }
  }

  .fab-main i{ display:inline-block; transition: transform .25s ease, opacity .25s ease; }
  @media (prefers-reduced-motion: no-preference) {
    .fab-main i{ animation: plus-breathe 1.8s ease-in-out infinite; }
  }
  @keyframes plus-breathe{ 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.15);opacity:.92} }
  .fab.open .fab-main i{ animation:none; transform:rotate(45deg) scale(1); }
  .fab-main:hover{ transform: translateY(-1px); }

  /* ===== To Top (kiri-bawah) ===== */
  .to-top{
    position:fixed;left:16px;bottom:16px;width:42px;height:42px;border-radius:999px;border:0;background:#0f172a;color:#fff;
    box-shadow:0 10px 24px rgba(15,23,42,.35);display:none;place-items:center;z-index:90;
  }
  .to-top.show{display:grid}

  /* === Polish: sembunyikan ikon/foto di samping greeting & besarkan teks === */
  .welcome-icon{ display:none !important; }
  .welcome-text{ font-size: clamp(20px, 4vw, 30px); }

  /* === STATUS ONLINE — Loader baru (wave bars) === */
  .table-responsive{ position: relative; }
  .table-skeleton{
    display:none; position:absolute; inset:0; pointer-events:auto;
    backdrop-filter: blur(2px);
    background:rgba(255,255,255,.86);
    border-radius: 8px;
    z-index:3;
  }
  .table-skeleton.show{ display:block; }
  .table-skeleton .loader-wrap{ position:absolute; inset:0; display:grid; place-items:center; }
  .table-skeleton .bars{ display:flex; gap:6px; align-items:flex-end; height:42px; margin-bottom:10px; }
  .table-skeleton .bars span{
    width:6px; height:12px; background:linear-gradient(180deg,#60a5fa,#2563eb);
    border-radius:6px; animation: wave 1.05s ease-in-out infinite;
  }
  .table-skeleton .bars span:nth-child(2){ animation-delay:.1s }
  .table-skeleton .bars span:nth-child(3){ animation-delay:.2s }
  .table-skeleton .bars span:nth-child(4){ animation-delay:.3s }
  .table-skeleton .bars span:nth-child(5){ animation-delay:.4s }
  @keyframes wave{ 0%,100%{transform:scaleY(.6);opacity:.6} 50%{transform:scaleY(1.9);opacity:1} }
  .table-skeleton .loader-hint{ font-size:12px; color:#334155; text-align:center; }

  /* === Typewriter subtitle & cursor === */
  .welcome-sub{ min-height:1.4em; }
  .welcome-sub .cursor{
    display:inline-block; width:2px; height:1.05em; vertical-align:-0.15em;
    background:#2563eb; margin-left:2px; animation: blink 1s steps(2,end) infinite;
  }
  @keyframes blink{ 0%,49%{opacity:1} 50%,100%{opacity:0} }

.badge-role{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #fdba74;background:#fff7ed;color:#9a3412;font-weight:700;font-size:12px;vertical-align:middle}
.badge-sekretaris{border-color:#fdba74;background:#fff7ed;color:#9a3412}
.toggle-sekretaris input::after{content:"";position:absolute;top:3px;left:3px;width:16px;height:16px;background:#fff;border-radius:50%;transition:.2s;box-shadow:0 1px 2px rgba(0,0,0,.2)}
.toggle-sekretaris input:checked{background:#2563eb}
.toggle-sekretaris input:checked::after{transform:translateX(16px)}

/* ===== ZONE HEADERS (section dividers) ===== */
.zone-header{display:flex;align-items:center;gap:10px;margin:22px 0 12px}
.zone-header .zl{flex:1;height:1px;background:linear-gradient(90deg,var(--c-border),transparent)}
.zone-header .zl-r{flex:1;height:1px;background:linear-gradient(270deg,var(--c-border),transparent)}
.zone-header .zone-label{font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--c-muted);white-space:nowrap;padding:0 6px;display:flex;align-items:center;gap:5px}

/* ===== EW ALERT BANNER (hero) ===== */
.ew-banner{
  background:linear-gradient(90deg,#7c2d12 0%,#b91c1c 100%);
  color:#fff;border-radius:10px;padding:10px 14px;
  display:flex;align-items:center;gap:10px;margin-top:12px;
  animation:ew-pulse 2.4s ease-in-out infinite;
}
@keyframes ew-pulse{0%,100%{box-shadow:0 0 0 0 rgba(185,28,28,.4)}50%{box-shadow:0 0 0 6px rgba(185,28,28,0)}}
.ew-banner a{color:#fcd34d;font-weight:700;white-space:nowrap;margin-left:auto;text-decoration:none}
.ew-banner a:hover{color:#fef9c3}

/* ===== EW TABLE SEVERITY ===== */
.ew-critical td{background:#fff5f5!important;border-left:3px solid #dc2626!important}
.ew-critical td:first-child{font-weight:700}
.ew-warn td{background:#fffbeb!important;border-left:3px solid #f59e0b!important}

/* ===== BOX MODERN unified ===== */
.box-modern{border-radius:14px;overflow:hidden;box-shadow:0 4px 18px rgba(0,0,0,.055)!important;border:1px solid #f0f4f8!important}
.box-modern>.box-header{background:#fff;border-bottom:1px solid #f0f4f8;padding:12px 16px}
.box-modern>.box-header .box-title{font-weight:700;font-size:14px;display:flex;align-items:center;gap:6px}
.box-modern.box-warning{border-top:none!important}
.box-modern.box-danger{border-top:none!important}
.box-modern.box-success{border-top:none!important}
.box-modern.box-info{border-top:none!important}

/* ===== EW PANEL HEADER variants ===== */
.ew-header-danger{background:linear-gradient(90deg,#7c2d12,#b91c1c)!important;color:#fff!important;border-bottom:none!important}
.ew-header-danger .box-title{color:#fff!important}
.ew-header-safe{background:linear-gradient(90deg,#065f46,#059669)!important;color:#fff!important;border-bottom:none!important}
.ew-header-safe .box-title{color:#fff!important}

/* ===== QUICK ACTIONS polish ===== */
.qa .ico{width:42px;height:42px;border-radius:12px;flex-shrink:0}
</style>

<div class="content-wrapper">
  <section class="content-header">
    <h1>Dashboard Pemantauan Poin Siswa
      <small>Monitoring Prestasi dan Pelanggaran Secara Digital</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Dashboard</li>
    </ol>
  </section>

  <section class="content">

    <!-- HERO -->
    <div class="hero-card">
      <div class="welcome-wrap">
        <div class="welcome-icon"><i class="fa fa-hand-peace-o fa-lg"></i></div>
        <div>
          <!-- Tambah class .welcome-name agar underline pas ke ujung nama -->
          <h2 class="welcome-text">Selamat datang, <span class="welcome-name" style="font-weight:900"><?php echo htmlspecialchars($nama_login); ?></span></h2>
          <!-- Subtitle: animasi typewriter -->
          <p id="welcome-sub" class="text-muted-600 welcome-sub" data-text="Pantau ringkasan, ranking, dan aktivitas terbaru dalam satu layar."></p>
        </div>
      </div>
      <?php if ($EW_COUNT > 0): ?>
      <div class="ew-banner" role="alert">
        <i class="fa fa-exclamation-circle fa-lg"></i>
        <span><strong><?php echo $EW_COUNT; ?> siswa</strong> mendekati ambang naik level peringatan — segera tinjau panel <em>Early Warning</em> di bawah.</span>
        <a href="#zone-sp"><i class="fa fa-arrow-down"></i> Lihat</a>
      </div>
      <?php endif; ?>
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar" role="region" aria-label="Filter Tahun Ajaran">
      <div class="filter-bar-inner">
        <span class="filter-pill"><i class="fa fa-filter"></i> Filter</span>

        <form id="filterTA" class="filter-form" method="get" action="">
          <select id="taSelect" name="ta" class="form-control" aria-label="Pilih Tahun Ajaran">
            <?php
              $ta_opt = db_query($koneksi,"SELECT * FROM ta ORDER BY ta_status DESC, ta_nama DESC");
              if($ta_opt){
                while($t=mysqli_fetch_assoc($ta_opt)){
                  $sel = ((int)$t['ta_id']===$TA_SELECTED)?'selected':'';
                  $lab = $t['ta_nama'].($t['ta_status']=='1'?' (Aktif)':'');
                  echo '<option value="'.(int)$t['ta_id'].'" '.$sel.'>'.htmlspecialchars($lab).'</option>';
                }
                mysqli_free_result($ta_opt);
              }
            ?>
          </select>
          <div class="filter-actions">
            <button class="btn btn-primary btn-sm" type="submit"><i class="fa fa-refresh"></i> Terapkan</button>
            <a href="index.php" class="btn btn-default btn-sm"><i class="fa fa-undo"></i> Reset</a>
          </div>
        </form>

        <div class="filter-ta">
          <span class="badge-soft badge-soft-green"><i class="fa fa-calendar"></i> TA: <?php echo htmlspecialchars($TA_LABEL); ?></span>
        </div>
      </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="quick-actions">
      <a class="qa" href="input_pelanggaran_tambah.php" aria-label="Input Pelanggaran">
        <span class="ico ico-red"><i class="fa fa-gavel"></i></span>
        <div><div style="font-weight:800">Input Pelanggaran</div><small class="text-muted-600">Catat kejadian baru</small></div>
      </a>
      <a class="qa" href="input_prestasi_tambah.php" aria-label="Input Prestasi">
        <span class="ico ico-green"><i class="fa fa-trophy"></i></span>
        <div><div style="font-weight:800">Input Prestasi</div><small class="text-muted-600">Tambahkan capaian</small></div>
      </a>
      <a class="qa" href="siswa.php" aria-label="Profil Siswa">
        <span class="ico ico-blue"><i class="fa fa-users"></i></span>
        <div><div style="font-weight:800">Profil Siswa</div><small class="text-muted-600">Progress Poin, Riwayat, Penerbitan SP</small></div>
      </a>
      <a class="qa" href="laporan.php" aria-label="Cetak Laporan">
        <span class="ico ico-amber"><i class="fa fa-print"></i></span>
        <div><div style="font-weight:800">Cetak Laporan</div><small class="text-muted-600">Poin Per Siswa / Kelas</small></div>
      </a>
    </div>

    <!-- KPI -->
    <div class="kpi-grid">
      <div class="kpi kpi-blue">
        <div class="kpi-value" data-count="<?php echo $SISWA; ?>">0</div>
        <div class="kpi-label">Total Siswa</div>
        <div class="kpi-sub"><?php echo $KELAS; ?> kelas aktif</div>
        <i class="fa fa-graduation-cap icon-bg"></i>
      </div>
      <div class="kpi kpi-amber">
        <div class="kpi-value" data-count="<?php echo $ADMIN; ?>">0</div>
        <div class="kpi-label">PTK</div>
        <div class="kpi-sub"><?php echo $ROMBEL; ?> jurusan/tingkat</div>
        <i class="fa fa-chalkboard-teacher icon-bg"></i>
      </div>
      <div class="kpi kpi-red">
        <div class="kpi-value" data-count="<?php echo $TPel; ?>">0</div>
        <div class="kpi-label">Transaksi Pelanggaran</div>
        <div class="kpi-sub">TA: <?php echo htmlspecialchars($ta_row['ta_nama'] ?? '-'); ?></div>
        <i class="fa fa-gavel icon-bg"></i>
      </div>
      <div class="kpi kpi-green">
        <div class="kpi-value" data-count="<?php echo $TPres; ?>">0</div>
        <div class="kpi-label">Transaksi Prestasi</div>
        <div class="kpi-sub">TA: <?php echo htmlspecialchars($ta_row['ta_nama'] ?? '-'); ?></div>
        <i class="fa fa-trophy icon-bg"></i>
      </div>
      <?php $kpi5Class = ($SP_AKTIF > 0 || $EW_COUNT > 0) ? 'kpi-danger' : 'kpi-safe'; ?>
      <div class="kpi <?php echo $kpi5Class; ?>">
        <div class="kpi-value" data-count="<?php echo $SP_AKTIF; ?>">0</div>
        <div class="kpi-label">Siswa Aktif SP</div>
        <div class="kpi-sub">
          <?php if ($EW_COUNT > 0): ?>
            <i class="fa fa-exclamation-triangle"></i> <?php echo $EW_COUNT; ?> mendekati ambang
          <?php else: ?>
            Semua dalam batas aman
          <?php endif; ?>
        </div>
        <i class="fa fa-exclamation-circle icon-bg"></i>
      </div>
    </div>

    <!-- ZONA: PEMANTAUAN AKTIF -->
    <div id="zone-sp" class="zone-header">
      <div class="zl"></div>
      <span class="zone-label"><i class="fa fa-shield"></i> Pemantauan Aktif</span>
      <div class="zl-r"></div>
    </div>

    <!-- SP DISTRIBUTION + EARLY WARNING -->
    <div class="row">
      <div class="col-lg-5">
        <div class="box box-modern">
          <div class="box-header">
            <h3 class="box-title"><i class="fa fa-pie-chart"></i> Distribusi Tahap SP Siswa
              <small class="text-muted-600" style="font-weight:400;margin-left:4px">(TA: <?php echo htmlspecialchars($ta_row['ta_nama'] ?? ''); ?>)</small>
            </h3>
          </div>
          <div class="box-body">
            <?php $totalSiswaKelas = array_sum($spDist); ?>
            <?php if ($totalSiswaKelas === 0): ?>
              <p class="text-center text-muted" style="padding:20px">Belum ada data siswa pada TA ini.</p>
            <?php else: ?>
              <div id="spDonut" style="min-height:260px"></div>
              <div style="display:flex;flex-wrap:wrap;gap:6px;justify-content:center;margin-top:8px">
                <span class="badge-soft" style="background:#e8fff3;color:#059669">Aman: <?php echo $spDist['aman']; ?></span>
                <span class="badge-soft" style="background:#e0f2fe;color:#0369a1">Pembinaan: <?php echo $spDist['pembinaan']; ?></span>
                <span class="badge-soft" style="background:#fef9c3;color:#854d0e">SP1: <?php echo $spDist['sp1']; ?></span>
                <span class="badge-soft" style="background:#ffedd5;color:#9a3412">SP2: <?php echo $spDist['sp2']; ?></span>
                <span class="badge-soft" style="background:#fee2e2;color:#991b1b">SP3: <?php echo $spDist['sp3']; ?></span>
                <span class="badge-soft" style="background:#fce7f3;color:#9d174d">SP4: <?php echo $spDist['sp4']; ?></span>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="box box-modern">
          <div class="box-header <?php echo $EW_COUNT > 0 ? 'ew-header-danger' : 'ew-header-safe'; ?>">
            <h3 class="box-title">
              <i class="fa fa-<?php echo $EW_COUNT > 0 ? 'exclamation-triangle' : 'check-circle'; ?>"></i>
              Early Warning &mdash; Mendekati Ambang SP
              <?php if ($EW_COUNT > 0): ?>
                <span style="margin-left:auto;background:rgba(255,255,255,.2);border-radius:999px;padding:2px 10px;font-size:12px;font-weight:900"><?php echo $EW_COUNT; ?> siswa</span>
              <?php endif; ?>
            </h3>
          </div>
          <div class="box-body" style="padding:8px 12px">
            <?php if (empty($earlyWarning)): ?>
              <div class="text-center" style="padding:20px;color:#059669">
                <i class="fa fa-check-circle fa-2x"></i><br>
                <span style="margin-top:6px;display:block">Tidak ada siswa mendekati ambang SP saat ini.</span>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-modern table-bordered table-hover" style="font-size:13px;margin-bottom:0">
                  <thead>
                    <tr>
                      <th>Nama Siswa</th>
                      <th>NIS</th>
                      <th style="width:70px;text-align:center">Saldo</th>
                      <th style="width:60px;text-align:center">Mendekati</th>
                      <th style="width:90px;text-align:center">Jarak</th>
                      <th style="width:40px"></th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php
                    $ewBadge = ['SP1'=>'badge-soft-amber','SP2'=>'badge-soft-red','SP3'=>'badge-soft-red','SP4'=>'badge-soft-red'];
                    foreach ($earlyWarning as $ew):
                      $ewRow = $ew['jarak'] <= 2 ? 'ew-critical' : 'ew-warn';
                  ?>
                    <tr class="<?php echo $ewRow; ?>">
                      <td><?php echo htmlspecialchars($ew['siswa_nama']); ?></td>
                      <td><?php echo htmlspecialchars($ew['siswa_nis']); ?></td>
                      <td style="text-align:center"><span class="badge-soft badge-soft-red"><?php echo $ew['saldo']; ?></span></td>
                      <td style="text-align:center"><span class="badge-soft <?php echo $ewBadge[$ew['mendekati']] ?? 'badge-soft-red'; ?>"><?php echo htmlspecialchars($ew['mendekati']); ?></span></td>
                      <td style="text-align:center"><strong><?php echo $ew['jarak']; ?></strong> poin</td>
                      <td style="text-align:center">
                        <a href="siswa_riwayat.php?id=<?php echo $ew['siswa_id']; ?>" class="btn btn-xs btn-info" title="Lihat Riwayat"><i class="fa fa-eye"></i></a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ZONA: ANALITIK -->
    <div class="zone-header">
      <div class="zl"></div>
      <span class="zone-label"><i class="fa fa-chart-area"></i> Analitik &amp; Tren</span>
      <div class="zl-r"></div>
    </div>

    <!-- GRAFIK -->
    <div class="box box-modern">
      <div class="box-header">
        <h3 class="box-title"><i class="fa fa-chart-line"></i> Tren Bulanan (TA: <?php echo htmlspecialchars($ta_row['ta_nama'] ?? ''); ?>)</h3>
      </div>
      <div class="box-body">
        <!-- Canvas Chart.js (fallback) -->
        <div class="chart-area">
          <canvas id="trendChart" aria-label="Tren Bulanan Pelanggaran & Prestasi" role="img"></canvas>
        </div>
        <!-- Highcharts container -->
        <div id="trendHC" class="hc-area" aria-label="Tren Bulanan Pelanggaran & Prestasi" role="img"></div>

        <?php if (empty($months)) { echo '<p class="text-center" style="margin:10px 0;color:#64748b">Belum ada data untuk TA ini.</p>'; } ?>
      </div>
    </div>

    <!-- ZONA: RIWAYAT & AKTIVITAS -->
    <div class="zone-header">
      <div class="zl"></div>
      <span class="zone-label"><i class="fa fa-history"></i> Riwayat &amp; Aktivitas</span>
      <div class="zl-r"></div>
    </div>

    <!-- AKTIVITAS TERBARU -->
    <div class="box box-modern box-aktivitas-bulan" style="border-radius:12px;">
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
            <button class="chip-filter" data-filter="Prestasi" type="button" aria-pressed="false">Prestasi</button>
            <button class="chip-filter" data-filter="Pelanggaran" type="button" aria-pressed="false">Pelanggaran</button>
          </div>
        </div>
      </div>
      <div class="box-body">
        <?php if(empty($aktivitas_all)){ ?>
          <div class="text-center text-muted" style="padding:18px;"><i class="fa fa-info-circle"></i> Belum ada data.</div>
        <?php } else { ?>
          <div class="akt-toolbar">
            <div class="page-info" id="aktInfo" aria-live="polite">Menampilkan 0–0 dari 0 entri</div>
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
            <?php
            foreach($aktivitas_all as $row){
              $isPrestasi = ($row['tipe']==='Prestasi');
              $cls = $isPrestasi ? 'sb-pos' : 'sb-neg';
              $badge = ($isPrestasi?'+':'').(int)$row['poin'];
            ?>
              <li data-tipe="<?php echo htmlspecialchars($row['tipe']); ?>">
                <span class="score-badge <?php echo $cls; ?>"><?php echo $badge; ?></span>
                <div class="act-main">
                  <p class="act-title">
                    <strong><?php echo htmlspecialchars($row['siswa']); ?></strong>
                    <span class="label <?php echo $isPrestasi ? 'label-success' : 'label-danger'; ?>" style="margin-left:6px;">
                      <?php echo htmlspecialchars($row['tipe']); ?>
                    </span>
                  </p>
                  <div class="act-meta">
                    <?php echo htmlspecialchars($row['nama']); ?> —
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

    <!-- TOP LISTS -->
    <div class="row">
      <section class="col-lg-6">
        <div class="box box-modern">
          <div class="box-header" style="border-left:4px solid #dc2626">
            <h3 class="box-title"><i class="fa fa-fire" style="color:#dc2626"></i> 5 Besar Pelanggaran Terbanyak
              <small class="text-muted-600" style="font-weight:400;margin-left:4px">(TA terpilih)</small>
            </h3>
          </div>
          <div class="box-body">
            <div class="table-responsive">
              <table class="table table-modern table-bordered table-striped table-hover">
                <thead>
                  <tr><th style="width:60px">NO</th><th>NAMA</th><th style="width:140px">NIS</th><th style="width:160px">POIN PELANGGARAN</th></tr>
                </thead>
                <tbody>
                <?php
                  $no=1;
                  if($topPel && mysqli_num_rows($topPel)>0){
                    while($d=mysqli_fetch_assoc($topPel)){ ?>
                    <tr>
                      <td><span class="badge-soft badge-soft-amber"><?php echo $no++; ?></span></td>
                      <td><?php echo htmlspecialchars($d['siswa_nama']); ?></td>
                      <td><?php echo htmlspecialchars($d['siswa_nis']); ?></td>
                      <td>
                        <div style="display:flex;align-items:center;gap:10px">
                          <strong><?php echo (int)$d['total_pelanggaran']; ?></strong>
                          <div class="mini-progress" style="flex:1">
                            <?php $width=min(100,max(1,(int)$d['total_pelanggaran']*4)); ?>
                            <span style="width: <?php echo $width; ?>%"></span>
                          </div>
                        </div>
                      </td>
                    </tr>
                <?php }
                    mysqli_free_result($topPel);
                  } else { echo '<tr><td colspan="4" class="text-center text-muted">Belum ada data pada TA terpilih.</td></tr>'; } ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <section class="col-lg-6">
        <div class="box box-modern">
          <div class="box-header" style="border-left:4px solid #059669">
            <h3 class="box-title"><i class="fa fa-star" style="color:#059669"></i> 5 Besar Skor Tertinggi
              <small class="text-muted-600" style="font-weight:400;margin-left:4px">(TA terpilih)</small>
            </h3>
          </div>
          <div class="box-body">
            <div class="table-responsive">
              <table class="table table-modern table-bordered table-striped table-hover">
                <thead>
                  <tr><th style="width:60px">NO</th><th>NAMA</th><th style="width:140px">NIS</th><th style="width:120px">SKOR</th></tr>
                </thead>
                <tbody>
                <?php
                  $no=1; if(count($rankTop)>0){
                    foreach($rankTop as $s){ ?>
                    <tr>
                      <td><span class="badge-soft badge-soft-blue"><?php echo $no++; ?></span></td>
                      <td><?php echo htmlspecialchars($s['siswa_nama']); ?></td>
                      <td><?php echo htmlspecialchars($s['siswa_nis']); ?></td>
                      <td>
                        <div style="display:flex;align-items:center;gap:10px">
                          <strong><?php echo (int)$s['skor']; ?></strong>
                          <div class="mini-progress" style="flex:1">
                            <?php $width=min(100,max(0,(int)$s['skor']*4)); ?>
                            <span style="width: <?php echo $width; ?>%"></span>
                          </div>
                        </div>
                      </td>
                    </tr>
                <?php } } else { echo '<tr><td colspan="4" class="text-center text-muted">Belum ada data pada TA terpilih.</td></tr>'; } ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- LOG AKTIVITAS -->
    <div class="row">
      <div class="col-lg-12">
        <div class="box box-modern">
          <div class="box-header" style="border-left:4px solid #2563eb">
            <h3 class="box-title"><i class="fa fa-history" style="color:#2563eb"></i> Log Aktivitas Pengguna (Terbaru)</h3>
          </div>
          <div class="box-body">
            <div class="table-responsive">
              <table id="logTable" class="table table-modern table-bordered table-striped table-hover">
                <thead>
                  <tr><th>No</th><th>Waktu</th><th>Nama Pengguna</th><th>Aktivitas</th></tr>
                </thead>
                <tbody>
                <?php
                  $log = db_query($koneksi,"SELECT * FROM log_aktivitas ORDER BY waktu DESC LIMIT 50");
                  $no=1;
                  if($log){
                    while($l=mysqli_fetch_assoc($log)){ ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td><?php echo date("d-m-Y H:i:s",strtotime($l['waktu'])); ?></td>
                      <td><?php echo htmlspecialchars($l['nama_guru']); ?></td>
                      <td><?php echo htmlspecialchars($l['aktivitas']); ?></td>
                    </tr>
                <?php }
                    mysqli_free_result($log);
                  } ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- STATUS ONLINE -->
    <div class="row">
      <div class="col-lg-12">
        <div class="box box-modern">
          <div class="box-header" style="border-left:4px solid #0ea5e9">
            <h3 class="box-title"><i class="fa fa-wifi" style="color:#0ea5e9"></i> Login Terakhir &amp; Status Online</h3>
          </div>
          <div class="status-summary" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:8px 0 12px;">
  <span id="sumAdmin" class="badge-soft badge-soft-blue">PTK &amp; Admin: 0/0 Online</span>
  <span id="sumSiswa" class="badge-soft badge-soft-indigo">Siswa: 0/0 Online</span>
  <small id="sumUpdated" class="text-muted-600" aria-live="polite"></small>
  <!-- Toggle: default OFF -->
  <label class="toggle-sekretaris" style="margin-left:auto;display:inline-flex;align-items:center;gap:6px;font-weight:600;">
    <input type="checkbox" id="toggleSekretaris" style="appearance:none;width:36px;height:20px;background:#e5e7eb;border-radius:999px;position:relative;outline:none;cursor:pointer;">
    <span style="user-select:none;">Tampilkan Sekretaris (Siswa)</span>
  </label>
</div>
    
          <div class="box-body">
            <div class="row">
              <div class="col-md-6">
                <h4 style="margin-top:0"><i class="fa fa-user-shield"></i> PTK &amp; Admin</h4>
                <div class="table-responsive">
                  <table id="adminTable" class="table table-modern table-bordered table-striped table-hover"><thead><tr><th>Nama</th><th>Terakhir Login</th><th>Status</th></tr></thead><tbody id="admin-table-body"></tbody>
                  </table>
                  <!-- Loader baru (wave bars) -->
                  <div class="table-skeleton" id="adminSkeleton" aria-hidden="true">
                    <div class="loader-wrap">
                      <div>
                        <div class="bars"><span></span><span></span><span></span><span></span><span></span></div>
                        <div class="loader-hint">Memuat PTK & Admin…</div>
                      </div>
                    </div>
                  </div>
                </div>
                <div id="loading-admin" class="text-center" style="padding:20px;">
                  <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
                  <p>Memuat PTK & Admin...</p>
                </div>
              </div>
              <div class="col-md-6">
                <h4 style="margin-top:0"><i class="fa fa-graduation-cap"></i> Siswa</h4>
                <div class="table-responsive">
                  <table id="siswaTable" class="table table-modern table-bordered table-striped table-hover"><thead><tr><th>Nama</th><th>Terakhir Login</th><th>Status</th></tr></thead><tbody id="siswa-table-body"></tbody>
                  </table>
                  <!-- Loader baru (wave bars) -->
                  <div class="table-skeleton" id="siswaSkeleton" aria-hidden="true">
                    <div class="loader-wrap">
                      <div>
                        <div class="bars"><span></span><span></span><span></span><span></span><span></span></div>
                        <div class="loader-hint">Memuat data Siswa…</div>
                      </div>
                    </div>
                  </div>
                </div>
                <div id="loading-siswa" class="text-center" style="padding:20px;">
                  <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
                  <p>Memuat data siswa...</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </section>
</div>

<!-- FAB (mobile) — Pojok kanan-bawah, varian arah via data-dir -->
<div class="fab" id="fab" data-dir="up">
  <div class="fab-group" role="menu" aria-label="Menu aksi cepat">
    <a class="pres" href="input_prestasi_tambah.php" role="menuitem"><i class="fa fa-trophy"></i> Prestasi</a>
    <a class="pel"  href="input_pelanggaran_tambah.php" role="menuitem"><i class="fa fa-gavel"></i> Pelanggaran</a>
    <a class="absen" href="absensi_mapel.php" role="menuitem"><i class="fa fa-calendar-check-o"></i> Absensi Mapel Anda</a>
  </div>
  <button class="fab-main" type="button" aria-label="Menu cepat" aria-haspopup="true" aria-expanded="false"><i class="fa fa-plus"></i></button>
</div>

<!-- Tombol Kembali ke atas (kiri-bawah) -->
<button id="toTop" class="to-top" aria-label="Kembali ke atas"><i class="fa fa-arrow-up"></i></button>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<!-- Plugin untuk label nilai -->
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>

<!-- Highcharts (Freemium) -->
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/accessibility.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>

<script>
  // ====== SET TOP OFFSET DINAMIS ======
  document.addEventListener('DOMContentLoaded', function(){
    var hdr = document.querySelector('.main-header');
    if(hdr){
      var h = hdr.offsetHeight || 58;
      document.documentElement.style.setProperty('--top-nav', h + 'px');
    }
  });

  // ====== ANIMASI ANGKA ======
  function animateCounter(el, end, duration){
    var start=0, st=null; end=Number(end)||0;
    function step(ts){ if(!st) st=ts; var p=Math.min((ts-st)/duration,1);
      el.textContent = Math.floor(start+(end-start)*p);
      if(p<1) requestAnimationFrame(step);
    }
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches){ el.textContent=end; return; }
    requestAnimationFrame(step);
  }
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.kpi .kpi-value').forEach(function(el){
      animateCounter(el, el.getAttribute('data-count'), 900);
    });

    // Auto submit filter TA
    var selTA = document.getElementById('taSelect'), t;
    if(selTA){
      selTA.addEventListener('change', function(){
        clearTimeout(t); t = setTimeout(function(){ document.getElementById('filterTA').submit(); }, 120);
      });
    }
  });
</script>

<script>
  // ====== GRAFIK TREN (Chart.js) — fallback ======
  (function(){
    var canvas = document.getElementById('trendChart');
    if(!canvas) return;

    var labels   = <?php echo json_encode($labels); ?>;
    var pelData  = <?php echo json_encode($seriePel); ?>;
    var presData = <?php echo json_encode($seriePres); ?>;

    if(labels.length===0){ return; }

    var maxVal = Math.max.apply(null, (pelData.concat(presData)).map(function(v){ return Number(v)||0; }));
    var step   = maxVal <= 10 ? 1 : (maxVal <= 50 ? 5 : (maxVal <= 100 ? 10 : Math.ceil(maxVal/10)));
    var yMax   = Math.max(5, maxVal + Math.ceil(maxVal * 0.15) + 1);

    if (window.ChartDataLabels) { Chart.register(ChartDataLabels); }

    new Chart(canvas, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [
          { label:'Pelanggaran', data: pelData, borderColor:'#dc2626', backgroundColor:'rgba(220,38,38,.12)', borderWidth:2.5, tension:.35, fill:true, pointRadius:3.5, pointHoverRadius:6, pointBackgroundColor:'#dc2626', pointBorderColor:'#fff', pointBorderWidth:1 },
          { label:'Prestasi', data: presData, borderColor:'#059669', backgroundColor:'rgba(5,150,105,.12)', borderWidth:2.5, tension:.35, fill:true, pointRadius:3.5, pointHoverRadius:6, pointBackgroundColor:'#059669', pointBorderColor:'#fff', pointBorderWidth:1 }
        ]
      },
      options: {
        responsive:true, maintainAspectRatio:false,
        layout:{ padding:{ top:16, right:12, left:8, bottom:6 } },
        interaction:{ mode:'index', intersect:false },
        animation:{ duration:900, easing:'easeOutQuart' },
        plugins:{
          title:{ display:true, text:'Tren Bulanan Pelanggaran & Prestasi', font:{ weight:'700', size:14 } },
          legend:{ position:'top', labels:{ usePointStyle:true, pointStyle:'circle' } },
          tooltip:{ callbacks:{ title:function(items){ return items[0].label; }, label:function(ctx){ return ctx.dataset.label+': '+ctx.parsed.y; } } },
          datalabels:{ align:'top', anchor:'end', clamp:true, offset:4, formatter:function(value){ return (value>0)? value : ''; }, font:{ weight:'700', size:10 }, color:function(context){ return context.dataset.borderColor || '#111'; } }
        },
        scales:{
          x:{ title:{ display:true, text:'Bulan (Tahun Ajaran)' }, ticks:{ maxRotation:0, autoSkip:true, autoSkipPadding:10 }, grid:{ display:true, drawBorder:false, color:'rgba(15,23,42,0.06)' } },
          y:{ title:{ display:true, text:'Jumlah Transaksi' }, beginAtZero:true, suggestedMax:yMax, ticks:{ precision:0, stepSize:step, display:true }, grid:{ display:true, drawBorder:false, color:'rgba(15,23,42,0.08)' } }
        },
        elements:{ point:{ hitRadius:10, hoverBorderWidth:1.5 } }
      }
    });
  })();
</script>

<script>
  // ====== GRAFIK TREN (Highcharts) — utama ======
  (function(){
    var labels   = <?php echo json_encode($labels); ?>;
    var pelData  = <?php echo json_encode($seriePel); ?>;
    var presData = <?php echo json_encode($seriePres); ?>;
    if(!labels || !labels.length){ return; }

    var maxPel  = Math.max.apply(null, pelData.concat(0));
    var maxPres = Math.max.apply(null, presData.concat(0));
    var maxVal  = Math.max(maxPel, maxPres, 0);
    var step    = (maxVal <= 10) ? 1 : (maxVal <= 50 ? 5 : (maxVal <= 100 ? 10 : Math.ceil(maxVal/10)));
    var yMax    = Math.max(5, maxVal + Math.ceil(maxVal * 0.15) + 1);

    Highcharts.chart('trendHC', {
      chart: {
        type: 'areaspline', zoomType: 'x', animation: { duration: 900 },
        backgroundColor: '#fafbff',
        style: { fontFamily: 'inherit' },
        spacing: [20, 20, 10, 10]
      },
      title:    { text: null },
      subtitle: { text: null },
      xAxis: {
        categories: labels,
        tickLength: 0,
        gridLineWidth: 1,
        gridLineColor: 'rgba(15,23,42,0.05)',
        lineColor: '#e5e7eb',
        labels: { style: { color: '#64748b', fontSize: '12px' } }
      },
      yAxis: {
        title: { text: 'Transaksi', style: { color: '#94a3b8', fontSize: '11px' } },
        allowDecimals: false, tickInterval: step, max: yMax,
        gridLineColor: 'rgba(15,23,42,0.07)',
        labels: { style: { color: '#64748b', fontSize: '11px' } }
      },
      legend: {
        layout: 'horizontal', align: 'right', verticalAlign: 'top',
        itemStyle: { fontWeight: '700', fontSize: '12px', color: '#374151' },
        symbolRadius: 4
      },
      tooltip: {
        shared: true, useHTML: true,
        backgroundColor: '#fff',
        borderColor: '#e5e7eb', borderRadius: 10,
        shadow: { color: 'rgba(0,0,0,.08)', offsetX: 0, offsetY: 4, opacity: 1, width: 16 },
        headerFormat: '<div style="font-weight:800;margin-bottom:4px;color:#111">{point.key}</div>',
        pointFormat: '<div style="display:flex;align-items:center;gap:6px;margin:2px 0"><span style="width:10px;height:10px;border-radius:50%;background:{series.color};display:inline-block"></span><span style="color:#374151">{series.name}</span><strong style="margin-left:auto">{point.y}</strong></div>'
      },
      plotOptions: {
        areaspline: {
          lineWidth: 2.5,
          marker: { enabled: true, radius: 4, lineWidth: 2, lineColor: '#fff' },
          dataLabels: {
            enabled: true,
            style: { fontWeight: '700', textOutline: 'none', fontSize: '11px' },
            formatter: function(){ return (this.y > 0) ? this.y : ''; }
          },
          states: { hover: { lineWidthPlus: 0 } },
          fillOpacity: 0.12
        }
      },
      colors: ['#dc2626', '#059669'],
      series: [
        { name: 'Pelanggaran', data: pelData,  fillColor: { linearGradient: {x1:0,y1:0,x2:0,y2:1}, stops: [[0,'rgba(220,38,38,.18)'],[1,'rgba(220,38,38,0)']] } },
        { name: 'Prestasi',    data: presData, fillColor: { linearGradient: {x1:0,y1:0,x2:0,y2:1}, stops: [[0,'rgba(5,150,105,.18)'],[1,'rgba(5,150,105,0)']] } }
      ],
      exporting: { enabled: false },
      credits:   { enabled: false },
      accessibility: { enabled: true, description: 'Garis merah menunjukkan jumlah pelanggaran per bulan, hijau untuk prestasi.' }
    });
  })();
</script>

<script>
  // ====== GRAFIK DISTRIBUSI SP (Highcharts Donut) ======
  (function(){
    var spData = <?php echo json_encode([
      ['name'=>'Aman',      'y'=>$spDist['aman'],      'color'=>'#059669'],
      ['name'=>'Pembinaan', 'y'=>$spDist['pembinaan'], 'color'=>'#0ea5e9'],
      ['name'=>'SP1',       'y'=>$spDist['sp1'],       'color'=>'#ca8a04'],
      ['name'=>'SP2',       'y'=>$spDist['sp2'],       'color'=>'#ea580c'],
      ['name'=>'SP3',       'y'=>$spDist['sp3'],       'color'=>'#dc2626'],
      ['name'=>'SP4',       'y'=>$spDist['sp4'],       'color'=>'#9d174d'],
    ]); ?>;
    var hasData = spData.some(function(d){ return d.y > 0; });
    if (!hasData || !document.getElementById('spDonut')) return;

    var totalSiswa = spData.reduce(function(s,d){ return s+d.y; }, 0);
    var amanCount  = <?php echo (int)$spDist['aman']; ?>;
    var amanPct    = totalSiswa > 0 ? Math.round(amanCount / totalSiswa * 100) : 0;

    var spChart = Highcharts.chart('spDonut', {
      chart: {
        type:'pie', animation:{ duration:700 },
        margin:[8,0,8,0], spacing:[0,0,0,0],
        backgroundColor:'transparent',
        style: { fontFamily:'inherit' },
        events: {
          render: function(){
            var c = this, cx = c.plotLeft + c.plotWidth/2, cy = c.plotTop + c.plotHeight/2;
            if (!c._cl) {
              c._cl = c.renderer.label(
                '<span style="font-size:26px;font-weight:900;color:#111827;line-height:1">'+ amanPct +'%</span><br>'+
                '<span style="font-size:11px;color:#6b7280;font-weight:600">Aman</span>',
                0, 0, null, null, null, true
              ).attr({ zIndex: 5 }).add();
            }
            var bb = c._cl.getBBox();
            c._cl.attr({ x: cx - bb.width/2, y: cy - bb.height/2 });
          }
        }
      },
      title: { text:null },
      tooltip: {
        useHTML:true, borderRadius:10, borderColor:'#e5e7eb',
        pointFormat:'<span style="color:{point.color}">●</span> <b>{point.name}</b>: {point.y} siswa ({point.percentage:.1f}%)'
      },
      plotOptions: {
        pie:{
          innerSize:'55%', startAngle:-90,
          dataLabels:{
            enabled:true, distance:10,
            format:'<b style="color:{point.color}">{point.name}</b>: {point.y}',
            style:{ fontWeight:'700', textOutline:'none', fontSize:'11px', color:'#374151' }
          },
          showInLegend:false,
          borderWidth: 2,
          borderColor: '#fff'
        }
      },
      series:[{ name:'Siswa', colorByPoint:true, data:spData }],
      credits:    { enabled:false },
      exporting:  { enabled:false },
      accessibility:{ enabled:false }
    });
  })();
</script>

<script>
  // ===== Helper: format tanggal dd/mm/yyyy HH:mm:ss untuk kolom "Terakhir Login" =====
  function formatIDDateTime(input){
    if(!input) return '-';
    var s = String(input).replace(' ', 'T');
    var d = new Date(s);
    if(isNaN(d.getTime())){
      var m = String(input).match(/(\d{2})[\/\-](\d{2})[\/\-](\d{4})[ T](\d{2}):(\d{2})(?::(\d{2}))?/);
      if(m){
        d = new Date(Number(m[3]), Number(m[2])-1, Number(m[1]), Number(m[4]), Number(m[5]), Number(m[6]||0));
      }
    }
    if(isNaN(d.getTime())) return input;
    var pad = function(n){ return (n<10?'0':'')+n; };
    return pad(d.getDate())+'/'+pad(d.getMonth()+1)+'/'+d.getFullYear()+' '+pad(d.getHours())+':'+pad(d.getMinutes())+':'+pad(d.getSeconds());
  }

  
function escHtml(s){
  return String(s == null ? '' : s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}



  // ===== Hitung lebar underline sampai ujung nama =====
  function calcWelcomeUnderline(){
    var wt = document.querySelector('.welcome-text');
    var wn = document.querySelector('.welcome-name');
    if(!wt || !wn) return;
    var rt = wt.getBoundingClientRect();
    var rn = wn.getBoundingClientRect();
    var width = Math.max(0, Math.round(rn.right - rt.left));
    wt.style.setProperty('--welcome-underline-width', width + 'px');
  }
  document.addEventListener('DOMContentLoaded', calcWelcomeUnderline);
  window.addEventListener('resize', calcWelcomeUnderline);

  // ===== Typewriter =====
  function typeWriter(el, text, speed){
    if(!el) return;
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if(reduce){ el.textContent = text; return; }
    el.textContent = '';
    var cursor = document.createElement('span');
    cursor.className = 'cursor';
    el.appendChild(cursor);
    var i = 0;
    (function tick(){
      if(i < text.length){
        cursor.insertAdjacentText('beforebegin', text.charAt(i++));
        setTimeout(tick, speed);
      }else{
        cursor.remove();
      }
    })();
  }
  document.addEventListener('DOMContentLoaded', function(){
    var sub = document.getElementById('welcome-sub');
    if(sub){
      var txt = sub.getAttribute('data-text') || sub.textContent || '';
      typeWriter(sub, txt, 16);
    }
  });
</script>

<script>
  // ====== AJAX STATUS ONLINE & DATATABLE ======
  $(document).ready(function(){
    var REFRESH_MS = 99000; // 99s
    $('#loading-admin, #loading-siswa').hide();
    if ($.fn.tooltip) { $('[data-toggle="tooltip"]').tooltip(); }

    /* ===== KONFIGURASI HIDE AKUN (dari PHP) ===== */
    var HIDE_ADMIN_USER_IDS = <?php echo json_encode(array_map('intval', $HIDE_ADMIN_USER_IDS)); ?>; // [1, 9, ...]
    var HIDE_ADMIN_USERNAMES = <?php
      // kirim apa adanya, akan diturunkan hurufnya di JS
      echo json_encode(array_values($HIDE_ADMIN_USERNAMES));
    ?>; // ["superadmin_ep","Admin EP",...]

    // Bentuk Set supaya pencocokan cepat
    var HIDE_IDS = new Set((HIDE_ADMIN_USER_IDS || []).map(function(x){ x=parseInt(x,10); return isNaN(x)?null:x; }).filter(function(x){ return x!==null; }));
    var HIDE_NAMES = new Set((HIDE_ADMIN_USERNAMES || []).map(function(s){ return String(s||'').toLowerCase().trim(); }).filter(Boolean));

    // Cek apakah suatu entri admin perlu disembunyikan (id/username/nama)
    function shouldHideAdminEntry(it){
      if(!it) return false;
      var uidRaw = (it.user_id != null) ? it.user_id : (it.id != null ? it.id : (it.userId != null ? it.userId : it.uid));
      var uid = parseInt(uidRaw, 10);
      var uname = (it.username || it.user_username || it.user || '').toString().toLowerCase().trim();
      var display = (it.nama || it.user_nama || it.name || '').toString().toLowerCase().trim();

      if (!isNaN(uid) && HIDE_IDS.has(uid)) return true;
      if (uname && HIDE_NAMES.has(uname)) return true;
      if (display && HIDE_NAMES.has(display)) return true;
      return false;
    }

    // === Opsi A: render khusus kolom tanggal (index 1) agar sorting pakai timestamp numerik ===
    var commonColumnDefs = [
      {
        targets: 1, // kolom "Terakhir Login"
        orderSequence: ['desc','asc'], // klik 1x: Terbaru, klik lagi: Terlama
        render: function (data, type, row) {
          if (type === 'sort') {
            var m = /data-ts="(\d+)"/.exec(data);
            return m ? parseInt(m[1], 10) : 0; // angka untuk sorting
          }
          return data; // tampilan & filter tetap pakai teks tanggal
        }
      },
      { targets: 2, orderable: true }
    ];

    var adminDT = $('#adminTable').DataTable({
      responsive: true, pageLength: 10, lengthMenu: [5,10,25,50,100], order: [],
      language:{ search:"Cari:", lengthMenu:"Tampilkan _MENU_ data", info:"Menampilkan _START_–_END_ dari _TOTAL_ data", infoEmpty:"Tidak ada data", zeroRecords:"Tidak ditemukan hasil",
        paginate:{ first:"Pertama", last:"Terakhir", next:"Berikutnya", previous:"Sebelumnya" } },
      columnDefs: commonColumnDefs
    });
    var siswaDT = $('#siswaTable').DataTable({
      responsive: true, pageLength: 10, lengthMenu: [5,10,25,50,100], order: [],
      language: $('#adminTable').DataTable().settings()[0].oLanguage,
      columnDefs: commonColumnDefs
    });

    function renderDT(dt, data){
      var currentPage = dt.page();
      dt.clear();
      (data || []).forEach(function(it){
        var isOnline = (it.status_login === 'online');
        var statusHtml =
          '<span class="status-badge '+(isOnline?'status-online':'status-offline')+'"></span> '+
          '<span class="status-text">'+(isOnline?'Online':'Offline')+'</span>';

        // Teks tampilan tanggal (dd/mm/yyyy HH:mm:ss)
        var f = formatIDDateTime(it.last_login);

        // Timestamp numerik untuk sorting (parse ISO, jika gagal coba dd/mm/yyyy atau dd-mm-yyyy)
        var ts = Date.parse(String(it.last_login).replace(' ', 'T'));
        if (isNaN(ts)) {
          var m = String(it.last_login).match(/(\d{2})[\/\-](\d{2})[\/\-](\d{4})[ T](\d{2}):(\d{2})(?::(\d{2}))?/);
          if (m) {
            ts = new Date(Number(m[3]), Number(m[2]) - 1, Number(m[1]), Number(m[4]), Number(m[5]), Number(m[6] || 0)).getTime();
          }
        }
        if (isNaN(ts)) { ts = 0; }

        var nameHtml = escHtml(it.nama || it.user_nama || '-');
        if (it.is_sekretaris === true || it.is_sekretaris === 1 || it.is_sekretaris === '1') {
          nameHtml += ' <span class="badge-role badge-sekretaris" title="Sekretaris Kelas">Sekretaris</span>';
        } // JANGAN pakai Date.now(); 0 supaya yang gagal parse jatuh di bawah saat DESC

        dt.row.add([
          nameHtml,
          '<span class="ts" data-ts="'+ts+'">'+f+'</span>', // tampilan + data-ts untuk sorter
          '<span data-order="'+(isOnline?1:0)+'">'+statusHtml+'</span>'
        ]);
      });
      dt.draw(false);
      var pages = dt.page.info().pages;
      if (currentPage < pages) dt.page(currentPage).draw(false);
    }

    function updateSummary(adminArr, siswaArr, serverStamp){
  adminArr = adminArr || []; siswaArr = siswaArr || [];
  var aOn = adminArr.filter(x => x.status_login === 'online').length;
  var sOn = siswaArr.filter(x => x.status_login === 'online').length;

  $('#sumAdmin').text('PTK & Admin: ' + aOn + '/' + adminArr.length + ' Online');
  $('#sumSiswa').text('Siswa: ' + sOn + '/' + siswaArr.length + ' Online');

  function pad(n){ return (n<10?'0':'')+n; }
  var now = serverStamp ? new Date(serverStamp.replace(' ', 'T')) : new Date();
  var month = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'][now.getMonth()];
  var stamp = pad(now.getDate())+' '+month+' '+now.getFullYear()+' '+pad(now.getHours())+':'+pad(now.getMinutes());
  $('#sumUpdated').text('Terakhir diperbarui: '+stamp);
}

    function showSkeleton(show){ $('#adminSkeleton, #siswaSkeleton').toggleClass('show', !!show); }

    var pauseAuto = false, pauseTimer = null;
    function setPause(ms){
      pauseAuto = true;
      $('#sumUpdated').text('Terakhir diperbarui: dijeda saat interaksi pengguna…');
      if (pauseTimer) clearTimeout(pauseTimer);
      pauseTimer = setTimeout(function(){ pauseAuto = false; updateStatusTables(true); }, ms || 3000);
    }
    adminDT.on('search.dt', function(){ setPause(5000); });
    adminDT.on('page.dt length.dt', function(){ setPause(2500); });
    siswaDT.on('search.dt', function(){ setPause(5000); });
    siswaDT.on('page.dt length.dt', function(){ setPause(2500); });

    function updateStatusTables(force){
  if (!force && pauseAuto){
    $('#sumUpdated').text('Terakhir diperbarui: dijeda saat interaksi pengguna…'); 
    return;
  }
  showSkeleton(true);

  var includeSek = $('#toggleSekretaris').is(':checked') ? 1 : 0;

  $.ajax({
    url: 'get_status.php?limit=1000&include_sekretaris=' + includeSek,
    method: 'GET',
    dataType: 'json'
  })
  .done(function(res){
    var adminArr = res && Array.isArray(res.admin) ? res.admin : [];
    var siswaArr = res && Array.isArray(res.siswa) ? res.siswa : [];

    // ====== FILTER: sembunyikan akun tertentu dari panel PTK & Admin ======
    adminArr = adminArr.filter(function(it){ return !shouldHideAdminEntry(it); });

    renderDT(adminDT, adminArr);
    renderDT(siswaDT, siswaArr);
    updateSummary(adminArr, siswaArr, (res && res.meta && res.meta.generated_at) ? res.meta.generated_at : null);
  })
  .fail(function(){ console.error('Gagal memuat data status.'); })
  .always(function(){ setTimeout(function(){ showSkeleton(false); }, 300); });
}


    // persist preferensi toggle di localStorage (opsional)
    var LS_KEY = 'dashboard_show_sekretaris_in_admin';
    var saved = localStorage.getItem(LS_KEY);
    if (saved === '1') $('#toggleSekretaris').prop('checked', true);

    $('#toggleSekretaris').on('change', function(){
      localStorage.setItem(LS_KEY, this.checked ? '1' : '0');
      updateStatusTables(true);
    });

    updateStatusTables(true);
    var refreshTimer = setInterval(function(){ updateStatusTables(false); }, REFRESH_MS);

    document.addEventListener('visibilitychange', function(){
      if (document.hidden){ setPause(REFRESH_MS + 1000); }
      else { updateStatusTables(true); }
    });

  });
</script>

<script>
  // ===== Aktivitas: Filter + Pagination =====
  document.addEventListener('DOMContentLoaded', function(){
    var list = document.getElementById('listAktBulan');
    var pager = document.getElementById('aktPager');
    var info  = document.getElementById('aktInfo');
    var sel   = document.getElementById('aktPageSize');
    if(!(list && pager && info && sel)) return;

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
        b.type = 'button'; b.className = 'btn-pager' + (active ? ' active' : '');
        b.textContent = label; if(disabled) b.setAttribute('disabled','disabled');
        b.addEventListener('click', handler); pager.appendChild(b);
      }

      makeBtn('Prev', currentPage===1, function(){ currentPage--; render(); });

      var maxBtns = 7;
      var startPage = Math.max(1, currentPage - Math.floor(maxBtns/2));
      var endPage   = Math.min(totalPages, startPage + maxBtns - 1);
      if(endPage - startPage + 1 < maxBtns){ startPage = Math.max(1, endPage - maxBtns + 1); }
      for(var p=startPage; p<=endPage; p++){
        (function(pp){ makeBtn(String(pp), false, function(){ currentPage = pp; render(); }, pp===currentPage); })(p);
      }

      makeBtn('Next', currentPage===totalPages, function(){ currentPage++; render(); });
    }

    document.querySelectorAll('.chip-filter').forEach(function(btn){
      btn.classList.remove('active');
      if(btn.getAttribute('data-filter')==='all') btn.classList.add('active');
      btn.addEventListener('click', function(){
        document.querySelectorAll('.chip-filter').forEach(function(b){ b.classList.remove('active'); b.setAttribute('aria-pressed','false'); });
        this.classList.add('active'); this.setAttribute('aria-pressed','true');
        currentFilter = this.getAttribute('data-filter'); currentPage = 1; render();
      });
    });

    sel.addEventListener('change', function(){ perPage = parseInt(this.value,10) || 5; currentPage = 1; render(); });

    render();
  });

  // ===== FAB & To-Top =====
  (function(){
    var fab = document.getElementById('fab');
    if(fab){
      var btn = fab.querySelector('.fab-main');

      // Efek "attention" awal
      btn.classList.add('attention');
      var _stopPulseOnce = function() {
        btn.classList.remove('attention');
        btn.removeEventListener('click', _stopPulseOnce);
      };
      btn.addEventListener('click', _stopPulseOnce, { once: false });

      var grp = fab.querySelector('.fab-group');

      // Toggle buka/tutup
      btn.addEventListener('click', function(e){
        e.stopPropagation();
        fab.classList.toggle('open');
        var expanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      });

      // Klik di luar => tutup
      document.addEventListener('click', function(e){
        if (!fab.contains(e.target)) {
          fab.classList.remove('open');
          btn.setAttribute('aria-expanded','false');
        }
      });

      // Esc => tutup
      document.addEventListener('keydown', function(e){
        if(e.key === 'Escape'){
          fab.classList.remove('open');
          btn.setAttribute('aria-expanded','false');
          btn.blur();
        }
      });
    }

    var btnTop = document.getElementById('toTop');
    window.addEventListener('scroll', function(){
      if(window.pageYOffset > 500) btnTop.classList.add('show'); else btnTop.classList.remove('show');
    });
    btnTop.addEventListener('click', function(){ window.scrollTo({top:0, behavior:'smooth'}); });
  })();
</script>

<?php include 'footer.php'; ?>
