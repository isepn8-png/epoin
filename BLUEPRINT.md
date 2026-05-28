# BLUEPRINT — E-Poin Siswa (EPOIN)

**Dokumen:** Arsitektur & inventaris fitur (verifikasi dari kode & database)  
**Lokasi project:** `C:\laragon\www\epoin`  
**Database lokal:** `epoin_local` (MySQL 8.x, port `DB_PORT` dari `.env`, default `3308`)  
**Tanggal audit:** 2026-05-22  

> Semua kredensial ditulis sebagai nama variabel saja (`DB_PASSWORD`, `UJIAN_PREVIEW_SECRET`, dll.), bukan nilai aktual. Data pribadi siswa tidak disalin dari database.

---

## 1. Ringkasan Aplikasi

| Aspek | Keterangan |
|-------|------------|
| **Nama** | **EPOIN** / **E-POIN** (E-Point Siswa) — *E-POIN Suite* di UI login |
| **Tujuan** | Mendigitalisasi pencatatan **poin prestasi & pelanggaran**, absensi, penilaian, rapor, ujian (Google Form), dan pengumpulan tugas sekolah dalam satu portal terpadu |
| **Masalah yang diselesaikan** | Pencatatan manual kesiswaan yang lambat, tidak konsisten, sulit diaudit, dan sulit dilaporkan ke orang tua; koordinasi guru–TU–BK terfragmentasi |
| **Pengguna sasaran** | Siswa, guru mapel, wali kelas, guru BK/BP, TU/TAS, guru piket, sekretaris kelas, administrator sekolah, kepala sekolah (via data sekolah/staff) |

**Nilai inti bisnis:** Saldo poin = Σ prestasi − Σ pelanggaran; tahapan pembinaan (SP1–SP4) dan laporan mengacu pada saldo negatif.

---

## 2. Tech Stack & Dependency

### Bahasa & runtime

| Komponen | Versi / keterangan |
|----------|-------------------|
| **PHP** | 8.1+ (lokal teruji 8.3) |
| **MySQL** | 8.x / MariaDB kompatibel, `utf8mb4` |
| **Web server** | Apache (Laragon); production: Apache atau Nginx + PHP-FPM |

### Framework

| Item | Status |
|------|--------|
| Laravel / CodeIgniter / Yii | **Tidak dipakai** sebagai framework utama |
| Pola | **Native PHP monolith**, satu file ≈ satu halaman |

### Dependency PHP (`vendor/`)

| Item | Status |
|------|--------|
| `composer.json` (root) | **Tidak ada** |
| `composer.lock` (root) | **Tidak ada** |
| `vendor/autoload.php` | **Ada** — wajib untuk import Excel |
| Paket produksi utama | `phpoffice/phpspreadsheet` (+ transitif: `ezyang/htmlpurifier`, `markbaker/*`, `maennchen/zipstream-php`) |
| Paket lain di `vendor/` | PHPUnit, Faker, dll. (dev — ikut ter-commit) |

**File yang memuat autoload:** `admin/siswa_import_act.php` (PhpSpreadsheet).

### Dependency frontend (static)

| Lokasi | Isi |
|--------|-----|
| `assets/bower_components/` | Bootstrap 3, jQuery, Font Awesome, dll. |
| `assets/dist/` | AdminLTE 2 build |
| `assets/composer.json` | Metadata template AdminLTE saja — **bukan** dependency PHP aplikasi |

### Konfigurasi environment

| File | Variabel (nama saja) |
|------|----------------------|
| `.env.example` | `APP_ENV`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| `config/database.php` | Membaca `.env` via `includes/env.php` |
| `koneksi.php` | Bootstrap `$koneksi` (mysqli) |

### Database

| Item | Nilai |
|------|--------|
| Engine | InnoDB |
| Nama DB lokal | `epoin_local` |
| Jumlah tabel (terverifikasi `SHOW TABLES`) | **62** (termasuk 3 view) |

---

## 3. Arsitektur Sistem

### Pola arsitektur

```
Browser
   → login.php (UI)
   → periksa_unified.php (router POST)
        ├─ siswa  → periksa_login.php → siswa/*.php
        └─ staff  → periksa_admin.php → includes/auth.php → admin/*.php
   → Setiap halaman: include header.php + koneksi.php + logika + HTML
```

