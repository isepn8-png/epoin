# PATCH REVIEW — Modul EPOIN Tahap 1

**Mode:** Read-only review (tanpa perubahan kode)  
**Tanggal review:** 2026-05-19  
**Patch scope:** Security hardening modul EPOIN (handlers + helper + form pendukung)

---

## Verdict: **PASS_WITH_NOTES**

Patch tahap 1 **substantif dan tidak setengah jalan** untuk file prioritas handler (`*_act`, `*_update`, `*_hapus`, `poin_kolektif`, partial `siswa_riwayat` / `sp1_cetak`, XSS ranking/list).  
Namun **belum aman end-to-end** untuk production/GitHub karena: endpoint SP AJAX belum di-hardening, beberapa halaman laporan masih raw SQL, login siswa critical, `.gitignore` belum lengkap, dan **credential lama ada di `*.bak`**.

---

## 1. Review `includes/epoin_security.php`

| Aspek | Status | Bukti / catatan |
|-------|--------|-----------------|
| `session_start` | **OK** | `epoin_ensure_session()` cek `PHP_SESSION_ACTIVE` — aman dipanggil ulang setelah `header.php` |
| CSRF generate | **OK** | `bin2hex(random_bytes(32))` di `$_SESSION['_csrf']` |
| CSRF validate | **OK** | `hash_equals($expected, $got)` — field `_csrf` + header `X-CSRF-TOKEN` |
| Redirect error | **OK dengan catatan** | `epoin_csrf_fail_redirect($backUrl)` — path **hardcoded** di handler (aman); jangan pass URL user |
| Escape output | **OK** | `epoin_h()` → `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` |
| Auth / role | **OK sebagian** | `epoin_staff_guard(true)` untuk master; `false` untuk input poin |
| Bypass risk | **Medium** | `epoin_is_admin_session()` mengandalkan `$_SESSION['roles']` / `level` — bisa dilengkari jika session dimanipulasi (perlu hardening session global) |
| Reuse modul lain | **OK** | Helper generik, siap dipakai modul admin lain |

**Potensi konflik session:** Tidak ada regenerasi session ID setelah login — di luar scope patch ini.

---

## 2. Review credential

### File aktif (prioritas)

| File | Hardcoded DB credential? |
|------|-------------------------|
| `admin/poin_kolektif.php` | **Tidak** — `epoin_get_pdo()` → `config/database.php` → `.env` |
| Semua handler `*_act/update/hapus` | **Tidak** — `koneksi.php` saja |

### File `*.bak` (20 file di `admin/`)

| File | Temuan |
|------|--------|
| `admin/poin_kolektif.php.bak` | **YA** — `$DB_HOST`, `$DB_USER`, `$DB_PASS` literal (password production lama) |

File `.bak` lainnya berisi pola SQLi lama (GET delete, raw query), **bukan** credential tambahan.

### `.gitignore`

```gitignore
.env
*.sql
*.bak
*.zip
*.log
```

| Item | Status |
|------|--------|
| `.env` | **Diblok** |
| `*.bak` | **Diblok** |
| `*.sql` | **Diblok** (kecuali manual migrations) |
| `uploads/*` | **Belum ada** di `.gitignore` |
| `vendor/` | **Belum ada** |
| `tests/` | **Belum ada** |

**Rekomendasi:** Jangan commit `*.bak`. Jika `poin_kolektif.php.bak` pernah pernah di repo/backup publik → **rotasi password DB** `[REDACTED]`.

**Tidak ada** `include`/`require` ke file `.bak` di kode aktif.

---

## 3. Review prepared statement (handlers)

| File | Prepared INSERT/UPDATE/DELETE | Raw fallback? | ID cast |
|------|------------------------------|---------------|---------|
| `pelanggaran_act.php` | Ya | Tidak | `point` (int) |
| `prestasi_act.php` | Ya | Tidak | Ya |
| `input_pelanggaran_act.php` | Ya | **Dihapus** | `(int)` semua |
| `input_prestasi_act.php` | Ya | **Dihapus** | Ya |
| `input_pelanggaran_update.php` | Ya | Tidak | Ya + `kelas_siswa` |
| `input_prestasi_update.php` | Ya | Tidak | Ya + `kelas_siswa` |
| `input_pelanggaran_hapus.php` | Ya | Tidak | `(int) $_POST['id']` |
| `input_prestasi_hapus.php` | Ya | Tidak | Ya |
| `pelanggaran_update.php` | Ya | Tidak | Ya |
| `prestasi_update.php` | Ya | Tidak | Ya |
| `pelanggaran_hapus.php` | Ya (2x DELETE) | Tidak | Ya |
| `prestasi_hapus.php` | Ya | Tidak | Ya |

