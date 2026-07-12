<?php
// includes/theme_brand.php
// Satu-satunya sumber brand: judul aplikasi, nama sekolah (DB), dan logo.

if (session_status() === PHP_SESSION_NONE) { @session_start(); }

/* ==== UTIL ==== */
if (!function_exists('__tb_pick')) {
  function __tb_pick(array $row, array $keys, $fallback=null){
    foreach($keys as $k){ if(isset($row[$k]) && trim((string)$row[$k])!=='') return $row[$k]; }
    return $fallback;
  }
}

/* ==== AMBIL ROW SEKOLAH ==== */
$__tb_row = null;
$__tb_school_name = 'SMPN 1 Gunungtanjung'; // fallback aman
$__tb_logo_path = null;

if (isset($koneksi) && $koneksi instanceof mysqli) {
  $q = null;
  if (!empty($SEKOLAH_ID)) {
    $q = @mysqli_query($koneksi, "SELECT * FROM sekolah WHERE sekolah_id=".(int)$SEKOLAH_ID." LIMIT 1");
  }
  if (!$q) { $q = @mysqli_query($koneksi, "SELECT * FROM sekolah LIMIT 1"); }
  $__tb_row = $q ? @mysqli_fetch_assoc($q) : null;

  if ($__tb_row) {
    // ⬇️ Nama sekolah: WAJIB dari kolom ini sesuai permintaan
    $__tb_school_name = __tb_pick($__tb_row, ['nama_sekolah'], $__tb_school_name);

    // ⬇️ Logo: utamakan kolom 'logo_path' (relatif ke /gambar/sistem), dukung juga URL absolut
    $__tb_logo_path = __tb_pick($__tb_row, ['logo_path']);
  }
}

/* ==== RESOLUSI PATH RELATIF/ABSOLUT ==== */
$REL = (strpos($_SERVER['SCRIPT_NAME'],'/admin/')!==false || strpos($_SERVER['SCRIPT_NAME'],'/siswa/')!==false) ? '../' : './';

if ($__tb_logo_path && preg_match('~^https?://~i', $__tb_logo_path)) {
  // Logo berupa URL absolut — pakai apa adanya.
} else {
  // Logo berupa nama file di /gambar/sistem/. Ambil basename saja (cegah path traversal),
  // lalu pastikan file-nya benar-benar ADA di disk. Jika hilang / kolom kosong,
  // fallback ke logo default agar tidak muncul gambar rusak (broken image).
  $__tb_logo_file = $__tb_logo_path ? basename($__tb_logo_path) : '';
  $__tb_logo_fs   = __DIR__ . '/../gambar/sistem/' . $__tb_logo_file;
  if ($__tb_logo_file !== '' && is_file($__tb_logo_fs)) {
    $__tb_logo_path = $REL.'gambar/sistem/'.$__tb_logo_file;
  } else {
    $__tb_logo_path = $REL.'gambar/sistem/logonesagun.png';
  }
}

/* ==== OBJEK BRAND UTAMA (boleh dioverride sebelum render) ==== */
$THEME_BRAND = array_replace([
  // Baris 1 (nama aplikasi)
  'title'     => 'E-Poin Siswa',
  // Baris 2 (nama sekolah) — SELALU dari DB kolom `nama_sekolah`
  'subtitle'  => $__tb_school_name,
  // Logo besar & mini
  'logo'      => $__tb_logo_path,
  'logo_mini' => $__tb_logo_path,
  // Link saat brand di-klik
  'link'      => 'index.php',
], isset($THEME_BRAND) && is_array($THEME_BRAND) ? $THEME_BRAND : []);

/* ==== HELPER RENDER BRAND DI HEADER ==== */
if (!function_exists('render_theme_brand')) {
  function render_theme_brand(array $cfg){
    $title    = htmlspecialchars($cfg['title']    ?? 'App', ENT_QUOTES, 'UTF-8');
    $subtitle = htmlspecialchars($cfg['subtitle'] ?? '',    ENT_QUOTES, 'UTF-8');
    $logo     = htmlspecialchars($cfg['logo']     ?? '',    ENT_QUOTES, 'UTF-8');
    $logoMini = htmlspecialchars($cfg['logo_mini']?? $logo, ENT_QUOTES, 'UTF-8');
    $href     = htmlspecialchars($cfg['link']     ?? 'index.php', ENT_QUOTES, 'UTF-8');
    ob_start(); ?>
    <a href="<?php echo $href; ?>" class="logo" aria-label="Ke dashboard">
      <span class="logo-mini"><img src="<?php echo $logoMini; ?>" alt="Logo" class="brand-mini"></span>
      <span class="logo-lg brand2">
        <img src="<?php echo $logo; ?>" alt="Logo sekolah" class="brand-logo">
        <span class="brand-text">
          <span class="brand-title"><?php echo $title; ?></span>
          <?php if ($subtitle!==''): ?><span class="brand-subtitle"><?php echo $subtitle; ?></span><?php endif; ?>
        </span>
      </span>
    </a>
    <?php return trim(ob_get_clean());
  }
}

/* ==== TITLE BUILDER UNTUK <title> ==== */
if (!function_exists('build_full_title')) {
  // $pageTitle opsional: "Dashboard", "Siswa", "Administrator", dsb.
  function build_full_title(string $pageTitle=null){
    global $THEME_BRAND;
    $left = ($pageTitle && trim($pageTitle)!=='') ? trim($pageTitle) : null;
    $right = trim(($THEME_BRAND['title'] ?? 'App').' '.($THEME_BRAND['subtitle'] ?? ''));
    return htmlspecialchars(trim(($left ? $left.' - ' : '').$right), ENT_QUOTES, 'UTF-8');
  }
}

/* ==== SHORTCUTS (opsional dipakai di footer/konten) ==== */
if (!function_exists('brand_school_name')) {
  function brand_school_name(){ global $THEME_BRAND; return htmlspecialchars($THEME_BRAND['subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('brand_app_name')) {
  function brand_app_name(){ global $THEME_BRAND; return htmlspecialchars($THEME_BRAND['title'] ?? 'App', ENT_QUOTES, 'UTF-8'); }
}
