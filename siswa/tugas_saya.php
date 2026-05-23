<?php
/**
 * E-Tugas — Daftar tugas siswa (Phase 2).
 */
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/etugas_helpers.php';

$ctx = etugas_siswa_context($koneksi);
$siswaId = $ctx['siswa_id'];
$kelasInfo = $ctx['kelas'];
$tablesReady = $ctx['tables_ready'];

$PAGE_TITLE = 'Tugas Saya';
include 'header.php';

$alert = etugas_alert_from_request();
$filters = [
    'mapel_id' => (int) ($_GET['mapel_id'] ?? 0),
    'status' => trim((string) ($_GET['status'] ?? '')),
];

$allTasks = [];
$tasks = [];
$summary = ['aktif' => 0, 'belum' => 0, 'sudah' => 0, 'revisi_terlambat' => 0];
$mapelOptions = [];

if ($tablesReady && $kelasInfo) {
    $allTasks = etugas_list_tasks_for_siswa($koneksi, $siswaId, $kelasInfo);
    $summary = etugas_siswa_summary($allTasks);
    $mapelOptions = etugas_mapel_options_from_tasks($allTasks);
    $tasks = etugas_filter_siswa_tasks($allTasks, $filters);
}
?>
<style>
.etugas-siswa-page .summary-card { border-radius:12px; box-shadow:0 4px 14px rgba(15,23,42,.06); margin-bottom:16px; }
.etugas-siswa-page .summary-card .inner { padding:14px 16px; }
.etugas-siswa-page .summary-card h3 { font-size:26px; font-weight:800; margin:0 0 4px; }
.etugas-siswa-page .summary-card p { margin:0; color:#64748b; font-size:13px; }
.etugas-siswa-page .sc-aktif { border-top:4px solid #22c55e; }
.etugas-siswa-page .sc-belum { border-top:4px solid #94a3b8; }
.etugas-siswa-page .sc-sudah { border-top:4px solid #3b82f6; }
.etugas-siswa-page .sc-revisi { border-top:4px solid #f59e0b; }
.etugas-siswa-page .filter-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px; margin-bottom:16px; }
.etugas-siswa-page .task-card { border:1px solid #e2e8f0; border-radius:12px; padding:16px; margin-bottom:12px; background:#fff; box-shadow:0 2px 8px rgba(15,23,42,.04); }
.etugas-siswa-page .task-card h4 { margin:0 0 8px; font-size:17px; font-weight:700; }
.etugas-siswa-page .task-meta { color:#64748b; font-size:13px; margin-bottom:10px; }
.etugas-siswa-page .task-actions .btn { margin:4px 6px 4px 0; min-height:38px; }
.etugas-siswa-page .empty-state { text-align:center; padding:40px 20px; color:#64748b; }
.etugas-siswa-page .empty-state .fa { font-size:48px; color:#cbd5e1; margin-bottom:12px; }
</style>

<div class="content-wrapper etugas-siswa-page">
  <section class="content-header">
    <h1>
      <i class="fas fa-tasks" style="color:#2563eb;margin-right:6px"></i>
      Tugas Saya
      <small>Pengumpulan tugas per mapel</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="index.php"><i class="fa fa-home"></i> Beranda</a></li>
      <li class="active">Tugas Saya</li>
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
    <div class="alert alert-warning">
      <i class="fa fa-database"></i> Modul tugas belum siap. Hubungi administrator sekolah.
    </div>
    <?php elseif (!$kelasInfo): ?>
    <div class="alert alert-warning">
      <i class="fas fa-exclamation-circle"></i>
      Kelas aktif belum terdeteksi. Hubungi wali kelas agar penugasan dapat ditampilkan.
    </div>
    <?php else: ?>

    <p class="text-muted" style="margin-top:0">
      Kelas: <strong><?= etugas_h($kelasInfo['kelas_nama']) ?></strong>
      <?php if (!empty($kelasInfo['ta_nama'])): ?>
        <span>(<?= etugas_h($kelasInfo['ta_nama']) ?>)</span>
      <?php endif; ?>
    </p>

    <div class="row">
      <div class="col-xs-6 col-sm-3">
        <div class="box summary-card sc-aktif"><div class="inner"><h3><?= (int) $summary['aktif'] ?></h3><p>Tugas Aktif</p></div></div>
      </div>
      <div class="col-xs-6 col-sm-3">
        <div class="box summary-card sc-belum"><div class="inner"><h3><?= (int) $summary['belum'] ?></h3><p>Belum Dikumpulkan</p></div></div>
      </div>
      <div class="col-xs-6 col-sm-3">
        <div class="box summary-card sc-sudah"><div class="inner"><h3><?= (int) $summary['sudah'] ?></h3><p>Sudah Dikumpulkan</p></div></div>
      </div>
      <div class="col-xs-6 col-sm-3">
        <div class="box summary-card sc-revisi"><div class="inner"><h3><?= (int) $summary['revisi_terlambat'] ?></h3><p>Terlambat / Revisi</p></div></div>
      </div>
    </div>

    <div class="filter-box">
      <form method="get" action="tugas_saya.php" class="form-inline" role="search" aria-label="Filter tugas">
        <div class="form-group" style="margin-right:12px;margin-bottom:8px">
          <label for="f_mapel">Mapel</label><br>
          <select name="mapel_id" id="f_mapel" class="form-control input-sm">
            <option value="0">Semua mapel</option>
            <?php foreach ($mapelOptions as $m): ?>
            <option value="<?= (int) $m['mapel_id'] ?>" <?= $filters['mapel_id'] === (int)$m['mapel_id'] ? 'selected' : '' ?>>
              <?= etugas_h($m['mapel_nama']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin-right:12px;margin-bottom:8px">
          <label for="f_status">Status pengumpulan</label><br>
          <select name="status" id="f_status" class="form-control input-sm">
            <option value="">Semua status</option>
            <option value="belum" <?= $filters['status'] === 'belum' ? 'selected' : '' ?>>Belum dikumpulkan</option>
            <option value="sudah" <?= $filters['status'] === 'sudah' ? 'selected' : '' ?>>Sudah dikumpulkan</option>
            <option value="revisi" <?= $filters['status'] === 'revisi' ? 'selected' : '' ?>>Perlu revisi</option>
            <option value="terlambat" <?= $filters['status'] === 'terlambat' ? 'selected' : '' ?>>Terlambat</option>
          </select>
        </div>
        <button type="submit" class="btn btn-default btn-sm">Terapkan filter</button>
      </form>
    </div>

    <?php if (empty($tasks)): ?>
    <div class="empty-state" role="status">
      <div><i class="fas fa-inbox" aria-hidden="true"></i></div>
      <p><strong>Belum ada tugas ditampilkan</strong></p>
      <p>Tugas dari guru Anda akan muncul di sini setelah dipublikasikan untuk kelas Anda.</p>
    </div>
    <?php else: ?>
    <?php foreach ($tasks as $t): ?>
    <article class="task-card">
      <h4><?= etugas_h($t['judul']) ?></h4>
      <p class="task-meta">
        <i class="fas fa-book"></i> <?= etugas_h($t['mapel_nama']) ?>
        &nbsp;·&nbsp;
        <i class="far fa-clock"></i> Deadline: <?= etugas_h(etugas_format_datetime_id($t['deadline_at'])) ?>
        &nbsp;·&nbsp;
        <?= etugas_h(etugas_jenis_label($t['allow_text'], $t['allow_link'])) ?>
      </p>
      <p style="margin-bottom:10px">
        <?= etugas_status_badge($t['status']) ?>
        <?= etugas_pengumpulan_status_badge($t['sub_status'] ?? '', !empty($t['is_late']) ? 1 : (int)($t['submission']['is_terlambat'] ?? 0)) ?>
        <?php if ($t['status'] === 'ditutup'): ?>
          <span class="label label-warning">Ditutup</span>
        <?php endif; ?>
      </p>
      <?php if (!$t['can_submit'] && $t['block_reason']): ?>
      <p class="text-warning" style="font-size:13px"><i class="fas fa-info-circle"></i> <?= etugas_h($t['block_reason']) ?></p>
      <?php endif; ?>
      <div class="task-actions">
        <a href="tugas_detail.php?id=<?= (int) $t['etugas_id'] ?>" class="btn btn-default btn-sm">
          <i class="fas fa-eye"></i> Lihat Detail
        </a>
        <?php if ($t['can_submit']): ?>
        <a href="tugas_detail.php?id=<?= (int) $t['etugas_id'] ?>#form-kumpul" class="btn btn-primary btn-sm">
          <i class="fas fa-paper-plane"></i> Kumpulkan Tugas
        </a>
        <?php elseif ($t['has_submission']): ?>
        <a href="tugas_detail.php?id=<?= (int) $t['etugas_id'] ?>#form-kumpul" class="btn btn-info btn-sm">
          <i class="fas fa-file-alt"></i> Lihat Pengumpulan
        </a>
        <?php endif; ?>
      </div>
    </article>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php endif; ?>
  </section>
</div>

<?php include 'footer.php'; ?>
