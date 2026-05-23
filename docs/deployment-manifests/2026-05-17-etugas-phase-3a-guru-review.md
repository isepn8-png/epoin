# Deployment Manifest — E-Tugas Phase 3A (Guru Review)

| Field | Value |
|-------|--------|
| **Manifest ID** | `2026-05-17-etugas-phase-3a-guru-review` |
| **Type** | Feature — admin/guru review grid |
| **SQL import required** | **No** |
| **Prerequisite** | Phase 1A tables, Phase 1B admin CRUD, Phase 2 siswa submit |

---

## 1. Purpose

Enable admin/guru to review student text/link submissions per task, including students who have **not** submitted (LEFT JOIN on class roster).

---

## 2. New files to upload

| File | Description |
|------|-------------|
| `admin/etugas_pengumpulan.php` | Review dashboard (filters, summary, student table) |
| `admin/etugas_pengumpulan_detail.php` | Submission detail + review form |
| `admin/etugas_pengumpulan_update.php` | POST handler for review updates |

---

## 3. Existing files to replace

| File | Description |
|------|-------------|
| `includes/etugas_helpers.php` | Phase 3A review helpers |
| `admin/etugas.php` | "Lihat Pengumpulan" action per task |
| `admin/header.php` | Menu: Kelola Tugas + Review Pengumpulan |

---

## 4. SQL files to import

**No SQL import required for Phase 3A.**

---

## 5. Database changes

None. Uses existing `etugas` and `etugas_pengumpulan` (including `reviewed_by`, `reviewed_at`, `nilai`, `catatan_guru`).

---

## 6. Files NOT to upload

- `docs/**` (optional)
- `tests/**` (dev harness only)
- `.env`, export scripts (not in this phase)

---

## 7. Manual hosting deployment steps

1. Backup replaced files.
2. Upload new admin review pages (3 files).
3. Replace `includes/etugas_helpers.php`, `admin/etugas.php`, `admin/header.php`.
4. Confirm Phase 1A migration already applied.
5. Login admin → open **Review Pengumpulan** → select task → verify all class students listed.
6. Review a submission → save status `ditinjau` / `revisi` / `selesai`.

---

## 8. Rollback file steps

1. Restore backups of modified files.
2. Delete the three new `admin/etugas_pengumpulan*.php` files if uploaded.

---

## 9. Rollback SQL

None.

---

## 10. Validation results

| Check | Result |
|-------|--------|
| `php -l includes/etugas_helpers.php` | PASS |
| `php -l admin/etugas.php` | PASS |
| `php -l admin/etugas_pengumpulan.php` | PASS |
| `php -l admin/etugas_pengumpulan_detail.php` | PASS |
| `php -l admin/etugas_pengumpulan_update.php` | PASS |

---

## 11. Browser test results

Use checklist in `docs/ai-agent-reports/2026-05-17-etugas-phase-3a-guru-review.md` (18 steps). Manual sign-off on Laragon `:8088` recommended.

---

## Related

- `docs/ai-agent-reports/2026-05-17-etugas-phase-3a-guru-review.md`
- `docs/deployment-manifests/2026-05-17-etugas-phase-2-qa.md`
