<?php
include '../koneksi.php';
session_start();

// Hanya siswa yang sudah login yang boleh mengganti password
if (!isset($_SESSION['id'])) {
    header("location:../login.php");
    exit;
}

$id       = (int) $_SESSION['id'];
$password = password_hash((string) ($_POST['password'] ?? ''), PASSWORD_DEFAULT);

// Update password — prepared statement (anti SQL injection), hash bcrypt
$stmt = mysqli_prepare($koneksi, 'UPDATE siswa SET siswa_password = ? WHERE siswa_id = ?');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'si', $password, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header("location:gantipassword.php?alert=sukses");
