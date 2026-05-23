# Deployment Manifest — E-Tugas Phase 1B (Admin/Guru CRUD)

| Field | Value |
|-------|--------|
| **Manifest ID** | `2026-05-17-etugas-phase-1b-admin-crud` |
| **Feature** | Pengumpulan Tugas — kelola tugas (admin/guru) |
| **SQL import required** | **No** (uses Phase 1A tables) |

---

## 1. Purpose

Enable administrators and teachers to create, edit, and change status of assignments (e-Tugas) without student submission or review grid. No hard delete — archive sets `status='arsip'` only.

---

## 2. New files to upload

| Path |
|------|
| `admin/etugas_tambah.php` |
| `admin/etugas_act.php` |
| `admin/etugas_edit.php` |
| `admin/etugas_update.php` |
| `admin/etugas_status.php` |
| `admin/etugas_form_inc.php` |
| `docs/deployment-manifests/2026-05-17-etugas-phase-1b-admin-crud.md` |
| `docs/ai-agent-reports/2026-05-17-etugas-phase-1b-admin-crud.md` |

---

## 3. Existing files to replace

| Path |
|------|
| `includes/etugas_helpers.php` |
| `admin/etugas.php` |
| `admin/header.php` |

**Unchanged but required from Phase 1A:** `database/manual-migrations/2026-05-17-001-create-etugas-tables.sql` (already imported on server).

---

## 4. SQL files to import

**No SQL import required for Phase 1B.**

Prerequisite: Phase 1A migration already applied (`etugas`, `etugas_pengumpulan`).

---

## 5. Database changes

None. All operations use existing `etugas` table columns from Phase 1A.

---

## 6. Files NOT to upload

- `.env`, full DB dumps, `*.bak`, `*.log`, `*.zip`, `*.rar`
- Local test scripts

---

## 7. Manual hosting deployment steps

1. Backup DB and `includes/etugas_helpers.php`, `admin/etugas.php`, `admin/header.php`.
2. Confirm Phase 1A tables exist.
3. Upload all **new** `admin/etugas_*.php` files.
4. Replace `includes/etugas_helpers.php`, `admin/etugas.php`, `admin/header.php`.
5. Clear OPcache if used.
6. Test as **administrator**: list → create → edit → status ditutup → arsip.
7. Test as **guru** with `pengampu_mapel`: create only allowed kelas/mapel; no other guru’s tasks visible.

---

## 8. Rollback file steps

1. Restore backed-up `etugas_helpers.php`, `etugas.php`, `header.php`.
2. Delete new files: `etugas_tambah.php`, `etugas_act.php`, `etugas_edit.php`, `etugas_update.php`, `etugas_status.php`, `etugas_form_inc.php`.
3. Data in `etugas` remains (optional manual cleanup).

---

## 9. Rollback SQL

**Not required** for Phase 1B rollback (no schema change).

To remove all e-Tugas data (destructive, Phase 1A only):

```sql
-- DANGER: backup first
DROP TABLE IF EXISTS etugas_pengumpulan;
DROP TABLE IF EXISTS etugas;
```

---

## 10. Validation results

| Check | Result |
|-------|--------|
| `php -l` on all Phase 1B PHP files | PASS (run after deploy) |
| Tables `etugas` / `etugas_pengumpulan` | Required from Phase 1A |

---

## 11. Browser test results

| Step | Expected |
|------|----------|
| Admin → Pengumpulan Tugas | Summary cards, filters, table/empty state |
| Buat Tugas | Form saves; redirect success |
| Edit / status | Updates; Arsipkan sets `arsip` only |
| Guru scope | Only pengampu kelas/mapel |
| Siswa `tugas_saya.php` | Unchanged placeholder |

---

## Related

- Phase 1A: `2026-05-17-etugas-phase-1a-foundation.md`
- Phase 1A bugfix: `2026-05-17-etugas-phase-1a-bugfix.md`
