<?php
// admin/helpers/deskripsi_sts_helper.php
if (!function_exists('db')) {
  // Sesuaikan dengan file koneksi di proyek Anda
  require_once __DIR__ . '/../../config/koneksi.php'; // mengisi $koneksi (mysqli)
  function db() { global $koneksi; return $koneksi; }
}

/**
 * Ubah array frasa menjadi daftar bahasa Indonesia yang rapi: "a, b, dan c".
 */
function id_list_sentence(array $items): string {
  $items = array_values(array_filter(array_unique(array_map(function($x){
    $x = trim($x);
    $x = preg_replace('/^[•\-\s]+/u', '', $x); // buang bullet titik/strip
    return $x;
  }, $items)), function($x){ return $x !== ''; }));

  $n = count($items);
  if ($n === 0) return '';
  if ($n === 1) return $items[0];
  if ($n === 2) return $items[0] . ' dan ' . $items[1];
  return implode(', ', array_slice($items, 0, -1)) . ', dan ' . end($items);
}

/**
 * Generator deskripsi final dari 2 daftar string (optimal, perlu)
 */
function build_deskripsi_final(array $optimal, array $perlu): string {
  // hilangkan duplikasi lintas kategori (prioritaskan tampil di "optimal")
  $optimal_clean = array_unique(array_map('trim', $optimal));
  $perlu_clean   = array_diff(array_unique(array_map('trim', $perlu)), $optimal_clean);

  $parts = [];
  $s1 = id_list_sentence($optimal_clean);
  if ($s1 !== '') {
    $parts[] = 'Mencapai kompetensi dengan sangat baik dalam hal ' . $s1 . '.';
  }
  $s2 = id_list_sentence($perlu_clean);
  if ($s2 !== '') {
    $parts[] = 'Perlu peningkatan dalam hal ' . $s2 . '.';
  }
  if (empty($parts)) return '—';
  return implode(' ', $parts);
}

/** Ambil 2 daftar TP dari view v_rapor_pts_deskripsi untuk satu pts_id */
function load_tp_lists_by_pts($pts_id): array {
  $sql = "SELECT deskripsi_optimal, deskripsi_perlu FROM v_rapor_pts_deskripsi WHERE pts_id = ?";
  $stmt = db()->prepare($sql);
  $stmt->bind_param('i', $pts_id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $optimal = $row && !empty($row['deskripsi_optimal']) ? preg_split("/\r?\n/", $row['deskripsi_optimal']) : [];
  $perlu   = $row && !empty($row['deskripsi_perlu'])   ? preg_split("/\r?\n/", $row['deskripsi_perlu'])   : [];
  return [$optimal, $perlu];
}