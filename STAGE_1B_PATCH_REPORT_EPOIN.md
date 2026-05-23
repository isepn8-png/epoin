# Stage 1B Patch Report — Modul EPOIN

**Tanggal:** 2026-05-19  
**Scope:** Penutupan blocker P0/P1 sebelum migration DB, GitHub, deploy aaPanel  
**Tidak diubah:** `periksa_login.php`, schema database, migration

---

## Ringkasan

Stage 1B menutup temuan kritis dari review Tahap 2 (`PASS_WITH_NOTES`):

| Prioritas | Item | Status |
|-----------|------|--------|
| P0 | `admin/siswa_riwayat.php` — `ajax=issue_sp` | **Selesai** |
| P0 | `admin/sp1_cetak.php` — `sp_log` + alasan GET | **Selesai** |
| P1 | `admin/laporan.php` | **Selesai** |
| P1 | `admin/rekap_tahunan.php` | **Selesai** |
| P1 | `.gitignore` | **Selesai** |
| P1 | Flash message escaping | **Selesai** (helper + pelanggaran/prestasi) |
| P1 | File `*.bak` di working tree | **Selesai** (dipindah ke folder eksternal) |

---

## A. `admin/siswa_riwayat.php` — `ajax=issue_sp`

**Sebelum:** Tanpa staff guard, tanpa CSRF, raw SQL (`$qx`), error SQL di JSON, `siswa_id`/`sp_level` di string query.

**Sesudah:**

- Endpoint memanggil `epoin_sp_ajax_issue_endpoint()` di `includes/epoin_sp_helpers.php`.
- Wajib **POST** + `epoin_staff_guard_json()` + `epoin_csrf_validate($_POST)`.
- Validasi `siswa_id` integer, `sp_level` whitelist `SP1`–`SP4`.
- Semua operasi `sp_log` via **prepared statement**.
- Response JSON generik (tanpa `mysqli_error`).
- Verifikasi siswa ada (`epoin_fetch_siswa_row`).
- Log aktivitas via `epoin_log_aktivitas` (actor dari session).
- Frontend: `window.EPOIN_CSRF` + field `_csrf` pada AJAX.

---

## B. `admin/sp1_cetak.php`

**Sebelum:** SELECT/INSERT `sp_log` raw; `alasan` dari GET masuk SQL.

**Sesudah:**

- `id` siswa: cast integer + validasi siswa.
- `sp`: `epoin_sp_validate_level()` whitelist.
- `sp_log`: `epoin_sp_fetch_latest_log`, `epoin_sp_auto_create_for_print` (prepared; auto-insert **tanpa** alasan dari GET).
- Alasan GET hanya `epoin_sp_sanitize_alasan()` untuk **tampilan HTML** (`e()`).
- Session staff tetap via `header.php` (login wajib).

---

## C. `admin/laporan.php`

- Whitelist `view` (`siswa`|`kelas`), `saldo_scope` (`all`|`pos`|`zero`|`neg`), `export` (`excel`|`''`).
- `ta_id` / `kelas_id` non-negatif integer.
- Label TA/kelas: prepared SELECT.
- Output tabel & export Excel sudah memakai `esc()` / cast numerik.

---

## D. `admin/rekap_tahunan.php`

- Tahun: integer, rentang **2000–2100**.
- Query agregasi: prepared (`YEAR(waktu) = ?`).
- Output: `epoin_h()` untuk nama/NIS.
- Error DB: pesan aman, tanpa detail SQL.

---

## E. `.gitignore`

Memblokir: `.env`, `*.bak`, `*.stage1b.bak`, `*.sql` (kecuali `database/manual-migrations/*.sql`), arsip, log, `uploads/*`, `vendor/`, `tests/`, `node_modules/`, `backup/`, `backups/`.

---

## F. File `.bak`

