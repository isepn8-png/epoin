-- ============================================================
--  EPOIN Migration — Ambang SP Fleksibel
--  Tanggal : 2026-07-10
--  Tujuan  : Membuat ambang Surat Peringatan (SP) dapat dikonfigurasi
--            (skala maksimal + jumlah level) alih-alih hardcode 100/SP1-SP4.
--            Ambang dihitung proporsional oleh aplikasi:
--              band = skala_max / (jumlah_level + 1)
--              ambang SPk = floor(k * band) + 1
--            Default 100/4 → SP1>=21, SP2>=41, SP3>=61, SP4>=81 (identik lama).
--  Idempotent: aman dijalankan berulang.
-- ============================================================

SET NAMES utf8mb4;

-- 1) Pastikan tabel meta global ada (juga dibuat migrasi rbac-version).
CREATE TABLE IF NOT EXISTS `app_meta` (
  `meta_key`   VARCHAR(64)  NOT NULL,
  `meta_value` VARCHAR(255) NOT NULL DEFAULT '',
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`meta_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Seed konfigurasi default. JANGAN timpa nilai yang sudah diset admin
--    (ON DUPLICATE = no-op), sehingga aman dijalankan ulang.
INSERT INTO `app_meta` (`meta_key`, `meta_value`) VALUES
  ('sp_skala_max',    '100'),
  ('sp_jumlah_level', '4')
  ON DUPLICATE KEY UPDATE `meta_key` = `meta_key`;

-- 3) Longgarkan kolom sp_level dari ENUM('SP1'..'SP4') menjadi VARCHAR(8)
--    agar mendukung jumlah level > 4 (mis. SP5). Data SP1-SP4 lama tetap utuh.
--    CATATAN: bila tabel `sp_log` belum pernah dibuat (instalasi baru — dibuat
--    otomatis oleh aplikasi sebagai VARCHAR), baris ALTER di bawah akan error
--    "table doesn't exist" dan boleh DIABAIKAN.
ALTER TABLE `sp_log` MODIFY `sp_level` VARCHAR(8) NOT NULL;

-- Verifikasi
SELECT meta_key, meta_value FROM app_meta WHERE meta_key IN ('sp_skala_max','sp_jumlah_level');
