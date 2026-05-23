# E-Tugas Phase 1B — QA Report

**Date:** 2026-05-17  
**Scope:** Admin/guru assignment CRUD (no Phase 2 siswa submit)

---

## Executive summary

| Result | Detail |
|--------|--------|
| **Go/No-Go for Phase 2** | **GO** — no blocking defects after minor QA fixes |
| **SQL import** | Not required |
| **Critical bugs fixed in QA** | 2 (access order, defense-in-depth on row actions) |

---

## QA checklist results

| # | Requirement | Result | Notes |
|---|-------------|--------|-------|
| 1 | Admin assignment CRUD flow | PASS | Insert/update/status verified via CLI harness |
| 2 | No hard delete | PASS | No `DELETE FROM etugas` in codebase |
| 3 | Status only draft/aktif/ditutup/arsip | PASS | `etugas_is_valid_status()` + status handler |
| 4 | CSRF on POST handlers | PASS | `etugas_act`, `etugas_update`, `etugas_status` |
| 5 | Prepared statements on writes | PASS | All handlers use `mysqli_prepare` |
| 6 | Output escaped | PASS | `etugas_h()` on list/forms/alerts |
| 7 | Guru scope via pengampu_mapel | PASS | `guru_user_id` + OR scope in `etugas_build_list_where` |
| 8 | Admin manages all | PASS | `etugas_user_can_manage` → true for admin |
| 9 | Non-admin/non-guru blocked | PASS | `etugas_require_access()` before header (after QA fix) |
| 10 | `siswa/tugas_saya.php` loads | PASS | `php -l` OK; unchanged placeholder |
| 11 | Menu visibility | PASS | Only `_is_admin()` / `_is_guru()` in `admin/header.php` |

---

## Bugs found

### Fixed during QA (minor, not schema)

1. **Access check after `header.php`** — Staff without guru/admin role could load admin chrome before plain 403.  
   **Fix:** Load `koneksi` + helpers + `etugas_admin_context()` before `include 'header.php'` on `etugas.php`, `etugas_tambah.php`, `etugas_edit.php`.

2. **List actions without `can_manage` guard** — POST handlers already enforced; UI now hides Edit/status buttons when `!etugas_user_can_manage()`.

3. **`etugas_status.php` missing `etugas_tables_ready()`** — Added check before CSRF.

### Known limitations (not bugs — document for Phase 2)

| Item | Severity | Note |
|------|----------|------|
| `guru_user_id` = creator on insert | Low | Admin-created tasks owned by admin user id, not assigned guru |
| Guru form matrix for admin uses all kelas×mapel combos in JS | Low | Server validation still enforces rules; guru uses pengampu combos only |
| 403 is plain text for direct URL access | Low | Consistent with minimal guard; menu hidden for other roles |
| No rate limiting on POST | Low | Acceptable for intranet Phase 1B |

### Not found

- Hard delete endpoints  
- SQL injection in Phase 1B paths (writes use binding)  
- Invalid status values accepted on status handler  
- CSRF bypass on POST handlers  

---

## Database verification (local `epoin_local`)

CLI harness:

- Insert row → success  
- Update status to `arsip` → row retained, status changed  
- Row count did not decrease (no delete)  

---

## Files changed in QA pass

| File | Change |
|------|--------|
| `admin/etugas.php` | Auth before header; `can_manage` on row actions |
| `admin/etugas_tambah.php` | Auth before header |
| `admin/etugas_edit.php` | Auth before header |
| `admin/etugas_status.php` | `etugas_tables_ready()` check |

---

## Validation

All `php -l` checks: **PASS** on 10 PHP files listed in task.

---

## Browser test checklist (manual)

- [ ] Login **administrator** → Pengumpulan Tugas → summary cards + filters  
- [ ] **Buat Tugas** → save → appears in list  
- [ ] **Edit** → change judul → save  
- [ ] **Tutup** → status `ditutup`; row still in DB  
- [ ] **Arsipkan** → status `arsip`; row still in DB  
- [ ] Confirm no **Hapus** / delete button  
- [ ] Login **guru** with `pengampu_mapel` → create only scoped kelas/mapel  
- [ ] Guru cannot open another guru’s `etugas_edit.php?id=` (access denied message)  
- [ ] Login **TAS/sekretaris** → menu hidden; direct URL `etugas.php` → 403 without full admin UI (after fix)  
- [ ] Login **siswa** → Tugas Saya placeholder loads  

---

## Security notes

- CSRF: session token, `hash_equals` verification  
- IDOR: `etugas_user_can_manage` on edit/update/status  
- Guru isolation: `guru_user_id` + pengampu scope in SQL WHERE  
- Errors: `error_log` in handlers; user-facing flash messages only  

---

## Suggested commit message (QA fixes only)

```
fix(etugas): QA hardening for Phase 1B access order and row actions

Check e-Tugas role before admin header; guard list actions with can_manage;
add tables_ready check on status handler.
```
