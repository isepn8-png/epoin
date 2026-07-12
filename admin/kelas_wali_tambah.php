<?php
/******************************************************************
 * KELAS_WALI_TAMBAH — FINAL
 * - Auto-resolve path koneksi
 * - Kompatibel MySQL 5.7/8.x (tanpa VALUES() di ON DUPLICATE)
 * - CSRF + prepared statements
 ******************************************************************/

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ====== Bootstrap koneksi ======
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// ====== Auth: hanya admin (staf) yang boleh menetapkan wali kelas ======
require_once __DIR__ . '/../includes/epoin_security.php';
epoin_staff_guard(true);

// Coba beberapa lokasi umum untuk koneksi.php
$koneksi_found = false;
$try_paths = [
  __DIR__ . '/koneksi.php',               // admin/koneksi.php
  __DIR__ . '/../koneksi.php',            // e.g. epoin/koneksi.php
  dirname(__DIR__) . '/koneksi.php',      // satu level di atas admin
  __DIR__ . '/config/koneksi.php',        // admin/config/koneksi.php
  dirname(__DIR__) . '/config/koneksi.php',
];

// 1) Cari koneksi.php
foreach ($try_paths as $p) {
  if (is_file($p)) {
    require_once $p;
    $koneksi_found = true;
    break;
  }
}

// 2) Jika belum ketemu, terakhir pinjam header.php tapi bersihkan output
if (!$koneksi_found && is_file(__DIR__ . '/header.php')) {
  ob_start();
  require_once __DIR__ . '/header.php';
  ob_end_clean();
  $koneksi_found = isset($koneksi);
}

// 3) Gagal total
if (!$koneksi_found || !isset($koneksi)) {
  http_response_code(500);
  echo 'Tidak dapat menemukan file koneksi. Pastikan koneksi.php tersedia dan variabel $koneksi terdefinisi.';
  exit;
}

// ====== Helper ======
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function go($u){ header('Location: '.$u); exit; }

// ====== CSRF ======
$redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : 'kelas.php';
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
  $_SESSION['flash_error'] = 'Sesi tidak valid. Silakan ulangi.';
  go($redirect);
}

// ====== Input ======
$kelas_id = isset($_POST['kelas_id']) ? (int)$_POST['kelas_id'] : 0;
$wali_id  = isset($_POST['wali_id'])  ? (int)$_POST['wali_id']  : 0;
$wali_nip = trim($_POST['wali_nip'] ?? '');
$wali_info= trim($_POST['wali_info'] ?? '');

if ($kelas_id <= 0 || $wali_id <= 0) {
  $_SESSION['flash_error'] = 'Data tidak lengkap.';
  go($redirect);
}

// ====== Validasi: user harus role "guru" (fallback user_level='guru' untuk legacy) ======
$sqlGuru = "
  SELECT u.user_id, u.user_username
  FROM user u
  LEFT JOIN user_roles ur ON ur.user_id = u.user_id
  LEFT JOIN roles r       ON r.role_id  = ur.role_id
  WHERE u.user_id = ? AND (r.role_key = 'guru' OR u.user_level = 'guru')
  LIMIT 1";
$stmt = mysqli_prepare($koneksi, $sqlGuru);
mysqli_stmt_bind_param($stmt, "i", $wali_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res->num_rows === 0) {
  mysqli_stmt_close($stmt);
  $_SESSION['flash_error'] = 'Guru tidak ditemukan / bukan role guru.';
  go($redirect);
}
$urow = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

// Auto isi NIP dari username jika field form kosong (sesuai skema DB kamu)
if ($wali_nip === '') { $wali_nip = (string)$urow['user_username']; }

// ====== Ambil TA dari kelas ======
$sqlTa = "SELECT kelas_ta FROM kelas WHERE kelas_id = ? LIMIT 1";
$stmt = mysqli_prepare($koneksi, $sqlTa);
mysqli_stmt_bind_param($stmt, "i", $kelas_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$ta_id = 0;
if ($row = mysqli_fetch_assoc($res)) { $ta_id = (int)$row['kelas_ta']; }
mysqli_stmt_close($stmt);

if ($ta_id <= 0) {
  $_SESSION['flash_error'] = 'Kelas tidak valid / tidak memiliki Tahun Ajaran.';
  go($redirect);
}

// ====== Siapkan tabel kelas_wali bila belum ada ======
$createSql = "CREATE TABLE IF NOT EXISTS kelas_wali (
  id INT(11) NOT NULL AUTO_INCREMENT,
  ta_id INT(11) NOT NULL,
  kelas_id INT(11) NOT NULL,
  wali_user_id INT(11) NOT NULL,
  wali_nip VARCHAR(100) DEFAULT NULL,
  info VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_ta_kelas (ta_id, kelas_id),
  KEY idx_wali_user (wali_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
mysqli_query($koneksi, $createSql);

// ====== UPSERT tanpa VALUES() (kompatibel MySQL terbaru) ======
$sql = "INSERT INTO kelas_wali (ta_id, kelas_id, wali_user_id, wali_nip, info)
        VALUES (?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
          wali_user_id = ?,
          wali_nip     = ?,
          info         = ?,
          updated_at   = NOW()";
$stmt = mysqli_prepare($koneksi, $sql);
/*
  Types:
   ta_id(i), kelas_id(i), wali_user_id(i), wali_nip(s), info(s),
   UPDATE -> wali_user_id(i), wali_nip(s), info(s)
*/
mysqli_stmt_bind_param(
  $stmt,
  "iiississ",
  $ta_id, $kelas_id, $wali_id, $wali_nip, $wali_info,
  $wali_id, $wali_nip, $wali_info
);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Sukses
$_SESSION['flash_success'] = 'Wali kelas berhasil disimpan.';
go($redirect);
