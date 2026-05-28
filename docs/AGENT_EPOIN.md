# 🤖 AGENT.md — Panduan AI Agent untuk Projek EPOIN

**Dokumen ini adalah panduan wajib bagi setiap AI agent** (ChatGPT, Gemini, Claude, Cursor, Copilot, atau coding assistant lainnya) **yang bekerja pada codebase EPOIN.**

Baca dokumen ini **sebelum** membuat perubahan kode apapun.

---

## 📌 1. Identitas Projek

| Aspek | Detail |
|-------|--------|
| **Nama** | EPOIN (E-POIN / E-Point Siswa) |
| **Jenis** | Sistem manajemen kesiswaan terpadu untuk sekolah |
| **Framework** | **Native PHP** — BUKAN Laravel/CodeIgniter/Yii |
| **Pola Arsitektur** | Page-based monolith (1 file PHP = 1 halaman) |
| **PHP** | 8.1+ (lokal teruji 8.3) |
| **Database** | MySQL 8.x / MariaDB, charset utf8mb4, 62 tabel+view |
| **Frontend** | AdminLTE 2 + Bootstrap 3 + jQuery |
| **Lokasi Lokal** | `C:\laragon\www\epoin` |
| **URL Lokal** | `http://localhost:8088/epoin/` |
| **DB Lokal** | `epoin_local` (port 3308) |

---

## ⚠️ 2. Aturan Fundamental

### 🔴 WAJIB DIPATUHI

1. **JANGAN mengubah file tanpa izin user.** Konfirmasi perubahan sebelum menerapkan.
2. **JANGAN menghapus komentar atau docstring** yang sudah ada, kecuali diminta.
3. **JANGAN mengubah `koneksi.php` atau `config/database.php`** tanpa alasan kuat — ini adalah bootstrap inti.
4. **JANGAN menambah dependency baru** (npm, composer, CDN) tanpa persetujuan.
5. **JANGAN commit `.env`, dump SQL, atau file credentials** ke Git.
6. **JANGAN mengubah pola arsitektur** (misalnya memperkenalkan framework/router) tanpa diskusi.
7. **Selalu backup-aware:** sebutkan risiko jika perubahan menyentuh data atau schema DB.
8. **Jaga backward compatibility** — banyak file lama masih aktif dipakai.

### 🟡 SANGAT DIANJURKAN

1. **Gunakan prepared statements** (`mysqli_prepare` / PDO) untuk SEMUA query yang menerima input user.
2. **Escape semua output** ke HTML dengan `htmlspecialchars($s, ENT_QUOTES, 'UTF-8')` atau `epoin_h($s)`.
3. **Validasi CSRF** di setiap handler POST — gunakan `epoin_csrf_token()` dan `epoin_csrf_validate()`.
4. **Cast integer** untuk semua ID dari `$_GET`/`$_POST` dengan `(int)`.
5. **Tulis komentar** dalam Bahasa Indonesia untuk kode bisnis, Bahasa Inggris untuk kode teknis.

---

## 🗂️ 3. Peta File & Navigasi

### 3.1 Entry Points

| File | Fungsi | Penting Untuk |
|------|--------|---------------|
| `index.php` | Redirect → `login.php` | — |
| `login.php` | UI login unified | Desain login |
| `periksa_unified.php` | Router POST login (siswa vs staff) | Alur autentikasi |
| `periksa_login.php` | Login siswa (⚠️ MD5 + SQLi) | **Prioritas patch** |
| `periksa_admin.php` | Login staff → `auth.php` | Autentikasi staff |
| `koneksi.php` | Bootstrap DB (`$koneksi`) | Jangan ubah |
| `logout.php` | Logout root | — |

### 3.2 Folder Utama

