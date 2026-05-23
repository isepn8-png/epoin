# E-Tugas Phase 2 — Siswa Submit Report

**Date:** 2026-05-17  
**Scope:** Student assignment list, detail, text/link submission (no file upload, no guru review, no export)

---

## Executive summary

| Item | Value |
|------|--------|
| **Status** | Implementation complete |
| **SQL** | No migration required |
| **Go for Phase 3** | After manual browser checklist on local/staging |

---

## Deliverables

### Pages

| Page | Role |
|------|------|
| `siswa/tugas_saya.php` | Summary cards, mapel/status filters, mobile-friendly task cards |
| `siswa/tugas_detail.php` | Instructions, deadline, late policy, existing submission, form |
| `siswa/tugas_kumpulkan.php` | POST-only: CSRF, validation, `INSERT … ON DUPLICATE KEY UPDATE` |

### Helpers (`includes/etugas_helpers.php`)

- `etugas_siswa_context()` — session siswa + kelas aktif + tables ready
- `etugas_list_tasks_for_siswa()` — JOIN mapel/kelas + LEFT JOIN pengumpulan
- `etugas_fetch_task_for_siswa()` — single task, `status IN ('aktif','ditutup')`, kelas/TA match
- `etugas_task_submission_state()` — can_submit, is_late, block_reason
- `etugas_validate_submission()` / `etugas_save_submission()`
- `etugas_is_safe_submission_link()` — http/https only, blocks dangerous schemes
- Summary/filter/badge utilities for siswa UI

---

## Access & business rules

| Rule | Implementation |
|------|----------------|
| Class scope | `etugas_get_siswa_kelas_aktif()` + `kelas_id` / `ta_id` match on queries |
| Hide draft/arsip | `WHERE e.status IN ('aktif', 'ditutup')` |
| `ditutup` | Visible; `can_submit = false` |
| Deadline + `izinkan_terlambat` | Block or allow with `is_terlambat=1` |
| `selesai` submission | Block overwrite |
| `revisi` | Resubmit → `status = terkirim` |
| `siswa_id` | Only from `$_SESSION['id']`, never from POST |
| Other students’ data | No list/detail without own session + class match |

---

## Security notes

- All reads/writes use `mysqli_prepare` + bound parameters
- CSRF on `tugas_kumpulkan.php` via `etugas_verify_csrf()`
- Output escaped with `etugas_h()` / `htmlspecialchars`
- Link validation: max 1000 chars, `http://`/`https://` only, rejects `javascript:`, `data:`, `file:`, `vbscript:`
- `link_jenis` from `etugas_classify_link()` (drive, youtube, canva, docs, lainnya)
- DB errors logged server-side only; user sees friendly flash messages
- No file upload endpoints

---

## UI/UX/A11y notes

- AdminLTE/Bootstrap 3 consistent with other siswa pages
- Vertical stacked cards for mobile; tap-friendly button min-height
- Visible labels on filter fields; form labels + helper text on textarea/link
- Status conveyed with text labels (badges), not color alone
- Video helper text on link field per spec
- Empty state explains tasks appear when guru publishes

---

## Validation (automated)

```
php -l includes/etugas_helpers.php     → OK
php -l siswa/tugas_saya.php              → OK
php -l siswa/tugas_detail.php            → OK
php -l siswa/tugas_kumpulkan.php         → OK
php -l siswa/header.php                  → OK

etugas_is_safe_submission_link('https://drive.google.com/...') → true
etugas_is_safe_submission_link('javascript:alert(1)')         → false
```

---

## Browser test checklist (manual)

See deployment manifest section 11. Agent did not complete full end-to-end browser run in this session; operator should verify on Laragon `:8088`.

---

## Remaining risks

| Risk | Mitigation |
|------|------------|
| Siswa without `kelas_siswa` row sees warning only | Document ops process for class enrollment |
| `mysqli` NULL binding on empty optional fields | Tested pattern with variables; verify on host PHP version |
| Closed task still visible in list | By design for history; submit blocked |
| No rate limiting on submit POST | Acceptable for intranet Phase 2 |
| Guru review overwrites student view fields | Phase 3 scope |

---

## Out of scope (unchanged)

- Guru review grid
- Export
- File/video upload to server

---

## Suggested commit message

```
feat(etugas): Phase 2 siswa task list and text/link submission

Add tugas_saya list UI, tugas_detail form, tugas_kumpulkan handler,
and siswa helpers for class-scoped assignments with CSRF and link validation.
No schema changes.
```
