<?php
/**
 * E-Tugas — update assignment (POST).
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
if (!$row || !etugas_user_can_manage($ctx, $row)) {
    etugas_flash_redirect('etugas.php', 'error', 'Anda tidak berhak mengubah tugas ini.');
}

$validation = etugas_validate_assignment($koneksi, $_POST, $ctx);
if (!$validation['ok']) {
    $_SESSION['etugas_form_errors'] = $validation['errors'];
    $_SESSION['etugas_form_old'] = $_POST;
    etugas_flash_redirect('etugas_edit.php?id=' . $etugasId, 'error', 'Periksa kembali isian formulir.');
}

$d = $validation['data'];
$userId = (int) $ctx['user_id'];

$sql = 'UPDATE etugas SET
            ta_id = ?, kelas_id = ?, mapel_id = ?, judul = ?, instruksi = ?, deadline_at = ?,
            allow_text = ?, allow_link = ?, izinkan_terlambat = ?, status = ?, updated_by = ?
        WHERE etugas_id = ?';

$stmt = mysqli_prepare($koneksi, $sql);
if (!$stmt) {
    error_log('[etugas] update prepare: ' . mysqli_error($koneksi));
    etugas_flash_redirect('etugas_edit.php?id=' . $etugasId, 'error', 'Gagal memperbarui tugas.');
}

$deadline = $d['deadline_at'];
mysqli_stmt_bind_param(
    $stmt,
    'iiisssiiisii',
    $d['ta_id'],
    $d['kelas_id'],
    $d['mapel_id'],
    $d['judul'],
    $d['instruksi'],
    $deadline,
    $d['allow_text'],
    $d['allow_link'],
    $d['izinkan_terlambat'],
    $d['status'],
    $userId,
    $etugasId
);

if (!mysqli_stmt_execute($stmt)) {
    error_log('[etugas] update execute: ' . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    etugas_flash_redirect('etugas_edit.php?id=' . $etugasId, 'error', 'Gagal memperbarui tugas.');
}
mysqli_stmt_close($stmt);

unset($_SESSION['etugas_form_errors'], $_SESSION['etugas_form_old']);
etugas_flash_redirect('etugas.php', 'success', 'Tugas berhasil diperbarui.');
