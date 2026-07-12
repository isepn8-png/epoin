<?php
/**
 * admin/kategori_import_act.php
 * Handler bersama untuk Jenis Prestasi & Jenis Pelanggaran:
 *   - aksi=import_excel      : impor kategori dari file Excel (.xlsx/.xls/.csv) via PhpSpreadsheet
 *   - aksi=muat_rekomendasi  : tanam kategori rekomendasi (referensi SMPN 1), skip duplikat
 *   - aksi=hapus_semua       : hapus semua kategori yang BELUM terpakai (lindungi data terpakai)
 * Parameter `jenis` = prestasi | pelanggaran.
 */
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/epoin_security.php';

epoin_staff_guard(true);            // admin/superadmin
epoin_require_post();

$jenis = strtolower(trim((string)($_POST['jenis'] ?? '')));
$MAP = [
    'prestasi' => [
        'table' => 'prestasi', 'nama' => 'prestasi_nama', 'point' => 'prestasi_point', 'id' => 'prestasi_id',
        'ref_table' => 'input_prestasi', 'ref_col' => 'prestasi',
        'label' => 'Prestasi', 'back' => 'prestasi.php', 'seed' => 'SEED_PRESTASI',
    ],
    'pelanggaran' => [
        'table' => 'pelanggaran', 'nama' => 'pelanggaran_nama', 'point' => 'pelanggaran_point', 'id' => 'pelanggaran_id',
        'ref_table' => 'input_pelanggaran', 'ref_col' => 'pelanggaran',
        'label' => 'Pelanggaran', 'back' => 'pelanggaran.php', 'seed' => 'SEED_PELANGGARAN',
    ],
];
if (!isset($MAP[$jenis])) {
    epoin_flash_error('Jenis kategori tidak dikenal.');
    header('Location: index.php');
    exit;
}
$M = $MAP[$jenis];
$back = $M['back'];

if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect($back);
}

/* ---------- util ---------- */
// set nama yang sudah ada (lowercase) untuk dedup
function existing_name_set(mysqli $k, array $M): array {
    $set = [];
    $r = mysqli_query($k, "SELECT `{$M['nama']}` AS n FROM `{$M['table']}`");
    while ($r && ($row = mysqli_fetch_assoc($r))) {
        $set[mb_strtolower(trim($row['n']))] = true;
    }
    return $set;
}
// insert daftar [['nama'=>,'point'=>], ...] — skip duplikat (existing & dalam batch). return [inserted, skipped]
function insert_rows(mysqli $k, array $M, array $rows): array {
    $exist = existing_name_set($k, $M);
    $stmt = mysqli_prepare($k, "INSERT INTO `{$M['table']}` (`{$M['nama']}`, `{$M['point']}`) VALUES (?, ?)");
    if (!$stmt) return [0, count($rows)];
    $inserted = 0; $skipped = 0;
    foreach ($rows as $row) {
        $nama = trim((string)($row['nama'] ?? ''));
        $point = (int)($row['point'] ?? 0);
        if ($point < 0) $point = -$point;          // simpan positif (tanda ditangani UI)
        $key = mb_strtolower($nama);
        if ($nama === '' || $point < 1 || mb_strlen($nama) > 255 || isset($exist[$key])) { $skipped++; continue; }
        mysqli_stmt_bind_param($stmt, 'si', $nama, $point);
        if (mysqli_stmt_execute($stmt)) { $inserted++; $exist[$key] = true; } else { $skipped++; }
    }
    mysqli_stmt_close($stmt);
    return [$inserted, $skipped];
}

$aksi = (string)($_POST['aksi'] ?? '');

/* ================= MUAT REKOMENDASI ================= */
if ($aksi === 'muat_rekomendasi') {
    require __DIR__ . '/includes/kategori_seed_data.php';
    $rows = ($M['seed'] === 'SEED_PRESTASI') ? ($SEED_PRESTASI ?? []) : ($SEED_PELANGGARAN ?? []);
    [$ins, $skip] = insert_rows($koneksi, $M, $rows);
    $_SESSION['flash_success'] = "Rekomendasi {$M['label']}: $ins ditambahkan"
        . ($skip > 0 ? ", $skip dilewati (nama sudah ada)." : ".");
    header('Location: ' . $back);
    exit;
}

/* ================= HAPUS SEMUA (aman) ================= */
if ($aksi === 'hapus_semua') {
    // Hanya hapus kategori yang belum pernah dipakai di input (cegah data yatim).
    $sql = "DELETE FROM `{$M['table']}`
            WHERE `{$M['id']}` NOT IN (SELECT DISTINCT `{$M['ref_col']}` FROM `{$M['ref_table']}`)";
    @mysqli_query($koneksi, $sql);
    $deleted = mysqli_affected_rows($koneksi);
    $sisa = (int)(mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) n FROM `{$M['table']}`"))['n'] ?? 0);
    $_SESSION['flash_success'] = "Hapus {$M['label']}: $deleted dihapus."
        . ($sisa > 0 ? " $sisa dipertahankan karena masih terpakai pada data poin siswa." : "");
    header('Location: ' . $back);
    exit;
}

/* ================= IMPORT EXCEL ================= */
if ($aksi === 'import_excel') {
    if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        epoin_flash_error('File tidak ada atau gagal diunggah.');
        header('Location: ' . $back); exit;
    }
    $f = $_FILES['file'];
    if ((int)$f['size'] > 5 * 1024 * 1024) {
        epoin_flash_error('Ukuran file maksimal 5 MB.');
        header('Location: ' . $back); exit;
    }
    $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
        epoin_flash_error('Format harus .xlsx, .xls, atau .csv.');
        header('Location: ' . $back); exit;
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        epoin_flash_error('Library pembaca Excel (PhpSpreadsheet) tidak tersedia di server.');
        header('Location: ' . $back); exit;
    }
    require_once $autoload;

    try {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($f['tmp_name']);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($f['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, false, false); // baris → [kolomA, kolomB, ...]
    } catch (\Throwable $e) {
        error_log('EPOIN import kategori gagal: ' . $e->getMessage());
        epoin_flash_error('File tidak dapat dibaca. Pastikan formatnya benar (gunakan template).');
        header('Location: ' . $back); exit;
    }

    $rows = [];
    $skippedEmpty = 0;
    foreach ($data as $i => $r) {
        if ($i === 0) continue; // baris header
        $nama = trim((string)($r[0] ?? ''));
        $rawPoint = $r[1] ?? '';
        $point = (int)preg_replace('/[^0-9\-]/', '', (string)$rawPoint);
        if ($nama === '' && $rawPoint === '') { continue; } // baris kosong penuh, abaikan diam
        if ($nama === '' || $point === 0) { $skippedEmpty++; continue; }
        $rows[] = ['nama' => $nama, 'point' => $point];
    }

    if (empty($rows)) {
        epoin_flash_error('Tidak ada baris valid ditemukan. Cek kolom: A=Nama, B=Poin (baris 1 = judul).');
        header('Location: ' . $back); exit;
    }

    [$ins, $skipDup] = insert_rows($koneksi, $M, $rows);
    $skipTotal = $skipDup + $skippedEmpty;
    $_SESSION['flash_success'] = "Impor {$M['label']}: $ins ditambahkan"
        . ($skipTotal > 0 ? ", $skipTotal dilewati (duplikat / tidak valid)." : ".");
    header('Location: ' . $back);
    exit;
}

epoin_flash_error('Aksi tidak dikenal.');
header('Location: ' . $back);
exit;
