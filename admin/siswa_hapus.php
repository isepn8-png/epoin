<?php
/**
 * admin/siswa_hapus.php — DINONAKTIFKAN (deprecated).
 *
 * Versi lama file ini menghapus siswa via GET tanpa autentikasi & rawan SQL
 * injection (siswa_hapus.php?id=N). Alur hapus yang benar & aman sekarang:
 *   siswa.php -> siswa_hapus_konfir.php (konfirmasi + CSRF) -> siswa_hapus_aksi.php
 *
 * File ini dipertahankan sbg guard agar URL lama/ter-bookmark tidak bisa
 * dipakai menghapus data: butuh admin, dan hanya mengarahkan ke halaman konfirmasi.
 */
require_once __DIR__ . '/../includes/epoin_security.php';
epoin_staff_guard(true);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
    header('Location: siswa_hapus_konfir.php?id=' . $id);
} else {
    header('Location: siswa.php');
}
exit;
