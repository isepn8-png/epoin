<?php
// admin/get_status.php — versi yang memisahkan Sekretaris (siswa) dari PTK & Admin
header('Content-Type: application/json; charset=utf-8');
include '../koneksi.php';
mysqli_set_charset($koneksi, 'utf8mb4');

// sanitasi parameter
$limit = isset($_GET['limit']) ? max(1, min(2000, (int)$_GET['limit'])) : 1000;
$include_sekretaris = isset($_GET['include_sekretaris']) && $_GET['include_sekretaris'] == '1';

// ===================
//  Ambil data PTK & Admin
//  Catatan: kita join ke v_users_with_roles untuk baca daftar roles,
//  lalu BUANG akun yang (a) terhubung ke siswa (linked_siswa_id != null) dan
//  (b) punya role 'sekretaris', KECUALI jika include_sekretaris=1.
// ===================
$sqlAdmin = "
  SELECT
    u.user_id,
    u.user_nama   AS nama,
    u.last_login,
    u.status_login,
    u.linked_siswa_id,
    COALESCE(v.roles,'') AS roles
  FROM user u
  LEFT JOIN v_users_with_roles v ON v.user_id = u.user_id
  ORDER BY u.last_login DESC
  LIMIT {$limit}
";

$admin_res = mysqli_query($koneksi, $sqlAdmin);
$admin_data = [];
if ($admin_res) {
  while ($r = mysqli_fetch_assoc($admin_res)) {
    $is_sekretaris = (!empty($r['linked_siswa_id']) && stripos($r['roles'], 'sekretaris') !== false);
    if (!$include_sekretaris && $is_sekretaris) {
      // skip sekretaris siswa dari panel PTK & Admin (default)
      continue;
    }
    unset($r['linked_siswa_id']); // tidak perlu dikembalikan ke UI
    $admin_data[] = $r;
  }
}

// ===================
//  Ambil data Siswa (+flag is_sekretaris)
//  Kita cek apakah ada akun user yg tertaut ke siswa tsb dgn role 'sekretaris'.
// ===================
$sqlSiswa = "
  SELECT
    s.siswa_id,
    s.siswa_nama AS nama,
    s.last_login,
    s.status_login,
    EXISTS(
      SELECT 1
      FROM user u
      JOIN user_roles ur ON ur.user_id = u.user_id
      JOIN roles r      ON r.role_id = ur.role_id
      WHERE u.linked_siswa_id = s.siswa_id
        AND r.role_key = 'sekretaris'
    ) AS is_sekretaris
  FROM siswa s
  ORDER BY s.last_login DESC
  LIMIT {$limit}
";

$siswa_res = mysqli_query($koneksi, $sqlSiswa);
$siswa_data = [];
if ($siswa_res) {
  while ($s = mysqli_fetch_assoc($siswa_res)) {
    // normalisasi boolean untuk frontend
    $s['is_sekretaris'] = (int)$s['is_sekretaris'] === 1;
    $siswa_data[] = $s;
  }
}

// meta waktu server untuk stempel "Terakhir diperbarui"
$meta = ['generated_at' => date('Y-m-d H:i:s')];

// keluaran JSON
echo json_encode([
  'meta'  => $meta,
  'admin' => $admin_data,
  'siswa' => $siswa_data
], JSON_UNESCAPED_UNICODE);
