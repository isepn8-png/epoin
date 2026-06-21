# Audit CSRF & XSS — EPOIN Admin Portal
**Tanggal audit:** 2026-06-21  
**Auditor:** AI Security Engineer  
**Status:** Patch TUGAS 1–3 selesai (commit belum push — menunggu konfirmasi)

---

## A. Sistem CSRF yang Sudah Ada

| Sistem | File | Token Session Key | POST Field | Dipakai di |
|--------|------|-------------------|------------|-----------|
| `epoin_csrf_*` | `includes/epoin_security.php` | `_csrf` | `_csrf` | pelanggaran_hapus, prestasi_hapus, input_*_hapus |
| `etugas_csrf_*` | `includes/etugas_helpers.php` | `etugas_csrf_token` | `etugas_csrf` | modul E-Tugas (tugas_*) |
| Homegrown (stale) | `admin/jurusan.php` hanya | `csrf_token` | `csrf_token` | Form tambah jurusan (token di-generate tapi TIDAK divalidasi di handler) |

**Fungsi helper XSS:**
- `epoin_h($s)` — `includes/epoin_security.php` ← **standar yang akan digunakan**
- `etugas_h($s)` — `includes/etugas_helpers.php` ← hanya untuk modul etugas
- `esc($s)` — lokal di `laporan.php` (tidak global)
- `escs($s)` — lokal di `rekap_kelas.php` (tidak global)
- `e($s)` — lokal di `jurusan.php` (tidak global)

---

## B. Handler POST Tanpa CSRF

### Sudah Aman ✅
| File | Sistem CSRF | Catatan |
|------|-------------|---------|
| `admin/input_prestasi_act.php` | `epoin_csrf_validate()` | + prepared statement |
| `admin/input_pelanggaran_act.php` | `epoin_csrf_validate()` | + prepared statement |
| `admin/prestasi_act.php` | `epoin_csrf_validate()` | + prepared statement |
| `admin/pelanggaran_act.php` | `epoin_csrf_validate()` | + prepared statement |
| `admin/etugas_act.php` | `etugas_verify_csrf()` | + role check |
| `siswa/gantipassword_act.php` | — (siswa) | bcrypt + prepared statement |

### Belum Ada CSRF ❌ (prioritas patch)
| File | Aksi | Auth Sebelumnya | Risiko |
|------|------|-----------------|--------|
| `admin/gantipassword_act.php` | UPDATE password | session ada | TINGGI — ganti password tanpa CSRF |
| `admin/siswa_act.php` | INSERT/UPDATE/DELETE siswa | ❌ tidak ada | TINGGI — manajemen data siswa |
| `admin/user_act.php` | INSERT/UPDATE/DELETE user | session ada | TINGGI — manajemen akun |
| `admin/siswa_import_act.php` | bulk INSERT siswa | ❌ tidak ada | TINGGI — mass import |
| `admin/jurusan_act.php` | INSERT jurusan | ❌ tidak ada | SEDANG |
| `admin/kelas_act.php` | INSERT kelas | ❌ tidak ada | SEDANG |
| `admin/ta_act.php` | INSERT TA + UPDATE status | ❌ tidak ada | SEDANG |
| `admin/kelas_siswa_act.php` | INSERT kelas_siswa | ❌ tidak ada | SEDANG |
| `admin/kelas_tambah_act.php` | INSERT kelas | session ada | SEDANG |
| `admin/siswa_hapus_aksi.php` | DELETE siswa (POST) | session ada | TINGGI — hapus permanen, no CSRF |

---

## C. DELETE via GET Tanpa CSRF

### Sangat Kritis (no auth + no CSRF + SQL injection) ❌❌
| File | Dipanggil dari | Masalah |
|------|---------------|---------|
| `admin/jurusan_hapus.php` | `jurusan.php` link | Raw `$_GET['id']` langsung ke SQL string = SQL injection; cascading delete (jurusan→siswa→prestasi→pelanggaran→kelas); no auth; no CSRF |
| `admin/kelas_hapus.php` | `kelas.php` link | Raw `$_GET['id']` = SQL injection; cascading delete; no auth; no CSRF |
| `admin/ta_hapus.php` | `ta.php` link | Raw `$_GET['id']` = SQL injection; cascading delete; no auth; no CSRF |
| `admin/user_hapus.php` | `user.php` link | Raw `$_GET['id']` = SQL injection; `unlink()` path bebas = path traversal risk; no auth; no CSRF |

