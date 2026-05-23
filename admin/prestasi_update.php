<?php
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/epoin_security.php';

epoin_staff_guard(true);
epoin_require_post();
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('prestasi.php');
}

$id    = (int) ($_POST['id'] ?? 0);
$nama  = trim((string) ($_POST['nama'] ?? ''));
$point = (int) ($_POST['point'] ?? 0);

if ($id <= 0 || $nama === '' || $point < 1) {
    epoin_flash_error('Data tidak valid.');
    header('Location: prestasi.php');
    exit;
}

$stmt = mysqli_prepare($koneksi, 'UPDATE prestasi SET prestasi_nama = ?, prestasi_point = ? WHERE prestasi_id = ?');
if (!$stmt || !mysqli_stmt_bind_param($stmt, 'sii', $nama, $point, $id) || !mysqli_stmt_execute($stmt)) {
    epoin_flash_error('Gagal memperbarui prestasi.');
    header('Location: prestasi_edit.php?id=' . $id);
    exit;
}
mysqli_stmt_close($stmt);

header('Location: prestasi.php');
exit;
