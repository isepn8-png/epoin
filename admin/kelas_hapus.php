<?php
require_once __DIR__ . '/../includes/epoin_security.php';
include '../koneksi.php';
epoin_staff_guard(true);
epoin_require_post();
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('kelas.php');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: kelas.php');
    exit;
}

foreach ([
    'DELETE FROM kelas WHERE kelas_id = ?',
    'DELETE FROM kelas_siswa WHERE ks_kelas = ?',
    'DELETE FROM input_prestasi WHERE kelas = ?',
    'DELETE FROM input_pelanggaran WHERE kelas = ?',
] as $sql) {
    $stmt = mysqli_prepare($koneksi, $sql);
    if ($stmt) { mysqli_stmt_bind_param($stmt, 'i', $id); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
}

header('Location: kelas.php');
