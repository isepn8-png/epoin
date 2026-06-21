# AUDIT MASTER DATA — EPOIN

**Tanggal:** 2026-06-22
**Auditor:** Senior PHP QA & Security Engineer
**Scope:** 8 modul Master Data (Siswa, Kelas, Jurusan, Mapel, Pengampu Mapel, Tahun Ajaran, Kalender Akademik, Pengguna)
**Aturan kerja:** Fatal error langsung fix; bug lain → audit + dokumentasi + fix yang aman; tidak menyentuh logika poin/SP/saldo/absensi.

---

## 1. PRIORITAS 1 — Fatal Error Kalender Akademik (SUDAH FIX ✅)

### Root cause
File `admin/kalender_akademik.php` (sekitar baris 144) menjalankan query:

```sql
SELECT tipe, COUNT(*) AS cnt FROM (
  SELECT tgl, tipe FROM view_non_efektif
  WHERE tgl BETWEEN '...' AND '...'
    AND (ta_id IS NULL OR ta_id=$TA)
) x
```

Query ini memakai agregasi `COUNT(*)` **tanpa `GROUP BY`** sambil tetap mem-`SELECT` kolom non-agregat `tipe`. Pada MySQL dengan `sql_mode=only_full_group_by` (default MySQL 8 / MariaDB 10.5+), ini memicu:

> Fatal error: In aggregated query without GROUP BY, expression #1 of SELECT list contains nonaggregated column 'x.tipe'; incompatible with sql_mode=only_full_group_by

### Analisis tujuan query
Hasil `$qSum` **tidak pernah dibaca** — ini *dead code*. Variabel `$sum` di-inisialisasi ulang pada baris berikutnya (`$sum = ['nasional'=>0, ...]`), lalu dihitung manual lewat loop `$qAll` agar bisa **mengecualikan akhir pekan** sesuai pengaturan 5/6 hari sekolah. Jadi `$qSum` adalah sisa kode lama yang tidak terpakai tetapi tetap dieksekusi MySQL sehingga error.

### Fix
Hapus blok query `$qSum` (dead code). Logika perhitungan ringkasan (lewat `$qAll`) **tidak berubah** — hasil ringkasan semester tetap identik.

**Status:** ✅ FIXED — commit `ba22de8`
**Verifikasi:** `php -l` clean. Fungsi tambah libur / lihat agenda / generate hari efektif tetap jalan (tidak tersentuh).

---

## 2. only_full_group_by — Audit Query Lain

Semua `GROUP BY` di 8 modul master data sudah diperiksa:

| File | Query | Status |
|------|-------|--------|
| `siswa.php:32` | `GROUP BY j.jurusan_id, j.jurusan_nama` | ✅ AMAN — semua kolom non-agregat ada di GROUP BY |
| `siswa.php:52` | `GROUP BY k.kelas_id, k.kelas_nama` | ✅ AMAN — idem |
| `kelas.php:83` | `GROUP BY u.user_id` (select user_nama, user_username) | ✅ AMAN — `user_id` adalah PK, kolom lain *functionally dependent* (MySQL 5.7+ mengizinkan) |
| `kalender_akademik.php` | `COUNT(*)` tanpa GROUP BY | ❌ → **SUDAH FIX** |

**Kesimpulan:** `kalender_akademik.php` adalah **satu-satunya** query yang benar-benar memicu `only_full_group_by`. Pola `GROUP BY <PK>` di `kelas.php` (dan juga di luar scope: `manajemen_pengguna.php`, `users/index.php`, `index.php`) aman karena bergantung pada *functional dependency* terhadap primary key — tidak akan error.

---

## 3. Daftar Bug / Temuan per Modul

### 🔴 HIGH — Privilege Escalation: handler master data dapat diakses SISWA

**Temuan inti:** `epoin_staff_guard()` **tanpa argumen** hanya memeriksa `$_SESSION['id'] > 0` (lihat `includes/epoin_security.php:110-117`). Login **siswa** (`periksa_login.php:65-68`) juga meng-set `$_SESSION['id']` + `$_SESSION['level']='siswa'`. Akibatnya guard tanpa argumen **lolos untuk sesi siswa** — tidak membedakan siswa vs staf.

Karena CSRF token bersifat per-sesi, siswa yang login memiliki token `_csrf` valid untuk sesinya sendiri, sehingga CSRF **tidak** menahan serangan ini. Satu-satunya gerbang adalah auth, dan auth-nya terlalu lemah.

