# Deployment Manifest — E-Tugas Phase 3A QA

| Field | Value |
|-------|--------|
| **Manifest ID** | `2026-05-17-etugas-phase-3a-qa` |
| **Type** | QA sign-off (no mandatory file changes) |
| **SQL import required** | **No** |

---

## 1. Purpose

Record Phase 3A QA results before Phase 4 (export). No production code patches required from QA.

---

## 2. New files to upload

None required for QA.

Optional dev-only:

- `tests/etugas_phase3a_qa_harness.php`

---

## 3. Existing files to replace

None for QA-only pass.

---

## 4. SQL files to import

**No SQL import required.**

---

## 5. Database changes

None.

Verified via CLI harness:

- LEFT JOIN list does not INSERT `etugas_pengumpulan` for belum rows.
- Review UPDATE affects single `pengumpulan_id` row.

---

## 6. Files NOT to upload

- `tests/etugas_phase3a_qa_harness.php` (optional)
- `docs/**` (documentation only)

---

## 7. Manual hosting deployment steps

If Phase 3A not yet on server, deploy per `2026-05-17-etugas-phase-3a-guru-review.md` first.

QA pass adds **no additional upload steps**.

Run manual browser checklist in `docs/ai-agent-reports/2026-05-17-etugas-phase-3a-qa.md`.

---

## 8. Rollback file steps

N/A (no QA file changes).

---

## 9. Rollback SQL

None.

---

## 10. Validation results

| Check | Result |
|-------|--------|
| `php -l` all Phase 3A + siswa Phase 2 files | PASS |
| `tests/etugas_phase3a_qa_harness.php` | 25/25 PASS |
| Authorization / LEFT JOIN / no INSERT | PASS |

---

## 11. Browser test results

Pending operator sign-off — use checklist in AI report § Browser test checklist.

Automated QA: **complete**.

---

## 12. Go/No-Go

**GO for Phase 4 (export)** after manual browser checklist.

---

## Related

- `docs/ai-agent-reports/2026-05-17-etugas-phase-3a-qa.md`
- `docs/deployment-manifests/2026-05-17-etugas-phase-3a-guru-review.md`
