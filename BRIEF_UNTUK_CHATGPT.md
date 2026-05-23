# BRIEF UNTUK CHATGPT — EPOIN

> Ringkasan teknis untuk di-upload/paste ke ChatGPT. Secret = `[REDACTED]`.

---

## 1. Ringkasan project

**EPOIN** adalah aplikasi web PHP native untuk sekolah: manajemen **poin pelanggaran & prestasi**, absensi, penilaian, rapor, ujian Google Form, dan modul **e-Tugas**. Berjalan di Laragon (`http://localhost:8088/epoin`), database MySQL `epoin_local` (~62 tabel). Akan di-push ke GitHub lalu deploy ke VPS **aaPanel**.

---

## 2. Framework / arsitektur

- **Bukan** Laravel/CodeIgniter full framework.
- **Native PHP** monolith: satu file PHP = satu halaman.
- UI: **AdminLTE 2 + Bootstrap 3** (`assets/`).
- DB: **mysqli** (dominan); sebagian kecil **PDO** (`admin/poin_kolektif.php`).
- Config: `.env` → `includes/env.php` → `config/database.php` → `koneksi.php`.
- **Tidak ada** `composer.json` di root.

---

## 3. Struktur folder utama

| Folder | Isi |
|--------|-----|
| `admin/` | ~140 file — panel admin/guru/TAS |
| `siswa/` | ~22 file — panel siswa |
| `includes/` | `auth.php`, `env.php`, `etugas_helpers.php`, `usage_helper.php` |
| `config/` | `database.php` |
| `assets/` | AdminLTE, Bootstrap, plugins |
| `database/manual-migrations/` | SQL e-Tugas saja di repo |
| `docs/` | LOCAL_SETUP, deployment manifests |
| `uploads/` | File user — **jangan Git** |

---

## 4. Entry point

- `index.php` → redirect `login.php`
- POST login → `periksa_unified.php` → `periksa_login.php` (siswa) atau `periksa_admin.php` (staff)
- Admin: `admin/index.php` + `admin/header.php`
- Siswa: `siswa/index.php` + `siswa/header.php`

---

## 5. Modul utama

| Modul | Lokasi |
|-------|--------|
| Poin pelanggaran/prestasi | `admin/pelanggaran.php`, `prestasi.php`, `input_*.php` |
| Absensi | `admin/absensi_harian.php`, `absensi_mapel.php` |
| Nilai & rapor | `admin/nilai_*.php`, `rapor_sts_*.php` |
| Ujian GForm | `admin/ujian_gform.php`, `siswa/exam_gform.php` |
| e-Tugas | `admin/etugas*.php`, `includes/etugas_helpers.php` |
| User/RBAC | `admin/user.php`, `includes/auth.php` |
| Sekolah/lisensi/kuota | `admin/sekolah.php`, `usage_helper.php` |
| Export/import | `admin/export_csv.php`, `siswa_import.php` |

---

## 6. Database & tabel penting

- DB: `epoin_local` (MySQL 8.x, port 3308 lokal).
- Schema lengkap: **dump SQL lokal**, tidak di repo (kecuali migrasi e-Tugas).
- Tabel inti: `siswa`, `input_pelanggaran`, `input_prestasi`, `pelanggaran`, `prestasi`, `kelas`, `kelas_siswa`, `ta`, `user`, `roles`, `user_roles`, `permissions`, `role_permissions`, `sekolah`, `absensi_harian`, `absensi_harian_detail`, `etugas`, `etugas_pengumpulan`, `audit_log`, `usage_log`, `tenant_quota`.

---

## 7. Relasi penting

- `siswa` ↔ `input_pelanggaran` ↔ `pelanggaran`
- `siswa` ↔ `input_prestasi` ↔ `prestasi`
- **Saldo** = SUM(prestasi_point) − SUM(pelanggaran_point)
- `user` ↔ `user_roles` ↔ `roles` ↔ `role_permissions` ↔ `permissions`
- `kelas` ↔ `kelas_siswa` ↔ `siswa`
- `etugas` ↔ `etugas_pengumpulan` ↔ `siswa` (UNIQUE per tugas+siswa)

---

## 8. Alur login