| Folder | Isi | Jumlah File |
|--------|-----|-------------|
| `admin/` | Panel staff (semua modul admin) | ~140 PHP |
| `siswa/` | Portal siswa | ~22 PHP |
| `includes/` | Shared logic (auth, security, helpers) | 13 PHP |
| `config/` | Database config | 1 PHP |
| `assets/` | AdminLTE, Bootstrap, plugins | Static |
| `database/manual-migrations/` | SQL migration files | 1 SQL |
| `library/fpdf185/` | FPDF library | Static |
| `security/` | Security headers | 1 PHP |
| `docs/` | Dokumentasi setup/deploy | MD files |

### 3.3 File Shared Kunci

| File | Peran | Ukuran |
|------|-------|--------|
| `includes/auth.php` | Login + RBAC + session helpers | 8KB |
| `includes/epoin_security.php` | CSRF, guard, validation, poin helpers | 9KB |
| `includes/epoin_sp_helpers.php` | SP logic (issue, validate, print) | 13KB |
| `includes/etugas_helpers.php` | e-Tugas business logic | 86KB |
| `includes/env.php` | Custom .env loader | 1.5KB |
| `includes/usage_helper.php` | Tenant quota tracking | 4.5KB |
| `includes/theme_brand.php` | School branding | 4.3KB |
| `admin/header.php` | Session guard + sidebar menu + globals | 88KB |
| `admin/footer.php` | JS + close HTML | 29KB |

### 3.4 File Berisiko Tinggi (⚠️ Hati-hati)

| File | Risiko | Catatan |
|------|--------|---------|
| `periksa_login.php` | 🔴 SQL Injection + MD5 | Harus dipatch |
| `admin/phpinfo.php` | 🔴 Info disclosure | Hapus di production |
| `admin/tools/reset_epoin.php` | 🔴 Data wipe | Hapus/block |
| `admin/poin_kolektif.php` | 🟡 Credential hardcoded | Pindah ke `.env` |
| `admin/sekolah.php` | 🟡 Backup/restore tanpa CSRF | Tambah CSRF |
| `admin/input_*_hapus.php` | 🟡 DELETE via GET tanpa auth check | Patch |

---

## 🏗️ 4. Konvensi Kode

### 4.1 PHP

```php
// ✅ BENAR — Prepared statement
$stmt = mysqli_prepare($koneksi, 'SELECT * FROM siswa WHERE siswa_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $siswaId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// ❌ SALAH — String concatenation
$result = mysqli_query($koneksi, "SELECT * FROM siswa WHERE siswa_id='$id'");

// ✅ BENAR — Output escaping
echo epoin_h($row['siswa_nama']);
// atau
echo htmlspecialchars($row['siswa_nama'], ENT_QUOTES, 'UTF-8');

// ❌ SALAH — Raw output
echo $row['siswa_nama'];

// ✅ BENAR — CSRF protection
// Di form:
echo epoin_csrf_field();
// Di handler:
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('form.php');
}

// ✅ BENAR — Integer casting untuk ID
$siswaId = (int) ($_GET['id'] ?? 0);
$kelasId = (int) ($_POST['kelas'] ?? 0);

// ✅ BENAR — Session guard
epoin_staff_guard();          // Redirect-based guard
epoin_staff_guard_json();     // JSON API guard (401)
epoin_require_post();         // Enforce POST method
```

### 4.2 Pola File Baru (Admin Module)

Ketika membuat file modul baru di `admin/`, ikuti pola ini:

```php
<?php
// admin/fitur_baru.php — Deskripsi singkat
// Terakhir diubah: YYYY-MM-DD

// 1. Include header (ini sudah include koneksi + session guard)
include 'header.php';

// 2. Include helper jika perlu
require_once __DIR__ . '/../includes/epoin_security.php';

// 3. Guard tambahan jika perlu
epoin_staff_guard();
// guard_roles(['administrator', 'guru']);  // Opsional: role-specific

// 4. Business logic (prepared statements!)
$stmt = mysqli_prepare($koneksi, 'SELECT ... WHERE id = ?');
// ...

// 5. HTML output
?>
<!-- Content Wrapper -->
<div class="content-wrapper">
    <section class="content-header">
        <h1>Judul Halaman</h1>
    </section>
    <section class="content">
        <!-- Konten -->
    </section>
</div>
<?php
// 6. Include footer
include 'footer.php';
```

