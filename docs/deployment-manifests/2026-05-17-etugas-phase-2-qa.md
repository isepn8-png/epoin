# Deployment Manifest — E-Tugas Phase 2 QA

| Field | Value |
|-------|--------|
| **Manifest ID** | `2026-05-17-etugas-phase-2-qa` |
| **Type** | QA follow-up (optional patch after Phase 2) |
| **SQL import required** | **No** |

---

## 1. Purpose

Document Phase 2 QA results and optional one-file fix before starting Phase 3 (guru review grid).

---

## 2. New files to upload

None required for production.

Optional dev-only (do **not** upload to shared hosting unless you want CLI QA):

- `tests/etugas_phase2_qa_harness.php`

---

## 3. Existing files to replace (optional QA fix)

| File | Reason |
|------|--------|
| `siswa/tugas_detail.php` | Avoid PHP warnings when opening invalid/missing task id |

If Phase 2 was never deployed, use the full Phase 2 manifest instead (`2026-05-17-etugas-phase-2-siswa-submit.md`).

---

## 4. SQL files to import

**No SQL import required.**

---

## 5. Database changes

None.

Verified:

- `UNIQUE KEY uq_etugas_siswa (etugas_id, siswa_id)` enforces one row per student per task.
- `INSERT … ON DUPLICATE KEY UPDATE` updates existing row on resubmit.

---

## 6. Files NOT to upload

- `tests/etugas_phase2_qa_harness.php` (optional)
- `docs/**` (documentation only)

---

## 7. Manual hosting deployment steps

**If Phase 2 already live:** replace only `siswa/tugas_detail.php` with QA-fixed version.

**If Phase 2 not yet live:** follow `2026-05-17-etugas-phase-2-siswa-submit.md` first, then apply this file if needed.

No database steps.

---

## 8. Rollback file steps

Restore previous `siswa/tugas_detail.php` from backup.

---

## 9. Rollback SQL

None.

---

## 10. Validation results

| Check | Result |
|-------|--------|
| `php -l` all Phase 2 PHP files | PASS |
| CLI harness `tests/etugas_phase2_qa_harness.php` | 26/26 PASS |
| Class scoping / draft / arsip / upsert | PASS (CLI + DB) |

---

## 11. Browser test results

Use checklist in `docs/ai-agent-reports/2026-05-17-etugas-phase-2-qa.md` § Browser test checklist.

Agent QA: automated checks complete; manual browser sign-off recommended on Laragon `:8088`.

---

## 12. Go/No-Go

**GO for Phase 3** after optional `tugas_detail.php` patch and manual browser checklist.

---

## Related

- `docs/ai-agent-reports/2026-05-17-etugas-phase-2-qa.md`
- `docs/deployment-manifests/2026-05-17-etugas-phase-2-siswa-submit.md`