Handler master data yang terdampak (gerbang auth lemah):

| File | Auth saat ini | Dampak bila ditembus siswa |
|------|---------------|----------------------------|
| `ta_act.php:4` | `epoin_staff_guard()` | **Tinggi** — INSERT TA + menjalankan `UPDATE ta SET ta_status='0'` (menonaktifkan SEMUA tahun ajaran aktif → mengganggu seluruh sistem) |
| `siswa_hapus_aksi.php:5` | `epoin_staff_guard()` | **Tinggi** — menghapus siswa mana pun + seluruh data turunannya |
| `jurusan_act.php:4` | `epoin_staff_guard()` | Sedang — INSERT jurusan sampah |
| `kelas_tambah_act.php:7` | `isset($_SESSION['id'])` | Sedang — INSERT kelas sampah |

**Pembanding (sudah benar, admin-only):** `jurusan_hapus.php`, `ta_hapus.php`, `kelas_hapus.php`, `user_hapus.php`, `siswa_import_act.php`, `user_act.php`, `siswa_act.php` semua memakai `epoin_staff_guard(true)`. `mapel.php` & `pengampu_mapel.php` memakai `$_SESSION['level']==='administrator'`. Jadi **pola dominan** untuk mutasi master data adalah admin-only; handler di tabel atas adalah outlier.

**Rekomendasi fix (1 baris per file):** ubah `epoin_staff_guard()` → `epoin_staff_guard(true)` pada `ta_act.php`, `siswa_hapus_aksi.php`, `jurusan_act.php`; dan ubah cek `isset($_SESSION['id'])` di `kelas_tambah_act.php` menjadi `epoin_staff_guard(true)`.

**⚠️ Butuh konfirmasi Bos sebelum apply** — perubahan ini menaikkan tingkat akses menjadi admin-only. Bila di sekolah ada staf non-admin (mis. TU/operator) yang memang bertugas menambah TA/kelas/jurusan, mereka akan ikut tertahan. Mohon konfirmasi model staf sebelum saya terapkan.

---

### 🟠 MEDIUM — CSRF gap pada CREATE/EDIT Mapel & Pengampu Mapel

- `mapel.php` action `tambah` & `edit`: hanya dilindungi cek `$_SESSION['level']==='administrator'`, **tidak ada validasi CSRF**. (Action `hapus` sudah pakai CSRF dari patch sebelumnya.)
- `pengampu_mapel.php` action `tambah` & `edit`: idem, tanpa CSRF.

**Dampak:** admin yang sedang login bisa di-*trick* (CSRF) untuk menambah/mengubah mapel atau penugasan guru lewat request lintas-situs. Severity Medium karena butuh admin sebagai korban + dampak terbatas (data master, bukan kredensial/poin).

**Rekomendasi:** tambah `epoin_csrf_field()` di form modal tambah/edit + `epoin_csrf_validate()` di handler. Termasuk dalam *CSRF backlog* yang sedang berjalan — sarankan dikerjakan satu commit terpisah.

---

### 🟠 MEDIUM — `kelas.php` masih pakai CSRF homegrown lama

`kelas.php:9-10` membuat `$_SESSION['csrf_token']` (pola lama) khusus untuk modal "Set Wali Kelas", dan `kelas_wali_tambah.php:56-61` memvalidasinya manual dengan `hash_equals`. Ini **berfungsi** tetapi tidak konsisten dengan standar `epoin_csrf_*` (`_csrf`) yang dipakai modul lain. Risiko: dua sistem token paralel membingungkan & rawan bug saat refactor.

**Rekomendasi:** migrasikan modal wali ke `epoin_csrf_field()` / `epoin_csrf_validate()`. Aman tapi non-urgent.

---

### 🟢 LOW — Lain-lain

