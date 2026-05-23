# Stage 1B Retest Checklist — Modul EPOIN

Gunakan checklist ini setelah deploy patch ke staging/lokal. Centang setiap item; catat PASS/FAIL.

**Environment:** Laragon, DB `epoin_local`, user staff terautentikasi.

---

## P0 — Wajib PASS

### 1. `siswa_riwayat.php` — `issue_sp`

| # | Uji | Expected | PASS |
|---|-----|----------|------|
| 1.1 | Buka riwayat siswa saldo negatif, terbitkan SP1 via modal | JSON `ok:true`, tab cetak terbuka | ☐ |
| 1.2 | Ulang tanpa CSRF (hapus `_csrf` di DevTools) | `403`, `ok:false`, pesan CSRF | ☐ |
| 1.3 | Request GET ke `?ajax=issue_sp` | `405` Method not allowed | ☐ |
| 1.4 | POST tanpa login (session kosong) | `401`, Belum login | ☐ |
| 1.5 | POST `sp_level=SP99` | `ok:false`, data tidak valid | ☐ |
| 1.6 | POST `siswa_id=999999` | `ok:false`, siswa tidak ditemukan | ☐ |
| 1.7 | POST saldo ≥ 0 | `ok:false`, SP tidak dapat diterbitkan | ☐ |
| 1.8 | Response error tidak mengandung `SQL`, `mysqli`, nama tabel | Pesan generik saja | ☐ |
| 1.9 | SP2 tanpa SP1 (sequential) | `ok:false`, urutan belum terpenuhi | ☐ |
| 1.10 | Tanpa `bp_user_id` | `ok:false`, pilih Guru BP | ☐ |

### 2. `sp1_cetak.php`

| # | Uji | Expected | PASS |
|---|-----|----------|------|
| 2.1 | `?id=1&sp=SP1` (siswa valid, ambang terpenuhi) | Halaman cetak, nomor/log tampil | ☐ |
| 2.2 | `?id=abc` | Pesan ID tidak valid, tanpa SQL error | ☐ |
| 2.3 | `?id=1&sp=HACK` | Fallback/normalisasi ke SP valid | ☐ |
| 2.4 | `?id=1&alasan=<script>alert(1)</script>` | Script tidak dieksekusi di HTML | ☐ |
| 2.5 | Auto-insert (belum ada log, ambang OK) | Log terbuat; alasan kosong di DB (bukan dari GET) | ☐ |
| 2.6 | `?debug=1` hanya di dev | Tidak aktif di production tanpa flag | ☐ |

---

## P1 — Sangat disarankan

### 3. `laporan.php`

| # | Uji | Expected | PASS |
|---|-----|----------|------|
| 3.1 | Filter TA + kelas + urutan + scope saldo | Data tampil benar | ☐ |
| 3.2 | `?view=evil` | Fallback ke `siswa` | ☐ |
| 3.3 | `?saldo_scope=evil` | Fallback ke `all` | ☐ |
| 3.4 | Export Excel | File terunduh; nama ter-escape | ☐ |
| 3.5 | Karakter `<` di nama siswa (jika ada data uji) | Tampil sebagai teks, bukan HTML | ☐ |

### 4. `rekap_tahunan.php`

| # | Uji | Expected | PASS |
|---|-----|----------|------|
| 4.1 | `?tahun=2025` | Tabel rekap tampil | ☐ |
| 4.2 | `?tahun=99999` | Peringatan tahun tidak valid | ☐ |
| 4.3 | `?tahun=' OR 1=1--` | Tidak SQLi; tahun invalid | ☐ |
| 4.4 | Error DB (simulasi koneksi putus) | Pesan aman, tanpa detail SQL | ☐ |

### 5. Repo hygiene

| # | Uji | Expected | PASS |
|---|-----|----------|------|
| 5.1 | `git status` tidak menampilkan `admin/*.bak` | Bersih | ☐ |
| 5.2 | `.env` tidak ter-track | Di `.gitignore` | ☐ |
| 5.3 | Folder `C:\laragon\backup\epoin_stage1_backup` ada manifest | 20 file | ☐ |

### 6. Flash messages

| # | Uji | Expected | PASS |
|---|-----|----------|------|
| 6.1 | CSRF gagal di `pelanggaran_act` | Redirect + alert escaped | ☐ |
| 6.2 | Simpan pelanggaran gagal validasi | Pesan di `pelanggaran.php` escaped | ☐ |

---

## Regresi Stage 1 (smoke)

| # | Modul | PASS |
|---|-------|------|
| R1 | Input pelanggaran + hapus (CSRF POST) | ☐ |
| R2 | Input prestasi + hapus | ☐ |
| R3 | Master pelanggaran/prestasi CRUD admin | ☐ |
| R4 | `poin_kolektif.php` API | ☐ |
| R5 | `ranking_siswa.php` | ☐ |

---

## Out of scope (catat terpisah)

- [ ] `periksa_login.php` — tidak diuji di Stage 1B

---

## Sign-off

| Peran | Nama | Tanggal | Hasil |
|-------|------|---------|-------|
| Tester | | | P0: __ / __ |
| Security | | | |

**Lanjut GitHub + migration + aaPanel hanya jika semua P0 PASS.**
