# EPOIN — Local setup (Laragon)

## Stack

| Item | Value |
|------|--------|
| Web | Apache `http://localhost:8088/epoin` |
| SSL (optional) | `https://localhost:8448/epoin` |
| MySQL port | `3308` |
| Database | `epoin_local` |
| Project root | `C:\laragon\www\epoin` |

## Database connection

1. Import your dump into `epoin_local` (62 tables expected).
2. Copy environment template:
   ```text
   copy .env.example .env
   ```
3. Defaults in `.env.example` match Laragon local:
   - `DB_HOST=127.0.0.1`
   - `DB_PORT=3308`
   - `DB_DATABASE=epoin_local`
   - `DB_USERNAME=root`
   - `DB_PASSWORD=` (empty)
   - `APP_ENV=local`

Connection flow:

- `koneksi.php` → `config/database.php` → `includes/env.php` (reads `.env` if present)
- Exposes **`$koneksi`** (mysqli). Some admin scripts also use **`$conn`** (aliased in `koneksi.php`).

Without a `.env` file, the same local defaults apply from `config/database.php`.

## Verify

```bash
php -l koneksi.php
php -l login.php
php -l periksa_unified.php
php -l periksa_admin.php
```

Open in browser:

- `http://localhost:8088/epoin/login.php`
- Submit login via `periksa_unified.php` (routes to `periksa_login.php` or `periksa_admin.php`)

## Production / VPS

1. Copy `.env.example` to `.env` on the server.
2. Set `APP_ENV=production` and real `DB_*` values (never commit `.env`).
3. Raw DB errors are hidden in production; check the PHP/Apache error log instead.

## Do not commit

`.env`, `.sql`, backups, logs — see root `.gitignore`.
