# E-Tugas (Pengumpulan Tugas Siswa) — Planning & Audit

**Date:** 2026-05-17  
**Status:** Audit & plan only — **no implementation**  
**Environment audited:** Laragon local (`epoin_local`, MySQL 3308)  
**Deployment model:** Manual file replace on shared hosting + small SQL migration import

---

## 1. Executive summary

The EPOIN app is native PHP (AdminLTE 2 / Bootstrap 3), mysqli-first, with RBAC in `includes/auth.php`. There is **no existing homework-submission module**. The closest patterns are:

| Pattern | Reference | Reuse for E-Tugas |
|---------|-----------|-------------------|
| Assignment scoped to TA + kelas + mapel + guru | `ujian_gform` | Scope, deadlines, active flag |
| Student portal filtered by `kelas_siswa` | `siswa/ujian.php` | “Tugas Saya” listing |
| Workflow status + note | `permohonan_absensi` | Review status + guru note |
| CRUD + filters | `input_prestasi.php`, `pengampu_mapel.php` | Admin UI |
| TA → kelas cascade | `ajax_get_kelas.php`, `footer.php` | Create-assignment form |

“Tugas” in `jenis_penilaian` / nilai harian is a **grade component type**, not student homework collection — do not overload those tables.

---

## 2. Existing database (verified on `epoin_local`)

### 2.1 Core academic tables

#### `siswa`
| Column | Type | Notes |
|--------|------|-------|
| `siswa_id` | int PK | Login id in `$_SESSION['id']` |
| `siswa_nama` | varchar(255) | |
| `siswa_nis` | varchar(255) | Login username |
| `siswa_jurusan` | varchar(11) | → `jurusan` |
| `siswa_status` | varchar(255) | aktif, tamat, … |
| `siswa_password` | varchar(255) | MD5 (legacy) |
| `siswa_foto` | varchar(100) | |
| `last_login`, `status_login` | datetime / enum | |

#### `kelas`
| Column | Type | Notes |
|--------|------|-------|
| `kelas_id` | int PK | |
| `kelas_nama` | varchar(255) | |
| `kelas_jurusan` | int | → `jurusan` |
| `kelas_ta` | int | → `ta.ta_id` |

#### `kelas_siswa`
| Column | Type | Notes |
|--------|------|-------|
| `ks_id` | int PK | |
| `ks_siswa` | int | → `siswa.siswa_id` |
| `ks_kelas` | int | → `kelas.kelas_id` |

**Note:** No `ta_id` on `kelas_siswa`; class membership is resolved via `kelas.kelas_ta`.

#### `mapel`
| Column | Type | Notes |
|--------|------|-------|
| `mapel_id` | int PK | |
| `mapel_kode` | varchar(20) UNIQUE | |
| `mapel_nama` | varchar(100) | |

#### `ta` (tahun ajaran — not `tahun_ajaran`)
| Column | Type | Notes |
|--------|------|-------|
| `ta_id` | int PK | |
| `ta_nama` | varchar(255) | e.g. 2025/2026 |
| `ta_status` | int | `1` = active |

#### `jurusan`
| Column | Type | Notes |
|--------|------|-------|
| `jurusan_id` | int PK | |
| `jurusan_nama` | varchar(255) | |

#### `user` (guru / admin / staf)
| Column | Type | Notes |
|--------|------|-------|
| `user_id` | int PK | Staff `$_SESSION['id']` |
| `user_nama`, `user_username`, `user_password` | | MD5 or bcrypt |
| `user_level` | varchar(20) | Legacy role string |
| `linked_siswa_id` | int | Optional link to siswa |
| `last_login`, `status_login` | | |

#### `pengampu_mapel` (guru mengajar)
| Column | Type | Notes |
|--------|------|-------|
| `id` | int PK | |
| `ta_id` | int | |
| `kelas_id` | int | |
| `mapel_id` | int | |
| `guru_user_id` | int | → `user.user_id` |
| `created_at` | datetime | |

