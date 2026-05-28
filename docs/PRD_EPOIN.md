# 📋 PRODUCT REQUIREMENTS DOCUMENT (PRD)
# EPOIN — E-Point Siswa

**Versi Dokumen:** 1.0  
**Tanggal:** 2026-05-28  
**Status:** Final Draft  
**Sumber Analisis:** Inspeksi langsung kode sumber (~187 file PHP) dan database `epoin_local` (62 tabel)

---

## 1. Ringkasan Eksekutif

**EPOIN** (E-POIN / E-Point Siswa) adalah platform digital terpadu untuk sekolah yang mendigitalisasi pencatatan **poin prestasi & pelanggaran siswa**, absensi, penilaian akademik, rapor, ujian berbasis Google Form, dan pengumpulan tugas dalam satu portal. Aplikasi ini menyelesaikan masalah pencatatan manual kesiswaan yang lambat, tidak konsisten, sulit diaudit, dan sulit dilaporkan ke orang tua.

### 1.1 Visi Produk

> *Menjadi platform manajemen kesiswaan terpadu #1 untuk sekolah di Indonesia, mengintegrasikan seluruh aspek akademik dan non-akademik siswa dalam satu sistem yang efisien, transparan, dan dapat dipertanggungjawabkan.*

### 1.2 Proposisi Nilai Inti

| Aspek | Nilai |
|-------|-------|
| **Efisiensi** | Menggantikan pencatatan manual dan lembar kertas dengan sistem digital real-time |
| **Transparansi** | Siswa dapat melihat poin, absensi, dan tugas secara langsung melalui portal |
| **Akuntabilitas** | Audit trail untuk setiap transaksi poin dan aktivitas guru |
| **Integrasi** | Satu platform untuk semua kebutuhan: poin, absensi, nilai, rapor, ujian, tugas |
| **Pembinaan Otomatis** | Sistem tahapan pembinaan (SP1–SP4) berbasis saldo poin negatif |

---

## 2. Stakeholder & Pengguna

### 2.1 Stakeholder Utama

| Stakeholder | Kepentingan |
|-------------|-------------|
| Kepala Sekolah | Monitoring performa kesiswaan, tanda tangan SP |
| Wakasek Kesiswaan | Kebijakan poin, pembinaan siswa |
| Guru BK/BP | Penerbitan Surat Peringatan, konseling |
| TU/TAS | Administrasi data siswa, kelas, laporan |
| Developer/Admin IT | Pemeliharaan dan pengembangan sistem |

### 2.2 Pengguna (User Personas)

#### 👨‍💼 Administrator / Superadmin
- **Deskripsi:** Pengelola utama sistem
- **Kebutuhan:** Full access semua modul, kelola user, master data, backup/restore
- **Frekuensi Akses:** Harian
- **Kriteria Login:** Username alfanumerik (bukan NIP)

#### 👩‍🏫 Guru Mata Pelajaran
- **Deskripsi:** Pengajar yang menginput nilai dan absensi per mapel
- **Kebutuhan:** Input poin, absensi mapel, nilai NH/PTS, buat tugas (e-Tugas)
- **Frekuensi Akses:** Harian
- **Kriteria Login:** NIP/NUPTK numerik (≥8 digit)

#### 👨‍💼 Wali Kelas
- **Deskripsi:** Guru yang bertanggung jawab atas satu kelas
- **Kebutuhan:** Monitoring poin siswa di kelas, cetak SP, rapor
- **Frekuensi Akses:** Harian–Mingguan
- **Data Relasi:** Tabel `kelas_wali` (wali_user_id per TA/kelas)

#### 📋 Staf TU/TAS
- **Deskripsi:** Tata Usaha yang mengelola administrasi
- **Kebutuhan:** Master data siswa/kelas, absensi, laporan, export
- **Frekuensi Akses:** Harian
- **Kriteria Login:** NIP numerik, role `tas`

