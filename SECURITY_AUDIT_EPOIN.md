# SECURITY AUDIT — EPOIN

**Mode:** Read-only code review  
**Scope:** Root, `admin/`, `siswa/`, `includes/`, `config/`  
**Tanggal:** 2026-05-19  

> Credential, password, token, dan API key ditulis sebagai `[REDACTED]`.

---

## A. AUTHENTICATION

### Login siswa

| Aspek | Temuan |
|-------|--------|
| File | `periksa_login.php` (dipanggil dari `periksa_unified.php`) |
| Metode | POST `nis` + `password` |
| Password storage | **MD5** (`md5($_POST['password'])`) |
| Query | **String concatenation** — `nis` dan hash password langsung di SQL |
| Session | `$_SESSION['level'] = 'siswa'`, `$_SESSION['id']`, dll. |
| Remember me | Tidak teridentifikasi |

**Dampak:** SQL injection pada NIS; password mudah di-crack (MD5); tidak ada `password_verify`.

### Login staff (admin/guru/TAS)

| Aspek | Temuan |
|-------|--------|
| File | `periksa_admin.php`, `includes/auth.php` |
| Query user | **Prepared statement** (`username` bound) |
| Password | **Dual:** `password_verify` (bcrypt) **atau** MD5 legacy compare |
| Session | `user_id`, `roles[]`, `perms[]`, `sekolah_id` |
| RBAC bootstrap | `bootstrap_rbac_for_user_id()` — beberapa query **interpolasi `$user_id`** tanpa prepared |

### Logout

- `admin/logout.php`, `siswa/logout.php` — destroy session (perlu verifikasi regenerasi session id).

### Rekomendasi auth

1. Migrasi semua password ke `password_hash(PASSWORD_DEFAULT)`.
2. Ganti `periksa_login.php` ke prepared statement + `password_verify`.
3. Paksa reset password siswa setelah migrasi hash.

---

## B. AUTHORIZATION

### Role

| Level | Folder | Guard |
|-------|--------|-------|
| Siswa | `siswa/*` | `siswa/header.php` — `$_SESSION['level'] === 'siswa'` |
| Staff | `admin/*` | `admin/header.php` — `ensure_logged_in()`, role helpers |

### Permission

- RBAC: `can($perm_key)`, `user_has_role()` di `includes/auth.php`.
- **Inkonsistensi:** tidak semua halaman admin memanggil `can()` — banyak hanya cek login.
- e-Tugas: scope `pengampu_mapel` + role admin — **lebih ketat**.

### Halaman rawan (tanpa login / direct URL)

| Risiko | File contoh |
|--------|-------------|
| **Tinggi** | `admin/phpinfo.php` — info server jika tidak diblokir |
| **Tinggi** | `admin/tools/reset_epoin.php` — reset data jika ada |
| **Sedang** | Handler POST tanpa CSRF di modul lama |
| **Sedang** | Export CSV dengan parameter GET |

**Direct URL:** Semua file `.php` di `admin/` dapat diakses langsung jika session valid — tidak ada route whitelist.

---

## C. SQL INJECTION

### Pola berbahaya (contoh)

| Status | Lokasi | Masalah |
|--------|--------|---------|
| **Critical** | `periksa_login.php` | `WHERE nis='$nis' AND password='$pass'` dari `$_POST` |
| **High** | `includes/auth.php` | `bootstrap_rbac_for_user_id`: `WHERE ur.user_id = $user_id` |
| **High** | Banyak `admin/*.php` | `mysqli_query($koneksi, "... $var ...")` dengan GET/SESSION |
| **Medium** | `admin/index.php` | KPI queries dengan variabel session/GET |
| **Aman** | `includes/auth.php` login | Prepared statement |
| **Aman** | `includes/etugas_helpers.php` | Prepared statements + binding |

### Input langsung ke SQL

- `$_POST['nis']`, `$_GET['id']`, `$_SESSION['id']` sering di-concat tanpa `(int)` cast.
- Beberapa file pakai `mysqli_real_escape_string` — **tidak cukup** untuk semua konteks.

### Rekomendasi

- Standarisasi: semua query pakai prepared statements atau minimal `(int)` untuk ID numerik.
- Audit otomatis: grep `mysqli_query.*\$` di `admin/` dan `siswa/`.

---

## D. XSS (Cross-Site Scripting)

### Area risiko

| Area | Risiko |
|------|--------|
| Echo data siswa/nama guru | Tanpa `htmlspecialchars` di beberapa tabel admin |
| Flash message `$_GET['alert']` | `login.php` — perlu escaping konsisten |
| e-Tugas jawaban/link | Helper ada sanitasi link; teks perlu escape saat render |

### Positif

- `admin/sekolah.php` punya helper `esc()` = `htmlspecialchars`.
- Modul e-Tugas umumnya escape output di UI baru.

### Rekomendasi

- Policy: semua output dinamis → `htmlspecialchars($s, ENT_QUOTES, 'UTF-8')`.
- Content-Security-Policy header di production.

