<?php
require_once __DIR__ . '/../includes/epoin_security.php';
include '../koneksi.php';
epoin_staff_guard(true);
epoin_require_post();
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('kelas.php');
}

$id      = (int) ($_POST['id'] ?? 0);
$nama    = trim((string) ($_POST['nama'] ?? ''));
$ta      = (string) ($_POST['ta'] ?? '');
$jurusan = (string) ($_POST['jurusan'] ?? '');
if ($id <= 0 || $nama === '') {
    header('location:kelas.php');
    exit;
}

$stmt = mysqli_prepare($koneksi, 'UPDATE kelas SET kelas_nama = ?, kelas_ta = ?, kelas_jurusan = ? WHERE kelas_id = ?');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'sssi', $nama, $ta, $jurusan, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header('location:kelas.php');
