<?php
/**
 * Ubah string bullet ("• item1\n• item2") menjadi: "item1, item2, dan item3"
 */
function bullet_to_list($text) {
  $text = trim((string)$text);
  if ($text === '') return '';
  $lines = preg_split('/\r?\n/', $text);
  $items = [];
  foreach ($lines as $ln) {
    $ln = trim($ln);
    if ($ln === '') continue;
    $ln = preg_replace('/^•\s*/u', '', $ln); // buang bullet
    $items[] = $ln;
  }
  $n = count($items);
  if ($n === 0) return '';
  if ($n === 1) return $items[0];
  if ($n === 2) return $items[0] . ' dan ' . $items[1];
  // Oxford-style (Bahasa Indonesia): gunakan koma + 'dan'
  return implode(', ', array_slice($items, 0, -1)) . ', dan ' . end($items);
}

/**
 * Bentuk paragraf rapor dari dua kategori: optimal & perlu.
 */
function build_deskripsi_paragraph($opt, $perlu) {
  $optList  = bullet_to_list($opt);
  $perList  = bullet_to_list($perlu);
  $parts    = [];
  if ($optList !== '') {
    $parts[] = 'Mencapai Kompetensi dengan sangat baik dalam hal ' . rtrim($optList, '.') . '.';
  }
  if ($perList !== '') {
    $parts[] = 'Perlu peningkatan dalam hal ' . rtrim($perList, '.') . '.';
  }
  if (!$parts) return 'Data deskripsi belum tersedia.';
  return implode(' ', $parts);
}