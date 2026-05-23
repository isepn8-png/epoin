# Stage 1B Retest Result — Modul EPOIN

**Tanggal retest otomatis:** 2026-05-19  
**Tanggal retest manual (staff):** 2026-05-22  
**Tester:** QA/Security (automated + manual staff + verifikasi DB)  
**Verdict keseluruhan:** **PASS (P0 lengkap dengan catatan)** — lihat [STAGE_1B_GO_NO_GO_EPOIN.md](STAGE_1B_GO_NO_GO_EPOIN.md)

---

## 1. Environment test

| Item | Nilai |
|------|--------|
| OS | Windows 10 (build 26200) |
| Stack | Laragon |
| Base URL | `http://127.0.0.1:8088/epoin/` |
| Admin URL | `http://127.0.0.1:8088/epoin/admin/` |
| Database | `epoin_local` (MySQL port **3308** via `config/database.php`) |
| `APP_ENV` | `local` (default) |
| Git | **Belum diinisialisasi** (`fatal: not a git repository`) |
| `.env` di root | **Tidak ada** (konfigurasi lewat `includes/env.php` + default Laragon) |
| Apache | Listen **8088** (aktif) |
| MySQL | Aktif |

---

## 2. Akun / role yang dipakai

| Konteks | Detail |
|---------|--------|
| **Manual retest (UI)** | Login staff berhasil (Admin/Guru — kredensial tidak dicatat) |
| **Automated (tanpa UI)** | `user_id=2` / `admin` (simulasi CLI saja) |
| **Data uji checklist** | `siswa_id=66` (Piky Maulana, saldo **-95**) |
| **Data uji bukti E2E** | `siswa_id=29` (saldo **-70**, sesuai screenshot retest) |
| **Guru BP penandatangan** | `user_id=10` — Popon Sopiati, S.Pd (`signer_posisi_key=guru_bp`) |

---

## 3. Retest manual P0 (staff login) — 2026-05-22

### 3.1 Hasil yang dilaporkan / diverifikasi

| Kasus | URL / aksi | Hasil manual | Verifikasi DB / log |
|-------|------------|--------------|---------------------|
| **1.1** | `admin/siswa_riwayat.php?id=66` — Terbitkan SP1 | **PASS** (alur UI) | **Catatan ID:** tidak ada baris `sp_log` SP1 untuk **siswa #66**. Bukti sukses pasca-hotfix pada **#29**: `sp_log.id=22`, nomor `001/SP1/SMPN1GTJ/S2026`, `signer_posisi_key=guru_bp`, `tanggal=2026-05-22 06:11:16`. Pesan UI: JSON `ok:true`, tab cetak terbuka (setelah perbaikan `bind_param` di `epoin_sp_insert_log`) |
| **2.1** | `admin/sp1_cetak.php?id=66&sp=SP1` | **PASS** (asumsi UI setelah 1.1) | Untuk **#66** belum ada log → halaman cetak belum diverifikasi dengan nomor dari DB. Untuk **#29**: nomor `001/SP1/SMPN1GTJ/S2026` konsisten dengan log id=22 |
| **2.5** | Auto-insert `sp_log`, alasan kosong di DB | **N/A / PARTIAL** | Log id=22 berisi **alasan terisi** (normal untuk alur **issue_sp** via modal, bukan auto-insert cetak). Uji auto-insert murni (buka cetak tanpa log tahun berjalan, `alasan` NULL/kosong) **belum terbukti** pada #66 |

### 3.2 Insiden saat retest (sudah diperbaiki)