| Lapisan | Implementasi |
|---------|----------------|
| **Presentasi** | PHP inline HTML + AdminLTE 2 + Bootstrap 3 |
| **Aplikasi** | File `.php` per fitur di `admin/` dan `siswa/` |
| **Shared logic** | `includes/` (`auth.php`, `epoin_security.php`, `etugas_helpers.php`, `usage_helper.php`, …) |
| **Data** | mysqli dominan; PDO di `admin/poin_kolektif.php` |
| **Routing** | Manual URL → file (tidak ada front controller) |

### Struktur folder utama

| Folder | Fungsi |
|--------|--------|
| `/` | Entry (`index.php`), login unified, handler auth, `koneksi.php` |
| `admin/` | Panel staff (~140 file PHP): master data, poin, absensi, penilaian, rapor, ujian, e-Tugas |
| `siswa/` | Portal siswa (22 file PHP) |
| `includes/` | RBAC, env, security Stage 1B, helpers modul |
| `config/` | `database.php` |
| `assets/` | CSS/JS vendor (AdminLTE) |
| `database/manual-migrations/` | SQL migrasi terkontrol (e-Tugas) |
| `docs/` | Setup, deployment manifests, laporan agent |
| `uploads/` | File user (rapor HTML, dll.) — **di-ignore Git** |
| `vendor/` | PhpSpreadsheet + dev deps — **harus ikut commit** (tanpa composer.json root) |
| `tests/` | Harness QA CLI (e-Tugas) — di-ignore Git |
| `gambar/`, `library/`, `security/` | Asset/tambahan |

### Multi-tenant

| Aspek | Temuan |
|-------|--------|
| **Skema** | Kolom `sekolah_id` di `user`, `sekolah`, `sekolah_staff`, `tenant_quota`, `usage_log`, penilaian, dll. |
| **Runtime** | **Single-school per database** — `admin/header.php` mengambil `SELECT sekolah_id FROM sekolah LIMIT 1` → `$GLOBALS['SEKOLAH_ID']` |
| **Kesimpulan** | Bukan SaaS multi-sekolah dalam satu DB pada deploy saat ini; satu instalasi ≈ satu sekolah. Multi-tenant penuh = *(perlu konfirmasi)* jika direncanakan subdomain per sekolah dengan DB terpisah |

---

## 4. Daftar Lengkap Modul & Fitur

**Legenda status:** **Selesai** = ada halaman/handler dan dipakai; **Dalam Pengembangan** = partial, placeholder, atau eksternal.

### 4.1 Autentikasi & profil

| Nama Fitur | Fungsi Singkat | File/Folder Terkait | Status |
|------------|----------------|---------------------|--------|
| Login unified | Satu form, pilih peran (siswa/guru/TAS/admin/dll.) | `login.php`, `periksa_unified.php` | Selesai |
| Login siswa | NIS + password → portal siswa | `periksa_login.php` | Selesai (legacy MD5 — lihat §9) |
| Login staff | Username/NIP + password + RBAC | `periksa_admin.php`, `includes/auth.php` | Selesai |
| Logout | Akhiri session | `logout.php`, `admin/logout.php`, `siswa/logout.php` | Selesai |
| Ganti password staff | Ubah password user | `admin/gantipassword.php`, `gantipassword_act.php` | Selesai |
| Ganti password siswa | Ubah password siswa | `siswa/gantipassword.php`, `gantipassword_act.php` | Selesai |
| Profil siswa | Lihat/ubah profil | `siswa/profil.php`, `profil_update.php` | Selesai |

### 4.2 Master data & sekolah

| Nama Fitur | Fungsi Singkat | File/Folder Terkait | Status |
|------------|----------------|---------------------|--------|
| Dashboard admin | KPI ringkas | `admin/index.php` | Selesai |
| Data sekolah & lisensi | Profil sekolah, lisensi, kuota, staff BP | `admin/sekolah.php` | Selesai |
| Manajemen pengguna & role | User staff + assign role | `admin/manajemen_pengguna.php`, `user.php`, `users/` | Selesai |
| Siswa CRUD | Data siswa | `admin/siswa.php`, `siswa_act.php`, `siswa_edit.php`, … | Selesai |
| Import siswa Excel | Import massal via PhpSpreadsheet | `admin/siswa_import.php`, `siswa_import_act.php` | Selesai |
| Kelas & rombel | Kelas per TA, siswa per kelas | `admin/kelas.php`, `kelas_siswa.php`, … | Selesai |
| Jurusan | Master jurusan | `admin/jurusan.php` | Selesai |
| Mapel & pengampu | Mapel, guru pengampu per kelas | `admin/mapel.php`, `pengampu_mapel.php` | Selesai |
| Tahun ajaran (TA) | Master TA | `admin/ta.php` | Selesai |
| Kalender akademik | Libur & hari efektif | `admin/kalender_akademik.php`, tabel `kalender_libur`, `hari_efektif` | Selesai |

