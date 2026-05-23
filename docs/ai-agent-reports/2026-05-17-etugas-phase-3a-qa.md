# E-Tugas Phase 3A — QA Report

**Date:** 2026-05-17  
**Scope:** Guru/admin review grid, detail, update (pre–Phase 4 export)  
**Reviewer:** Agent QA pass (code review + CLI harness + DB checks)

---

## Executive summary

| Result | Detail |
|--------|--------|
| **Go/No-Go for Phase 4 (export)** | **GO** — no critical defects; no schema changes |
| **SQL import** | **No SQL import required** |
| **Critical bugs** | **None** (no code changes in QA pass) |

---

## QA checklist (20 scope items)

| # | Requirement | Result | Evidence |
|---|-------------|--------|----------|
| 1 | Admin reviews all tasks/submissions | **PASS** | `etugas_user_can_review` → true for admin; list uses admin ctx |
| 2 | Guru only pengampu_mapel scope | **PASS** | `etugas_get_guru_scope` filters `pm.guru_user_id`; `etugas_scope_has` on ta/kelas/mapel |
| 3 | Review not only `etugas.guru_user_id` | **PASS** | `can_review` ≠ `can_manage`; admin-owned task reviewable by scoped guru |
| 4 | Admin-created tasks reviewable by correct guru | **PASS** | Harness: `can_review(guru, taskAdminOwned)` true while `can_manage` false |
| 5 | Siswa cannot access admin review pages | **PASS** | `etugas_admin_context` → `etugas_require_access()` (admin/guru only) |
| 6 | Non-admin/non-guru blocked | **PASS** | Same guard; 403 plain text |
| 7 | All class students in list (submitted + belum) | **PASS** | `kelas_siswa` INNER + `LEFT JOIN etugas_pengumpulan`; harness |
| 8 | No empty pengumpulan row for belum | **PASS** | No INSERT in review pages; harness count unchanged after student view |
| 9 | Detail: submitted / belum / invalid / unauthorized | **PASS** | `id` + `can_review`; `etugas_id`+`siswa_id` redirect if submitted; else null |
| 10 | Valid status only on update | **PASS** | `etugas_validate_review_update` + harness (4 statuses) |
| 11 | Nilai optional 0–100 | **PASS** | Harness rejects 101, `abc`; accepts empty |
| 12 | `catatan_guru` escaped | **PASS** | `nl2br(etugas_h(...))` on detail; stored via prepared stmt |
| 13 | Link `target="_blank" rel="noopener noreferrer"` | **PASS** | `etugas_pengumpulan_detail.php` line ~143 |
| 14 | CSRF on update | **PASS** | `etugas_verify_csrf()` before save |
| 15 | Prepared statements | **PASS** | List/detail/update helpers use `mysqli_prepare` |
| 16 | No raw DB errors to users | **PASS** | `error_log` only; flash messages generic |
| 17 | Siswa sees revisi after guru marks | **PASS** | Phase 2 detail shows `catatan_guru` + badge |
| 18 | Siswa resubmit after revisi | **PASS** | `etugas_task_submission_state` allows; save → `terkirim` |
| 19 | Siswa blocked after selesai | **PASS** | State machine + harness |
| 20 | Mobile/responsive review UI | **PASS** | `table-responsive`, summary cards, `@media` in list CSS |

---

## Bugs found

### Critical

None.

### Minor / deferred (not blocking Phase 4)

| ID | Severity | Issue | Recommendation |
|----|----------|-------|----------------|
| P3A-QA-L01 | Low | `etugas_fetch_review_student_view()` has no internal `can_review` check | Callers enforce; optional defense-in-depth |
| P3A-QA-L02 | Low | Duplicate `kelas_siswa` rows could duplicate list lines | Rare; add DISTINCT later if needed |
| P3A-QA-L03 | Low | Guru with empty pengampu sees empty UI + info alert | Expected; document for ops |
| P3A-QA-L04 | Low | Plain-text 403 for direct URL access | Consistent with Phase 1B–2 |

---

## Database checks

| Check | Result |
|-------|--------|
| LEFT JOIN returns unsubmitted without INSERT | **PASS** (harness) |
| Review UPDATE targets `pengumpulan_id` only | **PASS** (single row, status/nilai updated) |
| `revisi` → siswa can submit | **PASS** (state machine) |
| `selesai` → siswa blocked | **PASS** |

**No SQL import required.**

---

## Validation results

```
php -l includes/etugas_helpers.php              → OK
php -l admin/etugas.php                         → OK
php -l admin/etugas_pengumpulan.php             → OK
php -l admin/etugas_pengumpulan_detail.php      → OK
php -l admin/etugas_pengumpulan_update.php      → OK
php -l admin/header.php                         → OK
php -l siswa/tugas_saya.php                     → OK
php -l siswa/tugas_detail.php                   → OK
php -l siswa/tugas_kumpulkan.php                → OK

php tests/etugas_phase3a_qa_harness.php           → 25/25 PASS
```

---

## Browser test checklist (manual)

| # | Step | Expected |
|---|------|----------|
| 1 | Admin login → Review Pengumpulan → select task | All class students listed |
| 2 | Confirm submitted + **Belum Mengumpulkan** rows | Both visible |
| 3 | Open unsubmitted **Info** | No review form; no new DB row |
| 4 | Open submitted **Detail** → Buka Link | New tab, noopener |
| 5 | Save `ditinjau` then `revisi` + catatan | Flash success |
| 6 | Siswa login → sees **Perlu Revisi** + catatan guru | Phase 2 detail |
| 7 | Siswa resubmits | Status terkirim |
| 8 | Guru sets `selesai` + nilai | Saved |
| 9 | Siswa cannot edit submission | Block message |
| 10 | Guru outside pengampu opens `?etugas_id=` | Empty / no access |
| 11 | Guru in pengampu reviews admin-created task | Allowed |
| 12 | Siswa opens `/admin/etugas_pengumpulan.php` | Blocked / redirect |
| 13 | Staff non-guru opens review URL | 403 |
| 14 | POST update without CSRF | Error flash |
| 15 | Mobile width: scroll table, readable cards | UX OK |

---

## UI/UX/A11y notes

- Task filter required before table (prevents huge queries).
- Summary cards align with filter result set.
- **Detail** / **Info** buttons have text labels.
- Print button on detail page.
- Status badges use text labels.

---

## Security notes

- Authorization on task row (`ta_id`, `kelas_id`, `mapel_id`) via pengampu scope, not assignment owner id.
- `pengumpulan_id` in POST validated through fetch + `can_review` before UPDATE.
- No `siswa_id` from POST used for auth.
- CSRF on review POST.
- Output escaped with `etugas_h()`.

---

## Files changed in QA pass

| File | Change |
|------|--------|
| `tests/etugas_phase3a_qa_harness.php` | Optional CLI harness (dev only) |
| `docs/ai-agent-reports/2026-05-17-etugas-phase-3a-qa.md` | This report |
| `docs/deployment-manifests/2026-05-17-etugas-phase-3a-qa.md` | QA manifest |

**No production PHP fixes required.**

---

## Go/No-Go for Phase 4

**GO** — Proceed with export (Excel/PDF) after manual browser checklist on local/staging.

Prerequisites:

- Phase 3A files deployed (`2026-05-17-etugas-phase-3a-guru-review.md`)
- Manual sign-off on items 1–15 above

---

## Related

- `docs/deployment-manifests/2026-05-17-etugas-phase-3a-qa.md`
- `docs/ai-agent-reports/2026-05-17-etugas-phase-3a-guru-review.md`
