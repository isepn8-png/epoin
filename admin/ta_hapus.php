<?php
require_once __DIR__ . '/../includes/epoin_security.php';
include '../koneksi.php';
epoin_staff_guard(true);
epoin_require_post();
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('ta.php');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: ta.php');
    exit;
}

// Hapus kelas terkait TA ini (cascading)
$stmt = mysqli_prepare($koneksi, 'SELECT kelas_id FROM kelas WHERE kelas_ta = ?');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $kid = (int) $row['kelas_id'];
        foreach ([
            'DELETE FROM input_prestasi WHERE kelas = ?',
            'DELETE FROM input_pelanggaran WHERE kelas = ?',
            'DELETE FROM kelas_siswa WHERE ks_kelas = ?',
            'DELETE FROM kelas WHERE kelas_id = ?',
        ] as $sql) {
            $d = mysqli_prepare($koneksi, $sql);
            if ($d) { mysqli_stmt_bind_param($d, 'i', $kid); mysqli_stmt_execute($d); mysqli_stmt_close($d); }
        }
    }
    mysqli_stmt_close($stmt);
}

// Hapus TA
$d = mysqli_prepare($koneksi, 'DELETE FROM ta WHERE ta_id = ?');
if ($d) { mysqli_stmt_bind_param($d, 'i', $id); mysqli_stmt_execute($d); mysqli_stmt_close($d); }

header('Location: ta.php');
