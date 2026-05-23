# E-Tugas — Full Manual Hosting Deployment Manifest

| Field | Value |
|-------|--------|
| **Manifest ID** | `2026-05-17-etugas-full-manual-hosting-deploy` |
| **Release** | E-Tugas / Pengumpulan Tugas Siswa |
| **Phases included** | 1A, 1A bugfix, 1B, 1B QA, 2, 2 QA, 3A, 3A QA, 4A, 4A QA, **Multi-kelas create**, **Safe Delete** (+ QA) |
| **Deploy method** | cPanel File Manager / FTP + phpMyAdmin |
| **CI/CD / SSH** | Not used |
| **Date** | 2026-05-17 (final package) |
| **Final recommendation** | **GO** — deploy as single package |

---

## Release

**E-Tugas / Pengumpulan Tugas Siswa**

Complete module: assignment management (including multi-kelas create), student text/link submission, guru/admin review, recap/CSV export, and safe permanent delete for empty tasks.

---

## Purpose

Modul untuk guru/admin membuat tugas, siswa mengumpulkan teks/link, guru mereview, admin/guru melihat rekap/export CSV, membuat tugas untuk banyak kelas sekaligus, dan menghapus tugas kosong dengan aman.

| Capability | Phase / feature |
|------------|-----------------|
| Database tables + shared helpers | 1A |
| Runtime table-check fix | 1A bugfix |
| Admin/guru CRUD (status/arsip, no hard delete by default) | 1B + 1B QA |
| Siswa task list + submit text/link | 2 + 2 QA |
| Review grid (LEFT JOIN roster) | 3A + 3A QA |
| Rekap + CSV export | 4A + 4A QA |
| **Multi-kelas create** (one row per kelas) | Multi-kelas |
| **Safe Delete** (empty tasks only) | Safe Delete |

---

## Multi-kelas create (no SQL)

- On **Buat Tugas**, admin/guru can select **multiple kelas** (`kelas_ids[]` checkbox grid).
- One submit creates **one `etugas` row per selected kelas** with the same `ta_id`, `mapel_id`, `judul`, `instruksi`, `deadline_at`, flags, and `status` — only `kelas_id` differs.
- Duplicate combinations (draft/aktif, same judul/deadline/kelas/mapel) are **skipped** with a message such as `3 tugas dibuat, 1 dilewati karena sudah ada.`
- **Edit** remains **single task / single kelas** per row (each batch-created class is a separate task record).
- **No SQL import** required for multi-kelas.

**Key files:** `admin/etugas_act.php`, `admin/etugas_form_inc.php`, `admin/etugas_tambah.php`, `includes/etugas_helpers.php`

---

## Safe Delete (no SQL)

- Admin/guru can **permanently delete** only tasks with **zero** rows in `etugas_pengumpulan`.
- Tasks with submissions **cannot** be deleted — use **Arsipkan** (`admin/etugas_status.php`).
- Delete handler: **`admin/etugas_hapus.php`** (POST only, CSRF).
- List UI: **`admin/etugas.php`** — **Hapus** (`btn-danger btn-xs`) when safe; muted hint *Sudah ada pengumpulan — gunakan Arsipkan* when not.
- **`includes/etugas_helpers.php` MUST include the QA atomic delete:**
  `DELETE FROM etugas WHERE etugas_id = ? AND NOT EXISTS (SELECT 1 FROM etugas_pengumpulan p WHERE p.etugas_id = etugas.etugas_id)`
- Does **not** delete or cascade `etugas_pengumpulan` rows.
- **No SQL import** required for Safe Delete.

---

## Prerequisites

1. **Backup** full hosting files and **export** full database (phpMyAdmin).
2. Confirm EPOIN **admin** and **siswa** login work.
3. PHP **7.4+** (8.x recommended), **mysqli** enabled.
4. Phase 1A SQL **not imported twice** — check `SHOW TABLES` first.
5. **`pengampu_mapel`** and **`kelas_siswa`** data ready for guru/siswa tests.
6. Do **not** upload `tests/`, `.env`, dumps, zip/rar, credentials.

---

## SQL Files to Import

### Exact import order

| Order | File |
|-------|------|
| **1** | `database/manual-migrations/2026-05-17-001-create-etugas-tables.sql` |

**Import once only** if `etugas` and `etugas_pengumpulan` do not exist.

### Per-phase SQL status

