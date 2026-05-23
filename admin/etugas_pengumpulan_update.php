<?php
/**
 * E-Tugas — POST update penilaian pengumpulan (Phase 3A).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/etugas_helpers.php';

$ctx = etugas_admin_context($koneksi);
$listUrl = 'etugas_pengumpulan.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $listUrl);
    exit;
}

if (!etugas_tables_ready($koneksi)) {
    etugas_flash_redirect($listUrl, 'error', 'Tabel e-Tugas belum tersedia.');
}

if (!etugas_verify_csrf()) {
    etugas_flash_redirect($listUrl, 'error', 'Sesi tidak valid. Silakan coba lagi.');
}

$pengumpulanId = (int) ($_POST['pengumpulan_id'] ?? 0);
if ($pengumpulanId <= 0) {
    etugas_flash_redirect($listUrl, 'error', 'Pengumpulan tidak valid.');
}

$row = etugas_fetch_pengumpulan_by_id($koneksi, $pengumpulanId);
if (!$row || !etugas_user_can_review($ctx, $row)) {
    etugas_flash_redirect($listUrl, 'error', 'Anda tidak berhak meninjau pengumpulan ini.');
}

$detailUrl = 'etugas_pengumpulan_detail.php?id=' . $pengumpulanId;

$validation = etugas_validate_review_update($_POST);
if (!$validation['ok']) {
    $_SESSION['etugas_review_errors'] = $validation['errors'];
    $_SESSION['etugas_review_old'] = $_POST;
    etugas_flash_redirect($detailUrl, 'error', 'Periksa kembali isian formulir.');
}

$ok = etugas_update_pengumpulan_review(
    $koneksi,
    $pengumpulanId,
    $validation['data'],
    (int) $ctx['user_id']
);

if (!$ok) {
    etugas_flash_redirect($detailUrl, 'error', 'Gagal menyimpan penilaian. Coba lagi.');
}

$listUrlWithTask = $listUrl . '?etugas_id=' . (int) ($row['etugas_id'] ?? 0);
etugas_flash_redirect($detailUrl, 'success', 'Penilaian berhasil disimpan.');
