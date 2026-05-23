<?php
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/etugas_helpers.php';

$ctx = etugas_admin_context($koneksi);

$PAGE_TITLE = 'Buat Tugas';
include 'header.php';

if (!etugas_tables_ready($koneksi)) {
    echo '<div class="content-wrapper"><section class="content"><div class="alert alert-warning">Tabel e-Tugas belum tersedia. Impor file migrasi SQL terlebih dahulu.</div></section></div>';
    include 'footer.php';
    exit;
}

$matrix = etugas_form_matrix($koneksi, $ctx);
$noScope = !empty($ctx['is_guru']) && empty($matrix['combos']);

$formErrors = $_SESSION['etugas_form_errors'] ?? [];
$formData = $_SESSION['etugas_form_old'] ?? [];
unset($_SESSION['etugas_form_errors'], $_SESSION['etugas_form_old']);

if (empty($formData)) {
    $activeTa = etugas_get_active_ta($koneksi);
    $formData = [
        'ta_id' => $activeTa ? (int) $activeTa['ta_id'] : '',
        'allow_text' => 1,
        'allow_link' => 1,
        'izinkan_terlambat' => 1,
        'status' => 'draft',
    ];
}

$alert = etugas_alert_from_request();
$isEdit = false;
?>
<style>
.etugas-form-page .box { border-radius:12px; box-shadow:0 8px 24px rgba(37,99,235,.08); }
.etugas-form-page .box-header { border-bottom:1px solid #e8eef7; background:linear-gradient(180deg,#f8fbff,#fff); }
</style>

<div class="content-wrapper etugas-form-page">
  <section class="content-header">
    <h1>Buat Tugas <small>Pengumpulan Tugas</small></h1>
    <ol class="breadcrumb">
      <li><a href="index.php">Home</a></li>
      <li><a href="etugas.php">Pengumpulan Tugas</a></li>
      <li class="active">Buat Tugas</li>
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

    <?php if ($noScope): ?>
    <div class="alert alert-warning">
      <i class="fa fa-info-circle"></i>
      Anda belum memiliki penugasan <strong>pengampu mapel</strong>. Hubungi administrator untuk menambahkan penugasan terlebih dahulu.
    </div>
    <p><a href="etugas.php" class="btn btn-default">Kembali</a></p>
    <?php else: ?>

    <form method="post" action="etugas_act.php" novalidate>
      <?= etugas_csrf_field() ?>
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title"><i class="fa fa-plus-circle"></i> Form Tugas Baru</h3>
        </div>
        <div class="box-body">
          <?php include __DIR__ . '/etugas_form_inc.php'; ?>
        </div>
        <div class="box-footer">
          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Buat Tugas</button>
          <a href="etugas.php" class="btn btn-default">Kembali</a>
        </div>
      </div>
    </form>
    <?php endif; ?>
  </section>
</div>

<?php include 'footer.php'; ?>
