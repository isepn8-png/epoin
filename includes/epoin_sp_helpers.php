<?php
/**
 * Helpers SP (sp_log) — prepared statements, validasi level, penerbitan.
 */
declare(strict_types=1);

require_once __DIR__ . '/epoin_security.php';

/* =====================================================================
   KONFIGURASI AMBANG SP — FLEKSIBEL (skala proporsional + jumlah level)
   ---------------------------------------------------------------------
   Sumber: tabel `app_meta` (meta_key 'sp_skala_max' & 'sp_jumlah_level').
   Fallback AMAN ke 100 / 4 bila tabel/baris belum ada → perilaku lama
   (SP1>=21, SP2>=41, SP3>=61, SP4>=81) tidak berubah sebelum diatur.

   Rumus proporsional: band = skala_max / (jumlah_level + 1)
   ambang SPk = floor(k * band) + 1   ; pemulangan = >= skala_max
   Contoh 100/4 → 21/41/61/81 (sama seperti sekarang).
   Contoh 200/4 → 41/81/121/161.
   ===================================================================== */

const EPOIN_SP_DEFAULT_SKALA = 100;
const EPOIN_SP_DEFAULT_LEVEL = 4;

function epoin_sp_config(?mysqli $koneksi = null): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $max    = EPOIN_SP_DEFAULT_SKALA;
    $levels = EPOIN_SP_DEFAULT_LEVEL;
    $canCache = false;

    if ($koneksi instanceof mysqli) {
        $res = @mysqli_query(
            $koneksi,
            "SELECT meta_key, meta_value FROM app_meta
             WHERE meta_key IN ('sp_skala_max','sp_jumlah_level')"
        );
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                if ($row['meta_key'] === 'sp_skala_max')    { $max    = (int) $row['meta_value']; }
                if ($row['meta_key'] === 'sp_jumlah_level') { $levels = (int) $row['meta_value']; }
            }
            @mysqli_free_result($res);
            $canCache = true; // tabel app_meta ada → boleh cache per-request
        }
    }

    // Sanity clamp
    if ($levels < 1)  { $levels = 1; }
    if ($levels > 12) { $levels = 12; } // dibatasi ketersediaan angka romawi tahap
    if ($max < ($levels + 1)) { $max = $levels + 1; } // tiap band minimal 1 poin

    $out = ['skala_max' => $max, 'jumlah_level' => $levels];
    if ($canCache) {
        $cache = $out;
    }
    return $out;
}

/** Ambang minimal negSaldo per level: ['SP1'=>21,'SP2'=>41,...] (proporsional dari config). */
function epoin_sp_thresholds(?mysqli $koneksi = null): array
{
    $cfg  = epoin_sp_config($koneksi);
    $band = $cfg['skala_max'] / ($cfg['jumlah_level'] + 1);
    $thr  = [];
    for ($k = 1; $k <= $cfg['jumlah_level']; $k++) {
        $thr['SP' . $k] = (int) floor($k * $band) + 1;
    }
    return $thr;
}

function epoin_sp_levels(?mysqli $koneksi = null): array
{
    $out = [];
    $L = epoin_sp_config($koneksi)['jumlah_level'];
    for ($k = 1; $k <= $L; $k++) {
        $out[] = 'SP' . $k;
    }
    return $out;
}

function epoin_sp_validate_level(string $level, ?mysqli $koneksi = null): ?string
{
    $level = strtoupper(trim($level));
    return in_array($level, epoin_sp_levels($koneksi), true) ? $level : null;
}

/** Level SP aktif berdasarkan negSaldo (null bila aman/di bawah SP1). */
function epoin_sp_status_for(int $negSaldo, ?mysqli $koneksi = null): ?string
{
    if ($negSaldo <= 0) {
        return null;
    }
    $cfg = epoin_sp_config($koneksi);
    // Pemulangan (dikembalikan ke orang tua) = level SP(L+1) saat mencapai skala maksimal.
    if ($negSaldo >= $cfg['skala_max']) {
        return 'SP' . ($cfg['jumlah_level'] + 1);
    }
    $active = null;
    foreach (epoin_sp_thresholds($koneksi) as $lvl => $min) {
        if ($negSaldo >= $min) { $active = $lvl; }
    }
    return $active;
}

