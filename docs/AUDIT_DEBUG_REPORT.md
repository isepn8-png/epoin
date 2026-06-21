# AUDIT & DEBUG REPORT — Modul EPOIN

**Tanggal:** 2026-06-21
**Auditor:** Senior PHP Security & QA (Claude Opus)
**Scope:** Bug aktif "Koneksi error" (Quick-Add HP Ortu) + audit modul yang baru dikerjakan + scan umum.
**Metode:** Baca kode, reproduksi via PHP CLI, verifikasi DB (`SHOW COLUMNS`), `php -l`.
**Status push:** ❌ Belum push — menunggu konfirmasi Bos.

---

## 1. ROOT CAUSE — Bug "Koneksi error" (Quick-Add HP Ortu)  ✅ SUDAH DI-FIX

### Gejala
Modal "Tambah Nomor WA Orang Tua" → isi nomor → klik **Simpan** → muncul **"Koneksi error."**

### Root cause (terkonfirmasi via reproduksi)
Endpoint AJAX `admin/siswa_riwayat.php?ajax=save_hp_ortu` memanggil:

```php
$token = $_POST['_csrf'] ?? '';      // $token = STRING
if (!epoin_csrf_validate($token)) {  // ❌ kirim STRING
```

Tetapi signature fungsinya menerima **array**, bukan string:

```php
function epoin_csrf_validate(?array $source = null): bool   // includes/epoin_security.php:38
```

Memberi **string** ke parameter bertipe `?array` → **Fatal `TypeError`** (terjadi di batas pemanggilan fungsi, bahkan tanpa `declare(strict_types)` di file pemanggil — karena string tidak bisa di-*coerce* ke array).

**Reproduksi (PHP CLI):**
```
BUGGY CALL THREW: TypeError: epoin_csrf_validate(): Argument #1 ($source)
                  must be of type ?array, string given
```

### Mengapa muncul sebagai "Koneksi error" (bukan pesan CSRF)
1. Endpoint sudah mengirim `header('Content-Type: application/json')`.
2. Lalu `TypeError` fatal → body respons jadi **HTML/teks error PHP** (atau 500 kosong di production), **bukan JSON**.
3. Di sisi JS: `fetch(...).then(r => r.json())` → `r.json()` **gagal parse** → promise reject → masuk **`.catch()`** → `showErr('Koneksi error.')` (siswa_riwayat.php:812).

Jadi "Koneksi error" itu **menyesatkan** — sebenarnya bukan masalah jaringan/DB, tapi respons server bukan JSON akibat fatal error.

### Bukti pendukung
- Kolom DB **`hp_ortu` ADA** di lokal: `varchar(20) NULL DEFAULT NULL` → **bukan** penyebab.
- Prepared statement `UPDATE siswa SET hp_ortu=? WHERE siswa_id=?` + binding `'si'` → **benar**.
- CSRF token dikirim benar dari JS (`window.EPOIN_CSRF` via FormData) → **benar**.
- Masalah **murni** di cara memanggil `epoin_csrf_validate()`.

### Bug kembar (root cause IDENTIK) — `admin/hp_ortu_import_act.php:14`
Pola yang sama persis dipakai di handler **Import HP Ortu via Excel**:
```php
$token = $_POST['_csrf'] ?? '';
if (!epoin_csrf_validate($token)) {   // ❌ TypeError → import GAGAL TOTAL
```
Akibatnya fitur **bulk import HP ortu juga 100% rusak** (menampilkan fatal error / 500, bukan memproses Excel). Karena `php -l` hanya cek sintaks, bug runtime ini lolos saat verifikasi sebelumnya.

### Fix yang diterapkan (2 file)
Kedua file diubah ke pola yang sudah dipakai konsisten di **seluruh** codebase (`epoin_csrf_validate()` tanpa argumen → fungsi membaca `$_POST` sendiri):

| File | Sebelum | Sesudah |
|------|---------|---------|
| `admin/siswa_riwayat.php:23-24` | `$token = $_POST['_csrf'] ?? ''; if (!epoin_csrf_validate($token))` | `if (!epoin_csrf_validate())` |
| `admin/hp_ortu_import_act.php:13-14` | `$token = $_POST['_csrf'] ?? ''; if (!epoin_csrf_validate($token))` | `if (!epoin_csrf_validate())` |

### Verifikasi sesudah fix
- Reproduksi token valid → `bool(true)` (tidak throw). ✅
- Reproduksi token salah → `bool(false)` (graceful, JS akan tampilkan "Gagal: CSRF tidak valid." — bukan "Koneksi error"). ✅
- `php -l` kedua file → **0 error**. ✅

