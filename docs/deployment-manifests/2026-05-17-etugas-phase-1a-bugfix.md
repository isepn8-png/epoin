# Deployment Manifest — E-Tugas Phase 1A Bugfix

| Field | Value |
|-------|--------|
| **Manifest ID** | `2026-05-17-etugas-phase-1a-bugfix` |
| **Type** | Hotfix (runtime SQL error) |
| **SQL import required** | **No** |

---

## 1. Purpose

Fix fatal SQL error on e-Tugas placeholder pages caused by `SHOW TABLES LIKE ?` in `etugas_tables_ready()`. Pages must load with info/warning cards, not raw SQL errors.

---

## 2. Files changed

| File | Action |
|------|--------|
| `includes/etugas_helpers.php` | **Replace** on server |

**Unchanged (verify only):**

- `admin/etugas.php`
- `siswa/tugas_saya.php`

---

## 3. SQL import required

**No.** Database schema unchanged.

---

## 4. Manual hosting upload list

1. Upload `includes/etugas_helpers.php` (overwrite existing Phase 1A file).
2. Clear PHP OPcache if enabled.
3. Test `admin/etugas.php` and `siswa/tugas_saya.php`.

---

## 5. Rollback steps

1. Restore previous `includes/etugas_helpers.php` from backup (pre-bugfix version).
2. **Note:** Rollback restores the broken `SHOW TABLES LIKE ?` behavior — only rollback if this file must be reverted entirely.

No database rollback needed.

---

## 6. Validation results

| Check | Result |
|-------|--------|
| `php -l includes/etugas_helpers.php` | PASS |
| `php -l admin/etugas.php` | PASS |
| `php -l siswa/tugas_saya.php` | PASS |
| `information_schema` count for both tables | PASS (2) on local |
| `etugas_tables_ready()` CLI | PASS → `true` |

---

## 7. Browser test results

| Test | Expected | Notes |
|------|----------|-------|
| Admin login → `admin/etugas.php` | Placeholder card, DB status green | Requires manual login |
| Siswa login → `siswa/tugas_saya.php` | Empty state, no fatal | Requires manual login |
| Tables missing (if migration not run) | Yellow “Migrasi SQL” label | No SQL fatal |

Automated browser test without credentials: unauthenticated requests redirect to login (expected).

---

## Related report

`docs/ai-agent-reports/2026-05-17-etugas-phase-1a-bugfix.md`
