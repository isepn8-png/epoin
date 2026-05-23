# EPOIN Security Patch Report

**Tanggal:** 2026-05-19  
**Scope:** Modul EPOIN (file prioritas + helper + form pendukung)

---

## 1. Ringkasan file yang diubah

| File | Perubahan utama |
|------|-----------------|
| `includes/epoin_security.php` | **Baru** — CSRF, session guard, prepared helpers, `kelas_siswa` check, PDO dari `.env` |
| `admin/poin_kolektif.php` | Hapus credential hardcoded; PDO via `epoin_get_pdo()`; CSRF API; validasi `kelas_siswa` bulk |
| `admin/pelanggaran_act.php` | Prepared INSERT + CSRF + admin guard |
| `admin/prestasi_act.php` | Prepared INSERT + CSRF + admin guard |
| `admin/input_pelanggaran_act.php` | CSRF, kelas_siswa, prepared only, log prepared |
| `admin/input_prestasi_act.php` | Idem |
| `admin/input_pelanggaran_update.php` | Prepared UPDATE + CSRF + kelas_siswa |
| `admin/input_prestasi_update.php` | Idem |
| `admin/input_pelanggaran_hapus.php` | POST only + prepared DELETE + CSRF |
| `admin/input_prestasi_hapus.php` | Idem |
| `admin/pelanggaran_update.php` | Prepared UPDATE + CSRF + admin |
| `admin/prestasi_update.php` | Idem |
| `admin/pelanggaran_hapus.php` | POST + prepared cascade delete + CSRF |
| `admin/prestasi_hapus.php` | Idem |
| `admin/siswa_riwayat.php` | Prepared fetch siswa + agregasi poin via helper |
| `admin/sp1_cetak.php` | Validasi `id`; prepared riwayat/kelas; agregasi helper |
| `admin/ranking_siswa.php` | `epoin_h()` pada output nama/NIS |
| `admin/pelanggaran.php` | CSRF form tambah; hapus via POST form |
| `admin/prestasi.php` | Idem |
| `admin/pelanggaran_edit.php` | CSRF + prepared load master |
| `admin/prestasi_edit.php` | Idem |
| `admin/input_pelanggaran.php` | Escape list; hapus POST+CSRF (JS) |
| `admin/input_prestasi.php` | Idem |

**Backup:** Semua file di atas (kecuali file baru) disalin ke `*.bak` di folder yang sama.

---

## 2. Diff patch (ringkas per file)

### `includes/epoin_security.php` (baru)

- `epoin_csrf_token()` / `epoin_csrf_field()` / `epoin_csrf_validate()`
- `epoin_staff_guard()` / `epoin_staff_guard_json()`
- `epoin_verify_siswa_kelas()`
- `epoin_get_pdo()` membaca `config/database.php` + `.env`
- `epoin_sum_prestasi_siswa()` / `epoin_sum_pelanggaran_siswa()` / `epoin_fetch_siswa_row()`

### Handler `*_act.php` / `*_update.php` / `*_hapus.php`

**Sebelum (contoh `pelanggaran_act.php`):**

```php
$nama  = $_POST['nama'];
mysqli_query($koneksi, "insert into pelanggaran values (NULL,'$nama','$point')");
```

**Sesudah:**

```php
epoin_staff_guard(true);
epoin_require_post();
if (!epoin_csrf_validate()) { epoin_csrf_fail_redirect('pelanggaran.php'); }
$stmt = mysqli_prepare($koneksi, 'INSERT INTO pelanggaran (pelanggaran_nama, pelanggaran_point) VALUES (?, ?)');
mysqli_stmt_bind_param($stmt, 'si', $nama, $point);
```

### `admin/poin_kolektif.php`

**Sebelum:** `$DB_HOST`, `$DB_USER`, `$DB_PASS` hardcoded + PDO terpisah.

**Sesudah:** `require koneksi.php` + `epoin_get_pdo()`; API memanggil `epoin_staff_guard_json()` + `epoin_csrf_validate()`; `save_bulk` memverifikasi `kelas_siswa` per pasangan siswa:kelas.

### Hapus: GET → POST

**Sebelum:** `input_pelanggaran_hapus.php?id=5` via `window.location.href`.

**Sesudah:** Form POST dinamis + `_csrf` + `id` (JS di list) atau `<form method="post">` di master pelanggaran/prestasi.

---

## 3. Query yang sudah prepared statement

