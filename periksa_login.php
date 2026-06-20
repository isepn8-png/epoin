<?php
// menghubungkan dengan koneksi
include 'koneksi.php';

// menangkap data yang dikirim dari form
$nis      = trim((string) ($_POST['nis'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($nis === '' || $password === '') {
    header('location:index.php?alert=gagal');
    exit;
}

// Ambil siswa berdasarkan NIS — prepared statement (anti SQL injection)
$data = null;
$stmt = mysqli_prepare(
    $koneksi,
    'SELECT siswa_id, siswa_nama, siswa_nis, siswa_password FROM siswa WHERE siswa_nis = ? LIMIT 1'
);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $nis);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) {
        $data = mysqli_fetch_assoc($res);
    }
    mysqli_stmt_close($stmt);
}

// Verifikasi password: dukung bcrypt (baru) + MD5 legacy (akun lama)
$login_ok     = false;
$need_upgrade = false;
if ($data) {
    $stored = (string) $data['siswa_password'];
    if (preg_match('/^\$2y\$\d{2}\$/', $stored)) {
        // sudah bcrypt
        $login_ok = password_verify($password, $stored);
    } else {
        // hash lama (MD5) — verifikasi timing-safe & tandai untuk upgrade
        if (hash_equals($stored, md5($password))) {
            $login_ok     = true;
            $need_upgrade = true;
        }
    }
}

if ($login_ok && $data) {
    session_start();

    $siswa_id = (int) $data['siswa_id'];

    // Upgrade transparan hash lama (MD5) ke bcrypt saat login berhasil
    if ($need_upgrade) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        if ($newHash) {
            $up = mysqli_prepare($koneksi, 'UPDATE siswa SET siswa_password = ? WHERE siswa_id = ?');
            if ($up) {
                mysqli_stmt_bind_param($up, 'si', $newHash, $siswa_id);
                mysqli_stmt_execute($up);
                mysqli_stmt_close($up);
            }
        }
    }

    $_SESSION['id']    = $data['siswa_id'];
    $_SESSION['nama']  = $data['siswa_nama'];
    $_SESSION['nis']   = $data['siswa_nis'];
    $_SESSION['level'] = "siswa";

    // Update last_login dan status_login — prepared statement
    $upd = mysqli_prepare(
        $koneksi,
        "UPDATE siswa SET last_login = NOW(), status_login = 'online' WHERE siswa_id = ?"
    );
    if ($upd) {
        mysqli_stmt_bind_param($upd, 'i', $siswa_id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
    }

    header("location:siswa/");
    exit;
}

header("location:index.php?alert=gagal");
exit;
