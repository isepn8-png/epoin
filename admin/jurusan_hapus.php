<?php
require_once __DIR__ . '/../includes/epoin_security.php';
include '../koneksi.php';
epoin_staff_guard(true);
epoin_require_post();
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('jurusan.php');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: jurusan.php');
    exit;
}

// Hapus siswa yang berada di jurusan ini (cascading aman — urutan penting)
$stmt = mysqli_prepare($koneksi, 'SELECT siswa_id FROM siswa WHERE siswa_jurusan = ?');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $sid = (int) $row['siswa_id'];
        foreach ([
            'DELETE FROM input_prestasi WHERE siswa = ?',
            'DELETE FROM input_pelanggaran WHERE siswa = ?',
            'DELETE FROM kelas_siswa WHERE ks_siswa = ?',
            'DELETE FROM siswa WHERE siswa_id = ?',
        ] as $sql) {
            $d = mysqli_prepare($koneksi, $sql);
            if ($d) { mysqli_stmt_bind_param($d, 'i', $sid); mysqli_stmt_execute($d); mysqli_stmt_close($d); }
        }
    }
    mysqli_stmt_close($stmt);
}

// Hapus kelas yang terkait jurusan ini, lalu hapus jurusan
foreach ([
    'DELETE FROM kelas WHERE kelas_jurusan = ?',
    'DELETE FROM jurusan WHERE jurusan_id = ?',
] as $sql) {
    $d = mysqli_prepare($koneksi, $sql);
    if ($d) { mysqli_stmt_bind_param($d, 'i', $id); mysqli_stmt_execute($d); mysqli_stmt_close($d); }
}

header('Location: jurusan.php');
