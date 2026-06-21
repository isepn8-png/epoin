# AUDIT MODUL SISWA — EPOIN

**Tanggal:** 2026-06-22
**Auditor:** Senior PHP Engineer + UI/UX (audit mendalam — Opus)
**Scope:** `admin/siswa.php`, `admin/siswa_riwayat.php`
**Aturan kerja:** Fatal/Critical/High langsung fix bila aman; Medium/Low didokumentasikan. **Tidak menyentuh logika poin/SP/saldo.** Fitur existing (WA ortu, modal jenjang, SP, filter) dipertahankan.

---

## RINGKASAN EKSEKUTIF

| Aspek | Status |
|-------|--------|
| Fatal error / PHP error saat halaman dibuka | ✅ Tidak ditemukan |
| Bug kosmetik / konsistensi | 🟢 3 ditemukan → **sudah fix** (aman) |
| Logika saldo / SP / tahap pembinaan | ✅ Konsisten & benar (diverifikasi read-only, tidak diubah) |
| Fitur existing (WA, modal jenjang, SP, filter, copy NIS) | ✅ Tidak tersentuh, tetap jalan |
| Keamanan (CSRF, XSS, prepared statement) | 🟢 Mayoritas baik; 1 temuan **HIGH** auth (perlu konfirmasi Bos) |
| Performa query | 🟡 1 catatan optimasi (subquery korelasi 401 siswa) |

**Tidak ada perubahan pada perhitungan poin, ambang SP, atau penentuan tahap pembinaan.**

---

## FASE 1 — AUDIT FUNGSIONAL

### A. PHP error / warning / notice
- Kedua file: `php -l` **0 error**.
- Pemakaian null-coalescing (`?? ''`) konsisten pada akses array hasil query → minim notice.
- `siswa_riwayat.php` memanggil `epoin_fetch_siswa_row()` lalu memvalidasi `if(!$k)` sebelum dipakai → aman dari "fetch on null".

### B. Query MySQL

| Lokasi | Query | Temuan |
|--------|-------|--------|
| `siswa.php:28` | `GROUP BY j.jurusan_id, j.jurusan_nama` | ✅ Aman terhadap `only_full_group_by` |
| `siswa.php:47` | `GROUP BY k.kelas_id, k.kelas_nama` | ✅ Aman |
| `siswa.php:59` | Tabel utama: saldo via **subquery korelasi** (SUM prestasi − SUM pelanggaran) per baris | 🟡 Lihat catatan performa (P-OPT-1) |
| `siswa_riwayat.php` (saldo) | `epoin_sum_prestasi_siswa()` / `epoin_sum_pelanggaran_siswa()` | ✅ **Prepared statement**, `COALESCE(SUM,0)` |
| `siswa_riwayat.php` (data siswa) | `epoin_fetch_siswa_row()` | ✅ **Prepared statement** |
| `siswa_riwayat.php:199` | `$qlast` UNION ALL prestasi+pelanggaran | ⚠️ Pakai interpolasi `'$id_siswa'` — **aman** karena `$id_siswa = (int)` (cast), tapi idealnya prepared (L-3) |
| `siswa_riwayat.php:600/654` | Riwayat prestasi & pelanggaran | ⚠️ Idem — `$id_siswa` sudah `(int)`, aman dari injection |
| `siswa_riwayat.php` (`save_hp_ortu`) | `UPDATE siswa SET hp_ortu=?` | ✅ **Prepared statement + CSRF** |

**Kesimpulan injeksi:** Tidak ada SQL injection yang dapat dieksploitasi — semua input dari `$_GET['id']` di-cast `(int)` sebelum masuk query, dan endpoint AJAX memakai prepared statement.

### C. Logika bisnis (verifikasi read-only — TIDAK diubah)
- **Saldo poin** = `totalPrestasi − totalPelanggaran`. Konsisten antara `siswa.php` (subquery) dan `siswa_riwayat.php` (helper prepared). ✅
- **Tahap pembinaan** ditentukan dari `$negSaldo = max(0, -$saldo)` lalu dipetakan ke `$STAGES`. Saat saldo ≥ 0 → `$SAFE_STAGE` (Aman/Apresiasi). ✅
- **`$levelActive`** (modal jenjang) memetakan negSaldo → 1..6, selaras dengan rentang `$STAGES`. ✅
- **Ambang SP**: SP1≥21, SP2≥41, SP3≥61, SP4≥81 negSaldo; mode sekuensial mensyaratkan SP sebelumnya sudah terbit (`$sp1_issued` dst). **Hard guard**: bila `saldo ≥ 0` semua penerbitan SP baru dinonaktifkan, cetak ulang tetap boleh. ✅ Logika koheren.

