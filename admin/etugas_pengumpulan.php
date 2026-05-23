<?php
/**
 * E-Tugas — Review pengumpulan siswa (Phase 3A).
 */
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/etugas_helpers.php';

$ctx = etugas_admin_context($koneksi);

$PAGE_TITLE = 'Review Pengumpulan';
include 'header.php';

$tablesReady = etugas_tables_ready($koneksi);
$alert = etugas_alert_from_request();

$filters = [
    'ta_id' => (int) ($_GET['ta_id'] ?? 0),
    'kelas_id' => (int) ($_GET['kelas_id'] ?? 0),
    'mapel_id' => (int) ($_GET['mapel_id'] ?? 0),
    'etugas_id' => (int) ($_GET['etugas_id'] ?? 0),
    'sub_status' => trim((string) ($_GET['sub_status'] ?? '')),
    'q' => trim((string) ($_GET['q'] ?? '')),
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;

$summary = ['total' => 0, 'sudah' => 0, 'belum' => 0, 'revisi' => 0, 'selesai' => 0];
$allRows = [];
$rows = [];
$totalPages = 1;
$taskOptions = [];
$taOptions = [];
$kelasOptions = [];
$mapelOptions = [];
$selectedTask = null;
$listReady = false;

if ($tablesReady) {
    $taOptions = etugas_list_ta_options($koneksi);
    $matrix = etugas_form_matrix($koneksi, $ctx);
    $kelasOptions = $matrix['kelas'] ?? [];
    $mapelOptions = $matrix['mapel'] ?? [];
    $taskOptions = etugas_list_tasks_for_review_dropdown($koneksi, $ctx, $filters);
    $listReady = etugas_review_list_ready($filters);

    if ($listReady) {
        $selectedTask = etugas_fetch_by_id($koneksi, $filters['etugas_id']);
        if ($selectedTask && etugas_user_can_review($ctx, $selectedTask)) {
            $allRows = etugas_list_review_rows($koneksi, $ctx, $filters);
            $summary = etugas_review_summary($allRows);
            $totalPages = max(1, (int) ceil(count($allRows) / $perPage));
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $rows = array_slice($allRows, ($page - 1) * $perPage, $perPage);
        } else {
            $listReady = false;
            $selectedTask = null;
        }
    }
}

$guruNoScope = !empty($ctx['is_guru']) && empty($ctx['scope']);
?>
<style>
.etugas-review-page .summary-card { border-radius:12px; box-shadow:0 4px 14px rgba(15,23,42,.06); margin-bottom:16px; }
.etugas-review-page .summary-card .inner { padding:14px 16px; }
.etugas-review-page .summary-card h3 { font-size:26px; font-weight:800; margin:0 0 4px; }
.etugas-review-page .summary-card p { margin:0; color:#64748b; font-size:13px; }
.etugas-review-page .sc-total { border-top:4px solid #3b82f6; }
.etugas-review-page .sc-sudah { border-top:4px solid #22c55e; }
.etugas-review-page .sc-belum { border-top:4px solid #94a3b8; }
.etugas-review-page .sc-revisi { border-top:4px solid #f59e0b; }
.etugas-review-page .sc-selesai { border-top:4px solid #16a34a; }
.etugas-review-page .filter-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px; margin-bottom:16px; }
.etugas-review-page .empty-state { text-align:center; padding:40px 20px; color:#64748b; }
.etugas-review-page .table-review thead th { background:#f1f5f9; font-size:12px; }
@media (max-width:768px) { .etugas-review-page .table-responsive { border:0; } }
</style>

<div class="content-wrapper etugas-review-page">
  <section class="content-header">
    <div class="clearfix">
      <h1 class="pull-left" style="margin-top:0">
        <i class="fa-solid fa-clipboard-check" style="color:#2563eb;margin-right:6px"></i>
        Pengumpulan Tugas
        <small>Cek jawaban/link tugas siswa</small>
      </h1>
      <div class="pull-right" style="margin-top:8px">
        <a href="etugas.php" class="btn btn-default">
          <i class="fa fa-list"></i> Daftar Tugas
        </a>
      </div>
    </div>
    <ol class="breadcrumb">
      <li><a href="index.php"><i class="fa fa-dashboard"></i> Home</a></li>
      <li><a href="etugas.php">Tugas</a></li>
      <li class="active">Review Pengumpulan</li>
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
    <div class="alert alert-warning">Tabel e-Tugas belum tersedia.</div>
    <?php elseif ($guruNoScope): ?>
    <div class="alert alert-info">Anda belum memiliki data pengampu mapel untuk meninjau pengumpulan.</div>
    <?php else: ?>

    <div class="filter-box">
      <form method="get" action="etugas_pengumpulan.php" role="search" aria-label="Filter review pengumpulan">
        <div class="row">
          <div class="col-md-2 col-sm-6">
            <div class="form-group" style="margin-bottom:10px">
              <label for="f_ta">Tahun ajaran</label>
              <select class="form-control input-sm" id="f_ta" name="ta_id">
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
              <label for="f_kelas">Kelas</label>
              <select class="form-control input-sm" id="f_kelas" name="kelas_id">
                <option value="0">Semua</option>
                <?php foreach ($kelasOptions as $k): ?>
                <option value="<?= (int) $k['kelas_id'] ?>" data-ta="<?= (int)($k['kelas_ta'] ?? 0) ?>"
                  <?= $filters['kelas_id'] === (int)$k['kelas_id'] ? 'selected' : '' ?>>
                  <?= etugas_h($k['kelas_nama']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="col-md-2 col-sm-6">
            <div class="form-group" style="margin-bottom:10px">
              <label for="f_mapel">Mapel</label>
              <select class="form-control input-sm" id="f_mapel" name="mapel_id">
                <option value="0">Semua</option>
                <?php foreach ($mapelOptions as $m): ?>
                <option value="<?= (int) $m['mapel_id'] ?>" <?= $filters['mapel_id'] === (int)$m['mapel_id'] ? 'selected' : '' ?>>
                  <?= etugas_h($m['mapel_nama']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="col-md-3 col-sm-6">
            <div class="form-group" style="margin-bottom:10px">
              <label for="f_tugas">Tugas <span class="text-danger">*</span></label>
              <select class="form-control input-sm" id="f_tugas" name="etugas_id" required>
                <option value="0">— Pilih tugas —</option>
                <?php foreach ($taskOptions as $t): ?>
                <option value="<?= (int) $t['etugas_id'] ?>" <?= $filters['etugas_id'] === (int)$t['etugas_id'] ? 'selected' : '' ?>>
                  <?= etugas_h($t['judul']) ?> (<?= etugas_h($t['kelas_nama']) ?> · <?= etugas_h($t['mapel_nama']) ?>)
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="col-md-2 col-sm-6">
            <div class="form-group" style="margin-bottom:10px">
              <label for="f_sub">Status pengumpulan</label>
              <select class="form-control input-sm" id="f_sub" name="sub_status">
                <option value="">Semua</option>
                <option value="belum" <?= $filters['sub_status'] === 'belum' ? 'selected' : '' ?>>Belum Mengumpulkan</option>
                <?php foreach (etugas_valid_pengumpulan_statuses() as $st): ?>
                <option value="<?= etugas_h($st) ?>" <?= $filters['sub_status'] === $st ? 'selected' : '' ?>>
                  <?= etugas_h(ucfirst($st === 'revisi' ? 'Perlu Revisi' : $st)) ?>
                </option>
                <?php endforeach; ?>
                <option value="terlambat" <?= $filters['sub_status'] === 'terlambat' ? 'selected' : '' ?>>Terlambat</option>
              </select>
            </div>
          </div>
          <div class="col-md-3 col-sm-8">
            <div class="form-group" style="margin-bottom:10px">
              <label for="f_q">Cari nama / NIS</label>
              <input type="search" class="form-control input-sm" id="f_q" name="q"
                     value="<?= etugas_h($filters['q']) ?>" placeholder="Nama atau NIS siswa">
            </div>
          </div>
          <div class="col-md-1 col-sm-4">
            <label class="sr-only">Terapkan</label>
            <button type="submit" class="btn btn-primary btn-sm btn-block" style="margin-top:24px">Tampilkan</button>
          </div>
        </div>
      </form>
    </div>

    <?php if (!$listReady): ?>
    <div class="empty-state" role="status">
      <div><i class="fa fa-filter fa-3x" style="color:#cbd5e1" aria-hidden="true"></i></div>
      <p><strong>Pilih tugas terlebih dahulu</strong></p>
      <p>Gunakan filter di atas, lalu pilih <strong>Tugas</strong> untuk melihat semua siswa di kelas (termasuk yang belum mengumpulkan).</p>
    </div>
    <?php else: ?>

    <?php if ($selectedTask): ?>
    <p class="text-muted">
      Tugas: <strong><?= etugas_h($selectedTask['judul']) ?></strong>
      · <?= etugas_h($selectedTask['kelas_nama']) ?> · <?= etugas_h($selectedTask['mapel_nama']) ?>
      · <?= etugas_status_badge($selectedTask['status']) ?>
    </p>
    <?php endif; ?>

    <div class="row">
      <div class="col-xs-6 col-sm-4 col-md-2">
        <div class="box summary-card sc-total"><div class="inner"><h3><?= (int) $summary['total'] ?></h3><p>Total Siswa</p></div></div>
      </div>
      <div class="col-xs-6 col-sm-4 col-md-2">
        <div class="box summary-card sc-sudah"><div class="inner"><h3><?= (int) $summary['sudah'] ?></h3><p>Sudah Mengumpulkan</p></div></div>
      </div>
      <div class="col-xs-6 col-sm-4 col-md-2">
        <div class="box summary-card sc-belum"><div class="inner"><h3><?= (int) $summary['belum'] ?></h3><p>Belum Mengumpulkan</p></div></div>
      </div>
      <div class="col-xs-6 col-sm-4 col-md-2">
        <div class="box summary-card sc-revisi"><div class="inner"><h3><?= (int) $summary['revisi'] ?></h3><p>Perlu Revisi</p></div></div>
      </div>
      <div class="col-xs-6 col-sm-4 col-md-2">
        <div class="box summary-card sc-selesai"><div class="inner"><h3><?= (int) $summary['selesai'] ?></h3><p>Selesai</p></div></div>
      </div>
    </div>

    <div class="box">
      <div class="box-header with-border">
        <h3 class="box-title">Daftar Siswa</h3>
        <?php if ($totalPages > 1): ?>
        <div class="box-tools pull-right text-muted" style="font-size:12px;padding-top:6px">
          Halaman <?= (int) $page ?> / <?= (int) $totalPages ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="box-body table-responsive">
        <?php if (empty($allRows)): ?>
        <div class="empty-state" role="status">
          <p><strong>Tidak ada siswa di kelas ini</strong></p>
          <p>Pastikan data <code>kelas_siswa</code> sudah diisi untuk kelas tugas tersebut.</p>
        </div>
        <?php elseif (empty($rows)): ?>
        <div class="empty-state"><p>Tidak ada data pada halaman ini.</p></div>
        <?php else: ?>
        <table class="table table-bordered table-hover table-review">
          <thead>
            <tr>
              <th scope="col" style="width:40px">No</th>
              <th scope="col">Nama Siswa</th>
              <th scope="col">NIS</th>
              <th scope="col">Kelas</th>
              <th scope="col">Mapel</th>
              <th scope="col">Judul Tugas</th>
              <th scope="col">Waktu Kumpul</th>
              <th scope="col">Status</th>
              <th scope="col">Terlambat</th>
              <th scope="col">Nilai</th>
              <th scope="col" style="min-width:120px">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php $no = ($page - 1) * $perPage + 1; foreach ($rows as $r): ?>
            <tr>
              <td><?= (int) $no++ ?></td>
              <td><?= etugas_h($r['siswa_nama']) ?></td>
              <td><?= etugas_h($r['siswa_nis'] ?? '—') ?></td>
              <td><?= etugas_h($r['kelas_nama']) ?></td>
              <td><?= etugas_h($r['mapel_nama']) ?></td>
              <td><?= etugas_h($r['tugas_judul']) ?></td>
              <td>
                <?php if (!empty($r['kumpul_updated_at']) || !empty($r['kumpul_created_at'])): ?>
                  <?= etugas_h(etugas_format_datetime_id($r['kumpul_updated_at'] ?? $r['kumpul_created_at'])) ?>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td><?= etugas_review_status_display($r) ?></td>
              <td>
                <?php if (!empty($r['is_terlambat'])): ?>
                  <span class="label label-warning">Ya</span>
                <?php else: ?>
                  <span class="text-muted">Tidak</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($r['nilai'] !== null && $r['nilai'] !== ''): ?>
                  <?= etugas_h($r['nilai']) ?>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($r['pengumpulan_id'])): ?>
                <a href="etugas_pengumpulan_detail.php?id=<?= (int) $r['pengumpulan_id'] ?>"
                   class="btn btn-xs btn-primary" title="Detail pengumpulan" aria-label="Detail <?= etugas_h($r['siswa_nama']) ?>">
                  <i class="fa fa-eye"></i> Detail
                </a>
                <?php else: ?>
                <a href="etugas_pengumpulan_detail.php?etugas_id=<?= (int) $r['etugas_id'] ?>&amp;siswa_id=<?= (int) $r['siswa_id'] ?>"
                   class="btn btn-xs btn-default" title="Lihat siswa belum mengumpulkan" aria-label="Info <?= etugas_h($r['siswa_nama']) ?>">
                  <i class="fa fa-info-circle"></i> Info
                </a>
                <span class="text-muted" style="font-size:11px;display:block">Belum mengumpulkan</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Paginasi review" class="text-center">
          <ul class="pagination pagination-sm" style="margin:0">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="<?= $p === $page ? 'active' : '' ?>">
              <a href="etugas_pengumpulan.php?<?= etugas_h(etugas_review_filter_query($filters, $p)) ?>"><?= (int) $p ?></a>
            </li>
            <?php endfor; ?>
          </ul>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </section>
</div>

<?php include 'footer.php'; ?>
