-- ============================================================
--  EPOIN Migration — RBAC M-1 (cache-version stamp)
--  Tanggal : 2026-06-27
--  Acuan   : docs/BLUEPRINT_RBAC.md §5.2 ; docs/AUDIT_RBAC.md temuan M-1
--  Tujuan  : Tabel key-value `app_meta` untuk menyimpan `rbac_version`.
--            Versi di-bump tiap kali role/permission/role-membership berubah,
--            sehingga cache perms di session bisa di-invalidasi (reload otomatis
--            di request berikutnya tanpa user harus logout).
--  CATATAN : TIDAK mengaktifkan enforcement. Hanya invalidasi cache.
--  Idempotent: aman dijalankan berulang.
-- ============================================================

SET NAMES utf8mb4;

-- Tabel meta global (key-value). Bisa dipakai ulang utk flag global lain di masa depan.
CREATE TABLE IF NOT EXISTS `app_meta` (
  `meta_key`   VARCHAR(64)  NOT NULL,
  `meta_value` VARCHAR(255) NOT NULL DEFAULT '',
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`meta_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed baris rbac_version = 1 (JANGAN reset bila sudah ada → ON DUPLICATE no-op).
INSERT INTO `app_meta` (`meta_key`, `meta_value`) VALUES ('rbac_version', '1')
  ON DUPLICATE KEY UPDATE `meta_key` = `meta_key`;

SELECT meta_key, meta_value FROM app_meta WHERE meta_key='rbac_version';
