# Deployment Manifest — E-Tugas Phase 4A (Rekap & CSV Export)

| Field | Value |
|-------|--------|
| **Manifest ID** | `2026-05-17-etugas-phase-4a-rekap-export-csv` |
| **Type** | Feature — recap page + CSV export |
| **SQL import required** | **No** |
| **Prerequisite** | Phase 1A–3A deployed |

---

## 1. Purpose

Admin/guru can view a filtered recap of student submissions per task (or per kelas+mapel across tasks) and export results to CSV for Excel/LibreOffice.

---

## 2. New files to upload

| File | Description |
|------|-------------|
| `admin/etugas_rekap.php` | Rekap dashboard, filters, summary, table |
| `admin/etugas_rekap_export_csv.php` | CSV download handler |

---

## 3. Existing files to replace

| File | Description |
|------|-------------|
| `includes/etugas_helpers.php` | Phase 4A rekap/CSV helpers |
| `admin/header.php` | Menu: Rekap Tugas |

---

## 4. SQL files to import

**No SQL import required for Phase 4A.**

---

## 5. Database changes

None.

---

## 6. Files NOT to upload

- `docs/**` (optional)
- `tests/**` (dev harness if present)
- `.env`

---

## 7. Manual hosting deployment steps

1. Backup `includes/etugas_helpers.php` and `admin/header.php`.
2. Upload `admin/etugas_rekap.php` and `admin/etugas_rekap_export_csv.php`.
3. Replace `includes/etugas_helpers.php` and `admin/header.php`.
4. Login admin → **Rekap Tugas** → select task → verify table + export CSV.
5. Login guru in pengampu scope → verify access; guru outside scope denied.

---

## 8. Rollback file steps

1. Restore backed-up `includes/etugas_helpers.php` and `admin/header.php`.
2. Delete `admin/etugas_rekap.php` and `admin/etugas_rekap_export_csv.php`.

---

## 9. Rollback SQL

None.

---

## 10. Validation results

| Check | Result |
|-------|--------|
| `php -l` Phase 4A files | PASS (run after deploy) |

---

## 11. Browser test results

See checklist in `docs/ai-agent-reports/2026-05-17-etugas-phase-4a-rekap-export-csv.md`.

---

## Related

- `docs/ai-agent-reports/2026-05-17-etugas-phase-4a-rekap-export-csv.md`
- `docs/deployment-manifests/2026-05-17-etugas-phase-3a-guru-review.md`
