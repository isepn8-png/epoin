<?php
/** security_headers.php — CSP aman (tanpa CR/LF) + whitelist eksternal
 * Gunakan bersama konstanta:
 *   CSP_SCRIPT, CSP_STYLE, CSP_FONT, CSP_IMG, CSP_CONNECT, CSP_FRAME
 * Contoh (didefine di file pemanggil, SEBARIS — tanpa newline):
 *   define('CSP_SCRIPT',  " https://code.jquery.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://cdn.datatables.net https://ajax.googleapis.com https://code.highcharts.com https://maxcdn.bootstrapcdn.com https://netdna.bootstrapcdn.com https://oss.maxcdn.com https://gist.github.com https://www.w3counter.com");
 *   define('CSP_STYLE',   " 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://cdn.datatables.net https://maxcdn.bootstrapcdn.com https://netdna.bootstrapcdn.com https://oss.maxcdn.com https://code.jquery.com");
 *   define('CSP_FONT',    " https://fonts.gstatic.com https://cdnjs.cloudflare.com data:");
 *   define('CSP_IMG',     " https: data: blob:");
 *   define('CSP_CONNECT', ""); // isi jika ada XHR/fetch ke API eksternal
 *   define('CSP_FRAME',   " https://docs.google.com https://forms.gle"); // Google Forms
 */

if (headers_sent()) { return; }

/* ===== Util: kirim header tanpa CR/LF ===== */
function safe_header(string $name, string $value, bool $replace = true): void {
  // Hapus CR/LF dan kompres whitespace internal → satu baris aman
  $value = trim(preg_replace('/[\r\n]+/', ' ', $value));
  $value = trim(preg_replace('/\s+/', ' ', $value));
  if ($value !== '') { header($name . ': ' . $value, $replace); }
}

/* Ambil dan rapikan whitelist (tetap memakai yang Anda define) */
$S = defined('CSP_SCRIPT')  ? trim(preg_replace('/\s+/', ' ', CSP_SCRIPT))  : '';
$T = defined('CSP_STYLE')   ? trim(preg_replace('/\s+/', ' ', CSP_STYLE))   : '';
$F = defined('CSP_FONT')    ? trim(preg_replace('/\s+/', ' ', CSP_FONT))    : '';
$I = defined('CSP_IMG')     ? trim(preg_replace('/\s+/', ' ', CSP_IMG))     : '';
$C = defined('CSP_CONNECT') ? trim(preg_replace('/\s+/', ' ', CSP_CONNECT)) : '';
$R = defined('CSP_FRAME')   ? trim(preg_replace('/\s+/', ' ', CSP_FRAME))   : '';

$self = "'self'";

/* ===== Rakit CSP (satu baris) =====
   - Ikuti prinsip ketat, tapi longgar di channel aman (font/img data: blob:)
   - style-src: jika butuh inline style, taruh 'unsafe-inline' di CSP_STYLE (seperti contoh)
   - script-src: TIDAK otomatis menambah 'unsafe-inline' (lebih aman)
*/
$cspParts = [
  "default-src $self",
  "base-uri $self",
  "form-action $self",
  "object-src 'none'",
  "frame-ancestors $self",

  // JS dari origin sendiri + whitelist CDN Anda
  "script-src $self" . ($S !== '' ? " $S" : ''),

  // CSS dari origin sendiri + whitelist; 'unsafe-inline' bila Anda cantumkan di CSP_STYLE
  "style-src $self" . ($T !== '' ? " $T" : ''),

  // Gambar: aman untuk favicon/base64/Blob (ikon tidak keblok)
  "img-src $self data: blob:" . ($I !== '' ? " $I" : ""),

  // Font: aman untuk base64 (Font Awesome/Google Fonts)
  "font-src $self data:" . ($F !== '' ? " $F" : ""),

  // XHR/fetch: default ke self, tambah whitelist bila ada
  "connect-src $self" . ($C !== '' ? " $C" : ""),

  // Iframe/Embed (Google Forms, dsb). Tetap izinkan self.
  "frame-src $self" . ($R !== '' ? " $R" : ""),

  // Worker (ekspor/pdfmake kadang pakai blob)
  "worker-src $self blob:",

  // Prefetch/manifes aman
  "manifest-src $self",
  "prefetch-src $self",
];

// Gabung, buang entri kosong/null, kirim aman
$csp = implode('; ', array_filter($cspParts, static fn($x) => is_string($x) && $x !== ''));
safe_header('Content-Security-Policy', $csp);

/* ===== Header keamanan pendukung ===== */
safe_header('X-Frame-Options', 'SAMEORIGIN'); // konsisten dengan frame-ancestors 'self'
safe_header('X-Content-Type-Options', 'nosniff');
safe_header('Referrer-Policy', 'strict-origin-when-cross-origin');
safe_header('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
  safe_header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
}
