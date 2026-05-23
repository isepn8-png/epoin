# AI Agent Report — e-Tugas Phase 4A QA

**Date:** 2026-05-17  
**Phase:** 4A — Rekap Pengumpulan Tugas & CSV Export  
**Role:** Senior PHP QA Engineer, Security Reviewer, Export Reviewer, UI/UX Reviewer  
**Verdict:** ✅ GO — no critical bugs found; all 48 automated tests pass

---

## 1. QA Findings

### QA-1: Admin access to all recap data
- `etugas_admin_context()` calls `etugas_require_access()` which enforces `is_admin || is_guru` via `$_SESSION`.
- `etugas_user_can_review()` returns `true` unconditionally for `$ctx['is_admin'] === true`.
- `etugas_rekap_can_access_filters()` and `etugas_list_rekap_rows()` both delegate to `etugas_user_can_review()`.
- **Result: PASS** — admin sees all data, no scope restriction.

### QA-2: Guru access restricted to pengampu_mapel scope
- `etugas_get_guru_scope()` fetches TA/kelas/mapel combos from `pengampu_mapel` for the current `user_id`.
- `etugas_scope_has()` enforces that the requested combo exists in the guru's scope.
- `etugas_build_review_scope_sql()` adds an `INNER JOIN pengampu_mapel` restriction to the SQL when `is_guru = true`.
- Automated test: `guru out-of-scope gets empty rekap` → PASS.
- **Result: PASS**

### QA-3: Recap does NOT rely on etugas.guru_user_id
- `etugas_user_can_review()` checks only `pengampu_mapel` (TA + kelas + mapel), never `etugas.guru_user_id`.
- Automated test: `guru scope does NOT use guru_user_id` → PASS.
- **Result: PASS**

### QA-4: Siswa/non-guru cannot access rekap pages
- `etugas_require_access()` blocks any session where `!is_admin && !is_guru`, returns HTTP 403 and `die()`.
- Both `admin/etugas_rekap.php` and `admin/etugas_rekap_export_csv.php` call `etugas_admin_context()` which wraps `etugas_require_access()`.
- **Result: PASS**

### QA-5: All filters function correctly
- `etugas_parse_rekap_filters()` validates and casts all six filter keys (`ta_id`, `kelas_id`, `mapel_id`, `etugas_id`, `sub_status`, `q`).
- Invalid `sub_status` values are cleared to `''`.
- `belum` and `terlambat` are accepted as special status values alongside standard statuses.
- Automated tests: 6 parsing tests → all PASS.
- **Result: PASS**

### QA-6: Conditional data loading
- `etugas_rekap_list_ready()` returns `true` only when `etugas_id > 0` OR (`kelas_id > 0` AND `mapel_id > 0`).
- Page displays empty state prompt until condition is met; export also checks this before running.
- Automated tests: 5 list_ready tests → all PASS.
- **Result: PASS**

### QA-7: LEFT JOIN roster — no phantom rows
- `etugas_list_rekap_rows_kelas_mapel()` uses `LEFT JOIN etugas_pengumpulan p ON p.etugas_id = e.etugas_id AND p.siswa_id = s.siswa_id`.
- When `p.pengumpulan_id IS NULL`, `has_submission` is set to `false` and status displays as "Belum Mengumpulkan".
- No `INSERT` or `UPDATE` occurs in any rekap fetch path.
- Automated DB test: `rekap does not insert pengumpulan rows` → PASS.
- **Result: PASS**

### QA-8: Summary cards
- `etugas_rekap_summary()` counts: `total` (rows), `total_siswa` (unique `siswa_id`), `sudah`, `belum`, `revisi`, `selesai`, `terlambat` (rows where `has_submission && is_terlambat`).
- HTML uses `$summary['total_siswa'] ?? $summary['total']` for the Total Siswa card.
- Automated tests: 7 summary tests → all PASS.
- **Result: PASS**

