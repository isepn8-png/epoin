# AUDIT RBAC — E-POIN (Tahap A / Sub-fase 1–3)

> **Tanggal:** 2026-06-27 · **Auditor:** security review (Opus) · **Lokasi:** `C:\laragon\www\epoin`
> **Cakupan:** `admin/role_permission.php` (Matrix + handler AJAX), `includes/auth.php` (epoin_can dkk),
> `admin/header.php` (load perms + menu), plus surface terkait yang ditemukan saat uji bypass
> (`admin/manajemen_pengguna.php`, `admin/users/edit_roles.php`).
> **Status enforcement:** belum aktif (Sub-fase 4–5 belum dikerjakan) — temuan dinilai untuk dampak **sekarang** dan **pasca-enforcement**.

---

## 1. RINGKASAN TEMUAN

| Severity | Jumlah | Inti |
|---|--:|---|
| 🔴 Critical | 1 | Eskalasi privilege: aktor non-superadmin (`administrator`/`tas`) bisa meng-assign role **superadmin** ke dirinya sendiri |
| 🟠 High | 1 | Tools kelola role/akses ber-guard **admin-only**, padahal desain (`master.user.role_manage`) = **superadmin-only** |
| 🟡 Medium | 2 | (a) Stale permission cache tanpa invalidasi versi; (b) cakupan `tas` pada UI kelola role lebih luas dari semestinya |
| ⚪ Low | 4 | Semantik 403 vs redirect, stmt tak ditutup saat exception, reload perms tiap request utk role 0-perm, fallback `$SUPER_ID=0` |

**Handler Matrix (`save_matrix`) sendiri: AMAN** terhadap 3 skenario yang Bos tanyakan (non-admin, kunci superadmin, tanpa CSRF). Temuan Critical/High ada di **surface assignment role di sekitarnya**, bukan di handler matrix.

---

## 2. DETAIL TEMUAN

### 🔴 C-1 — Eskalasi ke Superadmin via assign role (Critical)
**Lokasi:** `admin/manajemen_pengguna.php` aksi `save_user_roles` (baris 92–128); pola sama di `admin/users/edit_roles.php` (baris 4–51).
**Dampak:** Guard halaman `manajemen_pengguna.php` mengizinkan `administrator`, `superadmin`, **dan `tas`**. Aksi `save_user_roles` hanya punya satu proteksi: *"jangan cabut superadmin terakhir"*. **Tidak ada** cek bahwa hanya superadmin yang boleh **memberikan** role superadmin. Akibatnya seorang `tas` (staf TU, privilege rendah) atau `administrator` bisa POST `save_user_roles` dengan `uid` dirinya sendiri + menyertakan `role_id` superadmin → otomatis menjadi **superadmin (wildcard, akses penuh)**. CSRF tervalidasi, tapi aktor punya token sah dari sesinya sendiri → CSRF tidak menahan ini. `edit_roles.php` (guard `administrator|superadmin`) punya celah identik.
**Bukti kode:** satu-satunya gate adalah blok `if ($isSuperadmin($uid) && $countSuperadmin() === 1)` (mencegah penghapusan super terakhir) — tidak ada gate untuk **penambahan** super oleh non-super.
**Rekomendasi (patch tertarget, aman, tanpa mengganggu kelola role normal):**
> Jika aktor **bukan** superadmin (`epoin_is_superadmin_session()` == false), TOLAK request bila: (a) selection menyertakan role `superadmin`, ATAU (b) target user saat ini punya role superadmin. Hanya superadmin yang boleh mencetak/mengubah keanggotaan superadmin. Terapkan di `save_user_roles` DAN `edit_roles.php`.
**Status:** ✅ **DI-FIX & VERIFIED RUNTIME** (2026-06-27). `manajemen_pengguna.php` `save_user_roles`: blok bila menyentuh role superadmin (assign/target) & aktor bukan superadmin. `edit_roles.php`: guard dijadikan superadmin-only (lihat H-1). 5/5 test C-1 PASS (tas/admin ditolak; superadmin asli tetap bisa assign).