### D. Fitur existing — verifikasi tetap jalan
| Fitur | Status |
|-------|--------|
| Smart WA button (ada nomor → link wa.me; kosong → modal tambah) | ✅ Tidak tersentuh |
| Normalisasi `08xx → 628xx` untuk wa.me | ✅ Benar |
| Quick-add modal HP ortu (AJAX, CSRF, prepared, reload) | ✅ Tidak tersentuh |
| Modal Jenjang Pembinaan (`jenjang_pembinaan_modal.php`) | ✅ Include dipertahankan |
| Tombol SP1–SP4 (terbit/cetak ulang + modal alasan + Select2 BP) | ✅ Tidak tersentuh |
| Filter jurusan/kelas/status + chips + reset + copy NIS (siswa.php) | ✅ Tidak tersentuh |
| DataTables (`#students-table`, `#tbl-prestasi`, `#tbl-pelanggaran`) | ✅ ID unik, init **setelah** footer.php → tidak bentrok auto-init `#table-datatable` |

### E. Edge case
| Kasus | Perilaku |
|-------|----------|
| Siswa tanpa transaksi | `COALESCE(...,0)` → saldo 0 → tahap Aman. ✅ |
| Saldo 0 | Badge `saldo-zero`, progress 0%, SP nonaktif. ✅ |
| Saldo negatif besar (≥100) | Tahap VI "Dikembalikan pada Orang Tua". ✅ |
| Foto kosong | Fallback `../gambar/sistem/user.png`. ✅ |
| HP ortu format aneh | `preg_replace('/\D+/','')` membersihkan non-digit; validasi 10–15 digit. ✅ |
| `id` siswa tidak valid / tidak ada | Pesan error + `exit` rapi sebelum render. ✅ |

### F. Keamanan
- **XSS:** Output user-controlled di-escape via `e()`/`epoin_h()`. Pesan WA dirakit di server lalu `rawurlencode()` untuk URL & `json_encode(..., JSON_UNESCAPED_UNICODE)` untuk JS — aman. ✅
- **CSRF:** Endpoint `save_hp_ortu` & `issue_sp` memvalidasi `epoin_csrf_validate()`. ✅
- **Auth:** Lihat temuan **HIGH** di bawah.

---

## FASE 2 — BUG & STATUS FIX

### 🟢 Sudah di-fix (aman, kosmetik/konsistensi — tidak menyentuh logika)

| # | File | Temuan | Fix |
|---|------|--------|-----|
| F1 | `siswa_riwayat.php:362` | Breadcrumb aktif tertulis **"Dashboard"** (salah — ini halaman profil siswa) | Diubah jadi trail `Home › Siswa › Profil Disiplin`, Home→`index.php`, Siswa→`siswa.php` |
| F2 | `siswa_riwayat.php:470` | CSS typo `style="margin-top:14px%"` → nilai invalid, browser mengabaikan sehingga jarak antar-blok tidak sesuai desain | Diperbaiki jadi `margin-top:14px` |
| F3 | `siswa.php:432` | Dropdown filter **jurusan** berlabel **"Semua Kelas"** (menyesatkan; ada dropdown kelas terpisah di sebelahnya) | Diubah jadi **"Semua Tingkat/Jur"** sesuai header kolom "TINGKAT / JUR" |

Semua fix di atas: `php -l` 0 error, murni teks/atribut, **nol risiko** ke fungsi.

### 🔴 HIGH — Auth lemah: halaman & endpoint dapat diakses sesi SISWA (BUTUH KONFIRMASI BOS)

**Temuan:** `header.php` hanya menjamin **login** (`$_SESSION['id']>0`), tidak membedakan staf vs siswa. `periksa_login.php:65-68` men-set `$_SESSION['id']` + `level='siswa'` saat **siswa** login. Akibatnya:

