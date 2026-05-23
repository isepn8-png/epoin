# E-Tugas Phase 1A — Foundation Implementation Report

**Date:** 2026-05-17  
**Scope:** Database tables, helpers, placeholder pages, menu links  
**Out of scope:** CRUD, siswa submit, guru review, hard delete

---

## Summary

Phase 1A delivers the structural foundation for **Pengumpulan Tugas Siswa (e-Tugas)**:

1. Two new InnoDB tables (`etugas`, `etugas_pengumpulan`) via manual migration SQL
2. Reusable helpers in `includes/etugas_helpers.php` (prepared statements, `function_exists` guards)
3. Placeholder UI pages for admin/guru and siswa
4. Single menu entry per portal in existing AdminLTE sidebars

---

## Files created

| File |
|------|
| `database/manual-migrations/2026-05-17-001-create-etugas-tables.sql` |
| `includes/etugas_helpers.php` |
| `admin/etugas.php` |
| `siswa/tugas_saya.php` |

## Files modified

| File | Change |
|------|--------|
| `admin/header.php` | Menu “Pengumpulan Tugas” under UJIAN & CBT |
| `siswa/header.php` | Menu “Tugas Saya” under Penilaian |
| `.gitignore` | `!database/manual-migrations/*.sql`, `*.zip`, `*.rar` |

---

## Helpers implemented

| Function | Purpose |
|----------|---------|
| `etugas_h()` | Output escape (htmlspecialchars) |
| `etugas_escape()` | mysqli escape |
| `etugas_user_is_admin()` | RBAC-compatible admin check |
| `etugas_user_is_guru()` | Guru role check |
| `etugas_get_active_ta()` | Active TA (`ta_status=1`) or latest |
| `etugas_get_siswa_kelas_aktif()` | Siswa → kelas via `kelas_siswa` + active TA |
| `etugas_get_guru_scope()` | `pengampu_mapel` rows for guru (optional TA filter) |
| `etugas_is_valid_url()` | http/https URL validation |
| `etugas_classify_link()` | drive / youtube / canva / docs / lainnya |
| `etugas_tables_ready()` | Both tables exist (for placeholder status) |

---

## Placeholder pages

### `admin/etugas.php`
- Uses `include 'header.php'` + `footer.php`
- Access: administrator or guru (`403` otherwise)
- Info card: module message, DB status, active TA, guru scope count

### `siswa/tugas_saya.php`
- Uses siswa header gate (`level === siswa`)
- Empty-state card; shows detected kelas if `kelas_siswa` exists

---

## Database

Migration path: `database/manual-migrations/2026-05-17-001-create-etugas-tables.sql`

Imported successfully on local `epoin_local`:

- `etugas`
- `etugas_pengumpulan`

Design notes:
- No FK constraints
- Indexes on filter columns
- `UNIQUE (etugas_id, siswa_id)` on submissions

---

## Deployment

See: `docs/deployment-manifests/2026-05-17-etugas-phase-1a-foundation.md`

---

## Suggested commit message

```
feat(etugas): Phase 1A foundation — tables, helpers, placeholder pages

Add etugas/etugas_pengumpulan migration, shared helpers, admin and siswa
placeholder pages with sidebar menu links. No CRUD or submission yet.
```

---

## Next: Phase 1B

Admin assignment CRUD (create/edit/update, soft status only; no hard delete).