**App rule:** unique `(ta_id, kelas_id, mapel_id)` — see `admin/pengampu_mapel.php`.

### 2.2 RBAC tables

| Table | Purpose |
|-------|---------|
| `roles` | `role_id`, `role_key` (UNI), `role_name`, `role_desc` |
| `user_roles` | `user_id` + `role_id` |
| `permissions` | `perm_id`, `perm_key` (UNI), `perm_name` |
| `role_permissions` | `role_id` + `perm_id` |

Canonical helpers: `includes/auth.php` — `user_has_role()`, `user_has_any_role()`, `can()`, `guard_roles()`.

### 2.3 Related modules (not homework)

| Table | Relevance |
|-------|-----------|
| `ujian_gform` | Online exam; TA/kelas/mapel/guru + `gform_url` + schedule |
| `ujian_gform_attempt` | Per-siswa attempt log |
| `pengumuman` | Broadcast (`audience`: all/siswa/guru) — no submission |
| `permohonan_absensi` | Siswa request + `status` pending/approved/rejected |
| `jenis_penilaian` | Grade type “Tugas” for nilai harian — **different domain** |
| `nilai_harian_set` | Daily grade sets per mapel/kelas/semester |

### 2.4 No existing homework tables

No `tugas`, `etugas`, `homework`, or `submission` tables found.

---

## 3. Auth & session (verified)

### 3.1 Login flow

| Entry | File | Target |
|-------|------|--------|
| Unified form | `login.php` → `periksa_unified.php` | Routes by `role` |
| Siswa | `periksa_login.php` | `siswa/` |
| Staff | `periksa_admin.php` + `includes/auth.php` | `admin/` |

### 3.2 Siswa session (`periksa_login.php`)

```php
$_SESSION['id']      // siswa_id
$_SESSION['nama']
$_SESSION['nis']
$_SESSION['level']   // "siswa"
```

Gate: `siswa/header.php` — `$_SESSION['level'] === 'siswa'`.

### 3.3 Staff session (`includes/auth.php`)

```php
$_SESSION['id']           // user_id
$_SESSION['username']
$_SESSION['user_nama']
$_SESSION['roles']        // array of role_key
$_SESSION['perms']        // ['perm.key' => true]
$_SESSION['level']        // legacy active role
$_SESSION['linked_siswa'] // optional
```

Gate: `admin/header.php` — `ensure_logged_in()` or redirect `../admin.php?alert=belum_login`.

### 3.4 Recommended guards for E-Tugas

| Portal | Roles | Pattern |
|--------|-------|---------|
| Admin create/review all | `administrator`, `superadmin` | `_is_admin()` or `user_has_any_role([...])` |
| Guru create/review own mapel | `guru` | `guard_roles(['administrator','guru'])` + filter `pengampu_mapel.guru_user_id = current_user_id()` |
| Siswa submit | `siswa` | Existing `siswa/header.php` gate |

Reference: `admin/ujian_gform.php` lines 53–54 (`guard_roles(['administrator','guru'])`).

---

## 4. Menu / layout files to modify (planned)

| File | Change |
|------|--------|
| `admin/header.php` | Add menu under **UJIAN & CBT** or new **PEMBELAJARAN** header: “Pengumpulan Tugas” |
| `siswa/header.php` | Add **Tugas Saya** under section **Penilaian** (near `ujian.php`) |

Layout pattern:

- `include 'header.php'` → `content-wrapper` → `include 'footer.php'`
- AdminLTE + DataTables `#table-datatable`
- Page-scoped CSS (see `input_prestasi.php`, `pelanggaran.php`)

Branding: `includes/theme_brand.php`.

---

## 5. UI / CRUD patterns to copy

### 5.1 Master CRUD (simple)

**Model:** `admin/pelanggaran.php` + `pelanggaran_act.php` + `_edit` + `_update` + `_hapus`

### 5.2 Transactional list + filters

**Model:** `admin/input_prestasi.php` — TA/kelas filters, themed table, modal forms

### 5.3 Mapel/kelas/TA assignment

