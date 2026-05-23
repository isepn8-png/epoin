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
  $sql = "SELECT user_id, user_nama, user_username, user_password, user_level, user_foto, last_login, linked_siswa_id
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
