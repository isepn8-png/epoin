# Vendor / Composer Decision — EPOIN

**Tanggal:** 2026-05-22  
**Konteks:** Native PHP monolith; deploy manual ke shared hosting / aaPanel  

---

## Temuan audit

| Item | Status |
|------|--------|
| `composer.json` (project root) | **Tidak ada** |
| `composer.lock` (project root) | **Tidak ada** |
| `vendor/autoload.php` | **Ada** |
| `vendor/composer/installed.json` | **Ada** (metadata install lama) |
| Root package di metadata | `codeigniter/framework` (placeholder — bukan CI app) |
| Perkiraan ukuran `vendor/` | ~**20–22 MB** (termasuk dev dependencies) |

### Paket utama di `vendor/`

| Folder | Fungsi | Dipakai produksi? |
|--------|--------|-------------------|
| `phpoffice/phpspreadsheet` | Import/export Excel | **Ya** — wajib |
| `ezyang/htmlpurifier` | Dependency PhpSpreadsheet | Ya (transitif) |
| `markbaker/matrix`, `maennchen/zipstream-php` | Dependency | Transitif |
| `fakerphp/faker` | Data dummy | Tidak (dev) |
| `phpunit/*`, `phpspec/*`, `sebastian/*` | Testing | Tidak (dev bloat) |

### Pemakaian di kode aplikasi

| File | Ketergantungan |
|------|----------------|
| `admin/siswa_import_act.php` | **Hard requirement** — `require vendor/autoload.php`; gagal jika tidak ada |
| `admin/rekap_bulanan.php` | Opsional `\Dompdf\Dompdf` — **tidak terinstall** di `vendor/` |
| `admin/laporan_pdf.php` | Opsional `\Mpdf\Mpdf` — **tidak terinstall** di `vendor/` |

---

## Opsi A vs Opsi B

### Opsi A — Commit `vendor/` (tanpa `composer.json` root)

**Cara:** Hapus `vendor/` dari `.gitignore`; commit seluruh folder `vendor/` ke Git.

| Pro | Kontra |
|-----|--------|
| Deploy copy/rsync **langsung jalan** | Repo besar (~20MB+); review PR berat |
| Tidak perlu Composer di VPS shared hosting | Termasuk `phpunit`/dev packages |
| Cocok dengan kondisi **sekarang** | Tidak ada lockfile reproduksibel di root |

### Opsi B — Buat `composer.json` + `composer install` di VPS

**Cara:** Tambah `composer.json` minimal; ignore `vendor/`; jalankan `composer install --no-dev` saat deploy.

| Pro | Kontra |
|-----|--------|
| Repo lebih bersih | Perlu Composer + PHP ext di server |
| Hanya prod deps dengan `--no-dev` | **Harus** buat & uji `composer.json` dulu |
| `composer.lock` reproducible | Risiko break jika VPS tanpa CLI Composer |

---

## Keputusan untuk project ini

### **Fase 1 (GitHub preparation — sekarang): Opsi A**

**Commit `vendor/`** ke repository private.

**Alasan:**

1. Tidak ada `composer.json` di root — Opsi B belum bisa dijalankan tanpa pekerjaan tambahan.
2. `siswa_import_act.php` **mati** tanpa `vendor/autoload.php`.
3. Tim deploy EPOIN memakai pola **upload file / rsync**, bukan pipeline Composer.
4. Risiko deploy rusak lebih besar jika `vendor/` di-ignore sekarang.

`.gitignore` sudah disesuaikan: baris `vendor/` **di-comment / dihapus**.

### **Fase 2 (disarankan 2–4 minggu setelah Git stabil): migrasi ke Opsi B**

Buat `composer.json` minimal di root, contoh:

```json
{
  "name": "sekolah/epoin",
  "description": "EPOIN native PHP",
  "require": {
    "php": ">=7.4",
    "phpoffice/phpspreadsheet": "^1.23"
  },
  "config": {
    "sort-packages": true
  }
}
```

Langkah:

```bash
composer install --no-dev
composer dump-autoload -o
```

Lalu:

1. Uji `siswa_import_act.php` import Excel.
2. Jika stabil, tambahkan `vendor/` ke `.gitignore` lagi.
3. Dokumentasikan di `docs/SETUP_ALIGNED.md`: wajib `composer install --no-dev` di VPS.

**Opsional terpisah** (bukan blocker):

- `composer require dompdf/dompdf` — untuk PDF di `rekap_bulanan.php`
- `composer require mpdf/mpdf` — untuk PDF di `laporan_pdf.php`

---

## Dompdf / mPDF / TCPDF

| Library | Ada di `vendor/`? | Status fitur |
|---------|-------------------|--------------|
| Dompdf | Tidak | `rekap_bulanan.php?pdf=1` menampilkan pesan install Composer |
| mPDF | Tidak | `laporan_pdf.php` fallback HTML jika tidak ada |
| TCPDF | Tidak (hanya suggest di PhpSpreadsheet dev) | Tidak dipakai |

Ini **bukan blocker** GitHub; fitur PDF opsional.

---

## Checklist deploy dengan Opsi A

| Langkah | VPS / aaPanel |
|---------|----------------|
| Clone / upload repo termasuk `vendor/` | ✅ |
| Copy `.env.example` → `.env` | ✅ |
| Isi `DB_*` produksi | ✅ |
| **Jangan** upload `uploads/rapor_sts` dari dev ke prod tanpa kebutuhan | ✅ |
| Jalankan migrasi SQL manual terpisah | Sesuai runbook |

---

## Ringkasan

| Pertanyaan | Jawaban |
|------------|---------|
| Commit `vendor/`? | **Ya** (fase 1) |
| Ignore `vendor/`? | **Tidak** (sampai ada `composer.json` + lock yang valid) |
| Pilihan terbaik jangka panjang | **Opsi B** setelah `composer.json` dibuat & diuji |
| Pilihan terbaik **hari ini** | **Opsi A** |

---

*Terhubung: [GITHUB_READINESS_EPOIN.md](GITHUB_READINESS_EPOIN.md), [GITIGNORE_REVIEW_EPOIN.md](GITIGNORE_REVIEW_EPOIN.md)*
