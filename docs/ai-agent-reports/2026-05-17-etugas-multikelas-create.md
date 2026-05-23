# AI Agent Report — E-Tugas Multi-Kelas Create

**Date:** 2026-05-17  
**Feature:** Create assignment for multiple classes in one submission  
**Schema change:** None

---

## Summary

Replaced single-kelas dropdown on **create** with a checkbox grid filtered by tahun ajaran + mapel. `etugas_act.php` validates `kelas_ids[]`, checks guru `pengampu_mapel` scope server-side, skips duplicates, and inserts rows inside a transaction (rollback on insert failure).

**Edit** remains single-kelas per `etugas` row, with UI note about batch-created tasks.

---

## Implementation

### Helpers (`includes/etugas_helpers.php`)

| Function | Role |
|----------|------|
| `etugas_parse_kelas_ids()` | Parse unique `kelas_ids[]` from POST |
| `etugas_assignment_is_duplicate()` | Match draft/aktif by TA, kelas, mapel, judul, deadline |
| `etugas_validate_assignment_create()` | Full create validation + per-kelas scope |
| `etugas_create_assignments_batch()` | Transactional multi-insert; skip duplicates |
| `etugas_format_batch_create_message()` | e.g. `3 tugas dibuat, 1 dilewati karena sudah ada.` |

### Form (`admin/etugas_form_inc.php`)

- **Create:** TA → Mapel → Kelas checkbox grid; Pilih semua / Hapus pilihan; live count.
- **Edit:** unchanged TA → Kelas → Mapel flow.
- Lightweight vanilla JS; no new libraries.

### Handler (`admin/etugas_act.php`)

Uses `etugas_validate_assignment_create` + `etugas_create_assignments_batch`.

---

## Files modified

1. `includes/etugas_helpers.php`
2. `admin/etugas_act.php`
3. `admin/etugas_form_inc.php`
4. `admin/etugas_tambah.php`
5. `admin/etugas_edit.php`

---

## SQL

**No SQL import required.**

---

## Validation

`php -l` on all five changed files — run on deploy host.

---

## Browser test checklist

1. Admin: single kelas → 1 row.
2. Admin: 3 kelas → 3 rows.
3. Per-class siswa visibility.
4. Duplicate skip message.
5. Guru scope on checkboxes + POST tamper rejection.
6. Review/rekap unchanged.
7. Edit page note + single kelas.

---

## UI/UX / A11y

- Label **Kelas tujuan** with required marker and help text.
- `role="group"`, `aria-live` on selection count.
- Responsive checkbox grid; toolbar buttons for bulk select/clear.
- Edit page info alert explains per-kelas records.

---

## Security

- CSRF on POST.
- Prepared statements for insert and duplicate check.
- Every `kelas_id` validated: integer, in TA, in guru scope.
- No partial insert on validation failure; transaction rollback on DB error.
- Duplicates skipped without exposing SQL errors.

---

## Remaining risks

- Large number of kelas selected → many rows (operator discipline).
- Guru must have `pengampu_mapel` for each selected kelas+mapel combo.
- Batch edit across kelas not in scope (by design).

---

## Suggested commit message

```
feat(etugas): create assignments for multiple kelas in one submit

Insert one etugas row per selected class with shared fields; skip duplicates;
enforce pengampu_mapel scope server-side. Edit remains single-kelas.
```
