<?php
/**
 * admin/kategori_import_act.php
 * Handler bersama untuk Jenis Prestasi & Jenis Pelanggaran. Parameter `jenis` = prestasi | pelanggaran.
 *
 * aksi (form biasa, redirect):
 *   - muat_rekomendasi  : tanam kategori rekomendasi (referensi kurasi), skip duplikat
 *   - hapus_semua       : hapus kategori yang BELUM terpakai (lindungi data terpakai)
 *
 * aksi (AJAX, JSON) — alur Import Excel/CSV 2 langkah (preview dulu, baru eksekusi):
 *   - preview_excel     : baca file, validasi tiap baris (tanpa tulis DB), simpan hasil valid
 *                         ke session dgn token, kembalikan preview lengkap + ringkasan.
 *   - eksekusi_import   : commit baris yg tersimpan (dari token preview) ke DB sesuai mode.
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

$aksi = (string)($_POST['aksi'] ?? '');
$isAjax = in_array($aksi, ['preview_excel', 'eksekusi_import'], true);

function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($MAP[$jenis])) {
    if ($isAjax) { json_out(['ok' => false, 'msg' => 'Jenis kategori tidak dikenal.'], 400); }
    epoin_flash_error('Jenis kategori tidak dikenal.');
    header('Location: index.php');
    exit;
}
$M = $MAP[$jenis];
$back = $M['back'];

if (!epoin_csrf_validate()) {
    if ($isAjax) { json_out(['ok' => false, 'msg' => 'Token keamanan (CSRF) tidak valid. Muat ulang halaman.'], 403); }
    epoin_csrf_fail_redirect($back);
}

/* ---------- util ---------- */
function existing_name_set(mysqli $k, array $M): array {
    $set = [];
    $r = mysqli_query($k, "SELECT `{$M['nama']}` AS n FROM `{$M['table']}`");
    while ($r && ($row = mysqli_fetch_assoc($r))) {
        $set[mb_strtolower(trim($row['n']))] = true;
    }
    return $set;
}
function insert_rows(mysqli $k, array $M, array $rows): array {
    $exist = existing_name_set($k, $M);
    $stmt = mysqli_prepare($k, "INSERT INTO `{$M['table']}` (`{$M['nama']}`, `{$M['point']}`) VALUES (?, ?)");
    if (!$stmt) return [0, count($rows)];
    $inserted = 0; $skipped = 0;
    foreach ($rows as $row) {
        $nama = trim((string)($row['nama'] ?? ''));
        $point = (int)($row['point'] ?? 0);
        if ($point < 0) $point = -$point;
        $key = mb_strtolower($nama);
        if ($nama === '' || $point < 1 || mb_strlen($nama) > 255 || isset($exist[$key])) { $skipped++; continue; }
        mysqli_stmt_bind_param($stmt, 'si', $nama, $point);
        if (mysqli_stmt_execute($stmt)) { $inserted++; $exist[$key] = true; } else { $skipped++; }
    }
    mysqli_stmt_close($stmt);
    return [$inserted, $skipped];
}

/* ================= MUAT REKOMENDASI (form biasa) ================= */
if ($aksi === 'muat_rekomendasi') {
    require __DIR__ . '/includes/kategori_seed_data.php';
    $rows = ($M['seed'] === 'SEED_PRESTASI') ? ($SEED_PRESTASI ?? []) : ($SEED_PELANGGARAN ?? []);
    [$ins, $skip] = insert_rows($koneksi, $M, $rows);
    $_SESSION['flash_success'] = "Rekomendasi {$M['label']}: $ins ditambahkan"
        . ($skip > 0 ? ", $skip dilewati (nama sudah ada)." : ".");
    header('Location: ' . $back);
    exit;
}

