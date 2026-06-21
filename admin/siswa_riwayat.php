<?php
// ===== helper: column exists (available for both page & ajax) =====
if (!function_exists('__col_exists')) {
  function __col_exists($db,$table,$col){
    $col = mysqli_real_escape_string($db,$col);
    $table = mysqli_real_escape_string($db,$table);
    $r = mysqli_query($db, "SHOW COLUMNS FROM `$table` LIKE '$col'");
    return $r && mysqli_num_rows($r)>0;
  }
}

// ====== AJAX endpoint: penerbitan SP (Stage 1B — staff guard, CSRF, prepared) ======
if (isset($_GET['ajax']) && $_GET['ajax'] === 'issue_sp') {
  require_once __DIR__ . '/../includes/epoin_sp_helpers.php';
  epoin_sp_ajax_issue_endpoint();
}
?>

<?php include 'header.php'; ?>
<?php require_once __DIR__ . '/../includes/epoin_security.php'; ?>
<script>window.EPOIN_CSRF = <?php echo json_encode(epoin_csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>

<?php
// ===== Helper kecil =====
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// ===== Konfigurasi kebijakan SP berbasis SALDO =====
$SP_BY_SALDO_SEQUENTIAL = true;

// ===== Pemetaan Tingkat Pembinaan (DIPAKAI UNTUK SALDO NEGATIF) =====
$STAGES = [
  ['roman'=>'I',   'min'=>1,   'max'=>20,     'program'=>'Pembinaan Umum',                          'action'=>'Teguran',                           'color'=>'#10b981', 'sp'=>'SP1'],
  ['roman'=>'II',  'min'=>21,  'max'=>40,     'program'=>'Pembinaan Umum / Panggilan Orang Tua',    'action'=>'Peringatan 1 (SP 1)',               'color'=>'#f59e0b', 'sp'=>'SP1'],
  ['roman'=>'III', 'min'=>41,  'max'=>60,     'program'=>'Panggilan Orang Tua',                     'action'=>'Peringatan 2 (SP 2)',               'color'=>'#f97316', 'sp'=>'SP2'],
  ['roman'=>'IV',  'min'=>61,  'max'=>80,     'program'=>'Pembinaan Khusus',                        'action'=>'Peringatan 3 (SP 3)',               'color'=>'#ef4444', 'sp'=>'SP3'],
  ['roman'=>'V',   'min'=>81,  'max'=>90,     'program'=>'Konferensi Kasus',                        'action'=>'Peringatan Terakhir (SP 4)',        'color'=>'#b91c1c', 'sp'=>'SP4'],
  ['roman'=>'V',   'min'=>91,  'max'=>99,     'program'=>'Konferensi Kasus',                        'action'=>'Tidak naik kelas (SP 4)',           'color'=>'#7f1d1d', 'sp'=>'SP4'],
  ['roman'=>'VI',  'min'=>100, 'max'=>999999, 'program'=>'Dikembalikan pada Orang Tua',             'action'=>'Pemulangan (SP 4)',                 'color'=>'#111827', 'sp'=>'SP4'],
];
$SAFE_STAGE = ['roman'=>'-', 'min'=>0, 'max'=>0, 'program'=>'Apresiasi / Monitoring', 'action'=>'Tidak ada tindakan', 'color'=>'#10b981', 'sp'=>null];

// ===== Ambil data siswa =====
require_once __DIR__ . '/../includes/epoin_security.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  echo '<div class="content-wrapper"><section class="content"><div class="alert alert-danger">ID siswa tidak valid.</div></section></div>';
  include 'footer.php';
  exit;
}

$k = epoin_fetch_siswa_row($koneksi, $id);
if (!$k) {
  echo '<div class="content-wrapper"><section class="content"><div class="alert alert-warning">Data siswa tidak ditemukan.</div></section></div>';
  include 'footer.php';
  exit;
}
$id_siswa = (int) $k['siswa_id'];
$foto_now = (!empty($k['siswa_foto']) ? '../gambar/siswa/' . $k['siswa_foto'] : '../gambar/sistem/user.png');

// ===== Total prestasi & pelanggaran =====
$totPrestasi = epoin_sum_prestasi_siswa($koneksi, $id_siswa);
$totPelang   = epoin_sum_pelanggaran_siswa($koneksi, $id_siswa);

$saldo = $totPrestasi - $totPelang; // saldo poin (positif = bagus)

// ===== Tentukan tahap pembinaan saat ini (BERDASARKAN SALDO) =====
$negSaldo = max(0, -$saldo); // hanya hitung saat saldo negatif
$currentStage = $SAFE_STAGE;
if ($negSaldo > 0){
  foreach($STAGES as $st){
    if($negSaldo >= $st['min'] && $negSaldo <= $st['max']){
      $currentStage = $st; break;
    }
  }
}

// ===== Pesan WA Orang Tua =====
$hpOrtu = ''; // kolom hp_ortu belum ada di tabel siswa
$spStatusWa = $currentStage['sp'] ? "\xF0\x9F\x94\xB4 Status Pembinaan: ".$currentStage['sp'] : "\xE2\x9C\x85 Status Pembinaan: Aman";
$waMsg = "Yth. Orang Tua/Wali ".$k['siswa_nama']." (".$k['jurusan_nama'].")\n"
       . "Kami informasikan perkembangan poin disiplin putra-putri Anda:\n\n"
       . "\xF0\x9F\x93\x8A Saldo Poin: ".$saldo."\n"
       . "\xE2\x9C\x85 Total Prestasi: ".$totPrestasi." kasus\n"
       . "\xE2\x9A\xA0 Total Pelanggaran: ".$totPelang." kasus\n"
       . $spStatusWa."\n\n"
       . "\xe2\x80\x93 Tim BK SMPN 1 Gunungtanjung";