### 4.3 Modul poin (inti EPOIN)

| Nama Fitur | Fungsi Singkat | File/Folder Terkait | Status |
|------------|----------------|---------------------|--------|
| Master jenis prestasi | CRUD definisi prestasi + poin | `admin/prestasi.php`, `prestasi_act.php`, … | Selesai |
| Master jenis pelanggaran | CRUD definisi pelanggaran + poin | `admin/pelanggaran.php`, `pelanggaran_act.php`, … | Selesai |
| Input prestasi siswa | Catat prestasi per siswa/kelas | `admin/input_prestasi.php`, `input_prestasi_act.php`, … | Selesai |
| Input pelanggaran siswa | Catat pelanggaran per siswa/kelas | `admin/input_pelanggaran.php`, `input_pelanggaran_act.php`, … | Selesai |
| Input kolektif | Input poin banyak siswa (API/PDO) | `admin/poin_kolektif.php` | Selesai |
| Riwayat & saldo siswa | Riwayat transaksi, saldo, terbit SP | `admin/siswa_riwayat.php`, `includes/epoin_sp_helpers.php` | Selesai |
| Ranking siswa | Peringkat berdasarkan poin | `admin/ranking_siswa.php` | Selesai |
| Cetak SP (SP1–SP4) | Surat peringatan berbasis saldo | `admin/sp1_cetak.php` (template SP; level via `?sp=`) | Selesai |
| Verifikasi SP | Verifikasi penerbitan SP | `admin/verifikasi_sp.php` | Selesai |
| Portal poin siswa | Lihat poin/pelanggaran sendiri | `siswa/poin.php`, `prestasi_saya.php`, `pelanggaran_saya.php` | Selesai |
| Laporan poin | Filter TA/kelas, export Excel | `admin/laporan.php` | Selesai |
| Laporan PDF poin | Export PDF (opsional mPDF) | `admin/laporan_pdf.php` | Selesai *(mPDF tidak terinstall di vendor)* |
| Rekap tahunan poin | Rekap per tahun | `admin/rekap_tahunan.php` | Selesai |
| Export CSV | Export data | `admin/export_csv.php` | Selesai |

### 4.4 Absensi

| Nama Fitur | Fungsi Singkat | File/Folder Terkait | Status |
|------------|----------------|---------------------|--------|
| Absensi harian | Input H/I/S/A per kelas | `admin/absensi_harian.php`, `absensi_harian_detail` | Selesai |
| Absensi per mapel | Sesi absensi mapel | `admin/absensi_mapel.php`, `absensi_sesi*` | Selesai |
| Sinkron / data absensi | Integrasi data absensi | `admin/data_absensi.php` | Selesai |
| Laporan absensi | Laporan & cetak | `admin/laporan_absensi.php`, `absensi_mapel_laporan_cetak.php` | Selesai |
| Rekap absensi bulanan | Rekap bulanan (Excel/PDF opsional) | `admin/rekap_bulanan.php` | Selesai |
| Monitoring absensi | Dashboard monitoring | `admin/header.php` (menu), `get_status.php` | Selesai |
| Petugas absensi (sekretaris) | Assign role sekretaris/piket ke siswa | `admin/pengaturan_petugas_absensi.php` | Selesai |
| Absensi siswa (view) | Lihat absensi | `siswa/absensi.php` | Selesai |
| Permohonan absensi | Workflow permohonan | Tabel `permohonan_absensi` | *(perlu konfirmasi UI)* |

### 4.5 Penilaian & rapor

