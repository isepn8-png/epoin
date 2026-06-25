-- ============================================================
--  EPOIN Migration — RBAC Sub-fase 1 (BAGIAN B: SEED DATA)
--  Tanggal : 2026-06-25
--  Acuan   : docs/BLUEPRINT_RBAC.md §3.4, §3.5, §4
--  Prasyarat: jalankan dulu 2026-06-25-001-rbac-alter.sql
--  Tujuan  : Seed 13 role + 67 permission (60 baru) + matrix default.
--  PENTING : TIDAK mengaktifkan enforcement. Helper can() belum dipakai
--            untuk gating. Akses tetap seperti sekarang.
--  Idempotent:
--    - roles & permissions : INSERT ... ON DUPLICATE KEY UPDATE (key UNIQUE)
--    - role_permissions    : INSERT IGNORE (PK komposit) — hanya menambah,
--                            tidak pernah menghapus mapping existing.
--  Keputusan Bos:
--    - wali_kelas : DATA-DRIVEN dari tabel kelas_wali (BUKAN role) -> tidak diseed
--    - sekretaris & pembina_ekskul : DIPISAH jadi 2 role
--    - petugas_absensi : tidak ada tabel sendiri -> jadi role biasa
--    - staf_tu : REUSE role "tas" (label diganti "Staf TU")
--    - siswa  : pseudo-role portal (level=siswa), DIKELOLA portal siswa,
--               BUKAN via user_roles. Diseed sbg penanda is_system, tanpa
--               permission, tanpa anggota.
-- ============================================================

SET NAMES utf8mb4;

-- ============================================================
--  1) SEED ROLES (13) — reuse 5 existing + 7 baru + siswa(penanda)
--     ON DUPLICATE KEY UPDATE -> idempotent & konvergen.
--     role_id dibiarkan AUTO_INCREMENT (matching by role_key UNIQUE).
-- ============================================================
INSERT INTO `roles` (`role_key`,`role_name`,`role_desc`,`is_system`,`sort_order`) VALUES
  ('superadmin',        'Super Admin',       'Akses penuh ke seluruh sistem (wildcard). Tidak dapat dihapus.',                         1, 10),
  ('administrator',     'Administrator',     'Pengelola utama: master data, pengguna, poin, absensi, penilaian.',                      1, 20),
  ('kepala_sekolah',    'Kepala Sekolah',    'Pimpinan sekolah: akses lihat & monitoring menyeluruh (read-only).',                     0, 30),
  ('wakasek_kurikulum', 'Wakasek Kurikulum', 'Kelola mapel, penugasan guru, tahun ajaran, kelas/jurusan, penilaian & ujian.',          0, 40),
  ('wakasek_kesiswaan', 'Wakasek Kesiswaan', 'Kelola poin/prestasi/pelanggaran, absensi & pembinaan kesiswaan.',                       0, 50),
  ('tas',               'Staf TU',           'Tenaga Administrasi: kelola data siswa, kelas, jurusan, kalender & laporan.',            0, 60),
  ('guru',              'Guru',              'Guru mapel: input poin, absensi mapel sendiri, penilaian & tugas.',                      0, 70),
  ('guru_bk',           'Guru BK',           'Bimbingan Konseling: lihat poin & riwayat siswa, input pembinaan.',                      0, 80),
  ('guru_piket',        'Guru Piket',        'Piket harian: input absensi harian & pelanggaran, monitoring.',                          0, 90),
  ('petugas_absensi',   'Petugas Absensi',   'Input & rekap absensi harian, monitoring absensi.',                                      0, 100),
  ('pembina_ekskul',    'Pembina Ekskul',    'Pembina ekstrakurikuler: input prestasi & absensi kegiatan.',                            0, 110),
  ('sekretaris',        'Sekretaris Kelas',  'Sekretaris kelas: input absensi harian & monitoring kelas.',                             0, 120),
  ('siswa',             'Siswa',             'Pseudo-role portal siswa (read-only data sendiri). Dikelola via portal siswa (level=siswa), BUKAN user_roles.', 1, 200)
