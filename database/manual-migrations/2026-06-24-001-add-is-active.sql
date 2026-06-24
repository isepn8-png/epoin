-- ============================================================
--  EPOIN Migration — Kolom is_active di tabel user
--  Tanggal  : 2026-06-24
--  Fitur    : Suspend / Aktifkan akun pengguna
--  DEFAULT 1 = aktif, 0 = suspended (tidak bisa login)
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
        SELECT 'SKIP: kolom is_active sudah ada, tidak perlu ALTER.' AS hasil;
    END IF;
END $$
DELIMITER ;

CALL epoin_add_is_active();
DROP PROCEDURE IF EXISTS epoin_add_is_active;

-- [ phpMyAdmin — alternatif manual jika DELIMITER error ]
-- Cek dulu:
--   SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
--   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user' AND COLUMN_NAME='is_active';
-- Jika hasilnya kosong → jalankan:
--   ALTER TABLE `user` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `status_login`;
