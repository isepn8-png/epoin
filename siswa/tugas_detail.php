<?php
/**
 * E-Tugas — Detail tugas & formulir pengumpulan (Phase 2).
 */
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/etugas_helpers.php';

$ctx = etugas_siswa_context($koneksi);
$siswaId = $ctx['siswa_id'];
$kelasInfo = $ctx['kelas'];
$tablesReady = $ctx['tables_ready'];

$etugasId = (int) ($_GET['id'] ?? 0);
if ($etugasId <= 0) {
    etugas_flash_redirect('tugas_saya.php', 'error', 'Tugas tidak ditemukan.');
}

$PAGE_TITLE = 'Detail Tugas';
include 'header.php';

$alert = etugas_alert_from_request();
$task = null;
$submission = null;
$enriched = null;
$formErrors = $_SESSION['etugas_sub_errors'] ?? [];
$formOld = $_SESSION['etugas_sub_old'] ?? [];
unset($_SESSION['etugas_sub_errors'], $_SESSION['etugas_sub_old']);

if ($tablesReady && $kelasInfo) {
    $task = etugas_fetch_task_for_siswa($koneksi, $etugasId, $siswaId, $kelasInfo);
    if ($task) {
        $submission = etugas_fetch_submission($koneksi, $etugasId, $siswaId);
        $enriched = etugas_enrich_siswa_task_row($task, $submission);
    }
}