### 4.3 Pola Handler POST (Action File)

```php
<?php
// admin/fitur_act.php — Handler POST untuk fitur
session_start();
require_once __DIR__ . '/../includes/epoin_security.php';
require_once __DIR__ . '/../koneksi.php';

// 1. Enforce method
epoin_require_post();

// 2. Session guard
$userId = epoin_staff_guard();

// 3. CSRF validation
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('fitur_form.php');
}

// 4. Input validation & sanitization
$nama  = trim($_POST['nama'] ?? '');
$id    = (int) ($_POST['id'] ?? 0);

if ($nama === '' || $id <= 0) {
    $_SESSION['flash_error'] = 'Data tidak valid.';
    header('Location: fitur_form.php');
    exit;
}

// 5. Prepared statement
$stmt = mysqli_prepare($koneksi, 'INSERT INTO tabel (nama, created_by) VALUES (?, ?)');
mysqli_stmt_bind_param($stmt, 'si', $nama, $userId);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// 6. Activity log
if ($ok) {
    $namaGuru = epoin_resolve_guru_nama($koneksi, $userId);
    epoin_log_aktivitas($koneksi, $userId, $namaGuru, "Menambah data: $nama");
    $_SESSION['flash_success'] = 'Data berhasil disimpan.';
} else {
    $_SESSION['flash_error'] = 'Gagal menyimpan data.';
}

// 7. Redirect
header('Location: fitur.php');
exit;
```

### 4.4 SQL

```sql
-- ✅ Penamaan tabel: snake_case, singular
-- ✅ Primary key: {tabel}_id ATAU id
-- ✅ Foreign key: merujuk ke {tabel}.{tabel}_id
-- ✅ Engine: InnoDB (untuk FK support)
-- ✅ Charset: utf8mb4

CREATE TABLE IF NOT EXISTS `fitur_baru` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `nama` VARCHAR(255) NOT NULL,
    `siswa_id` INT(11) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_siswa` (`siswa_id`),
    CONSTRAINT `fk_fitur_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`siswa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4.5 JavaScript / Frontend

```javascript
// ✅ Gunakan jQuery (sudah loaded di header.php)
$(document).ready(function() {
    // DataTables initialization
    $('#tabelData').DataTable({
        responsive: true,
        language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/id.json' }
    });
});

// ✅ AJAX pattern yang digunakan di EPOIN
$.ajax({
    url: 'handler.php',
    type: 'POST',
    data: { _csrf: csrfToken, param: value },
    dataType: 'json',
    success: function(res) {
        if (res.ok) { /* success */ }
        else { alert(res.msg); }
    }
});
```

---

## 🔐 5. Panduan Keamanan

### 5.1 Checklist Sebelum Submit Kode

- [ ] Semua query yang menerima user input menggunakan **prepared statements**
- [ ] Semua output HTML di-escape dengan `epoin_h()` atau `htmlspecialchars()`
- [ ] Semua form POST memiliki **CSRF token** (`epoin_csrf_field()`)
- [ ] Semua handler POST memvalidasi **CSRF** (`epoin_csrf_validate()`)
- [ ] Semua ID dari GET/POST di-cast ke `(int)`
- [ ] File handler POST memiliki **session guard** (`epoin_staff_guard()`)
- [ ] Tidak ada **credential hardcoded** dalam kode
- [ ] Tidak ada **file debug/info** (phpinfo, var_dump) tersisa
- [ ] Upload file memiliki **whitelist ekstensi** (jpg, png, pdf — bukan php)

### 5.2 Fungsi Security yang Tersedia

