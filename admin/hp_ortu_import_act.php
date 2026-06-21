<?php
// ============================================================
// admin/hp_ortu_import_act.php — Proses import HP Ortu dari Excel
// Format: kolom NIS + HP_ORTU → UPDATE siswa.hp_ortu by NIS
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/epoin_security.php';
epoin_staff_guard(false);

// CSRF
if (!epoin_csrf_validate()) {
  http_response_code(400);
  die('Token CSRF tidak valid. Silakan kembali dan coba lagi.');
}

// Autoload PhpSpreadsheet
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
  die('Gagal memuat vendor/autoload.php. Jalankan "composer require phpoffice/phpspreadsheet".');
}
require_once $autoload;
use PhpOffice\PhpSpreadsheet\IOFactory;

// DB
require_once __DIR__ . '/../koneksi.php';

// Validasi file upload
if (empty($_FILES['berkas']) || !is_uploaded_file($_FILES['berkas']['tmp_name'])) {
  die('Tidak ada file yang diunggah.');
}
$file = $_FILES['berkas'];
if ($file['error'] !== UPLOAD_ERR_OK) {
  die('Kesalahan upload (code: '.(int)$file['error'].').');
}
if ($file['size'] > 5 * 1024 * 1024) {
  die('Ukuran file melebihi 5 MB.');
}
if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
  die('Format tidak didukung. Hanya file .xlsx yang diperbolehkan.');
}

// Pindah ke temp
$tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hp_ortu_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.xlsx';
if (!move_uploaded_file($file['tmp_name'], $tmp)) {
  die('Gagal memproses file yang diunggah.');
}

// Baca Excel
try {
  $reader = IOFactory::createReader('Xlsx');
  $reader->setReadDataOnly(true);
  $spreadsheet = $reader->load($tmp);
  $sheet = $spreadsheet->getSheet(0);
} catch (Throwable $e) {
  @unlink($tmp);
  die('Gagal membaca file Excel: '.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
} finally {
  @unlink($tmp);
}

$highestRow = $sheet->getHighestRow();
$highestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());

if ($highestRow < 2) {
  die('Tidak ada data. Baris pertama adalah header, data mulai baris ke-2.');
}

// Map header (NIS, HP_ORTU)
$headerMap = [];
for ($c = 1; $c <= $highestCol; $c++) {
  $label = strtoupper(trim((string)$sheet->getCellByColumnAndRow($c, 1)->getValue()));
  if ($label !== '') $headerMap[$label] = $c;
}
foreach (['NIS','HP_ORTU'] as $req) {
  if (!isset($headerMap[$req])) {
    die('Header wajib tidak ditemukan: '.htmlspecialchars($req, ENT_QUOTES, 'UTF-8').'. Gunakan template yang disediakan.');
  }
}

// Prepared statements
$stCheck  = mysqli_prepare($koneksi, 'SELECT siswa_id FROM siswa WHERE siswa_nis=? LIMIT 1');
$stUpdate = mysqli_prepare($koneksi, 'UPDATE siswa SET hp_ortu=? WHERE siswa_id=?');
if (!$stCheck || !$stUpdate) {
  die('Gagal menyiapkan statement SQL.');
}

$ok = 0; $skip = 0; $errRows = [];

