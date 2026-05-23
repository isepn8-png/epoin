<?php
// kelas_siswa_act.php

include '../koneksi.php';

// Pastikan data yang dibutuhkan ada
if(isset($_POST['siswa']) && isset($_POST['kelas'])){
    
    // Ambil ID kelas dari form
    $kelas = $_POST['kelas'];

    // Ambil array ID siswa yang dipilih
    $siswa_array = $_POST['siswa'];

    // Loop melalui setiap ID siswa yang ada di dalam array
    foreach($siswa_array as $siswa) {
        // Query untuk memasukkan setiap siswa ke dalam kelas
        mysqli_query($koneksi, "insert into kelas_siswa values (NULL,'$siswa','$kelas')");
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