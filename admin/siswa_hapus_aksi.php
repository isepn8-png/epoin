<?php
// ==== ACTION ONLY (tanpa header/footer) ====
require_once __DIR__ . '/../includes/epoin_security.php';
require_once '../koneksi.php';
epoin_staff_guard();
epoin_require_post();
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('siswa.php');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) { http_response_code(400); exit('ID tidak valid'); }

// ---------- helpers ----------
function table_exists(mysqli $db, string $tbl, bool $baseOnly = true): bool {
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
          ".($baseOnly ? "AND TABLE_TYPE = 'BASE TABLE'" : "")."
          LIMIT 1";
  $st = $db->prepare($sql); $st->bind_param('s', $tbl); $st->execute();
  $r = $st->get_result(); return (bool)$r->fetch_row();
}
function col_exists(mysqli $db, string $tbl, string $col, bool $baseOnly = true): bool {
  $sql = "SELECT 1
          FROM INFORMATION_SCHEMA.COLUMNS c
          JOIN INFORMATION_SCHEMA.TABLES t
            ON c.TABLE_SCHEMA = t.TABLE_SCHEMA
           AND c.TABLE_NAME  = t.TABLE_NAME
          WHERE c.TABLE_SCHEMA = DATABASE()
            AND c.TABLE_NAME = ?
            AND c.COLUMN_NAME = ?
            ".($baseOnly ? "AND t.TABLE_TYPE = 'BASE TABLE'" : "")."
          LIMIT 1";
  $st = $db->prepare($sql); $st->bind_param('ss', $tbl, $col); $st->execute();
  $r = $st->get_result(); return (bool)$r->fetch_row();
}
function delete_if_exists(mysqli $db, string $tbl, string $col, int $id): void {
  if (!table_exists($db, $tbl, true)) return;             // skip VIEW
  if (!col_exists($db, $tbl, $col, true)) return;
  $sql = "DELETE FROM `$tbl` WHERE `$col` = ?";
  $st = $db->prepare($sql); $st->bind_param('i', $id); $st->execute();
}

if (session_status() === PHP_SESSION_NONE) session_start();

// --- ambil PK & kolom nama yang ada ---
$pk = col_exists($koneksi,'siswa','siswa_id',true) ? 'siswa_id' : 'id';

// cari nama siswa (dinamis: pakai kolom yang mengandung "nama")
$nameCol = null;
foreach (['nama','siswa_nama','nama_siswa','nama_lengkap','nm_siswa'] as $c) {
  if (col_exists($koneksi,'siswa',$c,true)) { $nameCol = $c; break; }
}
$kodeCol = null; // NISN/NIS opsional untuk ditampilkan
foreach (['nisn','nis'] as $c) {
  if (col_exists($koneksi,'siswa',$c,true)) { $kodeCol = $c; break; }
}

$namaSiswa = ''; $kode = '';
if ($nameCol) {
  $sql = "SELECT `$nameCol`".($kodeCol? ", `$kodeCol`": "")." FROM `siswa` WHERE `$pk`=? LIMIT 1";
  $st  = $koneksi->prepare($sql);
  $st->bind_param('i',$id);
  $st->execute();
  if ($row = $st->get_result()->fetch_assoc()) {
    $namaSiswa = (string)$row[$nameCol];
    if ($kodeCol) $kode = (string)$row[$kodeCol];
  }
}

try {
  $koneksi->begin_transaction();

  // 0) Putus link user->siswa (FK pada user sudah SET NULL, ini sekadar eksplisit)
  if (table_exists($koneksi, 'user', true) && col_exists($koneksi, 'user', 'linked_siswa_id', true)) {
    $st = $koneksi->prepare("UPDATE `user` SET linked_siswa_id = NULL WHERE linked_siswa_id = ?");
    $st->bind_param('i', $id); $st->execute();
  }

  // 1) Hapus tabel2 anak yang umum (berurutan, aman FK)
  delete_if_exists($koneksi, 'absensi_harian_detail', 'siswa_id', $id);
  delete_if_exists($koneksi, 'absensi_sesi_detail',  'siswa_id', $id);
  delete_if_exists($koneksi, 'permohonan_absensi',   'siswa_id', $id);

  delete_if_exists($koneksi, 'nilai_harian', 'siswa_id', $id);
  delete_if_exists($koneksi, 'nilai_pts',    'siswa_id', $id);
  delete_if_exists($koneksi, 'nilai_sas',    'siswa_id', $id); // kalau ada

  delete_if_exists($koneksi, 'ujian_gform_attempt', 'siswa_id', $id);
  delete_if_exists($koneksi, 'ujian_gform_violation', 'siswa_id', $id);

  delete_if_exists($koneksi, 'kelas_siswa', 'ks_siswa', $id);

  // 2) Sapu otomatis ke SEMUA BASE TABLE yang punya kolom siswa_id / ks_siswa
  //    (skip VIEW agar tidak muncul error "not updatable")
  $already = []; // hindari duplikat
  $cols = ['siswa_id','ks_siswa'];
  $q = $koneksi->prepare("
    SELECT c.TABLE_NAME
    FROM INFORMATION_SCHEMA.COLUMNS c
    JOIN INFORMATION_SCHEMA.TABLES t
      ON c.TABLE_SCHEMA = t.TABLE_SCHEMA
     AND c.TABLE_NAME  = t.TABLE_NAME
    WHERE c.TABLE_SCHEMA = DATABASE()
      AND c.COLUMN_NAME = ?
      AND t.TABLE_TYPE = 'BASE TABLE'
  ");
  foreach ($cols as $col) {
    $q->bind_param('s', $col);
    $q->execute();
    $res = $q->get_result();
    while ($row = $res->fetch_assoc()) {
      $tbl = $row['TABLE_NAME'];
      if ($tbl === 'siswa') continue;         // induk dihapus terakhir
      if (isset($already["$tbl|$col"])) continue;
      $already["$tbl|$col"] = true;
      // eksekusi delete aman
      delete_if_exists($koneksi, $tbl, $col, $id);
    }
  }

  // 3) Hapus record siswa (pilih PK yang ada)
  $pk = col_exists($koneksi, 'siswa', 'siswa_id', true) ? 'siswa_id' : 'id';
  $st = $koneksi->prepare("DELETE FROM `siswa` WHERE `$pk` = ?");
  $st->bind_param('i', $id); $st->execute();

  $koneksi->commit();
  $_SESSION['flash_ok'] =
  'Data siswa <b>'.htmlspecialchars($namaSiswa ?: ('#'.$id)).'</b>'
  .($kode ? ' ('.htmlspecialchars(strtoupper($kodeCol)).': '.htmlspecialchars($kode).')' : '')
  .' berhasil dihapus.';

  header('Location: siswa.php?alert=hapus_ok'); exit;

} catch (Throwable $e) {
  $koneksi->rollback();
  http_response_code(500);
  echo "Gagal menghapus: " . htmlspecialchars($e->getMessage());
}
