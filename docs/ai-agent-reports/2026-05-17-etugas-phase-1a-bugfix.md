# E-Tugas Phase 1A — Bugfix Report

**Date:** 2026-05-17  
**Issue:** SQL syntax error on `admin/etugas.php` and `siswa/tugas_saya.php`  
**Scope:** Helper fix only — no Phase 1B

---

## Root cause

`etugas_tables_ready()` used:

```sql
SHOW TABLES LIKE ?
```

with `mysqli_prepare()` / bound parameter. MySQL/MariaDB does **not** accept a placeholder in `SHOW TABLES LIKE ?`, which produced:

> SQL syntax error near '?' at line 1

at `includes/etugas_helpers.php` line 258.

---

## Fix

1. Added `etugas_table_exists($koneksi, $tableName)` using a prepared query on `information_schema.tables`:

```sql
SELECT COUNT(*) AS total
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name = ?
```

2. Added `etugas_tables_status($koneksi)` returning `['ready' => bool, 'missing' => string[]]`.

3. `etugas_tables_ready()` now delegates to `etugas_tables_status()` (checks both `etugas` and `etugas_pengumpulan`).

4. Prepare/execute failures are logged via `error_log()` and return `false` (no fatal on page).

5. Table names are validated with `/^[a-zA-Z0-9_]+$/` before bind.

---

## Files changed

| File | Change |
|------|--------|
| `includes/etugas_helpers.php` | Replaced `SHOW TABLES LIKE ?` with `information_schema` prepared query |

No changes required to `admin/etugas.php` or `siswa/tugas_saya.php` — they already show a warning label when tables are missing.

---

## SQL import required

**No** — application-only fix.

---

## Validation

- `php -l includes/etugas_helpers.php` — OK
- `php -l admin/etugas.php` — OK
- `php -l siswa/tugas_saya.php` — OK
- DB: `SELECT COUNT(*) ... table_name IN ('etugas','etugas_pengumpulan')` → 2 on `epoin_local`
- CLI: `etugas_tables_ready($koneksi)` → true

---

## Suggested commit message

```
fix(etugas): use information_schema for table existence check

Replace invalid SHOW TABLES LIKE ? prepared statement that caused SQL
syntax errors on admin/etugas.php and siswa/tugas_saya.php.
```
