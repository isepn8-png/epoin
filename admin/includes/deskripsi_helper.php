<?php
/**
 * Ubah "• item1\n• item2" → "item1, item2, dan item3"
 */
function bullet_to_list($text) {
  $text = trim((string)$text);
  if ($text === '') return '';
  $items = [];
  foreach (preg_split('/\r?\n/', $text) as $ln) {
    $ln = trim($ln);
    if ($ln === '') continue;
    $ln = preg_replace('/^•\s*/u', '', $ln);
    $items[] = $ln;
  }
  $n = count($items);
  if ($n === 0) return '';
  if ($n === 1) return $items[0];
  if ($n === 2) return $items[0] . ' dan ' . $items[1];
  return implode(', ', array_slice($items, 0, -1)) . ', dan ' . end($items);
}

/**
 * Gabungkan kategori optimal & perlu menjadi paragraf rapor.
 */
function build_deskripsi_paragraph($opt, $perlu) {
  $optList = bullet_to_list($opt);
  $perList = bullet_to_list($perlu);
  $parts = [];
  if ($optList !== '') {
    $parts[] = 'Mencapai Kompetensi dengan sangat baik dalam hal ' . rtrim($optList, '.') . '.';
  }
  if ($perList !== '') {
    $parts[] = 'Perlu peningkatan dalam hal ' . rtrim($perList, '.') . '.';
  }
  return $parts ? implode(' ', $parts) : 'Data deskripsi belum tersedia.';
}
