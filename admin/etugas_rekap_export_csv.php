<?php
/**
 * E-Tugas — Export rekap CSV (Phase 4A). No HTML output.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/etugas_helpers.php';

$ctx = etugas_admin_context($koneksi);
$rekapUrl = 'etugas_rekap.php';

if (!etugas_tables_ready($koneksi)) {
    etugas_flash_redirect($rekapUrl, 'error', 'Tabel e-Tugas belum tersedia.');
}

$filters = etugas_parse_rekap_filters($_GET);

if (!etugas_rekap_list_ready($filters)) {
    etugas_flash_redirect($rekapUrl, 'error', 'Pilih tugas atau kombinasi kelas dan mapel terlebih dahulu.');
}

if (!etugas_rekap_can_access_filters($koneksi, $ctx, $filters)) {
    etugas_flash_redirect($rekapUrl, 'error', 'Anda tidak berhak mengekspor rekap ini.');
}

$rows = etugas_list_rekap_rows($koneksi, $ctx, $filters);

if (!etugas_send_rekap_csv($rows)) {
    etugas_flash_redirect($rekapUrl . '?' . etugas_rekap_filter_query($filters), 'error', 'Gagal membuat file CSV.');
}

exit;
