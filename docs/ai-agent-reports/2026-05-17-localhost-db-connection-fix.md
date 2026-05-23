# Localhost database connection fix — 2026-05-17

## Symptom

`periksa_unified.php` → `periksa_admin.php` / `periksa_login.php` failed with:

- `Undefined variable $host` (and `$user`, `$pass`, `$db`, `$port`) in `koneksi.php`
- `mysqli_sql_exception: connection actively refused`

## Root cause

1. `koneksi.php` called `mysqli_connect($host, …)` without defining credentials (or loading config), so PHP warned on undefined variables and `$port` was not applied.
2. Without a valid port, mysqli fell back to **3306** while Laragon MySQL listens on **3308** → connection refused.

## Fix (minimal)

- Added `includes/env.php` — lightweight `.env` loader.
- Added `config/database.php` — sets `$host`, `$user`, `$pass`, `$db`, `$port` from env with Laragon-safe defaults.
- Updated `koneksi.php` — loads config, `utf8mb4`, local vs production error handling, `$conn` alias.
- Added `.env.example`, `.gitignore`, `docs/LOCAL_SETUP.md`.

## Connection variables in codebase

| Variable | Usage |
|----------|---------|
| `$koneksi` | Primary (~139 files) |
| `$conn` | Some admin/deskripsi modules (aliased from `$koneksi`) |
| `$pdo` | Optional in a few files that create PDO separately if present |

## Validation

- `php -l` on `koneksi.php`, `login.php`, `periksa_unified.php`, `periksa_admin.php`
- CLI mysqli test: `127.0.0.1:3308` → `epoin_local` OK

## Remaining risks

- Production deploy requires `.env` with `APP_ENV=production` and real credentials.
- Legacy SQL in login paths may still use string interpolation (separate security pass).
- No `.env` in repo by design; each machine must copy `.env.example`.

## Suggested commit message

```
fix(db): load mysqli config from env with Laragon local defaults

Define DB host/port via config/database.php and .env so koneksi.php no longer
uses undefined variables or wrong MySQL port on localhost.
```
