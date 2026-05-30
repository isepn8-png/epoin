#!/bin/bash
echo "SET FOREIGN_KEY_CHECKS=0;" > migrasi_data_terbaru.sql

# Ekstrak tabel input_pelanggaran
grep -i "^INSERT INTO \`input_pelanggaran\`" db-epoin-lama.sql >> migrasi_data_terbaru.sql

# Ekstrak tabel input_prestasi
grep -i "^INSERT INTO \`input_prestasi\`" db-epoin-lama.sql >> migrasi_data_terbaru.sql

# Ekstrak master pelanggaran dan prestasi
grep -i "^INSERT INTO \`pelanggaran\`" db-epoin-lama.sql >> migrasi_data_terbaru.sql
grep -i "^INSERT INTO \`prestasi\`" db-epoin-lama.sql >> migrasi_data_terbaru.sql

# Ekstrak semua tabel absensi
grep -i "^INSERT INTO \`absensi_" db-epoin-lama.sql >> migrasi_data_terbaru.sql

# Ekstrak log
grep -i "^INSERT INTO \`audit_log\`" db-epoin-lama.sql >> migrasi_data_terbaru.sql
grep -i "^INSERT INTO \`log_aktivitas\`" db-epoin-lama.sql >> migrasi_data_terbaru.sql
grep -i "^INSERT INTO \`sp_log\`" db-epoin-lama.sql >> migrasi_data_terbaru.sql

# Ubah INSERT INTO menjadi INSERT IGNORE INTO agar aman jika data sudah ada
sed -i 's/INSERT INTO/INSERT IGNORE INTO/g' migrasi_data_terbaru.sql

echo "SET FOREIGN_KEY_CHECKS=1;" >> migrasi_data_terbaru.sql

echo "Selesai mengekstrak ke migrasi_data_terbaru.sql"
