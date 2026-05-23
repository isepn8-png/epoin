<?php
/**
 * E-Tugas â€” Detail pengumpulan / siswa belum kumpul (Phase 3A).
 */
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/etugas_helpers.php';

$ctx = etugas_admin_context($koneksi);

$pengumpulanId = (int) ($_GET['id'] ?? 0);
$etugasId = (int) ($_GET['etugas_id'] ?? 0);
$siswaId = (int) ($_GET['siswa_id'] ?? 0);

$PAGE_TITLE = 'Detail Pengumpulan';
include 'header.php';

$tablesReady = etugas_tables_ready($koneksi);
$alert = etugas_alert_from_request();
$formErrors = $_SESSION['etugas_review_errors'] ?? [];
$formOld = $_SESSION['etugas_review_old'] ?? [];
unset($_SESSION['etugas_review_errors'], $_SESSION['etugas_review_old']);

$row = null;
$hasSubmission = false;
$canReview = false;

if ($tablesReady) {
    if ($pengumpulanId > 0) {
        $row = etugas_fetch_pengumpulan_by_id($koneksi, $pengumpulanId);
        if ($row && etugas_user_can_review($ctx, $row)) {
            $hasSubmission = true;
            $canReview = true;
            $etugasId = (int) $row['etugas_id'];
        } else {
            $row = null;
        }
    } elseif ($etugasId > 0 && $siswaId > 0) {
        $task = etugas_fetch_by_id($koneksi, $etugasId);
        if ($task && etugas_user_can_review($ctx, $task)) {
            $existing = etugas_fetch_submission($koneksi, $etugasId, $siswaId);
            if ($existing) {
                header('Location: etugas_pengumpulan_detail.php?id=' . (int) $existing['pengumpulan_id']);
                exit;
            }
            $row = etugas_fetch_review_student_view($koneksi, $etugasId, $siswaId);
            $hasSubmission = false;
            $canReview = false;
        }
    }
}