### Ada Session Auth Tapi Belum CSRF ❌
| File | Auth | Masalah |
|------|------|---------|
| `admin/mapel.php?action=hapus` | session check | DELETE via GET, no CSRF |
| `admin/pengampu_mapel.php?action=hapus` | session check | DELETE via GET, no CSRF |
| `admin/ujian_gform.php?__act=delete` | session + user ownership | DELETE via GET, no CSRF |

---

## D. Audit XSS

### Target Files Status

| File | Output User Data | Fungsi Escape | Status |
|------|-----------------|---------------|--------|
| `admin/ranking_siswa.php` | siswa_nama, siswa_nis | `epoin_h()` | ✅ AMAN |
| `admin/laporan.php` | siswa_nama, siswa_nis, kelas_nama | `esc()` = htmlspecialchars | ✅ AMAN |
| `admin/rekap_kelas.php` | kelas_nama, jurusan_nama | `escs()` = htmlspecialchars | ✅ AMAN |
| `admin/rekap_bulanan.php` | int cast + htmlspecialchars | ya | ✅ AMAN |
| `admin/rekap_tahunan.php` | int cast + htmlspecialchars | ya | ✅ AMAN |
| `admin/laporan_absensi.php` | int cast + htmlspecialchars | ya | ✅ AMAN |

### Temuan Minor (Reflected XSS risk LOW)
- `admin/laporan.php:576` — `http_build_query($_GET)` di-echo ke `href=` attribute. `http_build_query` URL-encodes values sehingga `"` jadi `%22`, tapi tidak HTML-encode `&` untuk konteks HTML. Bisa dipertegas dengan `htmlspecialchars(http_build_query(...))`.

---

## E. Rencana Patch

### TUGAS 1 — CSRF pada Handler POST
**Commit:** `security: CSRF validation di handler admin POST`

Langkah:
1. Tambah `require_once __DIR__ . '/../includes/epoin_security.php'` ke `admin/header.php`
2. Patch handlers: `gantipassword_act.php`, `jurusan_act.php`, `kelas_act.php`, `ta_act.php`, `kelas_siswa_act.php`, `kelas_tambah_act.php`, `siswa_hapus_aksi.php`
3. Tambah `<?= epoin_csrf_field() ?>` ke form: `gantipassword.php`, `jurusan.php`, `ta.php`, `kelas.php`, `kelas_siswa.php`, `kelas_tambah.php`, `siswa_hapus_konfir.php`
4. `jurusan.php`: ganti token homegrown `csrf_token` dengan `epoin_csrf_field()`

### TUGAS 2 — DELETE via GET → POST + CSRF + SweetAlert
**Commit:** `security: ubah DELETE via GET ke POST + konfirmasi SweetAlert`

Langkah:
1. Rewrite `jurusan_hapus.php`, `kelas_hapus.php`, `ta_hapus.php`, `user_hapus.php`:
   - Require POST + CSRF + epoin_staff_guard
   - Ganti raw SQL string query dengan prepared statements
2. Update link di `jurusan.php`, `kelas.php`, `ta.php`, `user.php`:
   - Ganti `<a href="hapus.php?id=X">` dengan form hidden+button
   - SweetAlert2 konfirmasi sebelum submit (sudah tersedia di admin/header.php)
3. Convert `mapel.php?action=hapus`, `pengampu_mapel.php?action=hapus`, `ujian_gform.php?__act=delete` ke POST+CSRF

### TUGAS 3 — XSS
**Commit:** `security: XSS hardening output laporan dan reflection`

Langkah:
1. `laporan.php:576` — wrap `http_build_query()` dengan `htmlspecialchars()`
2. Semua file laporan/rekap sudah aman — tidak ada echo tanpa escape
