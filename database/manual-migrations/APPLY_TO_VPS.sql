-- ============================================================
--  EPOIN — Konsolidasi Migration untuk VPS
--  Dibuat  : 2026-06-22
--  Berlaku untuk: MySQL 5.7+ / MariaDB 10.3+ / MySQL 8.x
-- ============================================================
--  CARA MENJALANKAN:
--
--  [ Opsi A — Terminal (SSH aaPanel/cPanel) ]
--    mysql -u <user> -p <nama_database> < APPLY_TO_VPS.sql
--  Contoh:
--    mysql -u epoin_user -p epoin_db < APPLY_TO_VPS.sql
--
--  [ Opsi B — phpMyAdmin / aaPanel SQL Runner ]
--    1. Login phpMyAdmin / aaPanel → Database
--    2. Pilih database epoin
--    3. Klik tab "SQL"
--    4. Paste seluruh isi file ini → klik "Go" / "Jalankan"
--    CATATAN: Jalankan per blok (pisahkan di baris "-- ---")
--             jika phpMyAdmin menolak DELIMITER
--
--  [ Pra-kondisi wajib ]
--    - Backup database dulu sebelum menjalankan!
--    - Pastikan nama database sudah terpilih / USE <database>
--      sudah dijalankan.
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';

-- ============================================================
--  BLOK 1 — Kolom hp_ortu di tabel siswa
--  Fitur   : Simpan nomor WA orang tua untuk notifikasi
--  Root cause "DB error": kolom ini belum ada di VPS
-- ============================================================
--
--  MySQL 8.x / 5.7 TIDAK mendukung sintaks:
--    ALTER TABLE ... ADD COLUMN IF NOT EXISTS ...
--  Gunakan stored procedure berikut untuk aman:

DROP PROCEDURE IF EXISTS epoin_add_hp_ortu;

DELIMITER $$
CREATE PROCEDURE epoin_add_hp_ortu()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'siswa'
          AND COLUMN_NAME  = 'hp_ortu'
        LIMIT 1
    ) THEN
        ALTER TABLE `siswa`
            ADD COLUMN `hp_ortu` VARCHAR(20) NULL DEFAULT NULL
            AFTER `siswa_status`;
        SELECT 'OK: kolom hp_ortu ditambahkan ke tabel siswa.' AS hasil;
    ELSE
        SELECT 'SKIP: kolom hp_ortu sudah ada, tidak perlu ALTER.' AS hasil;
    END IF;
END $$
DELIMITER ;

CALL epoin_add_hp_ortu();
DROP PROCEDURE IF EXISTS epoin_add_hp_ortu;

-- [ phpMyAdmin — alternatif manual jika DELIMITER error ]
-- Cek dulu: SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
--   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='siswa' AND COLUMN_NAME='hp_ortu';
-- Jika hasilnya kosong → jalankan:
--   ALTER TABLE `siswa` ADD COLUMN `hp_ortu` VARCHAR(20) NULL DEFAULT NULL AFTER `siswa_status`;

-- ============================================================
--  BLOK 2 — Tabel sp_log
--  Fitur  : Log penerbitan SP per siswa (SP1–SP4)
--  Catatan: Tabel ini JUGA dibuat otomatis oleh kode PHP
--           (epoin_sp_ensure_schema) saat fitur SP pertama kali
--           dipakai. Blok ini hanya mempercepat kesiapan.
-- ============================================================