| Nama Fitur | Fungsi Singkat | File/Folder Terkait | Status |
|------------|----------------|---------------------|--------|
| Tujuan pembelajaran (TP) | Master TP | `admin/tujuan_pembelajaran.php` | Selesai |
| Nilai harian (NH) | Input nilai harian | `admin/nilai_harian.php`, `penilaian/nilai_harian.php`, `nilai_harian_*` | Selesai |
| Nilai PTS | Input nilai PTS | `admin/nilai_pts.php`, `nilai_pts_*` | Selesai |
| Deskripsi rapor (STS/PTS) | Generate/simpan deskripsi AI/manual | `admin/deskripsi_pts.php`, `deskripsi_sts.php`, `ajax/deskripsi_*` | Selesai |
| e-Rapor STS | Nilai, leger, cetak rapor | `admin/rapor_sts_*.php`, `leger_rapor_sts.php`, `cetak_erapor_sts.php` | Selesai |
| Integrasi e-Rapor Dapodik | Placeholder integrasi | Menu modal di `admin/header.php` | **Dalam Pengembangan** |
| Rapor poin ringkas siswa | Ringkasan untuk siswa | `siswa/rapor_poin_ringkas.php` | Selesai |

### 4.6 Ujian & CBT

| Nama Fitur | Fungsi Singkat | File/Folder Terkait | Status |
|------------|----------------|---------------------|--------|
| Ujian Google Form | Kelola ujian GForm, attempt, monitoring | `admin/ujian_gform.php`, `ujian_gform_*`, `siswa/exam_gform.php`, `ujian.php` | Selesai |
| Monitoring attempt live | Pantau pengerjaan | `admin/monitoring_attempt_live.php` | Selesai |
| CBT NESAGUN | Link eksternal | Menu `admin/header.php` | Eksternal |
| CBT Pro demo | Demo terpisah | `admin/cbt_pro_demo.php` | **Dalam Pengembangan** / demo |
| Halaman CBT siswa | Entry CBT | `siswa/cbt.php` | Selesai |

### 4.7 e-Tugas (pengumpulan tugas)

| Nama Fitur | Fungsi Singkat | File/Folder Terkait | Status |
|------------|----------------|---------------------|--------|
| CRUD tugas (guru) | Buat/edit tugas per kelas-mapel | `admin/etugas.php`, `etugas_act.php`, … | Selesai |
| Pengumpulan siswa | Submit teks/link | `siswa/tugas_saya.php`, `tugas_detail.php`, `tugas_kumpulkan.php` | Selesai |
| Review & nilai guru | Review pengumpulan | `admin/etugas_pengumpulan.php`, `etugas_rekap.php` | Selesai |
| Export rekap CSV | Export rekap tugas | `admin/etugas_rekap_export_csv.php` | Selesai |

### 4.8 Utilitas & lainnya

| Nama Fitur | Fungsi Singkat | File/Folder Terkait | Status |
|------------|----------------|---------------------|--------|
| Pengumuman siswa | Pengumuman | `siswa/pengumuman.php`, tabel `pengumuman` | Selesai |
| PIN master admin | Gate fitur sensitif (PIN statis di file) | `admin/verify_pin.php` | Selesai *(hardcoded — risiko)* |
| Log aktivitas guru | Audit aktivitas | `log_aktivitas`, `includes/epoin_security.php` | Selesai |
| Kuota tenant | Disk/bandwidth usage | `includes/usage_helper.php`, `tenant_quota`, `usage_log` | Selesai |
| Tentang aplikasi | Modal marketing fitur | `includes/modal_tentang_epoin.php` | Selesai |

---

## 5. Peran Pengguna & Hak Akses

### 5.1 Portal login (`login.php`)

| Peran di form | Backend | Destinasi |
|---------------|---------|-----------|
| Siswa | `periksa_login.php` | `siswa/` |
| Guru | `periksa_admin.php` + role `guru` | `admin/` |
| Staf TU/TAS | role `tas` | `admin/` |
| Guru Piket | role `piket` | `admin/` (menu terbatas) |
| Pembimbing Eskul / Sekretaris | role `sekretaris` | `admin/` |
| Admin | role `administrator` / `superadmin` | `admin/` |

### 5.2 Role keys (tabel `roles` / `user_roles`)

| Role | Normalisasi | Hak akses umum |
|------|-------------|----------------|
| `administrator` | Juga dari login "admin" | Full menu; kelola sekolah, user, master |
| `superadmin` | Setara admin | Sama administrator |
| `guru` | Login wajib NIP numerik (≥8 digit) | Input nilai, poin, absensi mapel, tugas sesuai pengampu |
| `tas` | TU/TAS | Master data, absensi, laporan |
| `sekretaris` | Sekretaris kelas | Absensi harian terbatas + menu piket/sekretaris |
| `piket` | Guru piket | Subset menu (absensi harian, monitoring) |
| `siswa` | Bukan tabel `user` | Hanya folder `siswa/` |

