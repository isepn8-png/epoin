# Panduan Pasang (Drop-in, sesuai skema saat ini)

1) **Timpa/Copy** file di folder ini ke root project Anda (struktur sama: `epoin/siswa/...`).  
2) **Tambahkan menu** di `epoin/siswa/header.php` bila perlu:
   - `absensi.php`, `jadwal.php`, `poin.php`, `profil.php`, `pengumuman.php`, `izin_absensi.php`
3) Opsional (fitur baru): jalankan migrasi untuk tabel tambahan:
   - `epoin/migrations/001_pengumuman.sql`
   - `epoin/migrations/002_permohonan_absensi.sql`
4) Pastikan hak akses tulis untuk `epoin/siswa/uploads/izin` (755/775).
5) Selesai. Masuk sebagai siswa dan uji setiap halaman.

> Catatan: **Ganti Password** di sini tetap memakai MD5 agar kompatibel dengan `periksa_login.php` Anda saat ini. Jika ingin migrasi ke `password_hash()`, beri tahu — kami sertakan panduan terpisah.