**Bukti (contoh lama vs baru):**

```php
// pelanggaran_act.php.bak — RAW
mysqli_query($koneksi, "insert into pelanggaran values (NULL,'$nama','$point')");

// pelanggaran_act.php — PREPARED
mysqli_prepare($koneksi, 'INSERT INTO pelanggaran (pelanggaran_nama, pelanggaran_point) VALUES (?, ?)');
```

**Halaman list** (`input_pelanggaran.php`, `input_prestasi.php`) masih `mysqli_query` SELECT tanpa input user di WHERE — risiko rendah, bukan handler mutasi.

---

## 4. Review CSRF

| Lokasi | Kirim `_csrf` | Validasi handler |
|--------|---------------|------------------|
| `pelanggaran.php` modal + hapus POST form | Ya (`epoin_csrf_field`) | `pelanggaran_act.php`, `pelanggaran_hapus.php` |
| `prestasi.php` | Ya | `prestasi_act.php`, `prestasi_hapus.php` |
| `input_*_tambah.php` | Ya (hidden `_csrf`) | `input_*_act.php` |
| `input_*_edit.php` | Ya | `input_*_update.php` |
| `pelanggaran_edit.php` / `prestasi_edit.php` | Ya | `*_update.php` |
| Hapus input (JS POST) | Ya (`window.EPOIN_CSRF`) | `input_*_hapus.php` |
| `poin_kolektif.php` API | Ya (FormData) | `epoin_csrf_validate($_POST)` sebelum switch |

**Delete GET:** Handler menolak GET (`epoin_require_post()` → 405). Link aktif **tidak** lagi `href="*_hapus.php?id="` (hanya di `.bak`).

**Token invalid:** `epoin_csrf_fail_redirect()` atau JSON 403 di API.

**Belum CSRF:** `siswa_riwayat.php?ajax=issue_sp` (POST tanpa token).

---

## 5. Review session guard & role

| Handler | Guard | Admin-only? |
|---------|-------|-------------|
| Master `*_act/update/hapus` pelanggaran/prestasi | `epoin_staff_guard(true)` | Ya |
| Input `*_act/update/hapus` | `epoin_staff_guard(false)` | Guru + admin (sesuai desain UI) |
| `poin_kolektif` API | `epoin_staff_guard_json()` | Staff login |

**Gap:**

- **IDOR delete/update:** Staff mana pun bisa `DELETE input_pelanggaran WHERE id=?` jika tahu ID — tidak ada cek kepemilikan/wali kelas.
- **`siswa_riwayat.php` AJAX `issue_sp`:** Berjalan **sebelum** `include header.php` — **tidak** memanggil `epoin_staff_guard()`; tidak terbukti cek login di blok AJAX.
- Siswa tidak mengakses handler admin (path terpisah `siswa/`) — OK untuk scope admin files.

---

## 6. Review validasi siswa–kelas

| File | `epoin_verify_siswa_kelas()` | Catatan TA |
|------|------------------------------|------------|
| `input_pelanggaran_act.php` | Ya (gagal → flash + redirect) | Hanya `kelas_siswa`, **tanpa** filter TA aktif |
| `input_prestasi_act.php` | Ya | Sama |
| `input_*_update.php` | Ya | Sama |
| `poin_kolektif.php` `save_bulk` | Ya per pasangan `sid:kid` | Sama |

Validasi **server-side**, tidak hanya dropdown UI — **memenuhi requirement**.

**Catatan:** Siswa bisa terdaftar di kelas TA lama; input tetap lolos jika baris `kelas_siswa` ada.

---

## 7. File belum selesai (risiko residual)

