<?php
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/etugas_helpers.php';

$ctx = etugas_admin_context($koneksi);

$PAGE_TITLE = 'Edit Tugas';
include 'header.php';

if (!etugas_tables_ready($koneksi)) {
    echo '<div class="content-wrapper"><section class="content"><div class="alert alert-warning">Tabel e-Tugas belum tersedia.</div></section></div>';
    include 'footer.php';
    exit;
}

$etugasId = (int) ($_GET['id'] ?? 0);
$row = $etugasId > 0 ? etugas_fetch_by_id($koneksi, $etugasId) : null;
if (!$row || !etugas_user_can_manage($ctx, $row)) {
    echo '<div class="content-wrapper"><section class="content"><div class="alert alert-danger">Tugas tidak ditemukan atau akses ditolak.</div></section></div>';
    include 'footer.php';
    exit;
}

$matrix = etugas_form_matrix($koneksi, $ctx);
$formErrors = $_SESSION['etugas_form_errors'] ?? [];
$formOld = $_SESSION['etugas_form_old'] ?? [];
unset($_SESSION['etugas_form_errors'], $_SESSION['etugas_form_old']);

$formData = $formOld ?: $row;
$alert = etugas_alert_from_request();
$isEdit = true;
?>
<style>
.etugas-form-page .box { border-radius:12px; box-shadow:0 8px 24px rgba(37,99,235,.08); }
</style>

<div class="content-wrapper etugas-form-page">
  <section class="content-header">
    <h1>Edit Tugas <small><?= etugas_h($row['judul']) ?></small></h1>
    <ol class="breadcrumb">
      <li><a href="index.php">Home</a></li>
      <li><a href="etugas.php">Pengumpulan Tugas</a></li>
      <li class="active">Edit</li>
    </ol>
  </section>

  <section class="content">
    <?php if (!empty($formErrors)): ?>
    <div class="alert alert-danger" role="alert">
      <strong>Periksa isian berikut:</strong>
      <ul style="margin-bottom:0;padding-left:20px">
        <?php foreach ($formErrors as $err): ?>
        <li><?= etugas_h($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <?php if ($alert): ?>
    <div class="alert alert-<?= $alert['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible">
      <button type="button" class="close" data-dismiss="alert" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
      <?= etugas_h($alert['msg']) ?>
    </div>
    <?php endif; ?>

    <p class="text-muted">Status saat ini: <?= etugas_status_badge($row['status']) ?></p>

    <div class="alert alert-info">
      <i class="fa fa-info-circle"></i>
      Jika tugas dibuat untuk banyak kelas, masing-masing kelas menjadi data tugas terpisah dan dapat diedit per kelas.
    </div>

    <form method="post" action="etugas_update.php" novalidate>
      <?= etugas_csrf_field() ?>
      <input type="hidden" name="etugas_id" value="<?= (int) $row['etugas_id'] ?>">
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title"><i class="fa fa-pencil"></i> Ubah Tugas</h3>
        </div>
        <div class="box-body">
          <?php include __DIR__ . '/etugas_form_inc.php'; ?>
        </div>
        <div class="box-footer">
          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Perubahan</button>
          <a href="etugas.php" class="btn btn-default">Kembali</a>
        </div>
      </div>
    </form>
  </section>
</div>

<?php include 'footer.php'; ?>
