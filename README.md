# E-Poin Siswa

Sistem manajemen sekolah berbasis web untuk sekolah negeri di Indonesia.
Mengelola poin disiplin, absensi, e-rapor, dan ujian online (CBT) dalam satu platform.

Aplikasi ini saya bangun sendiri sebagai guru Informatika di SMPN 1 Gunungtanjung,
Tasikmalaya. Awalnya untuk kebutuhan sekolah sendiri, lalu berkembang dan kini
dipakai lebih dari 10 sekolah di Jawa Barat.

Live demo: https://demo.epoinsiswa.com
Website: https://epoinsiswa.com

## Latar Belakang

Sebagian besar sekolah negeri di daerah masih mencatat pelanggaran siswa di buku
saku manual, merekap absensi pakai kertas, dan menyusun rapor secara terpisah-pisah.
Solusi komersial yang ada biasanya mahal atau tidak sesuai dengan alur kerja
sekolah negeri Indonesia.

Karena saya mengalami sendiri masalah ini setiap hari sebagai guru, saya memutuskan
membangun sistemnya sendiri.

## Fitur

**Poin Disiplin & Prestasi**
Pencatatan pelanggaran dan prestasi siswa dengan bobot poin yang bisa diatur per
sekolah. Saat saldo poin mencapai ambang tertentu, sistem otomatis membuat Surat
Peringatan SP1 sampai SP4 dalam bentuk PDF.

**Absensi Digital**
Mendukung absensi harian maupun per jam pelajaran. Data absensi tersinkron otomatis
ke modul disiplin dan rapor, jadi tidak perlu input ganda.

**E-Rapor**
Guru bisa mencicil input nilai sepanjang semester. Hasil akhir bisa diekspor ke PDF
siap cetak, dengan opsi integrasi ke e-Rapor resmi Kemendikbud / Dapodik.

**CBT (Computer Based Test)**
Ujian online dengan pengaman anti-nyontek: fullscreen guard, deteksi pindah tab,
dan countdown timer.

**Manajemen Akses**
Role-based access control untuk Admin, Guru, Staf, Siswa, dan Orang Tua. Setiap
perubahan data tercatat di audit log.

## Teknologi

Dibangun dengan PHP native dan MySQL. Generate PDF menggunakan FPDF. Saat ini
berjalan di shared hosting maupun VPS.

## Instalasi

```bash
git clone https://github.com/isepn8-png/epoin.git
cd epoin
cp .env.example .env
```

Edit file `.env` sesuai konfigurasi database, lalu import skema database dari folder
`database/manual-migrations/`. Setelah itu aplikasi siap diakses lewat browser.

## Rencana Pengembangan

Versi berikutnya sedang saya kembangkan dengan nama SiTuntas, dibangun ulang
menggunakan Laravel. Modul yang sedang dirancang antara lain absensi berbasis
face recognition, generator jadwal pelajaran otomatis, dan jurnal mengajar digital.

Tujuan jangka panjangnya adalah membuat sistem manajemen sekolah yang lengkap,
terjangkau, dan bisa dipakai sekolah manapun di Indonesia — mulai dari sekolah di
pelosok sampai di kota besar.

## Lisensi

GNU General Public License v3.0. Lihat file LICENSE untuk detail.

## Kontak

Isep Nursami — Guru Informatika, SMPN 1 Gunungtanjung, Tasikmalaya
Founder INUSI.ID
Email lewat website: https://epoinsiswa.com
"# test auto deploy" 
