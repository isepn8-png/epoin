# Deployment Manifest — E-Tugas Multi-Kelas Create QA

| Field | Value |
|-------|--------|
| **Manifest ID** | `2026-05-17-etugas-multikelas-create-qa` |
| **Type** | QA sign-off |
| **SQL import required** | **No** |
| **Verdict** | **GO** |

---

## 1. Purpose

Record QA results for multi-kelas create before adding to the final hosting deployment package. No additional production file changes required from QA.

---

## 2. Files created (QA only)

| Path | Note |
|------|------|
| `tests/etugas_multikelas_qa_harness.php` | CLI — do **not** upload to production |
| `docs/ai-agent-reports/2026-05-17-etugas-multikelas-create-qa.md` | This QA report |
| `docs/deployment-manifests/2026-05-17-etugas-multikelas-create-qa.md` | This manifest |

---

## 3. Production files to deploy (from feature manifest)

Replace on server:

| Path |
|------|
| `includes/etugas_helpers.php` |
| `admin/etugas_act.php` |
| `admin/etugas_form_inc.php` |
| `admin/etugas_tambah.php` |
| `admin/etugas_edit.php` |

---

## 4. SQL import required

**No SQL import required.**

---

## 5. Database changes

None.

Verified via harness:

- Row count increases by number of non-duplicate selected kelas.
- Duplicate retry does not add rows.
- Shared columns match across batch rows; only `kelas_id` differs.

---

## 6. Files NOT to upload

- `tests/etugas_multikelas_qa_harness.php`
- `docs/**` (optional documentation only)

---

## 7. Manual hosting deployment steps

If multi-kelas feature not yet on server, follow `2026-05-17-etugas-multikelas-create.md` first.

QA adds **no extra steps** beyond that manifest.

After upload:

1. Clear OPcache if used.
2. Admin: create task for 2+ kelas → verify flash message and row count.
3. Guru: verify scoped kelas only.
4. Optional: `php tests/etugas_multikelas_qa_harness.php` on staging.

---

## 8. Rollback file steps

Restore backed-up copies of the five production files listed above.

No database rollback unless removing test data manually.

---

## 9. Validation results

| Check | Result |
|-------|--------|
| `php -l` all 5 production files | PASS |
| `tests/etugas_multikelas_qa_harness.php` | 35/35 PASS |
| CSRF on create | PASS (static) |
| Guru scope rejection | PASS |
| Duplicate skip | PASS |
| Siswa/review/rekap unchanged | PASS (grep) |

---

## 10. Browser test results

Automated QA complete. Manual sign-off: use checklist in  
`docs/ai-agent-reports/2026-05-17-etugas-multikelas-create-qa.md` § Browser test checklist.

---

## 11. Go/No-Go

**GO** — safe to include in final E-Tugas manual hosting deployment.

---

## Related

- `2026-05-17-etugas-multikelas-create.md` (feature deploy)
- `2026-05-17-etugas-full-manual-hosting-deploy.md` (full module bundle)
