<?php
require_once __DIR__ . '/../includes/epoin_security.php';
include '../koneksi.php';
epoin_staff_guard();
epoin_require_post();
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('ta.php');
}

$nama   = trim((string) ($_POST['nama'] ?? ''));
$status = (string) ($_POST['status'] ?? '');

if ($status == 1) {
    mysqli_query($koneksi, "update ta set ta_status='0'");
}

// pertahankan perilaku lama: ada spasi di belakang nama TA
$nama_db = $nama . ' ';

$stmt = mysqli_prepare($koneksi, 'INSERT INTO ta VALUES (NULL, ?, ?)');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ss', $nama_db, $status);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header('location:ta.php');
