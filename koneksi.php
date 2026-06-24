<?php
/**
 * Shared mysqli connection ($koneksi). Included across the app.
 */
require_once __DIR__ . '/config/database.php';

$isLocal = in_array(APP_ENV, ['local', 'development'], true);

if ($isLocal) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
} else {
    mysqli_report(MYSQLI_REPORT_OFF);
}

try {
    $koneksi = mysqli_connect($host, $user, $pass, $db, $port);
} catch (mysqli_sql_exception $e) {
    $koneksi = false;
    $connectError = $e->getMessage();
}

if (!$koneksi) {
    $connectError = $connectError ?? mysqli_connect_error();
    error_log('EPOIN database connection failed: ' . $connectError);

    if ($isLocal) {
        http_response_code(500);
        die(
            'Koneksi database gagal. Periksa MySQL, .env, dan config/database.php.<br>'
            . '<small>' . htmlspecialchars((string) $connectError, ENT_QUOTES, 'UTF-8') . '</small>'
        );
    }

    http_response_code(500);
    die('Koneksi database gagal.');
}

mysqli_set_charset($koneksi, 'utf8mb4');

// Alias for modules that expect $conn (e.g. deskripsi_pts)
if (!isset($conn)) {
    $conn = $koneksi;
}

// Helper akademik terpusat (TA & semester) — sediakan epoin_*() di semua modul.
require_once __DIR__ . '/includes/akademik_helper.php';