/**
 * Tahapan pembinaan (display) dibangun dari config — dipakai cetak & portal siswa
 * agar tidak ada lagi angka ambang yang di-hardcode/duplikat.
 * @return array<int,array{roman:string,min:int,max:int,program:string,action:string,sp:?string,color:string}>
 */
function epoin_sp_stages(?mysqli $koneksi = null): array
{
    $cfg = epoin_sp_config($koneksi);
    $M   = $cfg['skala_max'];
    $L   = $cfg['jumlah_level'];
    $thr = epoin_sp_thresholds($koneksi);
    $romans  = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII','XIII','XIV'];
    $palette = ['#f59e0b','#f97316','#ef4444','#b91c1c','#7f1d1d','#4c0519'];

    $stages = [];
    // Tahap awal: pembinaan umum (sebelum SP1) — belum terbit SP
    $stages[] = [
        'roman'   => $romans[0],
        'min'     => 1,
        'max'     => max(1, $thr['SP1'] - 1),
        'program' => 'Pembinaan Umum',
        'action'  => 'Teguran',
        'sp'      => null,
        'color'   => '#10b981',
    ];
    for ($k = 1; $k <= $L; $k++) {
        $lo = $thr['SP' . $k];
        $hi = ($k < $L) ? ($thr['SP' . ($k + 1)] - 1) : ($M - 1);
        if ($hi < $lo) { $hi = $lo; }
        $stages[] = [
            'roman'   => $romans[$k] ?? (string) ($k + 1),
            'min'     => $lo,
            'max'     => $hi,
            'program' => ($k === $L) ? 'Konferensi Kasus' : 'Pembinaan Bertingkat ' . $k,
            'action'  => 'Peringatan ' . $k . ' (SP' . $k . ')',
            'sp'      => 'SP' . $k,
            'color'   => $palette[($k - 1) % count($palette)],
        ];
    }
    // Tahap akhir: pemulangan = level tersendiri SP(L+1) "Dikembalikan kepada Orang Tua"
    $stages[] = [
        'roman'   => $romans[$L + 1] ?? 'MAX',
        'min'     => $M,
        'max'     => 999999,
        'program' => 'Dikembalikan kepada Orang Tua',
        'action'  => 'Dikembalikan kepada Orang Tua (SP' . ($L + 1) . ')',
        'sp'      => 'SP' . ($L + 1),
        'color'   => '#7f1d1d',
    ];
    return $stages;
}

function epoin_sp_sanitize_alasan(string $alasan, int $maxLen = 1000): string
{
    $alasan = trim(strip_tags($alasan));
    if ($maxLen > 0 && function_exists('mb_substr')) {
        $alasan = mb_substr($alasan, 0, $maxLen);
    } elseif ($maxLen > 0) {
        $alasan = substr($alasan, 0, $maxLen);
    }
    return $alasan;
}

