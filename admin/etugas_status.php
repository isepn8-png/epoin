<?php
/**
 * E-Tugas — safe status change (POST). No hard delete; arsip = status only.
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
    etugas_flash_redirect('etugas.php', 'error', 'Sesi tidak valid.');
}

$etugasId = (int) ($_POST['etugas_id'] ?? 0);
$status = strtolower(trim((string) ($_POST['status'] ?? '')));

if ($etugasId <= 0 || !etugas_is_valid_status($status)) {
    etugas_flash_redirect('etugas.php', 'error', 'Permintaan status tidak valid.');
}

$row = etugas_fetch_by_id($koneksi, $etugasId);
if (!$row || !etugas_user_can_manage($ctx, $row)) {
    etugas_flash_redirect('etugas.php', 'error', 'Anda tidak berhak mengubah status tugas ini.');
}

$sql = 'UPDATE etugas SET status = ?, updated_by = ? WHERE etugas_id = ?';
$stmt = mysqli_prepare($koneksi, $sql);
if (!$stmt) {
    error_log('[etugas] status prepare: ' . mysqli_error($koneksi));
    etugas_flash_redirect('etugas.php', 'error', 'Gagal mengubah status.');
}

$userId = (int) $ctx['user_id'];
mysqli_stmt_bind_param($stmt, 'sii', $status, $userId, $etugasId);
if (!mysqli_stmt_execute($stmt)) {
    error_log('[etugas] status execute: ' . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    etugas_flash_redirect('etugas.php', 'error', 'Gagal mengubah status.');
}
mysqli_stmt_close($stmt);

$labels = [
    'draft' => 'disimpan sebagai draft',
    'aktif' => 'diaktifkan',
    'ditutup' => 'ditutup',
    'arsip' => 'diarsipkan',
];
$msg = 'Tugas berhasil ' . ($labels[$status] ?? 'diperbarui') . '.';
etugas_flash_redirect('etugas.php', 'success', $msg);