| File / area | Risiko | Rekomendasi fase |
|-------------|--------|------------------|
| `siswa_riwayat.php` AJAX `issue_sp` | SQLi raw (`$qx("... '$sid' ...")`), **tanpa CSRF**, guard login lemah | **Stage 1B** (wajib sebelum prod) |
| `sp1_cetak.php` auto-insert `sp_log` | Raw SQL `VALUES ('$id', '$sp'...)` + `alasan` dari GET | **Stage 1B** |
| `admin/laporan.php` | Filter/build SQL concat `ta_id`, `kelas_id` (cast int, bukan prepared) | Stage 1B atau laporan terpisah |
| `admin/rekap_tahunan.php` | `YEAR(...) = '$tahun'` dari GET | Stage 1B |
| `admin/index.php` | KPI query concat TA | Stage 3 / hardening terpisah |
| `periksa_login.php` | MD5 + SQLi `siswa_nis='$nis'` | **Patch terpisah** (auth siswa, Critical) |

---

## 8. Review XSS

| File | Status |
|------|--------|
| `ranking_siswa.php` | **OK** — `epoin_h()` nama & NIS |
| `input_pelanggaran.php` / `input_prestasi.php` | **OK** — kolom teks di-escape |
| `pelanggaran.php` master list | Sudah `htmlspecialchars` nama master |
| Flash `$_SESSION['flash_error']` | **Risiko** jika ditampilkan tanpa escape — `input_*_act` bisa menyertakan `$err_msg` mysqli; perlu audit template tampil flash |

---

## 9. Review rollback risk

| Item | Status |
|------|--------|
| `*.bak` tidak di-include | **OK** |
| `*.bak` di `.gitignore` | **OK** |
| Rollback dokumentasi | Ada di `EPOIN_SECURITY_PATCH_REPORT.md` |
| Aplikasi tidak membaca `.bak` | **OK** |

**Risiko operasional:** Developer bisa salah edit file `.bak` mengira aktif — hapus atau pindah ke folder luar repo setelah patch stabil.

---

## 10. File yang sudah aman (ringkas)

- `includes/epoin_security.php`
- Semua 12 handler `*_act`, `*_update`, `*_hapus` prioritas
- `admin/poin_kolektif.php` (aktif, koneksi .env)
- `admin/pelanggaran.php`, `prestasi.php`, `*_edit.php` (CSRF form)
- `admin/input_pelanggaran.php`, `input_prestasi.php` (escape + POST delete)
- `admin/ranking_siswa.php`
- `admin/siswa_riwayat.php` — bagian load siswa & agregasi poin (partial)
- `admin/sp1_cetak.php` — bagian load siswa, riwayat UNION, agregasi (partial)

---

## 11. File yang masih perlu revisi

| Prioritas | File / isu |
|-----------|------------|
| **P0** | `siswa_riwayat.php` — `issue_sp` AJAX |
| **P0** | `sp1_cetak.php` — auto-insert & query `sp_log` raw |
| **P1** | Credential di `poin_kolektif.php.bak` — jangan commit, rotasi DB |
| **P1** | `.gitignore` — tambah `uploads/`, opsional `vendor/`, `tests/` |
| **P2** | `laporan.php`, `rekap_tahunan.php` |
| **P2** | IDOR delete/update input (authorization per record) |
| **P3** | `periksa_login.php` (luar modul admin tapi critical) |
| **P3** | Flash message escape konsisten |

---

## 12. Rekomendasi prioritas

1. **Stage 1B:** `issue_sp`, `sp1_cetak` mutasi SP, laporan/rekap, perketat `.gitignore`, hapus/pindahkan `*.bak` dari working tree.
2. **Sebelum GitHub push:** Pastikan `git status` tidak ada `.env`, `*.bak`, `*.sql`, uploads.
3. **Rotasi password** jika credential di `.bak` pernah exposed.
4. **DB migration:** Boleh ditunda; patch tahap 1 tidak mengubah schema.
5. **Manual retest:** Ikuti `SECURITY_RETEST_MODUL_EPOIN.md`.

---

*Review statis berbasis source code; tidak menjalankan test browser pada dokumen ini.*
