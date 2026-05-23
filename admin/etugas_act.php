<?php
/**
 * E-Tugas — create assignment(s) (POST). One row per selected kelas.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/etugas_helpers.php';

$ctx = etugas_admin_context($koneksi);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: etugas.php');
    exit;
}

if (!etugas_tables_ready($koneksi)) {
    etugas_flash_redirect('etugas.php', 'error', 'Tabel e-Tugas belum tersedia. Impor migrasi SQL terlebih dahulu.');
}

if (!etugas_verify_csrf()) {
    etugas_flash_redirect('etugas_tambah.php', 'error', 'Sesi tidak valid. Silakan coba lagi.');
}

if (!empty($ctx['is_guru']) && empty($ctx['scope'])) {
    etugas_flash_redirect('etugas.php', 'error', 'Anda belum memiliki penugasan pengampu mapel.');
}

$validation = etugas_validate_assignment_create($koneksi, $_POST, $ctx);
if (!$validation['ok']) {
    $_SESSION['etugas_form_errors'] = $validation['errors'];
    $_SESSION['etugas_form_old'] = $_POST;
    etugas_flash_redirect('etugas_tambah.php', 'error', 'Periksa kembali isian formulir.');
}

$d = $validation['data'];
$kelasIds = $d['kelas_ids'] ?? [];
$userId = (int) $ctx['user_id'];

$result = etugas_create_assignments_batch($koneksi, $d, $kelasIds, $userId);
if (!$result['ok']) {
    $_SESSION['etugas_form_errors'] = ['_general' => $result['error'] ?? 'Gagal menyimpan tugas.'];
    $_SESSION['etugas_form_old'] = $_POST;
    etugas_flash_redirect('etugas_tambah.php', 'error', $result['error'] ?? 'Gagal menyimpan tugas.');
}

unset($_SESSION['etugas_form_errors'], $_SESSION['etugas_form_old']);

$msg = etugas_format_batch_create_message($result['created'], $result['skipped']);
$type = ($result['created'] > 0) ? 'success' : 'warning';
etugas_flash_redirect('etugas.php', $type, $msg);
