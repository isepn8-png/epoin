# GitHub Readiness Audit — EPOIN

**Tanggal audit:** 2026-05-22  
**Lokasi:** `C:\laragon\www\epoin`  
**Konteks:** Stage 1B security **GO**; persiapan repo GitHub **private** (belum push)  
**Aksi auditor:** Read-only + penyesuaian `.gitignore` dan `uploads/.gitkeep` saja  

---

## Verdict

### **READY_WITH_NOTES**

Proyek **siap untuk `git init` dan commit lokal** setelah catatan di bawah diterapkan. **Belum** dijalankan `git init` atau push (sesuai instruksi).

| Blokir | Status |
|--------|--------|
| Secret / `.env` di tree | ✅ Bersih |
| File backup `*.bak` di tree | ✅ Tidak ditemukan |
| `uploads/` user data | ✅ Di-ignore; `.gitkeep` ditambahkan |
| `vendor/` vs `composer.json` | ⚠️ **Wajib commit `vendor/`** — hapus ignore `vendor/` (sudah diperbaiki di `.gitignore`) |
| Repo Git | ⚠️ Belum `git init` |

---

## 1. Git status awal

| Cek | Hasil |
|-----|--------|
| Folder `.git` ada? | **Tidak** (`Test-Path .git` → False) |
| `git status` | `fatal: not a git repository` |
| `git init` dijalankan? | **Tidak** (sesuai instruksi) |

---

## 2. Secret & backup scan

### 2.1 File sensitif di working tree

| Pola | Ditemukan di project? |
|------|------------------------|
| `.env` | **Tidak** |
| `.env.example` | **Ya** (aman — template tanpa secret produksi) |
| `*.bak` | **Tidak** (`dir /s /b` → File Not Found) |
| `*.stage1b.bak` | **Tidak** |
| `*.pre-stage1b.bak` | **Tidak** |
| `*.sql` (luar manual-migrations) | **Tidak** |
| `*.zip` / `*.rar` / `*.7z` | **Tidak** |
| `backup/` / `backups/` di root | **Tidak** |
| `_retest_*.php`, `_read_*.php`, `_verify_*.php` | **Tidak** |

### 2.2 Backup eksternal (di luar repo)

