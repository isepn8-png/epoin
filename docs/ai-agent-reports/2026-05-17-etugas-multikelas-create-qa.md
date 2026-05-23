# AI Agent Report — E-Tugas Multi-Kelas Create QA

**Date:** 2026-05-17  
**Feature:** Multi-kelas assignment create  
**Verdict:** **GO** — no critical bugs; 35/35 automated tests pass

---

## 1. QA Findings

| # | Scope | Result | Evidence |
|---|-------|--------|----------|
| 1 | Single kelas create | PASS | DB: `created === 1`, row count +1 |
| 2 | Multi-kelas insert one row per kelas | PASS | DB: 2 kelas → +2 rows |
| 3 | Shared fields identical | PASS | SQL compare `ta_id`, `mapel_id`, `judul`, `instruksi`, `deadline_at`, flags, `status` |
| 4 | Only `kelas_id` differs | PASS | Two-row compare |
| 5 | Duplicate skip + message | PASS | Retry → `created=0`, `skipped=N`; message `N dilewati karena sudah ada.` |
| 5b | Partial duplicate | PASS | 1 new kelas + 2 skipped |
| 6 | Transaction rollback on failure | PASS (code review) | `mysqli_rollback` on `execute` failure before commit |
| 7 | Admin multi-kelas UI | PASS | Checkbox grid, `kelas_ids[]` |
| 8 | Guru scope per kelas | PASS | Validation rejects mixed authorized/unauthorized batch |
| 9 | POST tampering | PASS | Non-integer dropped; out-of-scope rejected server-side |
| 10 | At least one kelas required | PASS | `kelas_ids` error when empty |
| 11 | Edit single-kelas only | PASS | `name="kelas_id"` when `$isEdit`; no `kelas_ids[]` on edit |
| 12 | Siswa visibility | PASS (design) | Unchanged siswa pages; per-row `kelas_id` scoping unchanged |
| 13 | Review for generated tasks | PASS (design) | `etugas_pengumpulan.php` unchanged |
| 14 | Rekap/CSV for generated tasks | PASS (design) | `etugas_rekap.php` unchanged |
| 15 | No schema migration | PASS | No new SQL files |
| 16 | No siswa/review/rekap code changes | PASS | Static grep: no `kelas_ids` in those files |

---

## 2. Bugs Found

| ID | Severity | Description | Status |
|----|----------|-------------|--------|
| — | — | None | — |

**Non-blocking notes:**

- Help text wording: *"membuat tugas yang sama untuk setiap kelas"* (acceptable; meaning matches spec).
- Transaction rollback not simulated in CLI (logic verified by code inspection).

---

## 3. Files Changed During QA

| File | Change |
|------|--------|
| `tests/etugas_multikelas_qa_harness.php` | **Created** — 35 automated tests |

No production PHP files modified during QA.

---

## 4. SQL Import Required

**No SQL import required.**

---

## 5. Validation Results

| File | `php -l` |
|------|----------|
| `includes/etugas_helpers.php` | PASS |
| `admin/etugas_act.php` | PASS |
| `admin/etugas_form_inc.php` | PASS |
| `admin/etugas_tambah.php` | PASS |
| `admin/etugas_edit.php` | PASS |

**CLI harness:** `tests/etugas_multikelas_qa_harness.php` → **35 passed, 0 failed**

---

## 6. Browser Test Checklist

| # | Test | Expected | Status |
|---|------|----------|--------|
| B1 | Admin → Buat Tugas | Checkbox grid, help text | Manual |
| B2 | Select 1 kelas, submit | 1 row; success flash | Manual |
| B3 | Select 3 kelas, submit | 3 rows; `3 tugas dibuat.` | Manual |
| B4 | Siswa in class A,B,C each see task | Yes | Manual |
| B5 | Siswa in other class | No task | Manual |
| B6 | Re-submit same judul/mapel/deadline/kelas | Skip message | Manual |
| B7 | Pilih semua / Hapus pilihan | Checkboxes update | Manual |
| B8 | Selected count updates | `N kelas dipilih` | Manual |
| B9 | Guru: only scoped kelas visible | Yes | Manual |
| B10 | Guru POST tamper `kelas_ids[]=99999` | Rejected | Manual |
| B11 | Edit page | Single kelas + info note | Manual |
| B12 | Review grid for batch tasks | All students listed | Manual |
| B13 | Rekap + CSV export | Works per task | Manual |

---

## 7. UI/UX / A11y Notes

- **Kelas tujuan** label with required marker and multi-select help text.
- Toolbar: **Pilih semua** / **Hapus pilihan** (vanilla JS).
- `aria-live="polite"` on selection count.
- `role="group"` + `sr-only` legend on checkbox panel.
- Responsive grid (`@media max-width 768px`).
- Edit: info alert + help block explaining per-kelas records.

---

## 8. Security Notes

- `etugas_verify_csrf()` on `etugas_act.php` before processing.
- `etugas_validate_assignment_create()` validates every `kelas_id` (TA membership + guru scope).
- `etugas_assignment_is_duplicate()` and batch `INSERT` use `mysqli_prepare`.
- Invalid POST values coerced/dropped (`(int)` cast, `> 0` filter).
- DB errors logged; user sees generic flash messages.
- Admin: no scope limit (by design); guru: strict `pengampu_mapel` per kelas.

---

## 9. Go/No-Go Recommendation

### **GO** for final manual hosting deployment package

Include multi-kelas files in deploy bundle:

1. `includes/etugas_helpers.php`
2. `admin/etugas_act.php`
3. `admin/etugas_form_inc.php`
4. `admin/etugas_tambah.php`
5. `admin/etugas_edit.php`

Run `tests/etugas_multikelas_qa_harness.php` on staging after upload (optional). Complete browser checklist B1–B13 on production URL before sign-off.