**Posisi struktural di `sekolah_staff.posisi_key`:** `guru_bp`, `kepala`, `wakasek_kesiswaan` (untuk tanda tangan SP dan data sekolah) — bukan semua dipetakan ke role login terpisah.

### 5.3 RBAC permission (`permissions` / `can()`)

Permission keys **terverifikasi di DB** (7 entri — dominan modul absensi):

| perm_key | Nama |
|----------|------|
| `attendance.harian.input` | Input absensi harian |
| `attendance.harian.view` | Lihat absensi harian |
| `attendance.mapel.own` | Kelola sesi mapel milik sendiri |
| `attendance.view_all` | Lihat semua sesi |
| `attendance.final_any` | Finalisasi sesi |
| `attendance.delete_any` | Hapus sesi |
| `monitoring.view` | Dashboard monitoring |

**Catatan:** Banyak halaman admin hanya memeriksa **login + role**, tidak selalu `can($perm_key)`. Inkonsistensi otorisasi = *(perlu konfirmasi)* untuk hardening bertahap.

### 5.4 Wali kelas & orang tua

| Peran | Temuan |
|-------|--------|
| **Wali kelas** | Data di `kelas_wali` (wali_user_id per TA/kelas) — dipakai cetak SP & konteks kelas, bukan role login terpisah |
| **Orang tua** | **Tidak ada** login portal orang tua terpisah di `login.php`. Notifikasi WA/email disebut di konten marketing — **belum diimplementasi** (§8) |

---

## 6. Skema Database

**Sumber:** `SHOW TABLES` pada `epoin_local` (62 objek), `SHOW COLUMNS` pada tabel inti, `database/manual-migrations/2026-05-17-001-create-etugas-tables.sql`.

### 6.1 Inventaris tabel & view (62)

**Tabel transaksi & master inti**

| Tabel | Fungsi |
|-------|--------|
| `siswa` | Master siswa + kredensial login siswa |
| `user` | Akun staff |
| `roles`, `user_roles`, `permissions`, `role_permissions` | RBAC |
| `jurusan`, `ta`, `kelas`, `kelas_siswa`, `kelas_wali` | Struktur akademik |
| `mapel`, `pengampu_mapel` | Mapel & guru pengampu |
| `pelanggaran`, `prestasi` | Master poin |
| `input_pelanggaran`, `input_prestasi` | Transaksi poin |
| `sp_log` | Log surat peringatan SP1–SP4 |

**Absensi**

| Tabel | Fungsi |
|-------|--------|
| `absensi_harian`, `absensi_harian_detail` | Absensi harian per siswa |
| `absensi_sesi`, `absensi_sesi_detail` | Absensi per sesi mapel |
| `permohonan_absensi` | Permohonan/perubahan absensi |

**Penilaian & rapor**

| Tabel | Fungsi |
|-------|--------|
| `tujuan_pembelajaran` | TP |
| `nilai_harian_set`, `nilai_harian_tp`, `nilai_harian_penilaian`, `nilai_harian_nilai`, `nilai_harian` | Nilai harian |
| `nilai_pts_set`, `nilai_pts_tp`, `nilai_pts` | Nilai PTS |
| `rapor_pts_deskripsi`, `rapor_pts_publish` | Rapor PTS |
| `rapor_sts_files`, `rapor_sts_print_config`, `rapor_sts_publish` | Rapor STS |
| `jenis_penilaian`, `penilaian_setup`, `penilaian_kegiatan` | Setup penilaian |

**Ujian, tugas, pengumuman**

| Tabel | Fungsi |
|-------|--------|
| `ujian_gform`, `ujian_gform_kelas`, `ujian_gform_attempt`, `ujian_gform_violation` | Ujian Google Form |
| `etugas`, `etugas_pengumpulan` | e-Tugas |
| `pengumuman` | Pengumuman |

**Sekolah, lisensi, audit**

| Tabel | Fungsi |
|-------|--------|
| `sekolah`, `sekolah_staff`, `sekolah_license`, `sekolah_license_log`, `license_codes` | Tenant & lisensi |
| `tenant_quota`, `usage_log`, `usage_daily` | Kuota pemakaian |
| `log_aktivitas`, `audit_log` | Audit |

