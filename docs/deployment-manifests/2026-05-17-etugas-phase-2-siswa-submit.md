# Deployment Manifest — E-Tugas Phase 2 (Siswa Submit)

| Field | Value |
|-------|--------|
| **Manifest ID** | `2026-05-17-etugas-phase-2-siswa-submit` |
| **Type** | Feature — siswa assignment list & submission |
| **SQL import required** | **No** |
| **Prerequisite** | Phase 1A tables + Phase 1B admin CRUD deployed |

---

## 1. Purpose

Enable students to view active/closed assignments for their class and submit text/link answers (no file upload, no guru review grid, no export).

---

## 2. New files to upload

| File | Description |
|------|-------------|
| `siswa/tugas_detail.php` | Task detail, instructions, submission view, form |
| `siswa/tugas_kumpulkan.php` | POST handler (CSRF, validation, UPSERT) |

---

## 3. Existing files to replace

| File | Description |
|------|-------------|
| `includes/etugas_helpers.php` | Siswa helpers: list, fetch, validate, save submission |
| `siswa/tugas_saya.php` | List UI: summary cards, filters, mobile cards |
| `siswa/header.php` | Active menu highlight for `tugas_detail.php` |

---

## 4. SQL files to import

**No SQL import required for Phase 2.**

Requires existing tables from Phase 1A:

- `database/manual-migrations/2026-05-17-001-create-etugas-tables.sql` (if not already imported)

---

## 5. Database changes

None. Uses existing `etugas` and `etugas_pengumpulan` tables.

---

## 6. Files NOT to upload

- `docs/**` (optional documentation only)
- `.env`, `.env.local`
- `database/manual-migrations/*` (unless Phase 1A never deployed)
- Guru review/export pages (not created in Phase 2)
- Test scripts, IDE folders

---

## 7. Manual hosting deployment steps

1. **Backup** current `includes/etugas_helpers.php`, `siswa/tugas_saya.php`, `siswa/header.php`.
2. **Upload** new files:
   - `siswa/tugas_detail.php`
   - `siswa/tugas_kumpulkan.php`
3. **Replace** on server:
   - `includes/etugas_helpers.php`
   - `siswa/tugas_saya.php`
   - `siswa/header.php`
4. Confirm Phase 1A migration already applied (`etugas`, `etugas_pengumpulan` exist).
5. As admin/guru: create or publish one **aktif** task for a test class.
6. Login as **siswa** in that class → **Tugas Saya** → submit text/link.
7. Verify row in `etugas_pengumpulan` via phpMyAdmin (optional).

---

## 8. Rollback file steps

1. Restore backed-up copies of:
   - `includes/etugas_helpers.php`
   - `siswa/tugas_saya.php`
   - `siswa/header.php`
2. Delete (if uploaded):
   - `siswa/tugas_detail.php`
   - `siswa/tugas_kumpulkan.php`

---

## 9. Rollback SQL

**None** — no schema changes in Phase 2.

Optional: delete test rows from `etugas_pengumpulan` created during validation (manual).

---

## 10. Validation results

| Check | Result |
|-------|--------|
| `php -l includes/etugas_helpers.php` | PASS |
| `php -l siswa/tugas_saya.php` | PASS |
| `php -l siswa/tugas_detail.php` | PASS |
| `php -l siswa/tugas_kumpulkan.php` | PASS |
| `php -l siswa/header.php` | PASS |
| `etugas_is_safe_submission_link(https://…)` | PASS |
| `etugas_is_safe_submission_link(javascript:…)` | PASS (rejected) |
| `etugas_classify_link` (YouTube sample) | PASS → `youtube` |

---

## 11. Browser test results

| # | Test | Result | Notes |
|---|------|--------|-------|
| 1 | Admin/guru creates aktif task for test class | **Manual** | Required before siswa tests |
| 2 | Siswa login → Tugas Saya | **Manual** | |
| 3 | Task appears in list | **Manual** | |
| 4 | Open task detail | **Manual** | |
| 5 | Submit text answer | **Manual** | |
| 6 | Submit valid Drive/YouTube link | **Manual** | |
| 7 | Reject `javascript:alert(1)` | **Manual** | Server-side validation |
| 8 | Row in `etugas_pengumpulan` | **Manual** | |
| 9 | Other siswa cannot access submission | **Manual** | Scoped by session `siswa_id` |
| 10 | Draft/arsip hidden | **Manual** | SQL filter `status IN ('aktif','ditutup')` |
| 11 | `ditutup` task — no submit | **Manual** | |
| 12 | `selesai` submission — no overwrite | **Manual** | |

CLI helper checks completed in dev environment; full browser sign-off pending on staging/local Laragon (`:8088`).

---

## Related

- `docs/ai-agent-reports/2026-05-17-etugas-phase-2-siswa-submit.md`
- `docs/deployment-manifests/2026-05-17-etugas-phase-1b-qa.md`
