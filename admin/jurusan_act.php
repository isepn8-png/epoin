<?php
require_once __DIR__ . '/../includes/epoin_security.php';
include '../koneksi.php';
epoin_staff_guard(true);
epoin_require_post();
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('jurusan.php');
}

$nama = trim((string) ($_POST['nama'] ?? ''));
if ($nama === '') {
    header('location:jurusan.php');
    exit;
}

$stmt = mysqli_prepare($koneksi, 'INSERT INTO jurusan VALUES (NULL, ?)');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $nama);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header('location:jurusan.php');