| Lokasi | Isi |
|--------|-----|
| `C:\laragon\backup\epoin_stage1_backup\` | 20 file Stage 1 (termasuk `poin_kolektif.php.bak` dengan kredensial lama) |

**Jangan** menyalin folder backup ke dalam project sebelum commit.

### 2.3 Kredensial di kode aktif

| Area | Hasil |
|------|--------|
| `config/database.php` | Memakai `epoin_env()` — tidak hardcode password produksi |
| `admin/poin_kolektif.php` | Memakai `epoin_get_pdo()` + `.env` |
| `koneksi.php` | Variabel dari `config/database.php` |

Tidak ditemukan pola kritis `mysqli_connect('host','user','password_plain')` di file aplikasi aktif yang discan.

### 2.4 `.env.example`

Placeholder kosong untuk `DB_PASSWORD=` — **boleh di-commit**.

---

## 3. Uploads

| Cek | Hasil |
|-----|--------|
| Folder `uploads/` | Ada |
| Isi | `uploads/rapor_sts/...` — banyak file HTML rapor (data user) |
| `uploads/*` di `.gitignore` | ✅ |
| `!uploads/.gitkeep` | ✅ |
| `uploads/.gitkeep` | **Ditambahkan** pada audit ini (folder kosong tetap ada di repo) |

**Isi `uploads/` tidak boleh masuk Git.**

---

## 4. Vendor / Composer (ringkas)

| File | Ada? |
|------|------|
| `composer.json` (root) | **Tidak** |
| `composer.lock` (root) | **Tidak** |
| `vendor/autoload.php` | **Ya** |
| Paket utama | `phpoffice/phpspreadsheet` (+ dependensi) |
| Dev bloat di `vendor/` | `phpunit`, `fakerphp`, dll. (dari install lama) |

**Penggunaan di aplikasi:**

| File | Library |
|------|---------|
| `admin/siswa_import_act.php` | **Wajib** `vendor/autoload.php` + PhpSpreadsheet |
| `admin/rekap_bulanan.php` | Opsional Dompdf (tidak ada di `vendor/`) |
| `admin/laporan_pdf.php` | Opsional mPDF (tidak ada di `vendor/`) |

**Keputusan:** Commit **`vendor/`** sekarang. Detail: [VENDOR_COMPOSER_DECISION_EPOIN.md](VENDOR_COMPOSER_DECISION_EPOIN.md).

---

## 5. `.gitignore` review

Diperbarui pada audit ini. Ringkasan: [GITIGNORE_REVIEW_EPOIN.md](GITIGNORE_REVIEW_EPOIN.md).

Perubahan penting:

- Tambah `*.pre-stage1b.bak`, `_retest_*.php`, `_read_*.php`, `_verify_*.php`, `_qa_*.php`
- **Hapus** ignore `vendor/` (agar deploy tidak rusak tanpa `composer.json`)

---

## 6. File yang layak commit

| Path | Catatan |
|------|---------|
| `admin/` | Panel admin/guru |
| `siswa/` | Panel siswa |
| `includes/` | Auth, security, helpers |
| `config/` | `database.php` (tanpa secret) |
| `assets/` | AdminLTE, CSS, JS |
| `database/manual-migrations/` | SQL migrasi terkontrol |
| `docs/` | Dokumentasi & deployment manifests |
| `gambar/`, `library/` | Asset/static lama (jika masih dipakai) |
| `vendor/` | **Wajib** untuk import Excel |
| Root `*.php` | `index.php`, `login.php`, `koneksi.php`, `periksa_*.php`, dll. |
| `.gitignore`, `.env.example` | Hygiene |
| `uploads/.gitkeep` | Placeholder folder |
| Dokumen audit/security `*.md` di root | Opsional tapi disarankan |

---

## 7. File yang tidak boleh commit

| Pola / path | Alasan |
|-------------|--------|
| `.env` | Secret DB |
| `uploads/**` (kecuali `.gitkeep`) | Data user |
| `*.bak`, `*.stage1b.bak`, `*.pre-stage1b.bak` | Backup / kredensial lama |
| `*.sql` dump penuh | Data DB |
| `tests/` | Harness QA lokal (di-ignore) |
| `_retest_*.php`, `_qa_*.php` | Skrip sementara |
| `backup/`, `backups/` | Arsip |
| `C:\laragon\backup\epoin_stage1_backup\` | Di luar repo |
| `cgi-bin/` | Biasanya tidak perlu (evaluasi saat commit) |

---

## 8. Perintah Git yang boleh dijalankan nanti

**Setelah** Anda review dokumen ini (masih **tanpa push**):

```bash
cd C:\laragon\www\epoin

git init

git status

# Pastikan tidak ada .env, uploads/rapor_sts, *.bak

git add .gitignore .env.example uploads/.gitkeep
git add admin siswa includes config assets database docs vendor
git add *.php gambar library security
# Tambahkan *.md dokumentasi jika diinginkan

git status

git commit -m "Initial commit: EPOIN native PHP (Stage 1 + 1B security patch)"
```

**Belum jalankan:**

```bash
git remote add origin git@github.com:ORG/epoin.git
git push -u origin main
```

---

## 9. Hal yang harus diperbaiki sebelum `git init`

| # | Item | Prioritas | Status audit |
|---|------|-----------|--------------|
| 1 | Hapus `vendor/` dari `.gitignore` | **P0** | ✅ Diperbaiki |
| 2 | Buat `uploads/.gitkeep` | **P0** | ✅ Ditambahkan |
| 3 | Pastikan tidak ada `.env` / `*.bak` | **P0** | ✅ OK |
| 4 | (Opsional) Tambah `composer.json` root | P1 | Rekomendasi fase 2 — lihat vendor doc |
| 5 | `git init` + review `git status` | P0 | Menunggu Anda |
| 6 | Buat repo GitHub **private** | P1 | Setelah commit lokal |

---

## 10. Yang sengaja menunggu (bukan blocker GitHub prep)

| Item | Catatan |
|------|---------|
| Migration DB | Setelah GitHub preparation |
| Deploy aaPanel | Setelah migration + staging |
| Push ke GitHub | Setelah commit lokal + review `git status` |
| Patch `periksa_login.php` | Track terpisah |

---

*Audit terkait: [STAGE_1B_GO_NO_GO_EPOIN.md](STAGE_1B_GO_NO_GO_EPOIN.md), [DEPLOYMENT_PLAN_GITHUB_AAPANEL.md](DEPLOYMENT_PLAN_GITHUB_AAPANEL.md)*
