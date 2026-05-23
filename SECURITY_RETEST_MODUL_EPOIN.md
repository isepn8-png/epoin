# SECURITY RETEST — Modul EPOIN Tahap 1

**Mode:** Manual test checklist  
**Environment:** Laragon — `http://localhost:8088/epoin`  
**Reviewer:** QA / security (isi kolom Actual saat eksekusi)

> Kolom **Actual** dan **PASS/FAIL** default *Belum diuji* kecuali diverifikasi statis dari kode.

---

## A. Autentikasi & sesi

| # | Test | Expected | Actual | PASS/FAIL |
|---|------|----------|--------|-----------|
| A1 | Buka `admin/pelanggaran.php` tanpa login | Redirect login | Belum diuji | — |
| A2 | Login sebagai **administrator** | Masuk dashboard admin | Belum diuji | — |
| A3 | Login sebagai **guru** (bukan admin) | Masuk; bisa input poin | Belum diuji | — |
| A4 | Login **siswa**, akses `admin/input_pelanggaran_act.php` POST | Ditolak / tidak ada session staff | Belum diuji | — |

---

## B. CSRF

| # | Test | Expected | Actual | PASS/FAIL |
|---|------|----------|--------|-----------|
| B1 | Tambah master pelanggaran (form normal) | Sukses | Belum diuji | — |
| B2 | POST `pelanggaran_act.php` tanpa field `_csrf` | Redirect + pesan CSRF invalid | Belum diuji | — |
| B3 | POST `input_pelanggaran_act.php` tanpa `_csrf` | Ditolak | Belum diuji | — |
| B4 | `poin_kolektif` — DevTools hapus `_csrf` dari FormData, panggil `save_bulk` | JSON `CSRF token tidak valid`, HTTP 403 | Belum diuji | — |
| B5 | Hapus master pelanggaran — submit form tanpa `_csrf` | Ditolak | Belum diuji | — |
| B6 | `siswa_riwayat.php?ajax=issue_sp` POST tanpa CSRF | **Saat ini belum dilindungi** — catat perilaku | Review statis: **FAIL expected** | FAIL (known gap) |

---

## C. Input poin valid

| # | Test | Expected | Actual | PASS/FAIL |
|---|------|----------|--------|-----------|
| C1 | Input pelanggaran: TA → kelas → siswa valid → simpan | 1 baris di `input_pelanggaran`, saldo berubah | Belum diuji | — |
| C2 | Input prestasi valid | 1 baris di `input_prestasi` | Belum diuji | — |
| C3 | `poin_kolektif` — bulk 2 siswa valid | JSON `ok:true`, `inserted` > 0 | Belum diuji | — |
| C4 | Edit baris input pelanggaran | Update tersimpan | Belum diuji | — |

---

## D. Validasi siswa–kelas

| # | Test | Expected | Actual | PASS/FAIL |
|---|------|----------|--------|-----------|
| D1 | POST `input_pelanggaran_act` dengan `siswa` valid tapi `kelas` tidak sesuai roster | Flash error: tidak terdaftar di kelas | Belum diuji | — |
| D2 | `poin_kolektif` kirim pair `siswa_id:kelas_id` palsu | JSON error siswa tidak terdaftar | Belum diuji | — |
| D3 | Siswa di kelas TA lama (masih di `kelas_siswa`) | **Mungkin lolos** — dokumentasikan jika OK secara bisnis | Belum diuji | — |

---

## E. Delete

| # | Test | Expected | Actual | PASS/FAIL |
|---|------|----------|--------|-----------|
| E1 | GET `input_pelanggaran_hapus.php?id=1` | **405** Method not allowed, data tidak terhapus | Review statis: `epoin_require_post()` | PASS (statis) |
| E2 | Hapus via tombol trash (konfirmasi) | POST + `_csrf`, baris hilang | Belum diuji | — |
| E3 | Hapus master pelanggaran (admin) | POST form, cascade input | Belum diuji | — |
| E4 | Guru coba hapus master pelanggaran | Ditolak (admin only) | Belum diuji | — |
| E5 | Guru hapus input milik sekolah lain (ID acak) | **Saat ini bisa** jika tahu ID — catat IDOR | Known gap | FAIL (authz) |

---

## F. Prepared statement / SQLi smoke

