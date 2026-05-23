-- E-Tugas Phase 1A: foundation tables (no FK — indexes only, shared-hosting friendly)
-- Import into epoin_local / production DB after backup.
-- Charset: utf8mb4

SET NAMES utf8mb4;
SET time_zone = '+07:00';

CREATE TABLE IF NOT EXISTS `etugas` (
  `etugas_id` INT NOT NULL AUTO_INCREMENT,
  `ta_id` INT NOT NULL,
  `kelas_id` INT NOT NULL,
  `mapel_id` INT NOT NULL,
  `guru_user_id` INT NULL DEFAULT NULL,
  `judul` VARCHAR(200) NOT NULL,
  `instruksi` TEXT NULL,
  `deadline_at` DATETIME NULL DEFAULT NULL,
  `allow_text` TINYINT(1) NOT NULL DEFAULT 1,
  `allow_link` TINYINT(1) NOT NULL DEFAULT 1,
  `izinkan_terlambat` TINYINT(1) NOT NULL DEFAULT 1,
  `status` ENUM('draft','aktif','ditutup','arsip') NOT NULL DEFAULT 'aktif',
  `created_by` INT NULL DEFAULT NULL,
  `updated_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`etugas_id`),
  KEY `idx_etugas_ta_id` (`ta_id`),
  KEY `idx_etugas_kelas_id` (`kelas_id`),
  KEY `idx_etugas_mapel_id` (`mapel_id`),
  KEY `idx_etugas_guru_user_id` (`guru_user_id`),
  KEY `idx_etugas_status` (`status`),
  KEY `idx_etugas_deadline_at` (`deadline_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `etugas_pengumpulan` (
  `pengumpulan_id` INT NOT NULL AUTO_INCREMENT,
  `etugas_id` INT NOT NULL,
  `siswa_id` INT NOT NULL,
  `jawaban_teks` MEDIUMTEXT NULL,
  `link_url` VARCHAR(1000) NULL DEFAULT NULL,
  `link_jenis` ENUM('drive','youtube','canva','docs','lainnya') NOT NULL DEFAULT 'lainnya',
  `catatan_siswa` TEXT NULL,
  `status` ENUM('terkirim','ditinjau','revisi','selesai') NOT NULL DEFAULT 'terkirim',
  `is_terlambat` TINYINT(1) NOT NULL DEFAULT 0,
  `nilai` DECIMAL(5,2) NULL DEFAULT NULL,
  `catatan_guru` TEXT NULL,
  `reviewed_by` INT NULL DEFAULT NULL,
  `reviewed_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pengumpulan_id`),
  UNIQUE KEY `uq_etugas_siswa` (`etugas_id`, `siswa_id`),
  KEY `idx_ep_etugas_id` (`etugas_id`),
  KEY `idx_ep_siswa_id` (`siswa_id`),
  KEY `idx_ep_status` (`status`),
  KEY `idx_ep_is_terlambat` (`is_terlambat`),
  KEY `idx_ep_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
