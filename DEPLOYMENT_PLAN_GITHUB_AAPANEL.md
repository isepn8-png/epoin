# DEPLOYMENT PLAN — GitHub → aaPanel VPS

**Asal:** Laragon `C:\laragon\www\epoin`  
**Target:** VPS dengan aaPanel  
**PHP lokal:** 8.3 | **MySQL lokal:** 8.4.3 port 3308 | **DB:** `epoin_local`  

> Panduan ini **tidak** menjalankan perubahan DB/kode — hanya dokumentasi prosedur.

---

## A. PERSIAPAN LOCAL

### 1. Backup database

```powershell
cd C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin
.\mysqldump.exe -u root -P 3308 epoin_local > C:\backup\epoin_local_pre_github.sql
```

Simpan file **di luar** folder project. Jangan commit ke Git.

### 2. Cek aplikasi jalan

- [ ] `http://localhost:8088/epoin/login.php` — halaman login tampil
- [ ] Login admin/guru — redirect `admin/index.php`
- [ ] Login siswa — redirect `siswa/index.php`
- [ ] Modul e-Tugas (jika dipakai) — create + submit + rekap

### 3. Cek config database

File: `config/database.php` + `.env`

| Variabel | Local contoh |
|----------|----------------|
| `APP_ENV` | `local` |
| `DB_HOST` | `127.0.0.1` |
| `DB_PORT` | `3308` |
| `DB_DATABASE` | `epoin_local` |
| `DB_USERNAME` | `root` |
| `DB_PASSWORD` | `[REDACTED]` atau kosong |

### 4. Cek file sensitif

- [ ] `.env` **tidak** akan di-commit
- [ ] Tidak ada `*.sql`, `*.zip` di staging
- [ ] Hapus atau exclude `admin/phpinfo.php` sebelum production
- [ ] Credential di kode = `[REDACTED]` atau pindah ke `.env`

### 5. Cek folder upload

- Catat isi `uploads/` (jika ada) — akan di-sync terpisah ke VPS, **bukan** via Git.

---

## B. GITHUB READINESS — `.gitignore` rekomendasi

Gabungkan dengan `.gitignore` existing:

```gitignore
# Environment & secrets
.env
.env.*
!.env.example

# Database dumps & backups
*.sql
!database/manual-migrations/*.sql
*.sql.gz
*.zip
*.rar
*.7z
backup/
backups/

# Uploads & user content
uploads/*
!uploads/.gitkeep

# Logs & cache
*.log
logs/
cache/
tmp/

# OS & IDE
.DS_Store
Thumbs.db
.idea/
.vscode/
*.swp

# Tests (jangan deploy ke production)
tests/

# Optional: vendor jika tidak dipakai
# vendor/

# Node (jika nanti ada)
node_modules/

# Laragon / local only
php_errors.log
```

**Jangan masuk GitHub:**

| Item | Alasan |
|------|--------|
| `.env` | Credential |
| Dump SQL | Data siswa + password hash |
| `uploads/*` | Konten user |
| Backup zip | Ukuran + sensitif |
| `tests/` | QA internal |

**Boleh masuk GitHub:**

- Source PHP (`admin/`, `siswa/`, `includes/`, root handlers)
- `assets/` (meski besar)
- `database/manual-migrations/*.sql`
- `docs/`, `.env.example`
- `.gitignore`, blueprint docs

---

## C. `.ENV` / CONFIG PRODUCTION

Project memakai `.env` via `includes/env.php` — **bukan** Laravel `.env` penuh, tapi cukup untuk DB + `APP_ENV`.

### Template production (di VPS, bukan di Git)

```ini
APP_ENV=production
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=epoin_prod
DB_USERNAME=epoin_user
DB_PASSWORD=[REDACTED]
```

### Perbedaan localhost vs VPS

| Setting | Localhost (Laragon) | VPS (aaPanel) |
|---------|---------------------|---------------|
| `APP_ENV` | `local` | `production` |
| `DB_PORT` | `3308` | `3306` |
| Document root | `C:\laragon\www\epoin` | `/www/wwwroot/domain.com` |
| Error display | On (dev) | Off |
| SSL | Opsional 8448 | Let's Encrypt wajib |
| `open_basedir` | — | Sesuai aaPanel |

**Jika tidak pakai `.env` di VPS:** edit `config/database.php` hanya di server (file tidak di-overwrite deploy script).

---

## D. PUSH KE GITHUB

```powershell
cd C:\laragon\www\epoin

git init
git branch -M main

# Verifikasi status — pastikan .env tidak ter-track
git status

git add .
git commit -m "Initial commit: EPOIN application source and documentation"

git remote add origin https://github.com/ORGANISASI/epoin.git
git push -u origin main
```

Ganti URL remote sesuai repositori Anda. Gunakan SSH atau PAT `[REDACTED]` — jangan commit token.

---

## E. SETUP AAPANEL

### 1. Create website

- **Domain:** `epoin.sekolahanda.sch.id` (contoh)
- **Document root:** `/www/wwwroot/epoin.sekolahanda.sch.id`
- **PHP version:** 8.1 atau 8.3
- **Run directory:** root project (sama seperti Laragon — `index.php` di root)

### 2. SSL

- aaPanel → SSL → Let's Encrypt → force HTTPS

### 3. Rewrite

Apache (`.htaccess` jika ada) atau Nginx:

- Blok akses `.env`, `.git`, `*.sql`
- Opsional: redirect HTTP → HTTPS

Contoh Nginx (snippet):