#### 🔐 Guru Piket
- **Deskripsi:** Guru bertugas harian untuk monitoring kehadiran
- **Kebutuhan:** Input absensi harian, monitoring dashboard
- **Frekuensi Akses:** Sesuai jadwal piket
- **Akses:** Subset menu (absensi harian, monitoring)

#### 📝 Sekretaris Kelas
- **Deskripsi:** Siswa yang ditugaskan sebagai petugas absensi kelas
- **Kebutuhan:** Input absensi harian kelas sendiri
- **Frekuensi Akses:** Harian
- **Akses:** Terbatas menu absensi + piket

#### 🎓 Siswa
- **Deskripsi:** Peserta didik pengguna portal siswa
- **Kebutuhan:** Lihat poin/saldo, absensi, kerjakan ujian, kumpulkan tugas
- **Frekuensi Akses:** Harian–Mingguan
- **Kriteria Login:** NIS + password

---

## 3. Ruang Lingkup Produk

### 3.1 Dalam Lingkup (In Scope)

| # | Modul | Status |
|---|-------|--------|
| 1 | Autentikasi & Manajemen User (login unified, RBAC) | ✅ Selesai |
| 2 | Master Data Sekolah (siswa, kelas, jurusan, TA, mapel) | ✅ Selesai |
| 3 | **Modul Poin** (inti EPOIN — prestasi & pelanggaran) | ✅ Selesai |
| 4 | Absensi (harian & per mapel) | ✅ Selesai |
| 5 | Penilaian & Rapor (NH, PTS, STS, e-Rapor) | ✅ Selesai |
| 6 | Ujian & CBT (Google Form, monitoring attempt) | ✅ Selesai |
| 7 | e-Tugas (pengumpulan tugas online) | ✅ Selesai |
| 8 | Utilitas (pengumuman, kalender, log audit, kuota) | ✅ Selesai |

### 3.2 Di Luar Lingkup Saat Ini (Out of Scope)

| Item | Status |
|------|--------|
| Portal Orang Tua (login terpisah) | Belum diimplementasi |
| Notifikasi WhatsApp/Email otomatis | Hanya link `wa.me` untuk support |
| Payment Gateway (SPP/pembayaran) | Tidak direncanakan |
| CBT Native (soal built-in) | Hanya demo (`cbt_pro_demo.php`) |
| Multi-tenant SaaS (banyak sekolah 1 DB) | Skema partial, runtime single-school |
| Integrasi e-Rapor Dapodik | Placeholder/WIP |
| Mobile App (Android/iOS) | Tidak direncanakan |

---

## 4. Kebutuhan Fungsional (Functional Requirements)

### 4.1 FR-AUTH: Autentikasi & Profil

| ID | Fitur | Deskripsi | Prioritas |
|----|-------|-----------|-----------|
| FR-AUTH-01 | Login Unified | Satu form login dengan pilihan peran (siswa/guru/TAS/admin/piket/sekretaris) | P0 |
| FR-AUTH-02 | Login Siswa | Autentikasi via NIS + password → redirect portal siswa | P0 |
| FR-AUTH-03 | Login Staff | Autentikasi via username/NIP + password + validasi RBAC → redirect admin panel | P0 |
| FR-AUTH-04 | Logout | Akhiri session dan update status offline | P0 |
| FR-AUTH-05 | Ganti Password Staff | Form ubah password dengan validasi password lama | P1 |
| FR-AUTH-06 | Ganti Password Siswa | Form ubah password siswa | P1 |
| FR-AUTH-07 | Profil Siswa | View/update data profil siswa | P2 |
| FR-AUTH-08 | RBAC Permission | Sistem roles → permissions dengan `can()`, `user_has_role()` | P0 |
| FR-AUTH-09 | Session Management | Pisah session staff vs siswa, guard per folder | P0 |

### 4.2 FR-MASTER: Master Data & Sekolah