**Kalender**

| Tabel | Fungsi |
|-------|--------|
| `kalender_libur`, `libur_nasional`, `hari_efektif`, `view_non_efektif` | Hari libur & efektif |

**View**

| View | Fungsi |
|------|--------|
| `v_rapor_pts_deskripsi`, `v_rapor_pts_deskripsi_final` | Agregasi deskripsi rapor |
| `v_users_with_roles` | User + roles |

### 6.2 Kolom penting (tabel inti — terverifikasi)

#### `siswa`

| Kolom | Tipe |
|-------|------|
| `siswa_id` | int PK |
| `siswa_nis`, `siswa_nama` | varchar |
| `siswa_jurusan`, `siswa_status` | varchar |
| `siswa_password` | varchar (hash login) |
| `last_login`, `status_login` | datetime / enum |

#### `user`

| Kolom | Tipe |
|-------|------|
| `user_id` | int PK |
| `user_username`, `user_nama`, `user_password` | varchar |
| `linked_siswa_id` | int (tautan ke akun sekretaris dari siswa) |
| `user_level` | varchar (legacy) |

#### `input_pelanggaran` / `input_prestasi`

| Kolom | Keterangan |
|-------|------------|
| `id`, `waktu` | Transaksi |
| `siswa`, `kelas` | FK ke siswa & kelas saat input |
| `pelanggaran` / `prestasi` | FK ke master |
| `ip_ym` / `pr_ym` | Periode (year-month) |

#### `sp_log`

| Kolom | Keterangan |
|-------|------------|
| `siswa_id`, `sp_level` | SP1–SP4 |
| `running_no`, `nomor` | Penomoran surat |
| `alasan`, `signer_*` | Snapshot penandatangan |

#### `sekolah`

| Kolom | Keterangan |
|-------|------------|
| `nama_sekolah`, `npsn`, `domain_name` | Identitas |
| `license_key`, `license_status`, `license_expires_at` | Lisensi |
| `kepala_user_id`, `wakasek_*_id` | Pejabat struktural |

### 6.3 Relasi utama

```
ta (1) ──< kelas (N)
kelas (N) ──< kelas_siswa (N) >── siswa (1)
siswa (1) ──< input_prestasi / input_pelanggaran
prestasi / pelanggaran (1) ──< input_*
siswa (1) ──< sp_log
user (N) ──< user_roles >── roles (N) ──< role_permissions >── permissions
etugas (1) ──< etugas_pengumpulan (N) >── siswa
ujian_gform (1) ──< ujian_gform_attempt (N)
```

**Rumus bisnis (aplikasi):** `saldo = SUM(prestasi_point) − SUM(pelanggaran_point)` per siswa.

---

## 7. Alur Kerja Utama (Workflow)

### 7.1 Input poin pelanggaran

1. Staff login → `admin/input_pelanggaran.php`
2. Pilih TA, kelas, siswa (validasi `kelas_siswa` pada handler Stage 1B)
3. Pilih jenis pelanggaran dari master `pelanggaran`
4. Submit → `input_pelanggaran_act.php` (POST + CSRF)
5. Insert ke `input_pelanggaran` dengan `waktu`, `siswa`, `kelas`, `pelanggaran`
6. Saldo siswa turun; riwayat tampil di `siswa_riwayat.php`

### 7.2 Input poin prestasi

Alur sama dengan pelanggaran, file: `input_prestasi.php` → `input_prestasi_act.php` → tabel `input_prestasi`.

### 7.3 Penerbitan Surat Peringatan (SP)

1. Buka `siswa_riwayat.php?id={siswa_id}` — hitung saldo & tahap pembinaan
2. Jika ambang terpenuhi, modal **Terbitkan SP** → AJAX `?ajax=issue_sp` (POST, CSRF, staff guard)
3. Insert `sp_log` + penandatangan Guru BP (`sekolah_staff`)
4. Redirect cetak → `sp1_cetak.php?id=&sp=SP1` (template level SP)

### 7.4 Absensi harian

1. Sekretaris/piket/TU → `absensi_harian.php`
2. Pilih kelas & tanggal → isi status H/I/S/A per siswa
3. Simpan ke `absensi_harian` + `absensi_harian_detail`
4. Laporan via `laporan_absensi.php` / `rekap_bulanan.php`

