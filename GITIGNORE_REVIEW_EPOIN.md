# .gitignore Review — EPOIN

**Tanggal:** 2026-05-22  
**File:** `.gitignore` (root project)

---

## Checklist requirement

| Rule | Required | In `.gitignore` | Status |
|------|----------|-----------------|--------|
| `.env` | ✅ | `.env` | ✅ |
| `.env.*` | ✅ | `.env.*` | ✅ |
| `!.env.example` | ✅ | `!.env.example` | ✅ |
| `*.bak` | ✅ | `*.bak` | ✅ |
| `*.bak.*` | ✅ | `*.bak.*` | ✅ |
| `*.stage1b.bak` | ✅ | `*.stage1b.bak` | ✅ |
| `*.pre-stage1b.bak` | ✅ | `*.pre-stage1b.bak` | ✅ **ditambahkan** |
| `*.sql` | ✅ | `*.sql` | ✅ |
| `!database/manual-migrations/*.sql` | ✅ | `!database/manual-migrations/*.sql` | ✅ |
| `*.zip` | ✅ | `*.zip` | ✅ |
| `*.rar` | ✅ | `*.rar` | ✅ |
| `*.7z` | ✅ | `*.7z` | ✅ |
| `*.log` | ✅ | `*.log` | ✅ |
| `uploads/*` | ✅ | `uploads/*` | ✅ |
| `!uploads/.gitkeep` | ✅ | `!uploads/.gitkeep` | ✅ |
| `backup/` | ✅ | `backup/` | ✅ |
| `backups/` | ✅ | `backups/` | ✅ |
| `node_modules/` | ✅ | `node_modules/` | ✅ |
| `tests/` | ✅ | `tests/` | ✅ |
| `vendor/` | **Jangan ignore** | ~~`vendor/`~~ **dihapus** | ✅ **diperbaiki** |

---

## Perubahan pada audit ini

### Sebelum (masalah)

```gitignore
vendor/
```

Tanpa `composer.json` di root, meng-ignore `vendor/` akan membuat deploy/VPS **rusak** (`siswa_import_act.php` gagal load autoload).

### Sesudah

```gitignore
# vendor/ DI-COMMIT (tidak ada composer.json di root; lihat VENDOR_COMPOSER_DECISION_EPOIN.md)
# vendor/
```

Tambahan pola QA:

```gitignore
_retest_*.php
_read_*.php
_verify_*.php
_qa_*.php
```

---

## Catatan `tests/`

Folder `tests/` di project berisi harness QA:

- `etugas_multikelas_qa_harness.php`
- `etugas_phase2_qa_harness.php`
- dll.

Di-ignore sesuai spesifikasi — **tidak masuk repo**. Jika tim ingin menyimpan harness di Git, hapus baris `tests/` dari `.gitignore` secara sengaja di fase berikutnya.

---

## Verifikasi `uploads/`

| Rule | Efek |
|------|------|
| `uploads/*` | Semua file di bawah `uploads/` diabaikan |
| `!uploads/.gitkeep` | Hanya `.gitkeep` yang boleh di-track |

File contoh yang **harus tetap untracked:**

- `uploads/rapor_sts/2025/2026/kelas_9/rapor_*.html` (37+ file saat audit)

---

## SQL exception

Hanya file di `database/manual-migrations/` yang boleh di-commit, contoh:

- `2026-05-17-001-create-etugas-tables.sql`

Dump full database (`epoin_local.sql`, dll.) harus tetap di-ignore via `*.sql`.

---

## Rekomendasi opsional (belum diterapkan)

| Pola | Alasan |
|------|--------|
| `.cursor/` | IDE metadata |
| `Thumbs.db`, `desktop.ini` | Windows |
| `admin/tmp/` | Cache mPDF jika dipakai |

---

## Verifikasi setelah `git init`

```bash
git init
git status
git check-ignore -v uploads/rapor_sts/2025/2026/kelas_9/rapor_241.html
git check-ignore -v vendor/autoload.php
git check-ignore -v .env
git check-ignore -v uploads/.gitkeep
```

**Expected:**

- `rapor_241.html` → ignored  
- `vendor/autoload.php` → **not** ignored  
- `.env` → ignored  
- `uploads/.gitkeep` → **not** ignored  

---

*Lihat: [GITHUB_READINESS_EPOIN.md](GITHUB_READINESS_EPOIN.md)*