/* ================= HAPUS SEMUA (form biasa, aman) ================= */
if ($aksi === 'hapus_semua') {
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

/* ================= PREVIEW EXCEL/CSV (AJAX) ================= */
if ($aksi === 'preview_excel') {
    if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        json_out(['ok' => false, 'msg' => 'File tidak ada atau gagal diunggah.'], 400);
    }
    $f = $_FILES['file'];
    if ((int)$f['size'] > 5 * 1024 * 1024) {
        json_out(['ok' => false, 'msg' => 'Ukuran file maksimal 5 MB.'], 400);
    }
    $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
        json_out(['ok' => false, 'msg' => 'Format harus .xlsx, .xls, atau .csv.'], 400);
    }

    $mode = ((string)($_POST['mode'] ?? 'skip') === 'update') ? 'update' : 'skip';
    $defaultPoinRaw = trim((string)($_POST['default_poin'] ?? ''));
    $defaultPoin = ($defaultPoinRaw !== '' && is_numeric($defaultPoinRaw)) ? (int)abs((float)$defaultPoinRaw) : null;

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        json_out(['ok' => false, 'msg' => 'Library pembaca Excel (PhpSpreadsheet) tidak tersedia di server.'], 500);
    }
    require_once $autoload;

    try {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($f['tmp_name']);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($f['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, false, false);
    } catch (\Throwable $e) {
        error_log('EPOIN import kategori (preview) gagal baca file: ' . $e->getMessage());
        json_out(['ok' => false, 'msg' => 'File tidak dapat dibaca. Pastikan formatnya benar (gunakan template).'], 400);
    }

    if (empty($data)) {
        json_out(['ok' => false, 'msg' => 'File kosong / tidak ada baris data.'], 400);
    }

    /* ---- deteksi kolom Nama & Poin secara fleksibel dari header baris pertama ---- */
    $namaAliases  = ['nama', $M['nama'], 'nama kategori', 'nama kategori ' . strtolower($M['label']), 'kategori', 'nama_kategori'];
    $poinAliases  = ['poin', 'point', $M['point'], 'nilai', 'bobot'];
    $header = array_map(fn($h) => mb_strtolower(trim((string)$h)), $data[0] ?? []);
    $colNama = null; $colPoin = null;
    foreach ($header as $idx => $h) {
        if ($colNama === null && in_array($h, $namaAliases, true)) { $colNama = $idx; }
        if ($colPoin === null && in_array($h, $poinAliases, true)) { $colPoin = $idx; }
    }
    // fallback: kalau header tidak match alias apa pun, asumsikan kolom A=Nama, B=Poin (kompatibel template lama)
    if ($colNama === null) { $colNama = 0; }
    if ($colPoin === null) { $colPoin = 1; }

    $exist = existing_name_set($koneksi, $M);   // nama yang sudah ada di DB (lowercase => true)
    $seenInFile = [];                            // dedup dalam file yg sama
    $preview = [];
    $pendingRows = [];                            // yang benar2 akan dieksekusi (status ok/update)
    $countOk = 0; $countUpdate = 0; $countSkip = 0; $countError = 0;

    foreach ($data as $i => $r) {
        if ($i === 0) continue; // baris header
        $line = $i + 1; // nomor baris di file (1-based, termasuk header)
        $namaRaw = trim((string)($r[$colNama] ?? ''));
        $poinRaw = trim((string)($r[$colPoin] ?? ''));

        if ($namaRaw === '' && $poinRaw === '') { continue; } // baris kosong penuh, abaikan diam-diam

        $status = 'ok'; $reason = '';
        $poinNum = null;

        if ($namaRaw === '') {
            $status = 'error'; $reason = 'Nama kosong';
        } elseif (mb_strlen($namaRaw) > 255) {
            $status = 'error'; $reason = 'Nama terlalu panjang (maks 255)';
        }

        if ($status !== 'error') {
            if ($poinRaw !== '' && is_numeric(str_replace(',', '.', $poinRaw))) {
                $poinNum = (int) abs((float) str_replace(',', '.', $poinRaw));
            } elseif ($defaultPoin !== null) {
                $poinNum = $defaultPoin;
            } else {
                $status = 'error'; $reason = 'Poin kosong/bukan angka (isi Default Poin agar otomatis terisi)';
            }
            if ($status !== 'error' && $poinNum < 1) {
                $status = 'error'; $reason = 'Poin harus lebih dari 0';
            }
        }

        if ($status !== 'error') {
            $key = mb_strtolower($namaRaw);
            if (isset($seenInFile[$key])) {
                $status = 'skip'; $reason = 'Duplikat di dalam file (baris ' . $seenInFile[$key] . ')';
            } elseif (isset($exist[$key])) {
                if ($mode === 'update') { $status = 'update'; $reason = 'Nama sudah ada — poin akan diperbarui'; }
                else { $status = 'skip'; $reason = 'Nama sudah ada di daftar'; }
            }
            $seenInFile[$key] = $line;
        }

        $row = ['line' => $line, 'nama' => $namaRaw, 'poin' => $poinNum, 'status' => $status, 'reason' => $reason];
        $preview[] = $row;

        if ($status === 'ok') { $countOk++; $pendingRows[] = $row; }
        elseif ($status === 'update') { $countUpdate++; $pendingRows[] = $row; }
        elseif ($status === 'skip') { $countSkip++; }
        else { $countError++; }
    }

    if (empty($preview)) {
        json_out(['ok' => false, 'msg' => 'Tidak ada baris data ditemukan. Cek isi file (baris 1 harus judul kolom).'], 400);
    }

    $token = bin2hex(random_bytes(12));
    if (!isset($_SESSION['kategori_import_pending'])) { $_SESSION['kategori_import_pending'] = []; }
    // batasi hanya simpan 3 token terakhir per sesi agar tidak menumpuk
    $_SESSION['kategori_import_pending'] = array_slice($_SESSION['kategori_import_pending'], -2, null, true);
    $_SESSION['kategori_import_pending'][$token] = ['jenis' => $jenis, 'mode' => $mode, 'rows' => $pendingRows, 'ts' => time()];

    json_out([
        'ok' => true,
        'token' => $token,
        'preview' => array_slice($preview, 0, 200), // batasi tampilan preview (tetap commit semua pendingRows)
        'preview_truncated' => count($preview) > 200,
        'summary' => ['total' => count($preview), 'baru' => $countOk, 'update' => $countUpdate, 'lewati' => $countSkip, 'error' => $countError],
        'mode' => $mode,
    ]);
}

/* ================= EKSEKUSI IMPORT (AJAX) ================= */
if ($aksi === 'eksekusi_import') {
    $token = (string)($_POST['token'] ?? '');
    $pending = $_SESSION['kategori_import_pending'][$token] ?? null;
    if (!$pending || $pending['jenis'] !== $jenis) {
        json_out(['ok' => false, 'msg' => 'Sesi preview sudah kedaluwarsa. Silakan Preview ulang.'], 400);
    }

    $mode = $pending['mode'];
    $rows = $pending['rows'];
    unset($_SESSION['kategori_import_pending'][$token]); // sekali pakai

    $stmtIns = mysqli_prepare($koneksi, "INSERT INTO `{$M['table']}` (`{$M['nama']}`, `{$M['point']}`) VALUES (?, ?)");
    $stmtUpd = mysqli_prepare($koneksi, "UPDATE `{$M['table']}` SET `{$M['point']}` = ? WHERE LOWER(`{$M['nama']}`) = LOWER(?)");
    $inserted = 0; $updated = 0; $failed = 0;

    foreach ($rows as $row) {
        $nama = (string)$row['nama'];
        $poin = (int)$row['poin'];
        if ($row['status'] === 'update' && $stmtUpd) {
            mysqli_stmt_bind_param($stmtUpd, 'is', $poin, $nama);
            if (mysqli_stmt_execute($stmtUpd)) { $updated++; } else { $failed++; }
        } elseif ($stmtIns) {
            mysqli_stmt_bind_param($stmtIns, 'si', $nama, $poin);
            if (mysqli_stmt_execute($stmtIns)) { $inserted++; } else { $failed++; }
        }
    }
    if ($stmtIns) mysqli_stmt_close($stmtIns);
    if ($stmtUpd) mysqli_stmt_close($stmtUpd);

    $msg = "Impor {$M['label']} selesai: $inserted ditambahkan";
    if ($updated > 0) $msg .= ", $updated diperbarui";
    if ($failed > 0) $msg .= ", $failed gagal";
    $msg .= '.';

    json_out(['ok' => true, 'msg' => $msg, 'inserted' => $inserted, 'updated' => $updated, 'failed' => $failed]);
}

if ($isAjax) { json_out(['ok' => false, 'msg' => 'Aksi tidak dikenal.'], 400); }
epoin_flash_error('Aksi tidak dikenal.');
header('Location: ' . $back);
exit;
