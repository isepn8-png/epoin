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
-- ============================================================
--  SELESAI
-- ============================================================
