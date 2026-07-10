<?php
// includes/auth.php — RBAC + Login helpers (final)
// Kompatibel dengan skrip lama, plus dukungan role: tas, sekretaris, piket, guru, admin.
// Menyediakan: do_login_with_role(), ensure_logged_in(), user_has_role(), user_has_any_role(), can(), guard_roles(), guard_perms()

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../koneksi.php'; // $koneksi (mysqli)

if (!defined('APP_LOGIN_URL')) define('APP_LOGIN_URL', '/epoin/admin.php?alert=belum_login');

/* ===================== Normalisasi & helpers ===================== */
function _norm_role($r){
  $r = strtolower(trim((string)$r));
  if ($r === 'admin') $r = 'administrator';
  if (in_array($r, ['tu','staf tu','staf tu/tas'], true)) $r = 'tas';
  return $r;
}
function _esc($s){ return mysqli_real_escape_string($GLOBALS['koneksi'], (string)$s); }
function _is_numeric_username($u){ return ctype_digit((string)$u) && strlen($u) >= 8; } // NIP/NUPTK
function _verify_password($plain, $stored){
  // dukung password_hash (bcrypt) & MD5 legacy
  if (preg_match('/^\$2y\$\d{2}\$/', (string)$stored)) return password_verify($plain, $stored);
  return md5($plain) === (string)$stored;
}
function current_user_id(){ return (int)($_SESSION['id'] ?? 0); }

/* ===================== Query helpers ===================== */
function fetch_user_by_username(mysqli $db, $username){
  $sql = "SELECT user_id, user_nama, user_username, user_password, user_level, user_foto, last_login, linked_siswa_id, is_active
          FROM `user` WHERE user_username=? LIMIT 1";
  $stmt = $db->prepare($sql);
  $stmt->bind_param('s', $username);
  $stmt->execute();
  $res = $stmt->get_result();
  return $res ? $res->fetch_assoc() : null;
}
function fetch_roles_for_user(mysqli $db, $userId){
  $roles = [];
  $sql = "SELECT r.role_key
          FROM user_roles ur
          JOIN roles r ON r.role_id = ur.role_id
          WHERE ur.user_id = ?";
  $stmt = $db->prepare($sql);
  $stmt->bind_param('i',$userId);
  $stmt->execute();
  $rs = $stmt->get_result();
  while($row = $rs->fetch_assoc()) $roles[] = strtolower($row['role_key']);
  return array_values(array_unique($roles));
}
/** Ambil permission keys untuk deretan role_key */
function fetch_permissions_for_roles(mysqli $db, array $roleKeys){
  if (empty($roleKeys)) return [];
  $roleKeys = array_map(function($x){ return "'". _esc(strtolower(trim($x))) ."'"; }, $roleKeys);
  $sql = "
    SELECT p.perm_key
    FROM roles r
    JOIN role_permissions rp ON rp.role_id = r.role_id
    JOIN permissions p       ON p.perm_id  = rp.perm_id
    WHERE r.role_key IN (".implode(',', $roleKeys).")
    GROUP BY p.perm_key
  ";
  $rs = $db->query($sql);
  $out = [];
  if ($rs) while($row = $rs->fetch_assoc()) $out[] = $row['perm_key'];
  return $out;
}

/* ===================== Session helpers (kompatibel skrip lama) ===================== */
function user_has_role($roleKey){
  $roleKey = _norm_role($roleKey);
  $roles = (array)($_SESSION['roles'] ?? []);
  if ($roleKey === 'administrator') return in_array('administrator',$roles,true) || in_array('superadmin',$roles,true);
  return in_array($roleKey,$roles,true);
}
function user_has_any_role(array $keys){
  foreach($keys as $k){ if(user_has_role($k)) return true; }
  return false;
}
function can($permKey){
  // simpan perms sebagai set associative: ['perm.key'=>true]
  return !empty($_SESSION['perms'][$permKey] ?? false);
}

/* ===================== RBAC v2 — permission helpers (Sub-fase 2) =====================
   Helper SIAP dipakai, TAPI belum dipasang ke menu/handler (itu Sub-fase 4-5).
   Akses aplikasi masih 100% berbasis role seperti sekarang. */

