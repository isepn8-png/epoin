<?php
/**
 * E-Tugas — POST handler pengumpulan siswa (Phase 2).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/etugas_helpers.php';

$redirectBase = 'tugas_saya.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectBase);
    exit;
}

$ctx = etugas_siswa_context($koneksi);
$siswaId = $ctx['siswa_id'];
$kelasInfo = $ctx['kelas'];

if (!$ctx['tables_ready']) {
    etugas_flash_redirect($redirectBase, 'error', 'Modul tugas belum siap.');
}

if (!$kelasInfo) {
    etugas_flash_redirect($redirectBase, 'error', 'Kelas aktif belum terdeteksi.');
}

if (!etugas_verify_csrf()) {
    etugas_flash_redirect($redirectBase, 'error', 'Sesi tidak valid. Silakan coba lagi.');
}

$etugasId = (int) ($_POST['etugas_id'] ?? 0);
if ($etugasId <= 0) {
    etugas_flash_redirect($redirectBase, 'error', 'Tugas tidak valid.');
}

$detailUrl = 'tugas_detail.php?id=' . $etugasId;

$task = etugas_fetch_task_for_siswa($koneksi, $etugasId, $siswaId, $kelasInfo);
if (!$task) {
    etugas_flash_redirect($redirectBase, 'error', 'Tugas tidak ditemukan atau Anda tidak memiliki akses.');
}

$submission = etugas_fetch_submission($koneksi, $etugasId, $siswaId);
$state = etugas_task_submission_state($task, $submission);

if (!$state['can_submit']) {
    $msg = $state['reason'] !== '' ? $state['reason'] : 'Pengumpulan tidak dapat dilakukan.';
    etugas_flash_redirect($detailUrl, 'error', $msg);
}

$validation = etugas_validate_submission($task, $_POST);
if (!$validation['ok']) {
    $_SESSION['etugas_sub_errors'] = $validation['errors'];
    $_SESSION['etugas_sub_old'] = $_POST;
    etugas_flash_redirect($detailUrl . '#form-kumpul', 'error', 'Periksa kembali isian formulir.');
}

$prevStatus = $submission['status'] ?? null;
$ok = etugas_save_submission(
    $koneksi,
    $task,
    $siswaId,
    $validation['data'],
    !empty($state['is_late']),
    $prevStatus
);

if (!$ok) {
    etugas_flash_redirect($detailUrl . '#form-kumpul', 'error', 'Gagal menyimpan pengumpulan. Coba lagi.');
}

etugas_flash_redirect($detailUrl, 'success', 'Pengumpulan tugas berhasil disimpan.');
