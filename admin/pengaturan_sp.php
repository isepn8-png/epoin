<?php
// admin/pengaturan_sp.php — Pengaturan ambang Surat Peringatan (SP) yang fleksibel.
// Admin mengatur: skala maksimal (mis. 100/200/...) & jumlah level SP.
// Ambang tiap level dihitung proporsional oleh helper epoin_sp_thresholds().

require_once __DIR__ . '/../includes/epoin_security.php';
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/epoin_sp_helpers.php';

// Hanya administrator/superadmin (sebelum output apa pun).
epoin_staff_guard(true);

// ===== Simpan (POST) =====
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!epoin_csrf_validate($_POST)) {
        epoin_csrf_fail_redirect('pengaturan_sp.php');
    }
    $skala = (int) ($_POST['sp_skala_max'] ?? 100);
    $jml   = (int) ($_POST['sp_jumlah_level'] ?? 4);

    // Sanity (samakan dengan clamp di epoin_sp_config()).
    if ($jml < 1)  { $jml = 1; }
    if ($jml > 12) { $jml = 12; }
    if ($skala < ($jml + 1)) { $skala = $jml + 1; }

    $stmt = mysqli_prepare(
        $koneksi,
        "INSERT INTO app_meta (meta_key, meta_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)"
    );
    if ($stmt) {
        foreach ([['sp_skala_max', (string) $skala], ['sp_jumlah_level', (string) $jml]] as $kv) {
            mysqli_stmt_bind_param($stmt, 'ss', $kv[0], $kv[1]);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
        $_SESSION['flash_success'] = "Ambang SP disimpan: skala maksimal $skala poin, $jml level.";
    } else {
        epoin_flash_error('Gagal menyimpan (tabel app_meta belum ada? Jalankan migrasi 2026-07-10-001-sp-config.sql).');
    }
    header('Location: pengaturan_sp.php');
    exit;
}

// ===== Data untuk tampilan =====
$cfg = epoin_sp_config($koneksi);
$thr = epoin_sp_thresholds($koneksi);
$stages = epoin_sp_stages($koneksi);

include 'header.php';
?>
<div class="content-wrapper">

  <section class="content-header">
    <h1>
      <i class="fa fa-sliders"></i> Pengaturan Ambang SP
      <small>Surat Peringatan</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="index.php"><i class="fa fa-home"></i> Home</a></li>
      <li class="active">Ambang SP</li>
    </ol>
  </section>

  <section class="content">
  <div class="row">
    <div class="col-lg-10 col-md-12">

      <?php epoin_flash_render(); ?>

      <div class="box" style="border-radius:14px;overflow:hidden;">
        <div class="box-header with-border">
          <h3 class="box-title" style="font-weight:800;">
            <i class="fa fa-sliders"></i> Pengaturan Ambang Surat Peringatan (SP)
          </h3>
        </div>
        <div class="box-body">
          <p style="color:#475569;margin-bottom:14px;">
            Ambang SP dihitung <b>proporsional</b> dari skala maksimal &amp; jumlah level.
            Rumus: <code>band = skala ÷ (jumlah level + 1)</code>, lalu
            <code>ambang SPk = floor(k × band) + 1</code>. Ubah angka di bawah lalu simpan;
            seluruh modul (penerbitan SP, cetak surat, portal siswa) langsung mengikuti.
          </p>

          <form method="post" action="pengaturan_sp.php" style="max-width:520px;">
            <?= epoin_csrf_field() ?>

            <div class="form-group">
              <label style="font-weight:700;">Skala poin negatif maksimal</label>
              <input type="number" name="sp_skala_max" class="form-control" min="2" max="100000"
                     value="<?= epoin_h((string) $cfg['skala_max']) ?>" required>
              <small style="color:#64748b;">Contoh: 100 (default) atau 200 — batas atas (pemulangan / SP tertinggi).</small>
            </div>

            <div class="form-group">
              <label style="font-weight:700;">Jumlah level SP</label>
              <input type="number" name="sp_jumlah_level" class="form-control" min="1" max="12"
                     value="<?= epoin_h((string) $cfg['jumlah_level']) ?>" required>
              <small style="color:#64748b;">Jumlah level <b>peringatan</b> (default 4 = SP1–SP4). Level <b>pemulangan</b> SP<?php echo (int)$cfg['jumlah_level']+1; ?> (dikembalikan ke orang tua) otomatis di skala maksimal.</small>
            </div>

            <button type="submit" class="btn btn-primary" style="font-weight:700;">
              <i class="fa fa-save"></i> Simpan
            </button>
          </form>
        </div>
      </div>

      <div class="box" style="border-radius:14px;overflow:hidden;">
        <div class="box-header with-border">
          <h3 class="box-title" style="font-weight:800;">
            <i class="fa fa-list-ol"></i> Pratinjau ambang saat ini
            (skala <?= (int) $cfg['skala_max'] ?>, <?= (int) $cfg['jumlah_level'] ?> level)
          </h3>
        </div>
        <div class="box-body">
          <table class="table table-bordered" style="max-width:640px;">
            <thead><tr><th>Tahap</th><th>Rentang poin negatif</th><th>Tindakan</th></tr></thead>
            <tbody>
              <?php foreach ($stages as $st): ?>
                <tr>
                  <td><b><?= epoin_h((string) $st['roman']) ?></b><?= $st['sp'] ? ' — ' . epoin_h((string) $st['sp']) : '' ?></td>
                  <td><?= (int) $st['min'] ?><?= ((int) $st['max'] >= 999999) ? '+' : (' – ' . (int) $st['max']) ?></td>
                  <td><?= epoin_h((string) $st['action']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <p style="color:#64748b;margin-top:8px;">
            Ambang terbit:
            <?php $parts = []; foreach ($thr as $lvl => $min) { $parts[] = epoin_h($lvl) . ' ≥ ' . (int) $min; }
                  echo implode(' &nbsp;·&nbsp; ', $parts); ?>
          </p>
        </div>
      </div>

    </div>
  </div>
  </section>

</div><!-- /.content-wrapper -->
<?php include 'footer.php'; ?>
