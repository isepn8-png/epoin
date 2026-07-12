<?php
// ========================
// siswa_import_act.php
// Disesuaikan untuk DB: smpn1gun_epoint (tabel siswa & jurusan)
// Diperbarui: 10 Sep 2025 (Asia/Jakarta)
// ========================

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// ========================
// Auth + CSRF (wajib)
// ========================
require_once __DIR__ . '/../includes/epoin_security.php';
epoin_staff_guard(true);
if (!epoin_csrf_validate()) {
  http_response_code(403);
  die('Token CSRF tidak valid. Silakan muat ulang halaman dan coba lagi.');
}

// ========================
// Konfigurasi dasar
// ========================
$MAX_BYTES = 5 * 1024 * 1024; // 5 MB
$ALLOWED_STATUSES = ['aktif', 'tamat', 'pindah', 'dikeluarkan'];

// ========================
// Autoload Composer (PhpSpreadsheet)
// ========================
$autoloadPaths = [
  __DIR__ . '/../vendor/autoload.php',
  __DIR__ . '/vendor/autoload.php',
  __DIR__ . '/../../vendor/autoload.php',
];
$autoloadFound = false;
foreach ($autoloadPaths as $p) {
  if (file_exists($p)) { require_once $p; $autoloadFound = true; break; }
}
if (!$autoloadFound) {
  http_response_code(500);
  die('Gagal memuat vendor/autoload.php. Jalankan "composer require phpoffice/phpspreadsheet".');
}

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlsDate;

// ========================
// Koneksi DB (mysqli)
// Pastikan file koneksi.php menginisialisasi $koneksi (mysqli)
// ========================
$koneksiPaths = [
  __DIR__ . '/koneksi.php',
  __DIR__ . '/../koneksi.php',
  __DIR__ . '/../../koneksi.php',
  __DIR__ . '/config/koneksi.php',
];
$koneksi = null;
foreach ($koneksiPaths as $kp) {
  if (file_exists($kp)) { include $kp; break; }
}
if (!$koneksi || !($koneksi instanceof mysqli)) {
  http_response_code(500);
  die('Koneksi database tidak tersedia. Pastikan koneksi.php ter-load dan variabel $koneksi ada.');
}

// ========================
// Validasi upload file
// ========================
if (empty($_FILES['berkas']) || !is_uploaded_file($_FILES['berkas']['tmp_name'])) {
  die('Tidak ada file yang diunggah.');
}
$file = $_FILES['berkas'];
if ($file['error'] !== UPLOAD_ERR_OK) {
  die('Terjadi kesalahan upload (code: ' . (int)$file['error'] . ').');
}
if ($file['size'] > $MAX_BYTES) {
  die('Ukuran file melebihi 5 MB.');
}
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'xlsx') {
  die('Format tidak didukung. Hanya file .xlsx yang diperbolehkan.');
}

// ========================
// Pindahkan ke lokasi sementara
// ========================
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import_siswa_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.xlsx';
if (!move_uploaded_file($file['tmp_name'], $tmp)) {
  die('Gagal memproses file yang diunggah.');
}

// ========================
// Baca Excel
// ========================
try {
  $reader = IOFactory::createReader('Xlsx');
  $reader->setReadDataOnly(true);
  $spreadsheet = $reader->load($tmp);
  $sheet = $spreadsheet->getSheet(0);
} catch (Throwable $e) {
  @unlink($tmp);
  die('Gagal membaca file Excel: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// ========================
// Validasi header
// ========================
$highestRow = $sheet->getHighestRow();
$highestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());

if ($highestRow < 2) {
  @unlink($tmp);
  die('Tidak ada data. Baris pertama adalah header, data mulai baris ke-2.');
}

$headerMap = []; // label uppercase => colIndex
for ($c = 1; $c <= $highestCol; $c++) {
  $label = strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, 1)->getValue()));
  if ($label !== '') $headerMap[$label] = $c;
}
$required = ['NIS', 'NAMA', 'STATUS', 'JURUSAN_ID'];
foreach ($required as $req) {
  if (!isset($headerMap[$req])) {
    @unlink($tmp);
    die('Header wajib tidak ditemukan: ' . htmlspecialchars($req, ENT_QUOTES, 'UTF-8') . '. Unduh template terbaru lewat tombol "Unduh Template" di halaman Import.');
  }
}