1. User buka `login.php`, pilih role (siswa / guru / admin / TAS).
2. POST ke `periksa_unified.php`.
3. **Siswa:** `periksa_login.php` — query `siswa` WHERE nis + **MD5(password)** — **risiko SQLi**.
4. **Staff:** `periksa_admin.php` → `do_login_with_role()` — prepared stmt, bcrypt atau MD5 legacy, load RBAC ke session.
5. Redirect: `siswa/index.php` atau `admin/index.php`.

---

## 9. Alur modul EPOIN (poin)

1. Admin definisi master di `pelanggaran` / `prestasi` (poin per jenis).
2. Input transaksi: `input_pelanggaran`, `input_prestasi` (per siswa, tanggal).
3. Dashboard/ranking: agregasi SUM join master.
4. SP/pembinaan: `admin/sp1_cetak.php` — tahap dari saldo negatif.
5. Log: `log_aktivitas`, `audit_log` (absensi).

---

## 10. File konfigurasi penting

| File | Fungsi |
|------|--------|
| `.env` | DB + APP_ENV (jangan commit) |
| `.env.example` | Template |
| `config/database.php` | mysqli connect |
| `includes/env.php` | Loader env |
| `koneksi.php` | Bootstrap DB + error mode |

**Production:** `APP_ENV=production`, DB port 3306, credential `[REDACTED]`.

---

## 11. Risiko keamanan utama

1. **Critical:** SQL injection + MD5 di `periksa_login.php`
2. **Critical:** `admin/phpinfo.php` / tools reset jika live
3. **High:** Banyak `mysqli_query` dengan string concat di modul lama
4. **High:** CSRF tidak global (kecuali e-Tugas)
5. **Medium:** XSS tidak konsisten; upload folder bisa dieksekusi jika salah config

---

## 12. Risiko deploy utama

- `.env` tidak ter-copy / tertimpa deploy script
- Dump SQL besar timeout
- FK order salah saat import
- `uploads/` permission / hilang saat deploy
- `APP_ENV` masih `local` → error detail bocor

---

## 13. Rekomendasi .gitignore

Commit: PHP source, `assets/`, `docs/`, `database/manual-migrations/*.sql`, `.env.example`.  
Ignore: `.env`, `*.sql` (kecuali manual migrations), `uploads/*`, `tests/`, `*.zip`, `*.log`, backup folders.

---

## 14. Rekomendasi deploy aaPanel

1. Backup DB + uploads lokal
2. Push GitHub tanpa secret
3. VPS: create site, PHP 8.1+, SSL, import DB, buat `.env` production
4. chmod `uploads/`, protect `.env`
5. Deploy script: git pull, backup tar, **jangan** hapus `.env`/uploads
6. Hapus/block phpinfo; test login + modul poin + e-Tugas

---

## 15. Pertanyaan belum terjawab (perlu cek manual)

- Daftar **62 tabel** lengkap + `SHOW CREATE TABLE` dari dump lokal
- Apakah semua halaman admin memanggil `can()` / role check?
- Daftar lengkap file upload handler + validasi MIME
- Apakah `admin/tools/reset_epoin.php` masih aktif di production?
- Struktur tabel nilai/rapor exact (nama tabel bervariasi)
- Lisensi `sekolah_license` — algoritma validasi exact
- Apakah ada cron/queue atau semua synchronous PHP?

---

## 16. File yang perlu dicek manual oleh ChatGPT

Prioritas audit kode:

```
periksa_login.php
periksa_admin.php
includes/auth.php
admin/header.php
admin/index.php
admin/input_pelanggaran.php
admin/input_prestasi.php
admin/absensi_harian.php
admin/sekolah.php
admin/phpinfo.php
admin/tools/reset_epoin.php
siswa/header.php
includes/etugas_helpers.php
config/database.php
koneksi.php
.gitignore
.env.example
```

Dokumentasi lengkap di repo:

- `PROJECT_BLUEPRINT_EPOIN.md`
- `DATABASE_BLUEPRINT_EPOIN.md`
- `SECURITY_AUDIT_EPOIN.md`
- `DEPLOYMENT_PLAN_GITHUB_AAPANEL.md`

---

*Paste brief ini bersama pertanyaan spesifik (mis. "buat patch SQLi untuk periksa_login.php tanpa mengubah struktur DB").*