$waUrl  = "https://wa.me/".($hpOrtu ? preg_replace('/\D/','',$hpOrtu) : '')."?text=".rawurlencode($waMsg);
$waDisabled = empty($hpOrtu);

// ===== LOG SP: cek apakah SP sudah diterbitkan (tahun berjalan) =====
mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS `sp_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `siswa_id` INT NOT NULL,
  `sp_level` ENUM('SP1','SP2','SP3','SP4') NOT NULL,
  `running_no` INT NOT NULL,
  `nomor` VARCHAR(64) NOT NULL,
  `alasan` TEXT NULL,
  `signer_user_id` INT NULL,
  `signer_posisi_key` ENUM('kepala','wakasek_kesiswaan','guru_bp') NULL,
  `signer_nama` VARCHAR(120) NULL,
  `signer_jabatan` VARCHAR(120) NULL,
  `tanggal` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_year` (`tanggal`),
  KEY `idx_siswa` (`siswa_id`, `sp_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (!function_exists('sp_issued')) {
  function sp_issued($koneksi, $sid, $level){
    $yr = date('Y');
    $q = mysqli_query($koneksi, "SELECT `nomor`,`tanggal` FROM `sp_log` WHERE `siswa_id`='$sid' AND `sp_level`='$level' AND YEAR(`tanggal`)='$yr' ORDER BY `id` DESC LIMIT 1");
    return $q ? mysqli_fetch_assoc($q) : null;
  }
}
$sp1_issued = sp_issued($koneksi, $id_siswa, 'SP1');
$sp2_issued = sp_issued($koneksi, $id_siswa, 'SP2');
$sp3_issued = sp_issued($koneksi, $id_siswa, 'SP3');
$sp4_issued = sp_issued($koneksi, $id_siswa, 'SP4');

// ===== Ambang tombol aktif (SEKARANG BERDASARKAN SALDO NEGATIF) =====
if ($SP_BY_SALDO_SEQUENTIAL) {
  $canSP1 = ($negSaldo >= 21);
  $canSP2 = ($negSaldo >= 41) && (bool)$sp1_issued;
  $canSP3 = ($negSaldo >= 61) && (bool)$sp2_issued;
  $canSP4 = ($negSaldo >= 81) && (bool)$sp3_issued;
} else {
  $canSP1 = ($negSaldo >= 21);
  $canSP2 = ($negSaldo >= 41);
  $canSP3 = ($negSaldo >= 61);
  $canSP4 = ($negSaldo >= 81);
}

// ===== HARD GUARD: jika saldo >= 0, nonaktifkan penerbitan SP baru (reprint tetap boleh) =====
if ($saldo >= 0) {
  $canSP1 = $canSP2 = $canSP3 = $canSP4 = false;
}

// ===== Helper label & class tombol SP =====
if (!function_exists('sp_button_conf')) {
  function sp_button_conf($enabled, $issued, $label_new, $label_reprint, $class_new='btn-danger', $class_reprint='btn-primary'){
    // Jika sudah pernah terbit, selalu izinkan cetak ulang (meskipun saldo sudah pulih)
    $conf = ['enabled'=>$enabled || (bool)$issued, 'label'=>$label_new, 'class'=>'btn-default btn-sp disabled'];
    if ($issued){
      $conf['label'] = $label_reprint;
      $conf['class'] = $class_reprint;
    } elseif ($enabled){
      $conf['label'] = $label_new;
      $conf['class'] = $class_new;
    }
    return $conf;
  }
}
$btnSP1 = sp_button_conf($canSP1, $sp1_issued, 'Terbitkan SP1', 'Cetak Ulang SP1');
$btnSP2 = sp_button_conf($canSP2, $sp2_issued, 'Terbitkan SP2', 'Cetak Ulang SP2');
$btnSP3 = sp_button_conf($canSP3, $sp3_issued, 'Terbitkan SP3', 'Cetak Ulang SP3');
$btnSP4 = sp_button_conf($canSP4, $sp4_issued, 'Terbitkan SP4', 'Cetak Ulang SP4');

// ===== Perubahan Terakhir (gabungan prestasi & pelanggaran) =====
$last = null;
$qlast = mysqli_query($koneksi, "
  (SELECT ip.`waktu` AS waktu, 'prestasi' AS jenis, p.`prestasi_nama` AS nama, p.`prestasi_point` AS poin
     FROM `input_prestasi` ip
     JOIN `prestasi` p ON ip.`prestasi`=p.`prestasi_id`
    WHERE ip.`siswa`='$id_siswa')
  UNION ALL
  (SELECT ig.`waktu` AS waktu, 'pelanggaran' AS jenis, pg.`pelanggaran_nama` AS nama, pg.`pelanggaran_point` AS poin
     FROM `input_pelanggaran` ig
     JOIN `pelanggaran` pg ON ig.`pelanggaran`=pg.`pelanggaran_id`
    WHERE ig.`siswa`='$id_siswa')
  ORDER BY waktu DESC
  LIMIT 1
");
if($qlast){ $last = mysqli_fetch_assoc($qlast); }

// ===== Tentukan sekolah aktif & daftar Guru BP untuk dropdown =====
$SEKOLAH_ID = (int)($_SESSION['sekolah_id'] ?? 1);
$guruBpList = [];
// deteksi kolom nama utk tabel user
$userNameCol = null;
foreach (['nama_lengkap','user_nama','nama','full_name','name','nama_user','display_name','realname'] as $c) {
  if (__col_exists($koneksi,'user',$c)) { $userNameCol = $c; break; }
}
if (!$userNameCol) $userNameCol = 'username'; // fallback minimal

$qbp = mysqli_query($koneksi, "SELECT ss.`user_id`, u.`$userNameCol` AS uname
         FROM `sekolah_staff` ss
         JOIN `user` u ON ss.`user_id`=u.`user_id`
        WHERE ss.`sekolah_id`='$SEKOLAH_ID' AND ss.`posisi_key`='guru_bp'
        ORDER BY u.`$userNameCol` ASC");
if($qbp){ while($r=mysqli_fetch_assoc($qbp)){ $guruBpList[] = ['user_id'=>$r['user_id'], 'nama'=>$r['uname']]; } }
?>
<style>
  .page-accent{display:inline-block;padding:.25rem .6rem;font-weight:600;border-radius:999px;background:linear-gradient(135deg,#3b82f6,#06b6d4);color:#fff;font-size:12px;box-shadow:0 4px 14px rgba(59,130,246,.25)}
  .content-header h1{display:flex;align-items:center;gap:10px}
  /* (… CSS TETAP …) */

  /* =========[ BARU: SERAGAM JUDUL (ikon kotak + badge kecil + fade/slide) ]========= */
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
    animation:textFade .6s ease-out .05s forwards; /* fade + slide-up */
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
  /* Paksa override <small> bawaan Bootstrap */
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
  /* =========[ /BARU ]========= */

  .profile-card{
    position:relative;
    border-radius:16px;
    background:
      radial-gradient(900px 200px at -10% -10%, rgba(255,255,255,.22), transparent 60%),
      linear-gradient(135deg,#0ea5e9,#6366f1);
    color:#fff;
    box-shadow:0 18px 36px rgba(2,6,23,.18), inset 0 0 0 1px rgba(255,255,255,.1);
    padding:16px;display:flex;gap:14px;align-items:center;overflow:hidden;
  }
  .profile-card::after{
    content:""; position:absolute; top:-120%; left:-30%;
    width:40%; height:320%; transform:rotate(25deg);
    background:linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,.35) 50%, rgba(255,255,255,0) 100%);
    animation: shimmer-soft 3.2s infinite; mix-blend-mode:screen; pointer-events:none;
  }
  @keyframes shimmer-soft{0%{left:-40%}60%{left:120%}100%{left:120%}}
  .avatar-lg{width:64px;height:64px;border-radius:50%;}
  .chip{display:inline-flex;align-items:center;gap:6px;padding:.25rem .55rem;border-radius:999px;background:rgba(255,255,255,.18);color:#fff;font-weight:700;border:1px solid rgba(255,255,255,.35)}
  .stat-card{position:relative;border-radius:14px;background:linear-gradient(135deg,#f8fafc,#eef2ff);box-shadow:0 10px 20px rgba(2,6,23,.08),inset 0 0 0 1px #e5e7eb;padding:16px;display:flex;gap:10px;align-items:center;overflow:hidden}
  .stat-ico{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;box-shadow:0 8px 16px rgba(0,0,0,.12)}
  .bg-green{background:linear-gradient(135deg,#10b981,#059669)}
  .bg-red{background:linear-gradient(135deg,#ef4444,#b91c1c)}
  .bg-blue{background:linear-gradient(135deg,#3b82f6,#2563eb)}
  .stat-label{font-size:12px;color:#334155;font-weight:700}
  .stat-value{font-size:22px;font-weight:900;letter-spacing:.2px}
  .countup{animation:pop .5s ease-out both}
  @keyframes pop{from{transform:scale(.9);opacity:0}to{transform:scale(1);opacity:1}}
  .saldo-wrap{background:#0b1220;border:1px solid #1f2a44;border-radius:12px;overflow:hidden;position:relative;box-shadow: inset 0 0 0 1px rgba(255,255,255,.03)}
  .saldo-zero{position:absolute;left:50%;top:0;bottom:0;width:2px;background:rgba(255,255,255,.25)}
  .saldo-bar{height:12px;transition:width .4s ease, transform .4s ease; background:linear-gradient(90deg, #34d399, #10b981)}
  .saldo-green{background:linear-gradient(90deg,#86efac,#22c55e)}
  .saldo-red{background:linear-gradient(90deg,#fecaca,#ef4444)}
  .saldo-legend{display:flex;justify-content:space-between;font-size:12px;color:#0f172a;margin-top:8px}
  .viol-wrap{background:linear-gradient(180deg,#f8fafc,#f1f5f9);border:1px solid #e5e7eb;border-radius:12px;padding:12px;box-shadow:0 12px 24px rgba(2,6,23,.06)}
  .viol-bar{height:10px;border-radius:999px;background:#e5e7eb;overflow:hidden}
  .viol-fill{height:100%; width:0; transition:width .4s ease; background:#f59e0b}
  .stage-card{position:relative;border:1px solid #e5e7eb;border-radius:12px;padding:12px;margin-top:10px;overflow:hidden;background:#fff}
  .stage-card::before{content:""; position:absolute; inset:0; background: radial-gradient(500px 120px at -10% -10%, rgba(99,102,241,.12), transparent 70%); pointer-events:none;}
  .stage-grid{display:grid;grid-template-columns:140px 1fr;gap:8px 14px}
  .stage-badge{display:inline-block;border-radius:999px;font-weight:900;padding:.15rem .6rem;color:#fff;box-shadow:0 6px 12px rgba(0,0,0,.12)}
  .btn-sp.disabled, .btn[disabled]{pointer-events:none; opacity:.55}
  #tbl-prestasi thead th, #tbl-pelanggaran thead th{position:sticky;top:0;background:#f8fafc;z-index:1}
  .table-badge{border-radius:999px;padding:.2em .55em;font-weight:800}
  .tb-green{background:#dcfce7;color:#065f46}
  .tb-red{background:#fee2e2;color:#991b1b}
  #tbl-prestasi tfoot td, #tbl-pelanggaran tfoot td{ text-align:center !important; vertical-align:middle !important; }
  @media (max-width:576px){
    .profile-card{padding:12px}
    .stat-card{padding:12px}
    .table-responsive{overflow-x:auto}
    #tbl-prestasi th,#tbl-prestasi td,#tbl-pelanggaran th,#tbl-pelanggaran td{white-space:nowrap}
    .stage-grid{grid-template-columns:1fr}
  }

  /* ====== FIX TOOLTIP TERTUTUP MODAL ======
     1) Naikkan z-index tooltip/popover supaya selalu di atas modal
     2) Tooltip akan dirender ke <body> via JS (lihat di bawah) sehingga tidak terpotong overflow */
  .tooltip       { z-index: 99999 !important; }
  .popover       { z-index: 99999 !important; }
  .modal .tooltip, .modal .popover { z-index: 99999 !important; }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <!-- SERAGAM DENGAN siswa.php -->
    <h1 class="page-title">
      <span class="title-icon"><i class="fa fa-user"></i></span>
      Profil Disiplin Siswa
      <small class="title-badge">
        <i class="fa fa-check-circle"></i>
        Ringkasan poin, riwayat, tingkat pembinaan, dan penerbitan SP
      </small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Dashboard</li>
    </ol>
  </section>

  <section class="content">

    <!-- Profil ringkas -->
<!-- Profil ringkas -->
<div class="row">
  <section class="col-lg-12">
    <div class="box box-primary">
      <div class="box-header" style="display:flex;align-items:center;gap:8px;">
        <h3 class="box-title" style="margin:0;">Tentang Siswa</h3>
        <div style="margin-left:auto;display:flex;gap:6px;align-items:center;">
          <?php if($waDisabled): ?>
            <span class="btn btn-success btn-sm disabled"
                  style="cursor:not-allowed;opacity:.6;"
                  data-toggle="tooltip" data-placement="bottom"
                  title="Nomor WA orang tua belum tersedia"
                  aria-disabled="true">
              <i class="fa fa-whatsapp"></i> Hubungi Ortu
            </span>
          <?php else: ?>
            <a href="<?php echo epoin_h($waUrl); ?>" target="_blank" rel="noopener"
               class="btn btn-success btn-sm">
              <i class="fa fa-whatsapp"></i> Hubungi Ortu
            </a>
          <?php endif; ?>
          <a href="siswa.php" class="btn btn-default btn-sm">
            <i class="fa fa-arrow-left"></i> Kembali
          </a>
        </div>
      </div>

          <div class="box-body">
            <?php if(!$k): ?>
              <div class="alert alert-danger">Data siswa tidak ditemukan.</div>
            <?php else: ?>
              <div class="profile-card">
                <img src="<?php echo e($foto_now); ?>" class="avatar-lg" alt="Foto siswa">
                <div>
                  <div style="font-weight:900;font-size:18px;letter-spacing:.3px;"><?php echo e($k['siswa_nama']); ?></div>
                  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;">
                    <span class="chip"><i class="fa fa-id-card-o"></i> NIS: <b><?php echo e($k['siswa_nis']); ?></b></span>
                    <span class="chip"><i class="fa fa-book"></i> <?php echo e($k['jurusan_nama']); ?></span>
                  </div>
                </div>
              </div>

              <!-- Stat cards -->
              <div class="row" style="margin-top:14px;">
                <div class="col-sm-4">
                  <div class="stat-card">
                    <div class="stat-ico bg-green"><i class="fa fa-trophy"></i></div>
                    <div>
                      <div class="stat-label">Total Prestasi</div>
                      <div class="stat-value countup" data-target="<?php echo (int)$totPrestasi; ?>" data-duration="1200">0</div>
                    </div>
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="stat-card">
                    <div class="stat-ico bg-red"><i class="fa fa-exclamation-circle"></i></div>
                    <div>
                      <div class="stat-label">Total Pelanggaran</div>
                      <div class="stat-value countup" data-target="<?php echo (int)$totPelang; ?>" data-duration="1200">0</div>
                    </div>
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="stat-card">
                    <div class="stat-ico bg-blue"><i class="fa fa-balance-scale"></i></div>
                    <div>
                      <div class="stat-label">Saldo (Prestasi − Pelanggaran)</div>
                      <div class="stat-value countup" data-target="<?php echo (int)$saldo; ?>" data-duration="1200">0</div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Progres Saldo Poin (pusat = 0) -->
              <?php
                $scaleMaxSaldo = max(100, abs($saldo));
                $percentSaldo = $scaleMaxSaldo > 0 ? min(100, round(abs($saldo) / $scaleMaxSaldo * 100)) : 0;
                $isPos = $saldo >= 0;
              ?>
              <div class="row" style="margin-top:12px;">
                <div class="col-lg-12">
                  <div class="saldo-wrap">
                    <div class="saldo-zero"></div>
                    <div class="saldo-bar <?php echo $isPos?'saldo-green':'saldo-red'; ?>"
                         style="width:<?php echo $percentSaldo; ?>%; transform:translateX(<?php echo $isPos? '50%' : '-50%'; ?>);">
                    </div>
                  </div>
                  <div class="saldo-legend">
                    <div><b>Progres Saldo Poin</b>: <span style="color:<?php echo $isPos?'#059669':'#b91c1c'; ?>;"><?php echo ($saldo>=0?'+':'').(int)$saldo; ?></span> dari ±<?php echo (int)$scaleMaxSaldo; ?></div>
                    <div><?php echo $percentSaldo; ?>%</div>
                  </div>
                </div>
              </div>

              <!-- Progres Poin Siswa (berdasarkan SALDO) & Tingkat Pembinaan -->
              <?php
                $riskPercent = max(0, min(100, -$saldo)); // hanya saldo negatif
                $stageColor  = $currentStage['color'] ?? '#10b981';
              ?>
              <div class="row" style="margin-top:14px%;">
                <div class="col-lg-12">
                  <div class="viol-wrap">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                      <b>Progres Poin Siswa (berdasarkan saldo)</b>
                      <span><?php echo $negSaldo; ?> / 100 (risiko sanksi)</span>
                    </div>
                    <div class="viol-bar" title="Semakin penuh = saldo semakin negatif">
                      <div class="viol-fill" style="width:<?php echo $riskPercent; ?>%; background:<?php echo e($stageColor); ?>;"></div>
                    </div>

                    <div class="stage-card">
                      <div class="stage-grid">
                        <div><b>Tingkat</b></div>
                        <div>
                          <span class="stage-badge" style="background:<?php echo e($stageColor); ?>;">
                            <?php echo e($currentStage['roman']); ?>
                          </span>
                          <span style="margin-left:8px;color:#475569;">(Saldo: <?php echo ($saldo>=0?'+':'').$saldo; ?>)</span>
                        </div>

                        <div><b>Program</b></div>
                        <div><?php echo e($currentStage['program']); ?></div>

                        <div><b>Tindakan</b></div>
                        <div><?php echo e($currentStage['action']); ?></div>

                        <div><b>Perubahan Terakhir</b></div>
                        <div>
                          <?php if($last): ?>
                            <?php
                              $isPrestasi = ($last['jenis']==='prestasi');
                              $sign = $isPrestasi ? '+' : '-';
                              $badgeClass = $isPrestasi ? 'tb-green' : 'tb-red';
                            ?>
                            <span class="table-badge <?php echo $badgeClass; ?>">
                              <?php echo $sign.(int)$last['poin']; ?>
                            </span>
                            <span style="margin-left:6px;">
                              <?php echo e(ucfirst($last['jenis'])); ?> — <?php echo e($last['nama']); ?>,
                              <i><?php echo e(date('d-m-Y H:i:s', strtotime($last['waktu']))); ?></i>
                            </span>
                          <?php else: ?>
                            <span class="text-muted">—</span>
                          <?php endif; ?>
                        </div>
                      </div>

                      <!-- Tombol SP: Terbitkan / Cetak Ulang (BERDASARKAN SALDO) + modal alasan -->
                      <div style="margin-top:12px; display:flex; flex-wrap:wrap; gap:8px;">
                        <a href="<?php echo e('sp1_cetak.php?id='.$id_siswa); ?>" target="_blank"
                           class="btn btn-sm <?php echo $btnSP1['class']; ?> js-sp-btn"
                           data-enabled="<?php echo $btnSP1['enabled']?1:0; ?>"
                           data-issued="<?php echo $sp1_issued?1:0; ?>"
                           data-sp="SP1"
                           data-print="<?php echo e('sp1_cetak.php?id='.$id_siswa); ?>">
                          <i class="fa fa-file-text-o"></i> <?php echo e($btnSP1['label']); ?>
                        </a>

                        <a href="<?php echo e('sp2_cetak.php?id='.$id_siswa); ?>" target="_blank"
                           class="btn btn-sm <?php echo $btnSP2['class']; ?> js-sp-btn"
                           data-enabled="<?php echo $btnSP2['enabled']?1:0; ?>"
                           data-issued="<?php echo $sp2_issued?1:0; ?>"
                           data-sp="SP2"
                           data-print="<?php echo e('sp2_cetak.php?id='.$id_siswa); ?>">
                          <i class="fa fa-file-text-o"></i> <?php echo e($btnSP2['label']); ?>
                        </a>

                        <a href="<?php echo e('sp3_cetak.php?id='.$id_siswa); ?>" target="_blank"
                           class="btn btn-sm <?php echo $btnSP3['class']; ?> js-sp-btn"
                           data-enabled="<?php echo $btnSP3['enabled']?1:0; ?>"
                           data-issued="<?php echo $sp3_issued?1:0; ?>"
                           data-sp="SP3"
                           data-print="<?php echo e('sp3_cetak.php?id='.$id_siswa); ?>">
                          <i class="fa fa-file-text-o"></i> <?php echo e($btnSP3['label']); ?>
                        </a>

                        <a href="<?php echo e('sp4_cetak.php?id='.$id_siswa); ?>" target="_blank"
                           class="btn btn-sm <?php echo $btnSP4['class']; ?> js-sp-btn"
                           data-enabled="<?php echo $btnSP4['enabled']?1:0; ?>"
                           data-issued="<?php echo $sp4_issued?1:0; ?>"
                           data-sp="SP4"
                           data-print="<?php echo e('sp4_cetak.php?id='.$id_siswa); ?>">
                          <i class="fa fa-file-text-o"></i> <?php echo e($btnSP4['label']); ?>
                        </a>

                        <small class="text-muted" style="align-self:center;">
                          (Catatan: <b>Tingkat</b> & <b>SP</b> berdasarkan <b>saldo (netto)</b><?php echo $SP_BY_SALDO_SEQUENTIAL ? ', dan <b>berurutan</b> (SP2⇐SP1, SP3⇐SP2, SP4⇐SP3).' : '.'; ?>
                          Cetak ulang tetap tersedia bila sudah pernah terbit.)
                        </small>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

            <?php endif; ?>
          </div>
        </div>
      </section>
    </div>

    <!-- Riwayat Prestasi & Pelanggaran -->
    <div class="row">
      <div class="col-lg-6">
        <div class="box box-success">
          <div class="box-header">
            <h3 class="box-title">Riwayat Prestasi</h3>
          </div>
          <div class="box-body">
            <div class="table-responsive">
              <table class="table table-bordered table-striped" id="tbl-prestasi" style="width:100%;">
                <thead>
                  <tr>
                    <th width="1%">NO</th>
                    <th>WAKTU</th>
                    <th>KELAS</th>
                    <th>TAHUN AJARAN</th>
                    <th>PRESTASI</th>
                    <th>POIN</th>
                  </tr>
                </thead>
                <tbody>
                <?php
                  $no=1;
                  $data = mysqli_query($koneksi,"SELECT ip.*, p.`prestasi_nama`, p.`prestasi_point`, k.`kelas_nama`, t.`ta_nama`
                          FROM `input_prestasi` ip
                          JOIN `prestasi` p ON ip.`prestasi`=p.`prestasi_id`
                          JOIN `kelas` k ON ip.`kelas`=k.`kelas_id`
                          JOIN `ta` t ON k.`kelas_ta`=t.`ta_id`
                          WHERE ip.`siswa`='$id_siswa'
                          ORDER BY ip.`waktu` DESC");
                  while($d = mysqli_fetch_assoc($data)):
                ?>
                  <tr>
                    <td><?php echo $no++; ?></td>
                    <td><?php echo e(date('d-m-Y H:i:s', strtotime($d['waktu']))); ?></td>
                    <td><?php echo e($d['kelas_nama']); ?></td>
                    <td><?php echo e($d['ta_nama']); ?></td>
                    <td><?php echo e($d['prestasi_nama']); ?></td>
                    <td class="text-center"><span class="table-badge tb-green">+<?php echo (int)$d['prestasi_point']; ?></span></td>
                  </tr>
                <?php endwhile; ?>
                </tbody>
                <tfoot>
                  <tr>
                    <td class="text-center text-bold" colspan="5">TOTAL</td>
                    <!-- Tambah tanda + dan pastikan rata tengah -->
                    <td class="bg-green text-center text-bold">+<?php echo (int)$totPrestasi; ?></td>
                  </tr>
                </tfoot>
              </table>
            </div>

          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="box box-danger">
          <div class="box-header">
            <h3 class="box-title">Riwayat Pelanggaran</h3>
          </div>
          <div class="box-body">
            <div class="table-responsive">
              <table class="table table-bordered table-striped" id="tbl-pelanggaran" style="width:100%;">
                <thead>
                  <tr>
                    <th width="1%">NO</th>
                    <th>WAKTU</th>
                    <th>KELAS</th>
                    <th>TAHUN AJARAN</th>
                    <th>PELANGGARAN</th>
                    <th>POIN</th>
                  </tr>
                </thead>
                <tbody>
                <?php
                  $no=1;
                  $data = mysqli_query($koneksi,"SELECT ig.*, pg.`pelanggaran_nama`, pg.`pelanggaran_point`, k.`kelas_nama`, t.`ta_nama`
                          FROM `input_pelanggaran` ig
                          JOIN `pelanggaran` pg ON ig.`pelanggaran`=pg.`pelanggaran_id`
                          JOIN `kelas` k ON ig.`kelas`=k.`kelas_id`
                          JOIN `ta` t ON k.`kelas_ta`=t.`ta_id`
                          WHERE ig.`siswa`='$id_siswa'
                          ORDER BY ig.`waktu` DESC");
                  while($d = mysqli_fetch_assoc($data)):
                ?>
                  <tr>
                    <td><?php echo $no++; ?></td>
                    <td><?php echo e(date('d-m-Y H:i:s', strtotime($d['waktu']))); ?></td>
                    <td><?php echo e($d['kelas_nama']); ?></td>
                    <td><?php echo e($d['ta_nama']); ?></td>
                    <td><?php echo e($d['pelanggaran_nama']); ?></td>
                    <td class="text-center"><span class="table-badge tb-red">-<?php echo (int)$d['pelanggaran_point']; ?></span></td>
                  </tr>
                <?php endwhile; ?>
                </tbody>
                <tfoot>
                  <tr>
                    <td class="text-center text-bold" colspan="5">TOTAL</td>
                    <!-- Tambah tanda - dan pastikan rata tengah -->
                    <td class="bg-red text-center text-bold" style="color:#fff">-<?php echo (int)$totPelang; ?></td>
                  </tr>
                </tfoot>
              </table>
            </div>

          </div>
        </div>
      </div>
    </div>

  </section>
</div>

<!-- ===== Modal Alasan Penerbitan SP ===== -->
<div class="modal fade" id="modalSpAlasan" tabindex="-1" role="dialog" aria-labelledby="modalSpAlasanLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content" style="border-radius:14px; overflow:hidden;">
      <div class="modal-header" style="background:linear-gradient(135deg,#0ea5e9,#6366f1); color:#fff;">
        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup" style="color:#fff; opacity:.9;"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="modalSpAlasanLabel" style="font-weight:800; letter-spacing:.2px;">
          Alasan Penerbitan SP
        </h4>
        <div style="font-size:12px;opacity:.9;margin-top:4px">
          Catat alasan & pilih <b>Guru BP</b> penandatangan.
          <span class="label label-info" style="margin-left:6px;cursor:help" data-toggle="tooltip"
                title="Tanda tangan Wali Kelas, Waka Kesiswaan, dan Kepala Sekolah akan diisi OTOMATIS dari Profil Sekolah pada halaman cetak.">
            Info
          </span>
        </div>
      </div>
      <form id="formSpAlasan" method="post" action="#">
        <div class="modal-body" style="background:#f8fafc">
          <div class="form-group">
            <label for="alasanSp" style="font-weight:700">Alasan</label>
            <textarea id="alasanSp" name="alasan" class="form-control" rows="4" minlength="5" required placeholder="Tuliskan ringkas alasan penerbitan SP..."></textarea>
            <p class="help-block">Alasan ini akan tercatat di log SP.</p>
          </div>

          <div class="form-group">
            <label for="penandatanganSelect" style="font-weight:700">
              Guru BP Penandatangan
              <i class="fa fa-info-circle" data-toggle="tooltip"
                 title="Wali Kelas, Waka Kesiswaan, dan Kepala Sekolah tidak perlu dipilih di sini — akan otomatis terisi saat dicetak."></i>
            </label>
            <select id="penandatanganSelect" class="form-control" style="width:100%" required>
              <option value="">— pilih Guru BP —</option>
              <?php foreach($guruBpList as $bp): ?>
                <option value="<?php echo (int)$bp['user_id']; ?>">Guru BP — <?php echo htmlspecialchars($bp['nama'],ENT_QUOTES,'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
            <p class="help-block">Hanya Guru BP/BK yang dipilih di sini. Tanda tangan pejabat lain akan diambil otomatis dari Profil Sekolah.</p>
          </div>
        </div>
        <div class="modal-footer" style="background:#fff">
          <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger" data-toggle="tooltip"
                  title="Klik untuk menerbitkan. Setelah berhasil, cetak akan terbuka — penandatangan lain otomatis.">
            <i class="fa fa-check-circle"></i> Terbitkan & Cetak
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>

<script>
  // ===== aktifkan Bootstrap tooltip =====
  // Penting: render ke <body> supaya tidak terpotong overflow modal-content
  $(function(){
    $('[data-toggle="tooltip"]').tooltip({
      container: 'body',   // <-- kunci utama agar tidak ketutup / terpotong
      html: false
    });
  });

  // Saat modal dibuka, pastikan semua elemen di dalamnya juga pakai container:'body'
  $('#modalSpAlasan').on('shown.bs.modal', function(){
    $(this).find('[data-toggle="tooltip"]').tooltip({ container: 'body' });
  });

  // Saat modal ditutup, sembunyikan semua tooltip supaya tidak “nyangkut”
  $('#modalSpAlasan').on('hide.bs.modal', function(){
    $('[data-toggle="tooltip"]').tooltip('hide');
  });

  // ===== Count-up angka =====
  (function(){
    function easeOutCubic(t){ return 1 - Math.pow(1 - t, 3); }
    function countUp(el){
      if (el.dataset.animated) return;
      el.dataset.animated = '1';
      var to  = parseInt(el.getAttribute('data-target'),10) || 0;
      var dur = parseInt(el.getAttribute('data-duration'),10) || 1000;
      var start = 0, t0 = performance.now();
      function frame(now){
        var p = Math.min((now-t0)/dur,1);
        var v = Math.round(start + (to-start)*easeOutCubic(p));
        el.textContent = v.toLocaleString('id-ID');
        if(p<1) requestAnimationFrame(frame);
      }
      requestAnimationFrame(frame);
    }
    var items = document.querySelectorAll('.countup');
    if('IntersectionObserver' in window){
      var io = new IntersectionObserver(function(es){
        es.forEach(function(en){ if(en.isIntersecting){ countUp(en.target); io.unobserve(en.target); } });
      },{threshold:.6});
      items.forEach(function(el){ io.observe(el); });
    }else{ items.forEach(countUp); }
  })();

  // ===== DataTables init (guarded) =====
  if ($.fn && $.fn.dataTable && $.fn.dataTable.ext) { $.fn.dataTable.ext.errMode = 'none'; }

  $(function(){
    var $tp = $('#tbl-prestasi'), $tv = $('#tbl-pelanggaran');
    if ($.fn.DataTable && $.fn.DataTable.isDataTable) {
      if ($.fn.DataTable.isDataTable($tp)) { $tp.DataTable().clear().destroy(); }
      if ($.fn.DataTable.isDataTable($tv)) { $tv.DataTable().clear().destroy(); }
      $tp.DataTable({
        destroy:true, pageLength:10, order:[[1,'desc']], autoWidth:false,
        columnDefs:[{targets:0,width:"32px"},{targets:5, className:'text-center'}],
        language:{search:"Cari:",lengthMenu:"Tampil _MENU_ data",info:"Menampilkan _START_–_END_ dari _TOTAL_ data",paginate:{previous:"←",next:"→"}}
      });
      $tv.DataTable({
        destroy:true, pageLength:10, order:[[1,'desc']], autoWidth:false,
        columnDefs:[{targets:0,width:"32px"},{targets:5, className:'text-center'}],
        language:{search:"Cari:",lengthMenu:"Tampil _MENU_ data",info:"Menampilkan _START_–_END_ dari _TOTAL_ data",paginate:{previous:"←",next:"→"}}
      });
    }

    // ===== SP issuance with alasan (AJAX) + PREFILL =====
    var siswaId   = <?php echo (int)$id_siswa; ?>;
    var saldoNow  = <?php echo (int)$saldo; ?>;
    var negSaldo  = <?php echo (int)$negSaldo; ?>;
    var stageRoman= "<?php echo e($currentStage['roman']); ?>";
    var stageMin  = <?php echo (int)($currentStage['min'] ?? 0); ?>;
    var stageMax  = <?php echo (int)($currentStage['max'] ?? 0); ?>;

    // Info perubahan terakhir utk prefill
    var lastInfo = {
      exists: <?php echo $last? 'true':'false'; ?>,
      jenis:  "<?php echo $last? e($last['jenis']) : ''; ?>",
      nama:   "<?php echo $last? e($last['nama'])  : ''; ?>",
      poin:   <?php echo $last? (int)$last['poin'] : 0; ?>,
      waktu:  "<?php echo $last? date('d-m-Y H:i', strtotime($last['waktu'])) : ''; ?>"
    };

    function planByLevel(sp){
      if (sp==='SP1') return 'Panggilan awal kepada orang tua/wali.';
      if (sp==='SP2') return 'Panggilan orang tua lanjutan & komitmen perbaikan.';
      if (sp==='SP3') return 'Pembinaan khusus terstruktur bersama BK.';
      return 'Konferensi kasus & keputusan lanjutan sesuai ketentuan.';
    }

    function cap(s){ return s ? s.charAt(0).toUpperCase()+s.slice(1) : s; }

    // Select2 (jika tersedia)
    if ($.fn && $.fn.select2) {
      $('#penandatanganSelect').select2({
        width:'100%',
        placeholder:'Pilih Guru BP',
        dropdownParent: $('#modalSpAlasan')
      });
    }

    $(document).on('click', '.js-sp-btn', function(e){
      var $btn    = $(this);
      var enabled = ($btn.data('enabled') == 1);
      var issued  = ($btn.data('issued') == 1);
      var sp      = $btn.data('sp');
      var printUrl= $btn.data('print');

      if (!enabled) { e.preventDefault(); return false; }
      if (issued) { return true; } // cetak ulang langsung

      if (saldoNow >= 0) { // hard guard klien
        e.preventDefault();
        alert('Saldo ≥ 0. SP tidak dapat diterbitkan.');
        return false;
      }

      e.preventDefault();
      var $modal = $('#modalSpAlasan');
      $modal.data('sp', sp).data('print', printUrl);

      // === PREFILL TEKS ALASAN ===
      var saldoTxt = (saldoNow>=0?'+':'') + String(saldoNow);
      var tahapTxt = (negSaldo>0 ? ('Tahap '+stageRoman+': '+stageMin+'–'+stageMax) : 'Aman/Apresiasi');
      var lastTxt  = '';
      if (lastInfo.exists) {
        var sign = lastInfo.jenis==='prestasi' ? '+' : '-';
        lastTxt = ' Perubahan terakhir: ' + cap(lastInfo.jenis) + ' — ' + lastInfo.nama +
                  ' (' + sign + Math.abs(lastInfo.poin) + ') pada ' + lastInfo.waktu + '.';
      }
      var rencana = planByLevel(sp);

      var prefill = 'Saldo saat ini ' + saldoTxt + ' (' + tahapTxt + '). ' +
                    'Penerbitan ' + sp + ' dilakukan karena ambang saldo tercapai dan sesuai tahapan pembinaan.' +
                    lastTxt + ' Rencana tindak lanjut: ' + rencana;

      $('#alasanSp').val(prefill);
      $('#penandatanganSelect').val('').trigger('change'); // wajib pilih BP
      $modal.modal('show');
    });

    $('#formSpAlasan').on('submit', function(e){
      e.preventDefault();
      var $modal   = $('#modalSpAlasan');
      var sp       = $modal.data('sp');
      var printUrl = $modal.data('print');
      var alasan   = $.trim($('#alasanSp').val());
      var bpUserId = parseInt($('#penandatanganSelect').val(),10) || 0;
      if (!alasan) { $('#alasanSp').focus(); return; }
      if (!bpUserId) { alert('Pilih Guru BP penandatangan.'); $('#penandatanganSelect').focus(); return; }

      // UX: cegah double submit
      var $btnSub = $(this).find('button[type=submit]').prop('disabled', true);
      var $btnClose = $(this).find('button[data-dismiss=modal]').prop('disabled', true);

      // Pre-open tab agar tidak diblokir
      var printWin = window.open('', '_blank');

      $.ajax({
        type: 'POST',
        url: '<?php echo htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, "UTF-8"); ?>?ajax=issue_sp',
        data: {
          _csrf: window.EPOIN_CSRF || '',
          siswa_id: <?php echo (int)$id_siswa; ?>,
          sp_level: sp,
          alasan: alasan,
          bp_user_id: bpUserId,
          sekolah_id: <?php echo (int)$SEKOLAH_ID; ?>
        },
        dataType: 'json',
        cache: false
      }).done(function(resp){
        if (resp && resp.ok) {
          if (printWin) { printWin.location = (resp.print_url || printUrl); }
          $('#alasanSp').val(''); $('#penandatanganSelect').val('').trigger('change');
          $modal.modal('hide');
        } else {
          alert(resp && resp.msg ? resp.msg : 'Gagal menerbitkan SP.');
          if (printWin) try { printWin.close(); } catch(e){}
        }
      }).fail(function(xhr, status, err){
        var msg = 'Gagal terhubung ke server.';
        if (status === 'parsererror' && xhr && xhr.responseText) {
          msg += '\n(respon bukan JSON valid)\n\nCuplikan:\n' + xhr.responseText.slice(0, 300);
        } else if (xhr && xhr.status) {
          msg += ' (HTTP ' + xhr.status + ' ' + (xhr.statusText||'') + ')';
        }
        alert(msg);
        if (window.console) {
          console.error('AJAX issue_sp fail:', {status: status, err: err, http: xhr && xhr.status, resp: xhr && xhr.responseText});
        }
        if (printWin) try { printWin.close(); } catch(e){}
      }).always(function(){
        $btnSub.prop('disabled', false);
        $btnClose.prop('disabled', false);
      });
    });
  });
</script>