| Fungsi | Lokasi | Kegunaan |
|--------|--------|----------|
| `epoin_h($val)` | `epoin_security.php` | HTML escape |
| `epoin_csrf_token()` | `epoin_security.php` | Get/generate CSRF token |
| `epoin_csrf_field()` | `epoin_security.php` | Render hidden CSRF input |
| `epoin_csrf_validate()` | `epoin_security.php` | Validate CSRF dari POST/header |
| `epoin_csrf_fail_redirect($url)` | `epoin_security.php` | Flash error + redirect |
| `epoin_staff_guard()` | `epoin_security.php` | Session guard (redirect) |
| `epoin_staff_guard_json()` | `epoin_security.php` | Session guard (JSON 401) |
| `epoin_require_post()` | `epoin_security.php` | Enforce POST method |
| `epoin_verify_siswa_kelas()` | `epoin_security.php` | Verify student in class |
| `epoin_is_admin_session()` | `epoin_security.php` | Check admin role |
| `epoin_log_aktivitas()` | `epoin_security.php` | Insert audit log |
| `ensure_logged_in()` | `auth.php` | Guard login (redirect) |
| `user_has_role($key)` | `auth.php` | Check role by key |
| `can($permKey)` | `auth.php` | Check RBAC permission |
| `guard_roles([$roles])` | `auth.php` | Guard specific roles |
| `_verify_password()` | `auth.php` | Verify bcrypt or MD5 |

---

## 🗄️ 6. Database

### 6.1 Koneksi

```
.env → includes/env.php → config/database.php → koneksi.php → $koneksi (mysqli)
```

**Variabel koneksi global:** `$koneksi` (mysqli) dan `$conn` (alias)

### 6.2 Tabel Paling Penting

| Prioritas | Tabel | Kenapa Penting |
|-----------|-------|----------------|
| 🔴 P0 | `siswa` | Identitas + login siswa, FK dari semua modul |
| 🔴 P0 | `input_pelanggaran`, `input_prestasi` | Data transaksional inti EPOIN |
| 🔴 P0 | `pelanggaran`, `prestasi` | Master poin — salah edit = saldo rusak |
| 🟡 P1 | `kelas`, `kelas_siswa`, `ta` | Scope semua laporan dan filter |
| 🟡 P1 | `user`, `roles`, `user_roles` | Keamanan akses admin |
| 🟡 P1 | `sekolah`, `sekolah_license` | Multi-tenant & batas fitur |
| 🟢 P2 | `absensi_harian_detail` | Volume tinggi, data sensitif |
| 🟢 P2 | `etugas`, `etugas_pengumpulan` | Modul aktif terbaru |

### 6.3 Rumus Bisnis Inti

```sql
-- Saldo poin siswa (dihitung real-time, tidak disimpan)
saldo = SUM(prestasi.prestasi_point FROM input_prestasi)
      - SUM(pelanggaran.pelanggaran_point FROM input_pelanggaran)
      WHERE siswa = ?

-- Saldo negatif (untuk SP)
negSaldo = MAX(0, -saldo)

-- Ambang SP:
-- SP1: negSaldo >= 21
-- SP2: negSaldo >= 41 (+ SP1 sudah terbit)
-- SP3: negSaldo >= 61 (+ SP2 sudah terbit)
-- SP4: negSaldo >= 81 (+ SP3 sudah terbit)
```

### 6.4 Migrasi Database

- **Schema utama:** Dari dump SQL (tidak di repo)
- **Migrasi manual:** `database/manual-migrations/*.sql`
- **Auto-create:** `epoin_sp_ensure_schema()` bisa auto-create `sp_log`
- **Cara menambah migrasi:**
  1. Buat file di `database/manual-migrations/YYYY-MM-DD-NNN-deskripsi.sql`
  2. Gunakan `CREATE TABLE IF NOT EXISTS` atau `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`
  3. Idempotent — aman dijalankan berkali-kali

---

## 🔄 7. Alur Kerja Pengembangan

### 7.1 Menambah Fitur Baru

```
1. Analisa requirements → identifikasi tabel & file terkait
2. Jika perlu tabel baru → buat migrasi di database/manual-migrations/
3. Buat file PHP baru di admin/ atau siswa/
4. Include header.php (guard + layout)
5. Tulis business logic dengan prepared statements
6. Render HTML dalam layout AdminLTE
7. Include footer.php
8. Test manual di localhost
```