---

## E. CSRF

| Form / aksi | CSRF token |
|-------------|------------|
| Login | **Tidak** |
| CRUD admin lama | **Mayoritas tidak** |
| e-Tugas (`etugas_act.php`, siswa submit) | **Ada** (`csrf_token`, `etugas_verify_csrf`) |
| Upload / import | **Perlu verifikasi per file** |
| `admin/sekolah.php` backup/restore | **Risiko tinggi** jika tanpa CSRF |

**Rekomendasi:** Token CSRF global di `admin/header.php` + validasi di semua POST.

---

## F. FILE UPLOAD

### Temuan umum

| Cek | Status |
|-----|--------|
| Validasi ekstensi | Bervariasi per modul — e-Tugas lebih ketat |
| Validasi MIME | Tidak konsisten di modul lama |
| Ukuran file | Perlu cek `upload_max_filesize` di php.ini |
| Lokasi | `uploads/` atau subfolder project |
| PHP shell | Risiko jika ekstensi `.php` bisa di-upload dan di-serve |
| Akses publik | Document root = project root → **uploads bisa diakses langsung** jika tidak dilindungi `.htaccess` |

### Rekomendasi

1. Simpan upload **di luar** document root atau blok eksekusi PHP di folder upload (Nginx/Apache).
2. Whitelist ekstensi: jpg, png, pdf — tolak `.php`, `.phtml`.
3. Rename file random; jangan pakai nama user.

---

## G. CONFIG EXPOSURE

| Item | Risiko | Mitigasi |
|------|--------|----------|
| `.env` | Credential DB | Sudah di `.gitignore` — jangan commit |
| `config/database.php` | Baca dari env | OK jika `.env` aman |
| `*.sql` backup | Full data leak | `.gitignore` — simpan offline |
| `.zip` / `.rar` | Archive backup | Di-ignore |
| Log PHP/Apache | Path disclosure | Jangan di web root |
| Hardcoded password | Cek grep `password =` | Tidak boleh literal di kode |
| `admin/phpinfo.php` | **Critical** | Hapus atau block di production |

---

## H. ERROR / DEBUG

| Environment | Perilaku |
|-------------|----------|
| `APP_ENV=local` | `mysqli_report(MYSQLI_REPORT_ERROR \| STRICT)` — error detail ke browser |
| `APP_ENV=production` | Pesan generik ke user; detail ke `error_log` |

**Risiko:** Jika `.env` production salah set `local`, stack trace + query bocor.

---

## I. PRIORITAS PERBAIKAN

| Prioritas | Masalah | File terkait | Dampak | Rekomendasi | Kesulitan |
|-----------|---------|--------------|--------|-------------|-----------|
| **Critical** | SQL injection login siswa | `periksa_login.php` | Account takeover, data leak | Prepared stmt + password_hash | Sedang |
| **Critical** | MD5 password siswa | `siswa` table, `periksa_login.php` | Brute force cepat | Migrasi bcrypt/argon2 | Sedang–Tinggi |
| **Critical** | phpinfo / reset tools exposed | `admin/phpinfo.php`, `admin/tools/*` | Full server compromise | Hapus atau IP whitelist | Mudah |
| **High** | Raw SQL di modul input poin | `admin/input_*.php`, dll. | Data manipulation | Prepared statements | Tinggi (banyak file) |
| **High** | CSRF pada POST admin | Seluruh `admin/*_act.php` | Unauthorized action | Global CSRF middleware | Sedang |
| **High** | RBAC tidak di semua halaman | `admin/*.php` | Privilege escalation | `can()` di setiap aksi | Tinggi |
| **Medium** | XSS output | Banyak view admin | Session hijack display | htmlspecialchars policy | Sedang |
| **Medium** | Upload tanpa hardening | upload handlers | Remote code exec | Whitelist + path luar webroot | Sedang |
| **Medium** | MD5 legacy staff password | `includes/auth.php` | Weak crypto | Force password reset | Sedang |
| **Low** | Session fixation | logout/login | Session hijack | `session_regenerate_id` | Mudah |
| **Low** | Missing security headers | Apache/Nginx config | Clickjacking, MIME sniff | HSTS, CSP, X-Frame-Options | Mudah |

---

## Ringkasan posture

| Area | Skor | Catatan |
|------|------|---------|
| Auth staff | **Baik–Sedang** | Prepared login, bcrypt support |
| Auth siswa | **Buruk** | MD5 + SQLi |
| Authorization | **Sedang** | RBAC ada, enforcement tidak merata |
| SQLi | **Buruk** di legacy | e-Tugas lebih aman |
| XSS | **Sedang** | Tidak konsisten |
| CSRF | **Buruk** kecuali e-Tugas | |
| Upload | **Sedang–Buruk** | Perlu hardening global |
| Config | **Baik** jika `.env` tidak bocor | |

---

*Audit ini tidak menggantikan penetration test profesional. Setelah perbaikan Critical/High, disarankan retest manual + OWASP ZAP baseline scan.*