### 7.5 Ujian Google Form

1. Guru/admin → `ujian_gform.php` — definisi ujian, link GForm, jadwal
2. Siswa → `siswa/ujian.php` / `exam_gform.php` — attempt tercatat di `ujian_gform_attempt`
3. Monitoring → `monitoring_attempt_live.php`

### 7.6 e-Tugas

1. Guru buat tugas (`etugas`) → status `aktif`
2. Siswa lihat `tugas_saya.php` → submit di `tugas_kumpulkan.php`
3. Guru review `etugas_pengumpulan.php` → rekap/export

### 7.7 Rekap & laporan poin

1. `laporan.php` — filter TA/kelas/urutan/scope saldo → tabel atau export Excel
2. `rekap_tahunan.php` — agregasi per tahun
3. `ranking_siswa.php` — peringkat saldo

### 7.8 Notifikasi orang tua

**Belum diimplementasi** sebagai workflow otomatis. Saldo/SP dapat dilihat siswa di portal; orang tua = *(perlu konfirmasi)* via komunikasi manual atau fitur mendatang.

---

## 8. Integrasi & Layanan Eksternal

| Layanan | Status | Bukti di kode |
|---------|--------|---------------|
| **WhatsApp API / SMS gateway** | Tidak ada | Hanya link `wa.me` di footer/modal (marketing/support) |
| **Email (SMTP/PHPMailer)** | Tidak ada | Tidak ditemukan `mail()` produksi |
| **Payment gateway** | Tidak ada | — |
| **Google Form** | Terintegrasi (link embed) | `ujian_gform.gform_url`, halaman exam siswa |
| **Google Drive/YouTube/Canva** | Link tugas siswa | `etugas_pengumpulan.link_jenis` |
| **CBT NESAGUN** | Eksternal | URL di menu sidebar |
| **e-Rapor Dapodik** | Placeholder | Modal WIP di menu admin |
| **PhpSpreadsheet** | Lokal via `vendor/` | Import siswa Excel |
| **Dompdf / mPDF** | Opsional, **tidak terinstall** | `rekap_bulanan.php`, `laporan_pdf.php` — fallback pesan install Composer |

---

## 9. Fitur Keamanan

| Area | Implementasi | Catatan |
|------|--------------|---------|
| **Auth staff** | `password_verify` (bcrypt) atau MD5 legacy | `includes/auth.php` |
| **Auth siswa** | MD5 + SQL concatenation | `periksa_login.php` — **risiko tinggi**, patch terencana |
| **Session** | PHP `$_SESSION` | Pisah staff vs siswa |
| **RBAC** | `roles`, `permissions`, `can()`, `user_has_role()` | Tidak merata di semua halaman |
| **CSRF** | `includes/epoin_security.php` — modul poin Stage 1/1B, `issue_sp` AJAX | Belum semua form lama |
| **Prepared statements** | `epoin_*` helpers, `auth.php` login, e-Tugas | Banyak file legacy masih concat SQL |
| **Output escaping** | `epoin_h()`, `htmlspecialchars`, `esc()` di laporan | Bertahap |
| **PIN master** | `admin/verify_pin.php` — variabel `$STATIC_PIN` di file | Hardcoded; TTL session `MASTER_PIN_OK_UNTIL` |
| **Presensi PIN** | *(perlu konfirmasi)* | Tidak sama dengan verify_pin; absensi berbasis status H/I/S/A |
| **Env secrets** | `.env` untuk `DB_*` | Jangan commit `.env` |
| **Upload** | `uploads/` di luar Git | Isi file user sensitif |
| **Stage 1B** | `epoin_sp_helpers.php`, CSRF `issue_sp` | Laporan: `STAGE_1B_GO_NO_GO_EPOIN.md` |

**File berisiko di production:** `admin/phpinfo.php`, `admin/tools/reset_epoin.php` — harus diblokir di VPS.

---

## 10. Deployment