**Model:** `admin/pengampu_mapel.php` — uniqueness per TA+kelas+mapel

### 5.4 Student list by class

**Model:** `siswa/ujian.php` — resolves kelas via `kelas_siswa`, filters active exams

### 5.5 Staff exam panel (complex)

**Model:** `admin/ujian_gform.php` — filters, prepared statements, role guard (use sparingly; prefer simpler CRUD for v1)

### 5.6 Filtering AJAX

| File | Behavior |
|------|----------|
| `admin/ajax_get_kelas.php` | POST `ta` → `<select class="pilih_kelas">` |
| `admin/ajax_get_siswa.php` | POST `kelas` → siswa in class |
| `admin/get_kelas_by_ta.php` | JSON kelas list |
| `admin/footer.php` | Wires `.pilih_ta` / `.pilih_kelas` / `.pilih_siswa` |

### 5.7 Export / report (phase 4 optional)

| File | Pattern |
|------|---------|
| `admin/laporan.php` | GET filters + `?export=excel` with early `ob_start()` |
| `admin/laporan_pdf.php` | Standalone PDF |
| `admin/export_csv.php` | Admin ZIP export |

---

## 6. Recommended table design

Prefix: **`etugas_`** (avoids clash with nilai harian “Tugas”).

### 6.1 `etugas` — assignment header

| Column | Type | Notes |
|--------|------|-------|
| `etugas_id` | int PK AI | |
| `ta_id` | int | FK logical → `ta` |
| `kelas_id` | int | FK logical → `kelas` |
| `mapel_id` | int | FK logical → `mapel` |
| `pengampu_id` | int NULL | Optional → `pengampu_mapel.id` |
| `guru_user_id` | int | Creator / owner → `user` |
| `judul` | varchar(200) | |
| `instruksi` | text | Rich text plain for v1 |
| `deadline_at` | datetime NULL | |
| `allow_text` | tinyint(1) DEFAULT 1 | |
| `allow_link` | tinyint(1) DEFAULT 1 | Drive/YouTube/Canva |
| `is_active` | tinyint(1) DEFAULT 1 | |
| `created_at` | datetime | |
| `updated_at` | datetime | |

**Indexes:** `(ta_id, kelas_id, mapel_id)`, `(guru_user_id)`, `(is_active, deadline_at)`.

### 6.2 `etugas_pengumpulan` — student submission

| Column | Type | Notes |
|--------|------|-------|
| `pengumpulan_id` | int PK AI | |
| `etugas_id` | int | |
| `siswa_id` | int | |
| `jawaban_teks` | text NULL | |
| `link_url` | varchar(500) NULL | Validated URL |
| `link_jenis` | enum('drive','youtube','canva','lainnya') NULL | Optional auto-detect |
| `status` | enum('draft','terkirim','ditinjau','revisi','selesai') DEFAULT 'terkirim' | |
| `nilai` | decimal(5,2) NULL | Optional score |
| `catatan_guru` | text NULL | |
| `submitted_at` | datetime | |
| `reviewed_by` | int NULL | → `user.user_id` |
| `reviewed_at` | datetime NULL | |
| `updated_at` | datetime | |

**Unique:** `(etugas_id, siswa_id)` — one submission row per student per task (updates overwrite or version in v2).

### 6.3 Why not reuse `ujian_gform`?

- Different lifecycle (text/link vs GForm URL + attempt proctoring).
- Keeps nilai/ujian modules independent.
- Easier rollback on shared hosting.

---

## 7. Recommended file structure

