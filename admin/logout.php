<?php
session_start();
include '../koneksi.php';

// Pastikan user sudah login sebelum melakukan proses logout
if (isset($_SESSION['id'])) {
    // Ambil user_id dari session
    $id = $_SESSION['id'];

    // Update status_login menjadi 'offline' di tabel 'user'
    // Menggunakan variabel $id yang sudah ada di session
    $update_user = mysqli_query($koneksi, "UPDATE user SET status_login = 'offline' WHERE user_id = '$id'");

    // Anda bisa menambahkan penanganan error jika update gagal
    // if (!$update_user) {
    //     die("Gagal update status logout: " . mysqli_error($koneksi));
    // }
}

// Bersihkan semua session
session_unset();
session_destroy();

// Redirect ke halaman login admin
header("Location: ../admin.php?alert=logout");
exit;
?>