> **Catatan keputusan:** Bug kembar di `hp_ortu_import_act.php` ikut di-fix dalam commit yang sama karena **root cause identik** dan keduanya **blocking** untuk fitur HP Ortu yang baru dibangun. Tidak ada logika bisnis yang diubah.

---

## 2. DAFTAR BUG / TEMUAN PER MODUL

### Severity legend
**Critical** = fitur rusak total/keamanan parah · **High** = fitur penting terganggu · **Medium** = bug nyata tapi terbatas · **Low** = kosmetik/konsistensi/by-design.

| # | Modul | Temuan | Severity | Status |
|---|-------|--------|----------|--------|
| 1 | siswa_riwayat.php | TypeError `epoin_csrf_validate(string)` → "Koneksi error" | **Critical** | ✅ Fixed |
| 2 | hp_ortu_import_act.php | TypeError sama → import Excel rusak total | **Critical** | ✅ Fixed |
| 3 | siswa_update.php:82-84 | Blok mati `$_FILES['file']`/`$abs_path`/`usage_log_file_uploaded()`/`$SEKOLAH_ID` (semua undefined) — **pra-ada sejak commit awal**, bukan dari kerja hp_ortu | **Medium** | ⏸ Tunggu konfirmasi |
| 4 | siswa_update.php | Validasi `hp_ortu` hanya batasi maks 20 digit, **tanpa min 10** (modal & import pakai min 10) | **Low** | ⏸ Tunggu konfirmasi |
| 5 | siswa_tambah.php | Field `hp_ortu` belum ada (hanya di form Edit) — gap konsistensi | **Low** | ⏸ Tunggu konfirmasi |
| 6 | index.php (SP strip) | "Total" = Σ `COUNT(DISTINCT siswa_id)` per level → siswa dgn SP1+SP2 terhitung 2× di Total | **Low** (by-design) | ℹ️ Catatan |
| 7 | siswa_edit.php:15 | `WHERE siswa_id='$id'` (sudah `(int)`, aman dari injeksi) tapi gaya string-quoted int tidak konsisten | **Low** | ℹ️ Catatan |

### Detail temuan #3 (siswa_update.php — blok mati)
```php
// admin/siswa_update.php:82-84  (DARI COMMIT AWAL f76a116, bukan hp_ortu)
if (move_uploaded_file($_FILES['file']['tmp_name'], $abs_path)) {
  usage_log_file_uploaded($koneksi, $SEKOLAH_ID, $abs_path, 'upload dokumen');
}
```
- `$_FILES['file']`, `$abs_path`, `$SEKOLAH_ID` **tidak terdefinisi** di file ini; `usage_log_file_uploaded()` ada di `includes/usage_helper.php` tapi **tidak di-include** di sini.
- `move_uploaded_file(null, null)` → `false`, jadi isi `if` (pemanggilan fungsi undefined) **tidak pernah jalan** → tidak fatal.
- **Tetapi** baris ini mengeluarkan **2 warning** (`Undefined array key 'file'`, `Undefined variable $abs_path`) di **setiap** simpan edit siswa. Bila `display_errors=On`, warning ini dapat **mematahkan redirect** `header('Location: ...')` ("headers already sent") sehingga setelah simpan tampil halaman aneh/blank meski data tersimpan.
- **Rekomendasi:** hapus 3 baris ini (kode nyasar dari integrasi multi-tenant). Aman, tidak menyentuh logika bisnis. Tidak saya fix dulu karena di luar Prioritas 1 dan bersifat pra-ada → **butuh konfirmasi**.

---

## 3. MODUL YANG SUDAH DIVERIFIKASI **BERSIH** (tidak ada bug)

### A. Dashboard admin — SP Strip (`admin/index.php`)
- Data `$spTerbit` dari `sp_log`, scope `YEAR(tanggal)=$ta_year`; `$ta_year` di-`(int)`-cast → **aman injeksi**.
- Responsif: `.sp-strip-items{overflow-x:auto}`, head disembunyikan `@media(max-width:640px)`. Tidak overflow di mobile.
- Counter JS menyasar `.sp-strip-num[data-count]` → animasi jalan. ✅
- Zone-header sudah seragam (tanpa inline-style override). ✅

