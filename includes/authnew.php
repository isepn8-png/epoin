<?php
// includes/auth.php — RBAC bootstrap & helpers
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../koneksi.php';

/* ========= CONFIG ========= */
if (!defined('APP_LOGIN_URL')) define('APP_LOGIN_URL', '/epoin/admin.php?alert=belum_login');

/* ========= Helpers ========= */
function _db(){ return $GLOBALS['koneksi']; }
function _esc($s){ return mysqli_real_escape_string(_db(), (string)$s); }
function _int($v){ return (int)$v; }
function _arr($v){ return is_array($v) ? $v : (array)$v; }

/** Normalisasi key role */
function normalize_role_key($k){
  $k = strtolower(trim((string)$k));
  if ($k === 'admin') $k = 'administrator';
  return $k;
}

/** Pastikan sudah login (minimal punya $_SESSION['id']) */
function ensure_logged_in(){
  if (!isset($_SESSION['id']) || !$_SESSION['id']){
    header('Location: '.APP_LOGIN_URL); exit;
  }
}

/** Muat roles & permissions ke session (panggil setelah login sukses) */
function bootstrap_rbac_for($user_id){
  $user_id = _int($user_id);
  $q = "
    SELECT u.user_id, u.user_nama, u.user_level,
           GROUP_CONCAT(r.role_key ORDER BY r.role_key SEPARATOR ',') AS roles
    FROM user u
    LEFT JOIN user_roles ur ON ur.user_id = u.user_id
    LEFT JOIN roles r       ON r.role_id = ur.role_id
    WHERE u.user_id = {$user_id}
    GROUP BY u.user_id
  ";
  $u = mysqli_fetch_assoc(mysqli_query(_db(), $q));
  if (!$u) return false;

  $_SESSION['id']         = (int)$u['user_id'];
  $_SESSION['user_nama']  = $u['user_nama'];
  $_SESSION['user_level'] = $u['user_level']; // legacy support
  $_SESSION['roles']      = array_values(array_filter(array_map('normalize_role_key', explode(',', (string)($u['roles'] ?? '')))));

  // Build permissions
  $_SESSION['perms'] = [];
  if (!empty($_SESSION['roles'])){
    $roleKeys = array_map(fn($x)=>"'"+_esc($x)+"'", $_SESSION['roles']); // escaped
    $sql = "
      SELECT p.perm_key
      FROM roles r
      JOIN role_permissions rp ON rp.role_id = r.role_id
      JOIN permissions p       ON p.perm_id  = rp.perm_id
      WHERE r.role_key IN (".implode(',', $roleKeys).")
      GROUP BY p.perm_key
    ";
    $rs = mysqli_query(_db(), $sql);
    while($row = mysqli_fetch_assoc($rs)){
      $_SESSION['perms'][ $row['perm_key'] ] = true;
    }
  }
  return true;
}

/** Cek role */
function user_has_role($key){
  $key = normalize_role_key($key);
  $roles = $_SESSION['roles'] ?? [];
  return in_array($key, $roles, true);
}
/** Cek salah satu dari beberapa role */
function user_has_any_role($keys){
  foreach (_arr($keys) as $k){
    if (user_has_role($k)) return true;
  }
  return false;
}
/** Cek izin (permission) */
function can($perm_key){
  return !empty($_SESSION['perms'][$perm_key] ?? null);
}

/** Quick guard */
function guard_roles($allowed_roles){
  if (!user_has_any_role($allowed_roles)){
    header('HTTP/1.1 403 Forbidden'); exit('403');
  }
}

/** CSRF sederhana */
function csrf_token(){
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}
function csrf_check($token){
  return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$token);
}