ON DUPLICATE KEY UPDATE
  `role_name`=VALUES(`role_name`),
  `role_desc`=VALUES(`role_desc`),
  `is_system`=VALUES(`is_system`),
  `sort_order`=VALUES(`sort_order`);

-- ============================================================
--  2) SEED PERMISSIONS (67 = 60 baru + 7 existing)
--     7 perm existing (attendance.*, monitoring.view) HANYA di-update
--     metadata-nya (perm_name tidak ditimpa). Perm baru di-insert.
-- ============================================================
INSERT INTO `permissions` (`perm_key`,`perm_name`,`perm_group`,`perm_type`,`sort_order`) VALUES
  -- ---- UMUM ----
  ('dashboard.view',                'Lihat Dashboard',                 'umum',      'menu', 10),
  ('sekolah.view',                  'Lihat data Sekolah',              'umum',      'menu', 20),
  ('sekolah.edit',                  'Ubah data Sekolah',               'umum',      'aksi', 21),
  ('account.password.self',         'Ganti password sendiri',          'umum',      'aksi', 30),
  -- ---- MASTER DATA ----
  ('master.siswa.view',             'Lihat Siswa',                     'master',    'menu', 100),
  ('master.siswa.create',           'Tambah Siswa',                    'master',    'aksi', 101),
  ('master.siswa.edit',             'Edit Siswa',                      'master',    'aksi', 102),
  ('master.siswa.delete',           'Hapus Siswa',                     'master',    'aksi', 103),
  ('master.siswa.import',           'Import Siswa',                    'master',    'aksi', 104),
  ('master.siswa.export',           'Export Siswa',                    'master',    'aksi', 105),
  ('master.kelas.view',             'Lihat Kelas',                     'master',    'menu', 110),
  ('master.kelas.manage',           'Kelola Kelas',                    'master',    'aksi', 111),
  ('master.kelas.assign_siswa',     'Atur Siswa per Kelas',            'master',    'aksi', 112),
  ('master.jurusan.view',           'Lihat Jurusan',                   'master',    'menu', 120),
  ('master.jurusan.manage',         'Kelola Jurusan',                  'master',    'aksi', 121),
  ('master.mapel.view',             'Lihat Mapel',                     'master',    'menu', 130),
  ('master.mapel.manage',           'Kelola Mapel',                    'master',    'aksi', 131),
  ('master.pengampu.view',          'Lihat Penugasan Guru Mapel',      'master',    'menu', 140),
  ('master.pengampu.manage',        'Kelola Penugasan Guru Mapel',     'master',    'aksi', 141),
  ('master.ta.view',                'Lihat Tahun Ajaran',              'master',    'menu', 150),
  ('master.ta.manage',              'Kelola Tahun Ajaran',             'master',    'aksi', 151),
  ('master.kalender.view',          'Lihat Kalender Akademik',         'master',    'menu', 160),
  ('master.kalender.manage',        'Kelola Kalender Akademik',        'master',    'aksi', 161),
  ('master.user.view',              'Lihat Pengguna',                  'master',    'menu', 170),
  ('master.user.create',            'Tambah Pengguna',                 'master',    'aksi', 171),
  ('master.user.edit',              'Edit Pengguna',                   'master',    'aksi', 172),
  ('master.user.delete',            'Hapus Pengguna',                  'master',    'aksi', 173),
  ('master.user.suspend',           'Suspend/Aktifkan Pengguna',       'master',    'aksi', 174),
  ('master.user.role_manage',       'Kelola Role & Hak Akses',         'master',    'aksi', 175),
  -- ---- KATEGORI POIN ----
  ('poin.jenis_prestasi.view',      'Lihat Jenis Prestasi',            'kategori',  'menu', 200),
  ('poin.jenis_prestasi.manage',    'Kelola Jenis Prestasi',           'kategori',  'aksi', 201),
  ('poin.jenis_pelanggaran.view',   'Lihat Jenis Pelanggaran',         'kategori',  'menu', 210),
  ('poin.jenis_pelanggaran.manage', 'Kelola Jenis Pelanggaran',        'kategori',  'aksi', 211),
  -- ---- KELOLA POIN ----
  ('poin.prestasi.input',           'Input Prestasi',                  'poin',      'menu', 300),
  ('poin.prestasi.edit',            'Edit Prestasi',                   'poin',      'aksi', 301),
  ('poin.prestasi.delete',          'Hapus Prestasi',                  'poin',      'aksi', 302),
  ('poin.pelanggaran.input',        'Input Pelanggaran',               'poin',      'menu', 310),
  ('poin.pelanggaran.edit',         'Edit Pelanggaran',                'poin',      'aksi', 311),
  ('poin.pelanggaran.delete',       'Hapus Pelanggaran',               'poin',      'aksi', 312),
  ('poin.kolektif.input',           'Input Poin Kolektif',             'poin',      'menu', 320),
  ('poin.laporan.view',             'Lihat Laporan Poin Siswa',        'poin',      'menu', 330),
  ('poin.laporan.export',           'Export Laporan Poin',             'poin',      'aksi', 331),
  -- ---- ABSENSI (7 existing + 3 baru) ----
  ('attendance.harian.input',       'Input absensi harian',            'absensi',   'menu', 400),
  ('attendance.harian.view',        'Lihat absensi harian',            'absensi',   'menu', 401),
  ('monitoring.view',               'Lihat dashboard monitoring',      'absensi',   'menu', 402),
  ('attendance.sinkron',            'Sinkron Absensi',                 'absensi',   'menu', 410),
  ('attendance.laporan.view',       'Lihat Laporan Absensi',           'absensi',   'menu', 420),
  ('attendance.laporan.export',     'Export Laporan Absensi',          'absensi',   'aksi', 421),
  ('attendance.mapel.own',          'Kelola sesi mapel milik sendiri', 'absensi',   'aksi', 430),
  ('attendance.view_all',           'Lihat semua sesi absensi',        'absensi',   'aksi', 431),
  ('attendance.final_any',          'Finalisasi sesi siapa pun',       'absensi',   'aksi', 432),
  ('attendance.delete_any',         'Hapus sesi siapa pun',            'absensi',   'aksi', 433),
  -- ---- PENILAIAN ----
  ('nilai.tp.view',                 'Lihat Tujuan Pembelajaran',       'penilaian', 'menu', 500),
  ('nilai.tp.manage',               'Kelola Tujuan Pembelajaran',      'penilaian', 'aksi', 501),
  ('nilai.harian.input',            'Input Nilai Harian',              'penilaian', 'menu', 510),
  ('nilai.sts.input',               'Input Nilai STS',                 'penilaian', 'menu', 520),
  ('nilai.tersimpan.view',          'Lihat Nilai Tersimpan',           'penilaian', 'menu', 530),
  ('nilai.status.view',             'Lihat Status Penilaian',          'penilaian', 'menu', 540),
  ('nilai.leger.view',              'Lihat Leger Rapor STS',           'penilaian', 'menu', 550),
  ('nilai.rapor.cetak',             'Cetak Rapor STS',                 'penilaian', 'aksi', 560),
  ('nilai.erapor.export',           'Export e-Rapor',                  'penilaian', 'aksi', 570),
  -- ---- UJIAN & CBT ----
  ('ujian.gform.view',              'Lihat Ujian GForm',               'ujian',     'menu', 600),
  ('ujian.gform.manage',            'Kelola Ujian GForm',              'ujian',     'aksi', 601),
  ('etugas.manage',                 'Kelola Tugas',                    'ujian',     'menu', 610),
  ('etugas.review',                 'Review Pengumpulan Tugas',        'ujian',     'menu', 620),
  ('etugas.rekap.view',             'Lihat Rekap Tugas',               'ujian',     'menu', 630),
  -- ---- SISTEM ----
  ('setting.petugas_absensi.manage','Kelola Petugas Absensi',          'sistem',    'aksi', 700)
