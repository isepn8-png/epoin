# Deployment Manifest — E-Tugas Multi-Kelas Create

| Field | Value |
|-------|--------|
| **Manifest ID** | `2026-05-17-etugas-multikelas-create` |
| **Feature** | Buat tugas untuk banyak kelas sekaligus |
| **SQL import required** | **No** |
| **Date** | 2026-05-17 |

---

## 1. Purpose

Saat membuat tugas baru, admin/guru dapat memilih **beberapa kelas** dalam satu submit. Sistem membuat **satu baris `etugas` per kelas** dengan judul, mapel, deadline, dan pengaturan yang sama — tanpa perubahan skema database.

Siswa, review guru, dan rekap/CSV tetap berjalan seperti sebelumnya (satu tugas = satu kelas).

---

## 2. Files created

| Path |
|------|
| `docs/deployment-manifests/2026-05-17-etugas-multikelas-create.md` |
| `docs/ai-agent-reports/2026-05-17-etugas-multikelas-create.md` |

---

## 3. Existing files to replace

| Path | Change |
|------|--------|
| `includes/etugas_helpers.php` | Multi-kelas validation, duplicate check, batch insert |
| `admin/etugas_act.php` | Create handler uses `kelas_ids[]` + batch insert |
| `admin/etugas_form_inc.php` | Checkbox grid (create); single kelas (edit) |
| `admin/etugas_tambah.php` | Submit button label |
| `admin/etugas_edit.php` | Info note for per-kelas editing |

**Not changed:** `siswa/*`, `admin/etugas_pengumpulan*`, `admin/etugas_rekap*`, `admin/etugas_update.php`

---

## 4. SQL files to import

**No SQL import required.**

---

## 5. Database changes

None. Multiple classes = multiple rows in existing `etugas` table.

---

## 6. Files NOT to upload

- `.env`, credentials, full DB dumps
- `tests/*`, `docs/*` (optional)
- `*.zip`, `*.rar`, `*.bak`, `*.log`

---

## 7. Manual hosting deployment steps

1. Backup `includes/etugas_helpers.php`, `admin/etugas_act.php`, `admin/etugas_form_inc.php`, `admin/etugas_tambah.php`, `admin/etugas_edit.php`.
2. Upload/replace the five files above.
3. Clear OPcache if enabled.
4. Login admin → **Buat Tugas** → select multiple kelas → save.
5. Verify row count in `etugas` matches selected kelas.
6. Login guru → confirm scope-limited kelas checkboxes.
7. Run browser tests in AI report.

---

## 8. Rollback file steps

1. Restore backed-up copies of the five files.
2. No database rollback needed (data remains as inserted rows).

---

## 9. Validation results

| File | `php -l` |
|------|----------|
| `includes/etugas_helpers.php` | PASS |
| `admin/etugas_act.php` | PASS |
| `admin/etugas_form_inc.php` | PASS |
| `admin/etugas_tambah.php` | PASS |
| `admin/etugas_edit.php` | PASS |

---

## 10. Browser test results

| # | Test | Expected | Status |
|---|------|----------|--------|
| 1 | Admin → Buat Tugas | Checkbox grid visible | Manual |
| 2 | Select 1 kelas, save | 1 row in `etugas` | Manual |
| 3 | Select 3 kelas, save | 3 rows, same judul/mapel | Manual |
| 4 | Siswa in each class sees task | Yes | Manual |
| 5 | Siswa outside class | No task | Manual |
| 6 | Duplicate same judul/mapel/deadline/kelas | Skipped + message | Manual |
| 7 | Guru sees only scoped kelas | Yes | Manual |
| 8 | POST tamper unauthorized `kelas_ids[]` | Rejected | Manual |
| 9 | Review / rekap for new tasks | Works | Manual |
| 10 | Edit single task | Single kelas dropdown | Manual |

---

## Related

- `docs/ai-agent-reports/2026-05-17-etugas-multikelas-create.md`