### 🟠 H-1 — Tools kelola role/akses tidak superadmin-only (High)
**Lokasi:** `admin/role_permission.php:18` (`epoin_staff_guard(true)` = admin-only), `admin/users/edit_roles.php:5`, `admin/manajemen_pengguna.php:19`.
**Dampak:** BLUEPRINT §4.1 menetapkan `master.user.role_manage` (kelola role & akses) **hanya superadmin** (kolom admin = tidak dicentang). Faktanya Matrix UI + assignment role bisa diakses **administrator** (bahkan `tas` utk manajemen_pengguna). Seorang administrator non-super bisa menulis ulang matrix permission seluruh role. **Sekarang** (enforcement off) dampak = perubahan DATA saja (bisa diperbaiki super). **Pasca-enforcement** = bypass kebijakan akses (admin mengatur izin yang seharusnya domain superadmin).
**Rekomendasi:** Sebelum Sub-fase 4–5, ganti guard tools kelola role menjadi superadmin-only (`epoin_is_superadmin_session()`), atau gate `epoin_can('master.user.role_manage')` (yang by design hanya super). Selaras dengan blueprint.
**Status:** ✅ **DI-FIX & VERIFIED RUNTIME** (2026-06-27). `role_permission.php` & `edit_roles.php` kini **superadmin-only** (`epoin_is_superadmin_session()`); AJAX matrix utk non-super → JSON 403 (sekaligus menutup L-1). `manajemen_pengguna.php` tetap admin/`tas` untuk kelola user umum, namun boundary superadmin dilindungi C-1. 8/8 test H-1 PASS (admin/tas ditolak; superadmin diizinkan, termasuk POST AJAX).

### 🟡 M-1 — Stale permission cache tanpa invalidasi versi (Medium)
**Lokasi:** `admin/header.php:154` — `if (empty($_SESSION['perms'])) { $_SESSION['perms'] = load_user_permissions($user_id); }`.
**Dampak:** Perms hanya dimuat ulang saat **kosong**. Setelah admin mengubah matrix, user lain yang sedang login tetap memakai perms lama sampai logout/login. `epoin_refresh_session_perms()` (dipanggil di `save_matrix`) hanya menyegarkan sesi admin yang sedang mengedit, bukan user lain. BLUEPRINT §5.2 mengusulkan `rbac_version` stamp untuk invalidasi — **belum diimplementasi**. Tidak ada dampak keamanan sekarang (enforcement off), tapi **wajib** diberesi sebelum enforcement agar pencabutan izin langsung berlaku.
**Rekomendasi:** Implementasi `rbac_version` (tabel `app_meta`/file) + bandingkan `$_SESSION['perms_ver']` di header → reload bila beda. Bump versi di `save_matrix`.

### 🟡 M-2 — Cakupan `tas` pada UI kelola role (Medium)
**Lokasi:** `admin/manajemen_pengguna.php:19` (`tas` diizinkan ke seluruh halaman, termasuk AJAX assign role).
**Dampak:** `tas` bisa mengelola assignment role user lain (selain celah C-1). Mungkin lebih luas dari maksud desain. Setelah C-1 diperbaiki (tas tak bisa cetak super), `tas` tetap bisa mengubah role non-super — perlu konfirmasi apakah ini memang dikehendaki.
**Rekomendasi:** Tinjau apakah Tab "Akun & Role" semestinya hanya admin/super; pertimbangkan memisahkan aksi assignment role dari akses `tas`.

### ⚪ Low
- **L-1** `role_permission.php` AJAX untuk non-admin → `epoin_staff_guard(true)` melakukan `header('Location: index.php')` (HTML 302), bukan JSON 403. **Aman** (tidak ada mutasi), tapi semantik endpoint JSON kurang konsisten.
- **L-2** `role_permission.php:72–75` saat exception transaksi, `mysqli_stmt_close($ins/$del)` dilewati sebelum `exit` — kebocoran resource diabaikan (request berakhir).
- **L-3** Untuk role dengan union perm kosong (mis. superadmin), `empty($_SESSION['perms'])` selalu true → query reload tiap request. Perf minor.
- **L-4** `role_permission.php:35–36` `$SUPER_ID` fallback 0 bila role superadmin tak ada; perbandingan `rid===0` tak berbahaya (role 0 invalid) dan wildcard `epoin_can` tetap melindungi via cek role.

---

## 3. HASIL UJI SKENARIO BYPASS (handler `save_matrix`)

| Skenario | Hasil | Bukti |
|---|---|---|
| Non-admin (guru/siswa) hit AJAX langsung untuk ubah permission | **DITOLAK** | `epoin_staff_guard(true)` dipanggil di baris 18 **sebelum** blok AJAX (baris 39). Non-admin → redirect, tidak ada mutasi. |
| Request tanpa/із token CSRF salah | **DITOLAK (419)** | `epoin_csrf_validate()` (tanpa argumen) di baris 40, pakai `hash_equals`. |
| Revoke / kosongkan permission superadmin via AJAX | **DITOLAK server-side** | baris 66: `if ($rid === $SUPER_ID) { $skipped++; continue; }` — perubahan ke superadmin diabaikan, bukan sekadar UI lock. |
| Kunci sistem dgn hapus semua perm superadmin | **TIDAK MUNGKIN** | Selain proteksi di atas, `epoin_can()` memperlakukan superadmin sebagai **wildcard** via cek role sesi (`epoin_is_superadmin_session`), independen dari baris `role_permissions`. |
| Inject `role_id`/`perm_id` sembarang / SQLi | **AMAN** | Semua di-cast `(int)`, divalidasi `isset($ROLES[$rid])` & `isset($PERMS[$pid])`, query INSERT/DELETE **prepared**. |
| Payload rusak/kosong | **Aman** | `json_decode` → cek `is_array`, sel invalid di-`continue` + `$skipped++`. |

