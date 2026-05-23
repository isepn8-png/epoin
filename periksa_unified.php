<?php
// periksa_unified.php — router ke skrip login lama (SISWA vs ADMIN/GURU/TAS)
// Pastikan tidak ada spasi/karakter sebelum <?php (biar header() aman)
ob_start();
session_start();

// Ambil role dari form gabungan
$role = isset($_POST['role']) ? strtolower(trim($_POST['role'])) : '';

// Samakan nama field password agar cocok dengan skrip lama
// - periksa_login.php & periksa_admin.php membaca $_POST['password']
if (!isset($_POST['password'])) {
  if (!empty($_POST['password_siswa'])) $_POST['password'] = $_POST['password_siswa'];
  if (!empty($_POST['password_user']))  $_POST['password'] = $_POST['password_user'];
}

// Validasi minimal
if ($role === 'siswa') {
  if (empty($_POST['nis']) || empty($_POST['password'])) {
    header("Location: login.php?alert=gagal"); exit;
  }
  // Teruskan ke skrip lama (login siswa)
  require __DIR__ . '/periksa_login.php';
  exit;
}

// Default: guru / tas / admin → pakai jalur admin lama
if (empty($_POST['username']) || empty($_POST['password'])) {
  header("Location: login.php?alert=gagal"); exit;
}

// Boleh lempar role, jika nanti kamu ingin validasi level di periksa_admin.php
$_POST['role'] = $role;

// Teruskan ke skrip lama (login admin/guru/tas)
require __DIR__ . '/periksa_admin.php';
exit;

// (Jangan pakai closing tag ?>)