**Dipindahkan ke:** `C:\laragon\backup\epoin_stage1_backup\`  
**Manifest:** `MANIFEST_STAGE1B.txt` (20 file Stage 1).

**Rekomendasi rotasi password DB:** File `poin_kolektif.php.bak` di folder backup tersebut berisi kredensial hardcoded Stage 1. Rotasi password user MySQL aplikasi jika file pernah ter-commit atau dibagikan.

**Jangan commit** file di folder backup atau `*.stage1b.bak` di repo.

---

## G. Flash message

- `epoin_flash_render()` di `includes/epoin_security.php` — output `epoin_h()`.
- Dipasang di `admin/pelanggaran.php` dan `admin/prestasi.php`.
- Pesan error handler (`pelanggaran_act`, dll.) sudah generik (bukan `mysqli_error`).

---

## File baru

| File | Fungsi |
|------|--------|
| `includes/epoin_sp_helpers.php` | SP whitelist, prepared `sp_log`, AJAX `issue_sp` |

---

## File diubah

1. `includes/epoin_security.php` — `epoin_flash_render()`
2. `includes/epoin_sp_helpers.php` — **baru**
3. `admin/siswa_riwayat.php`
4. `admin/sp1_cetak.php`
5. `admin/laporan.php`
6. `admin/rekap_tahunan.php`
7. `admin/pelanggaran.php`
8. `admin/prestasi.php`
9. `.gitignore`

---

## Backup `.stage1b.bak` (post-patch snapshot)

- `.gitignore.stage1b.bak`
- `admin/laporan.php.stage1b.bak`
- `admin/rekap_tahunan.php.stage1b.bak`
- `admin/siswa_riwayat.php.stage1b.bak`
- `admin/sp1_cetak.php.stage1b.bak`
- `includes/epoin_security.php.stage1b.bak`

Versi **pre–Stage 1B** untuk `siswa_riwayat` / `sp1_cetak`: lihat `C:\laragon\backup\epoin_stage1_backup\siswa_riwayat.php.bak`, `sp1_cetak.php.bak`.

---

## Query raw → prepared (Stage 1B)

| Lokasi | Operasi |
|--------|---------|
| `epoin_sp_ajax_issue_endpoint` | SELECT issued levels, MAX running_no, COUNT per siswa, INSERT sp_log, SELECT BP signer |
| `epoin_sp_fetch_latest_log` | SELECT sp_log |
| `epoin_sp_auto_create_for_print` | MAX/COUNT/INSERT/SELECT sp_log |
| `epoin_sp_fetch_bp_signer` | SELECT sekolah_staff + user |
| `admin/laporan.php` | SELECT ta_nama, kelas_nama |
| `admin/rekap_tahunan.php` | Agregasi tahunan per siswa |

---

## Endpoint dengan CSRF (baru / diperkuat Stage 1B)

| Endpoint | Metode | Catatan |
|----------|--------|---------|
| `siswa_riwayat.php?ajax=issue_sp` | POST | `_csrf` di body AJAX |

*(Stage 1 sudah: act/update/hapus pelanggaran & prestasi, input_*, poin_kolektif API.)*

---

## Risiko residual

| Risiko | Catatan |
|--------|---------|
| `periksa_login.php` | MD5 + SQLi — **patch terpisah** (out of scope) |
| `sp2_cetak.php` … `sp4_cetak.php` | Jika salinan `sp1_cetak.php` lama, perlu hardening sama |
| `laporan.php` filter TA/kelas di subquery | Integer cast + whitelist; belum full prepared di subquery agregasi |
| `poin_kolektif.php.bak` di backup eksternal | Berisi kredensial — rotasi DB |
| Auto-insert SP di cetak tanpa alasan | Perilaku disengaja; penerbitan resmi tetap lewat `issue_sp` |

---

## Keputusan deploy

| Langkah | Aman lanjut? |
|---------|----------------|
| **Manual retest** (checklist Stage 1B) | **Ya** — wajib sebelum langkah berikut |
| **GitHub** (push/PR) | **Ya**, setelah retest P0 PASS dan pastikan tidak ada `*.bak` / `.env` ter-stage |
| **Database migration** | **Ya**, setelah retest + review residual `sp*_cetak` |
| **aaPanel deploy** | **Ya**, setelah GitHub + migration sesuai runbook |

**Verdict Stage 1B:** **READY_FOR_RETEST** — blocker P0 ditutup; P1 hygiene selesai.