$canSubmit = false;
$allowText = false;
$allowLink = false;
$latePolicy = '';
if ($task) {
    $canSubmit = $enriched['can_submit'] ?? false;
    $allowText = !empty($task['allow_text']);
    $allowLink = !empty($task['allow_link']);
    $latePolicy = !empty($task['izinkan_terlambat'])
        ? 'Pengumpulan terlambat diizinkan (akan ditandai terlambat).'
        : 'Pengumpulan terlambat tidak diizinkan setelah deadline.';
}
?>
<style>
.etugas-detail-page .info-card { border:1px solid #e2e8f0; border-radius:12px; padding:18px; margin-bottom:16px; background:#fff; }
.etugas-detail-page .instruksi { background:#f8fafc; border-left:4px solid #3b82f6; padding:12px 14px; border-radius:0 8px 8px 0; white-space:pre-wrap; }
.etugas-detail-page .sub-box { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:14px; margin-bottom:16px; }
.etugas-detail-page .form-section { border:1px solid #e2e8f0; border-radius:12px; padding:18px; background:#fff; }
.etugas-detail-page .help-block { font-size:12px; color:#64748b; }
</style>

<div class="content-wrapper etugas-detail-page">
  <section class="content-header">
    <h1>
      <i class="fas fa-file-alt" style="color:#2563eb;margin-right:6px"></i>
      Detail Tugas
    </h1>
    <ol class="breadcrumb">
      <li><a href="index.php"><i class="fa fa-home"></i> Beranda</a></li>
      <li><a href="tugas_saya.php">Tugas Saya</a></li>
      <li class="active">Detail</li>
    </ol>
  </section>

  <section class="content">
    <?php if ($alert): ?>
    <div class="alert alert-<?= $alert['type'] === 'success' ? 'success' : ($alert['type'] === 'error' ? 'danger' : 'info') ?> alert-dismissible">
      <button type="button" class="close" data-dismiss="alert" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
      <?= etugas_h($alert['msg']) ?>
    </div>
    <?php endif; ?>

    <?php if (!$tablesReady): ?>
    <div class="alert alert-warning">Modul tugas belum siap.</div>
    <?php elseif (!$kelasInfo): ?>
    <div class="alert alert-warning">Kelas aktif belum terdeteksi.</div>
    <?php elseif (!$task): ?>
    <div class="alert alert-danger" role="alert">
      Tugas tidak ditemukan atau Anda tidak memiliki akses.
    </div>
    <a href="tugas_saya.php" class="btn btn-default"><i class="fa fa-arrow-left"></i> Kembali</a>
    <?php else: ?>

    <div class="info-card">
      <h3 style="margin-top:0"><?= etugas_h($task['judul']) ?></h3>
      <p class="text-muted" style="margin-bottom:8px">
        <i class="fas fa-book"></i> <?= etugas_h($task['mapel_nama']) ?>
        <?php if (!empty($task['mapel_kode'])): ?>
          <span>(<?= etugas_h($task['mapel_kode']) ?>)</span>
        <?php endif; ?>
        &nbsp;·&nbsp;
        <i class="fas fa-users"></i> <?= etugas_h($task['kelas_nama']) ?>
      </p>
      <p>
        <strong>Deadline:</strong> <?= etugas_h(etugas_format_datetime_id($task['deadline_at'])) ?>
        &nbsp;·&nbsp;
        <?= etugas_status_badge($task['status']) ?>
        <?= etugas_pengumpulan_status_badge(
            $enriched['sub_status'] ?? '',
            !empty($enriched['is_late']) ? 1 : (int) ($submission['is_terlambat'] ?? 0)
        ) ?>
      </p>
      <p class="text-muted" style="font-size:13px;margin-bottom:0">
        <i class="fas fa-info-circle"></i> <?= etugas_h($latePolicy) ?>
        &nbsp;·&nbsp;
        Jenis pengumpulan: <?= etugas_h(etugas_jenis_label($task['allow_text'], $task['allow_link'])) ?>
      </p>
    </div>

    <div class="info-card">
      <h4 style="margin-top:0">Instruksi</h4>
      <div class="instruksi"><?= nl2br(etugas_h($task['instruksi'] ?? '—')) ?></div>
    </div>

    <?php if ($enriched['has_submission'] && $submission): ?>
    <div class="sub-box" id="pengumpulan-saya">
      <h4 style="margin-top:0"><i class="fas fa-check-circle text-success"></i> Pengumpulan Anda</h4>
      <?php if (!empty($submission['jawaban_teks'])): ?>
      <p><strong>Jawaban teks:</strong></p>
      <div class="well well-sm" style="white-space:pre-wrap"><?= etugas_h($submission['jawaban_teks']) ?></div>
      <?php endif; ?>
      <?php if (!empty($submission['link_url'])): ?>
      <p>
        <strong>Link:</strong>
        <a href="<?= etugas_h($submission['link_url']) ?>" target="_blank" rel="noopener noreferrer">
          <?= etugas_h($submission['link_url']) ?>
        </a>
        <span class="label label-default"><?= etugas_h($submission['link_jenis'] ?? 'lainnya') ?></span>
      </p>
      <?php endif; ?>
      <?php if (!empty($submission['catatan_siswa'])): ?>
      <p><strong>Catatan:</strong> <?= etugas_h($submission['catatan_siswa']) ?></p>
      <?php endif; ?>
      <p class="text-muted" style="font-size:12px;margin-bottom:0">
        Terakhir diperbarui: <?= etugas_h(etugas_format_datetime_id($submission['updated_at'] ?? $submission['created_at'])) ?>
      </p>
      <?php if (!empty($submission['catatan_guru'])): ?>
      <div class="alert alert-info" style="margin-top:12px;margin-bottom:0">
        <strong>Catatan guru:</strong> <?= etugas_h($submission['catatan_guru']) ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="form-section" id="form-kumpul">
      <h4 style="margin-top:0">
        <?= $canSubmit ? 'Kumpulkan / Perbarui Tugas' : 'Formulir Pengumpulan' ?>
      </h4>

      <?php if (!$canSubmit && !empty($enriched['block_reason'])): ?>
      <div class="alert alert-warning" role="alert">
        <i class="fas fa-lock"></i> <?= etugas_h($enriched['block_reason']) ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($formErrors['submit'])): ?>
      <div class="alert alert-danger"><?= etugas_h($formErrors['submit']) ?></div>
      <?php endif; ?>

      <?php if ($canSubmit): ?>
      <form method="post" action="tugas_kumpulkan.php" novalidate>
        <input type="hidden" name="etugas_id" value="<?= (int) $etugasId ?>">
        <?= etugas_csrf_field() ?>

        <?php if ($allowText): ?>
        <div class="form-group <?= !empty($formErrors['jawaban_teks']) ? 'has-error' : '' ?>">
          <label for="jawaban_teks">Jawaban teks</label>
          <textarea
            class="form-control"
            id="jawaban_teks"
            name="jawaban_teks"
            rows="6"
            <?= $allowText && !$allowLink ? 'required' : '' ?>
          ><?= etugas_h($formOld['jawaban_teks'] ?? $submission['jawaban_teks'] ?? '') ?></textarea>
          <p class="help-block">Tulis jawaban Anda di sini jika tugas meminta jawaban teks.</p>
          <?php if (!empty($formErrors['jawaban_teks'])): ?>
          <span class="help-block text-danger"><?= etugas_h($formErrors['jawaban_teks']) ?></span>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($allowLink): ?>
        <div class="form-group <?= !empty($formErrors['link_url']) ? 'has-error' : '' ?>">
          <label for="link_url">Link jawaban</label>
          <input
            type="url"
            class="form-control"
            id="link_url"
            name="link_url"
            maxlength="1000"
            placeholder="https://"
            value="<?= etugas_h($formOld['link_url'] ?? $submission['link_url'] ?? '') ?>"
            <?= $allowLink && !$allowText ? 'required' : '' ?>
          >
          <p class="help-block">
            Untuk video praktik, unggah video ke Google Drive/YouTube/Canva terlebih dahulu, lalu tempelkan link di sini.
          </p>
          <?php if (!empty($formErrors['link_url'])): ?>
          <span class="help-block text-danger"><?= etugas_h($formErrors['link_url']) ?></span>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="form-group <?= !empty($formErrors['catatan_siswa']) ? 'has-error' : '' ?>">
          <label for="catatan_siswa">Catatan untuk guru <span class="text-muted">(opsional)</span></label>
          <textarea class="form-control" id="catatan_siswa" name="catatan_siswa" rows="2"><?= etugas_h($formOld['catatan_siswa'] ?? $submission['catatan_siswa'] ?? '') ?></textarea>
          <?php if (!empty($formErrors['catatan_siswa'])): ?>
          <span class="help-block text-danger"><?= etugas_h($formErrors['catatan_siswa']) ?></span>
          <?php endif; ?>
        </div>

        <?php if (!empty($enriched['is_late'])): ?>
        <p class="text-warning"><i class="fas fa-clock"></i> Pengumpulan ini akan ditandai terlambat.</p>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary btn-lg">
          <i class="fas fa-paper-plane"></i> Kirim Pengumpulan
        </button>
        <a href="tugas_saya.php" class="btn btn-default btn-lg">Batal</a>
      </form>
      <?php elseif (!$enriched['has_submission']): ?>
      <p class="text-muted">Belum ada pengumpulan untuk tugas ini.</p>
      <?php endif; ?>
    </div>

    <p style="margin-top:16px">
      <a href="tugas_saya.php" class="btn btn-default"><i class="fa fa-arrow-left"></i> Kembali ke Tugas Saya</a>
    </p>

    <?php endif; ?>
  </section>
</div>

<?php include 'footer.php'; ?>
