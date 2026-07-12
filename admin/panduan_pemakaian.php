<?php
// admin/panduan_pemakaian.php — Panduan pemakaian seluruh modul EPOIN (kegunaan + langkah).
// Halaman referensi statis, bisa diakses SEMUA staf (bukan admin-only) — bukan data sensitif.
// UI: sidebar section (1 section aktif tampil, tidak semua di-stack) + pencarian instan + langkah collapsible.

require_once __DIR__ . '/../includes/epoin_security.php';
epoin_staff_only_guard();

include 'header.php';

/**
 * Tiap item: [label, file.php, 'kegunaan (fungsi)', [langkah 1, langkah 2, ...]].
 * 'langkah' opsional — kalau kosong, hanya tampil kegunaan.
 * Menambah/mengubah panduan cukup edit array ini; HTML/CSS/JS di bawah tidak perlu disentuh.
 */
$PANDUAN = [
  [
    'id' => 'master-data', 'title' => 'Master Data', 'icon' => 'fa-database', 'color' => '#2563eb',
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
    'id' => 'kategori-poin', 'title' => 'Kategori Poin', 'icon' => 'fa-tags', 'color' => '#7c3aed',
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
    'id' => 'kelola-poin', 'title' => 'Kelola Poin (Disiplin)', 'icon' => 'fa-scale-balanced', 'color' => '#16a34a',
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
    'id' => 'absensi', 'title' => 'Absensi', 'icon' => 'fa-calendar-check', 'color' => '#d97706',
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
    'id' => 'penilaian', 'title' => 'Penilaian', 'icon' => 'fa-clipboard-check', 'color' => '#0891b2',
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
    'id' => 'ujian-cbt', 'title' => 'Ujian &amp; CBT', 'icon' => 'fa-layer-group', 'color' => '#e11d48',
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
    'id' => 'pengaturan', 'title' => 'Pengaturan', 'icon' => 'fa-gear', 'color' => '#475569',
    'desc' => 'Pengaturan akun & sistem.',
    'items' => [
      ['Ganti Password', 'gantipassword.php', 'Ubah password akun Anda sendiri.', []],
      ['Ambang SP', 'pengaturan_sp.php', 'Atur skala poin maksimal & jumlah level Surat Peringatan. <b>Khusus admin.</b>',
        ['Ubah "Skala poin negatif maksimal" (mis. 100 atau 200).', 'Ubah "Jumlah level SP" bila perlu.', 'Simpan — ambang tiap SP dihitung otomatis proporsional, semua modul ikut.']],
    ],
  ],
];