### 7.2 Memperbaiki Bug

```
1. Identifikasi file yang bermasalah
2. Cek apakah ada file _act.php, _update.php, _hapus.php terkait
3. Periksa session guard dan CSRF
4. Fix dengan mempertahankan backward compatibility
5. Test edge cases
```

### 7.3 Patch Keamanan

```
1. Identifikasi vulnerability (SQLi, XSS, CSRF, dll)
2. Buat patch minimal — JANGAN refactor seluruh file
3. Gunakan prepared statements untuk fix SQLi
4. Tambahkan epoin_h() untuk fix XSS
5. Tambahkan CSRF token/validate untuk fix CSRF
6. Dokumentasikan patch di commit message
```

---

## 🧪 8. Testing & QA

### 8.1 Test Manual Wajib

| # | Test Case | Expected |
|---|-----------|----------|
| 1 | Login admin | Dashboard KPI tampil |
| 2 | Login siswa | Dashboard poin tampil |
| 3 | Input pelanggaran | Data tersimpan, saldo berubah |
| 4 | Input prestasi | Data tersimpan, saldo berubah |
| 5 | Cetak SP | Surat tergenerate jika ambang terpenuhi |
| 6 | Absensi harian | H/I/S/A tersimpan |
| 7 | e-Tugas create | Tugas muncul di portal siswa |
| 8 | Siswa submit tugas | Pengumpulan terekam |
| 9 | Export Excel | File terdownload |
| 10 | Ganti password | Password baru berfungsi |

### 8.2 QA Harness (Existing)

- `tests/etugas_*_qa_harness.php` — CLI test untuk modul e-Tugas
- Jalankan: `php tests/etugas_phase2_qa_harness.php`

### 8.3 Security Test Checklist

```
□ Coba akses admin/*.php tanpa login → harus redirect
□ Coba SQL injection di form login → harus ditolak
□ Coba XSS di field nama → harus di-escape
□ Coba CSRF (submit form dari domain lain) → harus gagal
□ Coba akses .env via browser → harus 403/404
□ Coba upload file .php → harus ditolak
```

---

## 📊 9. Statistik Codebase

| Metrik | Nilai |
|--------|-------|
| File PHP (admin/) | ~140 |
| File PHP (siswa/) | ~22 |
| File PHP (includes/) | 13 |
| File PHP (root + config) | ~12 |
| **Total file PHP** | **~187** |
| **Total baris kode PHP** | **~60,372** |
| Tabel database | 59 + 3 view = **62** |
| Permission keys | 7 (fokus absensi) |
| SQL migration files | 1 |
| Modul fungsional | 8 |

---

## 📚 10. Dokumentasi Internal

### Di Root Projek

| File | Isi |
|------|-----|
| `BLUEPRINT.md` | Arsitektur & inventaris fitur lengkap |
| `PROJECT_BLUEPRINT_EPOIN.md` | Blueprint teknis |
| `DATABASE_BLUEPRINT_EPOIN.md` | Detail database |
| `SECURITY_AUDIT_EPOIN.md` | Audit keamanan |
| `MODUL_EPOIN_DEEP_ANALYSIS.md` | Analisis mendalam modul poin |
| `DEPLOYMENT_PLAN_GITHUB_AAPANEL.md` | Panduan deploy ke VPS |
| `BRIEF_UNTUK_CHATGPT.md` | Ringkasan untuk AI agent |
| `EPOIN_SECURITY_PATCH_REPORT.md` | Laporan patch keamanan |
| `GITHUB_READINESS_EPOIN.md` | Kesiapan push ke GitHub |
| `VENDOR_COMPOSER_DECISION_EPOIN.md` | Keputusan vendor/composer |
| `STAGE_1B_*.md` | Laporan stage 1B patch & retest |

### Di Folder docs/

| File/Folder | Isi |
|-------------|-----|
| `docs/LOCAL_SETUP.md` | Panduan setup lokal (Laragon) |
| `docs/SETUP_ALIGNED.md` | Config alignment |
| `docs/ai-agent-reports/` | Laporan dari AI agent sebelumnya |
| `docs/deployment-manifests/` | Manifest deploy per modul |