### B. Stepper Jenjang Pembinaan (`siswa/index.php`)
- **Event delegation** dipasang di elemen `#JenjangSheet` (bukan di node), dan listener tetap aktif setelah `appendChild(document.body)` — desain benar (siswa/index.php:1726).
- 7 entri data `D[0..6]` cocok dengan 7 `.jst-step` (data-step 0–6). Klik node → `selectStep()` → render detail. ✅
- Tutup via backdrop, tombol close, dan **Escape**. ✅
- Konten detail statis (developer-defined), bukan input user → tidak ada XSS. ✅
- Responsif: `@media` mengatur overlay/modal full-screen di mobile. ✅

### C. HP Ortu — sisi yang sudah benar
- `siswa_edit.php`: CSRF token ada, output pakai `e()`/escaping, field `hp_ortu` prefilled, ada `track-change`. ✅
- `siswa_update.php`: `hp_ortu` di-sanitasi (`preg_replace('/\D+/','')`), `NULL` bila kosong, prepared statement (`ssiss`). ✅ (selain temuan #3/#4 di atas)
- `siswa_riwayat.php` (sisi tampilan): normalisasi `08xx→628xx` benar di PHP (`$waDigits`) dan JS (line 822); validasi 10–15 digit konsisten JS↔PHP. ✅
- `hp_ortu_import_act.php`: prepared statement, transaksi, lookup NIS, **skip** (bukan error) bila NIS tak ditemukan, batasi `.xlsx`/5 MB, escaping hasil. Edge case (baris kosong, NIS kosong, HP tak valid) **tertangani**. ✅ (selain bug CSRF #2 yang sudah di-fix)

### D. Keamanan modul baru (CSRF/XSS)
- CSRF: semua **form & AJAX baru** mengirim & memvalidasi token. Sesudah fix, validasi berjalan benar.
- XSS: output di modal & halaman hasil import sudah di-escape (`epoin_h`/`htmlspecialchars`).
- Auth: `hp_ortu_import.php` & `_act.php` dilindungi `epoin_staff_guard(false)`. ✅

---

## 4. SCAN UMUM (Prioritas 3) — Backlog keamanan PRA-ADA (di luar scope kerja baru)

Dari pemetaan pemanggilan `epoin_csrf_validate()` di seluruh repo, file berikut **tidak** punya validasi CSRF/auth helper (handler tulis data) — **bukan regresi baru**, tapi layak hardening bertahap:

| File | Risiko | Severity |
|------|--------|----------|
| `admin/siswa_act.php` | INSERT/UPDATE/DELETE siswa — tanpa CSRF | **High** |
| `admin/user_act.php` | CRUD user — tanpa CSRF | **High** |
| `admin/siswa_import_act.php` | Bulk import — tanpa CSRF/auth helper | **High** |
| `admin/mapel.php?action=hapus` | DELETE via **GET** | **Medium** |
| `admin/pengampu_mapel.php?action=hapus` | DELETE via **GET** | **Medium** |
| `admin/ujian_gform.php?__act=delete` | DELETE via **GET** | **Medium** |

> Catatan: hardening backlog ini **tidak** dikerjakan sekarang (sesuai aturan: audit dulu, satu issue per commit, tunggu konfirmasi).

---

## 5. REKOMENDASI PERBAIKAN — TERPRIORITASKAN

| Prioritas | Item | Aksi | Status |
|-----------|------|------|--------|
| **P1** | "Koneksi error" Quick-Add HP Ortu | Fix `epoin_csrf_validate()` | ✅ **DONE** |
| **P1** | Import HP Ortu rusak (root cause sama) | Fix `epoin_csrf_validate()` | ✅ **DONE** |
| **P2** | siswa_update.php blok mati (#3) | Hapus 3 baris nyasar | ⏸ Konfirmasi |
| **P3** | Konsistensi validasi HP min 10 di edit (#4) | Tambah cek min 10 digit server-side | ⏸ Konfirmasi |
| **P3** | `hp_ortu` di form Tambah siswa (#5) | Tambah field + handle di insert | ⏸ Konfirmasi |
| **P4** | Hardening CSRF backlog (§4) | Patch bertahap, 1 file/commit | ⏸ Konfirmasi |

---

## 6. RINGKASAN STATUS

- ✅ **Di-fix sekarang (commit, belum push):** Bug Critical "Koneksi error" + bug kembar import (2 file, root cause identik, tanpa ubah logika bisnis).
- ⏸ **Tunggu konfirmasi:** temuan #3, #4, #5 + backlog keamanan §4.
- ℹ️ **Catatan/by-design:** #6, #7.
- 🔒 **Tidak disentuh:** logika SP1–SP4, kalkulasi saldo, absensi, rapor, CBT, struktur tabel.