ON DUPLICATE KEY UPDATE
  `perm_group`=VALUES(`perm_group`),
  `perm_type`=VALUES(`perm_type`),
  `sort_order`=VALUES(`sort_order`);
  -- perm_name SENGAJA tidak ditimpa agar label perm existing tetap.

-- ============================================================
--  3) SEED MATRIX DEFAULT (role_permissions)
--     INSERT IGNORE: hanya menambah (PK komposit role_id+perm_id),
--     tidak menghapus mapping existing. Hasil = UNION.
-- ============================================================

-- 3.a) SUPERADMIN = WILDCARD (semua permission).
--      Diberi SEMUA perm di level data sbg pengaman anti-lockout,
--      MESKIPUN nanti epoin_can() juga akan short-circuit superadmin.
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
SELECT r.role_id, p.perm_id
FROM `roles` r CROSS JOIN `permissions` p
WHERE r.role_key = 'superadmin';

-- 3.b) ADMINISTRATOR = semua KECUALI 'master.user.role_manage'
--      (kelola role hanya untuk superadmin secara default).
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
SELECT r.role_id, p.perm_id
FROM `roles` r CROSS JOIN `permissions` p
WHERE r.role_key = 'administrator'
  AND p.perm_key <> 'master.user.role_manage';

-- 3.c) KEPALA SEKOLAH = lihat & monitoring menyeluruh (read-only)
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
SELECT r.role_id, p.perm_id FROM `roles` r JOIN `permissions` p
WHERE r.role_key='kepala_sekolah' AND p.perm_key IN (
  'dashboard.view','sekolah.view','account.password.self',
  'master.siswa.view',
  'poin.laporan.view','poin.laporan.export',
  'attendance.view_all','attendance.harian.view','monitoring.view','attendance.laporan.view',
  'nilai.tersimpan.view','nilai.status.view','nilai.leger.view','nilai.rapor.cetak'
);

