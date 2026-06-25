-- ============================================================
--  EPOIN Migration — RBAC Sub-fase 1 (BAGIAN A: ALTER STRUKTUR)
--  Tanggal : 2026-06-25
--  Acuan   : docs/BLUEPRINT_RBAC.md §3.1–§3.2
--  Tujuan  : Tambah kolom metadata pada `permissions` & `roles`
--            agar matrix bisa dikelompokkan per modul & dirender rapi.
--  CATATAN : TIDAK mengaktifkan enforcement. Hanya struktur.
--            `role_permissions` TIDAK diubah (sudah ber-PK komposit).
--  Idempotent: aman dijalankan berulang (cek kolom via INFORMATION_SCHEMA).
-- ============================================================

SET NAMES utf8mb4;

-- MySQL 5.7/8.x tidak mendukung "ADD COLUMN IF NOT EXISTS".
-- Pakai stored procedure untuk cek-lalu-tambah (idempotent).
DROP PROCEDURE IF EXISTS epoin_rbac_alter;

DELIMITER $$
CREATE PROCEDURE epoin_rbac_alter()
BEGIN
    DECLARE db VARCHAR(64);
    SET db = DATABASE();

    -- ---- permissions.perm_group ----
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA=db AND TABLE_NAME='permissions' AND COLUMN_NAME='perm_group') THEN
        ALTER TABLE `permissions`
            ADD COLUMN `perm_group` VARCHAR(40) NOT NULL DEFAULT 'lainnya' AFTER `perm_name`;
    END IF;

    -- ---- permissions.perm_type (menu / aksi) ----
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA=db AND TABLE_NAME='permissions' AND COLUMN_NAME='perm_type') THEN
        ALTER TABLE `permissions`
            ADD COLUMN `perm_type` VARCHAR(10) NOT NULL DEFAULT 'aksi' AFTER `perm_group`;
    END IF;

    -- ---- permissions.sort_order ----
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA=db AND TABLE_NAME='permissions' AND COLUMN_NAME='sort_order') THEN
        ALTER TABLE `permissions`
            ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `perm_type`;
    END IF;

    -- ---- roles.is_system (role bawaan, tidak boleh dihapus) ----
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA=db AND TABLE_NAME='roles' AND COLUMN_NAME='is_system') THEN
        ALTER TABLE `roles`
            ADD COLUMN `is_system` TINYINT(1) NOT NULL DEFAULT 0 AFTER `role_desc`;
    END IF;

    -- ---- roles.sort_order ----
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA=db AND TABLE_NAME='roles' AND COLUMN_NAME='sort_order') THEN
        ALTER TABLE `roles`
            ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `is_system`;
    END IF;

    SELECT 'OK: struktur permissions & roles siap (perm_group, perm_type, sort_order, is_system).' AS hasil;
END $$
DELIMITER ;

CALL epoin_rbac_alter();
DROP PROCEDURE IF EXISTS epoin_rbac_alter;

-- ============================================================
-- [ phpMyAdmin — alternatif manual jika DELIMITER ditolak ]
-- Cek dulu tiap kolom, lalu jalankan ALTER yang belum ada:
--   SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
--     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='permissions'
--       AND COLUMN_NAME IN ('perm_group','perm_type','sort_order');
--   ALTER TABLE `permissions` ADD COLUMN `perm_group` VARCHAR(40) NOT NULL DEFAULT 'lainnya' AFTER `perm_name`;
--   ALTER TABLE `permissions` ADD COLUMN `perm_type`  VARCHAR(10) NOT NULL DEFAULT 'aksi'    AFTER `perm_group`;
--   ALTER TABLE `permissions` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `perm_type`;
--   ALTER TABLE `roles` ADD COLUMN `is_system`  TINYINT(1) NOT NULL DEFAULT 0 AFTER `role_desc`;
--   ALTER TABLE `roles` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `is_system`;
-- ============================================================