### QA-9: CSV export details
- `etugas_rekap_export_csv.php` checks tables ready → filter ready → access → then calls `etugas_send_rekap_csv()`.
- `etugas_send_rekap_csv()` calls `ob_end_clean()` loop, sets `Content-Type: text/csv; charset=UTF-8`, `Content-Disposition: attachment`, writes UTF-8 BOM (`\xEF\xBB\xBF`), then uses `fputcsv()`.
- Same `etugas_list_rekap_rows()` function used by both page and export — filter parity confirmed.
- Automated tests: BOM present, headers correct, no HTML tags → all PASS.
- **Result: PASS**

### QA-10: CSV column headers and order
- `etugas_rekap_csv_headers()` returns exactly 13 columns in order:  
  `No, NIS, Nama Siswa, Kelas, Mapel, Judul Tugas, Deadline, Status Pengumpulan, Waktu Kumpul, Terlambat, Nilai, Catatan Guru, Link URL`
- `etugas_rekap_row_to_csv()` returns 13 values in the same order.
- Automated tests: column count, header[0], header[7], header[12], row column count → all PASS.
- **Result: PASS**

### QA-11: No whitespace/output before CSV headers
- `etugas_rekap_export_csv.php` opens with `<?php` (no BOM, no trailing whitespace), calls `session_start()` at line 5.
- `etugas_send_rekap_csv()` calls `while (ob_get_level()) { ob_end_clean(); }` before sending headers.
- **Result: PASS**

### QA-12: Prepared statements throughout
- All DB queries in Phase 4A helpers use `mysqli_prepare` + `mysqli_stmt_bind_param` + `mysqli_stmt_execute`.
- No string interpolation of user input into SQL found.
- `etugas_bind_params()` helper used for dynamic param arrays.
- **Result: PASS**

### QA-13: No raw DB errors shown to users
- All `mysqli_prepare` failure paths call `error_log(...)` and return `[]` or `false`.
- Flash redirects with generic messages are used in the export handler.
- **Result: PASS**

### QA-14: Table responsiveness and filter labels
- Recap table is wrapped in `<div class="box-body table-responsive">`.
- All six filter selects have matching `<label for="...">` elements.
- CSS includes `@media (max-width:768px)` override for `.table-responsive`.
- **Result: PASS**

---

## 2. Bugs Found

| ID | Severity | Description | Status |
|----|----------|-------------|--------|
| — | — | No bugs found | — |

No critical or blocking bugs were identified.

---

## 3. Files Changed

No production files were modified during QA.

**New file created:**
- `tests/etugas_phase4a_qa_harness.php` — 48-test CLI harness for Phase 4A

---

## 4. SQL Import Required

**No SQL import required.** Phase 4A is read-only for the database; no schema changes, no new tables, no new rows inserted.

---

## 5. Validation Results

| File | `php -l` |
|------|---------|
| `includes/etugas_helpers.php` | No syntax errors |
| `admin/header.php` | No syntax errors |
| `admin/etugas_rekap.php` | No syntax errors |
| `admin/etugas_rekap_export_csv.php` | No syntax errors |

---

## 6. CSV Export Test Results

| Test | Result |
|------|--------|
| UTF-8 BOM present (`\xEF\xBB\xBF`) | PASS |
| `Content-Type: text/csv; charset=UTF-8` header | PASS (code review) |
| `Content-Disposition: attachment` header | PASS (code review) |
| Filename format `etugas-rekap-YYYYMMDD-HHMM.csv` | PASS |
| 13-column header row correct order | PASS |
| No HTML tags in output | PASS |
| BOM + header before any data row | PASS |
| `fputcsv` used for all rows | PASS (code review) |
| `ob_end_clean()` loop before headers | PASS (code review) |
| Guru scope enforced in export | PASS (automated: out-of-scope → empty) |
| Filter parity: `belum` filter reduces rows | PASS |

---

## 7. Browser Test Checklist

