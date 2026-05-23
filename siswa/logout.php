<?php
// siswa/logout.php
// ======================================================
// Logout instan (tanpa UI, tanpa loader).
// - Update status_login siswa -> 'offline'
// - Hapus sesi & cookie sesi
// - Redirect ke halaman login dengan alert=logout
// ======================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session aman
if (function_exists('session_status')) {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    @ini_set('session.use_only_cookies', 1);
    if (!headers_sent()) { @session_start(); }
  }
} else {
  if (session_id() === '' || !isset($_SESSION)) {
    if (!headers_sent()) { @session_start(); }
  }
}

// Koneksi DB
require_once __DIR__ . '/../koneksi.php';

// Jika siswa sedang login, tandai offline
if (isset($_SESSION['level'], $_SESSION['id']) && $_SESSION['level'] === 'siswa') {
  $sid = (int) $_SESSION['id'];
  if ($sid > 0) {
    @mysqli_query($koneksi, "UPDATE siswa SET status_login = 'offline' WHERE siswa_id = ".$sid);
  }
}

// Bersihkan semua data sesi
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], true);
}
@session_destroy();

// Arahkan kembali ke halaman login
header("Location: ../index.php?alert=logout");
exit;