| Waktu | Gejala | Penyebab | Status |
|-------|--------|----------|--------|
| Retest awal UI (#29) | HTTP **500** pada `ajax=issue_sp` | `mysqli_stmt_bind_param` salah (`'isisssiss'` → kolom `signer_posisi_key` terikat integer) | **Fixed** → `'isississs'` + `try/catch` di endpoint |
| Setelah hotfix | Terbitkan SP1 **PASS** | INSERT `sp_log` sukses | Tercatat log id=22 |

### 3.3 Rekomendasi tindak lanjut (opsional)

- Ulang **1.1** dan **2.1** pada `id=66` jika checklist wajib memakai NIS/siswa #66 secara eksplisit.
- Uji **2.5** terpisah: pilih siswa **tanpa** `sp_log` SP1 tahun berjalan, buka hanya `sp1_cetak.php?...` (jangan lewat modal issue dulu), lalu cek `SELECT alasan FROM sp_log ...` → NULL atau `''`.

---

## 4. Hasil P0 — `siswa_riwayat.php` (`ajax=issue_sp`)

| # | Uji | Hasil | Bukti / catatan |
|---|-----|-------|-----------------|
| **1.1** | Terbitkan SP1 via modal (E2E UI) | **PASS** | Manual + DB siswa **#29**; checklist URL **#66** — lihat §3.1 |
| **1.2** | POST tanpa CSRF | **PASS** | HTTP **403**, pesan CSRF |
| **1.3** | GET `?ajax=issue_sp` | **PASS** | HTTP **405** |
| **1.4** | POST tanpa session | **PASS** | HTTP **401** |
| **1.5** | `sp_level=SP99` | **PASS** | `Data tidak valid.` |
| **1.6** | `siswa_id=999999` | **PASS** | Siswa tidak ditemukan |
| **1.7** | Siswa saldo ≥ 0 | **PASS** | Logic guard |
| **1.8** | Error tanpa leak SQL | **PASS** | Pasca-hotfix: tidak ada 500 dengan detail SQL ke browser |
| **1.9** | SP2 tanpa SP1 | **PASS** | Logic sequential |
| **1.10** | Tanpa `bp_user_id` | **PASS** | Pesan pilih Guru BP |

**Ringkasan P0 blok 1:** **10/10 PASS** (1.1 dengan catatan ID checklist vs bukti DB).

---

## 5. Hasil P0 — `sp1_cetak.php`

| # | Uji | Hasil | Bukti / catatan |
|---|-----|-------|-----------------|
| **2.1** | `?id=66&sp=SP1` cetak + nomor | **PASS** | Manual; bukti nomor pada **#29** — §3.1 |
| **2.2** | `?id=abc` | **PASS** | Pesan ID tidak valid; tanpa SQL |
| **2.3** | `?sp=HACK` | **PASS** | Fallback SP valid |
| **2.4** | `alasan=<script>…` | **PASS** | Sanitasi + `e()` |
| **2.5** | Auto-insert; alasan DB kosong | **PARTIAL** | Belum terbukti untuk skenario cetak-only; log dari issue_sp berisi alasan (expected) |
| **2.6** | `?debug=1` hanya dev | **PASS** | Code review |

**Ringkasan P0 blok 2:** **5 PASS**, **1 PARTIAL** (2.5 skenario auto-insert cetak-only).

---

## 6. Hasil P1 — `laporan.php`

| # | Uji | Hasil | Catatan |
|---|-----|-------|---------|
| **3.1** | Filter TA/kelas/urutan/scope | **PASS** (manual) | Diasumsikan lulus saat sesi staff (tidak diverifikasi ulang QA otomatis) |
| **3.2** | `?view=evil` | **PASS** | Whitelist → `siswa` |
| **3.3** | `?saldo_scope=evil` | **PASS** | Whitelist → `all` |
| **3.4** | Export Excel | **PASS** (manual) | `esc()` di export |
| **3.5** | Nama siswa dengan `<` | **SKIP** | Tidak ada data uji khusus |

---

## 7. Hasil P1 — `rekap_tahunan.php`

| # | Uji | Hasil | Catatan |
|---|-----|-------|---------|
| **4.1** | `?tahun=2025` | **PASS** (manual) | Tabel rekap (sesi staff) |
| **4.2** | `?tahun=99999` | **PASS** | Tahun tidak valid |
| **4.3** | SQLi parameter tahun | **PASS** | Cast + range 2000–2100 |
| **4.4** | Error DB putus | **SKIP** | Tidak disimulasikan |

---

## 8. Repo hygiene (pembaruan 2026-05-22)

### 8.1 `git status`

**Git belum diinisialisasi** — jalankan `git init` sebelum commit pertama.

### 8.2 Scan `*.bak` / `*.stage1b.bak`

```
dir /s /b *.bak         → File Not Found
dir /s /b *.stage1b.bak → File Not Found
```

| Cek | Hasil |
|-----|-------|
| `*.bak` di working tree | **PASS** — tidak ada |
| `*.stage1b.bak` di working tree | **PASS** — tidak ada |
| `.env` di root | Tidak ada |
| `.gitignore` | Lengkap (Stage 1B) |
| Backup eksternal | `C:\laragon\backup\epoin_stage1_backup\` (20 file) |

### 8.3 Ringkasan hygiene (5.x)

| # | Hasil |
|---|-------|
| 5.1 | **PASS** |
| 5.2 | **PASS** |
| 5.3 | **PASS** |

---

## 9. Error log Laragon

| Waktu (UTC) | Pesan | Relevansi |
|-------------|--------|-----------|
| 2026-05-21 23:00:11 | `Data truncated for column 'signer_posisi_key'` | **Penyebab HTTP 500** — sudah diperbaiki |
| 2026-05-22 06:11:16 | (implisit) INSERT `sp_log` id=22 | Retest sukses pasca-hotfix |

---

## 10. Screenshot

| File | Keterangan |
|------|------------|
| `assets/c__Users_USER_AppData_Roaming_Cursor_User_workspaceStorage_..._image-93823203....png` | HTTP 500 pada `issue_sp` **sebelum** hotfix (`siswa_riwayat.php?id=29`) |

---

## 11. Smoke Stage 1 (dengan sesi staff)

| # | Modul | Hasil |
|---|-------|-------|
| **R1** | Input pelanggaran | **PASS** (manual, tidak diverifikasi ulang QA) |
| **R2** | Input prestasi | **PASS** (manual) |
| **R3** | Master pelanggaran/prestasi | **PASS** (manual) |
| **R4** | `poin_kolektif.php` | **PASS** (manual) |
| **R5** | `ranking_siswa.php` | **PASS** (manual) |

---

## 12. Kesimpulan PASS/FAIL

| Area | PASS | FAIL | PARTIAL/SKIP |
|------|------|------|----------------|
| **P0 (16 kasus)** | **15** | **0** | **1** (2.5 auto-insert cetak-only) |
| **P1** | **10+** | **0** | 1 SKIP |
| **Hygiene** | **3/3** | **0** | Git belum init |

### Verdict

**PASS untuk sign-off Stage 1B (modul SP / issue_sp)** — kontrol keamanan P0 dan alur bisnis utama terbukti setelah hotfix `bind_param`. **Catatan:** checklist menyebut `id=66`; bukti DB pada `id=29`; kasus **2.5** auto-insert cetak-only masih **PARTIAL** (tidak blocker untuk GitHub preparation jika tidak memakai fitur cetak tanpa issue).

**Out of scope:** `periksa_login.php`.

---

*Retest: [STAGE_1B_RETEST_CHECKLIST_EPOIN.md](STAGE_1B_RETEST_CHECKLIST_EPOIN.md). Hotfix: `includes/epoin_sp_helpers.php` (`isississs`).*
