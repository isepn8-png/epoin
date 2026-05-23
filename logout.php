<?php
session_start();
include "koneksi.php";

// Ambil id admin dari session
if (isset($_SESSION['id'])) {
    $id = $_SESSION['id'];
    // Ubah status login menjadi offline di database
    mysqli_query($koneksi, "UPDATE admin SET status_login = 'offline' WHERE id = '$id'");
}

// Hapus session dan redirect ke halaman login
session_destroy();
header("Location: index.php");
exit();
?>