CREATE TABLE IF NOT EXISTS `sp_log` (
  `id`               INT          NOT NULL AUTO_INCREMENT,
  `siswa_id`         INT          NOT NULL,
  `sp_level`         ENUM('SP1','SP2','SP3','SP4') NOT NULL,
  `running_no`       INT          NOT NULL,
  `nomor`            VARCHAR(64)  NOT NULL,
  `alasan`           TEXT         NULL,
  `signer_user_id`   INT          NULL DEFAULT NULL,
  `signer_posisi_key` ENUM('kepala','wakasek_kesiswaan','guru_bp') NULL DEFAULT NULL,
  `signer_nama`      VARCHAR(120) NULL DEFAULT NULL,
  `signer_jabatan`   VARCHAR(120) NULL DEFAULT NULL,
  `tanggal`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_year`   (`tanggal`),
  KEY `idx_siswa`  (`siswa_id`, `sp_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  BLOK 3 — Tabel etugas & etugas_pengumpulan
--  Fitur  : E-Tugas Phase 1A (penugasan online guru → siswa)
--  File asal: database/manual-migrations/
--             2026-05-17-001-create-etugas-tables.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS `etugas` (
  `etugas_id`         INT          NOT NULL AUTO_INCREMENT,
  `ta_id`             INT          NOT NULL,
  `kelas_id`          INT          NOT NULL,
  `mapel_id`          INT          NOT NULL,
  `guru_user_id`      INT          NULL DEFAULT NULL,
  `judul`             VARCHAR(200) NOT NULL,
  `instruksi`         TEXT         NULL,
  `deadline_at`       DATETIME     NULL DEFAULT NULL,
  `allow_text`        TINYINT(1)   NOT NULL DEFAULT 1,
  `allow_link`        TINYINT(1)   NOT NULL DEFAULT 1,
  `izinkan_terlambat` TINYINT(1)   NOT NULL DEFAULT 1,
  `status`            ENUM('draft','aktif','ditutup','arsip') NOT NULL DEFAULT 'aktif',
  `created_by`        INT          NULL DEFAULT NULL,
  `updated_by`        INT          NULL DEFAULT NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`etugas_id`),
  KEY `idx_etugas_ta_id`       (`ta_id`),
  KEY `idx_etugas_kelas_id`    (`kelas_id`),
  KEY `idx_etugas_mapel_id`    (`mapel_id`),
  KEY `idx_etugas_guru_user_id`(`guru_user_id`),
  KEY `idx_etugas_status`      (`status`),
  KEY `idx_etugas_deadline_at` (`deadline_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `etugas_pengumpulan` (
  `pengumpulan_id` INT          NOT NULL AUTO_INCREMENT,
  `etugas_id`      INT          NOT NULL,
  `siswa_id`       INT          NOT NULL,
  `jawaban_teks`   MEDIUMTEXT   NULL,
  `link_url`       VARCHAR(1000) NULL DEFAULT NULL,
  `link_jenis`     ENUM('drive','youtube','canva','docs','lainnya') NOT NULL DEFAULT 'lainnya',
  `catatan_siswa`  TEXT         NULL,
  `status`         ENUM('terkirim','ditinjau','revisi','selesai') NOT NULL DEFAULT 'terkirim',
  `is_terlambat`   TINYINT(1)   NOT NULL DEFAULT 0,
  `nilai`          DECIMAL(5,2) NULL DEFAULT NULL,
  `catatan_guru`   TEXT         NULL,
  `reviewed_by`    INT          NULL DEFAULT NULL,
  `reviewed_at`    DATETIME     NULL DEFAULT NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pengumpulan_id`),
  UNIQUE KEY `uq_etugas_siswa`    (`etugas_id`, `siswa_id`),
  KEY `idx_ep_etugas_id`          (`etugas_id`),
  KEY `idx_ep_siswa_id`           (`siswa_id`),
  KEY `idx_ep_status`             (`status`),
  KEY `idx_ep_is_terlambat`       (`is_terlambat`),
  KEY `idx_ep_created_at`         (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  BLOK 4 — Kolom is_active di tabel user
--  Fitur   : Suspend / Aktifkan akun pengguna
--  Source  : database/manual-migrations/2026-06-24-001-add-is-active.sql
-- ============================================================

DROP PROCEDURE IF EXISTS epoin_add_is_active;

DELIMITER $$
CREATE PROCEDURE epoin_add_is_active()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'user'
          AND COLUMN_NAME  = 'is_active'
        LIMIT 1
    ) THEN
        ALTER TABLE `user`
            ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1
            AFTER `status_login`;
        SELECT 'OK: kolom is_active ditambahkan ke tabel user.' AS hasil;
    ELSE
        SELECT 'SKIP: kolom is_active sudah ada.' AS hasil;
    END IF;
END $$
DELIMITER ;

CALL epoin_add_is_active();
DROP PROCEDURE IF EXISTS epoin_add_is_active;

-- ============================================================
--  BLOK 5 — RBAC: ALTER struktur permissions & roles
--  Fitur   : Metadata matrix (grup/tipe/urutan/sistem)
--  Source  : database/manual-migrations/2026-06-25-001-rbac-alter.sql
--  CATATAN : TIDAK mengaktifkan enforcement. Hanya struktur.
-- ============================================================

DROP PROCEDURE IF EXISTS epoin_rbac_alter;

DELIMITER $$
CREATE PROCEDURE epoin_rbac_alter()
BEGIN
    DECLARE db VARCHAR(64);
    SET db = DATABASE();
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA=db AND TABLE_NAME='permissions' AND COLUMN_NAME='perm_group') THEN
        ALTER TABLE `permissions` ADD COLUMN `perm_group` VARCHAR(40) NOT NULL DEFAULT 'lainnya' AFTER `perm_name`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA=db AND TABLE_NAME='permissions' AND COLUMN_NAME='perm_type') THEN
        ALTER TABLE `permissions` ADD COLUMN `perm_type` VARCHAR(10) NOT NULL DEFAULT 'aksi' AFTER `perm_group`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA=db AND TABLE_NAME='permissions' AND COLUMN_NAME='sort_order') THEN
        ALTER TABLE `permissions` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `perm_type`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA=db AND TABLE_NAME='roles' AND COLUMN_NAME='is_system') THEN
        ALTER TABLE `roles` ADD COLUMN `is_system` TINYINT(1) NOT NULL DEFAULT 0 AFTER `role_desc`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA=db AND TABLE_NAME='roles' AND COLUMN_NAME='sort_order') THEN
        ALTER TABLE `roles` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `is_system`;
    END IF;
    SELECT 'OK: struktur RBAC siap.' AS hasil;
