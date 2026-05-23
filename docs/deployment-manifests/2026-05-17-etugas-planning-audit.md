# Deployment Manifest — E-Tugas (Planning)

| Field | Value |
|-------|--------|
| **Manifest ID** | `2026-05-17-etugas-planning-audit` |
| **Feature** | Pengumpulan Tugas Siswa (E-Tugas) |
| **Status** | PLANNING ONLY — nothing deployed yet |
| **Target environment (dev)** | Laragon — `http://localhost:8088/epoin` |
| **Target environment (prod)** | Shared hosting — manual file upload + phpMyAdmin SQL |
| **Database** | `epoin_local` (dev) / production DB name on hosting |

---

## Purpose

Document all files and database objects for the **E-Tugas** module before implementation, so manual shared-hosting deployment can be done incrementally with a clear checklist and rollback path.

This manifest is a **planning baseline**. Future manifests (e.g. `2026-05-XX-etugas-phase1.md`) will list only what changed in each release.

---

## Files inspected (read-only audit)

### Core / auth
- `koneksi.php`
- `config/database.php`
- `includes/env.php`
- `includes/auth.php`
- `includes/eps_helpers.php`
- `includes/theme_brand.php`
- `periksa_unified.php`
- `periksa_login.php`
- `periksa_admin.php`
- `login.php`

### Layout / navigation
- `admin/header.php`
- `admin/footer.php`
- `siswa/header.php`
- `siswa/footer.php`

### CRUD & filter references
- `admin/pengampu_mapel.php`
- `admin/mapel.php`
- `admin/kelas.php`
- `admin/siswa.php`
- `admin/ta.php`
- `admin/input_prestasi.php`
- `admin/pelanggaran.php`
- `admin/ujian_gform.php`
- `admin/laporan.php`
- `admin/ajax_get_kelas.php`
- `admin/ajax_get_siswa.php`
- `admin/get_kelas_by_ta.php`
- `siswa/ujian.php`

### Schema verification
- Live `SHOW COLUMNS` on `epoin_local` (2026-05-17)

---

## Files planned to be changed (not yet modified)

| File | Type of change |
|------|----------------|
| `admin/header.php` | Add sidebar menu: Pengumpulan Tugas / Kelola Tugas |
| `siswa/header.php` | Add sidebar menu: Tugas Saya |

**No other existing files are required to change for Phase 1** if helpers are in new `includes/etugas_helpers.php`.

Optional later:
| File | Change |
|------|--------|
| `admin/footer.php` | Only if new AJAX endpoints need global JS hooks |
| `includes/auth.php` | Only if adding `etugas.*` permission keys |

---

## New files planned

### Phase 1
| File | Purpose |
|------|---------|
| `migrations/003_etugas.sql` | Create `etugas`, `etugas_pengumpulan` |
| `includes/etugas_helpers.php` | TA active, guru scope, siswa kelas resolver |
| `admin/etugas.php` | Assignment list + create UI |
| `admin/etugas_act.php` | Insert assignment |
| `admin/etugas_edit.php` | Edit form |
| `admin/etugas_update.php` | Update handler |
| `admin/etugas_hapus.php` | Soft delete or hard delete (TBD in impl.) |

### Phase 2
| File | Purpose |
|------|---------|
| `siswa/tugas_saya.php` | Student task list by mapel |
| `siswa/tugas_saya_detail.php` | Task detail + submit form |
| `siswa/tugas_saya_act.php` | Save submission |

### Phase 3
| File | Purpose |
|------|---------|
| `admin/etugas_pengumpulan.php` | Review submissions (filters) |
| `admin/etugas_pengumpulan_act.php` | Status, score, guru note |

### Phase 4 (optional)
| File | Purpose |
|------|---------|
| `admin/etugas_export.php` | CSV/Excel export |

### Documentation (per release)
| File | Purpose |
|------|---------|
| `docs/deployment-manifests/YYYY-MM-DD-etugas-phaseN.md` | Per-phase deploy log |
| `docs/ai-agent-reports/YYYY-MM-DD-etugas-phaseN.md` | Implementation notes |

