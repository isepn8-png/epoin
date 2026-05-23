# Deployment Manifest — E-Tugas Phase 1A (Foundation)

| Field | Value |
|-------|--------|
| **Manifest ID** | `2026-05-17-etugas-phase-1a-foundation` |
| **Feature** | Pengumpulan Tugas Siswa (e-Tugas) |
| **Phase** | 1A — DB + helpers + placeholder pages + menus |
| **Status** | Ready for manual deploy |
| **Date** | 2026-05-17 |

---

## 1. Purpose

Lay the foundation for e-Tugas without full CRUD or student submission:

- Create `etugas` and `etugas_pengumpulan` tables
- Shared PHP helpers (`includes/etugas_helpers.php`)
- Placeholder admin/siswa pages and sidebar menu links

---

## 2. New files to upload

| Path | Description |
|------|-------------|
| `database/manual-migrations/2026-05-17-001-create-etugas-tables.sql` | CREATE TABLE migration |
| `includes/etugas_helpers.php` | Helper functions |
| `admin/etugas.php` | Admin/Guru placeholder page |
| `siswa/tugas_saya.php` | Siswa placeholder page |
| `docs/deployment-manifests/2026-05-17-etugas-phase-1a-foundation.md` | This file |
| `docs/ai-agent-reports/2026-05-17-etugas-phase-1a-foundation.md` | Implementation report |

---

## 3. Existing files to replace

| Path | Change |
|------|--------|
| `admin/header.php` | +1 menu: Pengumpulan Tugas → `etugas.php` |
| `siswa/header.php` | +1 menu: Tugas Saya → `tugas_saya.php` |
| `.gitignore` | Allow tracked manual migrations; ignore zip/rar |

---

## 4. SQL files to import

| File | When |
|------|------|
| `database/manual-migrations/2026-05-17-001-create-etugas-tables.sql` | **Before** or **with** first PHP upload |

**phpMyAdmin:** Import → choose file → Go.

**CLI (example):**

```bash
mysql -h 127.0.0.1 -P 3308 -u root epoin_local < database/manual-migrations/2026-05-17-001-create-etugas-tables.sql
```

---

## 5. Database changes

### New tables

- `etugas` — assignment header (TA, kelas, mapel, deadline, status, flags)
- `etugas_pengumpulan` — per-siswa submission (text, link, status, nilai, review fields)

### Constraints

- **No foreign keys** (indexes only)
- **Unique:** `uq_etugas_siswa (etugas_id, siswa_id)` on `etugas_pengumpulan`
- **No changes** to existing tables
- **No seed data** in migration

### Verify after import

```sql
SHOW TABLES LIKE 'etugas';
SHOW TABLES LIKE 'etugas_pengumpulan';
SHOW COLUMNS FROM etugas;
SHOW COLUMNS FROM etugas_pengumpulan;
```

**Local validation (2026-05-17):** Both tables present on `epoin_local`.

---

## 6. Files NOT to upload

- `.env`, `.env.local`
- Full database dumps (`*.sql` except `database/manual-migrations/*.sql`)
- `*.sql.gz`, `*.bak`, `*.backup`, `*.log`
- `*.zip`, `*.rar` backups
- License keys, credentials, local test scripts

---

## 7. Manual hosting deployment steps

1. **Backup** production database (full export).
2. **Backup** `admin/header.php` and `siswa/header.php` if overwriting.
3. Import `2026-05-17-001-create-etugas-tables.sql` in phpMyAdmin.
4. Upload new files (helpers + placeholder pages + migration folder for archive).
5. Replace `admin/header.php` and `siswa/header.php`.
6. Clear PHP OPcache if enabled.
7. Login as **administrator** → open `admin/etugas.php` → confirm info card + “Tabel tersedia”.
8. Login as **siswa** → open `siswa/tugas_saya.php` → confirm empty state.
9. Login as **guru** → confirm menu visible and page loads (scope count if applicable).

---

## 8. Rollback SQL

> **DANGER:** Deletes all e-Tugas data. Run only after a full DB backup.

```sql
-- ROLLBACK Phase 1A — destructive
DROP TABLE IF EXISTS etugas_pengumpulan;
DROP TABLE IF EXISTS etugas;
```

---

## 9. Rollback file steps

1. Restore backed-up `admin/header.php` and `siswa/header.php` (removes menu links).
2. Delete from server:
   - `admin/etugas.php`
   - `siswa/tugas_saya.php`
   - `includes/etugas_helpers.php`
3. Run rollback SQL (section 8) if tables were created.

---

## 10. Validation results

| Check | Result |
|-------|--------|
| `php -l includes/etugas_helpers.php` | PASS |
| `php -l admin/etugas.php` | PASS |
| `php -l siswa/tugas_saya.php` | PASS |
| `php -l admin/header.php` | PASS |
| `php -l siswa/header.php` | PASS |
| SQL import on `epoin_local` | PASS — `etugas`, `etugas_pengumpulan` created |
| Browser `admin/etugas.php` (no session) | Redirect to login (expected) |
| Browser `siswa/tugas_saya.php` (no session) | Requires manual login test |

---

## Next phase

**Phase 1B:** Admin assignment CRUD (`etugas_act.php`, edit/update; no hard delete).
