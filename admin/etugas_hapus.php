<?php
/**
 * E-Tugas — safe permanent delete (POST). Only when zero pengumpulan rows.
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
    etugas_flash_redirect('etugas.php', 'error', 'Tabel e-Tugas belum tersedia.');
}

if (!etugas_verify_csrf()) {
    etugas_flash_redirect('etugas.php', 'error', 'Sesi tidak valid. Silakan coba lagi.');
}

$etugasId = (int) ($_POST['etugas_id'] ?? 0);
if ($etugasId <= 0) {
    etugas_flash_redirect('etugas.php', 'error', 'Tugas tidak ditemukan.');
}

$row = etugas_fetch_by_id($koneksi, $etugasId);
if (!$row) {
    etugas_flash_redirect('etugas.php', 'error', 'Tugas tidak ditemukan.');
}

if (!etugas_user_can_manage($ctx, $row)) {
    etugas_flash_redirect('etugas.php', 'error', 'Anda tidak berhak menghapus tugas ini.');
}

$result = etugas_delete_assignment_if_empty($koneksi, $etugasId);

if ($result['ok']) {
    etugas_flash_redirect('etugas.php', 'success', 'Tugas berhasil dihapus.');
}

switch ($result['reason'] ?? '') {
    case 'has_submissions':
        $msg = 'Tugas tidak dapat dihapus karena sudah memiliki pengumpulan siswa. Gunakan Arsipkan.';
        break;
    case 'not_found':
        $msg = 'Tugas tidak ditemukan.';
        break;
    default:
        $msg = 'Gagal menghapus tugas. Coba lagi.';
        break;
}

etugas_flash_redirect('etugas.php', 'error', $msg);
