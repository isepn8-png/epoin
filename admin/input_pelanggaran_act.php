<?php
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/epoin_security.php';

$user_id = epoin_staff_guard(false);
epoin_require_post();
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('input_pelanggaran.php');
}

$kelas       = (int) ($_POST['kelas'] ?? 0);
$siswa       = (int) ($_POST['siswa'] ?? 0);
$pelanggaran = (int) ($_POST['pelanggaran'] ?? 0);
$tanggal     = trim((string) ($_POST['tanggal'] ?? ''));
$jam         = trim((string) ($_POST['jam'] ?? ''));

if ($kelas <= 0 || $siswa <= 0 || $pelanggaran <= 0) {
    epoin_flash_error('Kelas, siswa, dan jenis pelanggaran wajib dipilih.');
    header('Location: input_pelanggaran_tambah.php');
    exit;
}

if (!epoin_verify_siswa_kelas($koneksi, $siswa, $kelas)) {
    epoin_flash_error('Siswa tidak terdaftar di kelas yang dipilih.');
    header('Location: input_pelanggaran_tambah.php');
    exit;
}

$tsTgl = strtotime($tanggal ?: 'today');
$tsJam = strtotime($jam ?: 'now');
$waktu = date('Y-m-d', $tsTgl) . ' ' . date('H:i:s', $tsJam);
$ip_ym = (int) date('Ym', strtotime($waktu));

$has_ip_ym = epoin_column_exists($koneksi, 'input_pelanggaran', 'ip_ym');

$ok_insert = false;
$err_msg   = '';

if ($has_ip_ym) {
    $stmt = mysqli_prepare(
        $koneksi,
        'INSERT INTO input_pelanggaran (waktu, siswa, kelas, pelanggaran, ip_ym) VALUES (?, ?, ?, ?, ?)'
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'siiii', $waktu, $siswa, $kelas, $pelanggaran, $ip_ym);
        $ok_insert = mysqli_stmt_execute($stmt);
        if (!$ok_insert) {
            $err_msg = mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    }
} else {
    $stmt = mysqli_prepare(
        $koneksi,
        'INSERT INTO input_pelanggaran (waktu, siswa, kelas, pelanggaran) VALUES (?, ?, ?, ?)'
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'siii', $waktu, $siswa, $kelas, $pelanggaran);
        $ok_insert = mysqli_stmt_execute($stmt);
        if (!$ok_insert) {
            $err_msg = mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    }
}

if (function_exists('usage_log_db_snapshot') && isset($GLOBALS['SEKOLAH_ID'])) {
    usage_log_db_snapshot($koneksi, (int) $GLOBALS['SEKOLAH_ID']);
}

$nama_guru = epoin_resolve_guru_nama($koneksi, $user_id);

$nama_siswa = '';
$stmt = mysqli_prepare($koneksi, 'SELECT siswa_nama FROM siswa WHERE siswa_id = ? LIMIT 1');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $siswa);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $nama_siswa = (string) $row['siswa_nama'];
    }
    mysqli_stmt_close($stmt);
}

$nama_pelanggaran = '';
$stmt = mysqli_prepare($koneksi, 'SELECT pelanggaran_nama FROM pelanggaran WHERE pelanggaran_id = ? LIMIT 1');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $pelanggaran);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $nama_pelanggaran = (string) $row['pelanggaran_nama'];
    }
    mysqli_stmt_close($stmt);
}

$aktivitas = $ok_insert
    ? "Input pelanggaran '$nama_pelanggaran' untuk siswa $nama_siswa oleh $nama_guru"
    : "GAGAL input pelanggaran untuk siswa $nama_siswa oleh $nama_guru ($err_msg)";

epoin_log_aktivitas($koneksi, $user_id, $nama_guru, $aktivitas);

if ($ok_insert) {
    $_SESSION['flash_success'] = 'Pelanggaran berhasil disimpan.';
    header('Location: input_pelanggaran.php?status=ok');
} else {
    $_SESSION['flash_error'] = 'Gagal menyimpan pelanggaran: ' . $err_msg;
    header('Location: input_pelanggaran.php?status=err');
}
exit;
