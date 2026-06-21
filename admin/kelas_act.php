<?php
require_once __DIR__ . '/../includes/epoin_security.php';
include '../koneksi.php';
epoin_staff_guard();
epoin_require_post();
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('kelas.php');
}

$nama    = trim((string) ($_POST['nama'] ?? ''));
$jurusan = (string) ($_POST['jurusan'] ?? '');
$ta      = (string) ($_POST['ta'] ?? '');

// pertahankan perilaku lama: ada spasi di belakang nama kelas
$nama_db = $nama . ' ';

$stmt = mysqli_prepare($koneksi, 'INSERT INTO kelas VALUES (NULL, ?, ?, ?)');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'sss', $nama_db, $jurusan, $ta);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header('location:kelas.php');