| ID | Fitur | Deskripsi | Prioritas |
|----|-------|-----------|-----------|
| FR-MASTER-01 | Dashboard Admin | KPI ringkas: jumlah siswa, poin, ranking, aktivitas terbaru | P0 |
| FR-MASTER-02 | Data Sekolah | Profil sekolah, logo, NPSN, konfigurasi lisensi | P1 |
| FR-MASTER-03 | Manajemen Pengguna | CRUD user staff + assign role (guru/tas/admin/piket/sekretaris) | P0 |
| FR-MASTER-04 | CRUD Siswa | Tambah/edit/hapus data siswa (NIS, nama, jurusan, status) | P0 |
| FR-MASTER-05 | Import Siswa Excel | Import massal data siswa via file .xlsx (PhpSpreadsheet) | P1 |
| FR-MASTER-06 | Master Kelas & Rombel | Kelas per tahun ajaran, penempatan siswa ke kelas | P0 |
| FR-MASTER-07 | Master Jurusan | CRUD jurusan/program keahlian | P1 |
| FR-MASTER-08 | Master Mapel & Pengampu | CRUD mata pelajaran + assign guru pengampu per kelas | P0 |
| FR-MASTER-09 | Tahun Ajaran (TA) | CRUD tahun ajaran dengan semester aktif | P0 |
| FR-MASTER-10 | Kalender Akademik | Hari libur nasional, hari efektif per bulan | P2 |
| FR-MASTER-11 | Wali Kelas | Assign guru sebagai wali kelas per TA | P1 |

### 4.3 FR-POIN: Modul Poin (Inti EPOIN)

| ID | Fitur | Deskripsi | Prioritas |
|----|-------|-----------|-----------|
| FR-POIN-01 | Master Jenis Prestasi | CRUD definisi prestasi + besaran poin | P0 |
| FR-POIN-02 | Master Jenis Pelanggaran | CRUD definisi pelanggaran + besaran poin | P0 |
| FR-POIN-03 | Input Prestasi Siswa | Catat prestasi per siswa/kelas (pilih TA → kelas → siswa → jenis) | P0 |
| FR-POIN-04 | Input Pelanggaran Siswa | Catat pelanggaran per siswa/kelas | P0 |
| FR-POIN-05 | Input Poin Kolektif | Input poin untuk banyak siswa sekaligus (bulk) | P1 |
| FR-POIN-06 | Riwayat & Saldo Siswa | Tampilkan riwayat gabungan (prestasi + pelanggaran) dan saldo poin per siswa | P0 |
| FR-POIN-07 | Ranking Siswa | Peringkat siswa berdasarkan saldo poin | P1 |
| FR-POIN-08 | Cetak Surat Peringatan (SP1–SP4) | Generate surat pembinaan berdasarkan saldo negatif | P0 |
| FR-POIN-09 | Verifikasi SP | Verifikasi keaslian nomor surat SP via hash URL | P2 |
| FR-POIN-10 | Portal Poin Siswa | Siswa lihat poin, riwayat prestasi/pelanggaran sendiri | P0 |
| FR-POIN-11 | Laporan Poin | Filter per TA/kelas + export Excel | P1 |
| FR-POIN-12 | Laporan PDF | Export laporan poin dalam format PDF | P2 |
| FR-POIN-13 | Rekap Tahunan | Rekap poin per tahun kalender | P2 |
| FR-POIN-14 | Export CSV | Export data master + sp_log ke format CSV/ZIP | P2 |

#### Rumus Bisnis Poin

```
saldo = Σ prestasi_point − Σ pelanggaran_point
negSaldo = max(0, -saldo)
```

#### Tahapan Pembinaan (SP)

