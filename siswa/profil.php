<?php
$PAGE_TITLE = 'Profil Saya';
include 'header.php';

function h_p($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$id_siswa = (int)($_SESSION['id'] ?? 0);

/* =========================================================
   HANDLER: Ganti Password (tab Keamanan) — bcrypt + prepared
   Diport dari gantipassword.php (selaras periksa_login.php)
   ========================================================= */
$pwErr = $pwOk = null;
$activeTab = 'profil';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'change_password')) {
  $activeTab = 'keamanan';
  $old     = (string)($_POST['old_password'] ?? '');
  $new     = (string)($_POST['new_password'] ?? '');
  $confirm = (string)($_POST['confirm_password'] ?? '');

  if ($id_siswa <= 0)        $pwErr = "Sesi tidak valid. Silakan login ulang.";
  elseif (strlen($new) < 6)  $pwErr = "Password baru minimal 6 karakter.";
  elseif ($new !== $confirm) $pwErr = "Konfirmasi password tidak cocok.";
  else {
    $hash = '';
    if ($stmt = mysqli_prepare($koneksi, "SELECT siswa_password FROM siswa WHERE siswa_id=? LIMIT 1")) {
      mysqli_stmt_bind_param($stmt, 'i', $id_siswa);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      $row = $res ? mysqli_fetch_assoc($res) : null;
      $hash = $row ? (string)$row['siswa_password'] : '';
      mysqli_stmt_close($stmt);
    }
    // Verifikasi: bcrypt (akun migrasi) atau MD5 legacy
    if (preg_match('/^\$2y\$\d{2}\$/', $hash)) {
      $verified = password_verify($old, $hash);
    } else {
      $verified = ($hash !== '' && hash_equals($hash, md5($old)));
    }
    if (!$verified) {
      $pwErr = "Password lama salah.";
    } else {
      $newHash = password_hash($new, PASSWORD_DEFAULT);
      if ($up = mysqli_prepare($koneksi, "UPDATE siswa SET siswa_password=? WHERE siswa_id=?")) {
        mysqli_stmt_bind_param($up, 'si', $newHash, $id_siswa);
        mysqli_stmt_execute($up);
        mysqli_stmt_close($up);
      }
      $pwOk = "Password berhasil diperbarui.";
    }
  }
}
if (($_GET['tab'] ?? '') === 'keamanan') $activeTab = 'keamanan';