/** Muat set permission user dari DB = UNION semua role-nya. Return ['perm.key'=>true]. */
if (!function_exists('load_user_permissions')) {
  function load_user_permissions($uid){
    global $koneksi;
    $uid = (int)$uid; $perms = [];
    if ($uid <= 0 || !($koneksi instanceof mysqli)) return $perms;
    $sql = "SELECT DISTINCT p.perm_key
            FROM user_roles ur
            JOIN role_permissions rp ON rp.role_id = ur.role_id
            JOIN permissions p       ON p.perm_id  = rp.perm_id
            WHERE ur.user_id = ?";
    if ($stmt = mysqli_prepare($koneksi, $sql)) {
      mysqli_stmt_bind_param($stmt, 'i', $uid);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      while ($res && ($row = mysqli_fetch_assoc($res))) { $perms[$row['perm_key']] = true; }
      mysqli_stmt_close($stmt);
    }
    return $perms;
  }
}

/** Apakah sesi saat ini SUPERADMIN? (wildcard akses penuh) */
if (!function_exists('epoin_is_superadmin_session')) {
  function epoin_is_superadmin_session(){
    $roles = $_SESSION['roles'] ?? [];
    if (is_array($roles)) {
      foreach ($roles as $r) {
        if (strtolower(str_replace([' ','-','_'],'',(string)$r)) === 'superadmin') return true;
      }
    }
    return strtolower((string)($_SESSION['level'] ?? '')) === 'superadmin';
  }
}

/** Cek 1 permission. Superadmin = selalu true (short-circuit). Baca cache $_SESSION['perms']. */
if (!function_exists('epoin_can')) {
  function epoin_can($permKey){
    if (epoin_is_superadmin_session()) return true;            // wildcard
    $perms = $_SESSION['perms'] ?? [];
    return is_array($perms) && !empty($perms[(string)$permKey]);
  }
}

/** True jika punya SALAH SATU permission. */
if (!function_exists('epoin_can_any')) {
  function epoin_can_any(array $keys){
    if (epoin_is_superadmin_session()) return true;
    foreach ($keys as $k) { if (epoin_can($k)) return true; }
    return false;
  }
}

/** True jika punya SEMUA permission. */
if (!function_exists('epoin_can_all')) {
  function epoin_can_all(array $keys){
    if (epoin_is_superadmin_session()) return true;
    foreach ($keys as $k) { if (!epoin_can($k)) return false; }
    return true;
  }
}

/** Refresh cache permission sesi (mis. dipanggil setelah matrix diubah). */
if (!function_exists('epoin_refresh_session_perms')) {
  function epoin_refresh_session_perms($uid){
    $_SESSION['perms'] = load_user_permissions($uid);
    return $_SESSION['perms'];
  }
}

/* ===================== RBAC cache-version stamp (M-1, blueprint §5.2) =====================
   Invalidasi cache perms/roles di session saat matrix / keanggotaan role berubah.
   Mekanisme: 1 baris di tabel `app_meta` (key 'rbac_version'). Setiap mutasi role/permission
   memanggil epoin_rbac_bump_version(); tiap request memanggil epoin_sync_session_perms()
   yang reload HANYA bila versi global != versi yang tersimpan di session.
   Aman bila tabel `app_meta` belum ada (VPS belum migrasi) → fallback ke perilaku lama. */

/** Baca rbac_version global (1 query ringan, di-cache per-request). 0 = tabel belum ada. */
if (!function_exists('epoin_rbac_version')) {
  function epoin_rbac_version(){
    global $koneksi;
    static $cached = null;                 // cek versi cukup 1x per request
    if ($cached !== null) return $cached;
    $cached = 0;
    if (!($koneksi instanceof mysqli)) return $cached;
    try {
      if ($res = @mysqli_query($koneksi, "SELECT meta_value FROM app_meta WHERE meta_key='rbac_version' LIMIT 1")) {
        if ($row = mysqli_fetch_assoc($res)) $cached = (int)$row['meta_value'];
        mysqli_free_result($res);
      }
    } catch (\Throwable $e) { $cached = 0; }  // tabel belum ada → fallback aman
    return $cached;
  }
}

