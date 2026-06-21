-- Migration: Tambah kolom hp_ortu ke tabel siswa
-- Tanggal  : 2026-06-21
-- Deskripsi: Menyimpan nomor HP orang tua/wali untuk fitur notifikasi WhatsApp

ALTER TABLE siswa
  ADD COLUMN hp_ortu VARCHAR(20) NULL DEFAULT NULL AFTER siswa_status;
