# E-Tugas Phase 1B — Admin/Guru CRUD Report

**Date:** 2026-05-17  
**Scope:** Assignment management UI + handlers (no siswa submit, no review grid, no export, no hard delete)

---

## Delivered

### Pages
- `admin/etugas.php` — dashboard: summary cards, filters, DataTable-style list, empty state, status actions
- `admin/etugas_tambah.php` — create form (two-column, responsive)
- `admin/etugas_edit.php` — edit form with current status badge
- `admin/etugas_form_inc.php` — shared fields + TA/kelas/mapel JS filter from `pengampu` combos

### Handlers (POST, CSRF, prepared statements)
- `admin/etugas_act.php` — INSERT
- `admin/etugas_update.php` — UPDATE
- `admin/etugas_status.php` — status only (`draft` / `aktif` / `ditutup` / `arsip`)

### Helpers (`includes/etugas_helpers.php`)
- CSRF: `etugas_csrf_token`, `etugas_csrf_field`, `etugas_verify_csrf`
- Access: `etugas_require_access`, `etugas_admin_context`, `etugas_user_can_manage`
- Validation: `etugas_validate_assignment`
- List: `etugas_list_assignments`, `etugas_count_summary`, `etugas_build_list_where` (guru scoped by `guru_user_id` + pengampu OR clauses)
- UI: `etugas_status_badge`, `etugas_jenis_label`, `etugas_form_matrix`

### Menu (`admin/header.php`)
- **Pengumpulan Tugas** visible only if `_is_admin()` or `_is_guru()`
- Page still guarded by `etugas_require_access()`

---

## Access rules

| Role | Create/list/edit |
|------|------------------|
| Administrator | All assignments |
| Guru | Only own `guru_user_id` + `(ta_id, kelas_id, mapel_id)` in `pengampu_mapel` |
| Other staff | No menu; 403 if URL accessed |

---

## Security

- mysqli prepared statements throughout
- Output via `etugas_h()` / `htmlspecialchars`
- CSRF on all POST handlers
- No `DELETE FROM etugas` — archive via `status='arsip'`
- DB errors logged; user sees generic flash messages

---

## UI/UX/A11y

- AdminLTE boxes, summary cards, filter form with labels
- Status badges (draft/aktif/ditutup/arsip)
- Form: required markers, `aria-required`, help text, fieldset legend for checkboxes
- Action buttons include text labels (not icon-only)
- Empty state with CTA “Buat Tugas Pertama”
- Mobile: stacked columns via Bootstrap grid

---

## SQL

**No new migration for Phase 1B.**

---

## Suggested commit message

```
feat(etugas): Phase 1B admin/guru assignment CRUD with scoped access

Add list dashboard, create/edit forms, CSRF-protected handlers, and status
workflow (no hard delete). Guru limited to pengampu_mapel scope.
```

---

## Next phase

- Phase 2: Siswa `tugas_saya` submission (text/link)
- Phase 3: Guru review grid (`etugas_pengumpulan`)
