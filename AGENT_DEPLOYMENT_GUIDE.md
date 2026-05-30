# 🚀 SISTEM DEPLOYMENT OTOMATIS: GitHub → aaPanel (Metode Cron)
**Tipe Dokumen:** Standard Operating Procedure (SOP) & Deployment Architecture
**Proyek Terkait:** Garuda CBT & EPOIN

Dokumen ini menggunakan **Metode Cron Job (Rekomendasi)** yang lebih tangguh dan aman untuk lingkungan sekolah dibandingkan metode Webhook.

---

## 🏗️ 1. Kenapa Pakai Cron dan Bukan Webhook?

Berdasarkan analisis keamanan dan kepraktisan untuk VPS aaPanel:
- **Lebih Aman:** Tidak perlu membuka fungsi `exec()` atau `shell_exec()` di PHP yang rentan diretas.
- **Lebih Tangguh (Self-Healing):** Jika internet server sempat putus saat deploy, sistem akan otomatis mencoba lagi di menit berikutnya.
- **Sangat Ringan:** Skrip di bawah ini sudah dioptimasi. Dia akan mengecek GitHub setiap menit, tapi **hanya akan mengeksekusi instalasi jika ada kode baru**. Jika tidak ada kode baru, skrip langsung berhenti dalam hitungan milidetik.

---

## 📜 2. Aturan Deployment (SOP)

1. **JANGAN PERNAH COMMIT FILE SENSITIF:**
   - Kredensial database (`application/config/database.php` versi VPS atau `.env`).
2. **JANGAN COMMIT FILE UPLOAD & BACKUP:**
   - Folder `/uploads/` dan `/backups/` tidak boleh masuk Git.
3. **JANGAN EDIT KODE LANGSUNG DI VPS:**
   - Segala perubahan kode WAJIB dilakukan di Localhost, lalu di-push ke GitHub.

---

## 💻 3. Skrip Deploy (`deploy.sh`)

Buat file `deploy.sh` di root direktori website (contoh: `/www/wwwroot/domain.sch.id/deploy.sh`). 

```bash
#!/bin/bash
set -euo pipefail

# SESUAIKAN DENGAN FOLDER DI AAPANEL
APP_DIR="/www/wwwroot/domain.sch.id"
LOG_FILE="$APP_DIR/deploy.log"

cd "$APP_DIR"

# 1. Ambil info terbaru dari GitHub (sangat ringan, < 1 detik)
git fetch origin main

# 2. Bandingkan versi lokal VPS dengan versi di GitHub
LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse origin/main)

# 3. Jika ada perbedaan, eksekusi pembaruan!
if [ "$LOCAL" != "$REMOTE" ]; then
    echo "[$(date -Iseconds)] 🚀 Update baru terdeteksi! Memulai sinkronisasi..." >> "$LOG_FILE"
    
    # Tarik kode baru paksa
    git reset --hard origin/main
    
    # Atur Ownership & Permission standar keamanan
    chown -R www:www "$APP_DIR"
    find "$APP_DIR" -type d -exec chmod 755 {} \;
    find "$APP_DIR" -type f -exec chmod 644 {} \;
    
    # Khusus folder Upload & Cache CI3 (harus writable)
    if [ -d "uploads" ]; then chmod -R 775 uploads; fi
    if [ -d "application/cache" ]; then chmod -R 775 application/cache; fi
    
    # Pastikan skrip ini sendiri tetap bisa dieksekusi
    chmod +x deploy.sh
    
    echo "[$(date -Iseconds)] ✅ Deploy sukses." >> "$LOG_FILE"
fi
```

---

## ⚙️ 4. Cara Pasang di aaPanel (Cron Menu)

Karena kita menggunakan Cron, bos **tidak perlu buat file webhook.php** dan **tidak perlu setting webhook di GitHub**. Cukup lakukan ini di aaPanel:

1. Buka menu **Cron** di aaPanel.
2. Tambahkan tugas baru (Add Cron Task):
   - **Type of Task:** `Shell Script`
   - **Name:** `Auto Deploy EPOIN`
   - **Execution cycle:** `N Minutes` ➔ isi `1` Minute.
   - **Script content:** 
     ```bash
     bash /www/wwwroot/domain.sch.id/deploy.sh
     ```
3. Klik **Add Task**. Selesai!

*(Opsional tapi penting):* Pastikan VPS bos sudah dipasangkan **Deploy Key (SSH)** ke GitHub agar saat Cron berjalan, VPS tidak ditanya password/username GitHub lagi.