$TOTAL_MODUL = array_sum(array_map(fn($s) => count($s['items']), $PANDUAN));
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
  <style>
    .pd-hero{
      border-radius:16px;overflow:hidden;margin-bottom:16px;position:relative;
      background:linear-gradient(120deg,#1e3a8a 0%,#2563eb 45%,#0ea5e9 100%);
      padding:22px 26px;color:#fff;box-shadow:0 10px 30px rgba(37,99,235,.25);
    }
    .pd-hero h2{margin:0 0 4px;font-weight:800;font-size:22px;}
    .pd-hero p{margin:0;opacity:.92;font-size:13.5px;}
    .pd-hero .pd-stats{display:flex;gap:10px;margin-top:14px;flex-wrap:wrap;}
    .pd-stat-chip{background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.28);
      border-radius:999px;padding:5px 14px;font-size:12.5px;font-weight:600;backdrop-filter:blur(4px);}
    .pd-search-wrap{position:relative;margin-top:16px;max-width:520px;}
    .pd-search-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#93c5fd;}
    #pdSearch{
      width:100%;border-radius:999px;border:none;padding:11px 16px 11px 40px;
      font-size:13.5px;box-shadow:0 4px 14px rgba(0,0,0,.15);outline:none;
      color:#0f172a;background:#fff;
    }
    #pdSearch::placeholder{color:#94a3b8;opacity:1;}
    #pdSearchClear{position:absolute;right:6px;top:6px;bottom:6px;width:28px;border:none;background:transparent;
      color:#64748b;cursor:pointer;display:none;}
    #pdNoResult{display:none;text-align:center;padding:40px 20px;color:#94a3b8;}
    #pdNoResult i{font-size:32px;display:block;margin-bottom:10px;}

    /* Stepper "alur pertama kali" */
    .pd-flow{border-radius:14px;background:#fff;box-shadow:0 2px 10px rgba(15,23,42,.06);margin-bottom:16px;overflow:hidden;}
    .pd-flow-head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;cursor:pointer;user-select:none;}
    .pd-flow-head h4{margin:0;font-weight:800;color:#0f172a;font-size:15px;}
    .pd-flow-head .fa-chevron-down{transition:transform .25s ease;color:#94a3b8;}
    .pd-flow.open .fa-chevron-down{transform:rotate(180deg);}
    .pd-flow-body{max-height:0;overflow:hidden;transition:max-height .35s ease;}
    .pd-flow.open .pd-flow-body{max-height:900px;}
    .pd-flow-steps{display:flex;flex-wrap:wrap;gap:10px;padding:6px 18px 18px;}
    .pd-flow-step{flex:1 1 200px;background:#f8fafc;border:1px solid #eef2f7;border-radius:12px;padding:12px 14px;position:relative;}
    .pd-flow-step .pd-num{
      width:26px;height:26px;border-radius:50%;background:#2563eb;color:#fff;font-weight:800;font-size:12.5px;
      display:inline-flex;align-items:center;justify-content:center;margin-bottom:6px;
    }
    .pd-flow-step b{display:block;font-size:13px;color:#0f172a;}
    .pd-flow-step span{font-size:12px;color:#64748b;line-height:1.5;}

    /* Layout 2 kolom */
    .pd-layout{display:flex;gap:16px;align-items:flex-start;}
    .pd-nav{
      flex:0 0 240px;position:sticky;top:15px;background:#fff;border-radius:14px;
      box-shadow:0 2px 10px rgba(15,23,42,.06);padding:10px;
    }
    .pd-nav-item{
      display:flex;align-items:center;gap:10px;width:100%;text-align:left;border:none;background:transparent;
      padding:10px 12px;border-radius:10px;margin-bottom:3px;cursor:pointer;font-size:13.5px;font-weight:600;
      color:#334155;transition:background .15s ease,color .15s ease;
    }
    .pd-nav-item .pd-ic{
      width:30px;height:30px;border-radius:9px;display:flex;align-items:center;justify-content:center;
      background:#f1f5f9;color:#64748b;flex-shrink:0;transition:.15s ease;
    }
    .pd-nav-item .pd-count{margin-left:auto;background:#f1f5f9;color:#94a3b8;border-radius:999px;
      padding:1px 8px;font-size:11px;font-weight:700;}
    .pd-nav-item:hover{background:#f8fafc;}
    .pd-nav-item.active{background:var(--sec-color,#2563eb);color:#fff;}
    .pd-nav-item.active .pd-ic{background:rgba(255,255,255,.22);color:#fff;}
    .pd-nav-item.active .pd-count{background:rgba(255,255,255,.25);color:#fff;}

    .pd-content{flex:1 1 auto;min-width:0;}
    .pd-panel{display:none;animation:pdFade .3s ease both;}
    .pd-panel.active{display:block;}
    @keyframes pdFade{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:translateY(0);}}
    .pd-panel-head{
      border-radius:14px 14px 0 0;padding:16px 20px;color:#fff;
      background:var(--sec-color,#2563eb); /* fallback solid utk browser tanpa color-mix() */
      background:linear-gradient(100deg,var(--sec-color,#2563eb),color-mix(in srgb, var(--sec-color,#2563eb) 60%, #0f172a));
    }
    .pd-panel-head h3{margin:0 0 4px;font-weight:800;font-size:18px;display:flex;align-items:center;gap:10px;}
    .pd-panel-head p{margin:0;opacity:.9;font-size:13px;}
    .pd-panel-note{background:#eff6ff;border-left:4px solid #3b82f6;padding:12px 16px;font-size:13px;
      color:#1e3a8a;border-radius:0 10px 10px 0;margin:14px 20px 0;}

    .pd-cards{padding:16px 20px 20px;display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px;}
    .pd-card{
      background:#fff;border:1px solid #eef2f7;border-radius:12px;padding:14px 16px;
      box-shadow:0 1px 3px rgba(15,23,42,.04);transition:box-shadow .18s ease,transform .18s ease;
    }
    .pd-card:hover{box-shadow:0 8px 20px rgba(15,23,42,.09);transform:translateY(-2px);}
    .pd-card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;}
    .pd-card-title{font-weight:800;font-size:14.5px;color:#0f172a;}
    .pd-card-open{
      font-size:11.5px;font-weight:700;color:#2563eb;white-space:nowrap;text-decoration:none;
      background:#eff6ff;padding:3px 10px;border-radius:999px;
    }
    .pd-card-open:hover{background:#dbeafe;text-decoration:none;}
    .pd-card-desc{color:#64748b;font-size:12.8px;margin:6px 0 8px;line-height:1.55;}
    .pd-steps-btn{
      border:none;background:#f8fafc;color:#334155;font-size:12px;font-weight:700;
      padding:6px 12px;border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;
    }
    .pd-steps-btn:hover{background:#eef2f7;}
    .pd-steps-btn i{transition:transform .2s ease;font-size:10px;}
    .pd-steps-btn.open i{transform:rotate(180deg);}
    .pd-steps{
      max-height:0;overflow:hidden;transition:max-height .3s ease;margin-top:0;
    }
    .pd-steps.open{max-height:400px;margin-top:8px;}
    .pd-steps ol{margin:0;padding-left:18px;color:#334155;font-size:12.5px;line-height:1.85;}

    .pd-badge-sec{position:relative;top:-1px;}

    @media (max-width:900px){
      .pd-layout{flex-direction:column;}
      .pd-nav{position:static;flex:1 1 auto;width:100%;display:flex;flex-wrap:wrap;gap:6px;}
      .pd-nav-item{flex:1 1 auto;width:auto;margin-bottom:0;}
      .pd-nav-item .pd-count{display:none;}
      .pd-cards{grid-template-columns:1fr;}
    }
  </style>

  <div class="col-lg-12" style="padding:0 15px;">

    <!-- HERO + SEARCH -->
    <div class="pd-hero">
      <h2><i class="fa-solid fa-book-open"></i> Panduan Pemakaian E-Poin</h2>
      <p>Cari cepat atau pilih kategori di samping — tidak perlu scroll semua modul satu per satu.</p>
      <div class="pd-stats">
        <span class="pd-stat-chip"><i class="fa-solid fa-layer-group"></i> <?php echo (int)$TOTAL_MODUL; ?> Modul</span>
        <span class="pd-stat-chip"><i class="fa-solid fa-folder"></i> <?php echo count($PANDUAN); ?> Kategori</span>
      </div>
      <div class="pd-search-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="pdSearch" placeholder="Cari menu, mis. 'absensi', 'import excel', 'SP'...">
        <button type="button" id="pdSearchClear" title="Bersihkan"><i class="fa-solid fa-xmark"></i></button>
      </div>
    </div>

    <!-- ALUR PERTAMA KALI (collapsible) -->
    <div class="pd-flow" id="pdFlow">
      <div class="pd-flow-head" id="pdFlowToggle">
        <h4><i class="fa fa-flag-checkered" style="color:#2563eb"></i> Alur Pemakaian Pertama Kali <small style="font-weight:400;color:#94a3b8;">— klik untuk lihat urutan setup</small></h4>
        <i class="fa-solid fa-chevron-down"></i>
      </div>
      <div class="pd-flow-body">
        <div class="pd-flow-steps">
          <?php
          $flow = [
            ['Sekolah', 'Isi profil, logo, & pejabat penandatangan.'],
            ['Tahun Ajaran & Jurusan', 'Buat TA aktif & struktur rombel.'],
            ['Mapel & Kelas', 'Daftar mapel, buat kelas + wali kelas.'],
            ['Siswa', 'Tambah/impor peserta didik ke kelas.'],
            ['Manajemen Pengguna', 'Buat akun guru/staf & role-nya.'],
            ['Kategori Poin', 'Isi Prestasi & Pelanggaran (pakai Muat Rekomendasi).'],
            ['Ambang SP', 'Sesuaikan skala poin & jumlah level (opsional).'],
            ['Mulai Harian', 'Input Poin, Absensi, Penilaian.'],
          ];
          foreach ($flow as $i => $f): ?>
            <div class="pd-flow-step">
              <span class="pd-num"><?php echo $i + 1; ?></span>
              <b><?php echo epoin_h($f[0]); ?></b>
              <span><?php echo epoin_h($f[1]); ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- LAYOUT 2 KOLOM -->
    <div class="pd-layout">
      <nav class="pd-nav" id="pdNav">
        <?php foreach ($PANDUAN as $i => $sec): ?>
          <button type="button" class="pd-nav-item<?php echo $i === 0 ? ' active' : ''; ?>"
                  style="--sec-color:<?php echo epoin_h($sec['color']); ?>"
                  data-target="<?php echo epoin_h($sec['id']); ?>">
            <span class="pd-ic"><i class="fa-solid <?php echo epoin_h($sec['icon']); ?>"></i></span>
            <?php echo $sec['title']; ?>
            <span class="pd-count"><?php echo count($sec['items']); ?></span>
          </button>
        <?php endforeach; ?>
      </nav>

      <div class="pd-content">
        <?php foreach ($PANDUAN as $i => $sec): ?>
        <div class="pd-panel<?php echo $i === 0 ? ' active' : ''; ?>" data-panel="<?php echo epoin_h($sec['id']); ?>" style="--sec-color:<?php echo epoin_h($sec['color']); ?>">
          <div class="pd-panel-head">
            <h3><i class="fa-solid <?php echo epoin_h($sec['icon']); ?>"></i> <?php echo $sec['title']; ?></h3>
            <p><?php echo $sec['desc']; ?></p>
          </div>
          <?php if (!empty($sec['note'])): ?>
            <div class="pd-panel-note"><i class="fa-solid fa-circle-info"></i> <?php echo $sec['note']; ?></div>
          <?php endif; ?>

          <div class="pd-cards">
            <?php foreach ($sec['items'] as $it): [$label, $file, $help, $langkah] = array_pad($it, 4, []); ?>
              <div class="pd-card" data-search="<?php echo epoin_h(mb_strtolower(strip_tags($label . ' ' . $help))); ?>">
                <div class="pd-card-top">
                  <span class="pd-card-title"><?php echo $label; ?></span>
                  <a class="pd-card-open" href="<?php echo epoin_h($file); ?>">Buka <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <div class="pd-card-desc"><?php echo $help; ?></div>
                <?php if (!empty($langkah)): ?>
                  <button type="button" class="pd-steps-btn" onclick="pdToggleSteps(this)">
                    <i class="fa-solid fa-chevron-down"></i> Lihat langkah (<?php echo count($langkah); ?>)
                  </button>
                  <div class="pd-steps">
                    <ol>
                      <?php foreach ($langkah as $lk): ?><li><?php echo $lk; ?></li><?php endforeach; ?>
                    </ol>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>

        <div id="pdNoResult">
          <i class="fa-solid fa-file-circle-question"></i>
          Tidak ada modul yang cocok. Coba kata kunci lain.
        </div>
      </div>
    </div>

  </div>
  </section>
</div>

<script>
function pdToggleSteps(btn){
  var box = btn.nextElementSibling;
  var open = box.classList.contains('open');
  box.classList.toggle('open', !open);
  btn.classList.toggle('open', !open);
}
(function(){
  var navItems = Array.prototype.slice.call(document.querySelectorAll('.pd-nav-item'));
  var panels   = Array.prototype.slice.call(document.querySelectorAll('.pd-panel'));
  var search   = document.getElementById('pdSearch');
  var clearBtn = document.getElementById('pdSearchClear');
  var noResult = document.getElementById('pdNoResult');
  var flowHead = document.getElementById('pdFlowToggle');
  var flowBox  = document.getElementById('pdFlow');

  flowHead.addEventListener('click', function(){ flowBox.classList.toggle('open'); });

  function showPanel(id){
    panels.forEach(function(p){ p.classList.toggle('active', p.getAttribute('data-panel') === id); });
    navItems.forEach(function(b){ b.classList.toggle('active', b.getAttribute('data-target') === id); });
  }

  navItems.forEach(function(btn){
    btn.addEventListener('click', function(){
      search.value = '';
      clearBtn.style.display = 'none';
      pdApplySearch('');
      showPanel(btn.getAttribute('data-target'));
    });
  });

  function pdApplySearch(qRaw){
    var q = qRaw.toLowerCase().trim();
    clearBtn.style.display = q ? 'block' : 'none';

    if (q === '') {
      panels.forEach(function(p){ p.querySelectorAll('.pd-card').forEach(function(c){ c.style.display=''; }); });
      var activeBtn = document.querySelector('.pd-nav-item.active') || navItems[0];
      showPanel(activeBtn.getAttribute('data-target'));
      noResult.style.display = 'none';
      return;
    }

    // mode pencarian: tampilkan semua panel, filter kartu, sembunyikan nav highlight
    var totalMatch = 0;
    panels.forEach(function(p){
      var anyMatch = false;
      p.querySelectorAll('.pd-card').forEach(function(c){
        var hay = c.getAttribute('data-search') || '';
        var match = hay.indexOf(q) !== -1;
        c.style.display = match ? '' : 'none';
        if (match) { anyMatch = true; totalMatch++; }
      });
      p.classList.toggle('active', anyMatch);
    });
    navItems.forEach(function(b){ b.classList.remove('active'); });
    noResult.style.display = totalMatch === 0 ? 'block' : 'none';
  }

  var t;
  search.addEventListener('input', function(){
    clearTimeout(t);
    var v = this.value;
    t = setTimeout(function(){ pdApplySearch(v); }, 120);
  });
  clearBtn.addEventListener('click', function(){ search.value=''; pdApplySearch(''); search.focus(); });
})();
</script>

<?php include 'footer.php'; ?>
