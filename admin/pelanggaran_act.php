<?php
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/epoin_security.php';

epoin_staff_guard(true);
epoin_require_post();
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('pelanggaran.php');
}

$nama  = trim((string) ($_POST['nama'] ?? ''));
$point = (int) ($_POST['point'] ?? 0);

if ($nama === '' || $point < 1) {
    epoin_flash_error('Nama dan poin wajib diisi (poin minimal 1).');
    header('Location: pelanggaran.php');
    exit;
}

$stmt = mysqli_prepare($koneksi, 'INSERT INTO pelanggaran (pelanggaran_nama, pelanggaran_point) VALUES (?, ?)');
if (!$stmt) {
    epoin_flash_error('Gagal menyiapkan query.');
    header('Location: pelanggaran.php');
    exit;
}
mysqli_stmt_bind_param($stmt, 'si', $nama, $point);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if (!$ok) {
    epoin_flash_error('Gagal menyimpan pelanggaran.');
}
header('Location: pelanggaran.php');
exit;