| Phase / feature | SQL import |
|-----------------|------------|
| **Phase 1A Foundation** | **Yes** — once |
| Phase 1A Bugfix | **No** |
| Phase 1B / 1B QA | **No** |
| Phase 2 / 2 QA | **No** |
| Phase 3A / 3A QA | **No** |
| Phase 4A / 4A QA | **No** |
| **Multi-kelas create** | **No** |
| **Safe Delete** | **No** |

---

## Production files to upload / replace

Upload or overwrite **all 19 PHP files** below (greenfield: 17 new + 2 header replacements).  
If upgrading from a partial deploy, treat every path as **replace** with backup.

### Complete list (required)

| Path |
|------|
| `includes/etugas_helpers.php` |
| `admin/header.php` |
| `siswa/header.php` |
| `admin/etugas.php` |
| `admin/etugas_tambah.php` |
| `admin/etugas_act.php` |
| `admin/etugas_edit.php` |
| `admin/etugas_update.php` |
| `admin/etugas_status.php` |
| `admin/etugas_hapus.php` |
| `admin/etugas_form_inc.php` |
| `admin/etugas_pengumpulan.php` |
| `admin/etugas_pengumpulan_detail.php` |
| `admin/etugas_pengumpulan_update.php` |
| `admin/etugas_rekap.php` |
| `admin/etugas_rekap_export_csv.php` |
| `siswa/tugas_saya.php` |
| `siswa/tugas_detail.php` |
| `siswa/tugas_kumpulkan.php` |

### By area

| Area | Files |
|------|--------|
| Shared | `includes/etugas_helpers.php` *(critical: final multi-kelas + atomic safe delete)* |
| Headers | `admin/header.php`, `siswa/header.php` |
| Kelola / create / edit | `etugas.php`, `etugas_tambah.php`, `etugas_act.php`, `etugas_edit.php`, `etugas_update.php`, `etugas_status.php`, **`etugas_hapus.php`**, `etugas_form_inc.php` |
| Review | `etugas_pengumpulan.php`, `etugas_pengumpulan_detail.php`, `etugas_pengumpulan_update.php` |
| Rekap | `etugas_rekap.php`, `etugas_rekap_export_csv.php` |
| Siswa | `tugas_saya.php`, `tugas_detail.php`, `tugas_kumpulkan.php` |

Flat list: `2026-05-17-etugas-full-changed-files.txt`

---

## Files NOT to Upload

| Category | Examples |
|----------|----------|
| Environment / secrets | `.env`, `.env.local`, credentials |
| Database dumps | Full `*.sql`, `*_local.sql`, `*.sql.gz` |
| Archives / backups | `*.zip`, `*.rar`, `*.bak`, `*.backup` |
| Logs | `*.log` |
| Dev tests | `tests/*` (all `etugas_*_qa_harness.php`) |
| Documentation | `docs/*` (optional on server) |
| Local config | Machine-specific config |
| Keys | Private keys, license keys |

Do not leave `database/manual-migrations/*.sql` in a public web folder; import via phpMyAdmin from your PC.

---

## Manual Deployment Steps

1. Schedule quiet maintenance window; notify operators.
2. Backup hosting files + export database.
3. `SHOW TABLES` for `etugas` / `etugas_pengumpulan` — import SQL **only if missing**.
4. Upload/replace all **19** production PHP files (see list above).
5. Clear OPcache if available.
6. Run `2026-05-17-etugas-post-deploy-checklist.md` (sections A–H).

---

## Post-Deploy Test Checklist (summary)

1. Import Phase 1A SQL once if needed.  
2. Admin creates task for **one** kelas.  
3. Admin creates task for **multiple** kelas.  
4. Confirm **one `etugas` row per selected kelas**.  
5. Admin **Hapus** empty test task.  
6. Cannot delete task **with** submissions (use Arsipkan).  
7. Siswa in selected classes see task.  
8. Siswa outside classes do not.  
9. Submit text/link.  
10. Guru review → revisi → resubmit → selesai + nilai.  
11. Rekap + CSV export.  
12. Guru `pengampu_mapel` scope.  
13. Siswa blocked from admin pages.  
14. No `tests/` on production.

Full detail: `2026-05-17-etugas-post-deploy-checklist.md`

---

## Rollback Plan

### File rollback

1. Restore backed-up **`admin/header.php`** and **`siswa/header.php`**.
2. Delete new E-Tugas PHP files if rolling back the module (all paths in production list except restored headers).
3. Restore previous copies of any replaced files from backup.

