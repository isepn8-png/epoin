# AI Agent Report — E-Tugas Safe Delete

**Date:** 2026-05-17  
**Feature:** Permanent delete for empty tasks only

---

## Root cause / need

Daftar tugas (`admin/etugas.php`) hanya menyediakan ubah status (Tutup, Arsipkan, Draft) tanpa cara menghapus tugas uji/kosong. Diperlukan hapus permanen yang **aman**: hanya jika belum ada data di `etugas_pengumpulan`.

---

## Implementation

### Helpers (`includes/etugas_helpers.php`)

| Function | Role |
|----------|------|
| `etugas_count_pengumpulan_for_task()` | `COUNT(*)` per `etugas_id` |
| `etugas_map_pengumpulan_counts()` | Batch counts for list page |
| `etugas_delete_assignment_if_empty()` | Re-check count; `DELETE` only if zero |

### Handler (`admin/etugas_hapus.php`)

- POST only (non-POST → redirect)
- `etugas_admin_context()` (blocks siswa/non-staff)
- CSRF verification
- `etugas_user_can_manage()` authorization
- Server-side delete via helper (never trusts UI alone)

### UI (`admin/etugas.php`)

- Batch-load submission counts for listed tasks
- **Hapus** (`btn-danger btn-xs`) + `confirm()` when count = 0 and user can manage
- Muted hint when count > 0: *Sudah ada pengumpulan — gunakan Arsipkan*
- **Arsipkan** unchanged

---

## Files created

- `admin/etugas_hapus.php`

## Files modified

- `includes/etugas_helpers.php`
- `admin/etugas.php`

---

## SQL

**No SQL import required.**

---

## Validation

`php -l` — PASS on all three production files.

---

## Security notes

- POST + CSRF only; no GET delete
- Prepared statements for COUNT and DELETE
- Authorization + submission count re-checked server-side
- `error_log` on DB failures; generic flash to user
- No cascade delete of `etugas_pengumpulan`

---

## Remaining risks

- Race: submission inserted between UI render and delete (mitigated by server-side count before DELETE)
- Guru delete limited by existing `etugas_user_can_manage()` (guru_user_id + scope)

---

## Suggested commit message

```
feat(etugas): safe permanent delete for tasks without submissions

Add etugas_hapus POST handler and list UI; block delete when
etugas_pengumpulan rows exist; keep Arsipkan for submitted tasks.
```
