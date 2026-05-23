# E-Tugas — Post-Deploy Checklist (Final Package)

**Date:** 2026-05-17  
**Scope:** Phases 1A–4A, Multi-kelas create, Safe Delete  
**Environment:** Shared hosting / cPanel / FTP + phpMyAdmin

Print and mark each item during deployment sign-off.

---

## A. Pre-flight (before upload)

| # | Check | Done |
|---|--------|------|
| A1 | Full **file backup** of hosting document root | ☐ |
| A2 | Full **database export** from phpMyAdmin | ☐ |
| A3 | EPOIN **admin** login works | ☐ |
| A4 | EPOIN **siswa** login works | ☐ |
| A5 | PHP **7.4+** (8.x recommended); **mysqli** enabled | ☐ |
| A6 | `etugas` / `etugas_pengumpulan` absent OR migration already applied **once** | ☐ |
| A7 | `pengampu_mapel` populated for guru test accounts | ☐ |
| A8 | Upload package has **no** `tests/` files | ☐ |
| A9 | No `.env`, dumps, zip/rar, credentials in upload | ☐ |
| A10 | `includes/etugas_helpers.php` is **final** (multi-kelas + atomic safe delete) | ☐ |

---

## B. Deploy execution

| # | Check | Done |
|---|--------|------|
| B1 | Import Phase 1A SQL **once** if tables missing | ☐ |
| B2 | `SHOW TABLES` confirms `etugas`, `etugas_pengumpulan` | ☐ |
| B3 | All **17** new PHP files uploaded (see changed-files list) | ☐ |
| B4 | `admin/header.php` replaced (backup first) | ☐ |
| B5 | `siswa/header.php` replaced (backup first) | ☐ |
| B6 | OPcache / hosting cache cleared | ☐ |
| B7 | No `tests/` on production web root | ☐ |

---

## C. Syntax validation (`php -l`)

| File | Done |
|------|------|
| `includes/etugas_helpers.php` | ☐ |
| `admin/header.php` | ☐ |
| `siswa/header.php` | ☐ |
| `admin/etugas.php` | ☐ |
| `admin/etugas_tambah.php` | ☐ |
| `admin/etugas_act.php` | ☐ |
| `admin/etugas_edit.php` | ☐ |
| `admin/etugas_update.php` | ☐ |
| `admin/etugas_status.php` | ☐ |
| `admin/etugas_hapus.php` | ☐ |
| `admin/etugas_form_inc.php` | ☐ |
| `admin/etugas_pengumpulan.php` | ☐ |
| `admin/etugas_pengumpulan_detail.php` | ☐ |
| `admin/etugas_pengumpulan_update.php` | ☐ |
| `admin/etugas_rekap.php` | ☐ |
| `admin/etugas_rekap_export_csv.php` | ☐ |
| `siswa/tugas_saya.php` | ☐ |
| `siswa/tugas_detail.php` | ☐ |
| `siswa/tugas_kumpulkan.php` | ☐ |

**Local validation (2026-05-17):** **19/19 PASS**

---

## D. Functional tests (browser — required)

| # | Test | Expected | Done |
|---|------|----------|------|
| 1 | Import Phase 1A SQL once (if needed) | Tables exist | ☐ |
| 2 | Admin creates task for **one** kelas | 1 row in `etugas` | ☐ |
| 3 | Admin creates task for **multiple** kelas | N rows (1 per kelas); flash e.g. `3 tugas dibuat` | ☐ |
| 4 | Confirm **one `etugas` row per selected kelas** | Same judul/mapel; different `kelas_id` | ☐ |
| 5 | Admin **Hapus** empty test task | Row removed; success flash | ☐ |
| 6 | Task **with** pengumpulan — no Hapus / POST blocked | Use Arsipkan; message about pengumpulan | ☐ |
| 7 | Siswa in **selected** classes sees task | Visible in Tugas Saya | ☐ |
| 8 | Siswa **outside** selected classes | Task not visible | ☐ |
| 9 | Student submits **text** | Row in `etugas_pengumpulan` | ☐ |
| 10 | Student submits valid **link** | Accepted | ☐ |
| 11 | Guru **Review Pengumpulan** | Submitted + Belum Mengumpulkan | ☐ |
| 12 | Guru marks **Revisi** | Status updated | ☐ |
| 13 | Student **resubmits** | Updated submission | ☐ |
| 14 | Guru marks **Selesai** with **nilai** | Saved | ☐ |
| 15 | **Rekap Tugas** | Summary + table correct | ☐ |
| 16 | **Export CSV** | Opens in Excel/LibreOffice; UTF-8; no HTML | ☐ |
| 17 | Guru **pengampu_mapel** scope | In-scope OK; out-of-scope denied | ☐ |
| 18 | **Siswa** opens `admin/etugas.php` or `etugas_hapus.php` | **403** | ☐ |
| 19 | No `tests/` on production | Absent or 404 | ☐ |

---

## E. Multi-kelas spot checks

| # | Check | Done |
|---|--------|------|
| E1 | Create form shows **Kelas tujuan** checkbox grid | ☐ |
| E2 | **Pilih semua** / **Hapus pilihan** work | ☐ |
| E3 | Edit page = **single kelas** only + batch note | ☐ |

---

## F. Safe Delete spot checks

| # | Check | Done |
|---|--------|------|
| F1 | Empty task shows **Hapus** with confirm dialog | ☐ |
| F2 | Task with submissions shows muted: *Sudah ada pengumpulan — gunakan Arsipkan* | ☐ |
| F3 | POST `etugas_hapus.php` without CSRF rejected | ☐ |
| F4 | `etugas_pengumpulan` count unchanged after delete attempt on submitted task | ☐ |

---

## G. Menu smoke test

| Role | Item | URL | Done |
|------|------|-----|------|
| Admin | Kelola Tugas | `admin/etugas.php` | ☐ |
| Admin | Review Pengumpulan | `admin/etugas_pengumpulan.php` | ☐ |
| Admin | Rekap Tugas | `admin/etugas_rekap.php` | ☐ |
| Guru | Same (scoped) | — | ☐ |
| Siswa | Tugas Saya | `siswa/tugas_saya.php` | ☐ |

---

## H. Sign-off

| Field | Value |
|-------|--------|
| Deployed by | _________________________ |
| Date / time | _________________________ |
| SQL imported? | Yes / No / Already existed |
| All section D tests passed? | Yes / No |
| **Go / No-Go** | ☐ GO  ☐ NO-GO |

---

## Related

- `2026-05-17-etugas-full-manual-hosting-deploy.md`
- `2026-05-17-etugas-full-changed-files.txt`
- `2026-05-17-etugas-sql-import-order.md`