| Tahap | negSaldo | Program Pembinaan | Surat |
|-------|----------|-------------------|-------|
| I | 1–20 | Pembinaan umum / teguran | SP1 |
| II | 21–40 | Panggilan orang tua | SP1 |
| III | 41–60 | Peringatan resmi | SP2 |
| IV | 61–80 | Pembinaan khusus | SP3 |
| V | 81–90 | Konferensi kasus | SP4 |
| V | 91–99 | Tidak naik kelas | SP4 |
| VI | 100+ | Pemulangan ke orang tua | SP4 |

### 4.4 FR-ABSEN: Absensi

| ID | Fitur | Deskripsi | Prioritas |
|----|-------|-----------|-----------|
| FR-ABSEN-01 | Absensi Harian | Input status H/I/S/A per siswa per kelas per hari | P0 |
| FR-ABSEN-02 | Absensi Per Mapel | Input absensi berdasarkan sesi mata pelajaran | P1 |
| FR-ABSEN-03 | Data Absensi Terpadu | Sinkronisasi dan integrasi data absensi | P1 |
| FR-ABSEN-04 | Laporan Absensi | Laporan kehadiran + cetak | P1 |
| FR-ABSEN-05 | Rekap Absensi Bulanan | Rekap absensi per bulan (Excel/PDF opsional) | P2 |
| FR-ABSEN-06 | Monitoring Absensi | Dashboard monitoring status kehadiran harian | P1 |
| FR-ABSEN-07 | Petugas Absensi | Assign siswa sebagai sekretaris/petugas absensi | P2 |
| FR-ABSEN-08 | Portal Absensi Siswa | Siswa lihat rekap absensi sendiri | P1 |
| FR-ABSEN-09 | Finalisasi Absensi | Lock absensi agar tidak bisa diubah setelah finalisasi | P1 |

### 4.5 FR-NILAI: Penilaian & Rapor

| ID | Fitur | Deskripsi | Prioritas |
|----|-------|-----------|-----------|
| FR-NILAI-01 | Tujuan Pembelajaran (TP) | Master tujuan pembelajaran per mapel | P1 |
| FR-NILAI-02 | Nilai Harian (NH) | Input nilai harian per TP per siswa | P0 |
| FR-NILAI-03 | Nilai PTS | Input nilai Penilaian Tengah Semester | P0 |
| FR-NILAI-04 | Deskripsi Rapor | Generate/simpan deskripsi rapor (AI/manual) untuk STS/PTS | P1 |
| FR-NILAI-05 | e-Rapor STS | Generate, leger, dan cetak rapor Sumatif Tengah Semester | P0 |
| FR-NILAI-06 | Status Penilaian | Monitoring kelengkapan input nilai per guru/mapel | P2 |
| FR-NILAI-07 | Rapor Poin Ringkas | Ringkasan poin untuk cetak siswa | P2 |

### 4.6 FR-UJIAN: Ujian & CBT

| ID | Fitur | Deskripsi | Prioritas |
|----|-------|-----------|-----------|
| FR-UJIAN-01 | Ujian Google Form | Kelola ujian berbasis Google Form (link embed, jadwal) | P1 |
| FR-UJIAN-02 | Monitoring Attempt | Pantau pengerjaan ujian secara live | P1 |
| FR-UJIAN-03 | Attempt & Violation Tracking | Rekam percobaan ujian dan pelanggaran (fullscreen exit) | P1 |
| FR-UJIAN-04 | Portal Ujian Siswa | Akses ujian dengan proteksi tab/window | P1 |

### 4.7 FR-TUGAS: e-Tugas

| ID | Fitur | Deskripsi | Prioritas |
|----|-------|-----------|-----------|
| FR-TUGAS-01 | CRUD Tugas (Guru) | Buat/edit/hapus tugas per kelas-mapel (multi-kelas support) | P1 |
| FR-TUGAS-02 | Pengumpulan Siswa | Submit tugas via teks/link (Google Drive, YouTube, Canva) | P1 |
| FR-TUGAS-03 | Review & Penilaian | Guru review pengumpulan, beri nilai/komentar | P1 |
| FR-TUGAS-04 | Rekap & Export | Rekap pengumpulan per tugas + export CSV | P2 |