---

## Database tables planned

### New tables

#### `etugas`
Assignment header: TA, kelas, mapel, guru, judul, instruksi, deadline, flags.

#### `etugas_pengumpulan`
Per-siswa submission: text, link, status, optional nilai, catatan guru, timestamps.

### Existing tables used (read-only joins)

| Table | Usage |
|-------|--------|
| `ta` | Active tahun ajaran |
| `kelas` | Filter by rombel |
| `kelas_siswa` | Resolve siswa → kelas |
| `mapel` | Subject list |
| `pengampu_mapel` | Guru authorization scope |
| `user` | Creator / reviewer |
| `siswa` | Student identity |

### Tables explicitly NOT modified
- `ujian_gform`, `nilai_harian_*`, `jenis_penilaian`, `input_prestasi`, etc.

---

## SQL migration file planned

| File | Action |
|------|--------|
| `migrations/003_etugas.sql` | `CREATE TABLE etugas`, `CREATE TABLE etugas_pengumpulan`, indexes, unique key `(etugas_id, siswa_id)` |

**Deploy on hosting:**
1. Backup database (export from phpMyAdmin).
2. Import `003_etugas.sql` only.
3. Verify: `SHOW TABLES LIKE 'etugas%';`

**Rollback SQL (planned):**
```sql
DROP TABLE IF EXISTS etugas_pengumpulan;
DROP TABLE IF EXISTS etugas;
```

---

## Manual shared-hosting deployment notes

### Pre-deploy
1. Backup **database** (full export).
2. Backup **changed PHP files** if overwriting an existing install.
3. Confirm PHP ≥ 7.4 (mysqli, session).
4. Confirm `.env` on server has correct `DB_*` and `APP_ENV=production`.

### Phase 1 deploy order
1. Upload `migrations/003_etugas.sql` → run in phpMyAdmin.
2. Upload `includes/etugas_helpers.php`.
3. Upload `admin/etugas*.php` (all act handlers).
4. Upload patched `admin/header.php` (menu only — diff carefully).
5. Clear OPcache if enabled (hosting panel).
6. Test as administrator: open `admin/etugas.php`.
7. Test as guru: create task only for assigned pengampu mapel.

### Phase 2 deploy order
1. Upload `siswa/tugas_saya*.php`.
2. Upload patched `siswa/header.php`.
3. Test siswa login → Tugas Saya → submit link + text.

### Phase 3 deploy order
1. Upload `admin/etugas_pengumpulan*.php`.
2. Test review flow: status + catatan + nilai.

### Files NOT to upload
- `.env`, `*.sql` dumps with real data, `*.bak`, `*.log`
- Local-only test scripts

### Post-deploy verification
- [ ] Admin menu visible
- [ ] Guru cannot open other guru’s kelas/mapel
- [ ] Siswa sees only tasks for their kelas + active TA
- [ ] Submission saves to `etugas_pengumpulan`
- [ ] No PHP errors in hosting error log

---

## Rollback notes

### Application rollback
1. Remove menu lines from `admin/header.php` and `siswa/header.php` (restore backup).
2. Delete new PHP files listed above from server.
3. Remove `includes/etugas_helpers.php`.

### Database rollback
Run rollback SQL (drop tables). **Warning:** destroys all submissions.

### Partial rollback
- Disable feature: set all `etugas.is_active = 0` (keeps data, hides from siswa queries).

---

## Version history

| Date | Manifest | Notes |
|------|----------|-------|
| 2026-05-17 | `2026-05-17-etugas-planning-audit` | Initial audit & plan — no code deployed |

---

## Next manifest (after Phase 1 implementation)

Create: `docs/deployment-manifests/YYYY-MM-DD-etugas-phase1.md` with:
- Exact file list uploaded
- SQL checksum / filename
- Tester sign-off
- Production URL tested
