<?php
// verify_pin.php
session_start();
header('Content-Type: application/json');

// Wajib admin yang aktif
if (!isset($_SESSION['level']) || $_SESSION['level'] !== 'administrator') {
  echo json_encode(['ok' => false, 'msg' => 'Unauthorized']);
  exit;
}

/**
 * ====== KONFIG PIN ======
 * Ubah PIN di sini. Contoh: '246810' atau '123456'
 * Tidak pakai DB. Disimpan di sisi server (tidak terekspos di browser).
 */
$STATIC_PIN = '123123';   // <-- GANTI sesuai kebutuhan

// Lama sesi setelah PIN benar (detik). 900 = 15 menit.
// Set 0 bila ingin diminta PIN setiap kali akses.
$MASTER_PIN_TTL = 600;

/** ====================== **/

$pin = isset($_POST['pin']) ? trim($_POST['pin']) : '';
if ($pin === '') {
  echo json_encode(['ok' => false, 'msg' => 'PIN wajib diisi.']);
  exit;
}

// Verifikasi sederhana (bisa ditingkatkan jadi hash_equals(sha256) jika mau)
if (hash_equals($STATIC_PIN, $pin)) {
  if ($MASTER_PIN_TTL > 0) {
    $_SESSION['MASTER_PIN_OK_UNTIL'] = time() + $MASTER_PIN_TTL;
  } else {
    // TTL 0 -> minta lagi tiap akses; set 1 detik agar selalu dianggap expired
    $_SESSION['MASTER_PIN_OK_UNTIL'] = time() - 1;
  }
  echo json_encode(['ok' => true]);
} else {
  echo json_encode(['ok' => false, 'msg' => 'PIN salah.']);
}
