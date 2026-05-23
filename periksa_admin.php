<?php
// periksa_admin.php — proses login utk Administrator/Guru/TAS/Sekretaris
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/includes/auth.php';

// Ambil input dari form (dukung nama field 'role' atau 'sebagai')
$role     = $_POST['role']     ?? $_POST['sebagai'] ?? 'administrator';
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = $_POST['password'] ?? ''; // kirim plain; verifikasi hash di auth

// Validasi form kosong
if ($username === '' || $password === '') {
  header("Location: admin.php?alert=gagal&code=FORM_KOSONG&msg=" . urlencode('Lengkapi username/password dan pilih peran.'));
  exit;
}

// Jalankan login dengan kontrol role
$res = do_login_with_role($username, $password, $role);
if (!$res['ok']) {
  // Kirim alasan yang jelas agar muncul notif "Anda salah login", dsb.
  $code = urlencode($res['err']);
  $msg  = urlencode($res['msg']); // aman karena ditampilkan sebagai teks di UI
  header("Location: admin.php?alert=gagal&code={$code}&msg={$msg}");
  exit;
}

// ---- Sukses: arahkan ke dashboard ----
$as    = $res['as'];
$roles = $res['roles'] ?? [];

// Notifikasi sukses sederhana (ditangkap di dashboard kalau diinginkan)
$_SESSION['login_notice'] = "Anda berhasil masuk sebagai " . (($as==='tas') ? 'Staf TU' : ucfirst($as)) . ".";
if (($as==='administrator' || $as==='superadmin') && in_array('guru',$roles,true)){
  $_SESSION['login_notice'] .= " Catatan: ini bukan sesi Guru. Jika ingin menu Guru, logout lalu pilih Guru saat login.";
}
if ($as==='guru' && (in_array('administrator',$roles,true) || in_array('superadmin',$roles,true))){
  $_SESSION['login_notice'] .= " (Akun Anda juga punya hak Administrator—login sebagai Admin bila perlu mengelola sistem.)";
}

header("Location: admin/");
exit;