> ⚠️ **Catatan penting:** uji bypass di atas khusus *handler matrix*. Untuk **assignment role** (siapa punya role apa), lihat **C-1** — di sana eskalasi ke superadmin **MUNGKIN** terjadi.

## 3b. KONFIRMASI POSITIF (sudah benar)
- `epoin_can()` superadmin short-circuit pakai `$_SESSION['roles']` yang di-set dari DB saat login (`bootstrap_rbac_for_user_id`) — tidak bisa dipalsukan dari input.
- Multi-role = UNION: `load_user_permissions()` `SELECT DISTINCT ... JOIN` semua role user (prepared). Benar.
- `load_user_permissions()` di `header.php` **sudah diperbaiki** (tidak lagi `return []`) — query DB asli; kedua definisi (auth.php & header.php) di-guard `function_exists`.
- `epoin_can()/_any/_all` **belum dipakai untuk gating di mana pun** (grep seluruh repo) → enforcement benar-benar OFF, tidak ada gating prematur.
- Tidak ada SQL injection pada jalur assign role (`array_map('intval')`, cast `(int)`).

---

## 4. INTEGRITAS DATA (BAGIAN 3) — ✅ CLEAN (2026-06-27)

MySQL dinyalakan & skrip `scratchpad/rbac_audit.php` dieksekusi. Semua check **bersih**:

| Check | Hasil |
|---|---|
| Jumlah roles | **13** ✅ |
| Jumlah permissions | **67** ✅ |
| Jumlah mappings (role_permissions) | **275** ✅ |
| Orphan role_permissions → roles | **0** ✅ |
| Orphan role_permissions → permissions | **0** ✅ |
| Orphan user_roles → roles | **0** ✅ |
| Permission tanpa role | **0** ✅ |
| FK role_permissions.role_id | `CASCADE` ✅ |
| FK role_permissions.perm_id | `CASCADE` ✅ |
| Duplikat role_key | **0** ✅ |
| Duplikat perm_key | **0** ✅ |
| Role siswa: perm | **0** ✅ (by design) |
| Role siswa: user | **0** ✅ (tidak ada staf salah-role) |
| Staf level='siswa' di user_roles | **0** ✅ |

**Catatan data:** Superadmin mempunyai **67 baris** di `role_permissions` (bukan 0 seperti asumsi awal audit). Ini tidak mengubah keamanan — `epoin_can()` short-circuit via `epoin_is_superadmin_session()` independen dari `role_permissions`. Baris tersebut kemungkinan hasil seed `INSERT ALL perms` ke superadmin; tidak berbahaya.

---

## 5. SUDAH DI-FIX vs MENUNGGU KONFIRMASI
- **Sudah benar (tidak perlu fix):** handler `save_matrix` (auth/CSRF/SQL/superadmin-lock), `epoin_can` wildcard, `load_user_permissions` (header sudah diperbaiki), enforcement OFF.
- **Sudah di-fix & diverifikasi runtime (2026-06-27):** C-1 (Critical) & H-1 (High) — patch tertarget, php -l 0 error, **13/13 runtime tests PASS** (DB hidup; semua skenario bypass ditolak; superadmin tetap bisa akses normal).
- **Untuk Sub-fase berikut:** M-1 (rbac_version), M-2 (cakupan tas), Low items (kecuali L-1 yang ikut tertutup oleh H-1).

---

## 6. REKOMENDASI SEBELUM ENFORCEMENT (Sub-fase 4–5)
1. **[Critical] Tutup C-1** — blok non-super meng-assign/mengubah keanggotaan role superadmin (manajemen_pengguna + edit_roles).
2. **[High] Tutup H-1** — guard tools kelola role = superadmin-only, selaras `master.user.role_manage`.
3. **[Medium] M-1** — implementasi invalidasi cache `rbac_version` agar pencabutan izin langsung berlaku (kritis begitu enforcement nyala).
4. **[Medium] M-2** — tinjau cakupan `tas` pada Tab Akun & Role.
5. Jalankan `rbac_audit.php` saat DB hidup; perbaiki orphan/FK bila ada sebelum mengaktifkan gating.
6. Terapkan fallback `epoin_can('x') || _is_admin()` saat transisi menu (blueprint §5.3) agar admin tak kehilangan akses saat matrix belum lengkap.
```
```
