<?php
// kelas_siswa_act.php

include '../koneksi.php';

// Pastikan data yang dibutuhkan ada
if (isset($_POST['siswa']) && isset($_POST['kelas'])) {

    // Ambil ID kelas dari form
    $kelas = (int) $_POST['kelas'];

    // Ambil array ID siswa yang dipilih (toleran terhadap input tunggal)
    $siswa_array = is_array($_POST['siswa']) ? $_POST['siswa'] : [$_POST['siswa']];

    // Query disiapkan sekali, dieksekusi berulang — prepared statement (anti SQL injection)
    $stmt = mysqli_prepare($koneksi, 'INSERT INTO kelas_siswa VALUES (NULL, ?, ?)');
    if ($stmt) {
        foreach ($siswa_array as $siswa) {
            $siswa_id = (int) $siswa;
            if ($siswa_id <= 0) {
                continue;
            }
            mysqli_stmt_bind_param($stmt, 'ii', $siswa_id, $kelas);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
    }

    // Redirect kembali ke halaman kelas_siswa setelah semua data dimasukkan
    header("location:kelas_siswa.php?id=$kelas&pesan=berhasil");

} else {
    // Jika data tidak lengkap, arahkan kembali dengan pesan error
    header("location:kelas.php?pesan=gagal");
}

// Tutup koneksi database
mysqli_close($koneksi);
?>
