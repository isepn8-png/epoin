<?php
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/epoin_security.php';

epoin_staff_guard(false);
epoin_require_post();
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('input_pelanggaran.php');
}

$id          = (int) ($_POST['id'] ?? 0);
$kelas       = (int) ($_POST['kelas'] ?? 0);
$siswa       = (int) ($_POST['siswa'] ?? 0);
$pelanggaran = (int) ($_POST['pelanggaran'] ?? 0);
$tanggal     = trim((string) ($_POST['tanggal'] ?? ''));
$jam         = trim((string) ($_POST['jam'] ?? ''));

if ($id <= 0 || $kelas <= 0 || $siswa <= 0 || $pelanggaran <= 0) {
    epoin_flash_error('Data tidak lengkap.');
    header('Location: input_pelanggaran.php');
    exit;
}

if (!epoin_verify_siswa_kelas($koneksi, $siswa, $kelas)) {
    epoin_flash_error('Siswa tidak terdaftar di kelas yang dipilih.');
    header('Location: input_pelanggaran_edit.php?id=' . $id);
    exit;
}

$waktu = date('Y-m-d', strtotime($tanggal ?: 'today')) . ' ' . date('H:i:s', strtotime($jam ?: 'now'));

$stmt = mysqli_prepare(
    $koneksi,
    'UPDATE input_pelanggaran SET waktu = ?, siswa = ?, kelas = ?, pelanggaran = ? WHERE id = ?'
);
if (!$stmt || !mysqli_stmt_bind_param($stmt, 'siiii', $waktu, $siswa, $kelas, $pelanggaran, $id) || !mysqli_stmt_execute($stmt)) {
    epoin_flash_error('Gagal memperbarui data pelanggaran.');
    header('Location: input_pelanggaran_edit.php?id=' . $id);
    exit;
}
mysqli_stmt_close($stmt);

header('Location: input_pelanggaran.php');
exit;