---

## 💡 11. Tips untuk Agent

### Ketika Diminta Menambah Fitur

1. **Periksa apakah fitur serupa sudah ada** — cari di `admin/` dan `siswa/`
2. **Ikuti pola yang sudah ada** — jangan perkenalkan pola baru
3. **Gunakan helper yang tersedia** di `includes/epoin_security.php`
4. **Jangan buat file besar** — pisah form, act, list, edit, hapus seperti pola existing
5. **Sertakan CSRF di setiap form** — ini non-negotiable untuk file baru

### Ketika Diminta Debug

1. **Cek `koneksi.php`** apakah DB terkoneksi
2. **Cek session** — `$_SESSION['level']`, `$_SESSION['id']`
3. **Cek `APP_ENV`** — `local` menampilkan error detail, `production` hanya log
4. **Cek prepared statement** — parameter binding harus cocok jumlah dan tipe
5. **Cek header.php** — sudah di-include? Menu muncul?

### Ketika Diminta Refactor

1. **Jangan refactor terlalu banyak sekaligus** — pecah jadi tahap kecil
2. **Pertahankan nama file dan URL** — URL mapping langsung ke file
3. **Jangan break session** — field `$_SESSION` dipakai di banyak tempat
4. **Test setiap file yang diubah** — tidak ada unit test otomatis
5. **Dokumentasikan perubahan** di commit message

### Context yang Sering Dibutuhkan

```
Koneksi DB:        $koneksi (mysqli), $conn (alias)
User ID aktif:     $_SESSION['id'] atau current_user_id()
Role aktif:        $_SESSION['level'], $_SESSION['roles']
Sekolah ID:        $GLOBALS['SEKOLAH_ID'] (dari header.php)
TA aktif:          Query dari tabel `ta` WHERE aktif
CSRF token:        epoin_csrf_token()
Base path admin:   /epoin/admin/
Base path siswa:   /epoin/siswa/
```

---

## 🚫 12. Anti-Patterns (Jangan Lakukan)

| ❌ Jangan | ✅ Sebagai gantinya |
|-----------|---------------------|
| `mysqli_query($koneksi, "...$_GET['id']...")` | `mysqli_prepare($koneksi, '...?')` + `bind_param` |
| `echo $data['nama']` | `echo epoin_h($data['nama'])` |
| `header("Location: $url")` tanpa `exit` | `header("Location: $url"); exit;` |
| Hardcode password di file PHP | Simpan di `.env`, baca via `epoin_env()` |
| DELETE via GET tanpa konfirmasi | POST + CSRF + konfirmasi JS |
| File PHP tanpa session check | Include `header.php` atau `epoin_staff_guard()` |
| `include 'file.php'` (relative) | `require_once __DIR__ . '/../file.php'` (absolute) |
| `md5($password)` untuk hash baru | `password_hash($password, PASSWORD_DEFAULT)` |
| `mysql_connect()` | `mysqli_connect()` (sudah di `koneksi.php`) |
| Buat tabel tanpa migrasi | Buat file di `database/manual-migrations/` |

---

## 📬 13. Komunikasi dengan User

Ketika berkomunikasi dengan pemilik projek EPOIN:

1. **Gunakan Bahasa Indonesia** — pemilik projek berbahasa Indonesia
2. **Jelaskan dampak perubahan** — sebutkan file yang terpengaruh
3. **Sebutkan risiko keamanan** jika relevan
4. **Berikan opsi** — bukan hanya satu solusi
5. **Tanyakan konfirmasi** sebelum mengubah:
   - File inti (`koneksi.php`, `auth.php`, `header.php`)
   - Schema database
   - Alur login
   - File yang dipakai banyak modul

---

*Dokumen ini adalah living document. Update ketika ada perubahan arsitektur, konvensi, atau pola kerja baru pada projek EPOIN.*
