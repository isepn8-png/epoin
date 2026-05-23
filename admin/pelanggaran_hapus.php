<?php
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/epoin_security.php';

epoin_staff_guard(true);
epoin_require_post();
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('pelanggaran.php');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    epoin_flash_error('ID tidak valid.');
    header('Location: pelanggaran.php');
    exit;
}

$stmt = mysqli_prepare($koneksi, 'DELETE FROM input_pelanggaran WHERE pelanggaran = ?');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

$stmt = mysqli_prepare($koneksi, 'DELETE FROM pelanggaran WHERE pelanggaran_id = ?');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header('Location: pelanggaran.php');
exit;
