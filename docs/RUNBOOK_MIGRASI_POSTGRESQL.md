# Panduan Runbook: Migrasi & Cutover Database SIAKAD ke PostgreSQL 16

Dokumen ini merupakan panduan standar operasional (SOP) tim teknis untuk memigrasikan database sistem informasi akademik (**SIAKAD**) dari MySQL/MariaDB ke **PostgreSQL 16** pada environment Staging dan Production.

---

## 1. Persiapan Pra-Cutover (Pre-Migration Checklist)

Sebelum jadwal maintenance cutover dimulai, pastikan poin-poin berikut telah tervalidasi:

- [ ] **Ketersediaan Database PostgreSQL 16**:
  - Service PostgreSQL 16 aktif dan dapat diakses dari host/container aplikasi.
  - Database baru telah dibuat, misal: `db_simak`.
  - User memiliki hak akses penuh (`ALL PRIVILEGES ON DATABASE db_simak TO ...`).
  - Encoding database diset ke `UTF8` dengan collate `id_ID.utf8` atau `C.UTF-8`.
- [ ] **Backup Penuh Database MySQL Eksisting**:
  ```bash
  mysqldump -u root -p \
    --default-character-set=utf8mb4 \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    db_simak_mysql > backup_siakad_mysql_$(date +%Y%m%d_%H%M%S).sql
  ```
- [ ] **Verifikasi Integritas Backup**:
  - Periksa ukuran file dump (`ls -lh backup_siakad_mysql_*.sql`).
  - Simpan salinan cadangan di server penyimpanan terpisah.
- [ ] **Persiapan Resource Mesin**:
  - RAM minimum 4GB (direkomendasikan 8GB+ untuk PostgreSQL dengan shared_buffers 2GB).
  - Disk space kosong minimal 3x dari ukuran data eksisting.

---

## 2. Prosedur Eksekusi Cutover

### Langkah 2.1: Aktifkan Mode Maintenance Aplikasi
Hentikan request pengguna baru untuk mencegah penulisan data transaksi selama proses migrasi:
```bash
php artisan down --secret="siakad-cutover-bypass-key" --render="errors.503"
```

### Langkah 2.2: Buat Skema Baseline PostgreSQL (95 Tabel)
Jalankan migrasi skema PostgreSQL yang telah terstandardisasi dan bersih dari sintaks MySQL:
```bash
php artisan migrate --database=simak_main --force
```
*Catatan: Perintah ini mengeksekusi `database/schema/siakad_main_schema_pgsql.sql` berisi 95 tabel DDL dan 156 indeks performa.*

### Langkah 2.3: Migrasi Data Riil dari MySQL ke PostgreSQL

Tersedia dua opsi penyalinan data:

#### Opsi A (Rekomendasi Utama: Laravel Native ETL)
Menggunakan perintah Artisan internal yang telah dilengkapi progress bar, verifikasi row count, dan chunking memory-safe:
```bash
php artisan siakad:migrate-mysql-to-pgsql \
  --source=mysql \
  --target=simak_main \
  --chunk=2000 \
  --truncate
```

#### Opsi B (Untuk Database Berskala Sangat Besar: pgloader)
Jika data berukuran puluhan gigabyte, gunakan `pgloader`:
```bash
# Sesuaikan kredensial di database/scripts/mysql_to_pgsql.load lalu jalankan:
pgloader database/scripts/mysql_to_pgsql.load
```

### Langkah 2.4: Sinkronisasi Sequence Auto-Increment PostgreSQL
Pastikan seluruh sequence ID sinkron dengan data tertinggi agar tidak terjadi bentrok *duplicate key* saat insert baru:
```bash
php artisan siakad:reset-sequences --connection=simak_main
```

### Langkah 2.5: Pembuatan Akun Admin Awal (Jika Database Baru/Bersih)
Jika migrasi dilakukan pada skema baru tanpa data admin:
```bash
php artisan siakad:bootstrap-admin
```

---

## 3. Checklist Validasi Pasca-Cutover (Smoke Testing)

Lakukan pengujian cepat sebelum membuka kembali akses publik:

1. **Verifikasi Koneksi & Environment**:
   ```bash
   php artisan tinker --execute="echo DB::connection('simak_main')->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME) . ' - ' . DB::connection('simak_main')->table('s1m4k_admin')->count() . ' admin accounts' . PHP_EOL;"
   ```
2. **Uji Otentikasi & Login**:
   - Buka browser menggunakan secret bypass: `https://siakad.domain.ac.id/siakad-cutover-bypass-key`.
   - Login menggunakan akun Administrator dan verifikasi Dashboard.
   - Login menggunakan akun Mahasiswa uji dan pastikan menu KRS/KHS terbuka.
3. **Uji Transaksional Kritis**:
   - Uji coba simpan / setujui 1 KRS contoh.
   - Periksa halaman tagihan pembayaran UKT.
4. **Periksa Log Aplikasi**:
   ```bash
   tail -n 100 storage/logs/laravel.log
   ```
   *Pastikan tidak ada exception SQLSTATE atau error strict group by.*

---

## 4. Peluncuran Kembali Layanan (Go-Live)

Jika seluruh checklist validasi lolos:
```bash
# Bersihkan seluruh cache konfigurasi dan view
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Buka akses aplikasi
php artisan up
```

---

## 5. Prosedur Rollback Darurat (Fallback Plan)

Apabila terjadi kendala kritis yang tidak dapat diselesaikan dalam batas waktu maintenance:

1. **Kembalikan Konfigurasi Driver ke MySQL**:
   Ubah baris konfigurasi pada file `.env`:
   ```dotenv
   SIMAK_DB_DRIVER=mysql
   SIMAK_DB_HOST=127.0.0.1
   SIMAK_DB_PORT=3306
   SIMAK_DB_DATABASE=db_simak_mysql
   SIMAK_DB_USERNAME=user_mysql
   SIMAK_DB_PASSWORD=password_mysql
   ```
2. **Bersihkan Cache Konfigurasi**:
   ```bash
   php artisan config:clear
   ```
3. **Buka Akses Kembali**:
   ```bash
   php artisan up
   ```
4. **Analisis Masalah**:
   Lakukan investigasi log di `storage/logs/laravel.log` sebelum merencanakan jadwal cutover ulang.

---

## 6. Pemeliharaan Rutin PostgreSQL

Untuk menjaga performa database PostgreSQL tetap prima:

- **Autovacuum & Reindex Berkala**:
  ```sql
  -- Dijalankan setiap akhir semester / pasca bulk import PDDIKTI Feeder:
  VACUUM ANALYZE;
  REINDEX DATABASE db_simak;
  ```
- **Backup Harian Otomatis (`cron`)**:
  ```bash
  pg_dump -h 127.0.0.1 -U postgres -F c -b -v -f "/backup/siakad_pgsql_$(date +\%Y\%m\%d).dump" db_simak
  ```
