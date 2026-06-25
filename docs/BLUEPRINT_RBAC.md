# BLUEPRINT RBAC — E‑POIN

> **Status:** RANCANGAN (audit + desain). **Belum ada kode implementasi.**
> Dokumen ini jadi acuan sebelum eksekusi. Tunggu review Bos sebelum coding.
> Tanggal audit: 2026‑06‑25 · Stack: PHP Native + MySQL + AdminLTE 2 + jQuery · Koneksi: `$koneksi` (mysqli)

---

## DAFTAR ISI
1. [Hasil Audit Sistem Existing](#1-hasil-audit-sistem-existing)
2. [Inventarisasi Lengkap Menu + Aksi](#2-inventarisasi-lengkap-menu--aksi)
3. [Skema DB Baru (CREATE/ALTER SQL)](#3-skema-db-baru-createalter-sql)
4. [14 Role + Matrix Permission Default](#4-14-role--matrix-permission-default)
5. [Helper `epoin_can()` + Mekanisme Cache](#5-helper-epoin_can--mekanisme-cache)
6. [Rancangan UI Matrix](#6-rancangan-ui-matrix)
7. [Rencana Implementasi 6 Sub‑Fase](#7-rencana-implementasi-6-sub-fase)
8. [Strategi Backward Compatibility](#8-strategi-backward-compatibility)

---

## 1. HASIL AUDIT SISTEM EXISTING

### 1.A — Tabel & Struktur RBAC (hasil `DESCRIBE` live DB)

Sistem **sudah punya 4 tabel RBAC** (bukan dari nol). Tidak perlu bikin tabel ganda.

**`roles`**
| Kolom | Tipe | Catatan |
|---|---|---|
| `role_id` | int | PK |
| `role_key` | varchar(50) | UNIQUE — dipakai di kode (`'guru'`, `'administrator'`, dll) |
| `role_name` | varchar(100) | label tampilan |
| `role_desc` | varchar(255) NULL | **selalu NULL** (belum pernah diisi) |

Isi tabel `roles` saat ini (**5 role**):

| role_id | role_key | role_name | jml user |
|--:|---|---|--:|
| 1 | `superadmin` | Super Admin | 2 |
| 2 | `administrator` | Administrator | 3 |
| 3 | `guru` | Guru | 23 |
| 4 | `tas` | Tenaga Administrasi | 0 |
| 8 | `sekretaris` | Pembimbing Eskul / Sekretaris | 27 |

**`permissions`**
| Kolom | Tipe | Catatan |
|---|---|---|
| `perm_id` | int | PK |
| `perm_key` | varchar(100) | UNIQUE |
| `perm_name` | varchar(150) | label |

> ⚠️ **Tidak ada kolom `grup`/`modul`/`tipe`.** Untuk matrix yang rapi & expandable, kolom ini perlu ditambah (lihat §3).

Isi `permissions` saat ini (**7 permission, semua tentang absensi**):

```
1 attendance.view_all       Lihat semua sesi absensi
2 attendance.final_any      Finalisasi sesi siapa pun
3 attendance.delete_any     Hapus sesi siapa pun
4 attendance.mapel.own      Kelola sesi mapel milik sendiri
5 attendance.harian.view    Lihat absensi harian
6 attendance.harian.input   Input absensi harian
7 monitoring.view           Lihat dashboard monitoring
```

**`role_permissions`** — komposit PK `(role_id, perm_id)` + index FK `fk_rp_p` di `perm_id`.
> Koreksi asumsi lama: tabel ini **SUDAH punya Primary Key** (komposit) dan tampak punya FK. Tidak rusak.

Mapping saat ini:
```
administrator → view_all, final_any, delete_any, mapel.own   (4)
guru          → mapel.own                                     (1)
sekretaris    → harian.input, harian.view, monitoring.view    (3)
tas           → harian.input, harian.view, monitoring.view    (3)
superadmin    → (KOSONG — tidak ada satupun)                  (0)
```

> 🔴 **Temuan kritis:** `superadmin` punya **0 permission**. Kalau nanti enforcement berbasis `epoin_can()` diaktifkan tanpa pengaman, **superadmin malah terkunci dari semuanya.** → `epoin_can()` WAJIB memperlakukan superadmin sebagai **wildcard (selalu true)**. Lihat §5.

**`user_roles`** — komposit PK `(user_id, role_id)`. Relasi user→role many‑to‑many. **Multi‑role sudah didukung secara struktur.**

**`user`** (kolom relevan): `user_id`, `user_nama`, `user_username`, `user_password` (bcrypt + MD5 legacy), `user_level` varchar(20) (legacy), `linked_siswa_id`, `last_login`, `status_login`, `is_active` tinyint(1) (fitur suspend — sudah ada di lokal).

---

### 1.B — Sistem Auth & Guard Existing

**Penyimpanan sesi** (di‑set saat login oleh `do_login_with_role()` → `bootstrap_rbac_for_user_id()` di [includes/auth.php](../includes/auth.php)):
```php
$_SESSION['id']        // user_id
$_SESSION['roles']     // array role_key, mis. ['guru','administrator']  ← SUMBER KEBENARAN
$_SESSION['perms']     // set assoc ['perm.key'=>true]  ← SUDAH di-load saat login!
$_SESSION['level']     // legacy single-string ('administrator'/'guru'/'tas') untuk halaman lama
$_SESSION['active_role'], $_SESSION['linked_siswa']
```

> 🟢 **Temuan penting:** Pipeline permission **sebenarnya sudah 80% terpasang tapi "tidur"**:
> - `bootstrap_rbac_for_user_id()` sudah memanggil `fetch_permissions_for_roles()` yang query `role_permissions` dan mengisi `$_SESSION['perms']`.
> - Helper `can($permKey)` sudah ada (`auth.php:79`) dan membaca `$_SESSION['perms']`.
> - **Tapi `can()` tidak dipakai untuk gating apa pun.** Akses 100% berbasis ROLE.

> ⚠️ **Konflik yang harus diperbaiki saat implementasi:** [admin/header.php](../admin/header.php) mendefinisikan ulang `load_user_permissions($uid){ return []; }` lalu:
> ```php
> if (empty($_SESSION['perms'])) { $_SESSION['perms'] = load_user_permissions($user_id); }
> ```
> Untuk role tanpa perm (mis. superadmin) ini menimpa `perms` jadi `[]`. Saat enforcement diaktifkan, baris ini harus diganti agar memuat perms asli dari DB (atau dihapus karena `bootstrap_rbac` sudah mengisinya).

**Fungsi guard yang ada** ([includes/epoin_security.php](../includes/epoin_security.php)):
| Fungsi | Fungsi/peran |
|---|---|
| `epoin_is_admin_session()` | true jika roles/level ∈ {administrator, superadmin, admin} |
| `epoin_is_staff_session()` | true untuk semua staf; **siswa ditolak** |
| `epoin_staff_only_guard($redir)` | guard halaman HTML staf (redirect siswa/tamu) |
| `epoin_staff_only_guard_json()` | versi JSON |
| `epoin_staff_guard(bool $requireAdmin=false)` | guard umum; jika `true` → admin‑only |
| `epoin_staff_guard_json()` | versi JSON (login wajib) |
| `epoin_require_post()` | tolak non‑POST |

Dari [includes/auth.php](../includes/auth.php): `ensure_logged_in()`, `guard_roles([...])`, `guard_perms([...])` (pakai `can()`), `user_has_role()`, `user_has_any_role()`.

**Cara guard dipakai sekarang (tidak konsisten — 3 gaya hidup berdampingan):**
1. **Guard berbasis sesi staf/admin** → `epoin_staff_guard(true)` (admin‑only) / `epoin_staff_guard()` (staf, termasuk lolos siswa jika ada role).
2. **Guard berbasis role** → `user_has_any_role([...])` / `guard_roles([...])`.
3. **Guard berbasis permission** → `guard_perms([...])` / `can()` — **hanya 2 file**: `rekap_bulanan.php`, `data_absensi.php`.

---

### 1.C — Menu & Tampilan (header.php)

Menu di‑render di [admin/header.php](../admin/header.php) (1729 baris; sidebar `<ul class="sidebar-menu">` mulai ~baris 855). **Logika tampil/sembunyi sudah ada, berbasis ROLE** lewat helper inline:
`_is_admin()`, `_is_guru()`, `_is_tas()`, `_is_sekretaris()`, `_is_only_sekretaris()`, `_is_piket()`, `_is_only_piket()`, `user_has_any_role([...])`, dan `is_wali_kelas($uid)` (query tabel `kelas_wali`).

Pola gating menu yang dipakai:
- `_is_only_sekretaris() || _is_only_piket()` → **menu super‑terbatas** (hanya Absensi Harian + Monitoring) + hard‑redirect guard di `header.php` (whitelist file).
- `user_has_any_role(['administrator','superadmin'])` → menu **Sekolah**.
- `_is_admin()` → seluruh blok **MASTER DATA** + sebagian besar **ABSENSI**.
- `_is_admin() || _is_guru()` → **Absensi Mapel**, **PENILAIAN**, **UJIAN & CBT**.
- `is_wali_kelas() && !admin` → blok **WALI KELAS**.

> 🔴 **Temuan:** role `piket` dipakai di kode (`_is_piket`, `_is_only_piket`, login `login_as=piket`) **tetapi tidak ada barisnya di tabel `roles`.** Begitu juga `wali_kelas` bukan role — diturunkan dari tabel `kelas_wali`. Ini perlu direkonsiliasi (lihat §4).

---

### 1.D — Handler & Halaman

**15 file handler `*_act.php`** yang perlu di‑guard:
```
input_prestasi_act.php   input_pelanggaran_act.php  prestasi_act.php
etugas_act.php           pelanggaran_act.php        hp_ortu_import_act.php
siswa_act.php            siswa_import_act.php       ta_act.php
jurusan_act.php          kelas_act.php              kelas_siswa_act.php
kelas_tambah_act.php     user_act.php               gantipassword_act.php
```
Plus handler non‑`_act` lain: `*_hapus.php`, `*_update.php`, `user_hapus.php`, `siswa_hapus_aksi.php`, dll.

**Cakupan guard saat ini (hasil grep, 94 kemunculan di 48 file):** mayoritas handler hanya `epoin_staff_guard()` (lolos staf apa pun) atau tanpa cek role granular. Beberapa handler master‑data (`ta_act.php`, `jurusan_act.php`, `kelas_tambah_act.php`, `siswa_hapus_aksi.php`) ada di backlog karena model aksesnya belum dipastikan. **Inilah yang akan dirapikan di Sub‑fase 5.**

---

## 2. INVENTARISASI LENGKAP MENU + AKSI

Ditelusuri dari `header.php`. Kolom **Aksi** = baris matrix permission. Format key: `modul.objek.aksi`.

### Modul UMUM
| Menu | File | Aksi → permission key |
|---|---|---|
| Dashboard | `index.php` | `dashboard.view` |
| Sekolah | `sekolah.php` | `sekolah.view`, `sekolah.edit` |
| Ganti Password | `gantipassword.php` | `account.password.self` *(semua user login)* |

### Modul MASTER DATA *(saat ini admin‑only)*
| Menu | File | Aksi → permission key |
|---|---|---|
| Siswa | `siswa.php` | `master.siswa.view`, `.create`, `.edit`, `.delete`, `.import`, `.export` |
| Manajemen Kelas | `kelas.php` | `master.kelas.view`, `.manage`, `.assign_siswa` |
| Tingkat/Jurusan | `jurusan.php` | `master.jurusan.view`, `.manage` |
| Mata Pelajaran | `mapel.php` | `master.mapel.view`, `.manage` |
| Penugasan Guru Mapel | `pengampu_mapel.php` | `master.pengampu.view`, `.manage` |
| Tahun Ajaran | `ta.php` | `master.ta.view`, `.manage` |
| Kalender Akademik | `kalender_akademik.php` | `master.kalender.view`, `.manage` |
| Manajemen Pengguna | `manajemen_pengguna.php` | `master.user.view`, `.create`, `.edit`, `.delete`, `.suspend`, **`.role_manage`** |

> **`master.user.role_manage`** = inti fitur "manajemen akses role" yang Bos minta (akses ke Matrix UI).

### Modul KATEGORI POIN
| Menu | File | Aksi |
|---|---|---|
| Jenis Prestasi | `prestasi.php` | `poin.jenis_prestasi.view`, `.manage` |
| Jenis Pelanggaran | `pelanggaran.php` | `poin.jenis_pelanggaran.view`, `.manage` |

### Modul KELOLA POIN
| Menu | File | Aksi |
|---|---|---|
| Input Prestasi | `input_prestasi.php` (+`_act/_update/_hapus`) | `poin.prestasi.input`, `.edit`, `.delete` |
| Input Pelanggaran | `input_pelanggaran.php` (+`_act/_update/_hapus`) | `poin.pelanggaran.input`, `.edit`, `.delete` |
| Input Kolektif | `poin_kolektif.php` | `poin.kolektif.input` |
| Laporan Poin Siswa | `laporan.php` | `poin.laporan.view`, `.export` |

### Modul ABSENSI *(7 perm existing dipetakan di sini — JANGAN di‑rename)*
| Menu | File | Aksi → permission key (existing/baru) |
|---|---|---|
| Absensi Mapel (Per JP) | `absensi_mapel.php` | `attendance.mapel.own` *(existing)*, `attendance.view_all`, `attendance.final_any`, `attendance.delete_any` *(existing)* |
| Absensi Harian | `absensi_harian.php` | `attendance.harian.input`, `attendance.harian.view` *(existing)* |
| Sinkron Absensi | `data_absensi.php` | `attendance.sinkron` *(baru)* |
| Monitoring Absensi | `rekap_kelas.php` | `monitoring.view` *(existing)* |
| Laporan Absensi | `laporan_absensi.php` | `attendance.laporan.view`, `.export` *(baru)* |

### Modul PENILAIAN *(admin/guru)*
| Menu | File | Aksi |
|---|---|---|
| Tujuan Pembelajaran | `tujuan_pembelajaran.php` | `nilai.tp.view`, `.manage` |
| Input Nilai Harian | `nilai_harian.php` | `nilai.harian.input` |
| Input Nilai STS | `nilai_pts.php` | `nilai.sts.input` |
| Nilai Tersimpan | `nilai_rapor_tersimpan.php` | `nilai.tersimpan.view` |
| Status Penilaian | `status_penilaian.php` | `nilai.status.view` |
| Leger Rapor STS | `leger_rapor_sts.php` | `nilai.leger.view` |
| Cetak Rapor STS | `rapor_sts_cetak.php` | `nilai.rapor.cetak` |
| Integrasi e‑Rapor | *(modal, WIP)* | `nilai.erapor.export` |

### Modul UJIAN & CBT *(admin/guru)*
| Menu | File | Aksi |
|---|---|---|
| Ujian Exam GForm | `ujian_gform.php` | `ujian.gform.view`, `.manage` |
| Kelola Tugas | `etugas.php` (+`_act`) | `etugas.manage` |
| Review Pengumpulan | `etugas_pengumpulan.php` | `etugas.review` |
| Rekap Tugas | `etugas_rekap.php` | `etugas.rekap.view` |
| CBT Nesagun | *(link eksternal)* | — *(tak perlu permission)* |

### Modul PENGATURAN / SISTEM
| Menu | File | Aksi |
|---|---|---|
| Petugas Absensi | `pengaturan_petugas_absensi.php` | `setting.petugas_absensi.manage` |
| **Akses & Role** | *(BARU — di Manajemen Pengguna)* | `master.user.role_manage` |

> **Total ± 60 permission key** tersebar di **11 modul**. Itu jumlah baris matrix. Untuk UX, dikelompokkan per modul yang bisa di‑expand/collapse (§6).

---

## 3. SKEMA DB BARU (CREATE/ALTER SQL)

Prinsip: **reuse** `roles`/`user_roles`/`role_permissions` (sudah ada & ber‑PK), hanya **tambah kolom metadata** + seed. Semua idempotent (pola stored‑procedure seperti migrasi `is_active`/`hp_ortu` yang sudah ada).

### 3.1 — `permissions`: tambah kolom grup/tipe/urutan
```sql
-- Tambah metadata agar matrix bisa dikelompokkan & dirender rapi.
-- (pakai pola cek INFORMATION_SCHEMA agar aman dijalankan berulang)
ALTER TABLE `permissions`
  ADD COLUMN `perm_group` VARCHAR(40)  NOT NULL DEFAULT 'lainnya' AFTER `perm_name`,
  ADD COLUMN `perm_type`  ENUM('menu','aksi') NOT NULL DEFAULT 'aksi' AFTER `perm_group`,
  ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `perm_type`,
  ADD COLUMN `is_active`  TINYINT(1) NOT NULL DEFAULT 1 AFTER `sort_order`;
```

### 3.2 — `roles`: tambah flag sistem & urutan (opsional, minimal)
```sql
ALTER TABLE `roles`
  ADD COLUMN `is_system`  TINYINT(1) NOT NULL DEFAULT 0 AFTER `role_desc`,  -- 1 = tak boleh dihapus (superadmin, dst)
  ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `is_system`;

UPDATE `roles` SET `is_system` = 1 WHERE `role_key` IN ('superadmin','administrator');
```

### 3.3 — `role_permissions`: sudah cukup
Komposit PK `(role_id, perm_id)` sudah ada. **Tidak perlu diubah.** (Opsional: tambah FK `ON DELETE CASCADE` ke `roles`/`permissions` agar hapus role/permission bersih — verifikasi dulu FK existing `fk_rp_p`.)

### 3.4 — Seed 9 role baru (lengkapi jadi 14)
```sql
INSERT INTO `roles` (`role_key`, `role_name`, `role_desc`, `is_system`, `sort_order`) VALUES
  ('kepala_sekolah',    'Kepala Sekolah',        'Pimpinan sekolah — akses lihat/monitoring menyeluruh', 0, 30),
  ('wakasek_kurikulum', 'Wakasek Kurikulum',     'Kelola mapel, penugasan guru, penilaian, jadwal',      0, 40),
  ('wakasek_kesiswaan', 'Wakasek Kesiswaan',     'Kelola poin/pelanggaran, absensi, kedisiplinan',       0, 50),
  ('guru_bk',           'Guru BK',               'Bimbingan konseling — lihat poin & riwayat siswa',     0, 80),
  ('guru_piket',        'Guru Piket',            'Input absensi harian & monitoring',                    0, 90),
  ('petugas_absensi',   'Petugas Absensi',       'Input/rekap absensi harian',                           0, 100),
  ('pembina_ekskul',    'Pembina Ekskul',        'Input prestasi ekskul',                                0, 110),
  ('siswa',             'Siswa',                 'Portal siswa (read-only data sendiri)',                1, 200);
-- 'staf_tu' = REUSE role 'tas' (lihat catatan rekonsiliasi §4). 'wali_kelas' = lihat §4.
```
> Catatan rekonsiliasi (penting, lihat §4): `staf_tu`↔`tas`, `wali_kelas` (dari `kelas_wali`), `siswa` (pseudo‑role) **tidak** diseed sembarangan — keputusan desain dulu.

### 3.5 — Seed permission baru (selain 7 existing)
```sql
INSERT INTO `permissions` (`perm_key`, `perm_name`, `perm_group`, `perm_type`, `sort_order`) VALUES
  ('dashboard.view',              'Lihat Dashboard',            'umum',        'menu', 10),
  ('sekolah.view',                'Lihat data Sekolah',         'umum',        'menu', 20),
  ('sekolah.edit',                'Ubah data Sekolah',          'umum',        'aksi', 21),
  ('account.password.self',       'Ganti password sendiri',     'umum',        'aksi', 30),

  ('master.siswa.view',           'Lihat Siswa',                'master',      'menu', 100),
  ('master.siswa.create',         'Tambah Siswa',               'master',      'aksi', 101),
  ('master.siswa.edit',           'Edit Siswa',                 'master',      'aksi', 102),
  ('master.siswa.delete',         'Hapus Siswa',                'master',      'aksi', 103),
  ('master.siswa.import',         'Import Siswa',               'master',      'aksi', 104),
  ('master.siswa.export',         'Export Siswa',               'master',      'aksi', 105),
  ('master.kelas.view',           'Lihat Kelas',                'master',      'menu', 110),
  ('master.kelas.manage',         'Kelola Kelas',               'master',      'aksi', 111),
  ('master.kelas.assign_siswa',   'Atur siswa per kelas',       'master',      'aksi', 112),
  ('master.jurusan.view',         'Lihat Jurusan',              'master',      'menu', 120),
  ('master.jurusan.manage',       'Kelola Jurusan',             'master',      'aksi', 121),
  ('master.mapel.view',           'Lihat Mapel',                'master',      'menu', 130),
  ('master.mapel.manage',         'Kelola Mapel',               'master',      'aksi', 131),
  ('master.pengampu.view',        'Lihat Penugasan Guru',       'master',      'menu', 140),
  ('master.pengampu.manage',      'Kelola Penugasan Guru',      'master',      'aksi', 141),
  ('master.ta.view',              'Lihat Tahun Ajaran',         'master',      'menu', 150),
  ('master.ta.manage',            'Kelola Tahun Ajaran',        'master',      'aksi', 151),
  ('master.kalender.view',        'Lihat Kalender',             'master',      'menu', 160),
  ('master.kalender.manage',      'Kelola Kalender',            'master',      'aksi', 161),
  ('master.user.view',            'Lihat Pengguna',             'master',      'menu', 170),
  ('master.user.create',          'Tambah Pengguna',            'master',      'aksi', 171),
  ('master.user.edit',            'Edit Pengguna',              'master',      'aksi', 172),
  ('master.user.delete',          'Hapus Pengguna',             'master',      'aksi', 173),
  ('master.user.suspend',         'Suspend/Aktifkan Pengguna',  'master',      'aksi', 174),
  ('master.user.role_manage',     'Kelola Role & Akses',        'master',      'aksi', 175),

  ('poin.jenis_prestasi.view',    'Lihat Jenis Prestasi',       'kategori',    'menu', 200),
  ('poin.jenis_prestasi.manage',  'Kelola Jenis Prestasi',      'kategori',    'aksi', 201),
  ('poin.jenis_pelanggaran.view', 'Lihat Jenis Pelanggaran',    'kategori',    'menu', 210),
  ('poin.jenis_pelanggaran.manage','Kelola Jenis Pelanggaran',  'kategori',    'aksi', 211),

  ('poin.prestasi.input',         'Input Prestasi',             'poin',        'menu', 300),
  ('poin.prestasi.edit',          'Edit Prestasi',              'poin',        'aksi', 301),
  ('poin.prestasi.delete',        'Hapus Prestasi',             'poin',        'aksi', 302),
  ('poin.pelanggaran.input',      'Input Pelanggaran',          'poin',        'menu', 310),
  ('poin.pelanggaran.edit',       'Edit Pelanggaran',           'poin',        'aksi', 311),
  ('poin.pelanggaran.delete',     'Hapus Pelanggaran',          'poin',        'aksi', 312),
  ('poin.kolektif.input',         'Input Poin Kolektif',        'poin',        'menu', 320),
  ('poin.laporan.view',           'Lihat Laporan Poin',         'poin',        'menu', 330),
  ('poin.laporan.export',         'Export Laporan Poin',        'poin',        'aksi', 331),

  ('attendance.sinkron',          'Sinkron Absensi',            'absensi',     'menu', 410),
  ('attendance.laporan.view',     'Lihat Laporan Absensi',      'absensi',     'menu', 420),
  ('attendance.laporan.export',   'Export Laporan Absensi',     'absensi',     'aksi', 421),

  ('nilai.tp.view',               'Lihat Tujuan Pembelajaran',  'penilaian',   'menu', 500),
  ('nilai.tp.manage',             'Kelola Tujuan Pembelajaran', 'penilaian',   'aksi', 501),
  ('nilai.harian.input',          'Input Nilai Harian',         'penilaian',   'menu', 510),
  ('nilai.sts.input',             'Input Nilai STS',            'penilaian',   'menu', 520),
  ('nilai.tersimpan.view',        'Lihat Nilai Tersimpan',      'penilaian',   'menu', 530),
  ('nilai.status.view',           'Lihat Status Penilaian',     'penilaian',   'menu', 540),
  ('nilai.leger.view',            'Lihat Leger Rapor',          'penilaian',   'menu', 550),
  ('nilai.rapor.cetak',           'Cetak Rapor STS',            'penilaian',   'aksi', 560),
  ('nilai.erapor.export',         'Export e-Rapor',             'penilaian',   'aksi', 570),

  ('ujian.gform.view',            'Lihat Ujian GForm',          'ujian',       'menu', 600),
  ('ujian.gform.manage',          'Kelola Ujian GForm',         'ujian',       'aksi', 601),
  ('etugas.manage',               'Kelola Tugas',               'ujian',       'menu', 610),
  ('etugas.review',               'Review Pengumpulan',         'ujian',       'menu', 620),
  ('etugas.rekap.view',           'Lihat Rekap Tugas',          'ujian',       'menu', 630),

  ('setting.petugas_absensi.manage','Kelola Petugas Absensi',   'sistem',      'aksi', 700);

-- Update metadata 7 permission existing agar masuk grup 'absensi'
UPDATE `permissions` SET `perm_group`='absensi', `perm_type`='menu', `sort_order`=400
  WHERE `perm_key` IN ('attendance.harian.input','attendance.harian.view','monitoring.view');
UPDATE `permissions` SET `perm_group`='absensi', `perm_type`='aksi', `sort_order`=405
  WHERE `perm_key` IN ('attendance.view_all','attendance.final_any','attendance.delete_any','attendance.mapel.own');
```

> Semua `INSERT` di atas dibungkus **idempotent** saat implementasi (mis. `INSERT ... ON DUPLICATE KEY UPDATE` atau `INSERT IGNORE`, karena `perm_key`/`role_key` UNIQUE). Ditulis polos di sini agar mudah dibaca.

---

## 4. 14 ROLE + MATRIX PERMISSION DEFAULT

### 4.0 — Rekonsiliasi 14 role target vs realita
| # | Role target | Status di sistem | Keputusan desain |
|--:|---|---|---|
| 1 | superadmin | ✅ ada (id 1) | **wildcard** — bypass semua cek |
| 2 | administrator | ✅ ada (id 2) | reuse |
| 3 | kepala_sekolah | ❌ | seed baru |
| 4 | wakasek_kurikulum | ❌ | seed baru |
| 5 | wakasek_kesiswaan | ❌ | seed baru |
| 6 | staf_tu | ⚠️ ada sbg **`tas`** (id 4) | **REUSE `tas`** (jangan duplikat). Opsi: ganti `role_name` jadi "Staf TU". `role_key` tetap `tas` (dipakai login & kode). |
| 7 | guru | ✅ ada (id 3) | reuse |
| 8 | wali_kelas | ⚠️ **bukan role** — dari tabel `kelas_wali` | **Keputusan Bos:** (a) tetap data‑driven via `kelas_wali` + permission `wali.*` diberikan otomatis, atau (b) jadikan role nyata. **Rekomendasi: (a)** agar 1 guru otomatis jadi wali saat di‑assign kelas. |
| 9 | guru_bk | ❌ | seed baru |
| 10 | guru_piket | ⚠️ kode pakai `piket`, **tak ada di tabel** | seed `guru_piket`; samakan helper `_is_piket()` agar kenal `guru_piket`. |
| 11 | petugas_absensi | ⚠️ ada `pengaturan_petugas_absensi.php` (mungkin tabel tersendiri) | seed role + verifikasi tabel `petugas_absensi` existing dulu. |
| 12 | pembina_ekskul | ⚠️ `sekretaris` (id 8) `role_name`="Pembimbing Eskul / Sekretaris" — **overloaded** | **Keputusan Bos:** pisah `sekretaris` vs `pembina_ekskul`, atau biarkan gabung. **Rekomendasi: seed `pembina_ekskul` terpisah**, biarkan `sekretaris` apa adanya (27 user) demi backward‑compat. |
| 13 | siswa | ⚠️ login portal `level='siswa'`, **bukan di tabel user_roles** | **pseudo‑role**. RBAC ini untuk STAF. `siswa` di‑seed hanya sebagai penanda; enforcement siswa tetap di portal siswa. |

> **Ringkasan:** dari 14 target, **5 sudah ada**, **`tas`=staf_tu** (reuse), **`sekretaris`** dibiarkan, **8 di‑seed**, **`wali_kelas`** & **`siswa`** perlu keputusan model (data‑driven vs role). 3 baris di tabel = butuh konfirmasi Bos sebelum seed.

### 4.1 — Matrix Permission Default (✓ = granted)

Legenda modul: **U**=Umum, **MD**=Master Data, **KP**=Kategori Poin, **P**=Poin, **AB**=Absensi, **NI**=Penilaian, **UJ**=Ujian, **SY**=Sistem.

| Permission (key) | super | admin | kepsek | wk.kur | wk.kes | tas/TU | guru | wali | bk | piket | p.abs | ekskul | siswa |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| **U** dashboard.view | ✓* | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | – |
| **U** sekolah.view | ✓* | ✓ | ✓ | – | – | – | – | – | – | – | – | – |
| **U** sekolah.edit | ✓* | ✓ | – | – | – | – | – | – | – | – | – | – |
| **U** account.password.self | ✓* | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | – |
| **MD** master.siswa.view | ✓* | ✓ | ✓ | ✓ | ✓ | ✓ | – | ✓ | ✓ | – | – | – |
| **MD** master.siswa.create/edit | ✓* | ✓ | – | – | – | ✓ | – | – | – | – | – | – |
| **MD** master.siswa.delete | ✓* | ✓ | – | – | – | – | – | – | – | – | – | – |
| **MD** master.siswa.import/export | ✓* | ✓ | – | – | – | ✓ | – | – | – | – | – | – |
| **MD** master.kelas.* | ✓* | ✓ | – | ✓ | – | ✓ | – | – | – | – | – | – |
| **MD** master.jurusan.* | ✓* | ✓ | – | ✓ | – | ✓ | – | – | – | – | – | – |
| **MD** master.mapel.* | ✓* | ✓ | – | ✓ | – | – | – | – | – | – | – | – |
| **MD** master.pengampu.* | ✓* | ✓ | – | ✓ | – | – | – | – | – | – | – | – |
| **MD** master.ta.* | ✓* | ✓ | – | ✓ | – | – | – | – | – | – | – | – |
| **MD** master.kalender.* | ✓* | ✓ | – | ✓ | – | ✓ | – | – | – | – | – | – |
| **MD** master.user.view | ✓* | ✓ | – | – | – | – | – | – | – | – | – | – |
| **MD** master.user.create/edit/delete | ✓* | ✓ | – | – | – | – | – | – | – | – | – | – |
| **MD** master.user.suspend | ✓* | ✓ | – | – | – | – | – | – | – | – | – | – |
| **MD** master.user.role_manage | ✓* | – | – | – | – | – | – | – | – | – | – | – |
| **KP** poin.jenis_prestasi.* | ✓* | ✓ | – | – | ✓ | – | – | – | – | – | – | – |
| **KP** poin.jenis_pelanggaran.* | ✓* | ✓ | – | – | ✓ | – | – | – | – | – | – | – |
| **P** poin.prestasi.input | ✓* | ✓ | – | – | ✓ | – | ✓ | ✓ | ✓ | – | – | ✓ekskul |
| **P** poin.prestasi.edit/delete | ✓* | ✓ | – | – | ✓ | – | – | – | – | – | – | – |
| **P** poin.pelanggaran.input | ✓* | ✓ | – | – | ✓ | – | ✓ | ✓ | ✓ | ✓ | – | – |
| **P** poin.pelanggaran.edit/delete | ✓* | ✓ | – | – | ✓ | – | – | – | – | – | – | – |
| **P** poin.kolektif.input | ✓* | ✓ | – | – | ✓ | – | ✓ | ✓ | – | – | – | – |
| **P** poin.laporan.view | ✓* | ✓ | ✓ | – | ✓ | ✓ | ✓ | ✓ | ✓ | – | – | – |
| **P** poin.laporan.export | ✓* | ✓ | ✓ | – | ✓ | ✓ | – | – | – | – | – | – |
| **AB** attendance.mapel.own | ✓* | ✓ | – | – | – | – | ✓ | – | – | – | – | – |
| **AB** attendance.view_all | ✓* | ✓ | ✓ | ✓ | ✓ | – | – | ✓ | – | – | – | – |
| **AB** attendance.final_any | ✓* | ✓ | – | – | ✓ | – | – | – | – | – | – | – |
| **AB** attendance.delete_any | ✓* | ✓ | – | – | – | – | – | – | – | – | – | – |
| **AB** attendance.harian.input | ✓* | ✓ | – | – | ✓ | ✓ | – | ✓ | – | ✓ | ✓ | – |
| **AB** attendance.harian.view | ✓* | ✓ | ✓ | – | ✓ | ✓ | – | ✓ | ✓ | ✓ | ✓ | – |
| **AB** attendance.sinkron | ✓* | ✓ | – | – | ✓ | – | – | ✓ | – | – | – | – |
| **AB** monitoring.view | ✓* | ✓ | ✓ | ✓ | ✓ | ✓ | – | ✓ | ✓ | ✓ | ✓ | – |
| **AB** attendance.laporan.view | ✓* | ✓ | ✓ | – | ✓ | ✓ | ✓ | ✓ | ✓ | – | ✓ | – |
| **NI** nilai.tp.* | ✓* | ✓ | – | ✓ | – | – | ✓ | – | – | – | – | – |
| **NI** nilai.harian.input | ✓* | ✓ | – | ✓ | – | – | ✓ | – | – | – | – | – |
| **NI** nilai.sts.input | ✓* | ✓ | – | ✓ | – | – | ✓ | – | – | – | – | – |
| **NI** nilai.tersimpan/status/leger.view | ✓* | ✓ | ✓ | ✓ | – | – | ✓ | ✓ | – | – | – | – |
| **NI** nilai.rapor.cetak | ✓* | ✓ | ✓ | ✓ | – | – | ✓ | ✓ | – | – | – | – |
| **NI** nilai.erapor.export | ✓* | ✓ | – | ✓ | – | – | ✓ | – | – | – | – | – |
| **UJ** ujian.gform.* | ✓* | ✓ | – | ✓ | – | – | ✓ | – | – | – | – | – |
| **UJ** etugas.manage/review/rekap | ✓* | ✓ | – | ✓ | – | – | ✓ | – | – | – | – | – |
| **SY** setting.petugas_absensi.manage | ✓* | ✓ | – | – | ✓ | – | – | – | – | – | – | – |

> `✓*` = superadmin **tidak perlu** baris‑per‑baris; di‑handle sebagai **wildcard** di `epoin_can()`.
> Sel "✓ekskul" pada baris prestasi = catatan: pembina_ekskul fokus input prestasi.
> Matrix ini **titik awal yang masuk akal** — detail per sel bisa di‑tweak Bos lewat Matrix UI (§6) tanpa coding.

### 4.2 — Multi‑role = UNION
User dengan banyak role → permission efektif = **gabungan (union)** semua perm dari semua role‑nya. Contoh: guru + wali_kelas → dapat perm guru ∪ perm wali. Sudah didukung `fetch_permissions_for_roles()` (query `IN (...)` + `GROUP BY`).

---

## 5. HELPER `epoin_can()` + MEKANISME CACHE

### 5.1 — Tanda tangan & semantik
```text
epoin_can(string $permKey): bool
  - return TRUE jika:
      a) user superadmin (wildcard), ATAU
      b) $permKey ada di set permission efektif user (union semua role).
  - sumber data: $_SESSION['perms'] (cache), fallback query DB jika cache kosong.
```
Varian helper pendamping (rancangan):
- `epoin_can_any(array $keys): bool` — true jika punya salah satu.
- `epoin_can_all(array $keys): bool` — true jika punya semua.
- `epoin_guard_can(string $permKey)` — guard halaman: 403 + redirect jika tidak punya.
- `epoin_guard_can_json(string $permKey)` — versi JSON.

### 5.2 — Cache di session (hindari query tiap cek)
- Saat login, `bootstrap_rbac_for_user_id()` **sudah** mengisi `$_SESSION['perms']` (set assoc `['perm.key'=>true]`). Pertahankan ini.
- Tambah **cache‑version stamp** untuk invalidasi saat admin mengubah matrix:
  - Simpan `rbac_version` global (mis. baris di tabel `app_meta` atau file) yang di‑bump setiap simpan Matrix UI.
  - Di `header.php`, bandingkan `$_SESSION['perms_ver']` vs `rbac_version`; jika beda → reload perms dari DB & update session. Biaya: 1 query ringan saat versi berubah saja.
- Superadmin: **tidak perlu** materialisasi semua perm ke session; cukup cek role di `epoin_can()` lebih dulu (`if (superadmin) return true;`).

```text
epoin_can(key):
  if session.roles has 'superadmin' -> return true
  if session.perms_ver != global.rbac_version -> reload session.perms from DB
  return isset(session.perms[key])
```

### 5.3 — Render menu dinamis
Ganti kondisi role di `header.php` jadi berbasis permission, mis.:
```text
sebelum:  <?php if (_is_admin()): ?> ...menu Master Data...
sesudah:  <?php if (epoin_can('master.siswa.view')): ?> ...item Siswa...
```
- Header modul ("MASTER DATA") tampil jika user punya **minimal satu** permission `*.view` di modul itu → pakai `epoin_can_any([...])`.
- **Fallback aman:** selama transisi, gunakan `epoin_can('x') || _is_admin()` agar admin lama tetap lihat semua walau matrix belum lengkap.

---

## 6. RANCANGAN UI MATRIX

**Lokasi:** tab baru **"Akses & Role"** di `manajemen_pengguna.php` (gated `master.user.role_manage`, superadmin‑only secara default). Konsisten dengan tab existing (Akun, Guru Piket, Sekretaris).

### 6.1 — Tiga sub‑tampilan
1. **Daftar Role** — tabel: nama, key, deskripsi, jml user, jml permission, tombol [Atur Akses] [Anggota] [Edit] [Hapus*]. (*hapus hanya jika `is_system=0` & 0 user).
2. **Form Role** — tambah/edit `role_name` + `role_desc` (akhirnya `role_desc` terisi!).
3. **Matrix Akses (inti)** — grid permission × role.

### 6.2 — UX Matrix (anti‑overwhelming)
```
┌───────────────────────────────────────────────────────────────┐
│ Pilih Role:  [ Guru ▼ ]        Cari permission: [_________]    │
│ [ Centang sema modul ]  [ Kosongkan ]      ● 12 dari 60 aktif │
├───────────────────────────────────────────────────────────────┤
│ ▸ UMUM ......................................... [3/4]  ☑ all  │
│ ▾ MASTER DATA .................................. [0/20] ☐ all  │
│     ☐ Lihat Siswa            ☐ Tambah Siswa     ☐ Hapus Siswa  │
│     ☐ Kelola Kelas           ☐ Kelola Mapel     ...            │
│ ▾ ABSENSI ...................................... [5/9]  ☐ all  │
│     ☑ Absensi Mapel (own)    ☑ Absensi Harian Input  ...       │
│ ▸ PENILAIAN .................................... [8/9]  ☐ all  │
│ ...                                                            │
├───────────────────────────────────────────────────────────────┤
│                                   [ Simpan Perubahan ]         │
└───────────────────────────────────────────────────────────────┘
```
Prinsip UX:
- **Per‑role, bukan grid raksasa** — pilih satu role, atur permission‑nya. Hindari tabel 14×60 yang bikin pusing.
- **Group expandable/collapse** per modul (`perm_group`), dengan counter `[aktif/total]` & checkbox "centang semua modul".
- **Search/filter** permission by nama.
- **Badge `menu` vs `aksi`** agar jelas mana yang nampilin menu vs sekadar izin aksi.
- Simpan via **AJAX** (pola `epoin_csrf_*` + `CSRF_TOKEN` yang sudah dipakai di `manajemen_pengguna.php`); 1 request kirim delta untuk 1 role → `DELETE`+`INSERT` di `role_permissions` (transaksi) → **bump `rbac_version`**.
- **Read‑only mode** untuk role `is_system` tertentu bila perlu (mis. tampilkan tapi kunci superadmin = "akses penuh").

### 6.3 — Sub‑tampilan "Anggota Role"
Klik [Anggota] → modal daftar user yang punya role tsb (reuse query `user_roles`). Membantu Bos cek "siapa saja sekretaris".

---

## 7. RENCANA IMPLEMENTASI 6 SUB‑FASE

> Aturan emas: **tiap sub‑fase di‑deploy & dites mandiri**, tidak memecahkan yang sudah jalan. Permission enforcement diaktifkan **paling akhir** (Sub‑fase 5), setelah data & UI siap.

| Sub‑fase | Pekerjaan | Risiko | Cara Test |
|--:|---|---|---|
| **1. DB + Seed** | ALTER `permissions`(+grup/tipe), `roles`(+is_system); seed 8 role baru + ±53 permission + matrix default ke `role_permissions`. File migrasi idempotent + masuk `APPLY_TO_VPS.sql`. | **Rendah.** Hanya tambah kolom/baris; tak sentuh kode. Bahaya: salah seed mapping. | Jalankan di lokal → `SELECT` verifikasi jumlah role=13/14, perm≈60, mapping per role sesuai matrix. Login existing harus tetap normal. |
| **2. Helper `epoin_can()` + cache** | Tambah `epoin_can/_any/_all`, `epoin_guard_can*`, cache‑version + reload. **Perbaiki** `load_user_permissions()` di header (jangan return `[]`). superadmin wildcard. | **Sedang.** Perubahan di `auth.php`/`header.php` (dipakai semua halaman). | Unit manual: login tiap role → `var_dump(epoin_can('x'))`. Pastikan superadmin selalu true. Pastikan menu existing tak berubah (helper belum dipasang ke menu). |
| **3. Matrix UI** | Tab "Akses & Role" di `manajemen_pengguna.php`: daftar role, form role, matrix per‑role, anggota. AJAX simpan + bump versi. | **Sedang.** Tulis ke `role_permissions` → bisa salah hapus mapping. | Ubah matrix 1 role uji → reload → cek DB. Test CSRF. Test role `is_system` tak terhapus. Backup `role_permissions` sebelum tes. |
| **4. Menu dinamis** | Ganti gating menu di `header.php` ke `epoin_can*()` dengan **fallback `|| _is_admin()`**. | **Sedang‑Tinggi.** `header.php` sensitif; salah kondisi → menu hilang. | Matriks uji: login tiap role → bandingkan menu tampil vs ekspektasi matrix. Admin harus tetap lihat semua (fallback). |
| **5. Guard handler** | Tambah `epoin_guard_can('...')` di 15 `*_act.php` + handler hapus/update. Mulai dari modul aman (master data), lalu poin/absensi/nilai. | **Tinggi.** Salah guard → user sah ketolak / aksi bocor. | Per handler: uji role berwenang (sukses) + role tak berwenang (403). Regression: alur input poin/absensi normal. |
| **6. Testing per role** | Skenario E2E 13 role: login → menu → buka tiap halaman → aksi CRUD. Dokumentasikan hasil. | **Rendah** (tes). | Checklist per role (lihat §7.1). Sign‑off Bos. |

### 7.1 — Checklist tes per role (template)
```
[ ] Login berhasil dgn role X
[ ] Menu yang tampil == matrix permission role X
[ ] Halaman ber-permission: boleh akses → 200; tak boleh → 403/redirect
[ ] Aksi CRUD ber-permission: granted sukses, denied ditolak
[ ] Multi-role: union perm benar (mis. guru+wali)
[ ] superadmin: akses penuh tanpa terkunci
[ ] Tidak ada regresi pada alur existing (login, input poin, absensi)
```

---

## 8. STRATEGI BACKWARD COMPATIBILITY

Prinsip: **tambah lapisan, jangan ganti pondasi.** Sistem lama tetap hidup sampai lapisan baru terbukti.

1. **Reuse tabel** `roles`/`user_roles`/`role_permissions` (sudah ber‑PK). **Tidak** bikin tabel baru → tak ada migrasi data user→role.
2. **Jangan rename** 7 `perm_key` existing & 5 `role_key` existing. Login (`login_as`), `fetch_permissions_for_roles()`, dan helper menu bergantung padanya.
3. **superadmin = wildcard** di `epoin_can()` → walau 0 baris di `role_permissions`, tetap akses penuh. **Mencegah lockout** (temuan §1.A).
4. **Pertahankan `$_SESSION['level']` & helper lama** (`_is_admin`, `user_has_role`, dst). Halaman lama yang cek `$_SESSION['level']==='administrator'` tetap jalan ([FIX‑LEVEL‑BRIDGE] di `header.php`).
5. **Fallback `epoin_can('x') || _is_admin()`** selama Sub‑fase 4–5. Admin/superadmin tak pernah kehilangan akses meski matrix belum lengkap. Fallback dilepas bertahap setelah matrix terbukti benar.
6. **Guard lama tidak dihapus** — `epoin_staff_guard()` tetap jadi lapisan pertama (cek login + staf). `epoin_guard_can()` jadi lapisan kedua (cek izin spesifik). Dua lapis, bukan ganti.
7. **Role belum dimigrasi tidak rugi** — role tanpa mapping = lihat menu minimal (yang umum) saja, tidak error. Tidak ada "deny‑all yang mematikan".
8. **Rollback aman per sub‑fase** — Sub‑fase 1 (DB) reversible via `DROP COLUMN`/hapus seed; Sub‑fase 2–5 reversible via revert commit. Karena enforcement terpisah dari data, bisa di‑rollback tanpa kehilangan mapping.
9. **`wali_kelas`/`piket`/`siswa`** tetap kompatibel: `wali_kelas` tetap dari `kelas_wali` (tidak memaksa jadi role), `piket` helper diperluas mengenal `guru_piket`, `siswa` tetap di portal terpisah.

---

## CATATAN AKHIR — KEPUTUSAN YANG MENUNGGU BOS
Sebelum Sub‑fase 1 dijalankan, perlu keputusan untuk 3 titik (lihat §4.0):
1. **`wali_kelas`** → data‑driven dari `kelas_wali` (rekomendasi) **atau** role nyata?
2. **`sekretaris` vs `pembina_ekskul`** → pisah (rekomendasi) **atau** biarkan gabung (role `sekretaris` id 8)?
3. **`petugas_absensi`** → cek dulu apakah sudah ada tabel/model tersendiri sebelum jadikan role.
4. **`staf_tu`** → konfirmasi reuse `tas` (rekomendasi) & opsi ganti `role_name` jadi "Staf TU".

> **Tidak ada kode implementasi yang ditulis.** Dokumen ini menunggu review.
