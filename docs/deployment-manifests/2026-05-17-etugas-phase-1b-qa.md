# Deployment Manifest — E-Tugas Phase 1B QA Fixes


| Field                   | Value                                                 |
| ----------------------- | ----------------------------------------------------- |
| **Manifest ID**         | `2026-05-17-etugas-phase-1b-qa`                       |
| **Type**                | QA follow-up (optional deploy with or after Phase 1B) |
| **SQL import required** | **No**                                                |


---

## 1. Purpose

Apply small hardening fixes from Phase 1B QA without schema changes or Phase 2 features.

---

## 2. Files changed


| File                      | Change                                                         |
| ------------------------- | -------------------------------------------------------------- |
| `admin/etugas.php`        | Role check before header; action buttons gated by `can_manage` |
| `admin/etugas_tambah.php` | Role check before header                                       |
| `admin/etugas_edit.php`   | Role check before header                                       |
| `admin/etugas_status.php` | `etugas_tables_ready()` guard                                  |


**No changes** to `includes/etugas_helpers.php` in this QA pass.

---

## 3. SQL import required

**No.**

---

## 4. Manual hosting upload list

Replace on server (if Phase 1B already deployed):

1. `admin/etugas.php`
2. `admin/etugas_tambah.php`
3. `admin/etugas_edit.php`
4. `admin/etugas_status.php`

---

## 5. Rollback

Restore previous copies of the four files from backup.

---

## 6. Validation results


| Check                        | Result                 |
| ---------------------------- | ---------------------- |
| `php -l` all Phase 1B files  | PASS                   |
| DB insert + status `arsip`   | PASS (row not deleted) |
| No DELETE in etugas handlers | PASS                   |


---

## 7. Browser test results

Pending manual sign-off — use checklist in `docs/ai-agent-reports/2026-05-17-etugas-phase-1b-qa.md`.

---

## 8. Go/No-Go

**GO for Phase 2** (siswa submission) after manual browser checklist on staging/local.

---

## Related manifests

- `2026-05-17-etugas-phase-1b-admin-crud.md`
- `2026-05-17-etugas-phase-1a-foundation.md`