END $$
DELIMITER ;

CALL epoin_rbac_alter();
DROP PROCEDURE IF EXISTS epoin_rbac_alter;

-- ============================================================
--  BLOK 6 — RBAC: SEED 13 role + 67 permission + matrix default
--  Source  : database/manual-migrations/2026-06-25-002-rbac-seed.sql
--  CATATAN : enforcement BELUM aktif. Akses tetap seperti sekarang.
--            Idempotent (ON DUPLICATE KEY / INSERT IGNORE).
-- ============================================================

-- 6.1 Roles
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
  ('siswa',             'Siswa',             'Pseudo-role portal siswa (read-only). Dikelola via portal siswa (level=siswa), BUKAN user_roles.', 1, 200)
ON DUPLICATE KEY UPDATE
  `role_name`=VALUES(`role_name`), `role_desc`=VALUES(`role_desc`),
  `is_system`=VALUES(`is_system`), `sort_order`=VALUES(`sort_order`);

-- 6.2 Permissions (67 = 60 baru + 7 existing). perm_name existing tidak ditimpa.
INSERT INTO `permissions` (`perm_key`,`perm_name`,`perm_group`,`perm_type`,`sort_order`) VALUES
  ('dashboard.view','Lihat Dashboard','umum','menu',10),
  ('sekolah.view','Lihat data Sekolah','umum','menu',20),
  ('sekolah.edit','Ubah data Sekolah','umum','aksi',21),
  ('account.password.self','Ganti password sendiri','umum','aksi',30),
  ('master.siswa.view','Lihat Siswa','master','menu',100),
  ('master.siswa.create','Tambah Siswa','master','aksi',101),
  ('master.siswa.edit','Edit Siswa','master','aksi',102),
  ('master.siswa.delete','Hapus Siswa','master','aksi',103),
  ('master.siswa.import','Import Siswa','master','aksi',104),
  ('master.siswa.export','Export Siswa','master','aksi',105),
  ('master.kelas.view','Lihat Kelas','master','menu',110),
  ('master.kelas.manage','Kelola Kelas','master','aksi',111),
  ('master.kelas.assign_siswa','Atur Siswa per Kelas','master','aksi',112),
  ('master.jurusan.view','Lihat Jurusan','master','menu',120),
  ('master.jurusan.manage','Kelola Jurusan','master','aksi',121),
  ('master.mapel.view','Lihat Mapel','master','menu',130),
  ('master.mapel.manage','Kelola Mapel','master','aksi',131),
  ('master.pengampu.view','Lihat Penugasan Guru Mapel','master','menu',140),
  ('master.pengampu.manage','Kelola Penugasan Guru Mapel','master','aksi',141),
  ('master.ta.view','Lihat Tahun Ajaran','master','menu',150),
  ('master.ta.manage','Kelola Tahun Ajaran','master','aksi',151),
  ('master.kalender.view','Lihat Kalender Akademik','master','menu',160),
  ('master.kalender.manage','Kelola Kalender Akademik','master','aksi',161),
  ('master.user.view','Lihat Pengguna','master','menu',170),
  ('master.user.create','Tambah Pengguna','master','aksi',171),
  ('master.user.edit','Edit Pengguna','master','aksi',172),
  ('master.user.delete','Hapus Pengguna','master','aksi',173),
  ('master.user.suspend','Suspend/Aktifkan Pengguna','master','aksi',174),
  ('master.user.role_manage','Kelola Role & Hak Akses','master','aksi',175),
  ('poin.jenis_prestasi.view','Lihat Jenis Prestasi','kategori','menu',200),
  ('poin.jenis_prestasi.manage','Kelola Jenis Prestasi','kategori','aksi',201),
  ('poin.jenis_pelanggaran.view','Lihat Jenis Pelanggaran','kategori','menu',210),
  ('poin.jenis_pelanggaran.manage','Kelola Jenis Pelanggaran','kategori','aksi',211),
  ('poin.prestasi.input','Input Prestasi','poin','menu',300),
  ('poin.prestasi.edit','Edit Prestasi','poin','aksi',301),
  ('poin.prestasi.delete','Hapus Prestasi','poin','aksi',302),
  ('poin.pelanggaran.input','Input Pelanggaran','poin','menu',310),
  ('poin.pelanggaran.edit','Edit Pelanggaran','poin','aksi',311),
  ('poin.pelanggaran.delete','Hapus Pelanggaran','poin','aksi',312),
  ('poin.kolektif.input','Input Poin Kolektif','poin','menu',320),
  ('poin.laporan.view','Lihat Laporan Poin Siswa','poin','menu',330),
  ('poin.laporan.export','Export Laporan Poin','poin','aksi',331),
  ('attendance.harian.input','Input absensi harian','absensi','menu',400),
  ('attendance.harian.view','Lihat absensi harian','absensi','menu',401),
  ('monitoring.view','Lihat dashboard monitoring','absensi','menu',402),
  ('attendance.sinkron','Sinkron Absensi','absensi','menu',410),
  ('attendance.laporan.view','Lihat Laporan Absensi','absensi','menu',420),
  ('attendance.laporan.export','Export Laporan Absensi','absensi','aksi',421),
  ('attendance.mapel.own','Kelola sesi mapel milik sendiri','absensi','aksi',430),
  ('attendance.view_all','Lihat semua sesi absensi','absensi','aksi',431),
  ('attendance.final_any','Finalisasi sesi siapa pun','absensi','aksi',432),
  ('attendance.delete_any','Hapus sesi siapa pun','absensi','aksi',433),
  ('nilai.tp.view','Lihat Tujuan Pembelajaran','penilaian','menu',500),
  ('nilai.tp.manage','Kelola Tujuan Pembelajaran','penilaian','aksi',501),
  ('nilai.harian.input','Input Nilai Harian','penilaian','menu',510),
  ('nilai.sts.input','Input Nilai STS','penilaian','menu',520),
  ('nilai.tersimpan.view','Lihat Nilai Tersimpan','penilaian','menu',530),
  ('nilai.status.view','Lihat Status Penilaian','penilaian','menu',540),
  ('nilai.leger.view','Lihat Leger Rapor STS','penilaian','menu',550),
  ('nilai.rapor.cetak','Cetak Rapor STS','penilaian','aksi',560),
  ('nilai.erapor.export','Export e-Rapor','penilaian','aksi',570),
  ('ujian.gform.view','Lihat Ujian GForm','ujian','menu',600),
  ('ujian.gform.manage','Kelola Ujian GForm','ujian','aksi',601),
  ('etugas.manage','Kelola Tugas','ujian','menu',610),
  ('etugas.review','Review Pengumpulan Tugas','ujian','menu',620),
  ('etugas.rekap.view','Lihat Rekap Tugas','ujian','menu',630),
  ('setting.petugas_absensi.manage','Kelola Petugas Absensi','sistem','aksi',700)