```
epoin/
├── migrations/
│   └── 003_etugas.sql                 # NEW — manual import on hosting
├── includes/
│   └── etugas_helpers.php             # NEW — scope guru, resolve kelas siswa, URL validate
├── admin/
│   ├── etugas.php                     # NEW — list/create assignments (guru+admin)
│   ├── etugas_act.php
│   ├── etugas_edit.php
│   ├── etugas_update.php
│   ├── etugas_hapus.php
│   ├── etugas_pengumpulan.php         # NEW — review grid (filter mapel/kelas/tugas)
│   ├── etugas_pengumpulan_act.php     # status, nilai, catatan
│   └── header.php                     # MODIFY — menu item
├── siswa/
│   ├── tugas_saya.php                 # NEW — list by mapel
│   ├── tugas_saya_detail.php          # NEW — view + submit form
│   ├── tugas_saya_act.php             # NEW — POST submit
│   └── header.php                     # MODIFY — menu item
└── docs/
    └── deployment-manifests/          # per-release manifest
```

Optional later: `admin/etugas_export.php` (Excel, copy `laporan.php` export branch).

---

## 8. Implementation phases

### Phase 1 — Foundation (DB + admin assignment CRUD)
- Create `migrations/003_etugas.sql`
- `includes/etugas_helpers.php` (active TA, guru scope from `pengampu_mapel`)
- Admin: `etugas.php` + act/edit/update/hapus
- Menu entry in `admin/header.php`
- Guru: only TA/kelas/mapel where `pengampu_mapel.guru_user_id = session user`
- Admin: all assignments

### Phase 2 — Siswa portal
- `siswa/tugas_saya.php` — list open tasks for student’s kelas(es) in active TA
- `siswa/tugas_saya_detail.php` + `tugas_saya_act.php` — text + link submit
- Menu in `siswa/header.php`
- Validate URL (allowlist hosts: drive.google.com, youtube.com, canva.com, etc.)

### Phase 3 — Guru review
- `admin/etugas_pengumpulan.php` — filters: TA, kelas, mapel, tugas, siswa
- `etugas_pengumpulan_act.php` — set status, `nilai`, `catatan_guru`
- Badge/count optional in sidebar (later)

### Phase 4 — Polish & deploy extras
- Export CSV/Excel per tugas/kelas
- Notifications (optional, out of scope v1)
- Permission key e.g. `etugas.manage` in RBAC (optional; role guard sufficient for v1)

---

## 9. Risks

| Risk | Mitigation |
|------|------------|
| Guru sees other classes’ submissions | Enforce `pengampu_mapel` + `etugas.guru_user_id` in every query |
| Siswa in multiple kelas / no `kelas_siswa` | Show clear error; use same join as `siswa/ujian.php` |
| MD5 / SQL injection in legacy code | New module: **prepared statements only** |
| Confusion with nilai harian “Tugas” | Separate tables `etugas_*`; UI label “Pengumpulan Tugas” |
| Shared hosting: large file upload | v1: **link only** + text; no file upload to server |
| Manual deploy drift | **Deployment manifest** per phase; checklist in manifest |
| `siswa/footer.php` AJAX paths | If reusing TA/kelas AJAX from siswa, prefix `../admin/` |

---

## 10. Files inspected (audit)

### Database / config
- Live introspection via `epoin_local` (May 2026)
- `koneksi.php`, `config/database.php`, `includes/env.php`

### Auth
- `includes/auth.php`, `periksa_login.php`, `periksa_admin.php`, `periksa_unified.php`

### Layout / menu
- `admin/header.php`, `admin/footer.php`
- `siswa/header.php`, `siswa/footer.php`
- `includes/theme_brand.php`

### Reference modules
- `admin/pengampu_mapel.php`, `admin/mapel.php`, `admin/input_prestasi.php`, `admin/pelanggaran.php`
- `admin/ujian_gform.php`, `admin/laporan.php`, `admin/ajax_get_kelas.php`
- `siswa/ujian.php`, `siswa/index.php`
- `includes/eps_helpers.php`

---

## 11. Security notes for implementation

- Prepared statements for all new queries.
- `htmlspecialchars()` on output; validate URLs (scheme https, host allowlist).
- CSRF token optional v1 (match existing app level — most `_act.php` files use POST without CSRF today).
- Do not store Google Drive file contents — store link only.
- Rate-limit submissions per siswa (application-level).

---

## 12. Related deployment manifest

See: `docs/deployment-manifests/2026-05-17-etugas-planning-audit.md`