- `siswa.php` & `siswa_riwayat.php` **tidak** memanggil `epoin_staff_guard(true)` → sesi siswa yang login bisa membuka daftar siswa & profil disiplin siapa pun.
- Endpoint AJAX `save_hp_ortu` dijaga **CSRF saja** (tanpa staff guard) → siswa login dapat mengubah `hp_ortu`.
- Endpoint `issue_sp` memakai `epoin_staff_guard_json()` yang **hanya cek login** (bukan admin) + CSRF → secara teori sesi siswa lolos.

**Severity:** HIGH (kerahasiaan data disiplin + integritas kontak ortu). Mitigasi yang sudah ada: CSRF menahan serangan lintas-situs anonim; eksploitasi butuh sesi siswa valid + tahu endpoint.

**Rekomendasi fix (1–3 baris):**
- Tambah `epoin_staff_guard(true)` di awal `siswa.php` & `siswa_riwayat.php` (setelah koneksi/security).
- Ganti `epoin_staff_guard_json()` pada alur SP/HP ortu menjadi varian yang memeriksa admin (mis. tambah cek `epoin_is_admin_session()`).

**⚠️ Ditangguhkan — sama dengan §3 HIGH di `AUDIT_MASTER_DATA.md`.** Perubahan ini menaikkan akses jadi admin-only. Bila ada Guru/Wali Kelas/BK non-administrator yang memang perlu membuka profil disiplin & menerbitkan SP, mereka akan ikut tertahan. **Mohon konfirmasi Bos: siapa saja role yang boleh mengakses modul siswa & menerbitkan SP** sebelum diterapkan.

### 🟡 MEDIUM / 🟢 LOW — Didokumentasikan (belum diubah)

| # | Sev | File | Temuan | Catatan |
|---|-----|------|--------|---------|
| P-OPT-1 | 🟡 Med | `siswa.php:59` | Saldo dihitung via 2 subquery korelasi per baris (≈ 2×401 eksekusi). Untuk 401 siswa masih wajar, tapi akan melambat seiring data tumbuh. | Opsi: precompute via 2 query agregat `GROUP BY siswa` lalu map di PHP, atau materialized/cron. Tidak mengubah hasil. |
| L-1 | 🟢 Low | `siswa.php:561`, `siswa_riwayat.php:892` | `dataTable.ext.errMode='none'` menyembunyikan error DataTables (by design agar tak ada popup). | Pertimbangkan `'console'` di dev agar mudah debug. |
| L-2 | 🟢 Low | `siswa.php:619`, `siswa_riwayat.php:902` | Paginate pakai panah `←/→`, sedangkan default global header pakai "Sebelumnya/Berikutnya". | Konsistensi minor; fungsi tidak terganggu. |
| L-3 | 🟢 Low | `siswa_riwayat.php:199,600,654` | Query riwayat pakai interpolasi `'$id_siswa'` (sudah `(int)`, aman). | Migrasi ke prepared statement untuk konsistensi gaya. |
| L-4 | 🟢 Low | `siswa_riwayat.php:131` | `CREATE TABLE IF NOT EXISTS sp_log` dieksekusi tiap load halaman (idempoten, murah). | Bisa dipindah ke migrasi/installer. |
| L-5 | 🟢 Low | `siswa_riwayat.php:368-369` | Komentar `<!-- Profil ringkas -->` duplikat. | Kosmetik. |

---

## FASE 3 — POLISH UI/UX

Kedua halaman **sudah** modern & konsisten dengan standar Master Data (kartu statistik, page-title ikon+badge, animasi count-up, DataTables bahasa Indonesia, badge berwarna, profile card dengan foto). Polish sesi ini sengaja **surgical** untuk mematuhi aturan "jangan rusak fitur":

**Dilakukan:**
- `siswa_riwayat.php`: breadcrumb benar (trail berjenjang, bukan "Dashboard") — konsisten dengan modul lain.
- `siswa_riwayat.php`: perbaikan spacing (typo `14px%`) → blok "Progres Poin / Tingkat Pembinaan" kini berjarak sesuai desain.
- `siswa.php`: label filter akurat → mengurangi kebingungan admin/guru.

**Sengaja TIDAK diubah (risiko vs manfaat):** struktur SP buttons, modal alasan SP, modal WA, modal jenjang, init DataTables — semua sudah berfungsi dan rapi; menyentuhnya berisiko tanpa manfaat tampilan signifikan.

---