### 4.8 FR-UTIL: Utilitas

| ID | Fitur | Deskripsi | Prioritas |
|----|-------|-----------|-----------|
| FR-UTIL-01 | Pengumuman | Publish pengumuman ke portal siswa | P2 |
| FR-UTIL-02 | PIN Master Admin | Gate fitur sensitif (backup/restore DB) dengan PIN | P1 |
| FR-UTIL-03 | Log Aktivitas Guru | Audit trail untuk aktivitas input data | P1 |
| FR-UTIL-04 | Kuota Tenant | Monitor pemakaian disk/bandwidth per sekolah | P2 |
| FR-UTIL-05 | Branding Sekolah | Kustomisasi logo, warna, nama sekolah di tampilan | P2 |
| FR-UTIL-06 | Backup & Restore DB | Backup/restore database dari panel admin | P1 |
| FR-UTIL-07 | Tentang Aplikasi | Modal informasi fitur-fitur EPOIN | P3 |

---

## 5. Kebutuhan Non-Fungsional (Non-Functional Requirements)

### 5.1 Performa

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-PERF-01 | Response time halaman | < 3 detik pada koneksi broadband |
| NFR-PERF-02 | Concurrent users | Minimal 100 user simultan |
| NFR-PERF-03 | Database query | Tidak ada query yang timeout > 30 detik |

### 5.2 Keamanan

| ID | Requirement | Status Saat Ini | Target |
|----|-------------|-----------------|--------|
| NFR-SEC-01 | Hashing password | MD5 (siswa), bcrypt+MD5 (staff) | Semua bcrypt/argon2 |
| NFR-SEC-02 | SQL injection prevention | Campuran (prepared stmt + raw) | 100% prepared statements |
| NFR-SEC-03 | CSRF protection | Partial (hanya e-Tugas & SP) | Global di semua POST |
| NFR-SEC-04 | XSS prevention | Tidak konsisten | htmlspecialchars policy global |
| NFR-SEC-05 | RBAC enforcement | Tidak merata | `can()` di setiap halaman/aksi |
| NFR-SEC-06 | File upload security | Bervariasi | Whitelist ekstensi + nama random |
| NFR-SEC-07 | Session management | Basic PHP session | + session_regenerate_id |
| NFR-SEC-08 | Security headers | Tidak ada | HSTS, CSP, X-Frame-Options |
| NFR-SEC-09 | HTTPS | Opsional di lokal | Wajib di production |
| NFR-SEC-10 | Sensitive file protection | `.env` di `.gitignore` | Block akses HTTP ke `.env`, `.git` |

### 5.3 Ketersediaan & Reliabilitas

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-AVAIL-01 | Uptime | 99.5% (non-SLA sekolah) |
| NFR-AVAIL-02 | Backup reguler | Dump DB + uploads harian |
| NFR-AVAIL-03 | Rollback capability | Restore dari backup < 30 menit |
| NFR-AVAIL-04 | Data integrity | FK constraints + InnoDB transactions |

### 5.4 Kompatibilitas

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-COMPAT-01 | Browser support | Chrome, Firefox, Edge (terbaru), Safari |
| NFR-COMPAT-02 | Responsive layout | AdminLTE responsive (mobile-friendly sidebar) |
| NFR-COMPAT-03 | PHP version | 8.1+ (teruji 8.3) |
| NFR-COMPAT-04 | MySQL version | 8.x / MariaDB compatible, utf8mb4 |

### 5.5 Skalabilitas

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-SCALE-01 | Jumlah siswa | Hingga 2.000 siswa per instalasi |
| NFR-SCALE-02 | Data historis | Retensi data ≥ 3 tahun ajaran |
| NFR-SCALE-03 | Multi-school | 1 instalasi = 1 sekolah (skema multi-tenant disiapkan) |

---

## 6. Integrasi Eksternal