```nginx
location ~ /\.(env|git) { deny all; }
location ~ \.sql$ { deny all; }
location ^~ /uploads/ {
    # jangan allow PHP execution
    location ~ \.php$ { deny all; }
}
```

### 4. Database

1. aaPanel → Database → Create `epoin_prod` + user dedicated
2. Import dump via phpMyAdmin **atau** SSH:

```bash
mysql -u epoin_user -p epoin_prod < /path/to/backup.sql
```

3. Jalankan migrasi e-Tugas jika belum ada di dump:

```bash
mysql -u epoin_user -p epoin_prod < database/manual-migrations/2026-05-17-001-create-etugas-tables.sql
```

### 5. File `.env` di VPS

```bash
cd /www/wwwroot/epoin.sekolahanda.sch.id
cp .env.example .env
nano .env   # isi credential production [REDACTED]
chmod 600 .env
```

### 6. Permission folder

```bash
chown -R www:www /www/wwwroot/epoin.sekolahanda.sch.id
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod -R 775 uploads   # jika folder upload dipakai
chmod 600 .env
```

---

## F. AUTO DEPLOY GitHub → aaPanel

### Strategi

- **Webhook** aaPanel / **Git pull** via cron / script `deploy.sh` di VPS.
- Branch: `main` (atau `production`).

### Script deploy aman (`deploy.sh` contoh)

```bash
#!/bin/bash
set -euo pipefail

APP_DIR="/www/wwwroot/epoin.sekolahanda.sch.id"
BACKUP_DIR="/www/backup/epoin"
LOG_FILE="/www/backup/epoin/deploy.log"
REPO_URL="git@github.com:ORGANISASI/epoin.git"

cd "$APP_DIR"

echo "[$(date -Iseconds)] Deploy start" >> "$LOG_FILE"

# Backup kode (exclude uploads & .env)
tar -czf "$BACKUP_DIR/code_$(date +%Y%m%d_%H%M%S).tar.gz" \
  --exclude='.env' --exclude='uploads' --exclude='.git' .

# Pull — jangan hapus file lokal yang dilindungi
git fetch origin
git reset --hard origin/main

# Restore file yang TIDAK boleh di-overwrite
# (.env dan uploads tidak ada di repo — tetap aman jika tidak di-delete)

# Permission
chown -R www:www "$APP_DIR"
chmod 600 .env 2>/dev/null || true
chmod -R 775 uploads 2>/dev/null || true

# Migrasi manual (opsional, idempotent)
if [ -f database/manual-migrations/2026-05-17-001-create-etugas-tables.sql ]; then
  mysql -u epoin_user -p'[REDACTED]' epoin_prod \
    < database/manual-migrations/2026-05-17-001-create-etugas-tables.sql 2>>"$LOG_FILE" || true
fi

echo "[$(date -Iseconds)] Deploy done" >> "$LOG_FILE"
```

### Aturan deploy

| Lakukan | Jangan |
|---------|--------|
| `git pull` / `reset --hard` | Hapus `.env` |
| Backup tar sebelum pull | Hapus `uploads/` |
| Log ke `deploy.log` | Commit `.env` ke repo |
| chmod setelah deploy | Import dump production otomatis tanpa backup |

### Clear cache

- Tidak ada OPcache clear khusus di app — restart PHP-FPM aaPanel setelah deploy besar.

---

## G. ROLLBACK PLAN

### Rollback kode

```bash
cd /www/wwwroot/epoin.sekolahanda.sch.id
tar -xzf /www/backup/epoin/code_YYYYMMDD_HHMMSS.tar.gz
# atau
git reset --hard <commit-hash-sebelumnya>
```

### Rollback database

```bash
mysql -u epoin_user -p epoin_prod < /www/backup/epoin/db_YYYYMMDD.sql
```

### Urutan rollback disarankan

1. Maintenance mode (halaman statis atau aaPanel pause site)
2. Restore DB jika deploy menyentuh schema/data
3. Restore kode dari tar atau git tag
4. Verifikasi `.env` dan `uploads/` masih utuh
5. Test login + 1 modul kritis

---

## H. TESTING SETELAH DEPLOY

| # | Cek | Expected |
|---|-----|----------|
| 1 | HTTPS login | Sertifikat valid, form tampil |
| 2 | Login admin | Dashboard KPI |
| 3 | Login siswa | Dashboard poin |
| 4 | Input pelanggaran/prestasi | Data tersimpan |
| 5 | Absensi harian | Simpan + final |
| 6 | Nilai / rapor | Generate tanpa error |
| 7 | e-Tugas | Create, submit, rekap |
| 8 | Export CSV | File terdownload |
| 9 | Import siswa | Hanya jika dipakai |
| 10 | Upload file | File masuk `uploads/`, tidak executable |
| 11 | `.env` tidak accessible | HTTP 403/404 |
| 12 | `phpinfo.php` | 404 atau dihapus |
| 13 | Error log | Tidak ada stack trace di browser |
| 14 | Permission upload | Writable oleh `www` |

---

## Referensi internal

- `docs/LOCAL_SETUP.md` — setup Laragon
- `docs/deployment-manifests/2026-05-17-etugas-full-manual-hosting-deploy.md` — manifest e-Tugas
- Blueprint: `PROJECT_BLUEPRINT_EPOIN.md`, `SECURITY_AUDIT_EPOIN.md`

---

*Setelah deploy pertama, dokumentasikan URL production, versi PHP, dan lokasi backup di wiki internal (tanpa menulis password).*