// ========================
// Load jurusan valid
// ========================
$validJurusan = [];
$qJ = mysqli_query($koneksi, "SELECT jurusan_id FROM jurusan");
if ($qJ) {
  while ($r = mysqli_fetch_assoc($qJ)) {
    $validJurusan[(string)$r['jurusan_id']] = true;
  }
} else {
  @unlink($tmp);
  die('Gagal memuat data jurusan dari database.');
}

// ========================
// Siapkan statement SQL (cek, insert, update)
// Struktur tabel siswa: siswa_nama, siswa_nis, siswa_jurusan, siswa_status, siswa_password, ... (lihat dump)
// ========================
$sqlCheck  = "SELECT siswa_id FROM siswa WHERE siswa_nis = ?";
$sqlInsert = "INSERT INTO siswa (siswa_nama, siswa_nis, siswa_jurusan, siswa_status, siswa_password) VALUES (?,?,?,?,?)";
$sqlUpdate = "UPDATE siswa SET siswa_nama=?, siswa_jurusan=?, siswa_status=? WHERE siswa_nis=?";

$stCheck  = mysqli_prepare($koneksi, $sqlCheck);
$stInsert = mysqli_prepare($koneksi, $sqlInsert);
$stUpdate = mysqli_prepare($koneksi, $sqlUpdate);

if (!$stCheck || !$stInsert || !$stUpdate) {
  @unlink($tmp);
  die('Gagal menyiapkan statement SQL. Periksa struktur tabel & privilege DB.');
}

// ========================
// Proses baris
// ========================
$ins = 0; $upd = 0; $err = [];
mysqli_begin_transaction($koneksi);

