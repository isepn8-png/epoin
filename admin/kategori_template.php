<?php
/**
 * admin/kategori_template.php?jenis=prestasi|pelanggaran
 * Mengunduh template Excel (.xlsx) untuk impor kategori: kolom A=Nama, B=Poin.
 */
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/epoin_security.php';
epoin_staff_guard(true);

$jenis = strtolower(trim((string)($_GET['jenis'] ?? 'prestasi')));
$isPel = ($jenis === 'pelanggaran');
$label = $isPel ? 'Pelanggaran' : 'Prestasi';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) { http_response_code(500); exit('PhpSpreadsheet tidak tersedia.'); }
require_once $autoload;

$ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $ss->getActiveSheet();
$sheet->setTitle('Template ' . $label);

// Judul kolom
$sheet->setCellValue('A1', 'Nama Kategori ' . $label);
$sheet->setCellValue('B1', 'Poin');

// Style header
$sheet->getStyle('A1:B1')->getFont()->setBold(true);
$sheet->getStyle('A1:B1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
    ->getStartColor()->setRGB($isPel ? 'FDE2E2' : 'DCFCE7');
$sheet->getColumnDimension('A')->setWidth(60);
$sheet->getColumnDimension('B')->setWidth(12);

// Contoh baris
$contoh = $isPel
    ? [['Terlambat masuk sekolah', 5], ['Tidak memakai atribut lengkap', 5], ['Membawa HP tanpa izin', 25]]
    : [['Juara lomba tingkat kabupaten', 25], ['Membantu kegiatan sekolah', 5], ['Kehadiran 100% satu semester', 20]];
$row = 2;
foreach ($contoh as $c) {
    $sheet->setCellValue('A' . $row, $c[0]);
    $sheet->setCellValue('B' . $row, $c[1]);
    $row++;
}

// Catatan
$sheet->setCellValue('A' . ($row + 1), 'CATATAN: Baris 1 adalah judul (jangan dihapus). Isi mulai baris 2. Poin = angka positif (min 1). Hapus contoh sebelum diisi data asli.');
$sheet->getStyle('A' . ($row + 1))->getFont()->setItalic(true)->getColor()->setRGB('64748B');

$fname = 'template_' . $jenis . '_epoin.xlsx';
if (ob_get_level()) { @ob_end_clean(); }
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
$writer->save('php://output');
exit;
