# SECURITY PATCH — Broken Access Control & Session Hardening (EPOIN)

**Tanggal:** 2026-07-10
**Mode:** Audit read-only → patch defensif (menambah guard, tidak mengubah logika bisnis)
**Scope:** `admin/`, `includes/`, root login

> Semua secret/PIN ditulis apa adanya di sini karena SUDAH terekspos di repo publik; nilai
> tersebut HARUS diganti via `.env` (lihat `.env.example`).

---

## A. Temuan utama — Broken Access Control (KRITIS)

Lima endpoint admin memakai pola berbahaya yang sama:

```php
if (!function_exists('_is_admin')) { function _is_admin(){ return true; } }  // stub selalu true
...
if ($_SERVER['REQUEST_METHOD']==='POST' && _is_admin()) { /* MUTASI DB */ }   // dieksekusi tanpa login
...
include __DIR__ . '/header.php';   // guard login (ensure_logged_in) baru di sini — TERLAMBAT
```

Karena `_is_admin()` selalu `true` dan `header.php` (satu-satunya guard login) di-`include`
di **akhir** file, semua handler POST + query di atasnya dapat dieksekusi **tanpa autentikasi**.

| File | Aksi tanpa login yang mungkin | Dampak |
|------|------------------------------|--------|
| `admin/HOSTING.php` | `reset_usage` (DELETE `usage_daily`+`usage_log`), `save_quota`, `rebuild_daily` | Hapus/ubah data usage & kuota tenant. "PIN" `123456` di source publik. |
| `admin/sekolah.php` | `save_profile`, `save_license`, `db_clear` | Ubah profil sekolah & lisensi, bersihkan data. |
| `admin/export_csv.php` | Export tabel DB → CSV/ZIP | Eksfiltrasi data (siswa dll) tanpa login. |
| `admin/KODE.php` | Generator **kode lisensi** | Cetak kode aktivasi/renew. Hanya dijaga PIN `789789` publik. |
| `admin/db_check_nh.php` | Dump struktur skema (JSON) | Bocor struktur DB. (Sebelumnya juga fatal karena path `koneksi.php` salah.) |

Temuan ini **belum tercatat** di laporan audit sebelumnya (`SECURITY_AUDIT_EPOIN.md` dll).

### Perbaikan
- Menambahkan `require_once includes/epoin_security.php;` + **`epoin_staff_guard(true);`** di
  bagian **paling atas** tiap file (sebelum handler apa pun) → wajib login sebagai admin.
- Mengganti stub `_is_admin(){ return true; }` → `_is_admin(){ return epoin_is_admin_session(); }`
  (kini jadi lapis kedua yang nyata).
- `admin/db_check_nh.php`: memperbaiki path include (`./koneksi.php` → `../koneksi.php`).
- PIN dipindah ke `.env`: `HOSTING_RESET_PIN`, `KODE_PIN` (fallback ke nilai lama demi kompatibilitas).

## B. Session hardening

- **Session fixation:** tidak ada rotasi session id saat login. Ditambahkan
  `session_regenerate_id(true)` pada login staf (`includes/auth.php` → `do_login_with_role`)
  dan login siswa (`periksa_login.php`).
- **Cookie session:** `epoin_ensure_session()` kini menyetel `HttpOnly`, `SameSite=Lax`, dan
  `Secure` (saat HTTPS) sebelum `session_start()`.

## C. File yang diubah

```
admin/HOSTING.php            guard admin + _is_admin nyata + PIN via env
admin/sekolah.php            guard admin + _is_admin nyata
admin/export_csv.php         guard admin + _is_admin nyata
admin/KODE.php               guard admin + _is_admin nyata + PIN via env
admin/db_check_nh.php        guard admin + fix path include
includes/auth.php            session_regenerate_id saat login staf
periksa_login.php            session_regenerate_id saat login siswa
includes/epoin_security.php  hardening cookie di epoin_ensure_session()
.env.example                 dokumentasi HOSTING_RESET_PIN / KODE_PIN / DEPLOY_WEBHOOK_SECRET
```

Semua file lulus `php -l` (no syntax errors).

## D. Tindak lanjut yang DISARANKAN (belum dikerjakan di patch ini)

1. Set `HOSTING_RESET_PIN` & `KODE_PIN` di `.env` production (jangan pakai default).
2. Sediakan konfigurasi keamanan **Nginx** setara `.htaccess` (aaPanel sering Nginx): blok
   `.env/.sql/.git`, security headers, paksa HTTPS + aktifkan HSTS.
3. Rate-limit / lockout pada login (anti brute-force NIS & akun staf).
4. Hapus file demo/legacy dari webroot: `test_deploy.php`, `admin/cbt_pro_demo.php`,
   `admin_lama.php`, `index_lama.php`, `admin/user.php` (duplikat terdeprekasi).
5. Seragamkan kunci session (`$_SESSION['id']` vs `user_id` vs `uid`).