| File | Query |
|------|--------|
| `pelanggaran_act.php` | `INSERT INTO pelanggaran (...)` |
| `prestasi_act.php` | `INSERT INTO prestasi (...)` |
| `input_pelanggaran_act.php` | `INSERT INTO input_pelanggaran` (+ optional `ip_ym`) |
| `input_prestasi_act.php` | `INSERT INTO input_prestasi` (+ optional `pr_ym`) |
| `input_pelanggaran_update.php` | `UPDATE input_pelanggaran SET ... WHERE id=?` |
| `input_prestasi_update.php` | `UPDATE input_prestasi SET ... WHERE id=?` |
| `input_pelanggaran_hapus.php` | `DELETE FROM input_pelanggaran WHERE id=?` |
| `input_prestasi_hapus.php` | `DELETE FROM input_prestasi WHERE id=?` |
| `pelanggaran_update.php` | `UPDATE pelanggaran SET ... WHERE pelanggaran_id=?` |
| `prestasi_update.php` | `UPDATE prestasi SET ... WHERE prestasi_id=?` |
| `pelanggaran_hapus.php` | `DELETE input_pelanggaran` + `DELETE pelanggaran` |
| `prestasi_hapus.php` | `DELETE input_prestasi` + `DELETE prestasi` |
| `input_*_act.php` | `SELECT siswa_nama`, `SELECT *_nama`, `INSERT log_aktivitas` |
| `epoin_security.php` | `kelas_siswa` check, agregasi poin, fetch siswa |
| `siswa_riwayat.php` | Via helper agregasi + fetch siswa |
| `sp1_cetak.php` | UNION riwayat + kelas terakhir (prepared) |
| `pelanggaran_edit.php` / `prestasi_edit.php` | `SELECT * FROM master WHERE id=?` |
| `poin_kolektif.php` | Sudah PDO prepared; ditambah `SELECT kelas_siswa` |

**Dihapus:** Fallback raw SQL di `input_*_act.php` setelah prepare gagal.

---

## 4. Form / handler dengan validasi CSRF

| Form / aksi | Token field | Handler validasi |
|-------------|-------------|------------------|
| Modal tambah `pelanggaran.php` | `_csrf` | `pelanggaran_act.php` |
| Modal tambah `prestasi.php` | `_csrf` | `prestasi_act.php` |
| `pelanggaran_edit.php` | `_csrf` | `pelanggaran_update.php` |
| `prestasi_edit.php` | `_csrf` | `prestasi_update.php` |
| `input_pelanggaran_tambah.php` | `_csrf` (sudah ada) | `input_pelanggaran_act.php` |
| `input_prestasi_tambah.php` | `_csrf` (sudah ada) | `input_prestasi_act.php` |
| `input_pelanggaran_edit.php` | `_csrf` (sudah ada) | `input_pelanggaran_update.php` |
| `input_prestasi_edit.php` | `_csrf` (perlu sama) | `input_prestasi_update.php` |
| Hapus master (POST form) | `_csrf` | `pelanggaran_hapus.php`, `prestasi_hapus.php` |
| Hapus input (JS POST) | `_csrf` | `input_*_hapus.php` |
| API `poin_kolektif.php` | `_csrf` di FormData | Semua `?action=` |

Token invalid → redirect + `flash_error` (handler HTML) atau JSON 403 (API).

---

## 5. Hal yang belum diperbaiki (di luar / sisa risiko)

| Item | Alasan |
|------|--------|
| `admin/laporan.php`, `rekap_tahunan.php` | Tidak dalam scope; masih filter SQL concat |
| `admin/index.php` KPI query | Scope modul handler; list masih mysqli concat |
| `input_pelanggaran.php` / `input_prestasi.php` query list besar (implicit JOIN) | Bukan injeksi langsung dari user; performa terpisah |
| `siswa_riwayat.php` AJAX `issue_sp` | Masih beberapa query concat di endpoint SP |
| `sp1_cetak.php` auto-insert `sp_log` | Bagian INSERT SP masih dynamic SQL |
| Duplikasi input poin (tanpa UNIQUE) | Butuh perubahan DB / business rule |
| Rate limiting / approval workflow | Fitur baru |
| `admin/tools/reset_epoin.php` | Tidak dalam daftar prioritas |
| Login siswa (`periksa_login.php`) | Di luar modul EPOIN admin |

---

## 6. Checklist testing manual

### Autentikasi & CSRF

- [ ] Login admin/guru → buka `pelanggaran.php` → tambah master → sukses
- [ ] Submit form tanpa token (devtools hapus `_csrf`) → ditolak + pesan error
- [ ] Buka `poin_kolektif.php` → simpan bulk tanpa login → JSON 401
- [ ] API bulk tanpa `_csrf` → JSON 403

### Input poin

- [ ] Input pelanggaran: pilih siswa **bukan** di kelas → ditolak
- [ ] Input prestasi valid → saldo berubah di `siswa_riwayat.php`
- [ ] Edit input pelanggaran/prestasi → data terupdate
- [ ] Hapus via tombol trash → POST (cek Network tab), bukan GET

### Master data (admin)

- [ ] Non-admin tidak bisa hapus master (redirect/error)
- [ ] Hapus jenis pelanggaran → input terkait ikut terhapus

### Poin kolektif

- [ ] Koneksi DB memakai `.env` lokal (bukan credential lama di file)
- [ ] Bulk 2 siswa 2 kelas → hanya pasangan valid `kelas_siswa` tersimpan

### Output & laporan

- [ ] `ranking_siswa.php` → nama dengan karakter `<>"` tidak dieksekusi sebagai HTML
- [ ] `sp1_cetak.php?id=VALID` → cetak normal
- [ ] `sp1_cetak.php?id=0` → pesan ID tidak valid

### Regresi

- [ ] Flash success/error masih tampil setelah redirect
- [ ] DataTables filter di list input masih jalan
- [ ] Tidak ada credential DB tampil di halaman/error browser

---

*Restore dari backup: salin `namafile.php.bak` ke `namafile.php` jika perlu rollback.*
