<?php
/**
 * admin/siswa_template.php
 * Mengunduh template Excel (.xlsx) untuk impor data siswa secara DINAMIS.
 *
 * Kenapa dinamis (bukan file statis import_siswa.xlsx di root)?
 * File statis itu masuk .gitignore -> tidak pernah ikut ke clone tiap sekolah,
 * sehingga tombol "Unduh Template" 404 di instalasi baru. Template ini
 * dibangun on-the-fly via PhpSpreadsheet & selalu tersedia di semua situs,
 * plus otomatis menampilkan daftar JURUSAN_ID milik sekolah ybs.
 *
 * Kolom mengikuti parser admin/siswa_import_act.php: NIS, NAMA, STATUS, JURUSAN_ID.
 */
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/epoin_security.php';
epoin_staff_guard(true);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) { http_response_code(500); exit('PhpSpreadsheet tidak tersedia.'); }
require_once $autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

// Ambil daftar jurusan valid milik sekolah ini (utk contoh & sheet referensi)
$jurusanList = [];
if (isset($koneksi) && ($koneksi instanceof mysqli)) {
    $q = mysqli_query($koneksi, "SELECT jurusan_id, jurusan_nama FROM jurusan ORDER BY jurusan_id ASC");
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) {
            $jurusanList[] = ['id' => (string)$r['jurusan_id'], 'nama' => (string)$r['jurusan_nama']];
        }
    }
}
// JURUSAN_ID contoh: pakai milik sekolah kalau ada, jika belum ada jurusan pakai placeholder
$idContoh1 = $jurusanList[0]['id'] ?? '1';
$idContoh2 = $jurusanList[1]['id'] ?? $idContoh1;

$ss = new Spreadsheet();

/* =========================================================
   SHEET 1 — Template isian
   ========================================================= */
$sheet = $ss->getActiveSheet();
$sheet->setTitle('Data Siswa');

$headers = ['NIS', 'NAMA', 'STATUS', 'JURUSAN_ID'];
$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($col . '1', $h);
    $col++;
}

// Style header
$sheet->getStyle('A1:D1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E88E5');
$sheet->getStyle('A1:D1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getRowDimension(1)->setRowHeight(22);
$sheet->getColumnDimension('A')->setWidth(20);
$sheet->getColumnDimension('B')->setWidth(38);
$sheet->getColumnDimension('C')->setWidth(16);
$sheet->getColumnDimension('D')->setWidth(14);

// Contoh baris (NIS sebagai TEKS agar angka depan 0 tidak hilang)
$contoh = [
    ['2024001', 'Ahmad Fauzi',        'aktif', $idContoh1],
    ['2024002', 'Siti Nurhaliza',     'aktif', $idContoh2],
    ['2024003', 'Budi Santoso',       'aktif', $idContoh1],
];
$row = 2;
foreach ($contoh as $c) {
    $sheet->setCellValueExplicit('A' . $row, $c[0], DataType::TYPE_STRING);
    $sheet->setCellValue('B' . $row, $c[1]);
    $sheet->setCellValue('C' . $row, $c[2]);
    $sheet->setCellValueExplicit('D' . $row, $c[3], DataType::TYPE_STRING);
    $row++;
}
// Paksa kolom NIS & JURUSAN_ID sebagai teks
$sheet->getStyle('A2:A100')->getNumberFormat()->setFormatCode('@');
$sheet->getStyle('D2:D100')->getNumberFormat()->setFormatCode('@');

// Border tipis area contoh
$sheet->getStyle('A1:D' . ($row - 1))->getBorders()->getAllBorders()
    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');

// Catatan
$noteRow = $row + 1;
$notes = [
    'CATATAN PENTING:',
    '1. Baris 1 = judul kolom (JANGAN diubah/dihapus). Data mulai baris 2.',
    '2. Hapus 3 baris contoh sebelum mengisi data asli.',
    '3. STATUS harus salah satu: aktif / tamat / pindah / dikeluarkan (huruf kecil).',
    '4. JURUSAN_ID harus sesuai daftar di sheet "Referensi Jurusan" (tab bawah).',
    '5. NIS tidak boleh kosong & tidak boleh sama antar siswa (jadi kunci data).',
    '6. Siswa baru otomatis dapat password awal = md5(NIS).',
];
foreach ($notes as $i => $n) {
    $cell = 'A' . ($noteRow + $i);
    $sheet->setCellValue($cell, $n);
    $style = $sheet->getStyle($cell)->getFont();
    if ($i === 0) { $style->setBold(true)->getColor()->setRGB('C62828'); }
    else { $style->setItalic(true)->getColor()->setRGB('64748B'); }
}

/* =========================================================
   SHEET 2 — Referensi Jurusan (JURUSAN_ID valid sekolah ini)
   ========================================================= */
$ref = $ss->createSheet();
$ref->setTitle('Referensi Jurusan');
$ref->setCellValue('A1', 'JURUSAN_ID');
$ref->setCellValue('B1', 'Nama Jurusan / Tingkat Kelas');
$ref->getStyle('A1:B1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$ref->getStyle('A1:B1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2E7D32');
$ref->getColumnDimension('A')->setWidth(14);
$ref->getColumnDimension('B')->setWidth(42);

if (!empty($jurusanList)) {
    $rr = 2;
    foreach ($jurusanList as $j) {
        $ref->setCellValueExplicit('A' . $rr, $j['id'], DataType::TYPE_STRING);
        $ref->setCellValue('B' . $rr, $j['nama']);
        $rr++;
    }
    $ref->getStyle('A2:A' . ($rr - 1))->getNumberFormat()->setFormatCode('@');
    $ref->getStyle('A1:B' . ($rr - 1))->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');
} else {
    $ref->setCellValue('A2', '(Belum ada jurusan)');
    $ref->mergeCells('A2:B2');
    $ref->setCellValue('A3', 'Tambahkan data Jurusan/Kelas dulu di menu "Tingkat Kelas / Jurusan" sebelum impor siswa.');
    $ref->mergeCells('A3:B3');
    $ref->getStyle('A3')->getFont()->setItalic(true)->getColor()->setRGB('C62828');
}

$ss->setActiveSheetIndex(0);

$fname = 'template_import_siswa.xlsx';
if (ob_get_level()) { @ob_end_clean(); }
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
$writer->save('php://output');
exit;
