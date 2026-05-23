<?php
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/epoin_security.php';

epoin_staff_guard(false);
epoin_require_post();
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('input_prestasi.php');
}

$id       = (int) ($_POST['id'] ?? 0);
$kelas    = (int) ($_POST['kelas'] ?? 0);
$siswa    = (int) ($_POST['siswa'] ?? 0);
$prestasi = (int) ($_POST['prestasi'] ?? 0);
$tanggal  = trim((string) ($_POST['tanggal'] ?? ''));
$jam      = trim((string) ($_POST['jam'] ?? ''));

if ($id <= 0 || $kelas <= 0 || $siswa <= 0 || $prestasi <= 0) {
    epoin_flash_error('Data tidak lengkap.');
    header('Location: input_prestasi.php');
    exit;
}

if (!epoin_verify_siswa_kelas($koneksi, $siswa, $kelas)) {
    epoin_flash_error('Siswa tidak terdaftar di kelas yang dipilih.');
    header('Location: input_prestasi_edit.php?id=' . $id);
    exit;
}

$waktu = date('Y-m-d', strtotime($tanggal ?: 'today')) . ' ' . date('H:i:s', strtotime($jam ?: 'now'));

$stmt = mysqli_prepare(
    $koneksi,
    'UPDATE input_prestasi SET waktu = ?, siswa = ?, kelas = ?, prestasi = ? WHERE id = ?'
);
if (!$stmt || !mysqli_stmt_bind_param($stmt, 'siiii', $waktu, $siswa, $kelas, $prestasi, $id) || !mysqli_stmt_execute($stmt)) {
    epoin_flash_error('Gagal memperbarui data prestasi.');
    header('Location: input_prestasi_edit.php?id=' . $id);
    exit;
}
mysqli_stmt_close($stmt);

header('Location: input_prestasi.php');
exit;
