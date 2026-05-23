# E-Tugas Phase 3A — Guru/Admin Review Report

**Date:** 2026-05-17  
**Scope:** Review grid, detail, status/nilai update (no export, no file upload)

---

## Executive summary

| Item | Value |
|------|--------|
| **Status** | Implementation complete |
| **SQL** | No migration required |
| **Access model** | Guru via `pengampu_mapel` (ta/kelas/mapel), not `etugas.guru_user_id` alone |

---

## Deliverables

### Pages

| Page | Role |
|------|------|
| `admin/etugas_pengumpulan.php` | Filters, summary cards, all-class student table (LEFT JOIN) |
| `admin/etugas_pengumpulan_detail.php` | Student + task info, submission view, review form |
| `admin/etugas_pengumpulan_update.php` | POST: status, catatan_guru, nilai, reviewed_by/at |

### Helpers

- `etugas_user_can_review()` — admin all; guru = `etugas_scope_has()` on pengampu
- `etugas_list_review_rows()` — `kelas_siswa` + `siswa` LEFT JOIN `etugas_pengumpulan`
- `etugas_fetch_pengumpulan_by_id()`, `etugas_fetch_review_student_view()`
- `etugas_validate_review_update()`, `etugas_update_pengumpulan_review()`
- Summary, badges, task dropdown scoped for review

### Other

- `admin/etugas.php` — **Lihat Pengumpulan** button (review scope)
- `admin/header.php` — separate menu entries for Kelola vs Review

---

## Key design decisions

1. **All students in class** — `INNER JOIN kelas_siswa` + `LEFT JOIN etugas_pengumpulan` so guru sees belum mengumpulkan.
2. **Guru scope** — `etugas_build_review_scope_sql()` uses pengampu combos only (fixes admin-created tasks where `guru_user_id` is admin).
3. **Task required** — Table loads only when `etugas_id` filter is set (avoids unbounded queries).
4. **Pagination** — 50 rows per page in PHP slice after filtered list.
5. **No auto-create submission** — Belum mengumpulkan shows info only, no review form.
6. **Belum detail URL** — `?etugas_id=&siswa_id=` with enrollment check; redirects to `?id=` if submission exists.

---

## Security

- `etugas_admin_context()` before `header.php` on all pages
- CSRF on `etugas_pengumpulan_update.php`
- Authorization via `etugas_user_can_review()` on task row, not request `siswa_id`
- Prepared statements on list/detail/update
- Links open with `rel="noopener noreferrer"`
- No raw DB errors to users

---

## UI/UX/A11y

- AdminLTE summary cards and filter labels
- Text status badges (Belum Mengumpulkan, Terkirim, etc.)
- Detail: Buka Link button with label; print-friendly CSS
- Empty states for “select task” and empty class roster

---

## Validation

All Phase 3A PHP files pass `php -l`.

---

## Manual browser checklist

1. Admin: create aktif task  
2. Siswa A submits, Siswa B does not  
3. Admin/guru: Review Pengumpulan → select task  
4. A = submitted, B = Belum Mengumpulkan  
5. Open A detail → link opens new tab  
6. Set ditinjau → revisi + catatan  
7. Siswa A sees revisi → resubmits  
8. Guru sets selesai + nilai  
9. Guru outside pengampu cannot access task review  
10. Non-staff blocked; siswa cannot access admin URLs  

---

## Remaining risks

| Risk | Note |
|------|------|
| Large classes | Pagination helps; very large rosters may need server-side LIMIT later |
| `etugas_user_can_manage` still uses `guru_user_id` | Edit task only; review uses separate `can_review` |
| Menu label split | Operators may need brief training (Kelola vs Review) |

---

## Suggested commit message

```
feat(etugas): Phase 3A guru review grid with full class roster

Add review list/detail/update for admin and guru (pengampu scope),
LEFT JOIN to show unsubmitted students, and link from task list.
No schema changes.
```
