<?php
/**
 * E-Tugas — Admin/Guru assignment list (Phase 1B).
 */
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/etugas_helpers.php';

$ctx = etugas_admin_context($koneksi);

$PAGE_TITLE = 'Pengumpulan Tugas';
include 'header.php';
$tablesReady = etugas_tables_ready($koneksi);
$alert = etugas_alert_from_request();

$filters = [
    'ta_id' => (int) ($_GET['ta_id'] ?? 0),
    'kelas_id' => (int) ($_GET['kelas_id'] ?? 0),
    'mapel_id' => (int) ($_GET['mapel_id'] ?? 0),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'q' => trim((string) ($_GET['q'] ?? '')),
];
if ($filters['status'] !== '' && !etugas_is_valid_status($filters['status'])) {
    $filters['status'] = '';
}

$summary = ['aktif' => 0, 'draft' => 0, 'ditutup' => 0, 'arsip' => 0, 'total' => 0];
$rows = [];
$taOptions = [];
$kelasOptions = [];
$mapelOptions = [];

$pengumpulanCounts = [];
if ($tablesReady) {
    $summary = etugas_count_summary($koneksi, $ctx, $filters);
    $rows = etugas_list_assignments($koneksi, $ctx, $filters);
    if (!empty($rows)) {
        $pengumpulanCounts = etugas_map_pengumpulan_counts(
            $koneksi,
            array_column($rows, 'etugas_id')
        );
    }
    $taOptions = etugas_list_ta_options($koneksi);
    $matrix = etugas_form_matrix($koneksi, $ctx);
    $kelasOptions = $matrix['kelas'] ?? [];
    $mapelOptions = $matrix['mapel'] ?? [];
}