| # | Test | Expected | Actual | PASS/FAIL |
|---|------|----------|--------|-----------|
| F1 | Master pelanggaran nama: `test' OR '1'='1` | Disimpan literal atau error validasi, **bukan** SQL error | Belum diuji | — |
| F2 | POST `id` hapus: `1 OR 1=1` | ID cast → 1 atau 0, tidak delete semua | Review statis: `(int)` | PASS (statis) |
| F3 | `rekap_tahunan.php?tahun=2024' OR '1'='1` | **Belum di-patch** — risiko SQLi | Known gap | FAIL (out of scope) |

---

## G. XSS

| # | Test | Expected | Actual | PASS/FAIL |
|---|------|----------|--------|-----------|
| G1 | Siswa nama `<script>alert(1)</script>` di DB, buka `ranking_siswa.php` | Tampil sebagai teks, tidak execute | Belum diuji | — |
| G2 | List `input_pelanggaran.php` kolom nama | Escape HTML | Review statis: `epoin_h()` | PASS (statis) |
| G3 | Flash error dengan pesan aneh | Tidak execute JS | Belum diuji | — |

---

## H. Credential & deploy hygiene

| # | Test | Expected | Actual | PASS/FAIL |
|---|------|----------|--------|-----------|
| H1 | Buka source `admin/poin_kolektif.php` di browser | Tidak ada password literal | Review statis: clean | PASS (statis) |
| H2 | `git status` sebelum commit | Tidak ada `.env`, `*.bak`, `*.sql` | Belum diuji | — |
| H3 | Grep repo aktif `DB_PASS=` / password literal | Hanya `.env.example` kosong / config | Review: credential hanya di `.bak` | PASS_WITH_NOTES |

---

## I. Halaman partial patch

| # | Test | Expected | Actual | PASS/FAIL |
|---|------|----------|--------|-----------|
| I1 | `siswa_riwayat.php?id={valid}` | Profil + saldo + riwayat tampil | Belum diuji | — |
| I2 | `siswa_riwayat.php?id=0` | Pesan ID tidak valid | Review statis: ada guard | PASS (statis) |
| I3 | `sp1_cetak.php?id=0` | Pesan ID tidak valid | Review statis: ada guard | PASS (statis) |
| I4 | `sp1_cetak.php?id={valid}&sp=SP1` | Cetak/surat (auto-insert mungkin jalan) | Belum diuji | — |
| I5 | Terbit SP dari UI riwayat (AJAX) | SP tersimpan | Belum diuji — **endpoint belum hardened** | — |

---

## J. Error log Laragon

| # | Test | Expected | Actual | PASS/FAIL |
|---|------|----------|--------|-----------|
| J1 | Jalankan A1–C3, cek `laragon/logs` atau PHP error log | Tidak ada Fatal/Warning baru di handler patch | Belum diuji | — |
| J2 | `poin_kolektif` gagal DB (simulasi .env salah) | Pesan generik, tidak expose password | Belum diuji | — |

---

## Ringkasan hasil (isi setelah test)

| Kategori | PASS | FAIL | Belum |
|----------|------|------|-------|
| Auth | | | |
| CSRF | | B6 | |
| Input/Kelas | | | |
| Delete | E1 statis | E5 | |
| SQLi/XSS | F2,G2 statis | F3 | |
| Deploy | H1 statis | | H2 |

**Minimum untuk release Stage 1:** A2, B1–B5, C1–C3, D1–D2, E1–E2, G1, H2 — semua PASS.

**Blocker known:** B6, E5 (authz), F3 (rekap), `issue_sp` / `sp1` raw SQL.

---

## Error yang ditemukan saat review statis

| ID | Severity | Deskripsi |
|----|----------|-----------|
| ERR-01 | Critical | `issue_sp` tanpa CSRF & tanpa `epoin_staff_guard` |
| ERR-02 | High | `sp1_cetak.php` auto-insert `sp_log` raw SQL |
| ERR-03 | High | Credential di `poin_kolektif.php.bak` |
| ERR-04 | Medium | IDOR delete/update input by ID |
| ERR-05 | Medium | `rekap_tahunan.php` SQLi potential |
| ERR-06 | Low | `.gitignore` belum cover `uploads/` |

*Tambahkan error runtime di kolom Actual saat QA menjalankan test.*
