<?php
// admin/panduan_pemakaian.php — Panduan pemakaian seluruh modul EPOIN (kegunaan + langkah).
// Halaman referensi statis, bisa diakses SEMUA staf (bukan admin-only) — bukan data sensitif.

require_once __DIR__ . '/../includes/epoin_security.php';
epoin_staff_only_guard();

include 'header.php';

/**
 * Tiap item: [label, file.php, 'kegunaan (fungsi)', [langkah 1, langkah 2, ...]].
 * 'langkah' opsional — kalau kosong, hanya tampil kegunaan.
 * Menambah/mengubah panduan cukup edit array ini; HTML tidak perlu disentuh.
 */
$PANDUAN = [
  [
    'id' => 'master-data', 'title' => 'Master Data', 'icon' => 'fa-database',
    'desc' => 'Data dasar rujukan seluruh modul lain. <b>Isi bagian ini dulu di awal tahun ajaran</b> sebelum memakai modul poin/absensi/penilaian.',
    'items' => [
      ['Siswa', 'siswa.php', 'Kelola data peserta didik (tambah/edit/hapus, foto, NIS, password awal). Tersedia import massal dari Excel.',
        ['Klik <b>Tambah Siswa</b> atau <b>Impor</b> untuk banyak siswa sekaligus.', 'Untuk impor: unduh template, isi, lalu unggah.', 'Pastikan Jurusan/Tingkat sudah dibuat lebih dulu agar bisa dipilih.']],
      ['Manajemen Kelas', 'kelas.php', 'Buat kelas per tahun ajaran, tetapkan wali kelas, kelola anggota siswa.',
        ['Pilih Tahun Ajaran aktif.', 'Klik <b>Tambah Kelas</b>, isi nama & tingkat, tetapkan wali kelas.', 'Buka kelas → tambahkan/pindahkan siswa ke dalamnya.']],
      ['Tingkat Kelas / Jurusan', 'jurusan.php', 'Struktur rombongan belajar (tingkat/jurusan) yang dipakai saat membuat kelas & siswa.', []],
      ['Mata Pelajaran', 'mapel.php', 'Daftar mapel sekolah — dipakai di penilaian, absensi per JP, dan penugasan guru.', []],
      ['Penugasan Guru Mapel', 'pengampu_mapel.php', 'Tentukan guru mengajar mapel apa di kelas mana. Wajib agar guru bisa input nilai/absensi mapelnya.',
        ['Pilih guru, mapel, dan kelas.', 'Simpan. Guru tsb kini bisa input nilai/absensi untuk kombinasi itu.']],
      ['Tahun Ajaran', 'ta.php', 'Kelola tahun ajaran aktif & pergantian semester. Banyak laporan difilter berdasarkan TA aktif di sini.', []],
      ['Kalender Akademik', 'kalender_akademik.php', 'Tandai hari libur/non-efektif — memengaruhi perhitungan rekap absensi otomatis.', []],
      ['Manajemen Pengguna', 'manajemen_pengguna.php', 'Kelola akun staf (guru/TAS/piket/BK/admin): tambah, atur role, suspend/aktifkan.',
        ['Klik <b>Tambah Pengguna</b>, isi nama & username (NIP/NUPTK untuk guru).', 'Pilih role (menentukan menu yang bisa diakses).', 'Beri password awal; user ganti sendiri saat login pertama.']],
      ['Role &amp; Hak Akses', 'role_permission.php', 'Matrix RBAC — atur permission tiap role. <b>Khusus superadmin.</b>', []],
      ['Sekolah', 'sekolah.php', 'Profil sekolah (nama, logo, NPSN, alamat), pejabat penandatangan surat, & status lisensi.',
        ['Klik <b>Ubah Data</b> untuk profil & pejabat (Kepala Sekolah, Waka, BK).', 'Data pejabat dipakai otomatis di tanda tangan Surat Peringatan & dokumen.', 'Panel Aktivasi & Lisensi: masukkan License Key + Kode Aktivasi lalu Simpan.']],
    ],
  ],
  [
    'id' => 'kategori-poin', 'title' => 'Kategori Poin', 'icon' => 'fa-tags',
    'desc' => 'Daftar "jenis" pelanggaran/prestasi beserta bobot poinnya. Diisi sekali di awal, dipakai berulang saat input poin harian.',
    'items' => [
      ['Jenis Prestasi', 'prestasi.php', 'Kategori prestasi + bobot poin positif. Ada 3 cara cepat mengisi: manual, import Excel, atau muat rekomendasi.',
        ['<b>Tambah manual</b>: klik "Tambah Prestasi Baru".', '<b>Muat Rekomendasi</b>: klik untuk menanam daftar referensi kurasi EPOIN (bisa diedit/dihapus setelahnya).', '<b>Import Excel</b>: klik → unduh template → isi kolom Nama & Poin → unggah. Nama duplikat otomatis dilewati.', '<b>Hapus Semua</b>: menghapus kategori yang belum terpakai (yang sudah dipakai di data siswa tetap aman).']],
      ['Jenis Pelanggaran', 'pelanggaran.php', 'Kategori pelanggaran + bobot poin negatif — menentukan seberapa cepat siswa mencapai ambang SP. Cara mengisi sama seperti Jenis Prestasi.',
        ['Sama seperti Jenis Prestasi: manual / Muat Rekomendasi / Import Excel / Hapus Semua.', 'Poin diisi angka positif; tanda minus ditampilkan otomatis oleh sistem.']],
    ],
    'note' => 'Fitur <b>Muat Rekomendasi</b> menanam daftar referensi (38 prestasi &amp; 137 pelanggaran) yang sudah dikurasi — sekolah bebas memakai sebagian, semua, atau menghapus semuanya lalu membuat sendiri.',
  ],
  [
    'id' => 'kelola-poin', 'title' => 'Kelola Poin (Disiplin)', 'icon' => 'fa-scale-balanced',
    'desc' => 'Modul harian mencatat kejadian & memantau saldo poin. Saldo = total prestasi − total pelanggaran.',
    'items' => [
      ['Input Prestasi', 'input_prestasi.php', 'Catat prestasi seorang siswa.',
        ['Cari/pilih siswa.', 'Pilih kategori dari daftar Jenis Prestasi.', 'Simpan — saldo poin siswa otomatis bertambah.']],
      ['Input Pelanggaran', 'input_pelanggaran.php', 'Catat pelanggaran seorang siswa.',
        ['Cari/pilih siswa.', 'Pilih kategori dari daftar Jenis Pelanggaran.', 'Simpan — bila saldo negatif mencapai ambang, siswa masuk tahap SP.']],
      ['Input Kolektif', 'poin_kolektif.php', 'Input massal ke banyak siswa dalam satu kelas sekaligus (mis. sekelas terlambat bersama).',
        ['Pilih kelas.', 'Pilih kategori & centang siswa yang terkena.', 'Simpan sekali untuk semua — jauh lebih cepat.']],
      ['Laporan Poin Siswa', 'laporan.php', 'Rekap saldo poin semua siswa, lihat riwayat, & terbitkan Surat Peringatan (SP).',
        ['Buka profil/Riwayat seorang siswa.', 'Lihat tahap pembinaan & tombol SP1–SP4 (aktif sesuai ambang).', 'Klik Terbitkan/Cetak SP untuk menghasilkan surat siap tanda tangan.']],
    ],
    'note' => 'Surat Peringatan diterbitkan <b>otomatis berjenjang</b> berdasarkan saldo poin negatif. Ambang tiap level bisa diatur di <a href="pengaturan_sp.php">Pengaturan → Ambang SP</a> tanpa ganti kode — cukup ubah skala &amp; jumlah level, seluruh modul (cetak surat, dashboard, portal siswa) otomatis ikut.',
  ],
  [
    'id' => 'absensi', 'title' => 'Absensi', 'icon' => 'fa-calendar-check',
    'desc' => 'Pencatatan kehadiran per jam pelajaran (guru mapel) maupun rekap harian (wali kelas/piket).',
    'items' => [
      ['Absensi Mapel (Per JP)', 'absensi_mapel.php', 'Guru mapel mengisi kehadiran untuk jam pelajarannya sendiri, per pertemuan.',
        ['Pilih kelas, mapel, & tanggal/JP.', 'Tandai status tiap siswa (Hadir/Izin/Sakit/Alpa).', 'Simpan.']],
      ['Absensi Harian', 'absensi_harian.php', 'Rekap kehadiran harian per kelas (wali kelas/piket).', []],
      ['Sinkron Absensi', 'data_absensi.php', 'Menyamakan/menarik data absensi antar sumber agar rekap tidak selisih.', []],
      ['Monitoring Absensi', 'rekap_kelas.php', 'Pantau real-time kelas mana yang belum diabsen hari ini — berguna untuk piket.', []],
      ['Laporan Absensi', 'laporan_absensi.php', 'Rekap kehadiran periodik per siswa/kelas, siap cetak/ekspor.', []],
    ],
  ],
  [
    'id' => 'penilaian', 'title' => 'Penilaian', 'icon' => 'fa-clipboard-check',
    'desc' => 'Input nilai harian sampai rapor semester (STS). <b>Isi Tujuan Pembelajaran dulu</b> sebelum input nilai.',
    'items' => [
      ['Tujuan Pembelajaran', 'tujuan_pembelajaran.php', 'Daftar TP per mapel & semester — dasar deskripsi capaian di rapor.', []],
      ['Input Nilai Harian', 'nilai_harian.php', 'Guru mengisi nilai harian sepanjang semester (boleh dicicil).',
        ['Pilih kelas & mapel (harus sudah ditugaskan ke Anda).', 'Isi nilai per siswa, simpan bertahap.']],
      ['Input Nilai STS', 'nilai_pts.php', 'Input nilai Sumatif Tengah Semester (STS/PTS/UTS).', []],
      ['Nilai Tersimpan', 'nilai_rapor_tersimpan.php', 'Tinjau ulang nilai tersimpan sebelum dicetak ke rapor.', []],
      ['Status Penilaian', 'status_penilaian.php', 'Cek kelengkapan input nilai per guru/kelas/mapel — pantau siapa yang belum input.', []],
      ['Leger Rapor STS', 'leger_rapor_sts.php', 'Rekapitulasi nilai semua mapel dalam satu tabel per kelas (leger).', []],
      ['Cetak Rapor STS', 'rapor_sts_cetak.php', 'Generate dokumen rapor siap cetak untuk siswa/kelas.', []],
    ],
  ],
  [
    'id' => 'ujian-cbt', 'title' => 'Ujian &amp; CBT', 'icon' => 'fa-layer-group',
    'desc' => 'Ujian online & pengelolaan tugas.',
    'items' => [
      ['Ujian Exam GForm', 'ujian_gform.php', 'Setup ujian berbasis Google Form termodifikasi (fullscreen guard, deteksi pindah tab, monitoring live).', []],
      ['Kelola Tugas (e-Tugas)', 'etugas.php', 'Guru membuat & mengelola tugas untuk siswa.',
        ['Klik Tambah Tugas, isi judul, kelas tujuan, tenggat, lampiran.', 'Publikasikan — siswa melihatnya di portal "Tugas Saya".']],
      ['Review Pengumpulan', 'etugas_pengumpulan.php', 'Tinjau & nilai tugas yang dikumpulkan siswa.', []],
      ['Rekap Tugas', 'etugas_rekap.php', 'Rekapitulasi nilai tugas per siswa/kelas, bisa diekspor CSV.', []],
    ],
  ],
  [
    'id' => 'pengaturan', 'title' => 'Pengaturan', 'icon' => 'fa-gear',
    'desc' => 'Pengaturan akun & sistem.',
    'items' => [
      ['Ganti Password', 'gantipassword.php', 'Ubah password akun Anda sendiri.', []],
      ['Ambang SP', 'pengaturan_sp.php', 'Atur skala poin maksimal & jumlah level Surat Peringatan. <b>Khusus admin.</b>',
        ['Ubah "Skala poin negatif maksimal" (mis. 100 atau 200).', 'Ubah "Jumlah level SP" bila perlu.', 'Simpan — ambang tiap SP dihitung otomatis proporsional, semua modul ikut.']],
    ],
  ],
];

