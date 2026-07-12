<?php
require_once __DIR__ . '/../includes/epoin_security.php';
include '../koneksi.php';
epoin_staff_guard(true);
epoin_require_post();
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('ta.php');
}

$id     = (int) ($_POST['id'] ?? 0);
$nama   = trim((string) ($_POST['nama'] ?? ''));
$status = (string) ($_POST['status'] ?? '');
if ($id <= 0 || $nama === '') {
    header('location:ta.php');
    exit;
}

// Jika TA ini di-set aktif, non-aktifkan TA lain dulu (hanya boleh 1 aktif)
if ($status == 1) {
    mysqli_query($koneksi, "update ta set ta_status='0'");
}

$stmt = mysqli_prepare($koneksi, 'UPDATE ta SET ta_nama = ?, ta_status = ? WHERE ta_id = ?');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ssi', $nama, $status, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header('location:ta.php');