-- 3.d) WAKASEK KURIKULUM = mapel, pengampu, TA, kelas/jurusan, kalender, penilaian, ujian
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
SELECT r.role_id, p.perm_id FROM `roles` r JOIN `permissions` p
WHERE r.role_key='wakasek_kurikulum' AND p.perm_key IN (
  'dashboard.view','account.password.self',
  'master.siswa.view',
  'master.kelas.view','master.kelas.manage','master.kelas.assign_siswa',
  'master.jurusan.view','master.jurusan.manage',
  'master.mapel.view','master.mapel.manage',
  'master.pengampu.view','master.pengampu.manage',
  'master.ta.view','master.ta.manage',
  'master.kalender.view','master.kalender.manage',
  'attendance.view_all','monitoring.view',
  'nilai.tp.view','nilai.tp.manage','nilai.harian.input','nilai.sts.input',
  'nilai.tersimpan.view','nilai.status.view','nilai.leger.view','nilai.rapor.cetak','nilai.erapor.export',
  'ujian.gform.view','ujian.gform.manage','etugas.manage','etugas.review','etugas.rekap.view'
);

-- 3.e) WAKASEK KESISWAAN = poin/prestasi/pelanggaran, absensi, pembinaan
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
SELECT r.role_id, p.perm_id FROM `roles` r JOIN `permissions` p
WHERE r.role_key='wakasek_kesiswaan' AND p.perm_key IN (
  'dashboard.view','account.password.self',
  'master.siswa.view',
  'poin.jenis_prestasi.view','poin.jenis_prestasi.manage',
  'poin.jenis_pelanggaran.view','poin.jenis_pelanggaran.manage',
  'poin.prestasi.input','poin.prestasi.edit','poin.prestasi.delete',
  'poin.pelanggaran.input','poin.pelanggaran.edit','poin.pelanggaran.delete',
  'poin.kolektif.input','poin.laporan.view','poin.laporan.export',
  'attendance.view_all','attendance.final_any','attendance.harian.input','attendance.harian.view',
  'attendance.sinkron','monitoring.view','attendance.laporan.view',
  'setting.petugas_absensi.manage'
);