mysqli_begin_transaction($koneksi);
try {
  for ($row = 2; $row <= $highestRow; $row++) {
    $nis = trim((string)$sheet->getCellByColumnAndRow($headerMap['NIS'], $row)->getValue());
    $hp  = preg_replace('/\D+/', '', trim((string)$sheet->getCellByColumnAndRow($headerMap['HP_ORTU'], $row)->getValue()));

    if ($nis === '' && $hp === '') continue; // baris kosong

    if ($nis === '') {
      $errRows[] = ['row'=>$row,'nis'=>$nis,'msg'=>'NIS kosong'];
      continue;
    }
    if ($hp === '' || strlen($hp) < 10 || strlen($hp) > 15) {
      $errRows[] = ['row'=>$row,'nis'=>$nis,'msg'=>'HP tidak valid (kosong atau panjang tidak wajar)'];
      continue;
    }

    // Cari siswa_id by NIS
    mysqli_stmt_bind_param($stCheck, 's', $nis);
    if (!mysqli_stmt_execute($stCheck)) {
      $errRows[] = ['row'=>$row,'nis'=>$nis,'msg'=>'Gagal cek NIS: '.mysqli_error($koneksi)];
      continue;
    }
    $rs = mysqli_stmt_get_result($stCheck);
    $found = $rs ? mysqli_fetch_assoc($rs) : null;

    if (!$found) {
      $skip++;
      $errRows[] = ['row'=>$row,'nis'=>$nis,'msg'=>'NIS tidak ditemukan di database (di-skip)'];
      continue;
    }

    $siswaId = (int)$found['siswa_id'];
    mysqli_stmt_bind_param($stUpdate, 'si', $hp, $siswaId);
    if (mysqli_stmt_execute($stUpdate)) {
      $ok++;
    } else {
      $errRows[] = ['row'=>$row,'nis'=>$nis,'msg'=>'Gagal UPDATE: '.mysqli_error($koneksi)];
    }
  }
  mysqli_commit($koneksi);
} catch (Throwable $e) {
  mysqli_rollback($koneksi);
  die('Error saat proses: '.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$totalErr  = count($errRows);
$totalProc = $ok + $totalErr;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Hasil Import HP Ortu</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{font-family:Arial,sans-serif;background:#f7f9fc;padding:24px}
.card{background:#fff;border-radius:12px;box-shadow:0 10px 24px rgba(43,71,133,.08);margin-bottom:20px;overflow:hidden}
.card-hdr{padding:14px 20px;color:#fff;font-weight:700;font-size:15px}
.card-hdr.green{background:linear-gradient(90deg,#047857,#10b981)}
.card-hdr.red{background:linear-gradient(90deg,#b91c1c,#ef4444)}
.card-body{padding:20px}
.stat{display:inline-block;margin-right:16px;padding:8px 14px;background:#eef5ff;border-radius:8px;margin-bottom:8px}
.stat .num{font-weight:800;font-size:20px}
.stat.s-ok{background:#e8f5e9}.stat.s-ok .num{color:#2e7d32}
.stat.s-skip{background:#fff3e0}.stat.s-skip .num{color:#e65100}
.stat.s-err{background:#ffebee}.stat.s-err .num{color:#c62828}
.btn{display:inline-block;padding:8px 14px;border-radius:8px;text-decoration:none;font-weight:600;margin-right:8px;margin-top:8px}
.btn-primary{background:#1e88e5;color:#fff}
.btn-ghost{background:#e3f2fd;color:#1565c0}
table{width:100%;border-collapse:collapse}
th{background:#f5f8ff;padding:8px 12px;text-align:left;font-size:13px;border-bottom:2px solid #e0e0e0}
td{padding:7px 12px;border-bottom:1px solid #f0f0f0;font-size:13px}
.badge-skip{background:#fff3e0;color:#e65100;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}
.badge-err{background:#ffebee;color:#c62828;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}
</style>
</head>
<body>

<div class="card">
  <div class="card-hdr green"><i class="fa fa-check-circle"></i> &nbsp;Rekap Hasil Import HP Orang Tua</div>
  <div class="card-body">
    <div class="stat s-ok">
      Berhasil diperbarui: <span class="num"><?php echo (int)$ok; ?></span>
    </div>
    <div class="stat s-skip">
      Skip / Error: <span class="num"><?php echo (int)$totalErr; ?></span>
    </div>
    <div style="margin-top:10px">
      <a class="btn btn-primary" href="hp_ortu_import.php">&larr; Import Lagi</a>
      <a class="btn btn-ghost" href="siswa.php">Ke Daftar Siswa</a>
    </div>
  </div>
</div>

<?php if (!empty($errRows)): ?>
<div class="card">
  <div class="card-hdr red">Detail Baris Skip / Error (<?php echo count($errRows); ?>)</div>
  <div class="card-body" style="padding:0">
    <table>
      <thead>
        <tr><th style="width:80px">Baris</th><th style="width:160px">NIS</th><th>Keterangan</th></tr>
      </thead>
      <tbody>
        <?php foreach ($errRows as $er): ?>
          <tr>
            <td><?php echo (int)$er['row']; ?></td>
            <td><code><?php echo htmlspecialchars($er['nis'], ENT_QUOTES, 'UTF-8'); ?></code></td>
            <td>
              <?php $isSkip = strpos($er['msg'],'di-skip')!==false; ?>
              <span class="<?php echo $isSkip?'badge-skip':'badge-err'; ?>">
                <?php echo $isSkip?'SKIP':'ERROR'; ?>
              </span>
              <?php echo htmlspecialchars($er['msg'], ENT_QUOTES, 'UTF-8'); ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

</body>
</html>
