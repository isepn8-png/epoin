<?php
// admin/panduan_pemakaian.php — Panduan pemakaian seluruh modul EPOIN.
// Halaman referensi statis, bisa diakses SEMUA staf (bukan admin-only) — bukan data sensitif.

require_once __DIR__ . '/../includes/epoin_security.php';
epoin_staff_only_guard();

include 'header.php';

/**
 * Struktur konten: 1 array per section sidebar, tiap section berisi daftar modul
 * dengan judul, file tujuan, dan penjelasan singkat cara pakai.
 * Menambah modul baru cukup tambah baris di array ini — tidak perlu ubah HTML.
 */
$PANDUAN = [
  [
    'id' => 'master-data',
    'title' => 'Master Data',
    'icon' => 'fa-database',
    'desc' => 'Data dasar yang jadi rujukan seluruh modul lain. Isi bagian ini dulu di awal tahun ajaran sebelum memakai modul poin/absensi/penilaian.',
    'items' => [
      ['Siswa', 'siswa.php', 'Kelola data peserta didik: tambah/edit/hapus, foto profil, NIS, password awal. Import massal tersedia lewat menu Impor.'],
      ['Manajemen Kelas', 'kelas.php', 'Buat kelas per tahun ajaran, tetapkan wali kelas, pindahkan atau keluarkan siswa dari kelas.'],
      ['Tingkat Kelas / Jurusan', 'jurusan.php', 'Atur struktur rombongan belajar (tingkat/jurusan) yang dipakai saat membuat kelas.'],
      ['Mata Pelajaran', 'mapel.php', 'Daftar mata pelajaran sekolah — dipakai di penilaian, absensi per JP, dan penugasan guru.'],
      ['Penugasan Guru Mapel', 'pengampu_mapel.php', 'Tentukan guru mana mengajar mapel apa di kelas mana. Wajib diisi agar guru bisa input nilai/absensi mapel-nya.'],
      ['Tahun Ajaran', 'ta.php', 'Kelola tahun ajaran aktif dan pergantian semester. Banyak laporan difilter berdasarkan TA yang aktif di sini.'],
      ['Kalender Akademik', 'kalender_akademik.php', 'Tandai hari libur/non-efektif — memengaruhi perhitungan rekap absensi otomatis.'],
      ['Manajemen Pengguna', 'manajemen_pengguna.php', 'Kelola akun staf (guru/TAS/piket/BK/admin): tambah akun, atur role, suspend/aktifkan akun.'],
      ['Role &amp; Hak Akses', 'role_permission.php', 'Matrix RBAC — atur permission apa saja yang dimiliki tiap role. Khusus superadmin.'],
      ['Sekolah', 'sekolah.php', 'Profil sekolah (nama, logo, NPSN, alamat), data pejabat penandatangan surat, dan status lisensi aplikasi.'],
    ],
  ],
  [
    'id' => 'kategori-poin',
    'title' => 'Kategori Poin',
    'icon' => 'fa-tags',
    'desc' => 'Daftar "jenis" pelanggaran/prestasi beserta bobot poinnya. Diisi sekali di awal, lalu dipakai berulang saat input poin harian.',
    'items' => [
      ['Jenis Prestasi', 'prestasi.php', 'Daftar kategori prestasi (mis. "Juara lomba", "Bantu guru") beserta bobot poin positifnya. Sesuaikan dengan kebijakan sekolah.'],
      ['Jenis Pelanggaran', 'pelanggaran.php', 'Daftar kategori pelanggaran beserta bobot poin negatifnya — ini yang menentukan seberapa cepat siswa mencapai ambang SP.'],
    ],
  ],
  [
    'id' => 'kelola-poin',
    'title' => 'Kelola Poin (Disiplin)',
    'icon' => 'fa-scale-balanced',
    'desc' => 'Modul harian untuk mencatat kejadian dan memantau saldo poin siswa. Saldo = total prestasi − total pelanggaran.',
    'items' => [
      ['Input Prestasi', 'input_prestasi.php', 'Catat prestasi seorang siswa: pilih siswa, pilih kategori dari daftar Jenis Prestasi, simpan.'],
      ['Input Pelanggaran', 'input_pelanggaran.php', 'Catat pelanggaran seorang siswa dengan cara yang sama seperti Input Prestasi.'],
      ['Input Kolektif', 'poin_kolektif.php', 'Input massal ke banyak siswa sekaligus dalam satu kelas (mis. seluruh kelas terlambat bareng) — lebih cepat daripada input satu-satu.'],
      ['Laporan Poin Siswa', 'laporan.php', 'Rekap saldo poin semua siswa, lihat riwayat, dan terbitkan Surat Peringatan (SP) dari sini via menu Riwayat Siswa.'],
    ],
    'note' => 'Surat Peringatan (SP1–SP4) diterbitkan <b>otomatis berjenjang</b> berdasarkan saldo poin negatif siswa. Ambang tiap level SP bisa diatur di menu <a href="pengaturan_sp.php">Pengaturan → Ambang SP</a> — tidak perlu ganti kode, tinggal ubah angka skala &amp; jumlah level di sana, seluruh modul (cetak surat, dashboard, portal siswa) otomatis ikut.',
  ],
  [
    'id' => 'absensi',
    'title' => 'Absensi',
    'icon' => 'fa-calendar-check',
    'desc' => 'Pencatatan kehadiran, baik per jam pelajaran (oleh guru mapel) maupun rekap harian (oleh wali kelas/piket).',
    'items' => [
      ['Absensi Mapel (Per JP)', 'absensi_mapel.php', 'Guru mapel mengisi kehadiran siswa untuk jam pelajarannya sendiri, per pertemuan.'],
      ['Absensi Harian', 'absensi_harian.php', 'Rekap kehadiran harian per kelas, biasanya diisi wali kelas atau petugas piket.'],
      ['Sinkron Absensi', 'data_absensi.php', 'Menyamakan/menarik data absensi antar sumber agar rekap tidak selisih.'],
      ['Monitoring Absensi', 'rekap_kelas.php', 'Pantau real-time siapa yang belum diabsen hari ini — berguna untuk piket.'],
      ['Laporan Absensi', 'laporan_absensi.php', 'Rekap kehadiran periodik per siswa atau per kelas, siap cetak/ekspor.'],
    ],
  ],
  [
    'id' => 'penilaian',
    'title' => 'Penilaian',
    'icon' => 'fa-clipboard-check',
    'desc' => 'Input nilai harian sampai penyusunan rapor semester (STS). Isi Tujuan Pembelajaran dulu sebelum input nilai.',
    'items' => [
      ['Tujuan Pembelajaran', 'tujuan_pembelajaran.php', 'Daftar Tujuan Pembelajaran (TP) per mata pelajaran &amp; semester — dasar deskripsi capaian di rapor.'],
      ['Input Nilai Harian', 'nilai_harian.php', 'Guru mengisi nilai harian siswa sepanjang semester berjalan (bisa dicicil, tidak harus sekali input).'],
      ['Input Nilai STS', 'nilai_pts.php', 'Input nilai Sumatif Tengah Semester (STS/PTS/UTS).'],
      ['Nilai Tersimpan', 'nilai_rapor_tersimpan.php', 'Tinjau ulang nilai yang sudah tersimpan sebelum dicetak ke rapor.'],
      ['Status Penilaian', 'status_penilaian.php', 'Cek kelengkapan input nilai per guru/kelas/mapel — bantu memantau siapa yang belum input.'],
      ['Leger Rapor STS', 'leger_rapor_sts.php', 'Rekapitulasi nilai semua mapel dalam satu tabel per kelas (leger).'],
      ['Cetak Rapor STS', 'rapor_sts_cetak.php', 'Generate dokumen rapor siap cetak untuk siswa/kelas.'],
    ],
  ],
  [
    'id' => 'ujian-cbt',
    'title' => 'Ujian &amp; CBT',
    'icon' => 'fa-layer-group',
    'desc' => 'Ujian online dan pengelolaan tugas.',
    'items' => [
      ['Ujian Exam GForm', 'ujian_gform.php', 'Setup ujian berbasis Google Form yang dimodifikasi (fullscreen guard, deteksi pindah tab, monitoring live).'],
      ['Kelola Tugas (e-Tugas)', 'etugas.php', 'Guru membuat &amp; mengelola tugas untuk siswa.'],
      ['Review Pengumpulan', 'etugas_pengumpulan.php', 'Tinjau &amp; nilai tugas yang sudah dikumpulkan siswa.'],
      ['Rekap Tugas', 'etugas_rekap.php', 'Rekapitulasi nilai tugas per siswa/kelas, bisa diekspor CSV.'],
    ],
  ],
  [
    'id' => 'pengaturan',
    'title' => 'Pengaturan',
    'icon' => 'fa-gear',
    'desc' => 'Pengaturan akun &amp; sistem.',
    'items' => [
      ['Ganti Password', 'gantipassword.php', 'Ubah password akun Anda sendiri.'],
      ['Ambang SP', 'pengaturan_sp.php', 'Khusus admin. Atur skala poin maksimal &amp; jumlah level Surat Peringatan — lihat catatan di section Kelola Poin di atas.'],
    ],
  ],
];

