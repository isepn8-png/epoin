<?php
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/epoin_security.php';

epoin_staff_guard(false);
epoin_require_post();
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('input_prestasi.php');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    epoin_flash_error('ID tidak valid.');
    header('Location: input_prestasi.php');
    exit;
}

$stmt = mysqli_prepare($koneksi, 'DELETE FROM input_prestasi WHERE id = ?');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header('Location: input_prestasi.php');
exit;
