<?php
/**
 * Riwayat versi & perubahan E-POIN — sumber tunggal utk modal "Info Versi"
 * (admin/footer.php & siswa/footer.php). Tambahkan entri baru di PALING ATAS
 * $EPOIN_CHANGELOG setiap kali ada rilis fitur/perbaikan berarti; versi "saat
 * ini" & badge otomatis ikut entri paling atas — tidak perlu ubah tempat lain.
 */

$EPOIN_CHANGELOG = [
    [
        'versi'   => '3.5.0',
        'tanggal' => '12 Juli 2026',
        'judul'   => 'Import Excel Kategori & Panduan Interaktif',
        'items'   => [
            'Fitur baru: Import Excel/CSV untuk Jenis Prestasi & Jenis Pelanggaran — ada pratinjau sebelum data disimpan, mode Lewati/Perbarui utk data duplikat, dan "Muat Rekomendasi" utk isi cepat kategori siap pakai.',
            'Fitur baru: halaman Panduan Pemakaian (menu BANTUAN) — panduan interaktif seluruh modul, bisa dicari.',
            'Peningkatan: tampilan dashboard admin dirapikan (label zona pemantauan, strip status SP, tampilan saat data kosong).',
            'Perbaikan: modal Import Excel yang sempat tampil gelap & terkunci.',
            'Perbaikan: warna teks pada kolom pencarian Panduan Pemakaian yang tidak terbaca.',
        ],
    ],
    [
        'versi'   => '3.4.0',
        'tanggal' => '11 Juli 2026',
        'judul'   => 'Dukungan Multi-Sekolah',
        'items'   => [
            'Fitur baru: nama & logo sekolah di halaman login kini mengikuti data sekolah masing-masing instalasi.',
            'Fitur baru: tombol Beranda & Tentang Aplikasi di halaman login dapat disembunyikan per sekolah.',
        ],
    ],
    [
        'versi'   => '3.3.0',
        'tanggal' => '10 Juli 2026',
        'judul'   => 'Ambang Surat Peringatan Fleksibel',
        'items'   => [
            'Fitur baru: skala poin & jumlah level Surat Peringatan (SP) kini dapat diatur lewat menu Pengaturan > Ambang SP.',
            'Peningkatan: deskripsi tiap tahap SP lebih rinci beserta program pembinaannya.',
            'Peningkatan keamanan & stabilitas sistem secara umum.',
        ],
    ],
];

if (!defined('EPOIN_VERSION')) {
    define('EPOIN_VERSION', $EPOIN_CHANGELOG[0]['versi']);
}