### Database rollback

**Preferred:** Restore full phpMyAdmin export from before deploy.

**Destructive (module removal only)** — after full backup:

```sql
-- DANGER: removes ALL e-Tugas assignments and submissions
DROP TABLE IF EXISTS etugas_pengumpulan;
DROP TABLE IF EXISTS etugas;
```

---

## Risks

| Risk | Mitigation |
|------|------------|
| PHP/MySQL version mismatch | PHP 7.4+; test on staging |
| OPcache stale files | Clear cache after upload |
| SQL imported twice | Check tables before import |
| Old `etugas_helpers.php` on server | Always deploy **final** helpers (multi-kelas + atomic delete) |
| Incomplete `pengampu_mapel` | Verify guru data before go-live |
| Multi-kelas creates many rows | Operator selects reasonable kelas count |
| Accidental delete of task with submissions | Server blocks delete; UI hides Hapus when pengumpulan > 0 |
| Large CSV export | Use filters on Rekap page |

---

## Validation Results

**`php -l` — 19 production PHP files (2026-05-17, local Laragon): ALL PASS**

| File | Result |
|------|--------|
| `includes/etugas_helpers.php` | No syntax errors |
| `admin/header.php` | No syntax errors |
| `siswa/header.php` | No syntax errors |
| `admin/etugas.php` | No syntax errors |
| `admin/etugas_tambah.php` | No syntax errors |
| `admin/etugas_act.php` | No syntax errors |
| `admin/etugas_edit.php` | No syntax errors |
| `admin/etugas_update.php` | No syntax errors |
| `admin/etugas_status.php` | No syntax errors |
| `admin/etugas_hapus.php` | No syntax errors |
| `admin/etugas_form_inc.php` | No syntax errors |
| `admin/etugas_pengumpulan.php` | No syntax errors |
| `admin/etugas_pengumpulan_detail.php` | No syntax errors |
| `admin/etugas_pengumpulan_update.php` | No syntax errors |
| `admin/etugas_rekap.php` | No syntax errors |
| `admin/etugas_rekap_export_csv.php` | No syntax errors |
| `siswa/tugas_saya.php` | No syntax errors |
| `siswa/tugas_detail.php` | No syntax errors |
| `siswa/tugas_kumpulkan.php` | No syntax errors |

**Automated QA harnesses (local only — do not upload `tests/`):**

| Harness | Result |
|---------|--------|
| `tests/etugas_phase2_qa_harness.php` | 26/26 PASS |
| `tests/etugas_phase3a_qa_harness.php` | 25/25 PASS |
| `tests/etugas_phase4a_qa_harness.php` | 48/48 PASS |
| `tests/etugas_multikelas_qa_harness.php` | 35/35 PASS |
| `tests/etugas_safe_delete_qa_harness.php` | 28/28 PASS |

---

## Final Go / No-Go

### **GO** — approved for manual hosting deployment

**Rationale:**

- Full module 1A–4A plus multi-kelas and safe delete QA-signed.
- **One** SQL migration (Phase 1A only).
- **19/19** production PHP files pass `php -l`.
- Safe delete uses atomic NOT EXISTS (QA-hardened).
- No open critical bugs in phase QA reports.

**Before go-live:**

- Full backup (files + database).
- Deploy **final** `includes/etugas_helpers.php`.
- Complete post-deploy checklist on production URL.

---

## Related deployment documents

| Document | Purpose |
|----------|---------|
| `2026-05-17-etugas-full-changed-files.txt` | Flat upload list |
| `2026-05-17-etugas-sql-import-order.md` | SQL import detail |
| `2026-05-17-etugas-post-deploy-checklist.md` | Operator sign-off |
| `2026-05-17-etugas-multikelas-create.md` | Multi-kelas feature |
| `2026-05-17-etugas-multikelas-create-qa.md` | Multi-kelas QA |
| `2026-05-17-etugas-safe-delete.md` | Safe Delete feature |
| `2026-05-17-etugas-safe-delete-qa.md` | Safe Delete QA |
| Phase manifests `2026-05-17-etugas-phase-*` | Per-phase detail |

---

## Suggested commit message (if using git locally)

```
docs(etugas): final manual hosting package incl. multi-kelas and safe delete

Update full deploy manifest, file list, SQL order, and post-deploy checklist.
19 production PHP files; single Phase 1A SQL migration.
```