?>
<div class="content-wrapper">

  <section class="content-header">
    <h1>
      <i class="fa-solid fa-circle-question"></i> Panduan Pemakaian
      <small>Referensi seluruh modul E-Poin</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="index.php"><i class="fa fa-home"></i> Home</a></li>
      <li class="active">Panduan Pemakaian</li>
    </ol>
  </section>

  <section class="content" style="padding:16px;">
    <div class="row">
      <div class="col-lg-10 col-md-12">

        <div class="box" style="border-radius:14px;overflow:hidden;">
          <div class="box-body">
            <p style="color:#475569;margin:0 0 12px;">
              Halaman ini merangkum fungsi setiap menu di E-Poin. Klik salah satu untuk lompat ke bagiannya.
            </p>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
              <?php foreach ($PANDUAN as $sec): ?>
                <a href="#<?php echo epoin_h($sec['id']); ?>" class="btn btn-default btn-sm" style="border-radius:999px;">
                  <i class="fa-solid <?php echo epoin_h($sec['icon']); ?>"></i> <?php echo epoin_h($sec['title']); ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <?php foreach ($PANDUAN as $sec): ?>
        <div class="box" id="<?php echo epoin_h($sec['id']); ?>" style="border-radius:14px;overflow:hidden;scroll-margin-top:20px;">
          <div class="box-header with-border">
            <h3 class="box-title" style="font-weight:800;">
              <i class="fa-solid <?php echo epoin_h($sec['icon']); ?>"></i> <?php echo epoin_h($sec['title']); ?>
            </h3>
          </div>
          <div class="box-body">
            <p style="color:#475569;"><?php echo $sec['desc']; ?></p>

            <?php if (!empty($sec['note'])): ?>
              <div class="alert alert-info" style="border-radius:10px;">
                <i class="fa-solid fa-circle-info"></i> <?php echo $sec['note']; ?>
              </div>
            <?php endif; ?>

            <table class="table table-bordered" style="margin-top:10px;">
              <thead>
                <tr><th style="width:220px;">Menu</th><th>Kegunaan</th></tr>
              </thead>
              <tbody>
                <?php foreach ($sec['items'] as $it): [$label, $file, $help] = $it; ?>
                  <tr>
                    <td><b><a href="<?php echo epoin_h($file); ?>"><?php echo epoin_h($label); ?></a></b></td>
                    <td><?php echo $help; ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endforeach; ?>

      </div>
    </div>
  </section>
</div>
<?php include 'footer.php'; ?>