/* ============== DATA PROFIL ============== */
$qs = mysqli_query($koneksi, "
    SELECT s.siswa_id, s.siswa_nama, s.siswa_nis, s.siswa_foto, j.jurusan_nama
    FROM siswa s
    LEFT JOIN jurusan j ON j.jurusan_id = s.siswa_jurusan
    WHERE s.siswa_id = {$id_siswa} LIMIT 1
");
$p = mysqli_fetch_assoc($qs) ?: [];

$qk = mysqli_query($koneksi, "
    SELECT k.kelas_nama
    FROM kelas_siswa ks JOIN kelas k ON k.kelas_id = ks.ks_kelas
    WHERE ks.ks_siswa = {$id_siswa}
    ORDER BY ks.ks_id DESC LIMIT 1
");
$kelas = mysqli_fetch_assoc($qk);

$foto_src = !empty($p['siswa_foto'])
    ? "../gambar/siswa/" . h_p($p['siswa_foto'])
    : "../gambar/sistem/user.png";

$initial = strtoupper(mb_substr($p['siswa_nama'] ?? 'S', 0, 1));

// Alert upload foto (dari profil_update.php)
$alert     = $_GET['alert'] ?? '';
$alert_map = [
    'ok'       => ['success', 'check-circle',        'Foto profil berhasil diperbarui.'],
    'nofile'   => ['warning', 'exclamation-triangle','Tidak ada file yang dipilih.'],
    'ext'      => ['danger',  'times-circle',         'Format file tidak didukung. Gunakan JPG, PNG, atau GIF.'],
    'size'     => ['danger',  'times-circle',         'Ukuran file melebihi batas 2 MB.'],
    'movefail' => ['danger',  'times-circle',         'Gagal menyimpan file. Coba lagi atau hubungi admin.'],
];
?>

<style>
  /* ===================== Animasi ===================== */
  .pf-fadein{opacity:0; transform:translateY(16px); animation:pfUp .55s cubic-bezier(.22,1,.36,1) forwards;}
  @keyframes pfUp{to{opacity:1; transform:none;}}
  .pf-d1{animation-delay:.05s}.pf-d2{animation-delay:.13s}.pf-d3{animation-delay:.21s}

  /* ===================== Hero banner ===================== */
  .pf-hero{
    position:relative; border-radius:20px; overflow:hidden; margin-bottom:18px;
    background:linear-gradient(135deg,#4f46e5 0%,#6366f1 45%,#0ea5e9 100%);
    box-shadow:0 18px 40px rgba(79,70,229,.28);
  }
  .pf-hero::before{content:""; position:absolute; top:-60px; right:-40px; width:240px; height:240px; border-radius:50%; background:rgba(255,255,255,.12);}
  .pf-hero::after{content:""; position:absolute; bottom:-80px; left:-30px; width:200px; height:200px; border-radius:50%; background:rgba(255,255,255,.08);}
  .pf-hero-in{position:relative; z-index:2; padding:26px 28px; display:flex; align-items:center; gap:22px; flex-wrap:wrap; color:#fff;}
  .pf-avatar{
    width:104px; height:104px; border-radius:50%; flex-shrink:0; position:relative;
    background:rgba(255,255,255,.2); border:4px solid rgba(255,255,255,.55);
    box-shadow:0 10px 26px rgba(0,0,0,.22); overflow:hidden;
    display:flex; align-items:center; justify-content:center;
    font-size:42px; font-weight:800; color:#fff;
  }
  .pf-avatar img{width:100%; height:100%; object-fit:cover; display:block}
  .pf-hero-txt{flex:1 1 260px; min-width:220px}
  .pf-hero-txt .role{
    display:inline-flex; align-items:center; gap:6px; font-size:11.5px; font-weight:800;
    text-transform:uppercase; letter-spacing:.6px; background:rgba(255,255,255,.22);
    padding:4px 12px; border-radius:999px; margin-bottom:8px;
  }
  .pf-hero-txt h2{margin:0 0 4px; font-weight:800; font-size:25px; line-height:1.15}
  .pf-chips{display:flex; gap:8px; flex-wrap:wrap; margin-top:10px}
  .pf-chip{
    display:inline-flex; align-items:center; gap:7px; font-size:12.5px; font-weight:700;
    background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.28);
    padding:6px 13px; border-radius:999px; backdrop-filter:blur(4px);
  }
  .pf-chip i{opacity:.9}

  /* ===================== Tabs ===================== */
  .pf-tabs{display:flex; gap:6px; background:#fff; padding:6px; border-radius:14px;
    box-shadow:0 6px 18px rgba(15,23,42,.06); margin-bottom:18px; flex-wrap:wrap}
  .pf-tab{
    flex:1 1 auto; text-align:center; cursor:pointer; border:0; background:transparent;
    padding:11px 16px; border-radius:10px; font-weight:700; font-size:14px; color:#64748b;
    display:inline-flex; align-items:center; justify-content:center; gap:8px;
    transition:all .2s ease;
  }
  .pf-tab:hover{color:#4f46e5; background:#f5f3ff}
  .pf-tab.active{color:#fff; background:linear-gradient(135deg,#4f46e5,#6366f1); box-shadow:0 6px 16px rgba(79,70,229,.3)}

  .pf-pane{display:none; animation:pfPane .35s ease}
  .pf-pane.active{display:block}
  @keyframes pfPane{from{opacity:0; transform:translateY(8px)} to{opacity:1; transform:none}}

  /* ===================== Cards ===================== */
  .pf-card{background:#fff; border:1px solid #eef0f4; border-radius:16px; overflow:hidden;
    box-shadow:0 8px 22px rgba(15,23,42,.06);}
  .pf-card-hd{padding:15px 20px; display:flex; align-items:center; gap:11px; color:#fff}
  .pf-hd-blue{background:linear-gradient(135deg,#3b82f6,#0ea5e9)}
  .pf-hd-green{background:linear-gradient(135deg,#10b981,#059669)}
  .pf-hd-amber{background:linear-gradient(135deg,#f59e0b,#d97706)}
  .pf-card-hd .ic{width:34px;height:34px;border-radius:10px;background:rgba(255,255,255,.22);
    display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:15px}
  .pf-card-hd h4{margin:0; font-size:15.5px; font-weight:800}
  .pf-card-bd{padding:22px}

  /* ===== Foto ===== */
  .pf-photo{text-align:center}
  .pf-photo-frame{
    position:relative; display:inline-block; width:150px; height:150px; border-radius:50%;
    overflow:hidden; cursor:pointer; border:5px solid #fff;
    box-shadow:0 8px 26px rgba(0,0,0,.16); background:#eef2ff;
  }
  .pf-photo-frame img{width:100%; height:100%; object-fit:cover; display:block; transition:transform .3s ease}
  .pf-photo-frame:hover img{transform:scale(1.07)}
  .pf-photo-ov{position:absolute; inset:0; background:rgba(79,70,229,.45); display:flex;
    flex-direction:column; gap:4px; align-items:center; justify-content:center; opacity:0; transition:opacity .2s}
  .pf-photo-frame:hover .pf-photo-ov{opacity:1}
  .pf-photo-ov i{color:#fff; font-size:24px}
  .pf-photo-ov span{color:#fff; font-size:11px; font-weight:700; letter-spacing:.3px}

  .pf-filechip{display:none; margin:14px auto 0; max-width:100%; font-size:13px; color:#475569;
    background:#f1f5f9; border:1px solid #e2e8f0; border-radius:999px; padding:7px 14px;
    align-items:center; gap:8px; width:max-content}
  .pf-filechip i{color:#4f46e5}
  .pf-hint{font-size:12px; color:#94a3b8; margin-top:12px}

  .pf-btn{display:inline-flex; align-items:center; gap:8px; border:0; cursor:pointer;
    font-weight:700; font-size:14px; padding:11px 20px; border-radius:999px;
    transition:transform .15s ease, box-shadow .15s ease, filter .15s ease; text-decoration:none}
  .pf-btn:active{transform:scale(.97)}
  .pf-btn-primary{background:linear-gradient(135deg,#4f46e5,#6366f1); color:#fff; box-shadow:0 8px 18px rgba(79,70,229,.3)}
  .pf-btn-primary:hover{filter:brightness(1.06); color:#fff}
  .pf-btn-success{background:linear-gradient(135deg,#10b981,#059669); color:#fff; box-shadow:0 8px 18px rgba(16,185,129,.3)}
  .pf-btn-success:hover{filter:brightness(1.06); color:#fff}
  .pf-btn-success:disabled{opacity:.5; cursor:not-allowed; box-shadow:none}
  .pf-btn-ghost{background:#f1f5f9; color:#475569; border:1px solid #e2e8f0}
  .pf-btn-ghost:hover{background:#e2e8f0; color:#334155}
  .pf-actions{display:flex; gap:10px; flex-wrap:wrap; justify-content:center; margin-top:18px}

  /* ===== Identitas ===== */
  .pf-idlist{list-style:none; padding:0; margin:0}
  .pf-idlist li{display:flex; align-items:center; gap:14px; padding:14px 0; border-bottom:1px solid #f1f5f9}
  .pf-idlist li:last-child{border-bottom:0}
  .pf-idico{width:42px; height:42px; border-radius:12px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:16px}
  .pf-ic-blue{background:#dbeafe; color:#2563eb}.pf-ic-green{background:#d1fae5; color:#059669}
  .pf-ic-amber{background:#fef3c7; color:#d97706}.pf-ic-violet{background:#ede9fe; color:#7c3aed}
  .pf-idlist .lbl{font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:#94a3b8; font-weight:700}
  .pf-idlist .val{font-size:15px; font-weight:700; color:#0f172a}
  .pf-idlist .val.none{color:#cbd5e1; font-style:italic; font-weight:500}
  .pf-note{margin-top:18px; padding:13px 16px; background:#f8fafc; border:1px solid #e2e8f0;
    border-radius:12px; font-size:13px; color:#64748b}
  .pf-note i{color:#3b82f6; margin-right:6px}

  /* ===== Alert ===== */
  .pf-alert{border-radius:12px; padding:13px 18px; display:flex; align-items:center; gap:11px;
    font-weight:600; border:0; margin-bottom:18px}
  .pf-alert .close{margin-left:auto; opacity:.6; padding:0 4px}

  /* ===================== Keamanan / Password ===================== */
  .pf-form-grp{margin-bottom:16px}
  .pf-form-grp label{font-size:12.5px; font-weight:700; color:#475569; margin-bottom:6px; display:block}
  .pf-input-wrap{position:relative}
  .pf-input-wrap input{width:100%; border:1px solid #e2e8f0; border-radius:12px; padding:12px 44px 12px 16px;
    font-size:14px; transition:box-shadow .2s, border-color .2s}
  .pf-input-wrap input:focus{outline:none; border-color:#6366f1; box-shadow:0 0 0 4px rgba(99,102,241,.14)}
  .pf-eye{position:absolute; right:8px; top:50%; transform:translateY(-50%); border:0; background:transparent;
    color:#94a3b8; cursor:pointer; padding:8px; border-radius:8px}
  .pf-eye:hover{color:#4f46e5; background:#f5f3ff}

  .pf-strength{margin-top:9px}
  .pf-strbar{height:8px; border-radius:999px; background:#e5e7eb; overflow:hidden}
  .pf-strbar > span{display:block; height:100%; width:0%; border-radius:999px; transition:width .35s ease, background .35s ease}
  .pf-strlabel{font-size:12px; margin-top:6px; font-weight:700}

  .pf-reqs{list-style:none; padding:0; margin:10px 0 0; display:grid; grid-template-columns:1fr 1fr; gap:7px}
  .pf-reqs li{font-size:12.5px; color:#94a3b8; display:flex; align-items:center; gap:7px}
  .pf-reqs li.ok{color:#059669}
  .pf-reqs li i{font-size:13px}

  .pf-match{display:none; margin-top:9px; align-items:center; gap:6px; font-size:12.5px; font-weight:700;
    padding:5px 12px; border-radius:999px; width:max-content}
  .pf-match.yes{display:inline-flex; background:#dcfce7; color:#047857}
  .pf-match.no{display:inline-flex; background:#fee2e2; color:#b91c1c}
  .pf-caps{display:none; font-size:12px; color:#b91c1c; margin-top:6px}
  .pf-caps.show{display:block}

  @media (max-width:768px){
    .pf-reqs{grid-template-columns:1fr}
    .pf-hero-in{padding:22px 20px; gap:16px}
    .pf-avatar{width:88px; height:88px; font-size:34px}
    .pf-hero-txt h2{font-size:21px}
    .pf-tab{flex:1 1 100%}
  }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <h1><i class="fas fa-id-card" style="color:#4f46e5;margin-right:8px"></i>Profil Saya</h1>
    <ol class="breadcrumb">
      <li><a href="index.php"><i class="fa fa-home"></i> Dashboard</a></li>
      <li class="active">Profil Saya</li>
    </ol>
  </section>

  <section class="content">

    <!-- ===== HERO ===== -->
    <div class="pf-hero pf-fadein">
      <div class="pf-hero-in">
        <div class="pf-avatar">
          <?php if (!empty($p['siswa_foto'])): ?>
            <img src="<?= $foto_src ?>" alt="Foto">
          <?php else: ?><?= $initial ?><?php endif; ?>
        </div>
        <div class="pf-hero-txt">
          <span class="role"><i class="fas fa-user-graduate"></i> Siswa</span>
          <h2><?= h_p($p['siswa_nama'] ?? 'Siswa') ?></h2>
          <div class="pf-chips">
            <span class="pf-chip"><i class="fas fa-id-badge"></i> NIS: <?= h_p($p['siswa_nis'] ?? '-') ?></span>
            <?php if (!empty($kelas['kelas_nama'])): ?>
              <span class="pf-chip"><i class="fas fa-school"></i> <?= h_p($kelas['kelas_nama']) ?></span>
            <?php endif; ?>
            <?php if (!empty($p['jurusan_nama'])): ?>
              <span class="pf-chip"><i class="fas fa-book-open"></i> <?= h_p($p['jurusan_nama']) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== TABS ===== -->
    <div class="pf-tabs pf-fadein pf-d1">
      <button type="button" class="pf-tab <?= $activeTab==='profil'?'active':'' ?>" data-tab="profil">
        <i class="fas fa-user"></i> Profil
      </button>
      <button type="button" class="pf-tab <?= $activeTab==='keamanan'?'active':'' ?>" data-tab="keamanan">
        <i class="fas fa-shield-halved"></i> Keamanan
      </button>
    </div>

    <!-- ===== PANE: PROFIL ===== -->
    <div class="pf-pane <?= $activeTab==='profil'?'active':'' ?>" id="pane-profil">

      <?php if ($alert && isset($alert_map[$alert])): [$at,$ai,$am] = $alert_map[$alert]; ?>
      <div class="alert alert-<?= $at ?> pf-alert pf-fadein">
        <i class="fas fa-<?= $ai ?>"></i><span><?= $am ?></span>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
      </div>
      <?php endif; ?>

      <div class="row">
        <!-- Foto -->
        <div class="col-md-5 col-sm-12" style="margin-bottom:18px">
          <div class="pf-card pf-fadein pf-d2">
            <div class="pf-card-hd pf-hd-blue">
              <div class="ic"><i class="fas fa-camera"></i></div>
              <h4>Foto Profil</h4>
            </div>
            <div class="pf-card-bd pf-photo">
              <form action="profil_update.php" method="post" enctype="multipart/form-data" id="formFoto">
                <div class="pf-photo-frame" onclick="document.getElementById('inputFoto').click()" title="Klik untuk ganti foto">
                  <img src="<?= $foto_src ?>" id="photoPreview" alt="Foto Profil">
                  <div class="pf-photo-ov"><i class="fas fa-camera"></i><span>Ganti Foto</span></div>
                </div>

                <input type="file" name="foto" id="inputFoto" accept="image/jpeg,image/png,image/gif" style="display:none" required>
                <div class="pf-filechip" id="fileChip"><i class="fas fa-image"></i><span id="fileName"></span></div>
                <div class="pf-hint">JPG / PNG / GIF &middot; Maks 2&nbsp;MB &middot; Rasio 1:1 dianjurkan</div>

                <div class="pf-actions">
                  <button type="button" class="pf-btn pf-btn-primary" onclick="document.getElementById('inputFoto').click()">
                    <i class="fas fa-image"></i> Pilih Gambar
                  </button>
                  <button type="submit" class="pf-btn pf-btn-success" id="btnSimpan" disabled>
                    <i class="fas fa-save"></i> Simpan Foto
                  </button>
                  <?php if (!empty($p['siswa_foto'])): ?>
                  <a href="profil_update.php?hapus=1" class="pf-btn pf-btn-ghost"
                     onclick="return confirm('Hapus foto profil saat ini?')">
                    <i class="fas fa-trash-alt"></i> Hapus
                  </a>
                  <?php endif; ?>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Identitas -->
        <div class="col-md-7 col-sm-12" style="margin-bottom:18px">
          <div class="pf-card pf-fadein pf-d3">
            <div class="pf-card-hd pf-hd-green">
              <div class="ic"><i class="fas fa-address-card"></i></div>
              <h4>Data Identitas</h4>
            </div>
            <div class="pf-card-bd">
              <ul class="pf-idlist">
                <li>
                  <div class="pf-idico pf-ic-blue"><i class="fas fa-user"></i></div>
                  <div><div class="lbl">Nama Lengkap</div>
                    <div class="val <?= empty($p['siswa_nama'])?'none':'' ?>"><?= !empty($p['siswa_nama']) ? h_p($p['siswa_nama']) : '—' ?></div></div>
                </li>
                <li>
                  <div class="pf-idico pf-ic-green"><i class="fas fa-id-badge"></i></div>
                  <div><div class="lbl">NIS</div>
                    <div class="val <?= empty($p['siswa_nis'])?'none':'' ?>"><?= !empty($p['siswa_nis']) ? h_p($p['siswa_nis']) : '—' ?></div></div>
                </li>
                <li>
                  <div class="pf-idico pf-ic-amber"><i class="fas fa-school"></i></div>
                  <div><div class="lbl">Kelas</div>
                    <div class="val <?= empty($kelas['kelas_nama'])?'none':'' ?>"><?= !empty($kelas['kelas_nama']) ? h_p($kelas['kelas_nama']) : 'Belum ada kelas' ?></div></div>
                </li>
                <li>
                  <div class="pf-idico pf-ic-violet"><i class="fas fa-book-open"></i></div>
                  <div><div class="lbl">Jurusan</div>
                    <div class="val <?= empty($p['jurusan_nama'])?'none':'' ?>"><?= !empty($p['jurusan_nama']) ? h_p($p['jurusan_nama']) : '—' ?></div></div>
                </li>
              </ul>
              <div class="pf-note">
                <i class="fas fa-info-circle"></i>Untuk mengubah data identitas, hubungi wali kelas atau admin sekolah.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div><!-- /pane profil -->

    <!-- ===== PANE: KEAMANAN ===== -->
    <div class="pf-pane <?= $activeTab==='keamanan'?'active':'' ?>" id="pane-keamanan">
      <div class="row">
        <div class="col-md-7 col-sm-12">
          <div class="pf-card pf-fadein">
            <div class="pf-card-hd pf-hd-amber">
              <div class="ic"><i class="fas fa-key"></i></div>
              <h4>Ubah Password</h4>
            </div>
            <div class="pf-card-bd">

              <?php if ($pwErr): ?>
              <div class="alert alert-danger pf-alert"><i class="fas fa-times-circle"></i><span><?= h_p($pwErr) ?></span></div>
              <?php endif; ?>
              <?php if ($pwOk): ?>
              <div class="alert alert-success pf-alert"><i class="fas fa-check-circle"></i><span><?= h_p($pwOk) ?></span></div>
              <?php endif; ?>

              <p style="color:#64748b; font-size:13.5px; margin-top:0">Jaga keamanan akun dengan password yang kuat dan unik.</p>

              <form method="post" autocomplete="off" id="formPw">
                <input type="hidden" name="action" value="change_password">

                <div class="pf-form-grp">
                  <label>Password Lama</label>
                  <div class="pf-input-wrap">
                    <input type="password" id="old_password" name="old_password" required>
                    <button type="button" class="pf-eye" data-target="old_password"><i class="fas fa-eye"></i></button>
                  </div>
                  <div class="pf-caps" id="capsOld"><i class="fas fa-exclamation-triangle"></i> Caps Lock aktif</div>
                </div>

                <div class="pf-form-grp">
                  <label>Password Baru</label>
                  <div class="pf-input-wrap">
                    <input type="password" id="new_password" name="new_password" required minlength="6">
                    <button type="button" class="pf-eye" data-target="new_password"><i class="fas fa-eye"></i></button>
                  </div>
                  <div class="pf-strength">
                    <div class="pf-strbar"><span id="strBar"></span></div>
                    <div class="pf-strlabel" id="strLabel" style="color:#94a3b8">Kekuatan: -</div>
                  </div>
                  <ul class="pf-reqs" id="reqs">
                    <li id="rLen"><i class="far fa-circle"></i> Minimal 6 karakter</li>
                    <li id="rUpper"><i class="far fa-circle"></i> Ada huruf besar</li>
                    <li id="rNum"><i class="far fa-circle"></i> Ada angka</li>
                    <li id="rSym"><i class="far fa-circle"></i> Ada simbol (!@#&hellip;)</li>
                  </ul>
                  <div class="pf-caps" id="capsNew"><i class="fas fa-exclamation-triangle"></i> Caps Lock aktif</div>
                </div>

                <div class="pf-form-grp">
                  <label>Konfirmasi Password Baru</label>
                  <div class="pf-input-wrap">
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <button type="button" class="pf-eye" data-target="confirm_password"><i class="fas fa-eye"></i></button>
                  </div>
                  <span class="pf-match" id="matchBadge"></span>
                  <div class="pf-caps" id="capsConfirm"><i class="fas fa-exclamation-triangle"></i> Caps Lock aktif</div>
                </div>

                <button type="submit" class="pf-btn pf-btn-primary" style="margin-top:4px">
                  <i class="fas fa-save"></i> Simpan Password
                </button>
              </form>
            </div>
          </div>
        </div>

        <div class="col-md-5 col-sm-12">
          <div class="pf-card pf-fadein pf-d2">
            <div class="pf-card-hd pf-hd-blue">
              <div class="ic"><i class="fas fa-lightbulb"></i></div>
              <h4>Tips Keamanan</h4>
            </div>
            <div class="pf-card-bd">
              <ul style="padding-left:18px; margin:0; color:#475569; font-size:13.5px; line-height:2">
                <li>Gunakan minimal 8 karakter campuran huruf, angka & simbol.</li>
                <li>Jangan pakai tanggal lahir atau NIS sebagai password.</li>
                <li>Jangan bagikan password ke siapa pun, termasuk teman.</li>
                <li>Ganti password secara berkala.</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div><!-- /pane keamanan -->

  </section>
</div>

<script>
(function(){
  /* ---------- Tabs ---------- */
  var tabs  = document.querySelectorAll('.pf-tab');
  var panes = {profil:document.getElementById('pane-profil'), keamanan:document.getElementById('pane-keamanan')};
  function activate(name){
    tabs.forEach(function(t){ t.classList.toggle('active', t.getAttribute('data-tab')===name); });
    for (var k in panes){ if(panes[k]) panes[k].classList.toggle('active', k===name); }
    if (history.replaceState) history.replaceState(null,'', '?tab='+name);
  }
  tabs.forEach(function(t){ t.addEventListener('click', function(){ activate(this.getAttribute('data-tab')); }); });
  if (location.hash === '#keamanan') activate('keamanan');

  /* ---------- Foto preview ---------- */
  var input = document.getElementById('inputFoto');
  if (input){
    input.addEventListener('change', function(){
      var f = this.files[0]; if(!f) return;
      document.getElementById('fileName').textContent = f.name;
      document.getElementById('fileChip').style.display = 'inline-flex';
      document.getElementById('btnSimpan').disabled = false;
      var rd = new FileReader();
      rd.onload = function(e){ document.getElementById('photoPreview').src = e.target.result; };
      rd.readAsDataURL(f);
    });
  }

  /* ---------- Show/hide password ---------- */
  document.querySelectorAll('.pf-eye').forEach(function(b){
    b.addEventListener('click', function(){
      var el = document.getElementById(this.getAttribute('data-target'));
      var ic = this.querySelector('i');
      if (el.type === 'password'){ el.type='text'; ic.className='fas fa-eye-slash'; }
      else { el.type='password'; ic.className='fas fa-eye'; }
    });
  });

  /* ---------- Strength meter ---------- */
  var COLORS = {weak:'#ef4444', fair:'#f59e0b', good:'#10b981', strong:'#16a34a'};
  function score(pw){
    var s=0;
    if(pw.length>=6) s++; if(/[A-Z]/.test(pw)) s++; if(/\d/.test(pw)) s++;
    if(/[^A-Za-z0-9]/.test(pw)) s++; if(pw.length>=10) s++;
    return Math.min(s,4);
  }
  function setReq(id, ok){
    var li=document.getElementById(id); if(!li) return;
    li.classList.toggle('ok', ok);
    li.querySelector('i').className = ok ? 'fas fa-check-circle' : 'far fa-circle';
  }
  function updateStrength(pw){
    var s=score(pw), pct=[0,25,50,75,100][s];
    var bar=document.getElementById('strBar'), lbl=document.getElementById('strLabel');
    var key = s<=1?'weak':(s===2?'fair':(s===3?'good':'strong'));
    var txt = {weak:'Lemah',fair:'Cukup',good:'Baik',strong:'Sangat Baik'}[key];
    bar.style.width=pct+'%'; bar.style.background=COLORS[key];
    lbl.textContent='Kekuatan: '+(pw?txt:'-'); lbl.style.color = pw?COLORS[key]:'#94a3b8';
    setReq('rLen', pw.length>=6); setReq('rUpper', /[A-Z]/.test(pw));
    setReq('rNum', /\d/.test(pw)); setReq('rSym', /[^A-Za-z0-9]/.test(pw));
  }
  function updateMatch(){
    var a=document.getElementById('new_password').value, b=document.getElementById('confirm_password').value;
    var m=document.getElementById('matchBadge');
    if(!a||!b){ m.className='pf-match'; m.innerHTML=''; return; }
    if(a===b){ m.className='pf-match yes'; m.innerHTML='<i class="fas fa-check"></i> Cocok'; }
    else { m.className='pf-match no'; m.innerHTML='<i class="fas fa-times"></i> Belum cocok'; }
  }
  var np=document.getElementById('new_password'), cp=document.getElementById('confirm_password');
  if(np) np.addEventListener('input', function(){ updateStrength(this.value); updateMatch(); });
  if(cp) cp.addEventListener('input', updateMatch);

  /* ---------- Caps Lock ---------- */
  function caps(inputId, warnId){
    var i=document.getElementById(inputId), w=document.getElementById(warnId);
    if(!i||!w) return;
    i.addEventListener('keyup', function(e){
      if(e.getModifierState && e.getModifierState('CapsLock')) w.classList.add('show'); else w.classList.remove('show');
    });
    i.addEventListener('blur', function(){ w.classList.remove('show'); });
  }
  caps('old_password','capsOld'); caps('new_password','capsNew'); caps('confirm_password','capsConfirm');
})();
</script>

<?php include 'footer.php'; ?>
