# E-Tugas Phase 4A — Rekap & CSV Export Report

**Date:** 2026-05-17  
**Scope:** Recap page + CSV export (no PDF/XLSX, no file upload)

---

## Executive summary

| Item | Value |
|------|--------|
| **Status** | Implementation complete |
| **SQL** | No migration required |
| **Export format** | CSV only (`fputcsv`, UTF-8 BOM) |

---

## Deliverables

### Pages

| Page | Role |
|------|------|
| `admin/etugas_rekap.php` | Filters, 6 summary cards, paginated recap table |
| `admin/etugas_rekap_export_csv.php` | Authorized CSV download (no HTML) |

### Helpers (`includes/etugas_helpers.php`)

- `etugas_parse_rekap_filters()` — validated filter input
- `etugas_rekap_list_ready()` — requires `etugas_id` OR `kelas_id` + `mapel_id`
- `etugas_rekap_can_access_filters()` — pengampu scope / admin
- `etugas_list_rekap_rows()` — single task via `etugas_list_review_rows`, or kelas+mapel multi-task query
- `etugas_rekap_summary()` — includes `terlambat` + `total_siswa` (unique)
- `etugas_rekap_status_text()`, `etugas_rekap_row_to_csv()`, `etugas_send_rekap_csv()`

### Menu

- `admin/header.php` — **Rekap Tugas** link (admin/guru only)

---

## Access model

Same as Phase 3A review:

- **Admin:** all recap data
- **Guru:** `etugas_user_can_review` via `pengampu_mapel` (not `etugas.guru_user_id` alone)
- **Siswa / others:** blocked by `etugas_admin_context()`

---

## Data model

- **Single task:** reuses Phase 3A `LEFT JOIN` (all class students, belum + submitted)
- **Kelas + mapel (no task):** joins all tasks in scope for that class/mapel
- **No INSERT** for belum students

---

## CSV export

- Filename: `etugas-rekap-YYYYMMDD-HHMM.csv`
- Headers: `Content-Type: text/csv; charset=UTF-8`
- UTF-8 BOM for Excel
- Columns: No, NIS, Nama, Kelas, Mapel, Judul, Deadline, Status, Waktu Kumpul, Terlambat, Nilai, Catatan Guru, Link URL
- `ob_end_clean()` before output — no stray HTML

---

## Manual browser checklist

1. Admin → Rekap Tugas → filter by task → summary + table  
2. Confirm submitted + **Belum Mengumpulkan** rows  
3. Export CSV → open in Excel — readable UTF-8  
4. Guru in pengampu → can view/export  
5. Guru outside scope → denied  
6. Siswa URL → blocked  
7. Export file has no HTML/error text  

---

## Suggested commit message

```
feat(etugas): Phase 4A rekap page and CSV export

Add etugas_rekap with filters, summary cards, LEFT JOIN roster,
and etugas_rekap_export_csv with UTF-8 BOM. Reuse pengampu review scope.
No schema changes.
```
