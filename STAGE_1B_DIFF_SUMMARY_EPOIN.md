# Stage 1B Diff Summary — Modul EPOIN

Ringkasan perubahan kode untuk review cepat (tanpa secret).

---

## Statistik

| Metrik | Nilai |
|--------|-------|
| File baru | 1 (`includes/epoin_sp_helpers.php`) |
| File diubah | 8 |
| File dipindah (`.bak`) | 20 → `C:\laragon\backup\epoin_stage1_backup\` |
| Snapshot `.stage1b.bak` | 6 |
| Perkiraan baris dihapus (siswa_riwayat AJAX saja) | ~195 |
| Perkiraan baris helper SP baru | ~380 |

---

## Per file

### `includes/epoin_sp_helpers.php` (BARU)

- `epoin_sp_validate_level`, `epoin_sp_sanitize_alasan`
- `epoin_sp_ensure_schema`, `epoin_sp_saldo_for_siswa`
- `epoin_sp_issued_levels_year`, `epoin_sp_can_issue_level`
- `epoin_sp_next_numbers`, `epoin_sp_insert_log`, `epoin_sp_fetch_latest_log`
- `epoin_sp_fetch_bp_signer`, `epoin_sp_auto_create_for_print`
- `epoin_sp_ajax_issue_endpoint()` — menggantikan seluruh blok AJAX lama

### `admin/siswa_riwayat.php`

```diff
- ~195 baris AJAX raw SQL + SQL error di JSON
+ 4 baris: require epoin_sp_helpers + epoin_sp_ajax_issue_endpoint()
+ epoin_security + window.EPOIN_CSRF
+ AJAX data: _csrf
```

### `admin/sp1_cetak.php`

```diff
- CREATE/SELECT/INSERT sp_log raw + alasan GET → SQL
+ epoin_sp_validate_level, epoin_sp_fetch_latest_log
+ epoin_sp_auto_create_for_print (alasan kosong di INSERT)
+ alasan GET → sanitize → e() untuk HTML saja
```

### `admin/laporan.php`

```diff
+ whitelist view, saldo_scope, export
+ guard ta_id/kelas_id >= 0
+ prepared SELECT ta_nama, kelas_nama
```

### `admin/rekap_tahunan.php`

```diff
- YEAR(...) = '$tahun' string concat
+ tahun int 2000-2100, prepared query, epoin_h output
```

### `includes/epoin_security.php`

```diff
+ epoin_flash_render()
```

### `admin/pelanggaran.php`, `admin/prestasi.php`

```diff
+ epoin_flash_render() di section content
```

### `.gitignore`

```diff
+ .env.*, !.env.example, *.stage1b.bak, uploads/*, vendor/, tests/, node_modules/, backup/, backups/, *.7z
```

---

## Endpoint CSRF (kumulatif modul EPOIN)

| File | Aksi |
|------|------|
| `siswa_riwayat.php` | `ajax=issue_sp` POST |
| `pelanggaran_act/update/hapus` | POST |
| `prestasi_act/update/hapus` | POST |
| `input_pelanggaran_act/update/hapus` | POST |
| `input_prestasi_act/update/hapus` | POST |
| `poin_kolektif.php` | API POST + header/body CSRF |

---

## Raw SQL yang diganti (Stage 1B saja)

1. `issue_sp`: 6+ query → prepared di helper  
2. `sp1_cetak` sp_log: 4 query → helper  
3. `laporan.php`: 2 query label → prepared  
4. `rekap_tahunan.php`: 1 query agregasi → prepared  

---

## Rollback

1. Restore dari `C:\laragon\backup\epoin_stage1_backup\*.bak` (pre–Stage 1) atau `*.stage1b.bak` (post-patch).  
2. Hapus `includes/epoin_sp_helpers.php` jika rollback penuh AJAX lama.  
3. Tidak perlu rollback database (tanpa migrasi schema).

---

## Verifikasi cepat

```bash
php -l includes/epoin_sp_helpers.php
php -l admin/siswa_riwayat.php
php -l admin/sp1_cetak.php
php -l admin/laporan.php
php -l admin/rekap_tahunan.php
```

Manual: ikuti `STAGE_1B_RETEST_CHECKLIST_EPOIN.md`.