| # | Scenario | Expected | Status |
|---|----------|----------|--------|
| B1 | Login as siswa, open `admin/etugas_rekap.php` | 403 Forbidden | Manual |
| B2 | Login as non-logged-in user | Redirect to login | Manual |
| B3 | Login as admin, open rekap with no filters | Empty state prompt shown | Manual |
| B4 | Admin selects etugas_id, clicks "Tampilkan Rekap" | Table with submitted + unsubmitted rows | Manual |
| B5 | Admin selects kelas + mapel (no etugas_id) | Multi-task table shown | Manual |
| B6 | Admin filters status=belum | Only rows with no submission shown | Manual |
| B7 | Admin searches NIS "2324" | Filtered rows shown | Manual |
| B8 | Admin clicks "Export CSV" | File downloads, opens in Excel with BOM | Manual |
| B9 | Guru logs in with pengampu_mapel scope | Only in-scope tasks visible | Manual |
| B10 | Guru tries to access out-of-scope etugas_id via URL | "Tidak memiliki akses" shown | Manual |
| B11 | Guru clicks "Export CSV" for in-scope task | CSV downloaded, respects scope | Manual |
| B12 | Confirm no new rows in `etugas_pengumpulan` after rekap | DB count unchanged | Manual |
| B13 | Mobile viewport (< 768 px) | Table scrollable, filters stack | Manual |
| B14 | Pagination with > 50 rows | Page links work, numbering continues | Manual |

---

## 8. UI/UX / A11y Notes

- All filter inputs have `<label for="...">` — a11y compliant.
- Empty state uses `role="status"` for screen-reader compatibility.
- Pagination `<nav>` has `aria-label="Paginasi rekap"`.
- Status text is in plain text (not color-only): "Belum Mengumpulkan", "Perlu Revisi", "Selesai" etc.
- "Terlambat" column uses `<span class="label label-warning">Ya</span>` (text + color) — acceptable.
- Export button only rendered when `$canAccess && !empty($allRows)` — prevents confusing empty export.
- Helper text under Tugas filter guides user on filter requirement.
- `link-cell` uses CSS truncation with full URL in `title=""` tooltip — good UX for long URLs.

---

## 9. Security Notes

- **Access control:** `etugas_require_access()` enforces `is_admin || is_guru` at the start of both pages; siswa/guests get HTTP 403.
- **Scope isolation:** Guru export path calls `etugas_rekap_can_access_filters()` which re-validates scope server-side; it cannot be bypassed via URL manipulation.
- **SQL injection:** All user-supplied filter values are bound via `mysqli_stmt_bind_param`; no interpolation.
- **XSS:** All HTML output uses `etugas_h()` (`htmlspecialchars(ENT_QUOTES, 'UTF-8')`). Link URLs additionally were validated at submission time via `etugas_is_safe_submission_link()` (blocks `javascript:`, `data:`, `file:`, `vbscript:`).
- **Output stream:** `ob_end_clean()` loop in `etugas_send_rekap_csv()` prevents stray HTML/warnings from leaking into the CSV file.
- **No raw DB errors:** All DB errors are `error_log()`-only; user sees only generic flash messages.
- **CSRF:** No POST operations on these pages (GET only for filters and export) — CSRF not applicable.

---

## 10. Go/No-Go Recommendation

**GO ✅**

All 48 automated harness tests pass. All four Phase 4A files pass `php -l`. No critical bugs found. Access control, prepared statements, CSV BOM/headers, scope enforcement, LEFT JOIN integrity, and summary card logic are all verified. The module is ready for inclusion in the final manual hosting deployment package.

**Pre-deployment checklist:**
- [ ] Run `tests/etugas_phase4a_qa_harness.php` on the production server after upload
- [ ] Verify `pengampu_mapel` data is populated for all guru accounts
- [ ] Confirm MySQL user has `SELECT` permissions on all e-Tugas related tables
- [ ] Confirm `php.ini` `output_buffering` setting does not conflict with CSV export (or that `ob_end_clean()` loop handles it)