function epoin_sp_ensure_schema(mysqli $koneksi): void
{
    @mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS `sp_log` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `siswa_id` INT(11) NOT NULL,
      `sp_level` VARCHAR(8) NOT NULL,
      `running_no` INT(11) NOT NULL,
      `nomor` VARCHAR(64) NOT NULL,
      `alasan` TEXT DEFAULT NULL,
      `signer_user_id` INT NULL,
      `signer_posisi_key` ENUM('kepala','wakasek_kesiswaan','guru_bp') NULL,
      `signer_nama` VARCHAR(120) NULL,
      `signer_jabatan` VARCHAR(120) NULL,
      `tanggal` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_year` (`tanggal`),
      KEY `idx_siswa` (`siswa_id`, `sp_level`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $needCols = [
        'alasan'            => 'TEXT NULL',
        'signer_user_id'    => 'INT NULL',
        'signer_posisi_key' => "ENUM('kepala','wakasek_kesiswaan','guru_bp') NULL",
        'signer_nama'       => 'VARCHAR(120) NULL',
        'signer_jabatan'    => 'VARCHAR(120) NULL',
    ];
    foreach ($needCols as $col => $def) {
        if (!epoin_column_exists($koneksi, 'sp_log', $col)) {
            @mysqli_query($koneksi, "ALTER TABLE `sp_log` ADD COLUMN `$col` $def");
        }
    }
}

function epoin_sp_saldo_for_siswa(mysqli $koneksi, int $siswaId): array
{
    $totPrestasi = epoin_sum_prestasi_siswa($koneksi, $siswaId);
    $totPelang   = epoin_sum_pelanggaran_siswa($koneksi, $siswaId);
    $saldo = $totPrestasi - $totPelang;
    return [
        'totPrestasi' => $totPrestasi,
        'totPelang'   => $totPelang,
        'saldo'       => $saldo,
        'negSaldo'    => max(0, -$saldo),
    ];
}

function epoin_sp_issued_levels_year(mysqli $koneksi, int $siswaId, int $year): array
{
    $issued = [];
    $stmt = mysqli_prepare(
        $koneksi,
        'SELECT sp_level FROM sp_log WHERE siswa_id = ? AND YEAR(tanggal) = ? GROUP BY sp_level'
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $siswaId, $year);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $issued[$row['sp_level']] = true;
        }
        mysqli_stmt_close($stmt);
    }
    return $issued;
}

function epoin_sp_can_issue_level(
    string $level,
    int $negSaldo,
    array $issued,
    bool $sequential = true,
    ?array $thresholds = null
): bool {
    if ($negSaldo <= 0) {
        return false;
    }
    $level = strtoupper(trim($level));
    if ($thresholds === null) {
        // Fallback aman: hitung dari default (100/4) bila pemanggil tak mengoper.
        $thresholds = epoin_sp_thresholds(null);
    }
    if (!isset($thresholds[$level]) || $negSaldo < $thresholds[$level]) {
        return false;
    }
    // Urutan berjenjang: SPk hanya boleh bila SP(k-1) sudah terbit tahun ini.
    if ($sequential && preg_match('/^SP(\d+)$/', $level, $m)) {
        $n = (int) $m[1];
        if ($n > 1 && empty($issued['SP' . ($n - 1)])) {
            return false;
        }
    }
    return true;
}

function epoin_sp_user_name_column(mysqli $koneksi): string
{
    foreach (['nama_lengkap', 'user_nama', 'nama', 'full_name', 'name', 'nama_user', 'display_name', 'realname', 'username'] as $c) {
        if (epoin_column_exists($koneksi, 'user', $c)) {
            return $c;
        }
    }
    return 'username';
}

function epoin_sp_fetch_bp_signer(mysqli $koneksi, int $sekolahId, int $bpUserId): ?array
{
    if ($bpUserId <= 0 || $sekolahId <= 0) {
        return null;
    }
    $nameCol = epoin_sp_user_name_column($koneksi);
    $sql = "SELECT u.user_id, u.`$nameCol` AS uname
            FROM sekolah_staff ss
            JOIN user u ON ss.user_id = u.user_id
            WHERE ss.sekolah_id = ? AND ss.posisi_key = 'guru_bp' AND ss.user_id = ?
            LIMIT 1";
    $stmt = mysqli_prepare($koneksi, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $sekolahId, $bpUserId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return null;
    }
    return [
        'user_id'     => (int) $row['user_id'],
        'posisi_key'  => 'guru_bp',
        'nama'        => trim((string) ($row['uname'] ?? '')),
        'jabatan'     => 'Guru BP/BK',
    ];
}

function epoin_sp_build_nomor(string $seq3, string $sp, string $schoolCode, int $year): string
{
    return $seq3 . '/' . $sp . '/' . $schoolCode . '/S' . $year;
}

function epoin_sp_next_numbers(mysqli $koneksi, int $siswaId, string $level, int $year, string $schoolCode = 'SMPN1GTJ'): array
{
    $running = 1;
    $stmt = mysqli_prepare(
        $koneksi,
        'SELECT COALESCE(MAX(running_no), 0) AS rn FROM sp_log WHERE sp_level = ? AND YEAR(tanggal) = ?'
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'si', $level, $year);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $running = (int) $row['rn'] + 1;
        }
        mysqli_stmt_close($stmt);
    }

    $seqStu = 1;
    $stmt2 = mysqli_prepare(
        $koneksi,
        'SELECT COUNT(*) AS cnt FROM sp_log WHERE siswa_id = ? AND sp_level = ? AND YEAR(tanggal) = ?'
    );
    if ($stmt2) {
        mysqli_stmt_bind_param($stmt2, 'isi', $siswaId, $level, $year);
        mysqli_stmt_execute($stmt2);
        $res2 = mysqli_stmt_get_result($stmt2);
        if ($res2 && ($row2 = mysqli_fetch_assoc($res2))) {
            $seqStu = (int) $row2['cnt'] + 1;
        }
        mysqli_stmt_close($stmt2);
    }

    $seq3 = str_pad((string) $seqStu, 3, '0', STR_PAD_LEFT);
    $nomor = epoin_sp_build_nomor($seq3, $level, $schoolCode, $year);

    return ['running' => $running, 'seqStu' => $seqStu, 'seq3' => $seq3, 'nomor' => $nomor];
}

function epoin_sp_insert_log(
    mysqli $koneksi,
    int $siswaId,
    string $level,
    int $running,
    string $nomor,
    string $alasan,
    array $signer
): bool {
    $stmt = mysqli_prepare(
        $koneksi,
        'INSERT INTO sp_log (siswa_id, sp_level, running_no, nomor, alasan, signer_user_id, signer_posisi_key, signer_nama, signer_jabatan, tanggal)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    if (!$stmt) {
        return false;
    }
    $signerUserId = (int) ($signer['user_id'] ?? 0);
    $posKey = (string) ($signer['posisi_key'] ?? 'guru_bp');
    $signerNama = (string) ($signer['nama'] ?? '');
    $signerJabatan = (string) ($signer['jabatan'] ?? '');
    // Types: i s i s s i s s s (siswa, level, running, nomor, alasan, signer_user_id, posisi_key, nama, jabatan)
    mysqli_stmt_bind_param(
        $stmt,
        'isississs',
        $siswaId,
        $level,
        $running,
        $nomor,
        $alasan,
        $signerUserId,
        $posKey,
        $signerNama,
        $signerJabatan
    );
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function epoin_sp_fetch_latest_log(mysqli $koneksi, int $siswaId, string $level, int $year): ?array
{
    $stmt = mysqli_prepare(
        $koneksi,
        'SELECT * FROM sp_log WHERE siswa_id = ? AND sp_level = ? AND YEAR(tanggal) = ? ORDER BY id DESC LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'isi', $siswaId, $level, $year);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

/**
 * Auto-create SP log for print view when threshold met (no GET alasan in SQL).
 */
function epoin_sp_auto_create_for_print(
    mysqli $koneksi,
    int $siswaId,
    string $level,
    int $negSaldo,
    string $schoolCode = 'SMPN1GTJ'
): ?array {
    if (!epoin_sp_can_issue_level($level, $negSaldo, [], false, epoin_sp_thresholds($koneksi))) {
        return null;
    }
    $year = (int) date('Y');
    $existing = epoin_sp_fetch_latest_log($koneksi, $siswaId, $level, $year);
    if ($existing) {
        return $existing;
    }
    $nums = epoin_sp_next_numbers($koneksi, $siswaId, $level, $year, $schoolCode);
    $signer = ['user_id' => 0, 'posisi_key' => 'kepala', 'nama' => '', 'jabatan' => 'Kepala Sekolah'];
    if (!epoin_sp_insert_log($koneksi, $siswaId, $level, $nums['running'], $nums['nomor'], '', $signer)) {
        return null;
    }
    return epoin_sp_fetch_latest_log($koneksi, $siswaId, $level, $year);
}

/**
 * AJAX issue_sp — outputs JSON and exits.
 */
function epoin_sp_ajax_issue_endpoint(): void
{
    @ini_set('display_errors', '0');
    @ini_set('log_errors', '1');
    if (function_exists('mysqli_report')) {
        @mysqli_report(MYSQLI_REPORT_OFF);
    }

    while (ob_get_level()) {
        @ob_end_clean();
    }

    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    $jsonEnd = static function (bool $ok, string $msg, array $extra = []): void {
        echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    };

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        $jsonEnd(false, 'Method not allowed.');
    }

    require_once dirname(__DIR__) . '/koneksi.php';
    if (function_exists('mysqli_report')) {
        @mysqli_report(MYSQLI_REPORT_OFF);
    }
    $actorUserId = epoin_staff_guard_json();

    if (!epoin_csrf_validate($_POST)) {
        http_response_code(403);
        $jsonEnd(false, 'Token keamanan (CSRF) tidak valid.');
    }

    if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
        http_response_code(500);
        $jsonEnd(false, 'Koneksi database tidak tersedia.');
    }
    @mysqli_set_charset($koneksi, 'utf8mb4');

    epoin_sp_ensure_schema($koneksi);

    $siswaId = (int) ($_POST['siswa_id'] ?? 0);
    $level   = epoin_sp_validate_level((string) ($_POST['sp_level'] ?? ''), $koneksi);
    $alasan  = epoin_sp_sanitize_alasan((string) ($_POST['alasan'] ?? ''));
    $sekolahId = (int) ($_POST['sekolah_id'] ?? ($_SESSION['sekolah_id'] ?? 1));

    $bpUserId = (int) ($_POST['bp_user_id'] ?? 0);
    if ($bpUserId <= 0) {
        $sc = trim((string) ($_POST['signer_choice'] ?? ''));
        if (stripos($sc, 'BP:') === 0) {
            $bpUserId = (int) substr($sc, 3);
        }
    }

    if ($siswaId <= 0 || $level === null) {
        $jsonEnd(false, 'Data tidak valid.');
    }
    if ($alasan === '') {
        $jsonEnd(false, 'Alasan penerbitan wajib diisi.');
    }
    if ($bpUserId <= 0) {
        $jsonEnd(false, 'Pilih Guru BP penandatangan terlebih dahulu.');
    }

    if (!epoin_fetch_siswa_row($koneksi, $siswaId)) {
        $jsonEnd(false, 'Data siswa tidak ditemukan.');
    }

    $saldoData = epoin_sp_saldo_for_siswa($koneksi, $siswaId);
    if ($saldoData['saldo'] >= 0) {
        $jsonEnd(false, 'Saldo ≥ 0. SP tidak dapat diterbitkan.');
    }

    $year = (int) date('Y');
    $issued = epoin_sp_issued_levels_year($koneksi, $siswaId, $year);
    if (!epoin_sp_can_issue_level($level, $saldoData['negSaldo'], $issued, true, epoin_sp_thresholds($koneksi))) {
        $jsonEnd(false, 'Ambang saldo/urutan belum terpenuhi untuk ' . $level . '.');
    }

    $signer = epoin_sp_fetch_bp_signer($koneksi, $sekolahId, $bpUserId);
    if (!$signer) {
        $jsonEnd(false, 'Guru BP tidak valid.');
    }

    $nums = epoin_sp_next_numbers($koneksi, $siswaId, $level, $year);
    try {
        if (!epoin_sp_insert_log($koneksi, $siswaId, $level, $nums['running'], $nums['nomor'], $alasan, $signer)) {
            $jsonEnd(false, 'Gagal menyimpan data SP.');
        }
    } catch (Throwable $e) {
        error_log('EPOIN issue_sp insert failed: ' . $e->getMessage());
        $jsonEnd(false, 'Gagal menyimpan data SP.');
    }

    $namaGuru = epoin_resolve_guru_nama($koneksi, $actorUserId);
    epoin_log_aktivitas(
        $koneksi,
        $actorUserId,
        $namaGuru,
        "Menerbitkan $level ({$nums['nomor']}) untuk siswa #$siswaId oleh $namaGuru"
    );

    // Rute cetak generik: sp1_cetak.php adalah mesin, terima ?sp=SPk (dukung level > 4).
    $printUrl = 'sp1_cetak.php?sp=' . $level . '&id=' . $siswaId;
    $jsonEnd(true, 'SP diterbitkan.', ['print_url' => $printUrl, 'nomor' => $nums['nomor']]);
}