## FASE 4 — REKOMENDASI FITUR / MENU BARU (saran, belum dibuat)

Diprioritaskan untuk **dampak presentasi ASN** (terlihat profesional, data-driven, mudah didemokan).

### Prioritas Tinggi (high impact, kompleksitas rendah–sedang)

| # | Fitur | Manfaat | Kompleksitas |
|---|-------|---------|--------------|
| R1 | **Export data siswa (Excel/CSV)** dari `siswa.php` (hormati filter aktif: jurusan/kelas/status) | Admin butuh rekap cepat untuk laporan dinas; sangat "demoable" | **Rendah** — sudah ada pola export CSV di `etugas` (lihat `docs/...phase-4a-rekap-export-csv`) |
| R2 | **Cetak Kartu Siswa (PDF)** — foto, NIS, nama, kelas, QR/barcode NIS | Output fisik berkesan untuk presentasi; reuse foto yang sudah ada | **Sedang** — butuh lib PDF (mPDF/Dompdf) + template |
| R3 | **Grafik tren saldo poin per siswa** di `siswa_riwayat.php` (Chart.js sudah dimuat di header) | Visual perkembangan disiplin antar waktu — kuat untuk demo BK | **Rendah–Sedang** — agregasi `input_*` per bulan + line chart |
| R4 | **Badge "Status SP" di tabel `siswa.php`** (chip SP1–SP4/Aman per baris) | Admin langsung lihat siswa berisiko tanpa membuka profil | **Rendah** — data tahap sudah dihitung; tinggal tampilkan |

### Prioritas Menengah

| # | Fitur | Manfaat | Kompleksitas |
|---|-------|---------|--------------|
| R5 | **Filter lanjutan di `siswa.php`**: rentang saldo (mis. "saldo < 0", "berisiko SP"), tahun ajaran | Temukan cepat siswa bermasalah | **Sedang** |
| R6 | **Riwayat poin per periode** (filter tanggal/semester) di `siswa_riwayat.php` | Laporan per semester untuk rapat | **Sedang** |
| R7 | **Bulk action** di `siswa.php` (pilih banyak → export/cetak kartu/kirim WA massal ke ortu) | Efisiensi tinggi untuk wali kelas | **Sedang–Tinggi** (perlu kehati-hatian WA massal) |
| R8 | **Ringkasan SP di profil** (kartu kecil: SP terakhir terbit, tanggal, nomor) | Lengkapi "kartu ringkasan status SP" yang diminta | **Rendah** — data ada di `sp_log` |

### Prioritas Rendah / Jangka Panjang

| # | Fitur | Manfaat | Kompleksitas |
|---|-------|---------|--------------|
| R9 | **Timeline gabungan** prestasi+pelanggaran (kronologis, ikon hijau/merah) menggantikan 2 tabel terpisah | Lebih mudah dibaca naratif | **Sedang** |
| R10 | **Dashboard analitik siswa** (top prestasi, top pelanggaran, distribusi saldo per kelas) | Insight tingkat sekolah | **Tinggi** |
| R11 | **Log audit akses profil disiplin** (siapa membuka/menerbitkan SP) | Akuntabilitas data sensitif | **Sedang** |

**Rekomendasi urutan eksekusi untuk presentasi ASN:** R4 → R1 → R3 → R8 → R2 (cepat terlihat, berdampak visual, risiko rendah).

---

## VERIFIKASI AKHIR

- `php -l admin/siswa.php` → **No syntax errors**
- `php -l admin/siswa_riwayat.php` → **No syntax errors**
- Tidak ada perubahan pada: perhitungan poin, ambang/penerbitan SP, penentuan tahap pembinaan, `$levelActive`, query saldo, endpoint AJAX, modal WA/jenjang/SP.
- Tidak ada `ALTER TABLE` / perubahan skema dari sesi ini.

---

## COMMIT (belum di-push — menunggu konfirmasi Bos)

1. `fix: breadcrumb & CSS spacing di siswa_riwayat.php` — F1, F2
2. `polish: label filter master data siswa lebih akurat` — F3
3. `docs: audit modul siswa + rekomendasi fitur`

**Menunggu keputusan Bos:** penerapan guard admin-only (temuan HIGH §Fase 2) — perlu konfirmasi role mana yang boleh mengakses modul siswa & menerbitkan SP.