$listUrl = 'etugas_pengumpulan.php' . ($etugasId ? '?etugas_id=' . $etugasId : '');
?>
<style>
.etugas-review-detail .info-card { border:1px solid #e2e8f0; border-radius:12px; padding:18px; margin-bottom:16px; background:#fff; }
.etugas-review-detail .answer-box { background:#f8fafc; border-left:4px solid #3b82f6; padding:12px 14px; white-space:pre-wrap; border-radius:0 8px 8px 0; }
@media print {
  .etugas-review-detail .no-print { display:none !important; }
  .etugas-review-detail .info-card { break-inside:avoid; }
}
</style>

<div class="content-wrapper etugas-review-detail">
  <section class="content-header no-print">
    <h1>
      <i class="fa-solid fa-user-check" style="color:#2563eb;margin-right:6px"></i>
      Detail Pengumpulan
    </h1>
    <ol class="breadcrumb">
      <li><a href="index.php"><i class="fa fa-dashboard"></i> Home</a></li>
      <li><a href="etugas.php">Tugas</a></li>
      <li><a href="<?= etugas_h($listUrl) ?>">Review</a></li>
      <li class="active">Detail</li>
    </ol>
  </section>

  <section class="content">
    <?php if ($alert): ?>
    <div class="alert alert-<?= $alert['type'] === 'success' ? 'success' : ($alert['type'] === 'error' ? 'danger' : 'info') ?> alert-dismissible no-print">
      <button type="button" class="close" data-dismiss="alert" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
      <?= etugas_h($alert['msg']) ?>
    </div>
    <?php endif; ?>

    <?php if (!$tablesReady): ?>
    <div class="alert alert-warning">Tabel e-Tugas belum tersedia.</div>
    <?php elseif (!$row): ?>
    <div class="alert alert-danger" role="alert">Data tidak ditemukan atau Anda tidak memiliki akses.</div>
    <a href="<?= etugas_h($listUrl) ?>" class="btn btn-default no-print"><i class="fa fa-arrow-left"></i> Kembali</a>
    <?php else: ?>

    <div class="info-card">
      <h3 style="margin-top:0"><i class="fa fa-user"></i> <?= etugas_h($row['siswa_nama']) ?></h3>
      <p class="text-muted" style="margin-bottom:0">
        NIS: <strong><?= etugas_h($row['siswa_nis'] ?? '—') ?></strong>
        &nbsp;·&nbsp; Kelas: <?= etugas_h($row['kelas_nama']) ?>
        &nbsp;·&nbsp; Mapel: <?= etugas_h($row['mapel_nama']) ?>
        <?php if (!empty($row['mapel_kode'])): ?>(<?= etugas_h($row['mapel_kode']) ?>)<?php endif; ?>
      </p>
    </div>

    <div class="info-card">
      <h4 style="margin-top:0"><?= etugas_h($row['judul']) ?></h4>
      <p>
        <strong>Deadline:</strong> <?= etugas_h(etugas_format_datetime_id($row['deadline_at'])) ?>
        &nbsp;·&nbsp; <?= etugas_status_badge($row['tugas_status'] ?? '') ?>
      </p>
      <?php if (!empty($row['instruksi'])): ?>
      <p class="text-muted" style="font-size:13px"><strong>Instruksi:</strong> <?= nl2br(etugas_h($row['instruksi'])) ?></p>
      <?php endif; ?>
    </div>

    <?php if (!$hasSubmission): ?>
    <div class="alert alert-warning" role="status">
      <i class="fa fa-clock-o"></i> <strong>Belum mengumpulkan</strong> — siswa belum mengirim jawaban untuk tugas ini.
    </div>
  <?php else: ?>

    <div class="info-card">
      <h4 style="margin-top:0">Pengumpulan Siswa</h4>
      <p>
        <strong>Waktu kumpul:</strong>
        <?= etugas_h(etugas_format_datetime_id($row['updated_at'] ?? $row['created_at'])) ?>
        &nbsp;·&nbsp;
        <?= etugas_pengumpulan_status_badge($row['status'] ?? '', (int) ($row['is_terlambat'] ?? 0)) ?>
      </p>
      <p>
        <strong>Terlambat:</strong>
        <?= !empty($row['is_terlambat']) ? '<span class="label label-warning">Ya</span>' : '<span class="text-muted">Tidak</span>' ?>
      </p>

      <?php if (!empty($row['jawaban_teks'])): ?>
      <p><strong>Jawaban teks</strong></p>
      <div class="answer-box"><?= etugas_h($row['jawaban_teks']) ?></div>
      <?php endif; ?>

      <?php if (!empty($row['link_url'])): ?>
      <p style="margin-top:12px">
        <strong>Link jawaban</strong>
        <span class="label label-default"><?= etugas_h($row['link_jenis'] ?? 'lainnya') ?></span>
      </p>
      <p>
        <a href="<?= etugas_h($row['link_url']) ?>" class="btn btn-info btn-sm" target="_blank" rel="noopener noreferrer"
           title="Buka link di tab baru">
          <i class="fa fa-external-link"></i> Buka Link
        </a>
        <br><small class="text-muted"><?= etugas_h($row['link_url']) ?></small>
      </p>
      <?php endif; ?>

      <?php if (!empty($row['catatan_siswa'])): ?>
      <p><strong>Catatan siswa:</strong> <?= etugas_h($row['catatan_siswa']) ?></p>
      <?php endif; ?>
    </div>

    <?php if (!empty($row['reviewed_at']) || !empty($row['reviewed_by'])): ?>
    <div class="info-card">
      <h4 style="margin-top:0">Riwayat Peninjauan</h4>
      <p class="text-muted" style="margin-bottom:0">
        Ditinjau oleh: <strong><?= etugas_h($row['reviewed_by_nama'] ?? '—') ?></strong>
        &nbsp;·&nbsp;
        Waktu: <?= etugas_h(etugas_format_datetime_id($row['reviewed_at'])) ?>
        <?php if ($row['nilai'] !== null && $row['nilai'] !== ''): ?>
        &nbsp;·&nbsp; Nilai: <strong><?= etugas_h($row['nilai']) ?></strong>
        <?php endif; ?>
      </p>
      <?php if (!empty($row['catatan_guru'])): ?>
      <p style="margin-top:8px"><strong>Catatan guru:</strong> <?= nl2br(etugas_h($row['catatan_guru'])) ?></p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="box no-print">
      <div class="box-header with-border"><h3 class="box-title">Form Penilaian</h3></div>
      <div class="box-body">
        <?php if (!empty($formErrors['submit'])): ?>
        <div class="alert alert-danger"><?= etugas_h($formErrors['submit']) ?></div>
        <?php endif; ?>

        <form method="post" action="etugas_pengumpulan_update.php">
          <input type="hidden" name="pengumpulan_id" value="<?= (int) $row['pengumpulan_id'] ?>">
          <?= etugas_csrf_field() ?>

          <div class="form-group <?= !empty($formErrors['status']) ? 'has-error' : '' ?>">
            <label for="status">Status penilaian</label>
            <select class="form-control" id="status" name="status" required>
              <?php
              $curStatus = $formOld['status'] ?? $row['status'] ?? 'terkirim';
              foreach (etugas_valid_pengumpulan_statuses() as $st):
                  $labels = ['terkirim' => 'Terkirim', 'ditinjau' => 'Ditinjau', 'revisi' => 'Perlu Revisi', 'selesai' => 'Selesai'];
              ?>
              <option value="<?= etugas_h($st) ?>" <?= $curStatus === $st ? 'selected' : '' ?>>
                <?= etugas_h($labels[$st] ?? ucfirst($st)) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <?php if (!empty($formErrors['status'])): ?>
            <span class="help-block text-danger"><?= etugas_h($formErrors['status']) ?></span>
            <?php endif; ?>
          </div>

          <div class="form-group <?= !empty($formErrors['nilai']) ? 'has-error' : '' ?>">
            <label for="nilai">Nilai <span class="text-muted">(opsional, 0-100)</span></label>
            <input type="number" class="form-control" id="nilai" name="nilai" min="0" max="100" step="0.01"
                   value="<?= etugas_h($formOld['nilai'] ?? ($row['nilai'] !== null && $row['nilai'] !== '' ? $row['nilai'] : '')) ?>">
            <?php if (!empty($formErrors['nilai'])): ?>
            <span class="help-block text-danger"><?= etugas_h($formErrors['nilai']) ?></span>
            <?php endif; ?>
          </div>

          <div class="form-group <?= !empty($formErrors['catatan_guru']) ? 'has-error' : '' ?>">
            <label for="catatan_guru">Catatan untuk siswa</label>
            <textarea class="form-control" id="catatan_guru" name="catatan_guru" rows="4"><?= etugas_h($formOld['catatan_guru'] ?? $row['catatan_guru'] ?? '') ?></textarea>
            <?php if (!empty($formErrors['catatan_guru'])): ?>
            <span class="help-block text-danger"><?= etugas_h($formErrors['catatan_guru']) ?></span>
            <?php endif; ?>
          </div>

          <button type="submit" class="btn btn-primary">
            <i class="fa fa-save"></i> Simpan Penilaian
          </button>
          <a href="<?= etugas_h($listUrl) ?>" class="btn btn-default">Batal</a>
        </form>
      </div>
    </div>

    <?php endif; ?>

    <p class="no-print" style="margin-top:16px">
      <a href="<?= etugas_h($listUrl) ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> Kembali ke Daftar</a>
      <button type="button" class="btn btn-default" onclick="window.print()"><i class="fa fa-print"></i> Cetak</button>
    </p>

    <?php endif; ?>
  </section>
</div>

<?php include 'footer.php'; ?>

