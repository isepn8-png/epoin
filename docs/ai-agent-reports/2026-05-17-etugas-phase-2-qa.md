# E-Tugas Phase 2 — QA Report

**Date:** 2026-05-17  
**Scope:** Siswa task list, detail, text/link submission (pre–Phase 3)  
**Reviewer:** Agent QA pass (code + CLI harness + DB checks)

---

## Executive summary

| Result | Detail |
|--------|--------|
| **Go/No-Go for Phase 3** | **GO** — one minor bug fixed during QA; no schema changes |
| **SQL import** | **No SQL import required** |
| **Critical bugs** | 1 found and fixed (`tugas_detail.php` null task access) |

---

## QA checklist (17 scope items)

| # | Requirement | Result | Evidence |
|---|-------------|--------|----------|
| 1 | Siswa sees only own active class tasks | **PASS** | `etugas_list_tasks_for_siswa` / `etugas_fetch_task_for_siswa` filter `kelas_id` + `ta_id` from `etugas_get_siswa_kelas_aktif()`; CLI: other class returns null |
| 2 | Draft & arsip hidden | **PASS** | SQL `status IN ('aktif','ditutup')`; CLI toggled draft/arsip → fetch null |
| 3 | `ditutup` cannot submit | **PASS** | `etugas_task_submission_state` + handler redirect |
| 4 | Active tasks can submit | **PASS** | State machine + DB fetch for aktif task |
| 5 | Deadline + `izinkan_terlambat` | **PASS** | Late blocked/allowed + `is_late` flag in state |
| 6 | `siswa_id` from session only | **PASS** | `etugas_siswa_context()` → `$_SESSION['id']`; no `$_POST['siswa_id']` |
| 7 | Cannot access other student's submission | **PASS** | `etugas_fetch_submission($etugasId, $sessionSiswaId)` only |
| 8 | CSRF on `tugas_kumpulkan.php` | **PASS** | `etugas_verify_csrf()` before save |
| 9 | Link validation | **PASS** | https/http OK; javascript/data/file/vbscript rejected; len>1000 rejected |
| 10 | Text-only tasks | **PASS** | `etugas_validate_submission` harness |
| 11 | Link-only tasks | **PASS** | Harness |
| 12 | Text + link tasks | **PASS** | Either field satisfies minimum |
| 13 | `revisi` → resubmit `terkirim` | **PASS** | State allows submit; `etugas_save_submission` sets `terkirim` |
| 14 | `selesai` cannot overwrite | **PASS** | State blocks before validate/save |
| 15 | Output escaped | **PASS** | User fields via `etugas_h()`; badges built with escaped labels |
| 16 | No raw DB errors to users | **PASS** | Errors `error_log` only; flash messages generic |
| 17 | Mobile-friendly UI | **PASS** | Stacked cards, min-height buttons, visible labels (review) |

---

## Bugs found

### Fixed during QA

| ID | Severity | Issue | Fix |
|----|----------|-------|-----|
| P2-QA-01 | **Minor** | `tugas_detail.php` read `$task['allow_text']` etc. when `$task` is null (invalid id) → PHP 8 warnings | Guard `$canSubmit` / `$allowText` / `$allowLink` / `$latePolicy` behind `if ($task)` |

### Not fixed (document for Phase 3 / product)

| ID | Severity | Issue | Recommendation |
|----|----------|-------|----------------|
| P2-QA-L01 | Low | Resubmit on text+link task with only one field filled sets the other column to `NULL` (full replace) | Consider merge semantics in Phase 3 if needed |
| P2-QA-L02 | Low | Any resubmit sets `status = terkirim` (including from `ditinjau`) | Phase 3 review grid may need to preserve `ditinjau` or add audit trail |
| P2-QA-L03 | Low | `etugas_siswa_context()` returns plain-text 403 (not styled) | Consistent with other guards; optional UX polish |

**No critical open defects** after P2-QA-01 fix.

---

## Database checks

| Check | Result |
|-------|--------|
| `UNIQUE KEY uq_etugas_siswa (etugas_id, siswa_id)` | Present in Phase 1A migration |
| One row per student per task | **PASS** — upsert harness: 2 saves → `COUNT(*) = 1`, text updated |
| Resubmit updates row | **PASS** — `ON DUPLICATE KEY UPDATE` |

**No SQL import required.**

---

## Validation results

```
php -l includes/etugas_helpers.php   → OK
php -l siswa/tugas_saya.php          → OK
php -l siswa/tugas_detail.php        → OK
php -l siswa/tugas_kumpulkan.php     → OK
php -l siswa/header.php              → OK

php tests/etugas_phase2_qa_harness.php → 26 passed, 0 failed
```

---

## Browser test checklist (manual sign-off)

| # | Step | Expected |
|---|------|----------|
| 1 | Login admin/guru → create **aktif** task for test class | Task saved |
| 2 | Login siswa in that class → **Tugas Saya** | Task listed |
| 3 | Login siswa in **other** class | Task not listed |
| 4 | Set task **draft** / **arsip** | Hidden from siswa list |
| 5 | Open **Detail** → submit text | Success flash; row in DB |
| 6 | Submit `https://drive.google.com/...` | Accepted; `link_jenis` classified |
| 7 | Submit `javascript:alert(1)` | Rejected with friendly error |
| 8 | Set task **ditutup** | Visible; form blocked |
| 9 | Past deadline, `izinkan_terlambat=0` | Submit blocked |
| 10 | Past deadline, `izinkan_terlambat=1` | Submit OK; `is_terlambat=1` |
| 11 | Guru sets submission **revisi** → siswa resubmits | Status **terkirim** |
| 12 | Guru sets **selesai** → siswa cannot edit | Block message |
| 13 | POST without CSRF token | Redirect error |
| 14 | Mobile viewport | Cards stack; buttons tappable |

---

## UI/UX/A11y notes

- Summary cards and filter labels readable on small screens.
- Form fields have `<label>` + helper text (link/video guidance).
- Status uses text badges (`Terkirim`, `Perlu Revisi`, etc.), not color alone.
- Empty state explains when guru publishes tasks.
- Duplicate “Ditutup” label on list when task status is `ditutup` (badge + extra label) — cosmetic only.

---

## Security notes

- Prepared statements on all siswa read/write paths reviewed.
- CSRF enforced on submission POST.
- Link scheme whitelist + length cap aligned with DB `VARCHAR(1000)`.
- Session-bound `siswa_id`; class/TA scoping on task fetch prevents cross-class IDOR.
- No file upload surface in Phase 2.

---

## Files changed in QA pass

| File | Change |
|------|--------|
| `siswa/tugas_detail.php` | Guard task-dependent variables when task missing |
| `tests/etugas_phase2_qa_harness.php` | Optional CLI QA harness (dev only, not for production upload) |

---

## Go/No-Go for Phase 3

**GO** — Proceed with guru review grid / export when manual browser checklist (above) is signed off on local/staging.

Prerequisites for Phase 3 deploy:

- Phase 2 files deployed (see `2026-05-17-etugas-phase-2-siswa-submit.md`)
- Optional: deploy QA fix `siswa/tugas_detail.php` if Phase 2 already uploaded without it

---

## Related

- `docs/deployment-manifests/2026-05-17-etugas-phase-2-qa.md`
- `docs/deployment-manifests/2026-05-17-etugas-phase-2-siswa-submit.md`
