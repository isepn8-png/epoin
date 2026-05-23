# Deployment Manifest — e-Tugas Phase 4A QA

**Date:** 2026-05-17  
**Phase:** 4A QA — Rekap Pengumpulan Tugas & CSV Export  
**Environment:** Laragon local → production shared hosting  
**Verdict:** GO ✅ — Approved for final manual hosting deployment

---

## Files to Upload (Phase 4A — all)

The following files must be uploaded to the production server via FTP/cPanel File Manager. These are the complete set of files created or modified in Phase 4A implementation plus QA.

### New files (Phase 4A implementation)

| Local path | Upload to (relative to project root) |
|-----------|--------------------------------------|
| `admin/etugas_rekap.php` | `admin/etugas_rekap.php` |
| `admin/etugas_rekap_export_csv.php` | `admin/etugas_rekap_export_csv.php` |

### Modified files (Phase 4A)

| Local path | Upload to (relative to project root) |
|-----------|--------------------------------------|
| `includes/etugas_helpers.php` | `includes/etugas_helpers.php` |
| `admin/header.php` | `admin/header.php` |

### QA test file (optional — do not upload to production)

| Local path | Note |
|-----------|------|
| `tests/etugas_phase4a_qa_harness.php` | CLI QA only, not needed in production |

---

## Files to Upload (Prior phases — ensure these are already deployed)

If Phases 2, 3A were already deployed, no re-upload needed. Verify with the Phase 3A manifest. The following must already be present on production:

- `siswa/tugas_saya.php`
- `siswa/tugas_detail.php`
- `siswa/tugas_kumpulkan.php`
- `siswa/header.php`
- `admin/etugas.php`
- `admin/etugas_pengumpulan.php`
- `admin/etugas_pengumpulan_detail.php`
- `admin/etugas_pengumpulan_update.php`

---

## SQL Import Required

**No SQL import required.**  
Phase 4A does not create or modify any database tables or rows. All data is read-only.

---

## Pre-Upload Validation

```
php -l includes/etugas_helpers.php         → No syntax errors
php -l admin/header.php                    → No syntax errors
php -l admin/etugas_rekap.php              → No syntax errors
php -l admin/etugas_rekap_export_csv.php   → No syntax errors
```

---

## Post-Upload Verification Steps

1. **Access control check:**
   - Log in as a siswa account → navigate to `admin/etugas_rekap.php` → expect 403 Forbidden.
   - Log in as non-admin/non-guru → same result.

2. **Admin recap check:**
   - Log in as admin → click "Rekap Tugas" in sidebar menu.
   - Select a TA and a specific task from the dropdown → click "Tampilkan Rekap".
   - Verify table shows both submitted and unsubmitted students.
   - Verify summary cards show correct counts.

3. **CSV export check:**
   - With a filtered recap loaded, click "Export CSV".
   - Verify file downloads with name format `etugas-rekap-YYYYMMDD-HHMM.csv`.
   - Open in Excel/LibreOffice → verify:
     - No BOM character shown as first-column garbage.
     - First row: `No, NIS, Nama Siswa, Kelas, Mapel, Judul Tugas, Deadline, Status Pengumpulan, Waktu Kumpul, Terlambat, Nilai, Catatan Guru, Link URL`.
     - Data rows correspond to the filtered table.

4. **Guru scope check:**
   - Log in as a guru with known `pengampu_mapel` entries.
   - Verify only in-scope kelas/mapel/TA appear in filter dropdowns.
   - Manually craft a URL with out-of-scope `etugas_id` → verify access denied message.

5. **DB integrity check (optional):**
   ```sql
   SELECT COUNT(*) FROM etugas_pengumpulan;
   ```
   Run before and after loading the recap page. Count must not change.

---

## Rollback Plan

If issues are found after deployment:

1. Restore the previous `includes/etugas_helpers.php` from backup.
2. Restore previous `admin/header.php` from backup.
3. Delete `admin/etugas_rekap.php` and `admin/etugas_rekap_export_csv.php`.
4. No database rollback needed (no schema changes).

---

## Automated QA Summary

```
tests/etugas_phase4a_qa_harness.php
Total: 48 passed, 0 failed
```

All tests passed on local Laragon environment (Apache 8088, MySQL 3308, PHP 8.x, DB: epoin_local).

---

## Sign-off

- [x] `php -l` all Phase 4A files — PASS  
- [x] Automated harness 48/48 — PASS  
- [x] Access control verified (admin, guru scope, siswa blocked)  
- [x] No SQL migration required  
- [x] CSV BOM, headers, fputcsv, ob_end_clean verified  
- [x] Prepared statements throughout  
- [x] No raw DB errors exposed  
- [x] LEFT JOIN integrity — no phantom inserts  
- [x] Summary cards accurate (total_siswa unique count)  
- [x] Responsive table + labeled filters  

**Approved for deployment.**