$guruNoScope = !empty($ctx['is_guru']) && empty($ctx['scope']);
$canCreate = $tablesReady && !$guruNoScope;
?>
<style>
.etugas-page .summary-card { border-radius:12px; box-shadow:0 4px 14px rgba(15,23,42,.06); margin-bottom:16px; }
.etugas-page .summary-card .inner { padding:16px; }
.etugas-page .summary-card h3 { font-size:28px; font-weight:800; margin:0 0 4px; }
.etugas-page .summary-card p { margin:0; color:#64748b; font-size:13px; }
.etugas-page .sc-aktif { border-top:4px solid #22c55e; }
.etugas-page .sc-draft { border-top:4px solid #94a3b8; }
.etugas-page .sc-ditutup { border-top:4px solid #f59e0b; }
.etugas-page .sc-arsip { border-top:4px solid #475569; }
.etugas-page .filter-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px; margin-bottom:16px; }
.etugas-page .table-etugas thead th { background:#f1f5f9; font-size:12px; text-transform:uppercase; letter-spacing:.03em; }
.etugas-page .empty-state { text-align:center; padding:48px 24px; color:#64748b; }
.etugas-page .empty-state .fa { font-size:48px; color:#cbd5e1; margin-bottom:12px; }
.etugas-page .btn-actions form { display:inline-block; margin:0 2px 4px 0; }
@media (max-width:768px) { .etugas-page .table-responsive { border:0; } }
</style>

<div class="content-wrapper etugas-page">
  <section class="content-header">
    <div class="clearfix">
      <h1 class="pull-left" style="margin-top:0">
        <i class="fa-solid fa-file-lines" style="color:#2563eb;margin-right:6px"></i>
        Pengumpulan Tugas
        <small>Kelola tugas teks/link untuk siswa</small>
      </h1>
      <?php if ($canCreate): ?>
      <div class="pull-right" style="margin-top:8px">
        <a href="etugas_tambah.php" class="btn btn-primary">
          <i class="fa fa-plus"></i> Buat Tugas
        </a>
      </div>
      <?php endif; ?>
    </div>
    <ol class="breadcrumb">
      <li><a href="index.php"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Pengumpulan Tugas</li>
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
      <i class="fa fa-database"></i>
      Tabel e-Tugas belum tersedia. Impor
      <code>database/manual-migrations/2026-05-17-001-create-etugas-tables.sql</code>
      terlebih dahulu.
    </div>
    <?php else: ?>

    <?php if ($guruNoScope): ?>
    <div class="alert alert-info">
      <i class="fa fa-info-circle"></i>
      Anda belum memiliki data <strong>pengampu mapel</strong>. Tugas hanya dapat dibuat untuk kombinasi kelas/mapel yang Anda ampu.
    </div>
    <?php endif; ?>

    <div class="row">
      <div class="col-sm-6 col-md-3">
        <div class="box summary-card sc-aktif">
          <div class="inner">
            <h3><?= (int) $summary['aktif'] ?></h3>
            <p><i class="fa fa-check-circle text-success"></i> Tugas Aktif</p>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-md-3">
        <div class="box summary-card sc-draft">
          <div class="inner">
            <h3><?= (int) $summary['draft'] ?></h3>
            <p><i class="fa fa-pencil"></i> Draft</p>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-md-3">
        <div class="box summary-card sc-ditutup">
          <div class="inner">
            <h3><?= (int) $summary['ditutup'] ?></h3>
            <p><i class="fa fa-lock"></i> Ditutup</p>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-md-3">
        <div class="box summary-card sc-arsip">
          <div class="inner">
            <h3><?= (int) $summary['arsip'] ?></h3>
            <p><i class="fa fa-archive"></i> Diarsipkan</p>
          </div>
        </div>
      </div>
    </div>

    <div class="filter-box">
      <form method="get" action="etugas.php" class="form-horizontal" role="search" aria-label="Filter daftar tugas">
        <div class="row">
          <div class="col-md-2 col-sm-6">
            <div class="form-group" style="margin-bottom:10px">
              <label for="f_ta_id">Tahun ajaran</label>
              <select class="form-control input-sm" id="f_ta_id" name="ta_id">
                <option value="0">Semua</option>
                <?php foreach ($taOptions as $t): ?>
                <option value="<?= (int) $t['ta_id'] ?>" <?= $filters['ta_id'] === (int)$t['ta_id'] ? 'selected' : '' ?>>
                  <?= etugas_h($t['ta_nama']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="col-md-2 col-sm-6">
            <div class="form-group" style="margin-bottom:10px">
              <label for="f_kelas_id">Kelas</label>
              <select class="form-control input-sm" id="f_kelas_id" name="kelas_id">
                <option value="0">Semua</option>
                <?php foreach ($kelasOptions as $k): ?>
                <option value="<?= (int) $k['kelas_id'] ?>" <?= $filters['kelas_id'] === (int)$k['kelas_id'] ? 'selected' : '' ?>>
                  <?= etugas_h($k['kelas_nama']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="col-md-2 col-sm-6">
            <div class="form-group" style="margin-bottom:10px">
              <label for="f_mapel_id">Mapel</label>
              <select class="form-control input-sm" id="f_mapel_id" name="mapel_id">
                <option value="0">Semua</option>
                <?php foreach ($mapelOptions as $m): ?>
                <option value="<?= (int) $m['mapel_id'] ?>" <?= $filters['mapel_id'] === (int)$m['mapel_id'] ? 'selected' : '' ?>>
                  <?= etugas_h($m['mapel_nama']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="col-md-2 col-sm-6">
            <div class="form-group" style="margin-bottom:10px">
              <label for="f_status">Status</label>
              <select class="form-control input-sm" id="f_status" name="status">
                <option value="">Semua</option>
                <?php foreach (etugas_valid_statuses() as $st): ?>
                <option value="<?= etugas_h($st) ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>>
                  <?= etugas_h(ucfirst($st)) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="col-md-3 col-sm-8">
            <div class="form-group" style="margin-bottom:10px">
              <label for="f_q">Cari judul / instruksi</label>
              <input type="search" class="form-control input-sm" id="f_q" name="q"
                     value="<?= etugas_h($filters['q']) ?>" placeholder="Kata kunci…">
            </div>
          </div>
          <div class="col-md-1 col-sm-4">
            <label class="sr-only">Terapkan</label>
            <button type="submit" class="btn btn-default btn-sm btn-block" style="margin-top:24px">Filter</button>
          </div>
        </div>
      </form>
    </div>

    <div class="box">
      <div class="box-header with-border">
        <h3 class="box-title">Daftar Tugas</h3>
      </div>
      <div class="box-body table-responsive">
        <?php if (empty($rows)): ?>
        <div class="empty-state" role="status">
          <div><i class="fa fa-folder-open-o" aria-hidden="true"></i></div>
          <p><strong>Belum ada tugas</strong></p>
          <p>Mulai dengan membuat tugas untuk siswa mengumpulkan jawaban teks atau tautan.</p>
          <?php if ($canCreate): ?>
          <a href="etugas_tambah.php" class="btn btn-primary">
            <i class="fa fa-plus"></i> Buat Tugas Pertama
          </a>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <table class="table table-bordered table-hover table-etugas" id="table-etugas">
          <thead>
            <tr>
              <th scope="col">Judul</th>
              <th scope="col">Kelas</th>
              <th scope="col">Mapel</th>
              <th scope="col">Deadline</th>
              <th scope="col">Jenis</th>
              <th scope="col">Status</th>
              <th scope="col" style="min-width:200px">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
              <td>
                <strong><?= etugas_h($r['judul']) ?></strong>
                <br><small class="text-muted"><?= etugas_h($r['ta_nama']) ?></small>
              </td>
              <td><?= etugas_h($r['kelas_nama']) ?></td>
              <td><?= etugas_h($r['mapel_nama']) ?></td>
              <td>
                <?php if (!empty($r['deadline_at'])): ?>
                  <?= etugas_h(date('d/m/Y H:i', strtotime($r['deadline_at']))) ?>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td><?= etugas_h(etugas_jenis_label($r['allow_text'], $r['allow_link'])) ?></td>
              <td><?= etugas_status_badge($r['status']) ?></td>
              <td class="btn-actions">
                <?php if (etugas_user_can_review($ctx, $r)): ?>
                <a href="etugas_pengumpulan.php?etugas_id=<?= (int) $r['etugas_id'] ?>" class="btn btn-xs btn-info"
                   title="Lihat pengumpulan siswa" aria-label="Lihat pengumpulan <?= etugas_h($r['judul']) ?>">
                  <i class="fa fa-users"></i> Lihat Pengumpulan
                </a>
                <?php endif; ?>
                <?php if (etugas_user_can_manage($ctx, $r)): ?>
                <a href="etugas_edit.php?id=<?= (int) $r['etugas_id'] ?>" class="btn btn-xs btn-default"
                   title="Edit tugas" aria-label="Edit tugas <?= etugas_h($r['judul']) ?>">
                  <i class="fa fa-pencil"></i> Edit
                </a>
                <?php
                $statusActions = [];
                if ($r['status'] !== 'aktif') {
                    $statusActions['aktif'] = ['label' => 'Aktifkan', 'class' => 'btn-success'];
                }
                if ($r['status'] !== 'ditutup' && $r['status'] !== 'arsip') {
                    $statusActions['ditutup'] = ['label' => 'Tutup', 'class' => 'btn-warning'];
                }
                if ($r['status'] !== 'arsip') {
                    $statusActions['arsip'] = ['label' => 'Arsipkan', 'class' => 'btn-default'];
                }
                if ($r['status'] !== 'draft' && $r['status'] !== 'arsip') {
                    $statusActions['draft'] = ['label' => 'Draft', 'class' => 'btn-default'];
                }
                foreach ($statusActions as $st => $meta):
                ?>
                <form method="post" action="etugas_status.php" class="inline">
                  <?= etugas_csrf_field() ?>
                  <input type="hidden" name="etugas_id" value="<?= (int) $r['etugas_id'] ?>">
                  <input type="hidden" name="status" value="<?= etugas_h($st) ?>">
                  <button type="submit" class="btn btn-xs <?= etugas_h($meta['class']) ?>"
                          title="<?= etugas_h($meta['label']) ?>" aria-label="<?= etugas_h($meta['label']) ?> tugas">
                    <?= etugas_h($meta['label']) ?>
                  </button>
                </form>
                <?php endforeach;
                $pengCount = (int) ($pengumpulanCounts[(int) $r['etugas_id']] ?? 0);
                if ($pengCount === 0): ?>
                <form method="post" action="etugas_hapus.php" class="inline"
                      onsubmit="return confirm('Hapus tugas ini secara permanen? Aksi ini hanya aman jika belum ada pengumpulan siswa.');">
                  <?= etugas_csrf_field() ?>
                  <input type="hidden" name="etugas_id" value="<?= (int) $r['etugas_id'] ?>">
                  <button type="submit" class="btn btn-danger btn-xs"
                          title="Hapus tugas permanen" aria-label="Hapus tugas <?= etugas_h($r['judul']) ?>">
                    Hapus
                  </button>
                </form>
                <?php else: ?>
                <br><small class="text-muted">Sudah ada pengumpulan — gunakan Arsipkan</small>
                <?php endif; ?>
                <?php else: ?>
                <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </section>
</div>

<?php include 'footer.php'; ?>
