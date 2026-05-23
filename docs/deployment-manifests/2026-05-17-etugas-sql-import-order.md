# E-Tugas — SQL Import Order

**Date:** 2026-05-17 (final package)  
**Target:** Shared hosting via phpMyAdmin  
**Schema changes after import:** None (all later phases are application-only)

---

## Import order (exact)

| Order | File | When |
|-------|------|------|
| **1** | `database/manual-migrations/2026-05-17-001-create-etugas-tables.sql` | **Once**, if `etugas` and `etugas_pengumpulan` do not exist |

**This is the only SQL file required for the entire E-Tugas module.**

---

## Per-phase SQL status

| Phase / feature | SQL import required? |
|-----------------|----------------------|
| **Phase 1A Foundation** | **Yes** — import once (creates tables) |
| Phase 1A Bugfix | No |
| Phase 1B Admin CRUD | No |
| Phase 1B QA | No |
| Phase 2 Siswa submit | No |
| Phase 2 QA | No |
| Phase 3A Guru review | No |
| Phase 3A QA | No |
| Phase 4A Rekap + CSV | No |
| Phase 4A QA | No |
| **Multi-kelas create** | **No** |
| **Safe Delete** | **No** |

---

## Multi-kelas (no SQL)

- Admin/guru select multiple **kelas** on create (`kelas_ids[]`).
- System inserts **one `etugas` row per selected kelas** (same judul, mapel, deadline, flags).
- **Edit** remains one row / one kelas at a time.
- No new tables or columns.

---

## Safe Delete (no SQL)

- Permanent delete only when **zero** rows in `etugas_pengumpulan`.
- Handler: `admin/etugas_hapus.php` (POST + CSRF).
- Uses atomic `DELETE ... AND NOT EXISTS (SELECT 1 FROM etugas_pengumpulan ...)` in `includes/etugas_helpers.php`.
- Does **not** delete or cascade `etugas_pengumpulan` rows.
- Tasks with submissions must use **Arsipkan** (`etugas_status.php`).

---

## Before you import

1. Export a full database backup from phpMyAdmin.
2. Run:

```sql
SHOW TABLES LIKE 'etugas';
SHOW TABLES LIKE 'etugas_pengumpulan';
```

- Both return a row → **skip import** (do not import twice).
- Neither exists → proceed with import.

---

## phpMyAdmin steps

1. cPanel → **phpMyAdmin** → select EPOIN database.
2. **Import** → choose `2026-05-17-001-create-etugas-tables.sql`.
3. Format: SQL; charset **utf8mb4**.
4. **Go** → confirm success.

---

## After import — verify

```sql
SHOW TABLES LIKE 'etugas';
SHOW TABLES LIKE 'etugas_pengumpulan';
SHOW INDEX FROM etugas_pengumpulan WHERE Key_name = 'uq_etugas_siswa';
```

Expected:

- `etugas` — assignment header.
- `etugas_pengumpulan` — per-student submission.
- Unique `(etugas_id, siswa_id)`.
- No foreign keys; no seed data.

---

## Rollback SQL (destructive)

> **DANGER:** Deletes all E-Tugas data. Full DB backup required first.

```sql
DROP TABLE IF EXISTS etugas_pengumpulan;
DROP TABLE IF EXISTS etugas;
```

---

## Summary

| Item | Value |
|------|--------|
| Total SQL files | **1** |
| Import count on fresh DB | **1 time only** |
| Multi-kelas SQL | **None** |
| Safe Delete SQL | **None** |
