<?php
include 'header.php';

/* =================== Guard koneksi & session =================== */
if (!isset($koneksi)) { die('Koneksi database ($koneksi) tidak ditemukan. Pastikan header.php men-define $koneksi.'); }
if (session_status() === PHP_SESSION_NONE) { session_start(); }

/* =================== CSRF untuk aksi tulis =================== */
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf_token'];

/* =================== Helper =================== */
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

/* =================== Filter TA =================== */
$filter_ta = isset($_GET['ta']) ? (int)$_GET['ta'] : 0;

/* =================== Daftar TA =================== */
$ta_rows = [];
if ($resTa = mysqli_query($koneksi, "SELECT ta_id, ta_nama, ta_status FROM ta ORDER BY ta_id DESC")) {
  while ($r = mysqli_fetch_assoc($resTa)) { $ta_rows[] = $r; }
}

/* =================== Data Kelas + Wali (LEFT JOIN) ===================
   - Hitung siswa via subquery
   - LEFT JOIN kelas_wali (per TA+Kelas) + user untuk nama wali
*/
$kelas_rows = [];
if ($filter_ta > 0) {
  $sql = "SELECT
            k.kelas_id, k.kelas_nama,
            j.jurusan_nama,
            t.ta_id, t.ta_nama,
            (SELECT COUNT(*) FROM kelas_siswa ks WHERE ks.ks_kelas = k.kelas_id) AS jumlah_siswa,
            u.user_nama AS wali_nama,
            COALESCE(kw.wali_nip, u.user_username) AS wali_nip
          FROM kelas k
          JOIN jurusan j ON k.kelas_jurusan = j.jurusan_id
          JOIN ta t      ON k.kelas_ta      = t.ta_id
          LEFT JOIN kelas_wali kw ON kw.kelas_id = k.kelas_id AND kw.ta_id = t.ta_id
          LEFT JOIN user u        ON u.user_id    = kw.wali_user_id
          WHERE t.ta_id = ?
          ORDER BY k.kelas_nama ASC";
  if ($stmt = mysqli_prepare($koneksi, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $filter_ta);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) { $kelas_rows[] = $r; }
    mysqli_stmt_close($stmt);
  }
} else {
  $sql = "SELECT
            k.kelas_id, k.kelas_nama,
            j.jurusan_nama,
            t.ta_id, t.ta_nama,
            (SELECT COUNT(*) FROM kelas_siswa ks WHERE ks.ks_kelas = k.kelas_id) AS jumlah_siswa,
            u.user_nama AS wali_nama,
            COALESCE(kw.wali_nip, u.user_username) AS wali_nip
          FROM kelas k
          JOIN jurusan j ON k.kelas_jurusan = j.jurusan_id
          JOIN ta t      ON k.kelas_ta      = t.ta_id
          LEFT JOIN kelas_wali kw ON kw.kelas_id = k.kelas_id AND kw.ta_id = t.ta_id
          LEFT JOIN user u        ON u.user_id    = kw.wali_user_id
          WHERE t.ta_status = '1'
          ORDER BY k.kelas_nama ASC";
  if ($res = mysqli_query($koneksi, $sql)) {
    while ($r = mysqli_fetch_assoc($res)) { $kelas_rows[] = $r; }
  }
}

/* =================== Daftar Guru untuk Modal Wali ===================
   Ambil guru dari roles.role_key='guru' atau fallback user_level='guru'
*/
$guru_rows = [];
$guru_sql = "
  SELECT u.user_id   AS guru_id,
         u.user_nama AS guru_nama,
         u.user_username AS guru_nip
  FROM user u
  LEFT JOIN user_roles ur ON ur.user_id = u.user_id
  LEFT JOIN roles r       ON r.role_id  = ur.role_id
  WHERE (r.role_key = 'guru' OR u.user_level = 'guru')
  GROUP BY u.user_id
  ORDER BY u.user_nama ASC";
if ($resG = mysqli_query($koneksi, $guru_sql)) {
  while ($g = mysqli_fetch_assoc($resG)) { $guru_rows[] = $g; }
}
?>