| Layanan | Status | Mekanisme |
|---------|--------|-----------|
| **Google Form** | ✅ Terintegrasi | Embed URL ujian di `ujian_gform.gform_url` |
| **Google Drive/YouTube/Canva** | ✅ Terintegrasi | Link submit tugas di e-Tugas |
| **PhpSpreadsheet** | ✅ Terintegrasi | Import siswa Excel via `vendor/` |
| **FPDF** | ✅ Tersedia | Library PDF di `library/fpdf185/` |
| **WhatsApp API** | ❌ Belum | Hanya link `wa.me` untuk support manual |
| **Email (SMTP)** | ❌ Belum | Tidak ada `mail()` atau PHPMailer |
| **Payment Gateway** | ❌ Tidak ada | Tidak direncanakan |
| **e-Rapor Dapodik** | 🔄 WIP | Placeholder modal di menu admin |
| **Dompdf / mPDF** | ⚠️ Opsional | Tidak terinstall di vendor, fallback pesan |
| **CBT NESAGUN** | 🔗 Eksternal | URL link di menu sidebar |

---

## 7. User Stories

### Modul Poin (Epic)

> **US-POIN-01:** Sebagai **guru**, saya ingin **mencatat pelanggaran siswa** dengan memilih jenis pelanggaran dari daftar master, sehingga **poin pelanggaran siswa terekam secara digital dan otomatis mengurangi saldo poin.**

> **US-POIN-02:** Sebagai **guru BK**, saya ingin **menerbitkan surat peringatan (SP)** ketika saldo poin negatif siswa melewati ambang batas, sehingga **proses pembinaan dapat berjalan secara terstruktur dan terdokumentasi.**

> **US-POIN-03:** Sebagai **siswa**, saya ingin **melihat saldo poin dan riwayat pelanggaran/prestasi saya** di portal, sehingga **saya dapat memantau status pembinaan sendiri.**

> **US-POIN-04:** Sebagai **admin/TU**, saya ingin **melakukan input poin kolektif** untuk banyak siswa sekaligus (misal pelanggaran upacara), sehingga **proses lebih efisien.**

### Modul Absensi (Epic)

> **US-ABSEN-01:** Sebagai **sekretaris kelas**, saya ingin **menginput absensi harian** kelas saya, sehingga **data kehadiran tersimpan real-time.**

> **US-ABSEN-02:** Sebagai **guru mapel**, saya ingin **mencatat absensi per sesi mapel**, sehingga **kehadiran siswa per mata pelajaran terdata.**

### Modul Penilaian (Epic)

> **US-NILAI-01:** Sebagai **guru mapel**, saya ingin **menginput nilai harian berdasarkan tujuan pembelajaran (TP)**, sehingga **penilaian formatif tersimpan secara terstruktur.**

> **US-NILAI-02:** Sebagai **wali kelas**, saya ingin **mencetak rapor STS** dengan leger nilai lengkap, sehingga **rapor dapat didistribusikan ke siswa/orang tua.**

### Modul e-Tugas (Epic)

> **US-TUGAS-01:** Sebagai **guru**, saya ingin **membuat tugas untuk satu atau beberapa kelas**, sehingga **siswa dapat mengumpulkan tugas secara online.**

> **US-TUGAS-02:** Sebagai **siswa**, saya ingin **mengumpulkan tugas via link (Google Drive, YouTube)**, sehingga **guru dapat me-review secara terpusat.**

---

## 8. Roadmap & Fitur yang Direncanakan

### Phase 1 — Security Hardening (🔴 Critical)

| Item | Prioritas | Estimasi |
|------|-----------|----------|
| Patch SQL injection login siswa (`periksa_login.php`) | P0-Critical | 1 hari |
| Migrasi password siswa ke bcrypt | P0-Critical | 2 hari |
| CSRF global untuk semua POST admin | P0-High | 3 hari |
| Prepared statements di semua handler lama | P0-High | 5 hari |
| Hapus/block `phpinfo.php`, `reset_epoin.php` | P0-Critical | 1 jam |

