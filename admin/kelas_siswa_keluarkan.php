<?php
require_once __DIR__ . '/../includes/epoin_security.php';
include '../koneksi.php';
epoin_staff_guard(true);
epoin_require_post();
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('kelas.php');
}

$siswa = (int) ($_POST['siswa'] ?? 0);
$kelas = (int) ($_POST['kelas'] ?? 0);
if ($siswa <= 0 || $kelas <= 0) {
    header('location:kelas.php');
    exit;
}

$stmt = mysqli_prepare($koneksi, 'DELETE FROM kelas_siswa WHERE ks_siswa = ? AND ks_kelas = ?');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ii', $siswa, $kelas);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header('location:kelas_siswa.php?id=' . $kelas);
