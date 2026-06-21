<?php
require_once __DIR__ . '/../includes/epoin_security.php';
include '../koneksi.php';
epoin_ensure_session();

// Hanya user yang sudah login yang boleh mengganti password
if (!isset($_SESSION['id'])) {
    header("location:../login.php");
    exit;
}

epoin_require_post();
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('gantipassword.php');
}

$id       = (int) $_SESSION['id'];
$password = md5((string) ($_POST['password'] ?? ''));

// Update password — prepared statement (anti SQL injection)
$stmt = mysqli_prepare($koneksi, 'UPDATE user SET user_password = ? WHERE user_id = ?');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'si', $password, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header("location:gantipassword.php?alert=sukses");
