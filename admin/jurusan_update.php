<?php
require_once __DIR__ . '/../includes/epoin_security.php';
include '../koneksi.php';
epoin_staff_guard(true);
epoin_require_post();
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('jurusan.php');
}

$id   = (int) ($_POST['id'] ?? 0);
$nama = trim((string) ($_POST['nama'] ?? ''));
if ($id <= 0 || $nama === '') {
    header('location:jurusan.php');
    exit;
}

$stmt = mysqli_prepare($koneksi, 'UPDATE jurusan SET jurusan_nama = ? WHERE jurusan_id = ?');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'si', $nama, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header('location:jurusan.php');