-- 3.f) TAS / STAF TU = data siswa, kelas, jurusan, kalender, laporan
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
SELECT r.role_id, p.perm_id FROM `roles` r JOIN `permissions` p
WHERE r.role_key='tas' AND p.perm_key IN (
  'dashboard.view','account.password.self',
  'master.siswa.view','master.siswa.create','master.siswa.edit','master.siswa.import','master.siswa.export',
  'master.kelas.view','master.kelas.manage','master.kelas.assign_siswa',
  'master.jurusan.view','master.jurusan.manage',
  'master.kalender.view','master.kalender.manage',
  'poin.laporan.view','poin.laporan.export',
  'attendance.harian.input','attendance.harian.view','monitoring.view','attendance.laporan.view'
);

-- 3.g) GURU = poin (input), absensi mapel sendiri, penilaian, tugas
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
SELECT r.role_id, p.perm_id FROM `roles` r JOIN `permissions` p
WHERE r.role_key='guru' AND p.perm_key IN (
  'dashboard.view','account.password.self',
  'poin.prestasi.input','poin.pelanggaran.input','poin.kolektif.input','poin.laporan.view',
  'attendance.mapel.own','attendance.laporan.view',
  'nilai.tp.view','nilai.tp.manage','nilai.harian.input','nilai.sts.input',
  'nilai.tersimpan.view','nilai.status.view','nilai.leger.view','nilai.rapor.cetak','nilai.erapor.export',
  'ujian.gform.view','ujian.gform.manage','etugas.manage','etugas.review','etugas.rekap.view'
);

-- 3.h) GURU BK = lihat siswa & poin, input pembinaan (prestasi/pelanggaran), absensi view
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
SELECT r.role_id, p.perm_id FROM `roles` r JOIN `permissions` p
WHERE r.role_key='guru_bk' AND p.perm_key IN (
  'dashboard.view','account.password.self',
  'master.siswa.view',
  'poin.prestasi.input','poin.pelanggaran.input','poin.laporan.view',
  'attendance.harian.view','monitoring.view','attendance.laporan.view'
);

-- 3.i) GURU PIKET = absensi harian (input/lihat), pelanggaran, monitoring
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
SELECT r.role_id, p.perm_id FROM `roles` r JOIN `permissions` p
WHERE r.role_key='guru_piket' AND p.perm_key IN (
  'dashboard.view','account.password.self',
  'poin.pelanggaran.input',
  'attendance.harian.input','attendance.harian.view','monitoring.view'
);

-- 3.j) PETUGAS ABSENSI = input/rekap absensi harian, monitoring
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
SELECT r.role_id, p.perm_id FROM `roles` r JOIN `permissions` p
WHERE r.role_key='petugas_absensi' AND p.perm_key IN (
  'dashboard.view','account.password.self',
  'attendance.harian.input','attendance.harian.view','monitoring.view'
);

-- 3.k) PEMBINA EKSKUL = input prestasi ekskul + absensi kegiatan
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
SELECT r.role_id, p.perm_id FROM `roles` r JOIN `permissions` p
WHERE r.role_key='pembina_ekskul' AND p.perm_key IN (
  'dashboard.view','account.password.self',
  'poin.prestasi.input',
  'attendance.harian.input','attendance.harian.view','monitoring.view','attendance.laporan.view'
);

-- 3.l) SEKRETARIS KELAS = absensi harian + monitoring (mempertahankan perm existing)
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
SELECT r.role_id, p.perm_id FROM `roles` r JOIN `permissions` p
WHERE r.role_key='sekretaris' AND p.perm_key IN (
  'attendance.harian.input','attendance.harian.view','monitoring.view'
);

-- 3.m) SISWA = TANPA permission (dikelola portal siswa). Tidak ada INSERT.

-- ============================================================
--  VERIFIKASI (jalankan manual setelah seed)
-- ============================================================
-- SELECT COUNT(*) AS jml_role  FROM roles;         -- harus 13
-- SELECT COUNT(*) AS jml_perm  FROM permissions;   -- harus 67
-- SELECT r.role_key, COUNT(rp.perm_id) AS n_perm
--   FROM roles r LEFT JOIN role_permissions rp ON rp.role_id=r.role_id
--   GROUP BY r.role_id ORDER BY r.sort_order;
--   -- superadmin harus = total perm (67); siswa harus = 0.
-- ============================================================
--  SELESAI — enforcement BELUM aktif. Akses tetap seperti sekarang.
-- ============================================================