?>
<div class="content-wrapper">

  <section class="content-header">
    <h1><i class="fa-solid fa-circle-question"></i> Panduan Pemakaian <small>Referensi seluruh modul E-Poin</small></h1>
    <ol class="breadcrumb">
      <li><a href="index.php"><i class="fa fa-home"></i> Home</a></li>
      <li class="active">Panduan Pemakaian</li>
    </ol>
  </section>

  <section class="content" style="padding:16px;">
    <div class="row">
      <div class="col-lg-10 col-md-12">

        <!-- Alur pertama kali -->
        <div class="box box-solid" style="border-radius:14px;overflow:hidden;border-top:3px solid #2563eb;">
          <div class="box-body">
            <h4 style="font-weight:800;margin-top:0;"><i class="fa fa-flag-checkered" style="color:#2563eb"></i> Alur Pemakaian Pertama Kali (urutan disarankan)</h4>
            <p style="color:#475569;margin-bottom:8px;">Untuk sekolah baru, ikuti urutan ini agar setiap modul punya data rujukan yang dibutuhkan:</p>
            <ol style="color:#334155;line-height:1.9;">
              <li><b>Sekolah</b> — isi profil, logo, & pejabat penandatangan.</li>
              <li><b>Tahun Ajaran</b> &amp; <b>Jurusan/Tingkat</b> — buat TA aktif & struktur.</li>
              <li><b>Mata Pelajaran</b> &amp; <b>Kelas</b> (+ wali kelas).</li>
              <li><b>Siswa</b> — tambah/impor peserta didik ke kelas.</li>
              <li><b>Manajemen Pengguna</b> — buat akun guru/staf & role-nya.</li>
              <li><b>Kategori Poin</b> — isi Jenis Prestasi &amp; Pelanggaran (pakai <b>Muat Rekomendasi</b> agar cepat).</li>
              <li><b>Ambang SP</b> — sesuaikan skala poin & jumlah level (opsional).</li>
              <li>Mulai pemakaian harian: <b>Input Poin</b>, <b>Absensi</b>, <b>Penilaian</b>.</li>
            </ol>
          </div>
        </div>

        <!-- Navigasi cepat -->
        <div class="box" style="border-radius:14px;overflow:hidden;">
          <div class="box-body">
            <p style="color:#475569;margin:0 0 10px;">Lompat ke bagian:</p>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
              <?php foreach ($PANDUAN as $sec): ?>
                <a href="#<?php echo epoin_h($sec['id']); ?>" class="btn btn-default btn-sm" style="border-radius:999px;">
                  <i class="fa-solid <?php echo epoin_h($sec['icon']); ?>"></i> <?php echo $sec['title']; ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <?php foreach ($PANDUAN as $sec): ?>
        <div class="box" id="<?php echo epoin_h($sec['id']); ?>" style="border-radius:14px;overflow:hidden;scroll-margin-top:20px;">
          <div class="box-header with-border">
            <h3 class="box-title" style="font-weight:800;"><i class="fa-solid <?php echo epoin_h($sec['icon']); ?>"></i> <?php echo $sec['title']; ?></h3>
          </div>
          <div class="box-body">
            <p style="color:#475569;"><?php echo $sec['desc']; ?></p>
            <?php if (!empty($sec['note'])): ?>
              <div class="alert alert-info" style="border-radius:10px;"><i class="fa-solid fa-circle-info"></i> <?php echo $sec['note']; ?></div>
            <?php endif; ?>

            <?php foreach ($sec['items'] as $it): [$label, $file, $help, $langkah] = array_pad($it, 4, []); ?>
              <div style="padding:12px 0;border-top:1px solid #eef2f7;">
                <div style="font-weight:700;font-size:15px;">
                  <a href="<?php echo epoin_h($file); ?>"><?php echo $label; ?></a>
                </div>
                <div style="color:#475569;margin:2px 0 6px;"><?php echo $help; ?></div>
                <?php if (!empty($langkah)): ?>
                  <div style="color:#334155;font-size:13px;">
                    <b style="color:#64748b;">Langkah:</b>
                    <ol style="margin:4px 0 0;padding-left:20px;line-height:1.8;">
                      <?php foreach ($langkah as $lk): ?><li><?php echo $lk; ?></li><?php endforeach; ?>
                    </ol>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>

      </div>
    </div>
  </section>
</div>
<?php include 'footer.php'; ?>
