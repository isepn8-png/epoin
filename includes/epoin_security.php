<?php
/**
 * Shared security helpers for modul EPOIN (CSRF, session, validation).
 */
declare(strict_types=1);

const EPOIN_CSRF_SESSION_KEY = '_csrf';
const EPOIN_CSRF_POST_FIELD  = '_csrf';

function epoin_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function epoin_ensure_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function epoin_csrf_token(): string
{
    epoin_ensure_session();
    if (empty($_SESSION[EPOIN_CSRF_SESSION_KEY]) || !is_string($_SESSION[EPOIN_CSRF_SESSION_KEY])) {
        $_SESSION[EPOIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[EPOIN_CSRF_SESSION_KEY];
}

function epoin_csrf_field(): string
{
    $name = EPOIN_CSRF_POST_FIELD;
    $token = epoin_csrf_token();
    return '<input type="hidden" name="' . epoin_h($name) . '" value="' . epoin_h($token) . '">';
}

function epoin_csrf_validate(?array $source = null): bool
{
    epoin_ensure_session();
    $expected = $_SESSION[EPOIN_CSRF_SESSION_KEY] ?? '';
    if (!is_string($expected) || $expected === '') {
        return false;
    }
    if ($source === null) {
        $source = $_POST;
        if (empty($source['_csrf']) && !empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $got = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
            return hash_equals($expected, $got);
        }
    }
    $got = (string) ($source[EPOIN_CSRF_POST_FIELD] ?? $source['_csrf'] ?? '');
    return $got !== '' && hash_equals($expected, $got);
}

function epoin_flash_error(string $message): void
{
    epoin_ensure_session();
    $_SESSION['flash_error'] = $message;
}

/**
 * Render flash alerts with HTML escaping (modul EPOIN).
 */
function epoin_flash_render(): void
{
    epoin_ensure_session();
    if (!empty($_SESSION['flash_success'])) {
        echo '<div class="alert alert-success alert-dismissible" style="border-radius:12px;">'
            . '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>'
            . '<i class="fa fa-check-circle"></i> ' . epoin_h((string) $_SESSION['flash_success'])
            . '</div>';
        unset($_SESSION['flash_success']);
    }
    if (!empty($_SESSION['flash_error'])) {
        echo '<div class="alert alert-danger alert-dismissible" style="border-radius:12px;">'
            . '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>'
            . '<i class="fa fa-exclamation-triangle"></i> ' . epoin_h((string) $_SESSION['flash_error'])
            . '</div>';
        unset($_SESSION['flash_error']);
    }
}

function epoin_csrf_fail_redirect(string $backUrl): void
{
    epoin_flash_error('Permintaan ditolak: token keamanan (CSRF) tidak valid. Silakan ulangi.');
    header('Location: ' . $backUrl);
    exit;
}

function epoin_is_admin_session(): bool
{
    epoin_ensure_session();
    $roles = $_SESSION['roles'] ?? [];
    if (is_array($roles)) {
        foreach ($roles as $r) {
            $k = strtolower(str_replace([' ', '-'], '', (string) $r));
            if (in_array($k, ['administrator', 'superadmin', 'admin'], true)) {
                return true;
            }
        }
    }
    $lvl = strtolower((string) ($_SESSION['level'] ?? ''));
    return in_array($lvl, ['administrator', 'superadmin', 'admin'], true);
}

/**
 * Apakah sesi saat ini milik STAF (admin/guru/wali kelas/BK/TU/sekretaris/piket)?
 * SISWA (portal siswa men-set level='siswa') DITOLAK.
 *
 * Prinsip: hanya siswa yang merupakan non-staf di sistem ini; semua akun di
 * tabel `user` (admin/guru/dll) adalah staf. Jadi: lolos jika admin, ATAU
 * punya role/level staf yang bukan 'siswa'; ditolak jika level/role = 'siswa'.
 */
function epoin_is_staff_session(): bool
{
    epoin_ensure_session();

    // Admin/superadmin selalu staf
    if (epoin_is_admin_session()) {
        return true;
    }

    $norm = static function ($v): string {
        return strtolower(str_replace([' ', '-', '_'], '', (string) $v));
    };

    // Siswa ditolak secara eksplisit
    if ($norm($_SESSION['level'] ?? '') === 'siswa') {
        return false;
    }
    $roles = $_SESSION['roles'] ?? [];
    if (is_array($roles)) {
        foreach ($roles as $r) {
            if ($norm($r) === 'siswa') {
                return false;
            }
        }
        foreach ($roles as $r) {
            if ($norm($r) !== '') {
                return true; // ada role staf yang valid
            }
        }
    }

    // Fallback: login dengan level non-kosong & bukan siswa → staf
    return $norm($_SESSION['level'] ?? '') !== '';
}

/**
 * Guard halaman HTML: izinkan STAF (admin + guru/wali/BK/dll), tolak siswa & tamu.
 * Tamu → login admin; siswa → portal siswa.
 * Panggil SEBELUM ada output (sebelum include header.php).
 * @return int user id
 */
function epoin_staff_only_guard(string $studentRedirect = '../siswa/'): int
{
    epoin_ensure_session();
    $uid = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        header('Location: ../admin.php?alert=belum_login');
        exit;
    }
    if (!epoin_is_staff_session()) {
        epoin_flash_error('Akses ditolak: halaman ini khusus staf (admin/guru).');
        header('Location: ' . $studentRedirect);
        exit;
    }
    return $uid;
}

/**
 * Guard endpoint JSON: izinkan STAF, tolak siswa & tamu (tanpa redirect HTML).
 * @return int user id
 */
function epoin_staff_only_guard_json(): int
{
    epoin_ensure_session();
    $uid = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Belum login'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!epoin_is_staff_session()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Akses ditolak: khusus staf'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $uid;
}

/**
 * @return int user id or 0
 */
function epoin_staff_guard(bool $requireAdmin = false): int
{
    epoin_ensure_session();
    $uid = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        header('Location: ../admin.php?alert=belum_login');
        exit;
    }
    if ($requireAdmin && !epoin_is_admin_session()) {
        epoin_flash_error('Akses ditolak: hanya administrator.');
        header('Location: index.php');
        exit;
    }
    return $uid;
}

