<?php
include '../koneksi.php';

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