ON DUPLICATE KEY UPDATE
  `perm_group`=VALUES(`perm_group`), `perm_type`=VALUES(`perm_type`), `sort_order`=VALUES(`sort_order`);

-- 6.3 Matrix default (INSERT IGNORE = hanya menambah, tidak menghapus)
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
  SELECT r.role_id,p.perm_id FROM `roles` r CROSS JOIN `permissions` p WHERE r.role_key='superadmin';
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
  SELECT r.role_id,p.perm_id FROM `roles` r CROSS JOIN `permissions` p
  WHERE r.role_key='administrator' AND p.perm_key<>'master.user.role_manage';
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
  SELECT r.role_id,p.perm_id FROM `roles` r JOIN `permissions` p WHERE r.role_key='kepala_sekolah' AND p.perm_key IN
  ('dashboard.view','sekolah.view','account.password.self','master.siswa.view','poin.laporan.view','poin.laporan.export','attendance.view_all','attendance.harian.view','monitoring.view','attendance.laporan.view','nilai.tersimpan.view','nilai.status.view','nilai.leger.view','nilai.rapor.cetak');
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
  SELECT r.role_id,p.perm_id FROM `roles` r JOIN `permissions` p WHERE r.role_key='wakasek_kurikulum' AND p.perm_key IN
  ('dashboard.view','account.password.self','master.siswa.view','master.kelas.view','master.kelas.manage','master.kelas.assign_siswa','master.jurusan.view','master.jurusan.manage','master.mapel.view','master.mapel.manage','master.pengampu.view','master.pengampu.manage','master.ta.view','master.ta.manage','master.kalender.view','master.kalender.manage','attendance.view_all','monitoring.view','nilai.tp.view','nilai.tp.manage','nilai.harian.input','nilai.sts.input','nilai.tersimpan.view','nilai.status.view','nilai.leger.view','nilai.rapor.cetak','nilai.erapor.export','ujian.gform.view','ujian.gform.manage','etugas.manage','etugas.review','etugas.rekap.view');
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
  SELECT r.role_id,p.perm_id FROM `roles` r JOIN `permissions` p WHERE r.role_key='wakasek_kesiswaan' AND p.perm_key IN
  ('dashboard.view','account.password.self','master.siswa.view','poin.jenis_prestasi.view','poin.jenis_prestasi.manage','poin.jenis_pelanggaran.view','poin.jenis_pelanggaran.manage','poin.prestasi.input','poin.prestasi.edit','poin.prestasi.delete','poin.pelanggaran.input','poin.pelanggaran.edit','poin.pelanggaran.delete','poin.kolektif.input','poin.laporan.view','poin.laporan.export','attendance.view_all','attendance.final_any','attendance.harian.input','attendance.harian.view','attendance.sinkron','monitoring.view','attendance.laporan.view','setting.petugas_absensi.manage');
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
  SELECT r.role_id,p.perm_id FROM `roles` r JOIN `permissions` p WHERE r.role_key='tas' AND p.perm_key IN
  ('dashboard.view','account.password.self','master.siswa.view','master.siswa.create','master.siswa.edit','master.siswa.import','master.siswa.export','master.kelas.view','master.kelas.manage','master.kelas.assign_siswa','master.jurusan.view','master.jurusan.manage','master.kalender.view','master.kalender.manage','poin.laporan.view','poin.laporan.export','attendance.harian.input','attendance.harian.view','monitoring.view','attendance.laporan.view');
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
  SELECT r.role_id,p.perm_id FROM `roles` r JOIN `permissions` p WHERE r.role_key='guru' AND p.perm_key IN
  ('dashboard.view','account.password.self','poin.prestasi.input','poin.pelanggaran.input','poin.kolektif.input','poin.laporan.view','attendance.mapel.own','attendance.laporan.view','nilai.tp.view','nilai.tp.manage','nilai.harian.input','nilai.sts.input','nilai.tersimpan.view','nilai.status.view','nilai.leger.view','nilai.rapor.cetak','nilai.erapor.export','ujian.gform.view','ujian.gform.manage','etugas.manage','etugas.review','etugas.rekap.view');
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
  SELECT r.role_id,p.perm_id FROM `roles` r JOIN `permissions` p WHERE r.role_key='guru_bk' AND p.perm_key IN
  ('dashboard.view','account.password.self','master.siswa.view','poin.prestasi.input','poin.pelanggaran.input','poin.laporan.view','attendance.harian.view','monitoring.view','attendance.laporan.view');
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
  SELECT r.role_id,p.perm_id FROM `roles` r JOIN `permissions` p WHERE r.role_key='guru_piket' AND p.perm_key IN
  ('dashboard.view','account.password.self','poin.pelanggaran.input','attendance.harian.input','attendance.harian.view','monitoring.view');
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
  SELECT r.role_id,p.perm_id FROM `roles` r JOIN `permissions` p WHERE r.role_key='petugas_absensi' AND p.perm_key IN
  ('dashboard.view','account.password.self','attendance.harian.input','attendance.harian.view','monitoring.view');
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
  SELECT r.role_id,p.perm_id FROM `roles` r JOIN `permissions` p WHERE r.role_key='pembina_ekskul' AND p.perm_key IN
  ('dashboard.view','account.password.self','poin.prestasi.input','attendance.harian.input','attendance.harian.view','monitoring.view','attendance.laporan.view');