/** Naikkan rbac_version global. Panggil setelah role/permission/keanggotaan-role berubah. */
if (!function_exists('epoin_rbac_bump_version')) {
  function epoin_rbac_bump_version(){
    global $koneksi;
    if (!($koneksi instanceof mysqli)) return false;
    try {
      return @mysqli_query($koneksi,
        "INSERT INTO app_meta (meta_key, meta_value) VALUES ('rbac_version','1')
         ON DUPLICATE KEY UPDATE meta_value = CAST(meta_value AS UNSIGNED) + 1");
    } catch (\Throwable $e) { return false; }  // tabel belum ada → diam (tak boleh ganggu handler)
  }
}

/** Sinkronkan cache perms/roles session dgn rbac_version global. Reload HANYA bila versi beda. */
if (!function_exists('epoin_sync_session_perms')) {
  function epoin_sync_session_perms($uid){
    $uid = (int)$uid;
    if ($uid <= 0) return;
    $global  = epoin_rbac_version();
    $have    = array_key_exists('perms_ver', $_SESSION) ? (int)$_SESSION['perms_ver'] : PHP_INT_MIN;
    $missing = !isset($_SESSION['perms']) || !is_array($_SESSION['perms'])
            || !isset($_SESSION['roles']) || !is_array($_SESSION['roles']) || empty($_SESSION['roles']);

    // VPS belum migrasi (versi 0): pertahankan perilaku lama (load sekali bila kosong).
    if ($global === 0 && !$missing) return;

    if ($have !== $global || $missing) {
      $_SESSION['perms'] = load_user_permissions($uid);
      if (function_exists('load_user_roles')) {
        $_SESSION['roles'] = load_user_roles($uid);   // role membership juga bisa berubah
      }
      $_SESSION['perms_ver'] = $global;
    }
  }
}

/* ===================== Guards ===================== */
function ensure_logged_in($need_roles = null){
  if (!isset($_SESSION['id'])) { header('Location: '.APP_LOGIN_URL); exit; }
  if ($need_roles){
    $need = is_array($need_roles) ? $need_roles : [$need_roles];
    if (!user_has_any_role($need)) { http_response_code(403); die('403 Forbidden: peran tidak memenuhi.'); }
  }
}
function guard_roles(array $allowed_roles){
  if (!user_has_any_role($allowed_roles)){ http_response_code(403); die('403'); }
}
function guard_perms(array $required_perms){
  foreach($required_perms as $p){ if (!can($p)) { http_response_code(403); die('403'); } }
}

/* ===================== Bootstrap RBAC ke session (pakai setelah login, atau untuk refresh) ===================== */
function bootstrap_rbac_for_user_id($user_id){
  global $koneksi;
  $user_id = (int)$user_id;
  if ($user_id <= 0) return false;

  // Ambil user + roles
  $sql = "SELECT u.user_id, u.user_nama, u.user_username, u.user_level, u.user_foto, u.linked_siswa_id
          FROM `user` u WHERE u.user_id={$user_id} LIMIT 1";
  $u = $koneksi->query($sql)->fetch_assoc();
  if (!$u) return false;

  $roles_arr = fetch_roles_for_user($koneksi, $user_id);
  $perms_arr = fetch_permissions_for_roles($koneksi, $roles_arr);

  // Set session
  $_SESSION['id']           = (int)$u['user_id'];
  $_SESSION['username']     = $u['user_username'];
  $_SESSION['user_nama']    = $u['user_nama'];
  $_SESSION['roles']        = $roles_arr;
  $_SESSION['level']        = $u['user_level'] ?: ( $roles_arr[0] ?? 'user' ); // fallback legacy
  $_SESSION['linked_siswa'] = (int)($u['linked_siswa_id'] ?? 0);

  // perms sebagai set
  $_SESSION['perms'] = [];
  foreach ($perms_arr as $pk) { $_SESSION['perms'][$pk] = true; }

  // [M-1] stempel versi RBAC saat login → hindari reload berlebih di request pertama.
  if (function_exists('epoin_rbac_version')) { $_SESSION['perms_ver'] = epoin_rbac_version(); }

  return true;
}

/* ===================== Proses login utama ===================== */
function do_login_with_role($username, $password, $login_as){
  global $koneksi;
  $login_as = _norm_role($login_as);
  $username = trim((string)$username);
  $password = (string)$password;

  if ($username==='' || $password==='' || $login_as===''){
    return ['ok'=>false,'err'=>'FORM_KOSONG','msg'=>'Lengkapi username/password dan pilih peran.'];
  }

  // Semua role staf (guru/tas/sekretaris/piket/administrator/superadmin) login via tabel `user`
  $user = fetch_user_by_username($koneksi, $username);
  if (!$user) return ['ok'=>false,'err'=>'USER_TIDAK_ADA','msg'=>'Akun tidak ditemukan.'];

  // Cek akun suspend sebelum verifikasi password (jangan bocorkan info akun)
  if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
    return ['ok'=>false,'err'=>'AKUN_SUSPEND','msg'=>'Akun Anda telah disuspend. Hubungi administrator.'];
  }

  if (!_verify_password($password, $user['user_password'])){
    return ['ok'=>false,'err'=>'PASS_SALAH','msg'=>'Password salah.'];
  }

  $roles_arr = fetch_roles_for_user($koneksi, (int)$user['user_id']);

  // Aturan format username (opsional, tetap dipertahankan)
  $is_nip = _is_numeric_username($username);
  if (in_array($login_as, ['administrator','superadmin'], true)){
    if ($is_nip) {
      return [
        'ok'=>false,
        'err'=>'ADMIN_NUMERIC_BLOCK',
        'msg'=>'Anda salah login: akun berbasis NIP/NUPTK tidak bisa masuk melalui pintu Administrator. Silakan pilih Guru atau Staf TU pada form login.'
      ];
    }
  } else if (in_array($login_as, ['guru','tas','sekretaris','piket'], true)){
    if (!$is_nip) {
      return [
        'ok'=>false,
        'err'=>'NIP_DIWAJIBKAN',
        'msg'=>'Untuk Guru/TU/Sekretaris/Piket gunakan NIP/NUPTK (angka).'
      ];
    }
  }

  // Validasi role terhadap DB
  $allowed = ($login_as==='administrator')
    ? (in_array('administrator',$roles_arr,true) || in_array('superadmin',$roles_arr,true))
    : in_array($login_as,$roles_arr,true);

  if (!$allowed) {
    return [
      'ok'=>false,
      'err'=>'ROLE_TIDAK_SESUAI',
      'msg'=>"Anda salah login: akun Anda tidak memiliki hak sebagai ".ucfirst($login_as).". Pilih peran yang sesuai."
    ];
  }

  // [SECURITY] Cegah session fixation: rotasi session id saat privilege naik (login sukses).
  if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); }

  // Set session utama (+ RBAC lengkap)
  $ok = bootstrap_rbac_for_user_id((int)$user['user_id']);
  if (!$ok){
    return ['ok'=>false,'err'=>'RBAC_FAIL','msg'=>'Gagal memuat hak akses.'];
  }

  // Role aktif (untuk UI lama)
  $_SESSION['active_role'] = $login_as;
  $_SESSION['level']       = $login_as; // kompatibilitas lama

  // update last_login & status_login
  $now = date('Y-m-d H:i:s'); $uid = (int)$user['user_id'];
  $stmt = $koneksi->prepare("UPDATE `user` SET last_login=?, status_login='online' WHERE user_id=?");
  $stmt->bind_param('si',$now,$uid);
  $stmt->execute();

  return ['ok'=>true,'as'=>$login_as,'uid'=>$uid,'roles'=>$roles_arr];
}

/* ===================== Logout helper (opsional) ===================== */
function do_logout(){
  if (isset($_SESSION['id'])) {
    $uid = (int)$_SESSION['id'];
    @$GLOBALS['koneksi']->query("UPDATE `user` SET status_login='offline' WHERE user_id={$uid}");
  }
  session_unset(); session_destroy();
  header('Location: '.APP_LOGIN_URL); exit;
}