try {
  for ($row = 2; $row <= $highestRow; $row++) {
    $nisIdx = $headerMap['NIS'];
    $namaIdx = $headerMap['NAMA'];
    $statusIdx = $headerMap['STATUS'];
    $jurIdx = $headerMap['JURUSAN_ID'];

    $nis  = trim((string)$sheet->getCellByColumnAndRow($nisIdx, $row)->getValue());
    $nama = trim((string)$sheet->getCellByColumnAndRow($namaIdx, $row)->getValue());
    $stat = strtolower(trim((string)$sheet->getCellByColumnAndRow($statusIdx, $row)->getValue()));
    $jur  = trim((string)$sheet->getCellByColumnAndRow($jurIdx, $row)->getValue());

    // Lewati baris kosong total
    if ($nis === '' && $nama === '' && $stat === '' && $jur === '') {
      continue;
    }

    // Validasi
    $rowErr = [];
    if ($nis === '') $rowErr[] = 'NIS kosong';
    if ($nama === '') $rowErr[] = 'NAMA kosong';
    if ($stat === '' || !in_array($stat, $ALLOWED_STATUSES, true)) $rowErr[] = 'STATUS tidak valid (aktif/tamat/pindah/dikeluarkan)';
    if ($jur === '' || !isset($validJurusan[$jur])) $rowErr[] = 'JURUSAN_ID tidak ditemukan';

    if (!empty($rowErr)) {
      $err[] = ['row' => $row, 'nis' => $nis, 'msg' => implode('; ', $rowErr)];
      continue;
    }

    // Cek ada/tidak (berdasarkan NIS)
    mysqli_stmt_bind_param($stCheck, 's', $nis);
    if (!mysqli_stmt_execute($stCheck)) {
      throw new Exception('Gagal eksekusi cek NIS: ' . mysqli_error($koneksi));
    }
    $rs = mysqli_stmt_get_result($stCheck);
    $exists = $rs && mysqli_fetch_assoc($rs);

    if ($exists) {
      // UPDATE: nama, jurusan, status (password tidak diubah)
      mysqli_stmt_bind_param($stUpdate, 'ssss', $nama, $jur, $stat, $nis);
      if (!mysqli_stmt_execute($stUpdate)) {
        $err[] = ['row' => $row, 'nis' => $nis, 'msg' => 'Gagal UPDATE: ' . mysqli_error($koneksi)];
        continue;
      }
      $upd++;
    } else {
      // INSERT: isi password md5(NIS)
      $pwd = md5($nis);
      mysqli_stmt_bind_param($stInsert, 'sssss', $nama, $nis, $jur, $stat, $pwd);
      if (!mysqli_stmt_execute($stInsert)) {
        $err[] = ['row' => $row, 'nis' => $nis, 'msg' => 'Gagal INSERT: ' . mysqli_error($koneksi)];
        continue;
      }
      $ins++;
    }
  }

  mysqli_commit($koneksi);

} catch (Throwable $e) {
  mysqli_rollback($koneksi);
  @unlink($tmp);
  die('Terjadi kesalahan saat import: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
} finally {
  @unlink($tmp);
}

// ========================
// Tampilkan Hasil
// ========================
$totalErr = count($err);
$total = $ins + $upd + $totalErr;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Hasil Import Siswa</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<style>
  body { font-family: Arial, sans-serif; background:#f7f9fc; padding: 24px; }
  .card { background:#fff; border-radius:12px; box-shadow:0 10px 24px rgba(43,71,133,.08); margin-bottom:20px; overflow:hidden; }
  .card-header { padding:16px 20px; background:linear-gradient(90deg, #1e88e5, #42a5f5); color:#fff; font-weight:700; }
  .card-body { padding:20px; }
  .stat { display:inline-block; margin-right:22px; padding:8px 12px; background:#eef5ff; border-radius:8px; }
  .stat .num { font-weight:800; font-size:18px; }
  .btn { display:inline-block; padding:10px 14px; border-radius:8px; text-decoration:none; }
  .btn-primary { background:#1e88e5; color:#fff; }
  .btn-ghost { background:#e3f2fd; color:#1565c0; }
  table.dataTable thead th { background:#f1f5ff; }
</style>
</head>
<body>

<div class="card">
  <div class="card-header">Rekap Hasil Import</div>
  <div class="card-body">
    <div class="stat">Diproses: <span class="num"><?php echo (int)$total; ?></span></div>
    <div class="stat" style="background:#e8f5e9;">Insert: <span class="num" style="color:#2e7d32;"><?php echo (int)$ins; ?></span></div>
    <div class="stat" style="background:#e3f2fd;">Update: <span class="num" style="color:#1565c0;"><?php echo (int)$upd; ?></span></div>
    <div class="stat" style="background:#ffebee;">Error: <span class="num" style="color:#c62828;"><?php echo (int)$totalErr; ?></span></div>
    <div style="margin-top:14px;">
      <a class="btn btn-primary" href="siswa_import.php">&larr; Kembali ke Import</a>
      <a class="btn btn-ghost" href="siswa.php">Ke Daftar Siswa</a>
      <a class="btn btn-ghost" href="siswa_template.php">Unduh Template XLSX</a>
    </div>
    <div style="margin-top:8px;color:#607d8b;font-size:12px;">
      Password untuk baris INSERT baru diset ke <b>md5(NIS)</b>. Update tidak mengubah password.
    </div>
  </div>
</div>

<?php if ($totalErr > 0): ?>
<div class="card">
  <div class="card-header">Detail Error (<?php echo (int)$totalErr; ?>)</div>
  <div class="card-body">
    <table id="tblErr" class="display" style="width:100%">
      <thead>
        <tr><th>Baris</th><th>NIS</th><th>Pesan</th></tr>
      </thead>
      <tbody>
        <?php foreach ($err as $e): ?>
          <tr>
            <td><?php echo (int)$e['row']; ?></td>
            <td><?php echo htmlspecialchars($e['nis'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($e['msg'], ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script>
$(function(){
  $('#tblErr').DataTable({
    pageLength: 10,
    lengthChange: false,
    language: {
      search: "Cari:",
      paginate: { previous: "Sebelumnya", next: "Berikutnya" },
      info: "Menampilkan _START_ - _END_ dari _TOTAL_ baris"
    }
  });
});
</script>
</body>
</html>