### Phase 2 — Code Quality (🟡 High)

| Item | Prioritas |
|------|-----------|
| Standarisasi RBAC `can()` di semua halaman | High |
| Pindahkan credential hardcoded ke `.env` | High |
| Buat `composer.json` root + `composer install --no-dev` | Medium |
| XSS escaping policy global (`htmlspecialchars`) | Medium |

### Phase 3 — Feature Expansion (🟢 Medium–Low)

| Item | Prioritas |
|------|-----------|
| Integrasi e-Rapor Dapodik | Medium |
| Notifikasi WhatsApp ke orang tua | Medium |
| Portal orang tua | Low |
| CBT Pro penuh (soal built-in) | Low |
| Dompdf/mPDF untuk PDF laporan | Low |
| Multi-tenant SaaS (1 DB multi-school) | Low |
| Grafik analitik poin per kelas | Medium |
| Approval workflow sebelum poin final | Medium |

---

## 9. Metrik Keberhasilan (KPIs)

| Metrik | Target | Cara Ukur |
|--------|--------|-----------|
| Adoption rate | 80% guru aktif | Active user dari `last_login` |
| Data entry speed | 50% lebih cepat vs manual | Benchmark waktu input |
| SP processing time | < 5 menit per SP | Dari input → cetak surat |
| Absensi completion | 95% kelas terinput harian | Dashboard monitoring |
| Siswa portal usage | 60% siswa pernah login | Data `siswa.last_login` |
| System uptime | 99.5% | Monitoring server |
| Zero critical vulnerability | 0 SQLi/RCE exposed | Security audit berkala |

---

## 10. Asumsi & Kendala

### Asumsi

1. Setiap sekolah memiliki koneksi internet yang stabil
2. Guru dan staf familiar dengan browser web
3. Sekolah memiliki VPS/hosting dengan PHP 8.1+ dan MySQL 8.x
4. Setiap siswa memiliki NIS unik
5. 1 instalasi EPOIN = 1 sekolah

### Kendala

1. **Tidak ada framework** — setiap fitur baru harus mengikuti pola page-based monolith
2. **Legacy code** — banyak file lama belum di-migrate ke prepared statements
3. **Tidak ada unit test** otomatis (hanya QA harness untuk e-Tugas)
4. **Vendor directory** harus di-commit karena tidak ada `composer.json` root
5. **Schema DB** tidak fully versioned (hanya 1 migrasi manual untuk e-Tugas)

---

## 11. Glosarium

| Istilah | Definisi |
|---------|----------|
| **Poin** | Nilai numerik yang menandakan prestasi (+) atau pelanggaran (−) siswa |
| **Saldo** | Selisih total poin prestasi dikurangi total poin pelanggaran |
| **negSaldo** | Besaran saldo negatif: `max(0, -saldo)` |
| **SP (Surat Peringatan)** | Surat pembinaan resmi dari sekolah (SP1–SP4) berdasarkan ambang negSaldo |
| **TA** | Tahun Ajaran |
| **NH** | Nilai Harian |
| **PTS** | Penilaian Tengah Semester |
| **STS** | Sumatif Tengah Semester |
| **TP** | Tujuan Pembelajaran |
| **NIS** | Nomor Induk Siswa |
| **NIP/NUPTK** | Nomor Induk Pegawai / Nomor Unik Pendidik dan Tenaga Kependidikan |
| **RBAC** | Role-Based Access Control |
| **CSRF** | Cross-Site Request Forgery |
| **e-Tugas** | Modul pengumpulan tugas online |
| **AdminLTE** | Template dashboard admin berbasis Bootstrap |

---

*Dokumen ini disusun dari analisis langsung terhadap seluruh codebase EPOIN, database `epoin_local`, dan dokumentasi internal proyek.*