| Aspek | Keterangan |
|-------|------------|
| **Lokal** | Laragon — `http://localhost:8088/epoin/` |
| **Production target** | VPS **aaPanel** (dokumen: `DEPLOYMENT_PLAN_GITHUB_AAPANEL.md`) |
| **Domain** | Kolom `sekolah.domain_name` — satu domain per instalasi *(perlu konfirmasi subdomain)* |
| **PHP** | 8.1+, ekstensi: mysqli, mbstring, gd, zip, xml |
| **MySQL** | 8.x, charset utf8mb4 |
| **Deploy metode** | Git clone/rsync + `.env` terpisah + import DB dump + sync `uploads/` manual |
| **Composer di server** | Opsional fase 2; fase 1 **commit `vendor/`** |
| **SSL** | Direkomendasikan untuk production |
| **Backup** | Dump DB + folder `uploads/` di luar repo |

---

## 11. Statistik Codebase

| Metrik | Nilai (terverifikasi 2026-05-22) |
|--------|----------------------------------|
| File PHP `admin/` | **140** |
| File PHP `siswa/` | **22** |
| File PHP `includes/` | **13** |
| File PHP root + `config/` | **~12** |
| **Total file PHP aplikasi (sample)** | **~187** |
| **Baris kode PHP (admin+siswa+includes+config+root)** | **~60.372** |
| Tabel database | **59 tabel** + **3 view** = **62** objek |
| Permission keys terdaftar | **7** (absensi-focused) |
| Migrasi SQL di repo | **1 file** (e-Tugas); sisanya dari dump |
| Modul fungsional (kelompok) | **~8** (auth, master, poin, absensi, penilaian, ujian, etugas, utilitas) |

---

## 12. Keunggulan & Unsur Inovasi

- **Saldo poin netto** (prestasi − pelanggaran) dengan **tahapan pembinaan otomatis** dan penerbitan **SP1–SP4** terintegrasi.
- **Portal ganda** (staff AdminLTE + siswa) dengan login unified satu pintu.
- **RBAC** staff + permission absensi granular (meski belum seluruh modul).
- **Modul terpadu** dalam satu DB: poin, absensi harian/mapel, nilai NH/PTS, rapor STS, ujian GForm, e-Tugas.
- **Input kolektif** multi-kelas untuk efisiensi guru/TU.
- **Ujian GForm** dengan monitoring attempt & pelanggaran (fullscreen/embed).
- **e-Tugas** dengan pengumpulan teks/link (Drive, YouTube, Canva).
- **Kuota tenant** (`usage_log`) untuk monitoring pemakaian hosting.
- **Import Excel** siswa via PhpSpreadsheet.
- **Branding sekolah** (`theme_brand.php`, data `sekolah`).

---

## 13. Roadmap / Fitur dalam Pengembangan

| Item | Sumber | Prioritas |
|------|--------|-----------|
| Patch keamanan login siswa (`periksa_login.php`) | `SECURITY_AUDIT_EPOIN.md`, `STAGE_1B_GO_NO_GO` | Tinggi |
| Hardening `sp2_cetak` … `sp4_cetak` sama seperti `sp1_cetak` | Retest Stage 1B | Sedang |
| `composer.json` root + `composer install --no-dev` | `VENDOR_COMPOSER_DECISION_EPOIN.md` | Sedang |
| Integrasi e-Rapor Dapodik | Menu WIP `admin/header.php` | Sedang |
| Notifikasi WhatsApp/email ke orang tua | Marketing copy saja | Rendah / direncanakan |
| CBT Pro penuh | `cbt_pro_demo.php` | Rendang |
| Dompdf/mPDF untuk PDF laporan | Opsional, belum di `vendor/` | Rendang |
| Perluasan permission RBAC ke modul poin | Audit security | Sedang |
| GitHub init & CI | `GITHUB_READINESS_EPOIN.md` | Operasional |
| Multi-tenant SaaS satu DB | Skema ada, runtime single-school | *(perlu konfirmasi produk)* |

---

## Lampiran: Dokumen terkait di repo

| File | Isi |
|------|-----|
| `PROJECT_BLUEPRINT_EPOIN.md` | Blueprint teknis sebelumnya |
| `DATABASE_BLUEPRINT_EPOIN.md` | Detail DB |
| `SECURITY_AUDIT_EPOIN.md` | Audit keamanan |
| `GITHUB_READINESS_EPOIN.md` | Kesiapan Git |
| `DEPLOYMENT_PLAN_GITHUB_AAPANEL.md` | Deploy VPS |

---

*Dokumen ini disusun dari inspeksi langsung terhadap filesystem dan database `epoin_local`. Untuk perubahan skema setelah tanggal audit, jalankan ulang `SHOW TABLES` / `SHOW COLUMNS`.*