/**
 * Session guard for JSON API endpoints (no HTML redirect).
 */
function epoin_staff_guard_json(): int
{
    epoin_ensure_session();
    $uid = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Belum login'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $uid;
}

function epoin_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Method not allowed');
    }
}

function epoin_verify_siswa_kelas(mysqli $koneksi, int $siswaId, int $kelasId): bool
{
    if ($siswaId <= 0 || $kelasId <= 0) {
        return false;
    }
    $stmt = mysqli_prepare($koneksi, 'SELECT 1 FROM kelas_siswa WHERE ks_siswa = ? AND ks_kelas = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $siswaId, $kelasId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $ok = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $ok;
}

function epoin_column_exists(mysqli $koneksi, string $table, string $column): bool
{
    $stmt = mysqli_prepare(
        $koneksi,
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $ok = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $ok;
}

function epoin_log_aktivitas(mysqli $koneksi, int $userId, string $namaGuru, string $aktivitas): void
{
    $stmt = mysqli_prepare(
        $koneksi,
        'INSERT INTO log_aktivitas (user_id, nama_guru, aktivitas, waktu) VALUES (?, ?, ?, NOW())'
    );
    if (!$stmt) {
        return;
    }
    mysqli_stmt_bind_param($stmt, 'iss', $userId, $namaGuru, $aktivitas);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function epoin_resolve_guru_nama(mysqli $koneksi, int $userId): string
{
    $nama = trim((string) ($_SESSION['nama'] ?? $_SESSION['user_nama'] ?? $_SESSION['username'] ?? ''));
    if ($nama !== '' || $userId <= 0) {
        return $nama !== '' ? $nama : 'Pengguna';
    }
    $stmt = mysqli_prepare($koneksi, 'SELECT user_nama FROM user WHERE user_id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $nama = trim((string) ($row['user_nama'] ?? ''));
        }
        mysqli_stmt_close($stmt);
    }
    return $nama !== '' ? $nama : 'Pengguna';
}

function epoin_get_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    require_once dirname(__DIR__) . '/config/database.php';
    global $host, $user, $pass, $db, $port;
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, (int) $port, $db);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function epoin_sum_prestasi_siswa(mysqli $koneksi, int $siswaId): int
{
    $stmt = mysqli_prepare(
        $koneksi,
        'SELECT COALESCE(SUM(p.prestasi_point), 0) AS total
         FROM input_prestasi ip
         JOIN prestasi p ON p.prestasi_id = ip.prestasi
         WHERE ip.siswa = ?'
    );
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $siswaId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return (int) ($row['total'] ?? 0);
}

function epoin_sum_pelanggaran_siswa(mysqli $koneksi, int $siswaId): int
{
    $stmt = mysqli_prepare(
        $koneksi,
        'SELECT COALESCE(SUM(pg.pelanggaran_point), 0) AS total
         FROM input_pelanggaran ig
         JOIN pelanggaran pg ON pg.pelanggaran_id = ig.pelanggaran
         WHERE ig.siswa = ?'
    );
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $siswaId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return (int) ($row['total'] ?? 0);
}

function epoin_fetch_siswa_row(mysqli $koneksi, int $siswaId): ?array
{
    $stmt = mysqli_prepare(
        $koneksi,
        'SELECT s.*, j.jurusan_nama
         FROM siswa s
         JOIN jurusan j ON s.siswa_jurusan = j.jurusan_id
         WHERE s.siswa_id = ? LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $siswaId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}
