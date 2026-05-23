# Deployment Manifest — E-Tugas Safe Delete

| Field | Value |
|-------|--------|
| **Manifest ID** | `2026-05-17-etugas-safe-delete` |
| **Feature** | Hapus tugas kosong (tanpa pengumpulan siswa) |
| **SQL import required** | **No** |
| **Date** | 2026-05-17 |

---

## 1. Purpose

Menambahkan aksi **Hapus** permanen pada daftar tugas (`admin/etugas.php`) hanya untuk tugas yang **belum memiliki baris** di `etugas_pengumpulan`. Tugas yang sudah ada pengumpulan siswa harus menggunakan **Arsipkan**, bukan hapus.

---

## 2. New files to upload

| Path |
|------|
| `admin/etugas_hapus.php` |

---

## 3. Existing files to replace

| Path |
|------|
| `includes/etugas_helpers.php` |
| `admin/etugas.php` |

---

## 4. SQL files to import

**No SQL import required.**

---

## 5. Database changes

None. Uses `DELETE FROM etugas WHERE etugas_id = ?` only when submission count is zero. Does **not** delete or cascade `etugas_pengumpulan`.

---

## 6. Files NOT to upload

- `.env`, credentials, full DB dumps
- `tests/*`, `docs/*` (optional)
- `*.zip`, `*.rar`, `*.bak`, `*.log`

---

## 7. Manual hosting deployment steps

1. Backup `includes/etugas_helpers.php` and `admin/etugas.php`.
2. Upload `admin/etugas_hapus.php`.
3. Replace `includes/etugas_helpers.php` and `admin/etugas.php`.
4. Clear OPcache if enabled.
5. Login admin → buat tugas uji tanpa pengumpulan → verifikasi tombol **Hapus**.
6. Konfirmasi hapus → baris hilang dari `etugas`.
7. Buat tugas + kumpulkan sebagai siswa → verifikasi **Hapus** tidak tampil / POST ditolak.

---

## 8. Rollback file steps

1. Restore backed-up `etugas_helpers.php` and `etugas.php`.
2. Delete `admin/etugas_hapus.php` from server.

No database rollback unless restoring from backup.

---

## 9. Validation results

| File | `php -l` |
|------|----------|
| `admin/etugas_hapus.php` | PASS |
| `admin/etugas.php` | PASS |
| `includes/etugas_helpers.php` | PASS |

---

## 10. Browser test results

| # | Test | Expected | Status |
|---|------|----------|--------|
| 1 | Admin, tugas kosong | Tombol Hapus tampil | Manual |
| 2 | Klik Hapus + konfirmasi | Row terhapus | Manual |
| 3 | Tugas ada pengumpulan | Hapus tidak tampil; teks Arsipkan | Manual |
| 4 | POST hapus tugas ber-pengumpulan | Ditolak + pesan | Manual |
| 5 | POST tanpa CSRF | Ditolak | Manual |
| 6 | Guru, tugas dapat dikelola, kosong | Hapus OK | Manual |
| 7 | Guru, tugas luar scope | Ditolak | Manual |
| 8 | Siswa akses `etugas_hapus.php` | 403 | Manual |
| 9 | Arsipkan masih berfungsi | OK | Manual |

---

## Related

- `docs/ai-agent-reports/2026-05-17-etugas-safe-delete.md`