<style>
  /* ================= THEME: Soft Blue Dashboard ================= */
  :root{
    --bg-page:    #f5f9ff;
    --bg-card:    #ffffff;
    --bg-row:     #eef5ff;
    --bg-hover:   #e8f2ff;
    --border:     #dbeafe;

    --head-txt:   #0f2a56;
    --body-txt:   #000000;

    --accent-1:   #3b82f6;
    --accent-2:   #1d4ed8;
    --accent-3:   #93c5fd;

    --btn-siswa:  #2563eb;
    --btn-wali:   #0ea5e9;
    --btn-edit:   #f59e0b;
    --btn-del:    #ef4444;

    --wali-bg:    #e0f2fe;
    --wali-txt:   #0c4a6e;

    --glow:       0 10px 30px rgba(59,130,246,.25);
    --glow-soft:  0 6px 18px rgba(59,130,246,.18);
    --card-shadow:0 8px 22px rgba(15,42,86,.08);
  }

  .content-wrapper{
    background:
      radial-gradient(1200px 420px at 80% -50%, rgba(147,197,253,.25), transparent 60%),
      radial-gradient(900px 360px at -10% 10%, rgba(191,219,254,.25), transparent 60%),
      var(--bg-page);
    min-height: 100vh;
  }

  /* ===== Header / Title Area ===== */
  .content-header{
    border-bottom: 1px solid var(--border);
    padding-bottom: 10px;
    margin-bottom: 8px;
  }
  .content-header h1{
    color:#000000;
    font-weight: 800;
    display:flex; align-items:center; gap:12px;
    letter-spacing:.2px;
    opacity:0; transform: translateY(6px);
    animation: textFade .6s ease-out .05s forwards;
  }
  .title-icon{
    display:inline-flex; align-items:center; justify-content:center;
    width:40px; height:40px; border-radius:12px;
    background: linear-gradient(135deg, #e0ecff, #f0f7ff);
    color:#1e3a8a; box-shadow: var(--glow-soft);
  }

  /* === Badge "Panel Kendali" === */
  .title-badge{
    display:inline-flex; align-items:center; gap:6px;
    background:linear-gradient(90deg,#2563eb,#1d4ed8);
    color:#ffffff;
    border-radius:999px;
    padding:3px 10px;
    font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
    font-weight: 700;
    line-height: 1;
    font-size: clamp(8px, 1.6vw, 10px);
    border:0;
    box-shadow: 0 4px 12px rgba(29,78,216,.25);
    transform: translateY(-1px);
  }
  .title-badge i{ font-size: 12px; }
  .content-header > h1 > small.title-badge{ font-size: 10px !important; padding: 3px 10px !important; line-height: 1 !important; }
  .content-header > h1 > small.title-badge i{ font-size: 11px !important; }

  .breadcrumb > li + li:before { content: "› "; color:#64748b; }
  .breadcrumb > li > a, .breadcrumb > .active{ color:#475569; opacity:0; transform: translateY(4px); animation: textFade .5s ease-out .12s forwards; }

  /* ===== Box / Card ===== */
  .box{ border-top:0; box-shadow: var(--card-shadow); border:1px solid var(--border); border-radius:14px; overflow:hidden; }
  .box-header{ background: linear-gradient(180deg, #f7fbff 0%, #ffffff 100%); color: var(--head-txt); border-bottom: 1px solid var(--border); padding: 14px 15px; }
  .box-header .box-title{ opacity:0; transform: translateY(6px); animation: textFade .6s ease-out .14s forwards; }
  .box-header .box-title i{ margin-right:8px; color:#2563eb; }

  /* ===== Tombol Tambah ===== */
  .btn-add{ background: linear-gradient(90deg, var(--accent-1), var(--accent-2)); color:#fff; border:0; border-radius:12px; padding:10px 16px; display:inline-flex; align-items:center; gap:8px; box-shadow: var(--glow); position: relative; overflow: hidden; transition: transform .08s ease, filter .15s ease; }
  .btn-add:hover{ filter: brightness(1.06); transform: translateY(-1px); }
  .btn-add:active{ transform: translateY(0); }

  /* ===== Tabel (teks hitam) ===== */
  .table-compact > thead > tr > th,
  .table-compact > tbody > tr > td{ padding:10px 12px !important; vertical-align: middle; white-space: nowrap; }
  #table-datatable{ table-layout:auto; border-color:var(--border); background:#fff; }
  #table-datatable thead th{ background: linear-gradient(180deg,#f0f6ff 0%, #e8f2ff 100%); color: #0f2a56; border-bottom: 1px solid var(--border) !important; text-transform: none; font-weight: 600; }
  #table-datatable tbody tr:nth-child(odd){ background: var(--bg-card); }
  #table-datatable tbody tr:nth-child(even){ background: var(--bg-row); }
  #table-datatable tbody tr{ transition: background-color .15s ease, transform .06s ease; }
  #table-datatable tbody tr:hover{ background: var(--bg-hover) !important; cursor: pointer; }
  #table-datatable tbody td{ color: #000000 !important; }

  .pill-total{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; background:linear-gradient(90deg,#eaf2ff,#dbeafe); color:#1e3a8a; font-size:12px; border:1px solid var(--border); opacity:0; transform: translateY(6px); animation: textFade .6s ease-out .18s forwards; }

  /* ===== Wali tag ===== */
  .wali-badge{ background: var(--wali-bg); color: var(--wali-txt); border-radius: 999px; padding:5px 10px; display:inline-flex; align-items:center; gap:6px; font-size:12px; border:1px solid #bae6fd; }
  .wali-badge i{ color:#0284c7; }
  .wali-empty{ background:#ffffff; color:#64748b; border:1px dashed var(--border); border-radius: 10px; padding:5px 10px; font-size:12px; }

  /* ===== Action group ===== */
  .kelas-actions .btn{ height:36px; padding:6px 12px; border-radius:0; display:inline-flex; align-items:center; justify-content:center; gap:6px; border:0; box-shadow: 0 2px 8px rgba(15,42,86,.10); transition: transform .06s ease, filter .15s ease, box-shadow .2s ease; color:#fff; position: relative; }
  .kelas-actions .btn:hover{ transform: translateY(-1px); filter: brightness(1.03); box-shadow: 0 6px 14px rgba(15,42,86,.12); }
  .btn-siswa{ background: var(--btn-siswa); }
  .btn-wali { background: var(--btn-wali);  }
  .btn-edit{ background: var(--btn-edit);  }
  .btn-del { background: var(--btn-del);   }

  /* === Tooltip kustom === */
  .has-tip::after{ content: attr(data-tip); position: absolute; bottom: 100%; left: 50%; transform: translate(-50%, -6px); background: rgba(17,24,39,.95); color:#fff; padding:6px 8px; font-size:12px; line-height:1; border-radius:4px; white-space:nowrap; box-shadow: 0 6px 14px rgba(0,0,0,.18); opacity:0; pointer-events:none; transition: opacity .15s ease, transform .15s ease; z-index: 10; }
  .has-tip::before{ content:""; position:absolute; bottom: calc(100% - 2px); left:50%; transform: translateX(-50%); border:6px solid transparent; border-top-color: rgba(17,24,39,.95); opacity:0; transition: opacity .15s ease; z-index: 9; }
  .has-tip:hover::after, .has-tip:focus::after, .has-tip.show-tip::after, .has-tip:hover::before, .has-tip:focus::before, .has-tip.show-tip::before{ opacity:1; transform: translate(-50%, -10px); }

  /* ===== Select & input focus ===== */
  .form-control:focus{ border-color: #93c5fd; box-shadow: 0 0 0 3px rgba(147,197,253,.35); }
  .select2-container .select2-selection--single { height: 38px; border:1px solid var(--border) !important; border-radius:10px !important; }
  .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; color:#0f172a; }
  .select2-container--default .select2-selection--single .select2-selection__arrow { height: 38px; }
  .select2-dropdown{ border:1px solid var(--border) !important; box-shadow: var(--card-shadow) !important; }

  /* ===== Modal header ===== */
  .modal-header{ background: linear-gradient(180deg,#f0f6ff 0%, #ffffff 100%) !important; border-bottom:1px solid var(--border) !important; }
  .modal-title{ color:#0f2a56; }

  /* ===== Kolom lebar tetap ===== */
  th.th-no{ width: 1%; }
  th.th-opsi{ width: 26%; }

  /* ===== Badge kecil judul modal ===== */
  #modal_wali_label strong{ background: #eaf2ff; color:#1e3a8a; border:1px solid var(--border); padding:2px 8px; border-radius:999px; font-weight:600; }

  /* ===== Panel Tahun Ajaran ===== */
  .box-filter{ position: relative; background: radial-gradient(600px 120px at 20% -10%, rgba(59,130,246,.18), transparent 60%), radial-gradient(500px 140px at 110% 10%, rgba(29,78,216,.12), transparent 60%), #ffffff; border:1px solid var(--border); box-shadow: 0 10px 26px rgba(37,99,235,.10); overflow: hidden; }
  .box-filter::before{ content:""; position:absolute; inset:0 0 auto 0; height:6px; background: linear-gradient(90deg,#60a5fa,#2563eb,#1d4ed8,#60a5fa); background-size: 200% 100%; animation: ribbonMove 8s linear infinite; }
  .box-filter .box-body{ padding-top:18px; }
  .box-filter label{ font-weight:700; color:#0f2a56; display:flex; align-items:center; gap:8px; }
  .box-filter label::before{ content:"\f133"; font-family:"FontAwesome"; color:#1d4ed8; width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; background:#e0eaff; border-radius:6px; border:1px solid #c7d2fe; }
  .box-filter select.form-control{ border:1px solid #bfdbfe; border-radius:12px; background:#ffffff; transition: box-shadow .2s ease, transform .06s ease; }
  .box-filter select.form-control:hover{ box-shadow: 0 0 0 4px rgba(147,197,253,.25); }
  .box-filter select.form-control:focus{ border-color:#60a5fa !important; box-shadow: 0 0 0 4px rgba(96,165,250,.35); }
  .box-filter .btn.btn-default{ border-radius:10px; border:1px solid #bfdbfe; color:#1e3a8a; background: linear-gradient(180deg,#f8fbff 0%, #eef4ff 100%); box-shadow: 0 6px 16px rgba(37,99,235,.08); transition: transform .08s ease, filter .15s ease, box-shadow .2s ease; }
  .box-filter .btn.btn-default:hover{ filter: brightness(1.04); transform: translateY(-1px); box-shadow: 0 10px 22px rgba(37,99,235,.14); }
  .box-filter .btn.btn-default:active{ transform: translateY(0); }
  .box-filter.pulse{ animation: pulseGlow .6s ease-out; }

  /* ===== Animations ===== */
  @keyframes textFade{ from{ opacity:0; transform: translateY(6px); } to{ opacity:1; transform: translateY(0); } }
  @keyframes ribbonMove{ 0%{ background-position: 0% 0; } 100%{ background-position: 200% 0; } }
  @keyframes pulseGlow{ 0%{ box-shadow: 0 0 0 0 rgba(96,165,250,.45); } 70%{ box-shadow: 0 0 0 10px rgba(96,165,250,0); } 100%{ box-shadow: 0 0 0 0 rgba(96,165,250,0); } }

  /* ===== Mobile tweaks ===== */
  @media (max-width: 480px){
    .content-header h1{ gap:8px; }
    .title-badge{ font-size: 9px; padding: 3px 8px; transform:none; }
    .content-header > h1 > small.title-badge{ font-size: 9px !important; padding: 3px 8px !important; }
    .content-header > h1 > small.title-badge i{ font-size: 10px !important; }
    .pill-total{ margin-top:8px; display:block; }
    .kelas-actions{ display:flex; gap:6px; flex-wrap:wrap; }
    .kelas-actions .btn{ width:42px; height:42px; padding:0; border-radius:0; }
    .kelas-actions .btn span{ display:none !important; }
    th.th-opsi{ width: 40%; }
  }
</style>

<div class="content-wrapper">

  <section class="content-header">
    <h1>
      <span class="title-icon"><i class="fa fa-university"></i></span>
      Manajemen Kelas
      <small class="title-badge"><i class="fa fa-check-circle"></i> Panel Kendali</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Manajemen Kelas</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">

      <!-- Filter TA -->
      <section class="col-lg-4">
        <div class="box box-filter">
          <div class="box-body">
            <form method="get" action="">
              <div class="form-group">
                <label>Tahun Ajaran</label>
                <select class="form-control" name="ta" required>
                  <?php foreach ($ta_rows as $j): ?>
                    <option value="<?php echo (int)$j['ta_id']; ?>"
                      <?php echo ($filter_ta > 0 ? $filter_ta == (int)$j['ta_id'] : $j['ta_status'] === '1') ? 'selected' : ''; ?>>
                      <?php echo e($j['ta_nama']); ?> <?php echo ($j['ta_status'] === '1' ? '(Aktif)' : ''); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <input type="submit" class="btn btn-default" value="Filter">
              <?php if ($filter_ta > 0): ?>
                <a href="kelas.php" class="btn btn-default">Reset</a>
              <?php endif; ?>
            </form>
          </div>
        </div>
      </section>

      <section class="col-lg-12">
        <div class="box">

          <div class="box-header">
            <h3 class="box-title"><i class="fa fa-list-ul"></i> Data Kelas</h3>
            <div class="pull-right" style="display:flex; gap:10px; align-items:center;">
              <span class="pill-total"><i class="fa fa-database"></i> Total Kelas: <?php echo count($kelas_rows); ?></span>
              <a href="kelas_tambah.php" class="btn btn-add">
                <i class="fa fa-plus"></i> Tambah Kelas
              </a>
            </div>
          </div>

          <!-- Modal: Tambah/Set Wali Kelas -->
          <div class="modal fade" id="modal_wali" tabindex="-1" role="dialog" aria-labelledby="modal_wali_label">
            <div class="modal-dialog" role="document">
              <div class="modal-content">
                <div class="modal-header" style="background:#f8fafc;color:#0f172a;border-bottom:1px solid var(--border);">
                  <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true" style="color:#0f172a;">&times;</span>
                  </button>
                  <h4 class="modal-title" id="modal_wali_label"><i class="fa fa-user-plus"></i> Tambah / Set Wali Kelas — <strong><?php /* akan diisi JS */ ?></strong></h4>
                </div>
                <div class="modal-body">
                  <form id="form-wali" action="kelas_wali_tambah.php" method="post" autocomplete="off" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                    <input type="hidden" name="kelas_id" id="wali_kelas_id" value="">
                    <input type="hidden" name="redirect" value="<?php echo e($_SERVER['REQUEST_URI']); ?>">

                    <div class="form-group">
                      <label>Pilih Guru (Wali Kelas)</label>
                      <!-- PATCH: hilangkan class 'select2' agar tidak auto-init oleh tema; ganti penanda menjadi 'js-wali-select' -->
                      <select class="form-control js-wali-select" name="wali_id" id="wali_id" required style="width:100%;">
                        <option value="">-- Pilih Guru --</option>
                        <?php foreach($guru_rows as $g): ?>
                          <option value="<?php echo (int)$g['guru_id']; ?>" data-nip="<?php echo e($g['guru_nip']); ?>">
                            <?php echo e($g['guru_nama']); ?> <?php echo $g['guru_nip'] ? ' • NIP: '.e($g['guru_nip']) : ''; ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <p class="help-block" style="color:#64748b;">Nama & NIP terisi otomatis dari data guru (tabel user).</p>
                    </div>

                    <div class="form-group">
                      <label>NIP (otomatis)</label>
                      <input type="text" class="form-control" name="wali_nip" id="wali_nip" readonly placeholder="NIP guru akan muncul di sini">
                    </div>

                    <div class="form-group">
                      <label>Catatan (opsional)</label>
                      <input type="text" class="form-control" name="wali_info" id="wali_info" maxlength="120" placeholder="Contoh: Nomor HP / keterangan lain">
                    </div>

                    <div class="form-group" style="display:flex; gap:8px;">
                      <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i> Batal</button>
                      <button type="submit" class="btn btn-add" style="padding:6px 14px;"><i class="fa fa-save"></i> Simpan</button>
                    </div>
                  </form>
                  <?php if(empty($guru_rows)): ?>
                    <div class="alert alert-warning" style="margin-top:10px;">
                      Data guru kosong. Tambahkan guru (user dengan role “guru”) terlebih dahulu.
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          <!-- /Modal -->

          <div class="box-body">
            <div class="table-responsive">
              <table class="table table-bordered table-hover table-compact" id="table-datatable" style="width:100%; border-color:var(--border);">
                <thead>
                  <tr>
                    <th class="th-no">No</th>
                    <th>Nama Kelas</th>
                    <th>Tingkat Kelas</th>
                    <th class="hidden-xs">Tahun Ajaran</th>
                    <th>Jumlah Siswa</th>
                    <th>Wali Kelas</th>
                    <th class="th-opsi" data-orderable="false">Opsi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $no=1; foreach ($kelas_rows as $d): ?>
                  <tr>
                    <td><?php echo $no++; ?></td>
                    <td><?php echo e($d['kelas_nama']); ?></td>
                    <td><?php echo e($d['jurusan_nama']); ?></td>
                    <td class="hidden-xs"><?php echo e($d['ta_nama']); ?></td>
                    <td><?php echo (int)$d['jumlah_siswa']; ?></td>
                    <td>
                      <?php if(!empty($d['wali_nama'])): ?>
                        <span class="wali-badge" title="<?php echo e($d['wali_nip']); ?>">
                          <i class="fa fa-user"></i> <?php echo e($d['wali_nama']); ?>
                        </span>
                      <?php else: ?>
                        <span class="wali-empty" title="Belum ada wali untuk TA ini. Klik tombol Wali untuk menetapkan.">
                          <i class="fa fa-info-circle"></i> Belum dipilih
                        </span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="btn-group kelas-actions">
                        <a class="btn btn-siswa btn-sm has-tip" href="kelas_siswa.php?id=<?php echo (int)$d['kelas_id']; ?>" data-tip="Data Siswa" title="Data Siswa">
                          <i class="fa fa-users"></i> <span class="hidden-xs">Siswa</span>
                        </a>
                        <button class="btn btn-wali btn-sm btn-wali-open has-tip"
                                data-id="<?php echo (int)$d['kelas_id']; ?>"
                                data-nama="<?php echo e($d['kelas_nama']); ?>"
                                data-tip="Set Wali Kelas" title="Set Wali Kelas">
                          <i class="fa fa-user-plus"></i> <span class="hidden-xs">Wali</span>
                        </button>
                        <a class="btn btn-edit btn-sm has-tip" href="kelas_edit.php?id=<?php echo (int)$d['kelas_id']; ?>" data-tip="Edit Kelas" title="Edit Kelas">
                          <i class="fa fa-cog"></i> <span class="hidden-xs"></span>
                        </a>
                        <form class="eps-del-form" action="kelas_hapus.php" method="post" style="display:inline">
                          <?= epoin_csrf_field() ?>
                          <input type="hidden" name="id" value="<?php echo (int)$d['kelas_id']; ?>">
                          <button type="button" class="btn btn-del btn-sm btn-del-confirm has-tip"
                                  data-nama="<?php echo epoin_h($d['kelas_nama']); ?>"
                                  data-tip="Hapus Kelas" title="Hapus Kelas">
                            <i class="fa fa-trash"></i> <span class="hidden-xs"></span>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
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

<!-- Select2 (dropdown guru) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
(function(){
  // buka modal set wali
  $(document).on('click', '.btn-wali-open', function(){
    var id = $(this).data('id');
    var nama = $(this).data('nama');
    $('#wali_kelas_id').val(id);
    $('#modal_wali_label').html('<i class="fa fa-user-plus"></i> Tambah / Set Wali Kelas — <strong>'+ (nama || '') +'</strong>');
    $('#modal_wali').modal('show');
  });

  // ===== PATCH inti anti-double: destroy jika sudah terinit, lalu init SEKALI dengan dropdownParent
  $('#modal_wali').on('shown.bs.modal', function () {
    var $sel = $('#wali_id');
    if ($sel.hasClass('select2-hidden-accessible')) {
      try { $sel.select2('destroy'); } catch(e){}
      $sel.next('.select2-container').remove();
    }
    $sel.select2({
      dropdownParent: $('#modal_wali'),
      width: '100%',
      placeholder: '-- Pilih Guru --',
      allowClear: true
    });
  });

  // destroy saat ditutup (hindari reinit ganda saat buka ulang)
  $('#modal_wali').on('hidden.bs.modal', function () {
    var $sel = $('#wali_id');
    if ($sel.hasClass('select2-hidden-accessible')) {
      try { $sel.select2('destroy'); } catch(e){}
      $sel.next('.select2-container').remove();
    }
    $sel.val('').trigger('change');
    $('#wali_nip').val('');
  });

  // auto-isi NIP dari option terpilih
  $(document).on('change', '#wali_id', function(){
    var nip = $('#wali_id option:selected').data('nip') || '';
    $('#wali_nip').val(nip);
  });

  // validasi ringan
  $('#form-wali').on('submit', function(e){
    if (!$('#wali_id').val()){
      e.preventDefault();
      alert('Pilih guru terlebih dahulu.');
    }
  });

  // konfirmasi hapus
  $(document).on('click', '.btn-hapus', function(e){
    if(!confirm('Hapus kelas ini? Data terkait mungkin ikut terhapus.')) e.preventDefault();
  });

  // Interaksi kecil untuk Panel Tahun Ajaran: pulsa saat user memilih TA
  $('select[name="ta"]').on('change', function(){
    var $panel = $(this).closest('.box-filter');
    $panel.addClass('pulse');
    setTimeout(function(){ $panel.removeClass('pulse'); }, 600);
  });

  // Tooltip mobile
  document.querySelectorAll('.kelas-actions .has-tip').forEach(function(btn){
    btn.addEventListener('touchstart', function(){
      btn.classList.add('show-tip');
      setTimeout(function(){ btn.classList.remove('show-tip'); }, 1200);
    }, {passive:true});
  });
})();
</script>