INSERT IGNORE INTO `role_permissions` (`role_id`,`perm_id`)
  SELECT r.role_id,p.perm_id FROM `roles` r JOIN `permissions` p WHERE r.role_key='sekretaris' AND p.perm_key IN
  ('attendance.harian.input','attendance.harian.view','monitoring.view');
-- siswa: tanpa permission (dikelola portal siswa).

-- ============================================================
--  VERIFIKASI — jalankan setelah semua blok di atas
-- ============================================================
--
-- 1. Pastikan hp_ortu ada di tabel siswa:
--    SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
--    FROM INFORMATION_SCHEMA.COLUMNS
--    WHERE TABLE_SCHEMA = DATABASE()
--      AND TABLE_NAME = 'siswa'
--      AND COLUMN_NAME = 'hp_ortu';
--    → Harus mengembalikan 1 baris.
--
-- 2. Pastikan tabel sp_log ada:
--    SHOW TABLES LIKE 'sp_log';
--    → Harus mengembalikan 'sp_log'.
--
-- 3. Pastikan tabel etugas ada:
--    SHOW TABLES LIKE 'etugas';
--    → Harus mengembalikan 'etugas'.
--
-- 4. Pastikan is_active ada di tabel user:
--    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
--    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user' AND COLUMN_NAME='is_active';
--    → Harus mengembalikan 1 baris.
--
-- 5. Pastikan struktur RBAC ter-ALTER:
--    SHOW COLUMNS FROM permissions;  -- ada perm_group, perm_type, sort_order
--    SHOW COLUMNS FROM roles;        -- ada is_system, sort_order
--
-- 6. Pastikan seed RBAC benar:
--    SELECT (SELECT COUNT(*) FROM roles) AS roles,        -- harus 13
--           (SELECT COUNT(*) FROM permissions) AS perms;  -- harus 67
--    SELECT r.role_key, COUNT(rp.perm_id) n
--      FROM roles r LEFT JOIN role_permissions rp ON rp.role_id=r.role_id
--      GROUP BY r.role_id ORDER BY r.sort_order;
--      -- superadmin = 67 (wildcard), siswa = 0.
--
-- ============================================================
--  SELESAI
-- ============================================================