| # | File | Temuan | Status |
|---|------|--------|--------|
| L1 | `user.php` | Semua kolom (`user_nama`, `user_username`, `user_level`, `user_foto`) di-echo **tanpa** `htmlspecialchars` → XSS stored. Juga ada `include '../koneksi.php'` ganda (header.php sudah memuat). | ✅ **FIXED** commit `baba214` |
| L2 | `kelas_wali_tambah.php` | `ini_set('display_errors','1')` + `error_reporting(E_ALL)` tertinggal dari fase dev → bocor stack trace di produksi. | ✅ **FIXED** commit `12092c0` |
| L3 | `mapel.php`/`pengampu_mapel.php` (tambah/edit) | Query INSERT/UPDATE pakai `mysqli_real_escape_string` / `(int)` cast, **bukan** prepared statement. Saat ini aman dari injection (semua input di-escape/cast), tapi disarankan migrasi ke prepared statement agar konsisten. | Dokumentasi |
| L4 | `siswa.php:431` | Dropdown filter jurusan berlabel `"Semua Kelas"` padahal memfilter **jurusan** (bukan kelas). Salah label, membingungkan. | Dokumentasi (kosmetik) |
| L5 | `mapel.php:20` | Komentar `// ... Hapus via GET` sudah usang — hapus kini via POST. | Dokumentasi (kosmetik) |
| L6 | `kalender_akademik.php` | Helper `table_exists()` didefinisikan tapi tidak dipakai sebelum query ke `view_non_efektif`/`hari_efektif`. Bila view/tabel belum ada → fatal. Edge case deployment baru. | Dokumentasi |

---

## 4. CRUD — Status Fungsional

| Modul | Create | Read | Update | Delete | Catatan |
|-------|:------:|:----:|:------:|:------:|---------|
| Siswa | ✅ | ✅ | ✅ | ✅ | Hapus via konfirmasi → `siswa_hapus_aksi.php` (robust, INFORMATION_SCHEMA + transaksi) |
| Kelas | ✅ | ✅ | ✅ | ✅ | + set wali kelas (modal) |
| Jurusan | ✅ | ✅ | ✅ | ✅ | Delete cascade (ikut hapus siswa & kelas terkait) |
| Mapel | ✅ | ✅ | ✅ | ✅ | Tolak hapus bila dipakai penugasan (`alert=relasi`) |
| Pengampu Mapel | ✅ | ✅ | ✅ | ✅ | Unik per (TA, Kelas, Mapel) |
| Tahun Ajaran | ✅ | ✅ | ✅ | ✅ | Set aktif → menonaktifkan TA lain |
| Kalender Akademik | ✅ | ✅ | — | ✅ | Tambah libur (single/range) + generate hari efektif. **Sebelumnya Read FATAL → sudah fix** |
| Pengguna | ✅ | ✅ | ✅ | ✅ | User id=1 tidak bisa dihapus (proteksi super admin) |

**Tidak ada CRUD yang gagal jalan** setelah fix kalender. Semua tombol/link mengarah ke file yang ada (cek 404: `siswa_riwayat.php`, `siswa_edit.php`, `kelas_edit.php`, `kelas_siswa.php`, `jurusan_edit.php`, `ta_edit.php`, `hp_ortu_import.php` — semua **OK ada**).

---

## 5. Rekomendasi Terprioritaskan

| Prioritas | Aksi | File | Status |
|-----------|------|------|--------|
| P1 | Fix fatal `only_full_group_by` | `kalender_akademik.php` | ✅ DONE `ba22de8` |
| P2 | Fix XSS stored | `user.php` | ✅ DONE `baba214` |
| P2 | Hapus debug flags produksi | `kelas_wali_tambah.php` | ✅ DONE `12092c0` |
| **P1** | **Tutup privilege escalation** (guard admin-only) | `ta_act.php`, `siswa_hapus_aksi.php`, `jurusan_act.php`, `kelas_tambah_act.php` | ⏳ **TUNGGU KONFIRMASI BOS** (lihat §3 HIGH) |
| P3 | Tambah CSRF di tambah/edit | `mapel.php`, `pengampu_mapel.php` | ⏳ Backlog CSRF |
| P4 | Migrasi CSRF homegrown → epoin_csrf | `kelas.php` + `kelas_wali_tambah.php` | ⏳ Non-urgent |
| P5 | Prepared statement + perbaikan label/komentar kosmetik | `mapel.php`, `pengampu_mapel.php`, `siswa.php` | ⏳ Non-urgent |

---

## 6. Commit yang Dibuat (belum di-push)

```
12092c0  fix: hapus debug flags di kelas_wali_tambah.php
baba214  fix: XSS di admin/user.php + hapus double-include koneksi
ba22de8  fix: query only_full_group_by di kalender_akademik.php
```

Semua perubahan: `php -l` → 0 error. Tidak menyentuh logika poin/SP/saldo/absensi/rapor/CBT. Tidak ada `ALTER TABLE`.
